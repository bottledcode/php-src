#include "zend.h"
#include "zend_API.h"
#include "zend_literal_strings.h"
#include "zend_literal_strings_arginfo.h"
#include "zend_interfaces.h"

ZEND_API zend_class_entry *zend_ce_literal_string;

typedef struct _zend_literal_string zend_literal_string;

static zend_object_handlers zend_literal_string_handlers;

static inline zend_literal_string *literal_string_from_obj(zend_object *obj)
{
    return (zend_literal_string *)((char *)(obj) - XtOffsetOf(zend_literal_string, std));
}

static zend_object *zend_literal_string_object_create(zend_class_entry *ce)
{
    zend_literal_string *literalString = emalloc(sizeof(zend_literal_string));
    memset(literalString, 0, sizeof(zend_literal_string));

    zend_object_std_init(&literalString->std, ce);
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

void zend_register_literal_string_ce(void)
{
    zend_ce_literal_string = register_class_LiteralString(zend_ce_stringable);
    zend_ce_literal_string->create_object = zend_literal_string_object_create;
    zend_ce_literal_string->default_object_handlers = &zend_literal_string_handlers;

    zend_literal_string_handlers = std_object_handlers;
    // todo: override object handlers
}


