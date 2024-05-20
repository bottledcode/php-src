#ifndef ZEND_LITERAL_STRING_H
#define ZEND_LITERAL_STRING_H

#include "zend_API.h"
#include "zend_types.h"

BEGIN_EXTERN_C()

struct _zend_literal_string {
	/* The literal string value */
	zend_string base;
};

void zend_register_literal_string_ce(void);
void create_literal_string_from_string(zend_string *val, zval *retVal);

END_EXTERN_C()

#endif