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

#ifndef ZEND_STRUCT_H
#define ZEND_STRUCT_H

#include "zend.h"
#include "zend_API.h"
#include "zend_compile.h"

#include <stdint.h>

BEGIN_EXTERN_C()

extern ZEND_API zend_object_handlers zend_struct_object_handlers;

/* Core struct functions */
void zend_struct_init(zend_class_entry *ce);
void zend_verify_struct(const zend_class_entry *ce);
void zend_register_struct_handlers(void);
void zend_register_struct_attribute(void);

/* Copy-on-write functions */
ZEND_API zend_object *zend_struct_separate(zend_object *obj);
ZEND_API zend_object *zend_struct_clone_obj(zend_object *old_object);

/* Check if currently in an unsafe method context */
ZEND_API bool zend_is_in_unsafe_method(void);

/* Property modification handler (COW trigger) */
ZEND_API zval *zend_struct_write_property(zend_object *object, zend_string *member, zval *value, void **cache_slot);

/* Helper to check if a class is a struct */
static zend_always_inline bool zend_class_is_struct(const zend_class_entry *ce)
{
	return (ce->ce_flags & ZEND_ACC_STRUCT) != 0;
}

/* Helper to check if an object is a struct instance */
static zend_always_inline bool zend_object_is_struct(const zend_object *obj)
{
	return zend_class_is_struct(obj->ce);
}

/* Struct handle functions */
ZEND_API zend_struct_handle *zend_struct_handle_alloc(zend_object *obj);
ZEND_API zend_struct_handle *zend_struct_handle_dup(zend_struct_handle *handle);
ZEND_API void zend_struct_handle_free(zend_struct_handle *handle);
ZEND_API void zend_struct_handle_separate(zend_struct_handle *handle);

/* Macros for working with struct handles in zvals */
#define Z_STRUCT_HANDLE(zval)         (zval).value.sh
#define Z_STRUCT_HANDLE_P(zval_p)     Z_STRUCT_HANDLE(*(zval_p))

#define Z_STRUCT_OBJ(zval)            (Z_STRUCT_HANDLE(zval)->obj)
#define Z_STRUCT_OBJ_P(zval_p)        Z_STRUCT_OBJ(*(zval_p))

#define ZVAL_STRUCT(z, h) do {        \
		zval *__z = (z);              \
		Z_STRUCT_HANDLE_P(__z) = (h); \
		Z_TYPE_INFO_P(__z) = IS_STRUCT_EX; \
	} while (0)

END_EXTERN_C()

#endif /* ZEND_STRUCT_H */
