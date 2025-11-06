/*
   +----------------------------------------------------------------------+
   | Zend Engine                                                          |
   +----------------------------------------------------------------------+
   | Copyright (c) Zend Technologies Ltd. (http://www.zend.com)           |
   +----------------------------------------------------------------------+
   | This source file is subject to version 2.00 of the Zend license,     |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.zend.com/license/2_00.txt.                                |
   | If you did not receive a copy of the Zend license and are unable to  |
   | obtain it through the world-wide-web, please send a note to          |
   | license@zend.com so we can mail you a copy immediately.              |
   +----------------------------------------------------------------------+
*/

#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"
#include "zend_struct.h"
#include "zend_struct_arginfo.h"
#include "zend_objects.h"
#include "zend_object_handlers.h"
#include "zend_attributes.h"

ZEND_API zend_object_handlers zend_struct_object_handlers;

/* Constructor for Struct\Unsafe attribute */
static ZEND_METHOD(Struct_Unsafe, __construct)
{
	ZEND_PARSE_PARAMETERS_NONE();
}

/* Register the Struct\Unsafe attribute class */
static zend_class_entry *register_class_Struct_Unsafe(void)
{
	zend_class_entry ce, *class_entry;
	static const zend_function_entry class_Struct_Unsafe_methods[] = {
		ZEND_ME(Struct_Unsafe, __construct, arginfo_class_Struct_Unsafe___construct, ZEND_ACC_PUBLIC)
		ZEND_FE_END
	};

	INIT_NS_CLASS_ENTRY(ce, "Struct", "Unsafe", class_Struct_Unsafe_methods);
	class_entry = zend_register_internal_class(&ce);
	class_entry->ce_flags |= ZEND_ACC_FINAL;

	/* Add #[Attribute(Attribute::TARGET_METHOD)] to the class */
	zend_string *attribute_name = zend_string_init_interned("Attribute", sizeof("Attribute") - 1, 1);
	zend_attribute *attribute = zend_add_class_attribute(class_entry, attribute_name, 1);
	zend_string_release(attribute_name);
	ZVAL_LONG(&attribute->args[0].value, ZEND_ATTRIBUTE_TARGET_METHOD);

	return class_entry;
}

/* Initialize struct class entry with proper handlers and restrictions */
void zend_struct_init(zend_class_entry *ce)
{
	ZEND_ASSERT(ce->ce_flags & ZEND_ACC_STRUCT);

	/* Structs are always final */
	ce->ce_flags |= ZEND_ACC_FINAL;

	/* Structs cannot have dynamic properties */
	ce->ce_flags |= ZEND_ACC_NO_DYNAMIC_PROPERTIES;

	/* Use custom object handlers for COW behavior */
	ce->default_object_handlers = &zend_struct_object_handlers;
}

/* Verify struct constraints during compilation */
void zend_verify_struct(const zend_class_entry *ce)
{
	ZEND_ASSERT(ce->ce_flags & ZEND_ACC_STRUCT);

	/* Structs cannot extend other classes */
	if (ce->parent != NULL) {
		zend_error_noreturn(E_COMPILE_ERROR,
			"Struct %s cannot extend class %s",
			ZSTR_VAL(ce->name),
			ZSTR_VAL(ce->parent->name));
	}

	/* Structs must be final */
	if (!(ce->ce_flags & ZEND_ACC_FINAL)) {
		zend_error_noreturn(E_COMPILE_ERROR,
			"Struct %s must be final",
			ZSTR_VAL(ce->name));
	}
}

/* Clone a struct object for copy-on-write */
ZEND_API zend_object *zend_struct_clone_obj(zend_object *old_object)
{
	ZEND_ASSERT(zend_object_is_struct(old_object));

	zend_class_entry *ce = old_object->ce;
	zend_object *new_object = zend_objects_new(ce);

	/* Initialize all properties to UNDEF */
	if (ce->default_properties_count) {
		zval *p = new_object->properties_table;
		zval *end = p + ce->default_properties_count;
		do {
			ZVAL_UNDEF(p);
			p++;
		} while (p != end);
	}

	/* Copy all property values */
	zend_objects_clone_members(new_object, old_object);

	return new_object;
}

/* Separate struct for copy-on-write if refcount > 1 */
ZEND_API zend_object *zend_struct_separate(zend_object *obj)
{
	ZEND_ASSERT(zend_object_is_struct(obj));

	/* If refcount is 1, we can modify in-place */
	if (GC_REFCOUNT(obj) == 1) {
		return obj;
	}

	/* Refcount > 1, need to clone */
	GC_DELREF(obj);
	return zend_struct_clone_obj(obj);
}

/* Allocate a new struct handle wrapping an object */
ZEND_API zend_struct_handle *zend_struct_handle_alloc(zend_object *obj)
{
	ZEND_ASSERT(zend_object_is_struct(obj));

	zend_struct_handle *handle = emalloc(sizeof(zend_struct_handle));
	GC_SET_REFCOUNT(handle, 1);
	GC_TYPE_INFO(handle) = GC_STRUCT;
	handle->obj = obj;
	/* Each handle owns a reference to the object it points to */
	/* When called from object creation, obj already has refcount=1, no need to increment */
	/* When called from zval_copy_ctor_func, caller already did GC_ADDREF(obj) */
	return handle;
}

/* Duplicate a struct handle for value semantics (used by zval_copy_ctor) */
ZEND_API zend_struct_handle *zend_struct_handle_dup(zend_struct_handle *old_handle)
{
	/* Create a new handle pointing to the same object */
	zend_struct_handle *new_handle = emalloc(sizeof(zend_struct_handle));
	GC_SET_REFCOUNT(new_handle, 1);
	GC_TYPE_INFO(new_handle) = GC_STRUCT;
	new_handle->obj = old_handle->obj;
	/* New handle needs its own reference to the shared object */
	GC_ADDREF(old_handle->obj);
	return new_handle;
}

/* Free a struct handle */
ZEND_API void zend_struct_handle_free(zend_struct_handle *handle)
{
	if (handle->obj) {
		zend_object *obj = handle->obj;
		handle->obj = NULL;
		OBJ_RELEASE(obj);
	}
	efree(handle);
}

/* Perform COW separation on a struct handle */
ZEND_API void zend_struct_handle_separate(zend_struct_handle *handle)
{
	ZEND_ASSERT(handle != NULL);
	ZEND_ASSERT(handle->obj != NULL);

	/* Only separate if the object has multiple references */
	if (GC_REFCOUNT(handle->obj) > 1) {
		zend_object *old_obj = handle->obj;
		zend_object *new_obj = zend_struct_clone_obj(old_obj);

		/* Update THIS handle to point to new object (which has refcount=1) */
		handle->obj = new_obj;
		/* Handle takes ownership of new_obj's refcount=1, no need to increment */

		/* Release our reference to old object using OBJ_RELEASE */
		/* This will properly handle refcount=0 case if needed */
		OBJ_RELEASE(old_obj);
	}
}

/* Check if we're currently executing within an Unsafe method */
ZEND_API bool zend_is_in_unsafe_method(void)
{
	zend_execute_data *execute_data = EG(current_execute_data);

	if (!execute_data || !execute_data->func) {
		return false;
	}

	zend_function *func = execute_data->func;

	/* Constructors are always allowed to mutate (they need to initialize) */
	if (func->common.function_name &&
	    zend_string_equals_literal_ci(func->common.function_name, "__construct")) {
		return true;
	}

	/* Check if this method has the Struct\Unsafe attribute */
	if (func->common.attributes) {
		zend_attribute *attr;
		ZEND_HASH_PACKED_FOREACH_PTR(func->common.attributes, attr) {
			if (zend_string_equals(attr->name, zend_ce_struct_unsafe->name)) {
				return true;
			}
		} ZEND_HASH_FOREACH_END();
	}

	return false;
}

/* Custom write_property handler for structs */
ZEND_API zval *zend_struct_write_property(
	zend_object *object,
	zend_string *member,
	zval *value,
	void **cache_slot)
{
	ZEND_ASSERT(zend_object_is_struct(object));

	/* Safety/Unsafe checks and COW are handled at the VM level in ZEND_ASSIGN_OBJ */
	/* If we reach here through a different path, just use standard behavior */

	/* Call standard write property handler */
	return zend_std_write_property(object, member, value, cache_slot);
}

/* Initialize struct object handlers */
static void zend_struct_init_object_handlers(void)
{
	/* Start with standard object handlers */
	memcpy(&zend_struct_object_handlers, &std_object_handlers, sizeof(zend_object_handlers));

	/* Override clone handler for COW */
	zend_struct_object_handlers.clone_obj = zend_struct_clone_obj;

	/* Override write property for COW trigger */
	zend_struct_object_handlers.write_property = zend_struct_write_property;
}

/* Module initialization - called during PHP startup */
void zend_register_struct_handlers(void)
{
	zend_struct_init_object_handlers();
}

/* Register Struct\Unsafe attribute - called during attribute registration */
void zend_register_struct_attribute(void)
{
	zend_ce_struct_unsafe = register_class_Struct_Unsafe();
	zend_mark_internal_attribute(zend_ce_struct_unsafe);
	/* No validator needed for Struct\Unsafe */
}
