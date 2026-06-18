/*
   +----------------------------------------------------------------------+
   | Zend OPcache                                                         |
   +----------------------------------------------------------------------+
   | Copyright © The PHP Group and Contributors.                          |
   +----------------------------------------------------------------------+
   | This source file is subject to the Modified BSD License that is      |
   | bundled with this package in the file LICENSE, and is available      |
   | through the World Wide Web at <https://www.php.net/license/>.        |
   |                                                                      |
   | SPDX-License-Identifier: BSD-3-Clause                                |
   +----------------------------------------------------------------------+
   | Authors: Dmitry Stogov <dmitry@php.net>                              |
   +----------------------------------------------------------------------+
*/

#include "Optimizer/zend_optimizer.h"
#include "Optimizer/zend_optimizer_internal.h"
#include "zend_API.h"
#include "zend_constants.h"
#include "zend_execute.h"
#include "zend_vm.h"
#include "zend_bitset.h"
#include "zend_cfg.h"
#include "zend_ssa.h"
#include "zend_func_info.h"
#include "zend_call_graph.h"
#include "zend_inference.h"
#include "zend_dump.h"
#include "zend_inheritance.h"

#ifndef ZEND_DEBUG_DFA
# define ZEND_DEBUG_DFA ZEND_DEBUG
#endif

#if ZEND_DEBUG_DFA
# include "ssa_integrity.c"
#endif

zend_result zend_dfa_analyze_op_array(zend_op_array *op_array, zend_optimizer_ctx *ctx, zend_ssa *ssa)
{
	uint32_t build_flags;

	if (op_array->last_try_catch) {
		/* TODO: we can't analyze functions with try/catch/finally ??? */
		return FAILURE;
	}

    /* Build SSA */
	memset(ssa, 0, sizeof(zend_ssa));

	zend_build_cfg(&ctx->arena, op_array, ZEND_CFG_NO_ENTRY_PREDECESSORS, &ssa->cfg);

	if ((ssa->cfg.flags & ZEND_FUNC_INDIRECT_VAR_ACCESS)) {
		/* TODO: we can't analyze functions with indirect variable access ??? */
		return FAILURE;
	}

	zend_cfg_build_predecessors(&ctx->arena, &ssa->cfg);

	if (ctx->debug_level & ZEND_DUMP_DFA_CFG) {
		zend_dump_op_array(op_array, ZEND_DUMP_CFG, "dfa cfg", &ssa->cfg);
	}

	/* Compute Dominators Tree */
	zend_cfg_compute_dominators_tree(op_array, &ssa->cfg);

	/* Identify reducible and irreducible loops */
	zend_cfg_identify_loops(op_array, &ssa->cfg);

	if (ctx->debug_level & ZEND_DUMP_DFA_DOMINATORS) {
		zend_dump_dominators(op_array, &ssa->cfg);
	}

	build_flags = 0;
	if (ctx->debug_level & ZEND_DUMP_DFA_LIVENESS) {
		build_flags |= ZEND_SSA_DEBUG_LIVENESS;
	}
	if (ctx->debug_level & ZEND_DUMP_DFA_PHI) {
		build_flags |= ZEND_SSA_DEBUG_PHI_PLACEMENT;
	}
	if (zend_build_ssa(&ctx->arena, ctx->script, op_array, build_flags, ssa) == FAILURE) {
		return FAILURE;
	}

	if (ctx->debug_level & ZEND_DUMP_DFA_SSA) {
		zend_dump_op_array(op_array, ZEND_DUMP_SSA, "dfa ssa", ssa);
	}


	zend_ssa_compute_use_def_chains(&ctx->arena, op_array, ssa);

	zend_ssa_find_false_dependencies(op_array, ssa);

	zend_ssa_find_sccs(op_array, ssa);

	if (zend_ssa_inference(&ctx->arena, op_array, ctx->script, ssa, ctx->optimization_level) == FAILURE) {
		return FAILURE;
	}

	if (zend_ssa_escape_analysis(ctx->script, op_array, ssa) == FAILURE) {
		return FAILURE;
	}

	if (ctx->debug_level & ZEND_DUMP_DFA_SSA_VARS) {
		zend_dump_ssa_variables(op_array, ssa, 0);
	}

	return SUCCESS;
}

static void zend_ssa_remove_nops(zend_op_array *op_array, zend_ssa *ssa, zend_optimizer_ctx *ctx)
{
	zend_basic_block *blocks = ssa->cfg.blocks;
	zend_basic_block *blocks_end = blocks + ssa->cfg.blocks_count;
	zend_basic_block *b;
	zend_func_info *func_info;
	int j;
	uint32_t i = 0;
	uint32_t target = 0;
	uint32_t *shiftlist;
	ALLOCA_FLAG(use_heap);

	shiftlist = (uint32_t *)do_alloca(sizeof(uint32_t) * op_array->last, use_heap);
	memset(shiftlist, 0, sizeof(uint32_t) * op_array->last);
	/* remove empty callee_info */
	func_info = ZEND_FUNC_INFO(op_array);
	if (func_info) {
		zend_call_info **call_info = &func_info->callee_info;
		while ((*call_info)) {
			if ((*call_info)->caller_init_opline->opcode == ZEND_NOP) {
				*call_info = (*call_info)->next_callee;
			} else {
				call_info = &(*call_info)->next_callee;
			}
		}
	}

	for (b = blocks; b < blocks_end; b++) {
		if (b->flags & (ZEND_BB_REACHABLE|ZEND_BB_UNREACHABLE_FREE)) {
			if (b->len) {
				uint32_t new_start, old_end;
				while (i < b->start) {
					shiftlist[i] = i - target;
					i++;
				}

				if (b->flags & ZEND_BB_UNREACHABLE_FREE) {
					/* Only keep the FREE for the loop var */
					ZEND_ASSERT(op_array->opcodes[b->start].opcode == ZEND_FREE
							|| op_array->opcodes[b->start].opcode == ZEND_FE_FREE);
					b->len = 1;
				}

				new_start = target;
				old_end = b->start + b->len;
				while (i < old_end) {
					shiftlist[i] = i - target;
					if (EXPECTED(op_array->opcodes[i].opcode != ZEND_NOP)) {
						if (i != target) {
							op_array->opcodes[target] = op_array->opcodes[i];
							ssa->ops[target] = ssa->ops[i];
							ssa->cfg.map[target] = b - blocks;
						}
						target++;
					}
					i++;
				}
				b->start = new_start;
				if (target != old_end) {
					zend_op *opline;
					zend_op *new_opline;

					b->len = target - b->start;
					opline = op_array->opcodes + old_end - 1;
					if (opline->opcode == ZEND_NOP) {
						continue;
					}

					new_opline = op_array->opcodes + target - 1;
					zend_optimizer_migrate_jump(op_array, new_opline, opline);
				}
			} else {
				b->start = target;
			}
		} else {
			b->start = target;
			b->len = 0;
		}
	}

	if (target != op_array->last) {
		/* reset rest opcodes */
		for (i = target; i < op_array->last; i++) {
			MAKE_NOP(op_array->opcodes + i);
		}

		/* update SSA variables */
		for (j = 0; j < ssa->vars_count; j++) {
			if (ssa->vars[j].definition >= 0) {
				ssa->vars[j].definition -= shiftlist[ssa->vars[j].definition];
			}
			if (ssa->vars[j].use_chain >= 0) {
				ssa->vars[j].use_chain -= shiftlist[ssa->vars[j].use_chain];
			}
		}
		for (i = 0; i < op_array->last; i++) {
			if (ssa->ops[i].op1_use_chain >= 0) {
				ssa->ops[i].op1_use_chain -= shiftlist[ssa->ops[i].op1_use_chain];
			}
			if (ssa->ops[i].op2_use_chain >= 0) {
				ssa->ops[i].op2_use_chain -= shiftlist[ssa->ops[i].op2_use_chain];
			}
			if (ssa->ops[i].res_use_chain >= 0) {
				ssa->ops[i].res_use_chain -= shiftlist[ssa->ops[i].res_use_chain];
			}
		}

		/* update branch targets */
		for (b = blocks; b < blocks_end; b++) {
			if ((b->flags & ZEND_BB_REACHABLE) && b->len != 0) {
				zend_op *opline = op_array->opcodes + b->start + b->len - 1;
				zend_optimizer_shift_jump(op_array, opline, shiftlist);
			}
		}

		/* update try/catch array */
		for (uint32_t j = 0; j < op_array->last_try_catch; j++) {
			op_array->try_catch_array[j].try_op -= shiftlist[op_array->try_catch_array[j].try_op];
			op_array->try_catch_array[j].catch_op -= shiftlist[op_array->try_catch_array[j].catch_op];
			if (op_array->try_catch_array[j].finally_op) {
				op_array->try_catch_array[j].finally_op -= shiftlist[op_array->try_catch_array[j].finally_op];
				op_array->try_catch_array[j].finally_end -= shiftlist[op_array->try_catch_array[j].finally_end];
			}
		}

		/* update call graph */
		if (func_info) {
			zend_call_info *call_info = func_info->callee_info;
			while (call_info) {
				call_info->caller_init_opline -=
					shiftlist[call_info->caller_init_opline - op_array->opcodes];
				if (call_info->caller_call_opline) {
					call_info->caller_call_opline -=
						shiftlist[call_info->caller_call_opline - op_array->opcodes];
				}
				call_info = call_info->next_callee;
			}
		}

		op_array->last = target;
	}
	free_alloca(shiftlist, use_heap);
}

static bool safe_instanceof(const zend_class_entry *ce1, const zend_class_entry *ce2) {
	if (ce1 == ce2) {
		return true;
	}
	if (!(ce1->ce_flags & ZEND_ACC_LINKED)) {
		/* This case could be generalized, similarly to unlinked_instanceof */
		return false;
	}
	return instanceof_function(ce1, ce2);
}

static inline bool can_elide_list_type(
	const zend_script *script, const zend_op_array *op_array,
	const zend_ssa_var_info *use_info, const zend_type type)
{
	const zend_type *single_type;
	/* For intersection: result==false is failure, default is success.
	 * For union: result==true is success, default is failure. */
	bool is_intersection = ZEND_TYPE_IS_INTERSECTION(type);
	ZEND_TYPE_FOREACH(type, single_type) {
		if (ZEND_TYPE_HAS_LIST(*single_type)) {
			ZEND_ASSERT(!is_intersection);
			return can_elide_list_type(script, op_array, use_info, *single_type);
		}
		if (ZEND_TYPE_HAS_NAME(*single_type)) {
			zend_string *lcname = zend_string_tolower(ZEND_TYPE_NAME(*single_type));
			const zend_class_entry *ce = zend_optimizer_get_class_entry(script, op_array, lcname);
			zend_string_release(lcname);
			bool result = ce && safe_instanceof(use_info->ce, ce);
			if (result == !is_intersection) {
				return result;
			}
		}
	} ZEND_TYPE_FOREACH_END();
	return is_intersection;
}

static inline bool can_elide_return_type_check(
		const zend_script *script, zend_op_array *op_array, zend_ssa *ssa, zend_ssa_op *ssa_op) {
	zend_arg_info *arg_info = &op_array->arg_info[-1];
	zend_ssa_var_info *use_info = &ssa->var_info[ssa_op->op1_use];
	uint32_t use_type = use_info->type & (MAY_BE_ANY|MAY_BE_UNDEF);
	if (use_type & MAY_BE_REF) {
		return false;
	}
	/* When the declared return type contains a generic parameter, the
	 * compile-time arg_info is the erased type (typically mixed) but the
	 * effective type on a monomorph is the substituted one — eliding the
	 * VERIFY_RETURN_TYPE here would skip the per-monomorph runtime check. */
	if (op_array->generic_types && op_array->generic_types->return_type) {
		return false;
	}

	if (use_type & MAY_BE_UNDEF) {
		use_type &= ~MAY_BE_UNDEF;
		use_type |= MAY_BE_NULL;
	}

	uint32_t disallowed_types = use_type & ~ZEND_TYPE_PURE_MASK(arg_info->type);
	if (!disallowed_types) {
		/* Only contains allowed types. */
		return true;
	}

	if (disallowed_types == MAY_BE_OBJECT && use_info->ce && ZEND_TYPE_IS_COMPLEX(arg_info->type)) {
		return can_elide_list_type(script, op_array, use_info, arg_info->type);
	}

	return false;
}

static bool opline_supports_assign_contraction(
		zend_op_array *op_array, zend_ssa *ssa, zend_op *opline, int src_var, uint32_t cv_var) {
	if (opline->opcode == ZEND_NEW) {
		/* see Zend/tests/generators/aborted_yield_during_new.phpt */
		return false;
	}

	/* Frameless calls override the return value, but the return value may overlap with the arguments. */
	switch (opline->opcode) {
		case ZEND_FRAMELESS_ICALL_3:
			if ((opline + 1)->op1_type == IS_CV && (opline + 1)->op1.var == cv_var) return false;
			ZEND_FALLTHROUGH;
		case ZEND_FRAMELESS_ICALL_2:
			if (opline->op2_type == IS_CV && opline->op2.var == cv_var) return false;
			ZEND_FALLTHROUGH;
		case ZEND_FRAMELESS_ICALL_1:
			if (opline->op1_type == IS_CV && opline->op1.var == cv_var) return false;
			return true;
	}

	if (opline->opcode == ZEND_DO_ICALL || opline->opcode == ZEND_DO_UCALL
			|| opline->opcode == ZEND_DO_FCALL || opline->opcode == ZEND_DO_FCALL_BY_NAME) {
		/* Function calls may dtor the return value after it has already been written -- allow
		 * direct assignment only for types where a double-dtor does not matter. */
		uint32_t type = ssa->var_info[src_var].type;
		uint32_t simple = MAY_BE_NULL|MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_LONG|MAY_BE_DOUBLE;
		return !((type & MAY_BE_ANY) & ~simple);
	}

	if (opline->opcode == ZEND_POST_INC || opline->opcode == ZEND_POST_DEC) {
		/* POST_INC/DEC write the result variable before performing the inc/dec. For $i = $i++
		 * eliding the temporary variable would thus yield an incorrect result. */
		return opline->op1_type != IS_CV || opline->op1.var != cv_var;
	}

	if (opline->opcode == ZEND_INIT_ARRAY) {
		/* INIT_ARRAY initializes the result array before reading key/value. */
		return (opline->op1_type != IS_CV || opline->op1.var != cv_var)
			&& (opline->op2_type != IS_CV || opline->op2.var != cv_var);
	}

	if (opline->opcode == ZEND_CAST
			&& (opline->extended_value == IS_ARRAY || opline->extended_value == IS_OBJECT)) {
		/* CAST to array/object may initialize the result to an empty array/object before
		 * reading the expression. */
		return opline->op1_type != IS_CV || opline->op1.var != cv_var;
	}

	if ((opline->opcode == ZEND_ASSIGN_OP
	  || opline->opcode == ZEND_ASSIGN_OBJ
	  || opline->opcode == ZEND_ASSIGN_DIM
	  || opline->opcode == ZEND_ASSIGN_OBJ_OP
	  || opline->opcode == ZEND_ASSIGN_DIM_OP)
	 && opline->op1_type == IS_CV
	 && opline->op1.var == cv_var
	 && zend_may_throw(opline, &ssa->ops[ssa->vars[src_var].definition], op_array, ssa)) {
		return false;
	}

	return true;
}

static bool variable_defined_or_used_in_range(zend_ssa *ssa, int var, int start, int end)
{
	while (start < end) {
		const zend_ssa_op *ssa_op = &ssa->ops[start];
		if ((ssa_op->op1_def >= 0 && ssa->vars[ssa_op->op1_def].var == var) ||
			(ssa_op->op2_def >= 0 && ssa->vars[ssa_op->op2_def].var == var) ||
			(ssa_op->result_def >= 0 && ssa->vars[ssa_op->result_def].var == var) ||
			(ssa_op->op1_use >= 0 && ssa->vars[ssa_op->op1_use].var == var) ||
			(ssa_op->op2_use >= 0 && ssa->vars[ssa_op->op2_use].var == var) ||
			(ssa_op->result_use >= 0 && ssa->vars[ssa_op->result_use].var == var)
		) {
			return true;
		}
		start++;
	}
	return false;
}

/* SSA arg types reflect the call-site value, not the declared type, so inferring T from them is sound. */
static bool zend_dfa_send_concrete_type(
		const zend_op_array *op_array, const zend_ssa *ssa,
		const zend_op *send, zend_type *out)
{
	/* By-ref / unusual sends: the callee could observe a reference; skip. */
	if (send->opcode == ZEND_SEND_REF
			|| send->opcode == ZEND_SEND_VAR_NO_REF
			|| send->opcode == ZEND_SEND_VAR_NO_REF_EX
			|| send->opcode == ZEND_SEND_USER) {
		return false;
	}

	if (send->op1_type == IS_CONST) {
		const zval *zv = CT_CONSTANT_EX(op_array, send->op1.constant);
		switch (Z_TYPE_P(zv)) {
			case IS_LONG:   *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_LONG, 0, 0);   return true;
			case IS_DOUBLE: *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_DOUBLE, 0, 0); return true;
			case IS_STRING: *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_STRING, 0, 0); return true;
			default:        return false;
		}
	}

	int var = ssa->ops[send - op_array->opcodes].op1_use;
	if (var < 0) {
		return false;
	}
	const zend_ssa_var_info *info = &ssa->var_info[var];
	if (info->type & (MAY_BE_UNDEF | MAY_BE_REF)) {
		return false;
	}
	uint32_t pure = info->type & MAY_BE_ANY;
	switch (pure) {
		case MAY_BE_LONG:   *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_LONG, 0, 0);   return true;
		case MAY_BE_DOUBLE: *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_DOUBLE, 0, 0); return true;
		case MAY_BE_STRING: *out = (zend_type) ZEND_TYPE_INIT_CODE(IS_STRING, 0, 0); return true;
	}
	/* Exact class only: !is_instanceof matches what runtime inference binds T to. */
	if (pure == MAY_BE_OBJECT && info->ce && !info->is_instanceof) {
		*out = (zend_type) ZEND_TYPE_INIT_CLASS(zend_string_copy(info->ce->name), 0, 0);
		return true;
	}
	return false;
}

static zend_op *zend_dfa_find_call_verify(zend_op_array *op_array, const zend_call_info *call_info)
{
	if (!call_info->caller_call_opline || !call_info->caller_init_opline) {
		return NULL;
	}
	zend_op *p = call_info->caller_call_opline;
	while (p > call_info->caller_init_opline) {
		p--;
		if (p->opcode == ZEND_NOP || p->opcode == ZEND_EXT_NOP
				|| p->opcode == ZEND_EXT_FCALL_BEGIN) {
			continue;
		}
		/* VERIFY extended_value 0 = speculative non-turbofish site, != 0 = turbofish; INSTALL always turbofish. */
		if ((p->opcode == ZEND_VERIFY_GENERIC_ARGUMENTS
				|| p->opcode == ZEND_INSTALL_GENERIC_ARGS)
				&& p->op1_type == IS_UNUSED) {
			return p;
		}
		return NULL;
	}
	return NULL;
}

static bool zend_dfa_try_direct_dispatch(zend_op_array *op_array,
		const zend_call_info *ci, zend_op *site, const zend_function *fbc)
{
	if (!op_array->generic_types || !op_array->generic_types->turbofish_args) {
		return false;
	}
	zend_op *init = ci->caller_init_opline;
	if (!init || (init->opcode != ZEND_INIT_FCALL
			&& init->opcode != ZEND_INIT_FCALL_BY_NAME
			&& init->opcode != ZEND_INIT_NS_FCALL_BY_NAME)) {
		return false;
	}
	zend_turbofish_args_entry *entry = zend_hash_index_find_ptr(
		op_array->generic_types->turbofish_args, site->extended_value);
	if (!entry || !ZEND_TYPE_HAS_NAMED_WITH_ARGS(entry->args_box)) {
		return false;
	}
	const zend_type_named_with_args *nwa = ZEND_TYPE_NAMED_WITH_ARGS(entry->args_box);
	for (uint32_t i = 0; i < nwa->count; i++) {
		if (zend_type_contains_type_parameter(nwa->args[i])) {
			return false;
		}
	}

	zend_string *mangled = zend_generic_canonical_class_name(
		fbc->common.function_name, nwa->args, nwa->count);
	zend_string *lc = zend_string_tolower(mangled);
	zend_string_hash_val(mangled);
	zend_string_hash_val(lc);
	zval zv;
	ZVAL_STR(&zv, mangled);
	uint32_t lit = zend_optimizer_add_literal(op_array, &zv);  /* orig name */
	ZVAL_STR(&zv, lc);
	zend_optimizer_add_literal(op_array, &zv);                 /* lc name at lit+1 */

	init->opcode = ZEND_INIT_FCALL_BY_NAME;
	init->op1_type = IS_UNUSED;
	init->op1.num = 0;
	init->op2_type = IS_CONST;
	init->op2.constant = lit;
	/* result.num keeps the existing 1-slot function cache. */
	MAKE_NOP(site);
	return true;
}

/* On a final class the called scope is always one of its own monomorphs, so static ≡ the own-params turbofish. */
static uint32_t zend_dfa_selfize_generic_new(zend_op_array *op_array)
{
	zend_class_entry *scope = op_array->scope;
	if (!scope || !scope->generic_parameters
			|| !(scope->ce_flags & ZEND_ACC_FINAL)
			|| !op_array->generic_types || !op_array->generic_types->turbofish_args) {
		return 0;
	}
	uint32_t pcount = scope->generic_parameters->count;
	uint32_t changed = 0;
	for (uint32_t i = 0; i < op_array->last; i++) {
		zend_op *verify = &op_array->opcodes[i];
		if (verify->opcode != ZEND_VERIFY_GENERIC_ARGUMENTS
				|| verify->op1_type == IS_UNUSED
				|| verify->extended_value == 0) {
			continue;
		}
		zend_op *newop = NULL;
		for (uint32_t j = i; j > 0; j--) {
			zend_op *p = &op_array->opcodes[j - 1];
			if (p->opcode == ZEND_NEW
					&& p->result_type == IS_TMP_VAR
					&& p->result.var == verify->op1.var) {
				newop = p;
				break;
			}
		}
		if (!newop || newop->op1_type != IS_CONST) {
			continue;
		}
		zend_string *cname = Z_STR(op_array->literals[newop->op1.constant]);
		if (!zend_string_equals_ci(cname, scope->name)) {
			continue;
		}
		zend_turbofish_args_entry *entry = zend_hash_index_find_ptr(
			op_array->generic_types->turbofish_args, verify->extended_value);
		if (!entry || !ZEND_TYPE_HAS_NAMED_WITH_ARGS(entry->args_box)) {
			continue;
		}
		const zend_type_named_with_args *nwa = ZEND_TYPE_NAMED_WITH_ARGS(entry->args_box);
		if (nwa->count != pcount) {
			continue;
		}
		bool identity = true;
		for (uint32_t a = 0; a < nwa->count; a++) {
			if (!ZEND_TYPE_HAS_TYPE_PARAMETER(nwa->args[a])
					|| (ZEND_TYPE_FULL_MASK(nwa->args[a]) & MAY_BE_NULL)) {
				identity = false;
				break;
			}
			const zend_type_parameter_ref *ref = ZEND_TYPE_TYPE_PARAMETER(nwa->args[a]);
			if (ref->origin != ZEND_GENERIC_ORIGIN_CLASS_LIKE || ref->index != a) {
				identity = false;
				break;
			}
		}
		if (!identity) {
			continue;
		}
		newop->op1_type = IS_UNUSED;
		newop->op1.num = ZEND_FETCH_CLASS_STATIC | ZEND_FETCH_CLASS_EXCEPTION;
		newop->op2_type = IS_UNUSED;
		newop->op2.num = 0;
		MAKE_NOP(verify);
		changed++;
	}
	return changed;
}

static uint32_t zend_dfa_optimize_generic_calls(zend_op_array *op_array, zend_ssa *ssa)
{
	const zend_func_info *func_info = ZEND_FUNC_INFO(op_array);
	uint32_t changed = 0;

	if (!func_info || !func_info->callee_info) {
		return 0;
	}

	for (const zend_call_info *ci = func_info->callee_info; ci; ci = ci->next_callee) {
		const zend_function *fbc = ci->callee_func;
		if (!fbc || ci->is_prototype || ci->named_args || ci->send_unpack) {
			continue;
		}
		if (!ZEND_USER_CODE(fbc->common.type)) {
			continue;
		}
		const zend_generic_parameter_list *params = fbc->op_array.generic_parameters;
		if (!params || params->count == 0) {
			continue;
		}

		zend_op *verify = zend_dfa_find_call_verify(op_array, ci);
		if (!verify) {
			continue;
		}

		/* Turbofish site needs no value inference, so runs even for callees with no inferable params. */
		if (verify->extended_value != 0) {
			if (zend_dfa_try_direct_dispatch(op_array, ci, verify, fbc)) {
				changed++;
				continue;
			}
			uint8_t opcode = zend_generic_try_install_resolved_turbofish(
				op_array, fbc, verify->extended_value, verify->op2.num);
			if (opcode != ZEND_NOP) {
				verify->opcode = opcode;
				changed++;
			}
			continue;
		}

		if (params->inferable_mask == 0
				|| !fbc->op_array.generic_types || !fbc->op_array.generic_types->parameters) {
			continue;
		}

		uint32_t total = params->count;
		uint32_t required = 0;
		while (required < total
				&& !ZEND_TYPE_IS_SET(params->parameters[required].default_type)) {
			required++;
		}
		if (total > ZEND_GENERIC_MAX_PARAMS) {
			continue;
		}

		/* Bare top-level T only, matching zend_build_generic_call_type_args. */
		int arg_pos_for_gp[ZEND_GENERIC_MAX_PARAMS];
		for (uint32_t i = 0; i < total; i++) {
			arg_pos_for_gp[i] = -1;
		}
		HashTable *pre = fbc->op_array.generic_types->parameters;
		zend_ulong arg_idx;
		zend_type *pe;
		ZEND_HASH_FOREACH_NUM_KEY_PTR(pre, arg_idx, pe) {
			if (!ZEND_TYPE_HAS_TYPE_PARAMETER(*pe)) continue;
			if (ZEND_TYPE_FULL_MASK(*pe) & MAY_BE_NULL) continue;
			const zend_type_parameter_ref *ref = ZEND_TYPE_TYPE_PARAMETER(*pe);
			if (ref->origin != ZEND_GENERIC_ORIGIN_FUNCTION_LIKE) continue;
			if (ref->index >= total) continue;
			if (arg_pos_for_gp[ref->index] == -1 && arg_idx < ci->num_args) {
				arg_pos_for_gp[ref->index] = (int) arg_idx;
			}
		} ZEND_HASH_FOREACH_END();

		zend_type inferred[ZEND_GENERIC_MAX_PARAMS];
		uint32_t arity = 0;
		for (uint32_t g = 0; g < total; g++) {
			int pos = arg_pos_for_gp[g];
			if (pos < 0 || !ci->arg_info[pos].opline) {
				break;
			}
			zend_type entry;
			if (!zend_dfa_send_concrete_type(op_array, ssa, ci->arg_info[pos].opline, &entry)) {
				break;
			}
			inferred[arity++] = entry;
		}
		/* arity 0 would underflow ZEND_TYPE_NAMED_WITH_ARGS_SIZE; stay generic. */
		if (arity == 0 || arity < required) {
			for (uint32_t i = 0; i < arity; i++) {
				zend_type_release(inferred[i], /* persistent */ false);
			}
			continue;
		}

		uint32_t args_id = 0;
		uint8_t opcode = zend_generic_install_inferred_call(
			op_array, fbc, inferred, arity, &args_id);
		if (opcode == ZEND_NOP) {
			continue;
		}
		verify->opcode = opcode;
		verify->op2.num = arity;
		verify->extended_value = args_id;
		zend_dfa_try_direct_dispatch(op_array, ci, verify, fbc);
		changed++;
	}

	return changed;
}

static uint32_t zend_aot_rewrite_new_sites(zend_op_array *op_array)
{
	if (!op_array->generic_types || !op_array->generic_types->turbofish_args
			|| !op_array->generic_types->monomorph_type_args) {
		return 0;
	}
	const zend_type_arg_table *binds = op_array->generic_types->monomorph_type_args;
	if (binds->count > ZEND_GENERIC_MAX_PARAMS) {
		return 0;
	}
	zend_type bindv[ZEND_GENERIC_MAX_PARAMS];
	uint32_t bindc = binds->count;
	for (uint32_t i = 0; i < bindc; i++) {
		const zend_type *t = zend_type_arg_entry_type(&binds->entries[i]);
		bindv[i] = t ? *t : (zend_type) ZEND_TYPE_INIT_NONE(0);
	}

	uint32_t changed = 0;
	for (uint32_t i = 0; i < op_array->last; i++) {
		zend_op *verify = &op_array->opcodes[i];
		if (verify->opcode != ZEND_VERIFY_GENERIC_ARGUMENTS
				|| verify->op1_type == IS_UNUSED
				|| verify->extended_value == 0) {
			continue;
		}
		zend_op *newop = NULL;
		for (uint32_t j = i; j > 0; j--) {
			zend_op *p = &op_array->opcodes[j - 1];
			if (p->opcode == ZEND_NEW
					&& p->result_type == IS_TMP_VAR
					&& p->result.var == verify->op1.var) {
				newop = p;
				break;
			}
		}
		if (!newop || newop->op1_type != IS_CONST) {
			continue; /* self/static/dynamic base: leave the runtime VERIFY. */
		}

		zend_turbofish_args_entry *entry = zend_hash_index_find_ptr(
			op_array->generic_types->turbofish_args, verify->extended_value);
		if (!entry || !ZEND_TYPE_HAS_NAMED_WITH_ARGS(entry->args_box)) {
			continue;
		}
		const zend_type_named_with_args *nwa = ZEND_TYPE_NAMED_WITH_ARGS(entry->args_box);
		if (nwa->count == 0 || nwa->count > ZEND_GENERIC_MAX_PARAMS) {
			continue;
		}
		zend_type resolved[ZEND_GENERIC_MAX_PARAMS];
		bool ok = true;
		for (uint32_t a = 0; a < nwa->count; a++) {
			zend_type r = bindc
				? zend_substitute_function_type_param(nwa->args[a], bindv, bindc)
				: nwa->args[a];
			if (zend_type_contains_type_parameter(r)) {
				ok = false;
				break;
			}
			resolved[a] = r;
		}
		if (!ok) {
			continue;
		}

		zend_string *base_name = Z_STR(op_array->literals[newop->op1.constant]);
		zend_string *canonical = zend_generic_canonical_class_name(
			base_name, resolved, nwa->count);
		zend_string *lc = zend_string_tolower(canonical);
		zend_string_hash_val(canonical);
		zend_string_hash_val(lc);

		zval zv;
		ZVAL_STR(&zv, canonical);
		uint32_t lit = zend_optimizer_add_literal(op_array, &zv);  /* full name */
		ZVAL_STR(&zv, lc);
		zend_optimizer_add_literal(op_array, &zv);                 /* lc at lit+1 */

		newop->op1.constant = lit;     /* keep op2.num cache slot (caches mono ce) */
		MAKE_NOP(verify);
		changed++;
	}
	return changed;
}

/* Per-monomorph ONCE: re-running zend_optimize_script would corrupt SSA. */
static void zend_aot_optimize_monomorph(zend_op_array *op_array, zend_script *script, zend_long opt_level)
{
	if (op_array->last_try_catch) {
		/* dfa bails on try/catch; do the SSA-free class-`new` rewrite, leave the rest generic. */
		zend_revert_pass_two(op_array);
		zend_aot_rewrite_new_sites(op_array);
		zend_redo_pass_two(op_array);
		return;
	}
	zend_optimizer_ctx ctx;
	memset(&ctx, 0, sizeof(ctx));
	ctx.arena = zend_arena_create(64 * 1024);
	ctx.script = script;
	ctx.optimization_level = opt_level;

	zend_func_info *func_info = zend_arena_calloc(&ctx.arena, 1, sizeof(zend_func_info));
	ZEND_SET_FUNC_INFO(op_array, func_info);

	zend_revert_pass_two(op_array);
	zend_aot_rewrite_new_sites(op_array);
	zend_analyze_calls(&ctx.arena, script, 0, op_array, func_info);
	func_info->call_map = zend_build_call_map(&ctx.arena, func_info, op_array);
	if (zend_dfa_analyze_op_array(op_array, &ctx, &func_info->ssa) == SUCCESS) {
		zend_dfa_optimize_op_array(op_array, &ctx, &func_info->ssa, func_info->call_map);
	}
	zend_redo_pass_two(op_array);

	ZEND_SET_FUNC_INFO(op_array, NULL);
	zend_arena_destroy(ctx.arena);
}

/* Returns the case-preserved (+0) name, not the lc one: arg_info needs the original case. */
static zend_string *zend_aot_mangled_call_name(const zend_op_array *op_array, const zend_op *op)
{
	if (op->op2_type != IS_CONST) {
		return NULL;
	}
	zend_string *display;
	if (op->opcode == ZEND_INIT_FCALL_BY_NAME || op->opcode == ZEND_INIT_NS_FCALL_BY_NAME) {
		display = Z_STR_P(RT_CONSTANT(op, op->op2));
	} else {
		return NULL;
	}
	return memchr(ZSTR_VAL(display), '<', ZSTR_LEN(display)) ? display : NULL;
}

/* Synthesizes each direct-dispatch callee; its own calls become the next round's work (fixpoint). */
ZEND_API uint32_t zend_aot_monomorphize_script(zend_script *script, zend_long opt_level)
{
	/* Collect names first: synthesis rehashes function_table mid-iteration. */
	zend_string *wanted[4096];
	uint32_t want_count = 0;
	zend_op_array *op_array;
	/* wanted[] holds display names; table is keyed by lc, so lowercase only for the existence check. */
	#define ZEND_AOT_SCAN_OPS(oa) do { \
		zend_op_array *zoa = (oa); \
		if (zoa->type == ZEND_USER_FUNCTION && zoa->opcodes) { \
			for (uint32_t _i = 0; _i < zoa->last && want_count < (sizeof(wanted)/sizeof(wanted[0])); _i++) { \
				zend_string *_disp = zend_aot_mangled_call_name(zoa, &zoa->opcodes[_i]); \
				if (_disp) { \
					zend_string *_lc = zend_string_tolower(_disp); \
					if (!zend_hash_exists(&script->function_table, _lc)) { \
						wanted[want_count++] = _disp; \
					} \
					zend_string_release(_lc); \
				} \
			} \
		} \
	} while (0)
	ZEND_HASH_MAP_FOREACH_PTR(&script->function_table, op_array) {
		ZEND_AOT_SCAN_OPS(op_array);
	} ZEND_HASH_FOREACH_END();
	zend_class_entry *ce;
	ZEND_HASH_MAP_FOREACH_PTR(&script->class_table, ce) {
		zend_function *m;
		ZEND_HASH_MAP_FOREACH_PTR(&ce->function_table, m) {
			ZEND_AOT_SCAN_OPS(&m->op_array);
		} ZEND_HASH_FOREACH_END();
	} ZEND_HASH_FOREACH_END();
	#undef ZEND_AOT_SCAN_OPS

	zend_op_array *new_monos[4096];
	uint32_t new_count = 0;
	for (uint32_t i = 0; i < want_count; i++) {
		zend_string *lc = zend_string_tolower(wanted[i]);
		bool exists = zend_hash_exists(&script->function_table, lc);
		zend_string_release(lc);
		if (exists) {
			continue; /* synthesized as a duplicate request earlier this round. */
		}
		zend_function *mono = zend_synthesize_specialized_monomorph_by_name(
			&script->function_table, wanted[i]);
		if (mono && new_count < (sizeof(new_monos)/sizeof(new_monos[0]))) {
			new_monos[new_count++] = &mono->op_array;
		}
	}

	for (uint32_t i = 0; i < new_count; i++) {
		zend_aot_optimize_monomorph(new_monos[i], script, opt_level);
	}
	return new_count;
}

/* Upgrade INIT_FCALL_BY_NAME -> INIT_FCALL (+ DO_UCALL): safe since AOT persists the callee. */
static zend_always_inline bool zend_aot_is_call_open(uint8_t opcode)
{
	switch (opcode) {
		case ZEND_INIT_FCALL:
		case ZEND_INIT_FCALL_BY_NAME:
		case ZEND_INIT_NS_FCALL_BY_NAME:
		case ZEND_INIT_METHOD_CALL:
		case ZEND_INIT_STATIC_METHOD_CALL:
		case ZEND_INIT_DYNAMIC_CALL:
		case ZEND_INIT_USER_CALL:
		case ZEND_INIT_PARENT_PROPERTY_HOOK_CALL:
		case ZEND_NEW:
			return true;
		default:
			return false;
	}
}

static uint32_t zend_aot_upgrade_op_array_to_ucall(zend_op_array *op_array, zend_script *script)
{
	if (!op_array->opcodes) {
		return 0;
	}
	/* Nesting stack matches each call-open to its closing DO_FCALL*; fbc resolved at INIT for the SENDs. */
	struct { uint32_t init_i; zend_function *fbc; } stack[512];
	uint32_t sp = 0;
	uint32_t changed = 0;
	for (uint32_t i = 0; i < op_array->last; i++) {
		zend_op *op = &op_array->opcodes[i];
		if (zend_aot_is_call_open(op->opcode)) {
			if (sp >= sizeof(stack)/sizeof(stack[0])) {
				return changed; /* pathological nesting — stop, stay correct. */
			}
			/* Both by-name forms keep the registered lc name at the +1 literal. */
			zend_function *fbc = NULL;
			if ((op->opcode == ZEND_INIT_FCALL_BY_NAME
					|| op->opcode == ZEND_INIT_NS_FCALL_BY_NAME)
					&& op->op2_type == IS_CONST) {
				zend_string *lc = Z_STR_P(RT_CONSTANT(op, op->op2) + 1);
				if (memchr(ZSTR_VAL(lc), '<', ZSTR_LEN(lc))) {
					zend_function *f = zend_hash_find_ptr(&script->function_table, lc);
					if (f && f->type == ZEND_USER_FUNCTION) {
						fbc = f;
					}
				}
			}
			stack[sp].init_i = i;
			stack[sp].fbc = fbc;
			sp++;
			continue;
		}
		if (op->opcode == ZEND_CALLABLE_CONVERT) {
			if (sp > 0) sp--; /* first-class callable closes its frame, no DO. */
			continue;
		}
		if (sp > 0 && stack[sp - 1].fbc
				&& op->opcode == ZEND_SEND_VAR_EX
				&& op->op2_type != IS_CONST
				&& !ARG_SHOULD_BE_SENT_BY_REF(stack[sp - 1].fbc, op->op2.num)) {
			op->opcode = ZEND_SEND_VAR;
			zend_vm_set_opcode_handler(op);
		}
		bool is_do = op->opcode == ZEND_DO_FCALL || op->opcode == ZEND_DO_FCALL_BY_NAME
			|| op->opcode == ZEND_DO_ICALL || op->opcode == ZEND_DO_UCALL;
		if (!is_do || sp == 0) {
			continue;
		}
		zend_function *fbc = stack[sp - 1].fbc;
		zend_op *init = &op_array->opcodes[stack[sp - 1].init_i];
		sp--;
		if (!fbc) {
			continue; /* not a resolved monomorph dispatch site. */
		}
		init->opcode = ZEND_INIT_FCALL;
		init->op1_type = IS_UNUSED;
		init->op1.num = zend_vm_calc_used_stack(init->extended_value, fbc);
		/* INIT_FCALL reads op2 directly: advance one zval to the +1 lc literal it resolves. */
		init->op2.constant += sizeof(zval);
		zend_vm_set_opcode_handler(init);

		/* Skip deprecated/nodiscard (as zend_get_call_op does) so warnings still fire. */
		if (op->opcode == ZEND_DO_FCALL_BY_NAME
				&& !(fbc->common.fn_flags & (ZEND_ACC_DEPRECATED | ZEND_ACC_NODISCARD))) {
			op->opcode = ZEND_DO_UCALL;
			zend_vm_set_opcode_handler(op);
		}
		changed++;
	}
	return changed;
}

ZEND_API uint32_t zend_aot_upgrade_dispatch_to_ucall(zend_script *script)
{
	uint32_t changed = 0;
	zend_op_array *op_array;
	ZEND_HASH_MAP_FOREACH_PTR(&script->function_table, op_array) {
		changed += zend_aot_upgrade_op_array_to_ucall(op_array, script);
	} ZEND_HASH_FOREACH_END();
	zend_class_entry *ce;
	ZEND_HASH_MAP_FOREACH_PTR(&script->class_table, ce) {
		zend_function *m;
		ZEND_HASH_MAP_FOREACH_PTR(&ce->function_table, m) {
			if (m->type == ZEND_USER_FUNCTION) {
				changed += zend_aot_upgrade_op_array_to_ucall(&m->op_array, script);
			}
		} ZEND_HASH_FOREACH_END();
	} ZEND_HASH_FOREACH_END();
	return changed;
}

static uint32_t zend_dfa_optimize_calls(zend_op_array *op_array, zend_ssa *ssa)
{
	const zend_func_info *func_info = ZEND_FUNC_INFO(op_array);
	uint32_t removed_ops = 0;

	if (func_info->callee_info) {
		const zend_call_info *call_info = func_info->callee_info;

		do {
			zend_op *op = call_info->caller_init_opline;

			if ((op->opcode == ZEND_FRAMELESS_ICALL_2
			  || (op->opcode == ZEND_FRAMELESS_ICALL_3 && (op + 1)->op1_type == IS_CONST))
			 && call_info->callee_func
			 && zend_string_equals_literal_ci(call_info->callee_func->common.function_name, "in_array")) {
				bool strict = false;
				bool has_opdata = op->opcode == ZEND_FRAMELESS_ICALL_3;
				ZEND_ASSERT(!call_info->is_prototype);

				if (has_opdata) {
					if (zend_is_true(CT_CONSTANT_EX(op_array, (op + 1)->op1.constant))) {
						strict = true;
					}
				}

				if (op->op2_type == IS_CONST
				 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op->op2.constant)) == IS_ARRAY) {
					bool ok = true;

					const HashTable *src = Z_ARRVAL_P(CT_CONSTANT_EX(op_array, op->op2.constant));
					HashTable *dst;
					zval *val, tmp;
					zend_ulong idx;

					ZVAL_TRUE(&tmp);
					dst = zend_new_array(zend_hash_num_elements(src));
					if (strict) {
						ZEND_HASH_FOREACH_VAL(src, val) {
							if (Z_TYPE_P(val) == IS_STRING) {
								zend_hash_add(dst, Z_STR_P(val), &tmp);
							} else if (Z_TYPE_P(val) == IS_LONG) {
								zend_hash_index_add(dst, Z_LVAL_P(val), &tmp);
							} else {
								zend_array_destroy(dst);
								ok = false;
								break;
							}
						} ZEND_HASH_FOREACH_END();
					} else {
						ZEND_HASH_FOREACH_VAL(src, val) {
							if (Z_TYPE_P(val) != IS_STRING || ZEND_HANDLE_NUMERIC(Z_STR_P(val), idx)) {
								zend_array_destroy(dst);
								ok = false;
								break;
							}
							zend_hash_add(dst, Z_STR_P(val), &tmp);
						} ZEND_HASH_FOREACH_END();
					}

					if (ok) {
						ZVAL_ARR(&tmp, dst);

						/* Update opcode */
						op->opcode = ZEND_IN_ARRAY;
						op->extended_value = strict;
						op->op2.constant = zend_optimizer_add_literal(op_array, &tmp);
						if (has_opdata) {
							MAKE_NOP(op + 1);
							removed_ops++;
						}
					}
				}
			}
			call_info = call_info->next_callee;
		} while (call_info);
	}

	return removed_ops;
}

static zend_always_inline void take_successor_0(zend_ssa *ssa, uint32_t block_num, zend_basic_block *block)
{
	if (block->successors_count == 2) {
		if (block->successors[1] != block->successors[0]) {
			zend_ssa_remove_predecessor(ssa, block_num, block->successors[1]);
		}
		block->successors_count = 1;
	}
}

static zend_always_inline void take_successor_1(zend_ssa *ssa, uint32_t block_num, zend_basic_block *block)
{
	if (block->successors_count == 2) {
		if (block->successors[1] != block->successors[0]) {
			zend_ssa_remove_predecessor(ssa, block_num, block->successors[0]);
			block->successors[0] = block->successors[1];
		}
		block->successors_count = 1;
	}
}

static zend_always_inline void take_successor_ex(zend_ssa *ssa, uint32_t block_num, zend_basic_block *block, int target_block)
{
	for (uint32_t i = 0; i < block->successors_count; i++) {
		if (block->successors[i] != target_block) {
			zend_ssa_remove_predecessor(ssa, block_num, block->successors[i]);
		}
	}
	block->successors[0] = target_block;
	block->successors_count = 1;
}

static void compress_block(zend_op_array *op_array, zend_basic_block *block)
{
	while (block->len > 0) {
		zend_op *opline = &op_array->opcodes[block->start + block->len - 1];

		if (opline->opcode == ZEND_NOP) {
			block->len--;
		} else {
			break;
		}
	}
}

static void replace_predecessor(zend_ssa *ssa, int block_id, int old_pred, int new_pred) {
	zend_basic_block *block = &ssa->cfg.blocks[block_id];
	int *predecessors = &ssa->cfg.predecessors[block->predecessor_offset];
	zend_ssa_phi *phi;

	int old_pred_idx = -1;
	int new_pred_idx = -1;
	for (uint32_t i = 0; i < block->predecessors_count; i++) {
		if (predecessors[i] == old_pred) {
			old_pred_idx = i;
		}
		if (predecessors[i] == new_pred) {
			new_pred_idx = i;
		}
	}

	ZEND_ASSERT(old_pred_idx != -1);
	if (new_pred_idx == -1) {
		/* If the new predecessor doesn't exist yet, simply rewire the old one */
		predecessors[old_pred_idx] = new_pred;
	} else {
		/* Otherwise, rewiring the old predecessor would make the new predecessor appear
		 * twice, which violates our CFG invariants. Remove the old predecessor instead. */
		memmove(
			predecessors + old_pred_idx,
			predecessors + old_pred_idx + 1,
			sizeof(int) * (block->predecessors_count - old_pred_idx - 1)
		);

		/* Also remove the corresponding phi node entries */
		for (phi = ssa->blocks[block_id].phis; phi; phi = phi->next) {
			if (phi->pi >= 0) {
				if (phi->pi == old_pred || phi->pi == new_pred) {
					zend_ssa_rename_var_uses(
						ssa, phi->ssa_var, phi->sources[0], /* update_types */ 0);
					zend_ssa_remove_phi(ssa, phi);
				}
			} else {
				memmove(
					phi->sources + old_pred_idx,
					phi->sources + old_pred_idx + 1,
					sizeof(int) * (block->predecessors_count - old_pred_idx - 1)
				);
			}
		}

		block->predecessors_count--;
	}
}

static void zend_ssa_replace_control_link(zend_op_array *op_array, zend_ssa *ssa, int from, int to, int new_to)
{
	zend_basic_block *src = &ssa->cfg.blocks[from];
	zend_basic_block *old = &ssa->cfg.blocks[to];
	zend_basic_block *dst = &ssa->cfg.blocks[new_to];
	zend_op *opline;

	for (uint32_t i = 0; i < src->successors_count; i++) {
		if (src->successors[i] == to) {
			src->successors[i] = new_to;
		}
	}

	if (src->len > 0) {
		opline = op_array->opcodes + src->start + src->len - 1;
		switch (opline->opcode) {
			case ZEND_JMP:
			case ZEND_FAST_CALL:
				ZEND_ASSERT(ZEND_OP1_JMP_ADDR(opline) == op_array->opcodes + old->start);
				ZEND_SET_OP_JMP_ADDR(opline, opline->op1, op_array->opcodes + dst->start);
				break;
			case ZEND_JMPZ:
			case ZEND_JMPNZ:
			case ZEND_JMPZ_EX:
			case ZEND_JMPNZ_EX:
			case ZEND_FE_RESET_R:
			case ZEND_FE_RESET_RW:
			case ZEND_JMP_SET:
			case ZEND_COALESCE:
			case ZEND_ASSERT_CHECK:
			case ZEND_JMP_NULL:
			case ZEND_BIND_INIT_STATIC_OR_JMP:
			case ZEND_JMP_FRAMELESS:
				if (ZEND_OP2_JMP_ADDR(opline) == op_array->opcodes + old->start) {
					ZEND_SET_OP_JMP_ADDR(opline, opline->op2, op_array->opcodes + dst->start);
				}
				break;
			case ZEND_CATCH:
				if (!(opline->extended_value & ZEND_LAST_CATCH)) {
					if (ZEND_OP2_JMP_ADDR(opline) == op_array->opcodes + old->start) {
						ZEND_SET_OP_JMP_ADDR(opline, opline->op2, op_array->opcodes + dst->start);
					}
				}
				break;
			case ZEND_FE_FETCH_R:
			case ZEND_FE_FETCH_RW:
				if (ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, opline->extended_value) == old->start) {
					opline->extended_value = ZEND_OPLINE_NUM_TO_OFFSET(op_array, opline, dst->start);
				}
				break;
			case ZEND_SWITCH_LONG:
			case ZEND_SWITCH_STRING:
			case ZEND_MATCH:
				{
					HashTable *jumptable = Z_ARRVAL(ZEND_OP2_LITERAL(opline));
					zval *zv;
					ZEND_HASH_FOREACH_VAL(jumptable, zv) {
						if (ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, Z_LVAL_P(zv)) == old->start) {
							Z_LVAL_P(zv) = ZEND_OPLINE_NUM_TO_OFFSET(op_array, opline, dst->start);
						}
					} ZEND_HASH_FOREACH_END();
					if (ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, opline->extended_value) == old->start) {
						opline->extended_value = ZEND_OPLINE_NUM_TO_OFFSET(op_array, opline, dst->start);
					}
					break;
				}
		}
	}

	replace_predecessor(ssa, new_to, to, from);
}

static void zend_ssa_unlink_block(zend_op_array *op_array, zend_ssa *ssa, zend_basic_block *block, uint32_t block_num)
{
	if (block->predecessors_count == 1 && ssa->blocks[block_num].phis == NULL) {
		int *predecessors;
		zend_basic_block *fe_fetch_block = NULL;

		ZEND_ASSERT(block->successors_count == 1);
		predecessors = &ssa->cfg.predecessors[block->predecessor_offset];
		if (block->predecessors_count == 1 && (block->flags & ZEND_BB_FOLLOW)) {
			zend_basic_block *pred_block = &ssa->cfg.blocks[predecessors[0]];

			if (pred_block->len > 0 && (pred_block->flags & ZEND_BB_REACHABLE)) {
				if ((op_array->opcodes[pred_block->start + pred_block->len - 1].opcode == ZEND_FE_FETCH_R
				 || op_array->opcodes[pred_block->start + pred_block->len - 1].opcode == ZEND_FE_FETCH_RW)
				  && op_array->opcodes[pred_block->start + pred_block->len - 1].op2_type == IS_CV) {
					fe_fetch_block = pred_block;
			    }
			}
		}
		for (uint32_t i = 0; i < block->predecessors_count; i++) {
			zend_ssa_replace_control_link(op_array, ssa, predecessors[i], block_num, block->successors[0]);
		}
		zend_ssa_remove_block(op_array, ssa, block_num);
		if (fe_fetch_block && fe_fetch_block->successors[0] == fe_fetch_block->successors[1]) {
			/* The body of "foreach" loop was removed */
			int ssa_var = ssa->ops[fe_fetch_block->start + fe_fetch_block->len - 1].op2_def;
			if (ssa_var >= 0) {
				zend_ssa_remove_uses_of_var(ssa, ssa_var);
			}
		}
	}
}

static int zend_dfa_optimize_jmps(zend_op_array *op_array, zend_ssa *ssa)
{
	int removed_ops = 0;
	uint32_t block_num = 0;

	for (block_num = 1; block_num < ssa->cfg.blocks_count; block_num++) {
		zend_basic_block *block = &ssa->cfg.blocks[block_num];

		if (!(block->flags & ZEND_BB_REACHABLE)) {
			continue;
		}
		compress_block(op_array, block);
		if (block->len == 0) {
			zend_ssa_unlink_block(op_array, ssa, block, block_num);
		}
	}

	block_num = 0;
	while (block_num < ssa->cfg.blocks_count
		&& !(ssa->cfg.blocks[block_num].flags & ZEND_BB_REACHABLE)) {
		block_num++;
	}
	while (block_num < ssa->cfg.blocks_count) {
		uint32_t next_block_num = block_num + 1;
		zend_basic_block *block = &ssa->cfg.blocks[block_num];
		uint32_t op_num;
		zend_op *opline;
		zend_ssa_op *ssa_op;
		bool can_follow = true;

		while (next_block_num < ssa->cfg.blocks_count
			&& !(ssa->cfg.blocks[next_block_num].flags & ZEND_BB_REACHABLE)) {
			if (ssa->cfg.blocks[next_block_num].flags & ZEND_BB_UNREACHABLE_FREE) {
				can_follow = false;
			}
			next_block_num++;
		}

		if (block->len) {
			op_num = block->start + block->len - 1;
			opline = op_array->opcodes + op_num;
			ssa_op = ssa->ops + op_num;

			switch (opline->opcode) {
				case ZEND_JMP:
optimize_jmp:
					if (block->successors[0] == next_block_num && can_follow) {
						MAKE_NOP(opline);
						removed_ops++;
						goto optimize_nop;
					}
					break;
				case ZEND_JMPZ:
optimize_jmpz:
					if (opline->op1_type == IS_CONST) {
						if (zend_is_true(CT_CONSTANT_EX(op_array, opline->op1.constant))) {
							MAKE_NOP(opline);
							removed_ops++;
							take_successor_1(ssa, block_num, block);
							goto optimize_nop;
						} else {
							opline->opcode = ZEND_JMP;
							COPY_NODE(opline->op1, opline->op2);
							take_successor_0(ssa, block_num, block);
							goto optimize_jmp;
						}
					} else {
						if (block->successors[0] == next_block_num && can_follow) {
							take_successor_0(ssa, block_num, block);
							if (opline->op1_type == IS_CV && (OP1_INFO() & MAY_BE_UNDEF)) {
								opline->opcode = ZEND_CHECK_VAR;
								opline->op2.num = 0;
							} else if (opline->op1_type == IS_CV || !(OP1_INFO() & (MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {
								zend_ssa_remove_instr(ssa, opline, ssa_op);
								removed_ops++;
								goto optimize_nop;
							} else {
								opline->opcode = ZEND_FREE;
								opline->op2.num = 0;
							}
						}
					}
					break;
				case ZEND_JMPNZ:
optimize_jmpnz:
					if (opline->op1_type == IS_CONST) {
						if (zend_is_true(CT_CONSTANT_EX(op_array, opline->op1.constant))) {
							opline->opcode = ZEND_JMP;
							COPY_NODE(opline->op1, opline->op2);
							take_successor_0(ssa, block_num, block);
							goto optimize_jmp;
						} else {
							MAKE_NOP(opline);
							removed_ops++;
							take_successor_1(ssa, block_num, block);
							goto optimize_nop;
						}
					} else if (block->successors_count == 2) {
						if (block->successors[0] == next_block_num && can_follow) {
							take_successor_0(ssa, block_num, block);
							if (opline->op1_type == IS_CV && (OP1_INFO() & MAY_BE_UNDEF)) {
								opline->opcode = ZEND_CHECK_VAR;
								opline->op2.num = 0;
							} else if (opline->op1_type == IS_CV || !(OP1_INFO() & (MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {
								zend_ssa_remove_instr(ssa, opline, ssa_op);
								removed_ops++;
								goto optimize_nop;
							} else {
								opline->opcode = ZEND_FREE;
								opline->op2.num = 0;
							}
						}
					}
					break;
				case ZEND_JMPZ_EX:
					if (ssa->vars[ssa_op->result_def].use_chain < 0
							&& ssa->vars[ssa_op->result_def].phi_use_chain == NULL) {
						opline->opcode = ZEND_JMPZ;
						opline->result_type = IS_UNUSED;
						zend_ssa_remove_result_def(ssa, ssa_op);
						goto optimize_jmpz;
					} else if (opline->op1_type == IS_CONST) {
						if (zend_is_true(CT_CONSTANT_EX(op_array, opline->op1.constant))) {
							opline->opcode = ZEND_BOOL;
							take_successor_1(ssa, block_num, block);
						}
					}
					break;
				case ZEND_JMPNZ_EX:
					if (ssa->vars[ssa_op->result_def].use_chain < 0
							&& ssa->vars[ssa_op->result_def].phi_use_chain == NULL) {
						opline->opcode = ZEND_JMPNZ;
						opline->result_type = IS_UNUSED;
						zend_ssa_remove_result_def(ssa, ssa_op);
						goto optimize_jmpnz;
					} else if (opline->op1_type == IS_CONST) {
						if (!zend_is_true(CT_CONSTANT_EX(op_array, opline->op1.constant))) {
							opline->opcode = ZEND_BOOL;
							take_successor_1(ssa, block_num, block);
						}
					}
					break;
				case ZEND_JMP_SET:
					if (ssa->vars[ssa_op->result_def].use_chain < 0
							&& ssa->vars[ssa_op->result_def].phi_use_chain == NULL) {
						opline->opcode = ZEND_JMPNZ;
						opline->result_type = IS_UNUSED;
						zend_ssa_remove_result_def(ssa, ssa_op);
						goto optimize_jmpnz;
					} else if (opline->op1_type == IS_CONST) {
						if (!zend_is_true(CT_CONSTANT_EX(op_array, opline->op1.constant))) {
							MAKE_NOP(opline);
							removed_ops++;
							take_successor_1(ssa, block_num, block);
							zend_ssa_remove_result_def(ssa, ssa_op);
							goto optimize_nop;
						}
					}
					break;
				case ZEND_COALESCE:
				{
					zend_ssa_var *var = &ssa->vars[ssa_op->result_def];
					if (opline->op1_type == IS_CONST
							&& var->use_chain < 0 && var->phi_use_chain == NULL) {
						if (Z_TYPE_P(CT_CONSTANT_EX(op_array, opline->op1.constant)) == IS_NULL) {
							zend_ssa_remove_result_def(ssa, ssa_op);
							MAKE_NOP(opline);
							removed_ops++;
							take_successor_1(ssa, block_num, block);
							goto optimize_nop;
						} else {
							opline->opcode = ZEND_JMP;
							opline->result_type = IS_UNUSED;
							zend_ssa_remove_result_def(ssa, ssa_op);
							COPY_NODE(opline->op1, opline->op2);
							take_successor_0(ssa, block_num, block);
							goto optimize_jmp;
						}
					}
					break;
				}
				case ZEND_JMP_NULL:
				{
					zend_ssa_var *var = &ssa->vars[ssa_op->result_def];
					if (opline->op1_type == IS_CONST
							&& var->use_chain < 0 && var->phi_use_chain == NULL) {
						if (Z_TYPE_P(CT_CONSTANT_EX(op_array, opline->op1.constant)) == IS_NULL) {
							opline->opcode = ZEND_JMP;
							opline->result_type = IS_UNUSED;
							zend_ssa_remove_result_def(ssa, ssa_op);
							COPY_NODE(opline->op1, opline->op2);
							take_successor_0(ssa, block_num, block);
							goto optimize_jmp;
						} else {
							zend_ssa_remove_result_def(ssa, ssa_op);
							MAKE_NOP(opline);
							removed_ops++;
							take_successor_1(ssa, block_num, block);
							goto optimize_nop;
						}
					}
					break;
				}
				case ZEND_SWITCH_LONG:
				case ZEND_SWITCH_STRING:
				case ZEND_MATCH:
					if (opline->op1_type == IS_CONST) {
						zval *zv = CT_CONSTANT_EX(op_array, opline->op1.constant);
						uint8_t type = Z_TYPE_P(zv);
						bool correct_type =
							(opline->opcode == ZEND_SWITCH_LONG && type == IS_LONG)
							|| (opline->opcode == ZEND_SWITCH_STRING && type == IS_STRING)
							|| (opline->opcode == ZEND_MATCH && (type == IS_LONG || type == IS_STRING));

						/* Switch statements have a fallback chain for loose comparison. In those
						 * cases the SWITCH_* instruction is a NOP. Match does strict comparison and
						 * thus jumps to the default branch on mismatched types, so we need to
						 * convert MATCH to a jmp. */
						if (!correct_type && opline->opcode != ZEND_MATCH) {
							removed_ops++;
							MAKE_NOP(opline);
							opline->extended_value = 0;
							take_successor_ex(ssa, block_num, block, block->successors[block->successors_count - 1]);
							goto optimize_nop;
						}

						uint32_t target;
						if (correct_type) {
							HashTable *jmptable = Z_ARRVAL_P(CT_CONSTANT_EX(op_array, opline->op2.constant));
							zval *jmp_zv = type == IS_LONG
								? zend_hash_index_find(jmptable, Z_LVAL_P(zv))
								: zend_hash_find(jmptable, Z_STR_P(zv));

							if (jmp_zv) {
								target = ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, Z_LVAL_P(jmp_zv));
							} else {
								target = ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, opline->extended_value);
							}
						} else {
							ZEND_ASSERT(opline->opcode == ZEND_MATCH);
							target = ZEND_OFFSET_TO_OPLINE_NUM(op_array, opline, opline->extended_value);
						}
						opline->opcode = ZEND_JMP;
						opline->extended_value = 0;
						SET_UNUSED(opline->op1);
						ZEND_SET_OP_JMP_ADDR(opline, opline->op1, op_array->opcodes + target);
						SET_UNUSED(opline->op2);
						take_successor_ex(ssa, block_num, block, ssa->cfg.map[target]);
						goto optimize_jmp;
					}
					break;
				case ZEND_NOP:
optimize_nop:
					compress_block(op_array, block);
					if (block->len == 0) {
						if (block_num > 0) {
							zend_ssa_unlink_block(op_array, ssa, block, block_num);
							/* backtrack to previous basic block */
							int backtracking_block_num = block_num;
							do {
								backtracking_block_num--;
							} while (backtracking_block_num >= 0
								&& !(ssa->cfg.blocks[backtracking_block_num].flags & ZEND_BB_REACHABLE));
							if (backtracking_block_num >= 0) {
								block_num = backtracking_block_num;
								continue;
							}
						}
					}
					break;
				default:
					break;
			}
		}

		block_num = next_block_num;
	}

	return removed_ops;
}

static bool zend_dfa_try_to_replace_result(zend_op_array *op_array, zend_ssa *ssa, int def, int cv_var)
{
	int result_var = ssa->ops[def].result_def;
	uint32_t cv = EX_NUM_TO_VAR(ssa->vars[cv_var].var);

	if (result_var >= 0
	 && !(ssa->var_info[cv_var].type & MAY_BE_REF)
	 && ssa->vars[cv_var].alias == NO_ALIAS
	 && ssa->vars[result_var].phi_use_chain == NULL
	 && ssa->vars[result_var].sym_use_chain == NULL) {
		int use = ssa->vars[result_var].use_chain;

		if (use >= 0
		 && zend_ssa_next_use(ssa->ops, result_var, use) < 0
		 && op_array->opcodes[use].opcode != ZEND_FREE
		 && op_array->opcodes[use].opcode != ZEND_SEND_VAL
		 && op_array->opcodes[use].opcode != ZEND_SEND_VAL_EX
		 && op_array->opcodes[use].opcode != ZEND_VERIFY_RETURN_TYPE
		 && op_array->opcodes[use].opcode != ZEND_YIELD) {
			if (use > def) {
				int i = use;
				const zend_op *opline = &op_array->opcodes[use];

				while (i > def) {
					if ((opline->op1_type == IS_CV && opline->op1.var == cv)
					 || (opline->op2_type == IS_CV && opline->op2.var == cv)
					 || (opline->result_type == IS_CV && opline->result.var == cv)) {
						return false;
					}
					opline--;
					i--;
				}

				/* Update opcodes and reconstruct SSA */
				ssa->vars[result_var].definition = -1;
				ssa->vars[result_var].use_chain = -1;
				ssa->ops[def].result_def = -1;

				op_array->opcodes[def].result_type = IS_UNUSED;
				op_array->opcodes[def].result.var = 0;

				if (ssa->ops[use].op1_use == result_var) {
					ssa->ops[use].op1_use = cv_var;
					ssa->ops[use].op1_use_chain = ssa->vars[cv_var].use_chain;
					ssa->vars[cv_var].use_chain = use;

					op_array->opcodes[use].op1_type = IS_CV;
					op_array->opcodes[use].op1.var = cv;
				} else if (ssa->ops[use].op2_use == result_var) {
					ssa->ops[use].op2_use = cv_var;
					ssa->ops[use].op2_use_chain = ssa->vars[cv_var].use_chain;
					ssa->vars[cv_var].use_chain = use;

					op_array->opcodes[use].op2_type = IS_CV;
					op_array->opcodes[use].op2.var = cv;
				} else if (ssa->ops[use].result_use == result_var) {
					ssa->ops[use].result_use = cv_var;
					ssa->ops[use].res_use_chain = ssa->vars[cv_var].use_chain;
					ssa->vars[cv_var].use_chain = use;

					op_array->opcodes[use].result_type = IS_CV;
					op_array->opcodes[use].result.var = cv;
				}

				return true;
			}
		}
	}

	return false;
}

void zend_dfa_optimize_op_array(zend_op_array *op_array, zend_optimizer_ctx *ctx, zend_ssa *ssa, zend_call_info **call_map)
{
	if (ctx->debug_level & ZEND_DUMP_BEFORE_DFA_PASS) {
		zend_dump_op_array(op_array, ZEND_DUMP_SSA, "before dfa pass", ssa);
	}

	if (ssa->var_info) {
		int op_1;
		int v;
		int remove_nops = 0;
		zend_op *opline;
		zend_ssa_op *ssa_op;
		zval tmp;

#if ZEND_DEBUG_DFA
		ssa_verify_integrity(op_array, ssa, "before dfa");
#endif

		if (ZEND_OPTIMIZER_PASS_8 & ctx->optimization_level) {
			if (sccp_optimize_op_array(ctx, op_array, ssa, call_map)) {
				remove_nops = 1;
			}

			if (zend_dfa_optimize_jmps(op_array, ssa)) {
				remove_nops = 1;
			}

#if ZEND_DEBUG_DFA
			ssa_verify_integrity(op_array, ssa, "after sccp");
#endif
			if (ZEND_FUNC_INFO(op_array)) {
				if (zend_dfa_optimize_calls(op_array, ssa)) {
					remove_nops = 1;
				}
				zend_dfa_optimize_generic_calls(op_array, ssa);
				if (zend_dfa_selfize_generic_new(op_array)) {
					remove_nops = 1;
				}
			}
			if (ctx->debug_level & ZEND_DUMP_AFTER_PASS_8) {
				zend_dump_op_array(op_array, ZEND_DUMP_SSA, "after sccp pass", ssa);
			}
#if ZEND_DEBUG_DFA
			ssa_verify_integrity(op_array, ssa, "after calls");
#endif
		}

		if (ZEND_OPTIMIZER_PASS_14 & ctx->optimization_level) {
			if (dce_optimize_op_array(op_array, ctx, ssa, 0)) {
				remove_nops = 1;
			}
			if (zend_dfa_optimize_jmps(op_array, ssa)) {
				remove_nops = 1;
			}
			if (ctx->debug_level & ZEND_DUMP_AFTER_PASS_14) {
				zend_dump_op_array(op_array, ZEND_DUMP_SSA, "after dce pass", ssa);
			}
#if ZEND_DEBUG_DFA
			ssa_verify_integrity(op_array, ssa, "after dce");
#endif
		}

		for (v = op_array->last_var; v < ssa->vars_count; v++) {

			op_1 = ssa->vars[v].definition;

			if (op_1 < 0) {
				continue;
			}

			opline = op_array->opcodes + op_1;
			ssa_op = &ssa->ops[op_1];

			/* Convert LONG constants to DOUBLE */
			if (ssa->var_info[v].use_as_double) {
				if (opline->opcode == ZEND_ASSIGN
				 && opline->op2_type == IS_CONST
				 && ssa->ops[op_1].op1_def == v
				 && !RETURN_VALUE_USED(opline)
				) {

// op_1: ASSIGN ? -> #v [use_as_double], long(?) => ASSIGN ? -> #v, double(?)

					zval *zv = CT_CONSTANT_EX(op_array, opline->op2.constant);
					ZEND_ASSERT(Z_TYPE_INFO_P(zv) == IS_LONG);
					ZVAL_DOUBLE(&tmp, zval_get_double(zv));
					opline->op2.constant = zend_optimizer_add_literal(op_array, &tmp);

				} else if (opline->opcode == ZEND_QM_ASSIGN
				 && opline->op1_type == IS_CONST
				) {

// op_1: QM_ASSIGN #v [use_as_double], long(?) => QM_ASSIGN #v, double(?)

					zval *zv = CT_CONSTANT_EX(op_array, opline->op1.constant);
					ZEND_ASSERT(Z_TYPE_INFO_P(zv) == IS_LONG);
					ZVAL_DOUBLE(&tmp, zval_get_double(zv));
					opline->op1.constant = zend_optimizer_add_literal(op_array, &tmp);
				}

			} else {
				if (opline->opcode == ZEND_ADD
				 || opline->opcode == ZEND_SUB
				 || opline->opcode == ZEND_MUL
				 || opline->opcode == ZEND_IS_EQUAL
				 || opline->opcode == ZEND_IS_NOT_EQUAL
				 || opline->opcode == ZEND_IS_SMALLER
				 || opline->opcode == ZEND_IS_SMALLER_OR_EQUAL
				) {

					if (opline->op1_type == IS_CONST && opline->op2_type != IS_CONST) {
						zval *zv = CT_CONSTANT_EX(op_array, opline->op1.constant);

						if ((OP2_INFO() & MAY_BE_ANY) == MAY_BE_DOUBLE
						 && Z_TYPE_INFO_P(zv) == IS_LONG) {

// op_1: #v.? = ADD long(?), #?.? [double] => #v.? = ADD double(?), #?.? [double]

							ZVAL_DOUBLE(&tmp, zval_get_double(zv));
							opline->op1.constant = zend_optimizer_add_literal(op_array, &tmp);
							zv = CT_CONSTANT_EX(op_array, opline->op1.constant);
						}
						if (opline->opcode == ZEND_ADD) {
							zv = CT_CONSTANT_EX(op_array, opline->op1.constant);

							if (((OP2_INFO() & (MAY_BE_ANY|MAY_BE_UNDEF)) == MAY_BE_LONG
							  && Z_TYPE_INFO_P(zv) == IS_LONG
							  && Z_LVAL_P(zv) == 0)
							 || ((OP2_INFO() & (MAY_BE_ANY|MAY_BE_UNDEF)) == MAY_BE_DOUBLE
							  && Z_TYPE_INFO_P(zv) == IS_DOUBLE
							  && Z_DVAL_P(zv) == 0.0)) {

// op_1: #v.? = ADD 0, #?.? [double,long] => #v.? = QM_ASSIGN #?.?

								opline->opcode = ZEND_QM_ASSIGN;
								opline->op1_type = opline->op2_type;
								opline->op1.var = opline->op2.var;
								opline->op2_type = IS_UNUSED;
								opline->op2.num = 0;
								ssa->ops[op_1].op1_use = ssa->ops[op_1].op2_use;
								ssa->ops[op_1].op1_use_chain = ssa->ops[op_1].op2_use_chain;
								ssa->ops[op_1].op2_use = -1;
								ssa->ops[op_1].op2_use_chain = -1;
							}
						} else if (opline->opcode == ZEND_MUL
						 && (OP2_INFO() & ((MAY_BE_ANY|MAY_BE_UNDEF)-(MAY_BE_LONG|MAY_BE_DOUBLE))) == 0) {
							zv = CT_CONSTANT_EX(op_array, opline->op1.constant);

							if ((Z_TYPE_INFO_P(zv) == IS_LONG
							  && Z_LVAL_P(zv) == 2)
							 || (Z_TYPE_INFO_P(zv) == IS_DOUBLE
							  && Z_DVAL_P(zv) == 2.0
							  && !(OP2_INFO() & MAY_BE_LONG))) {

// op_1: #v.? = MUL 2, #x.? [double,long] => #v.? = ADD #x.?, #x.?

								opline->opcode = ZEND_ADD;
								opline->op1_type = opline->op2_type;
								opline->op1.var = opline->op2.var;
								ssa->ops[op_1].op1_use = ssa->ops[op_1].op2_use;
								ssa->ops[op_1].op1_use_chain = ssa->ops[op_1].op2_use_chain;
							}
						}
					} else if (opline->op1_type != IS_CONST && opline->op2_type == IS_CONST) {
						zval *zv = CT_CONSTANT_EX(op_array, opline->op2.constant);

						if ((OP1_INFO() & MAY_BE_ANY) == MAY_BE_DOUBLE
						 && Z_TYPE_INFO_P(CT_CONSTANT_EX(op_array, opline->op2.constant)) == IS_LONG) {

// op_1: #v.? = ADD #?.? [double], long(?) => #v.? = ADD #?.? [double], double(?)

							ZVAL_DOUBLE(&tmp, zval_get_double(zv));
							opline->op2.constant = zend_optimizer_add_literal(op_array, &tmp);
							zv = CT_CONSTANT_EX(op_array, opline->op2.constant);
						}
						if (opline->opcode == ZEND_ADD || opline->opcode == ZEND_SUB) {
							if (((OP1_INFO() & (MAY_BE_ANY|MAY_BE_UNDEF)) == MAY_BE_LONG
							  && Z_TYPE_INFO_P(zv) == IS_LONG
							  && Z_LVAL_P(zv) == 0)
							 || ((OP1_INFO() & (MAY_BE_ANY|MAY_BE_UNDEF)) == MAY_BE_DOUBLE
							  && Z_TYPE_INFO_P(zv) == IS_DOUBLE
							  && Z_DVAL_P(zv) == 0.0)) {

// op_1: #v.? = ADD #?.? [double,long], 0 => #v.? = QM_ASSIGN #?.?

								opline->opcode = ZEND_QM_ASSIGN;
								opline->op2_type = IS_UNUSED;
								opline->op2.num = 0;
							}
						} else if (opline->opcode == ZEND_MUL
						 && (OP1_INFO() & ((MAY_BE_ANY|MAY_BE_UNDEF)-(MAY_BE_LONG|MAY_BE_DOUBLE))) == 0) {
							zv = CT_CONSTANT_EX(op_array, opline->op2.constant);

							if ((Z_TYPE_INFO_P(zv) == IS_LONG
							  && Z_LVAL_P(zv) == 2)
							 || (Z_TYPE_INFO_P(zv) == IS_DOUBLE
							  && Z_DVAL_P(zv) == 2.0
							  && !(OP1_INFO() & MAY_BE_LONG))) {

// op_1: #v.? = MUL #x.? [double,long], 2 => #v.? = ADD #x.?, #x.?

								opline->opcode = ZEND_ADD;
								opline->op2_type = opline->op1_type;
								opline->op2.var = opline->op1.var;
								ssa->ops[op_1].op2_use = ssa->ops[op_1].op1_use;
								ssa->ops[op_1].op2_use_chain = ssa->ops[op_1].op1_use_chain;
							}
						}
					}
				} else if (opline->opcode == ZEND_CONCAT) {
					if (!(OP1_INFO() & MAY_BE_OBJECT)
					 && !(OP2_INFO() & MAY_BE_OBJECT)) {
						opline->opcode = ZEND_FAST_CONCAT;
					}
				} else if (opline->opcode == ZEND_VERIFY_RETURN_TYPE
				 && opline->op1_type != IS_CONST
				 && ssa->ops[op_1].op1_def == v
				 && ssa->ops[op_1].op1_use >= 0) {
					int orig_var = ssa->ops[op_1].op1_use;
					int ret = ssa->vars[v].use_chain;

					if (ssa->ops[op_1].op1_use_chain == -1
					 && can_elide_return_type_check(ctx->script, op_array, ssa, &ssa->ops[op_1])) {

// op_1: VERIFY_RETURN_TYPE #orig_var.? [T] -> #v.? [T] => NOP

						zend_ssa_unlink_use_chain(ssa, op_1, orig_var);

						if (ret >= 0) {
							ssa->ops[ret].op1_use = orig_var;
							ssa->ops[ret].op1_use_chain = ssa->vars[orig_var].use_chain;
							ssa->vars[orig_var].use_chain = ret;
						}

						ssa->vars[v].definition = -1;
						ssa->vars[v].use_chain = -1;

						ssa->ops[op_1].op1_def = -1;
						ssa->ops[op_1].op1_use = -1;

						MAKE_NOP(opline);
						remove_nops = 1;
					} else if (ret >= 0
					 && ssa->ops[ret].op1_use == v
					 && ssa->ops[ret].op1_use_chain == -1
					 && can_elide_return_type_check(ctx->script, op_array, ssa, &ssa->ops[op_1])) {

// op_1: VERIFY_RETURN_TYPE #orig_var.? [T] -> #v.? [T] => NOP

						zend_ssa_replace_use_chain(ssa, op_1, ret, orig_var);

						ssa->ops[ret].op1_use = orig_var;
						ssa->ops[ret].op1_use_chain = ssa->ops[op_1].op1_use_chain;

						ssa->vars[v].definition = -1;
						ssa->vars[v].use_chain = -1;

						ssa->ops[op_1].op1_def = -1;
						ssa->ops[op_1].op1_use = -1;

						MAKE_NOP(opline);
						remove_nops = 1;
					}
				}
			}

			if (opline->opcode == ZEND_QM_ASSIGN
			 && ssa->ops[op_1].result_def == v
			 && opline->op1_type & (IS_TMP_VAR|IS_VAR)
			 && !(ssa->var_info[v].type & (MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))
			) {

				int src_var = ssa->ops[op_1].op1_use;

				if (src_var >= 0
				 && !(ssa->var_info[src_var].type & MAY_BE_REF)
				 && (ssa->var_info[src_var].type & (MAY_BE_UNDEF|MAY_BE_ANY))
				 && ssa->vars[src_var].definition >= 0
				 && ssa->ops[ssa->vars[src_var].definition].result_def == src_var
				 && ssa->ops[ssa->vars[src_var].definition].result_use < 0
				 && ssa->vars[src_var].use_chain == op_1
				 && ssa->ops[op_1].op1_use_chain < 0
				 && !ssa->vars[src_var].phi_use_chain
				 && !ssa->vars[src_var].sym_use_chain
				 && opline_supports_assign_contraction(
					 op_array, ssa, &op_array->opcodes[ssa->vars[src_var].definition],
					 src_var, opline->result.var)
				 && !variable_defined_or_used_in_range(ssa, EX_VAR_TO_NUM(opline->result.var),
						ssa->vars[src_var].definition+1, op_1)
				) {

					int orig_var = ssa->ops[op_1].result_use;
					int op_2 = ssa->vars[src_var].definition;

// op_2: #src_var.T = OP ...                                        => #v.CV = OP ...
// op_1: QM_ASSIGN #src_var.T #orig_var.CV [undef,scalar] -> #v.CV,    NOP

					if (orig_var >= 0) {
						zend_ssa_unlink_use_chain(ssa, op_1, orig_var);
					}

					/* Reconstruct SSA */
					ssa->vars[v].definition = op_2;
					ssa->ops[op_2].result_def = v;

					ssa->vars[src_var].definition = -1;
					ssa->vars[src_var].use_chain = -1;

					ssa->ops[op_1].op1_use = -1;
					ssa->ops[op_1].op1_def = -1;
					ssa->ops[op_1].op1_use_chain = -1;
					ssa->ops[op_1].result_use = -1;
					ssa->ops[op_1].result_def = -1;
					ssa->ops[op_1].res_use_chain = -1;

					/* Update opcodes */
					op_array->opcodes[op_2].result_type = opline->result_type;
					op_array->opcodes[op_2].result.var = opline->result.var;

					MAKE_NOP(opline);
					remove_nops = 1;

					if (op_array->opcodes[op_2].opcode == ZEND_SUB
					 && op_array->opcodes[op_2].op1_type == op_array->opcodes[op_2].result_type
					 && op_array->opcodes[op_2].op1.var == op_array->opcodes[op_2].result.var
					 && op_array->opcodes[op_2].op2_type == IS_CONST
					 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == IS_LONG
					 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == 1
					 && ssa->ops[op_2].op1_use >= 0
					 && !(ssa->var_info[ssa->ops[op_2].op1_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

						op_array->opcodes[op_2].opcode = ZEND_PRE_DEC;
						SET_UNUSED(op_array->opcodes[op_2].op2);
						SET_UNUSED(op_array->opcodes[op_2].result);

						ssa->ops[op_2].result_def = -1;
						ssa->ops[op_2].op1_def = v;

					} else if (op_array->opcodes[op_2].opcode == ZEND_ADD
					 && op_array->opcodes[op_2].op1_type == op_array->opcodes[op_2].result_type
					 && op_array->opcodes[op_2].op1.var == op_array->opcodes[op_2].result.var
					 && op_array->opcodes[op_2].op2_type == IS_CONST
					 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == IS_LONG
					 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == 1
					 && ssa->ops[op_2].op1_use >= 0
					 && !(ssa->var_info[ssa->ops[op_2].op1_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

						op_array->opcodes[op_2].opcode = ZEND_PRE_INC;
						SET_UNUSED(op_array->opcodes[op_2].op2);
						SET_UNUSED(op_array->opcodes[op_2].result);

						ssa->ops[op_2].result_def = -1;
						ssa->ops[op_2].op1_def = v;

					} else if (op_array->opcodes[op_2].opcode == ZEND_ADD
					 && op_array->opcodes[op_2].op2_type == op_array->opcodes[op_2].result_type
					 && op_array->opcodes[op_2].op2.var == op_array->opcodes[op_2].result.var
					 && op_array->opcodes[op_2].op1_type == IS_CONST
					 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op1.constant)) == IS_LONG
					 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op1.constant)) == 1
					 && ssa->ops[op_2].op2_use >= 0
					 && !(ssa->var_info[ssa->ops[op_2].op2_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

						op_array->opcodes[op_2].opcode = ZEND_PRE_INC;
						op_array->opcodes[op_2].op1_type = op_array->opcodes[op_2].op2_type;
						op_array->opcodes[op_2].op1.var = op_array->opcodes[op_2].op2.var;
						SET_UNUSED(op_array->opcodes[op_2].op2);
						SET_UNUSED(op_array->opcodes[op_2].result);

						ssa->ops[op_2].result_def = -1;
						ssa->ops[op_2].op1_def = v;
						ssa->ops[op_2].op1_use = ssa->ops[op_2].op2_use;
						ssa->ops[op_2].op1_use_chain = ssa->ops[op_2].op2_use_chain;
						ssa->ops[op_2].op2_use = -1;
						ssa->ops[op_2].op2_use_chain = -1;
					}
				}
			}

			if (ssa->vars[v].var >= op_array->last_var) {
				/* skip TMP and VAR */
				continue;
			}

			if (ssa->ops[op_1].op1_def == v
			 && RETURN_VALUE_USED(opline)) {
				if (opline->opcode == ZEND_ASSIGN
				 || opline->opcode == ZEND_ASSIGN_OP
				 || opline->opcode == ZEND_PRE_INC
				 || opline->opcode == ZEND_PRE_DEC) {
					zend_dfa_try_to_replace_result(op_array, ssa, op_1, v);
				} else if (opline->opcode == ZEND_POST_INC) {
					int result_var = ssa->ops[op_1].result_def;

					if (result_var >= 0
					 && (ssa->var_info[result_var].type & ((MAY_BE_ANY|MAY_BE_REF|MAY_BE_UNDEF) - (MAY_BE_LONG|MAY_BE_DOUBLE))) == 0) {
						int use = ssa->vars[result_var].use_chain;

						if (use >= 0 && op_array->opcodes[use].opcode == ZEND_IS_SMALLER
						 && ssa->ops[use].op1_use == result_var
						 && zend_dfa_try_to_replace_result(op_array, ssa, op_1, v)) {
							opline->opcode = ZEND_PRE_INC;
							op_array->opcodes[use].opcode = ZEND_IS_SMALLER_OR_EQUAL;
						}
					}
				} else if (opline->opcode == ZEND_POST_DEC) {
					int result_var = ssa->ops[op_1].result_def;

					if (result_var >= 0
					 && (ssa->var_info[result_var].type & ((MAY_BE_ANY|MAY_BE_REF|MAY_BE_UNDEF) - (MAY_BE_LONG|MAY_BE_DOUBLE))) == 0) {
						int use = ssa->vars[result_var].use_chain;

						if (use >= 0 && op_array->opcodes[use].opcode == ZEND_IS_SMALLER
						 && ssa->ops[use].op2_use == result_var
						 && zend_dfa_try_to_replace_result(op_array, ssa, op_1, v)) {
							opline->opcode = ZEND_PRE_DEC;
							op_array->opcodes[use].opcode = ZEND_IS_SMALLER_OR_EQUAL;
						}
					}
				}
			}

			if (opline->opcode == ZEND_ASSIGN
			 && ssa->ops[op_1].op1_def == v
			 && !RETURN_VALUE_USED(opline)
			) {
				int orig_var = ssa->ops[op_1].op1_use;

				if (orig_var >= 0
				 && !(ssa->var_info[orig_var].type & (MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))
				) {
					int src_var = ssa->ops[op_1].op2_use;

					if ((opline->op2_type & (IS_TMP_VAR|IS_VAR))
					 && src_var >= 0
					 && !(ssa->var_info[src_var].type & MAY_BE_REF)
					 && (ssa->var_info[src_var].type & (MAY_BE_UNDEF|MAY_BE_ANY))
					 && ssa->vars[src_var].definition >= 0
					 && ssa->ops[ssa->vars[src_var].definition].result_def == src_var
					 && ssa->ops[ssa->vars[src_var].definition].result_use < 0
					 && ssa->vars[src_var].use_chain == op_1
					 && ssa->ops[op_1].op2_use_chain < 0
					 && !ssa->vars[src_var].phi_use_chain
					 && !ssa->vars[src_var].sym_use_chain
					 && opline_supports_assign_contraction(
						 op_array, ssa, &op_array->opcodes[ssa->vars[src_var].definition],
						 src_var, opline->op1.var)
					 && !variable_defined_or_used_in_range(ssa, EX_VAR_TO_NUM(opline->op1.var),
							ssa->vars[src_var].definition+1, op_1)
					) {

						int op_2 = ssa->vars[src_var].definition;

// op_2: #src_var.T = OP ...                                     => #v.CV = OP ...
// op_1: ASSIGN #orig_var.CV [undef,scalar] -> #v.CV, #src_var.T    NOP

						zend_ssa_unlink_use_chain(ssa, op_1, orig_var);
						/* Reconstruct SSA */
						ssa->vars[v].definition = op_2;
						ssa->ops[op_2].result_def = v;

						ssa->vars[src_var].definition = -1;
						ssa->vars[src_var].use_chain = -1;

						ssa->ops[op_1].op1_use = -1;
						ssa->ops[op_1].op2_use = -1;
						ssa->ops[op_1].op1_def = -1;
						ssa->ops[op_1].op1_use_chain = -1;

						/* Update opcodes */
						op_array->opcodes[op_2].result_type = opline->op1_type;
						op_array->opcodes[op_2].result.var = opline->op1.var;

						MAKE_NOP(opline);
						remove_nops = 1;

						if (op_array->opcodes[op_2].opcode == ZEND_SUB
						 && op_array->opcodes[op_2].op1_type == op_array->opcodes[op_2].result_type
						 && op_array->opcodes[op_2].op1.var == op_array->opcodes[op_2].result.var
						 && op_array->opcodes[op_2].op2_type == IS_CONST
						 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == IS_LONG
						 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == 1
						 && ssa->ops[op_2].op1_use >= 0
						 && !(ssa->var_info[ssa->ops[op_2].op1_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

							op_array->opcodes[op_2].opcode = ZEND_PRE_DEC;
							SET_UNUSED(op_array->opcodes[op_2].op2);
							SET_UNUSED(op_array->opcodes[op_2].result);

							ssa->ops[op_2].result_def = -1;
							ssa->ops[op_2].op1_def = v;

						} else if (op_array->opcodes[op_2].opcode == ZEND_ADD
						 && op_array->opcodes[op_2].op1_type == op_array->opcodes[op_2].result_type
						 && op_array->opcodes[op_2].op1.var == op_array->opcodes[op_2].result.var
						 && op_array->opcodes[op_2].op2_type == IS_CONST
						 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == IS_LONG
						 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op2.constant)) == 1
						 && ssa->ops[op_2].op1_use >= 0
						 && !(ssa->var_info[ssa->ops[op_2].op1_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

							op_array->opcodes[op_2].opcode = ZEND_PRE_INC;
							SET_UNUSED(op_array->opcodes[op_2].op2);
							SET_UNUSED(op_array->opcodes[op_2].result);

							ssa->ops[op_2].result_def = -1;
							ssa->ops[op_2].op1_def = v;

						} else if (op_array->opcodes[op_2].opcode == ZEND_ADD
						 && op_array->opcodes[op_2].op2_type == op_array->opcodes[op_2].result_type
						 && op_array->opcodes[op_2].op2.var == op_array->opcodes[op_2].result.var
						 && op_array->opcodes[op_2].op1_type == IS_CONST
						 && Z_TYPE_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op1.constant)) == IS_LONG
						 && Z_LVAL_P(CT_CONSTANT_EX(op_array, op_array->opcodes[op_2].op1.constant)) == 1
						 && ssa->ops[op_2].op2_use >= 0
						 && !(ssa->var_info[ssa->ops[op_2].op2_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

							op_array->opcodes[op_2].opcode = ZEND_PRE_INC;
							op_array->opcodes[op_2].op1_type = op_array->opcodes[op_2].op2_type;
							op_array->opcodes[op_2].op1.var = op_array->opcodes[op_2].op2.var;
							SET_UNUSED(op_array->opcodes[op_2].op2);
							SET_UNUSED(op_array->opcodes[op_2].result);

							ssa->ops[op_2].result_def = -1;
							ssa->ops[op_2].op1_def = v;
							ssa->ops[op_2].op1_use = ssa->ops[op_2].op2_use;
							ssa->ops[op_2].op1_use_chain = ssa->ops[op_2].op2_use_chain;
							ssa->ops[op_2].op2_use = -1;
							ssa->ops[op_2].op2_use_chain = -1;
						}
					} else if (opline->op2_type == IS_CONST
					 || ((opline->op2_type & (IS_TMP_VAR|IS_VAR|IS_CV))
					     && ssa->ops[op_1].op2_use >= 0
					     && ssa->ops[op_1].op2_def < 0)
					) {

// op_1: ASSIGN #orig_var.CV [undef,scalar] -> #v.CV, CONST|TMPVAR => QM_ASSIGN v.CV, CONST|TMPVAR

						if (ssa->ops[op_1].op1_use != ssa->ops[op_1].op2_use) {
							zend_ssa_unlink_use_chain(ssa, op_1, orig_var);
						} else {
							ssa->ops[op_1].op2_use_chain = ssa->ops[op_1].op1_use_chain;
						}

						/* Reconstruct SSA */
						ssa->ops[op_1].result_def = v;
						ssa->ops[op_1].op1_def = -1;
						ssa->ops[op_1].op1_use = ssa->ops[op_1].op2_use;
						ssa->ops[op_1].op1_use_chain = ssa->ops[op_1].op2_use_chain;
						ssa->ops[op_1].op2_use = -1;
						ssa->ops[op_1].op2_use_chain = -1;

						/* Update opcode */
						opline->result_type = opline->op1_type;
						opline->result.var = opline->op1.var;
						opline->op1_type = opline->op2_type;
						opline->op1.var = opline->op2.var;
						opline->op2_type = IS_UNUSED;
						opline->op2.var = 0;
						opline->opcode = ZEND_QM_ASSIGN;
					}
				}

			} else if (opline->opcode == ZEND_ASSIGN_OP
			 && opline->extended_value == ZEND_ADD
			 && ssa->ops[op_1].op1_def == v
			 && opline->op2_type == IS_CONST
			 && Z_TYPE_P(CT_CONSTANT_EX(op_array, opline->op2.constant)) == IS_LONG
			 && Z_LVAL_P(CT_CONSTANT_EX(op_array, opline->op2.constant)) == 1
			 && ssa->ops[op_1].op1_use >= 0
			 && !(ssa->var_info[ssa->ops[op_1].op1_use].type & (MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

// op_1: ASSIGN_ADD #?.CV [undef,null,int,foat] ->#v.CV, int(1) => PRE_INC #?.CV ->#v.CV

				opline->opcode = ZEND_PRE_INC;
				opline->extended_value = 0;
				SET_UNUSED(opline->op2);

			} else if (opline->opcode == ZEND_ASSIGN_OP
			 && opline->extended_value == ZEND_SUB
			 && ssa->ops[op_1].op1_def == v
			 && opline->op2_type == IS_CONST
			 && Z_TYPE_P(CT_CONSTANT_EX(op_array, opline->op2.constant)) == IS_LONG
			 && Z_LVAL_P(CT_CONSTANT_EX(op_array, opline->op2.constant)) == 1
			 && ssa->ops[op_1].op1_use >= 0
			 && !(ssa->var_info[ssa->ops[op_1].op1_use].type & (MAY_BE_UNDEF|MAY_BE_NULL|MAY_BE_FALSE|MAY_BE_TRUE|MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))) {

// op_1: ASSIGN_SUB #?.CV [undef,null,int,foat] -> #v.CV, int(1) => PRE_DEC #?.CV ->#v.CV

				opline->opcode = ZEND_PRE_DEC;
				opline->extended_value = 0;
				SET_UNUSED(opline->op2);

			} else if (ssa->ops[op_1].op1_def == v
			 && !RETURN_VALUE_USED(opline)
			 && ssa->ops[op_1].op1_use >= 0
			 && !(ssa->var_info[ssa->ops[op_1].op1_use].type & (MAY_BE_STRING|MAY_BE_ARRAY|MAY_BE_OBJECT|MAY_BE_RESOURCE|MAY_BE_REF))
			 && opline->opcode == ZEND_ASSIGN_OP
			 && opline->extended_value != ZEND_CONCAT) {

// op_1: ASSIGN_OP #orig_var.CV [undef,null,bool,int,double] -> #v.CV, ? => #v.CV = ADD #orig_var.CV, ?

				/* Reconstruct SSA */
				ssa->ops[op_1].result_def = ssa->ops[op_1].op1_def;
				ssa->ops[op_1].op1_def = -1;

				/* Update opcode */
				opline->opcode = opline->extended_value;
				opline->extended_value = 0;
				opline->result_type = opline->op1_type;
				opline->result.var = opline->op1.var;

			}
		}

#if ZEND_DEBUG_DFA
		ssa_verify_integrity(op_array, ssa, "after dfa");
#endif

		if (remove_nops) {
			zend_ssa_remove_nops(op_array, ssa, ctx);
#if ZEND_DEBUG_DFA
			ssa_verify_integrity(op_array, ssa, "after nop");
#endif
		}
	}

	if (ctx->debug_level & ZEND_DUMP_AFTER_DFA_PASS) {
		zend_dump_op_array(op_array, ZEND_DUMP_SSA, "after dfa pass", ssa);
	}
}

void zend_optimize_dfa(zend_op_array *op_array, zend_optimizer_ctx *ctx)
{
	void *checkpoint = zend_arena_checkpoint(ctx->arena);
	zend_ssa ssa;

	if (zend_dfa_analyze_op_array(op_array, ctx, &ssa) == FAILURE) {
		zend_arena_release(&ctx->arena, checkpoint);
		return;
	}

	zend_dfa_optimize_op_array(op_array, ctx, &ssa, NULL);

	/* Destroy SSA */
	zend_arena_release(&ctx->arena, checkpoint);
}
