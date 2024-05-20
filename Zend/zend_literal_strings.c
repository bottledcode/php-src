#include "zend.h"
#include "zend_API.h"
#include "zend_literal_strings.h"
#include "zend_literal_strings_arginfo.h"
#include "zend_interfaces.h"
#include "TSRM.h"

ZEND_API zend_class_entry *zend_ce_literal_string;

typedef struct _zend_literal_string zend_literal_string;

static zend_object_handlers zend_literal_string_handlers;

static HashTable literal_string_intern_pool;
static MUTEX_T literal_string_mutex;
static int is_initialized = 0;

static void initialize_literal_string_globals(void);
static inline zend_literal_string *literal_string_from_obj(zend_object *obj);
static zend_object *literal_string_get_interned(zend_string *str);

static void initialize_literal_string_globals(void)
{
    if (is_initialized) {
        return;
    }

    zend_hash_init(&literal_string_intern_pool, 0, NULL, ZVAL_PTR_DTOR, 1);
    literal_string_mutex = tsrm_mutex_alloc();
    if (literal_string_mutex == NULL) {
        fprintf(stderr, "Failed to allocate mutex\n");
        abort();
    }
    is_initialized = 1;
}

static void zend_literal_string_free_obj(zend_object *object)
{
	zend_literal_string *literal_string = literal_string_from_obj(object);
	fprintf(stderr, "Freeing zend_literal_string object: %p\n", literal_string);

	if (literal_string->value) {
		if (literal_string_mutex != NULL) {
			tsrm_mutex_lock(literal_string_mutex);
		}

		// Check if the object is still in the intern pool before attempting to delete
		zval *interned = zend_hash_find(&literal_string_intern_pool, literal_string->value);
		if (interned) {
			zend_hash_del(&literal_string_intern_pool, literal_string->value);
			// Release the string only if it was removed from the pool
			zend_string_release(literal_string->value);
		}

		if (literal_string_mutex != NULL) {
			tsrm_mutex_unlock(literal_string_mutex);
		}
	}

	zend_object_std_dtor(&literal_string->std);
	efree(literal_string); // Free the allocated memory
}

static inline zend_literal_string *literal_string_from_obj(zend_object *obj)
{
    return (zend_literal_string *)((char *)(obj) - XtOffsetOf(zend_literal_string, std));
}

static zend_object *zend_literal_string_object_create(zend_class_entry *ce)
{
	zend_literal_string *literalString = emalloc(sizeof(zend_literal_string));
	fprintf(stderr, "Allocated memory for zend_literal_string: %p\n", literalString);
	memset(literalString, 0, sizeof(zend_literal_string));

	zend_object_std_init(&literalString->std, ce);
	object_properties_init(&literalString->std, ce);

	literalString->std.handlers = &zend_literal_string_handlers;

	return &literalString->std;
}

static zend_object *literal_string_get_interned(zend_string *str)
{
	zval *interned;
	zend_literal_string *literal_string;
	zend_object *obj;
	zval tmp;

	initialize_literal_string_globals();

	if (literal_string_mutex != NULL) {
		tsrm_mutex_lock(literal_string_mutex);
	}

	interned = zend_hash_find(&literal_string_intern_pool, zend_string_copy(str));

	if (interned != NULL) {
		// Increment reference count before returning the object
		Z_TRY_ADDREF_P(interned);
		if (literal_string_mutex != NULL) {
			tsrm_mutex_unlock(literal_string_mutex);
		}
		return Z_OBJ_P(interned);
	}

	obj = zend_literal_string_object_create(zend_ce_literal_string);
	literal_string = literal_string_from_obj(obj);
	literal_string->value = zend_string_copy(str);

	ZVAL_OBJ(&tmp, obj);
	Z_TRY_ADDREF(tmp); // Increment reference count before adding to hash table
	zend_hash_add_new(&literal_string_intern_pool, literal_string->value, &tmp);

	if (literal_string_mutex != NULL) {
		tsrm_mutex_unlock(literal_string_mutex);
	}

	return obj;
}

void create_literal_string_from_string(zend_string *val, zval *retVal)
{
	if (ZSTR_IS_INTERNED(val)) {
		// Interned strings should not be manually managed
		ZVAL_STR_COPY(retVal, val);
	} else {
		zend_object *obj = literal_string_get_interned(val);
		ZVAL_OBJ(retVal, obj);
	}
}

ZEND_METHOD(LiteralString, from)
{
    zend_string *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(arg)
    ZEND_PARSE_PARAMETERS_END();

    zend_object *obj = literal_string_get_interned(arg);
    RETURN_OBJ(obj);
}

ZEND_METHOD(LiteralString, __toString)
{
    if (zend_parse_parameters_none() == FAILURE) {
        RETURN_THROWS();
    }

    zend_literal_string *string = (zend_literal_string *) Z_OBJ_P(ZEND_THIS);

    RETURN_STR(zend_string_copy(string->value));
}

ZEND_METHOD(LiteralString, __construct)
{
    zend_string *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(arg)
    ZEND_PARSE_PARAMETERS_END();

    zend_object *obj = literal_string_get_interned(arg);
    ZVAL_OBJ(return_value, obj);
}

int zend_literal_string_compare(zval *obj1, zval *obj2)
{
    zend_literal_string *literal_string1, *literal_string2;

    ZEND_COMPARE_OBJECTS_FALLBACK(obj1, obj2);

    literal_string1 = literal_string_from_obj(Z_OBJ_P(obj1));
    literal_string2 = literal_string_from_obj(Z_OBJ_P(obj2));

    return zendi_smart_strcmp(literal_string1->value, literal_string2->value);
}

static zend_result zend_literal_string_op_literal_string(uint8_t opcode, zval *result, zval *op1, zval *op2)
{
    zend_literal_string *literal_string1;
    zend_literal_string *literal_string2;

    literal_string1 = literal_string_from_obj(Z_OBJ_P(op1));
    literal_string2 = literal_string_from_obj(Z_OBJ_P(op2));

    switch(opcode) {
        case ZEND_ADD:
        case ZEND_SUB:
        case ZEND_MUL:
        case ZEND_POW:
            // These operations are not sensible on strings
            return FAILURE;
        case ZEND_CONCAT:
            // Concatenate the strings
            zend_string *concat_result;

            concat_result = zend_string_concat2(ZSTR_VAL(literal_string1->value), ZSTR_LEN(literal_string1->value),
                                                ZSTR_VAL(literal_string2->value), ZSTR_LEN(literal_string2->value));
            zend_literal_string *new_literal_string = literal_string_from_obj(zend_literal_string_object_create(zend_ce_literal_string));
            new_literal_string->value = concat_result;
            ZVAL_OBJ(result, &new_literal_string->std);
            return SUCCESS;
        default:
            // For other opcode types
            return FAILURE;
    }
}

static zend_result zend_literal_string_op_non_literal(uint8_t opcode, zval *result, zval *op1, zval *op2)
{
    zend_literal_string *literal_string;
    zval actual_op;

    switch (opcode) {
        case ZEND_CONCAT:
            if (Z_TYPE_P(op1) == IS_OBJECT && Z_OBJCE_P(op1) == zend_ce_literal_string) {
                literal_string = literal_string_from_obj(Z_OBJ_P(op1));
                ZVAL_STR(&actual_op, literal_string->value);
                return concat_function(result, &actual_op, op2);
            }

            return FAILURE;
        default:
            return FAILURE;
    }
}

static zend_result zend_literal_string_operation(uint8_t opcode, zval *result, zval *op1, zval *op2)
{
    if (Z_TYPE_P(op1) == IS_OBJECT && Z_OBJCE_P(op1) == zend_ce_literal_string &&
        Z_TYPE_P(op2) == IS_OBJECT && Z_OBJCE_P(op2) == zend_ce_literal_string) {
        return zend_literal_string_op_literal_string(opcode, result, op1, op2);
    } else {
        return zend_literal_string_op_non_literal(opcode, result, op1, op2);
    }
}

void zend_register_literal_string_ce(void)
{
    zend_ce_literal_string = register_class_LiteralString(zend_ce_stringable);
    zend_ce_literal_string->create_object = zend_literal_string_object_create;
    zend_ce_literal_string->default_object_handlers = &zend_literal_string_handlers;

    zend_literal_string_handlers = std_object_handlers;
    zend_literal_string_handlers.offset = XtOffsetOf(zend_literal_string, std);
    zend_literal_string_handlers.compare = zend_literal_string_compare;
    zend_literal_string_handlers.do_operation = zend_literal_string_operation;
    zend_literal_string_handlers.free_obj = zend_literal_string_free_obj;
}

PHP_MINIT_FUNCTION(literal_string)
{
		// Initialization code
		return SUCCESS;
}

PHP_MSHUTDOWN_FUNCTION(literal_string)
{
		if (is_initialized) {
			zend_hash_destroy(&literal_string_intern_pool);
			if (literal_string_mutex != NULL) {
				tsrm_mutex_free(literal_string_mutex);
				literal_string_mutex = NULL;
			}
			is_initialized = 0;
		}
		return SUCCESS;
}