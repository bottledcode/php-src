/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: ad4cbcf515c04987b820e97cf268787aca46042c */

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_LiteralString_from, 0, 1, LiteralString, 0)
	ZEND_ARG_OBJ_INFO(0, string, LiteralString, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(LiteralString, from);

static const zend_function_entry class_LiteralString_methods[] = {
	ZEND_ME(LiteralString, from, arginfo_class_LiteralString_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_LiteralString(void)
{
	zend_class_entry ce, *class_entry;

	INIT_CLASS_ENTRY(ce, "LiteralString", class_LiteralString_methods);
	class_entry = zend_register_internal_class_ex(&ce, NULL);
	class_entry->ce_flags |= ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES;

	return class_entry;
}
