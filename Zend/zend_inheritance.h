/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright © Zend Technologies Ltd., a subsidiary company of          |
   |     Perforce Software, Inc., and Contributors.                       |
   +----------------------------------------------------------------------+
   | This source file is subject to the Modified BSD License that is      |
   | bundled with this package in the file LICENSE, and is available      |
   | through the World Wide Web at <https://www.php.net/license/>.        |
   |                                                                      |
   | SPDX-License-Identifier: BSD-3-Clause                                |
   +----------------------------------------------------------------------+
   | Authors: Andi Gutmans <andi@php.net>                                 |
   |          Zeev Suraski <zeev@php.net>                                 |
   +----------------------------------------------------------------------+
*/

#ifndef ZEND_INHERITANCE_H
#define ZEND_INHERITANCE_H

#include "zend.h"
#include "zend_compile.h"

BEGIN_EXTERN_C()

typedef enum {
	INHERITANCE_UNRESOLVED = -1,
	INHERITANCE_ERROR = 0,
	INHERITANCE_WARNING = 1,
	INHERITANCE_SUCCESS = 2,
} zend_inheritance_status;

typedef struct _zend_inheritance_binding_cache {
	const zend_class_entry *ce;
	const zend_class_entry *target;
	uint32_t arity;
	bool present;
	bool valid;
	zend_type args[ZEND_GENERIC_MAX_PARAMS];
} zend_inheritance_binding_cache;

ZEND_API void zend_do_implement_interface(zend_class_entry *ce, zend_class_entry *iface);
ZEND_API void zend_do_inheritance_ex(zend_class_entry *ce, zend_class_entry *parent_ce, bool checked);
ZEND_API void zend_type_copy_ctor(zend_type *const type, bool use_arena, bool persistent);
ZEND_API zend_inheritance_status zend_check_generic_arg_satisfies_bound(
		zend_class_entry *arg_scope, zend_type arg,
		zend_class_entry *bound_scope, zend_type bound);
ZEND_API bool zend_get_inheritance_binding_full(
		const zend_class_entry *ce,
		const zend_class_entry *target,
		zend_type *out_args,
		uint32_t out_capacity,
		uint32_t *out_arity);

static zend_always_inline void zend_do_inheritance(zend_class_entry *ce, zend_class_entry *parent_ce) {
	zend_do_inheritance_ex(ce, parent_ce, 0);
}

ZEND_API zend_class_entry *zend_do_link_class(zend_class_entry *ce, zend_string *lc_parent_name, const zend_string *key);

/* Monomorphization: synthesize a generic class application as a real
 * class_entry registered in EG(class_table) under the canonical name. The
 * synthesized class extends `base` with the supplied type args, inheriting
 * all methods/properties/constants with proper substitution.
 *
 * Idempotent: a second call with the same (base, args) canonicalizing to an
 * existing entry returns that entry. Returns NULL on failure (exception will
 * be set). The args array is copied; the caller retains ownership. */
ZEND_API zend_class_entry *zend_synthesize_monomorph(
	zend_class_entry *base, const zend_type *args, uint32_t arity);

/* Same as zend_synthesize_monomorph, but first resolves any TYPE_PARAMETER refs
 * in args[] against the currently executing frame's bindings (function-level
 * via EX()->type_args; class-level via the lexical class's monomorph descendant).
 * Use at runtime `new` sites where the args originate from an op_array side-table
 * compiled inside a generic scope and may name enclosing-scope T's by ref.
 * Static-build callers with already-resolved args keep using the base. */
ZEND_API zend_class_entry *zend_synthesize_monomorph_resolved(
	zend_class_entry *base, const zend_type *args, uint32_t arity);

/* For a bare generic class `base`, synthesize (or return the cached) monomorph
 * built from the parameters' declared defaults. Returns NULL and throws Error
 * if any parameter has no default. If `base` is itself a monomorph (no
 * generic_parameters), returns `base` unchanged. Used by ZEND_NEW for the
 * `new static()` and dynamic `new $name()` paths. */
ZEND_API zend_class_entry *zend_get_defaults_monomorph(zend_class_entry *base);

/* True when the name has monomorph-canonical shape (contains `<`). The canonical
 * encoding for synthesized monomorphs embeds `<...>` in the class name, which is
 * invalid in any user-declared class. Use these helpers rather than open-coding
 * the memchr check so the encoding stays in one place. */
static zend_always_inline bool zend_class_name_is_monomorph(const zend_string *name)
{
	return memchr(ZSTR_VAL(name), '<', ZSTR_LEN(name)) != NULL;
}

static zend_always_inline bool zend_class_is_monomorph(const zend_class_entry *ce)
{
	return zend_class_name_is_monomorph(ce->name);
}

/* Parses a generic-shaped class-name string ("Box<int|null>", etc.), looks
 * up the base class, and synthesizes the monomorph. Returns NULL if the
 * name doesn't have generic shape, the base isn't generic, or parsing fails.
 * Used by `zend_lookup_class_ex` to make unserialize, dynamic
 * `new $name()`, and `class_exists()` all transparently materialize
 * monomorphs on demand. */
ZEND_API zend_class_entry *zend_try_synthesize_monomorph_by_name(
	zend_string *name, uint32_t flags);

ZEND_API zend_type zend_substitute_function_type_param(zend_type t, const zend_type *args, uint32_t arity);

/* Synthesize (or return the cached) concrete specialization of generic function
 * `base` for the given type args, registered in EG(function_table) as
 * `base<arg0,...>`. Returns NULL when args are not concrete or base isn't generic. */
ZEND_API zend_function *zend_synthesize_function_monomorph(
	zend_function *base, const zend_type *args, uint32_t arity);

ZEND_API zend_function *zend_try_synthesize_function_monomorph_by_name(zend_string *lc_name);

void zend_verify_abstract_class(zend_class_entry *ce);
void zend_build_properties_info_table(zend_class_entry *ce);
ZEND_API zend_class_entry *zend_try_early_bind(zend_class_entry *ce, zend_class_entry *parent_ce, zend_string *lcname, zval *delayed_early_binding);

void zend_inheritance_check_override(const zend_class_entry *ce);
void zend_check_generic_variance_markers(zend_class_entry *ce);
void zend_check_function_variance_markers(zend_op_array *op_array);

ZEND_API extern zend_class_entry* (*zend_inheritance_cache_get)(zend_class_entry *ce, zend_class_entry *parent, zend_class_entry **traits_and_interfaces);
ZEND_API extern zend_class_entry* (*zend_inheritance_cache_add)(zend_class_entry *ce, zend_class_entry *proto, zend_class_entry *parent, zend_class_entry **traits_and_interfaces, HashTable *dependencies);

ZEND_API zend_inheritance_status zend_verify_property_hook_variance(const zend_property_info *prop_info, const zend_function *func);
ZEND_API ZEND_COLD ZEND_NORETURN void zend_hooked_property_variance_error(const zend_property_info *prop_info);
ZEND_API ZEND_COLD ZEND_NORETURN void zend_hooked_property_variance_error_ex(zend_string *value_param_name, zend_string *class_name, zend_string *prop_name);
ZEND_API void zend_verify_hooked_property(const zend_class_entry *ce, zend_property_info *prop_info, zend_string *prop_name);

END_EXTERN_C()

#endif
