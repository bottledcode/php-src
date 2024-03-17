#include "zend.h"
#include "zend_API.h"
#include "zend_literal_strings.h"
#include "zend_literal_strings_arginfo.h"
#include "zend_interfaces.h"

ZEND_API zend_class_entry *zend_ce_literal_string;

typedef struct _zend_literal_string zend_literal_string;

static zend_object_handlers zend_literal_string_handlers;

ZEND_METHOD(LiteralString, from)
{
    zend_string *arg;
    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STR(arg)
    ZEND_PARSE_PARAMETERS_END();

    // todo: check that arg is a literal string or throw
    // todo: add a Z_PARAM_STR_LITERAL arg parser

    // todo: return new literal string
    RETURN_TRUE;
}

ZEND_METHOD(LiteralString, __toString)
{
    RETURN_TRUE;
}

static zend_object *zend_literal_string_object_create(zend_class_entry *ce)
{
    zend_literal_string *literalString = emalloc(sizeof(zend_literal_string));
    memset(literalString, 0, sizeof(zend_literal_string));

    zend_object_std_init(&literalString->std, ce);
    return &literalString->std;
}

void zend_register_literal_string_ce(void) {
    zend_ce_literal_string = register_class_LiteralString(zend_ce_stringable);
    zend_ce_literal_string->create_object = zend_literal_string_object_create;
    zend_ce_literal_string->default_object_handlers = &zend_literal_string_handlers;

    zend_literal_string_handlers = std_object_handlers;
    // todo: override object handlers
}


