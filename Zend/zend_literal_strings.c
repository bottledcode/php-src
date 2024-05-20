#include "zend.h"
#include "zend_API.h"
#include "zend_literal_strings.h"
#include "zend_literal_strings_arginfo.h"
#include "zend_interfaces.h"

ZEND_API zend_class_entry *zend_ce_literal_string;

typedef struct _zend_literal_string zend_literal_string;

static zend_object_handlers zend_literal_string_handlers;

void create_literal_string_from_string(zend_string *val, zval *retVal)
{
    struct _zend_literal_string *literalString = emalloc(sizeof(struct _zend_literal_string));
    memset(literalString, 0, sizeof(struct _zend_literal_string));

    zend_object_std_init(&literalString->std, zend_ce_literal_string);
    zend_string_delref(val);
    literalString->value = zend_string_copy(val);

    ZVAL_OBJ(retVal, &literalString->std);
}

static inline zend_literal_string *literal_string_from_obj(zend_object *obj)
{
    return (zend_literal_string *)((char *)(obj) - XtOffsetOf(zend_literal_string, std));
}

static void zend_literal_string_free_obj(zend_object *object) {
    zend_literal_string *literal_string = literal_string_from_obj(object);

    if (literal_string->value) {
        zend_string_release(literal_string->value);
    }

    zend_object_std_dtor(&literal_string->std);
    efree(literal_string);
}

static zend_object *zend_literal_string_object_create(zend_class_entry *ce)
{
    zend_literal_string *literalString = emalloc(sizeof(zend_literal_string));
    memset(literalString, 0, sizeof(zend_literal_string));

    zend_object_std_init(&literalString->std, ce);
    object_properties_init(&literalString->std, ce);

    literalString->std.handlers = &zend_literal_string_handlers;

    return &literalString->std;
}

ZEND_METHOD(LiteralString, from)
{
    zend_string *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(arg)
    ZEND_PARSE_PARAMETERS_END();

    // todo: check that arg is a literal string or throw
    // todo: add a Z_PARAM_STR_LITERAL arg parser

    // todo: return existing
    zend_object *obj = zend_literal_string_object_create(zend_ce_literal_string);
    zend_literal_string *str = literal_string_from_obj(obj);
    str->value = zend_string_copy(arg);
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

    zend_literal_string *string = (zend_literal_string *) Z_OBJ_P(ZEND_THIS);

    string->value = zend_string_copy(arg);
}

int zend_literal_string_compare(zval *obj1, zval *obj2)
{
    zend_literal_string *literal_string1, *literal_string2;

    ZEND_COMPARE_OBJECTS_FALLBACK(obj1, obj2);

    literal_string1 = literal_string_from_obj(Z_OBJ_P(obj1));
    literal_string2 = literal_string_from_obj(Z_OBJ_P(obj2));

    return zendi_smart_strcmp(literal_string1->value, literal_string2->value);
}


static zend_result zend_literal_string_op_literal_string(uint8_t opcode, zval *result, zval *op1, zval *op2) {
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

static zend_result zend_literal_string_op_non_literal(uint8_t opcode, zval *result, zval *op1, zval *op2) {
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
    }
    else {
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