/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: fb2eeb9560252f439935f1ffa75ba925a23d51f1 */

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_LiteralString_from, 0, 1, IS_STRING, 0)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
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
	class_entry = zend_register_internal_class_with_flags(&ce, NULL, ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES);

	return class_entry;
}
