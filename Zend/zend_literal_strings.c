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
	zend_string *cpy;
	ZEND_PARSE_PARAMETERS_START(1, 1)
			Z_PARAM_STR(arg)
	ZEND_PARSE_PARAMETERS_END();

	cpy = zend_string_copy(arg);
	ZSTR_SET_LITERAL(&cpy);

	RETURN_STR(cpy);
}

void zend_register_literal_string_ce(void)
{
	zend_ce_literal_string = register_class_LiteralString();

	zend_literal_string_handlers = std_object_handlers;
}