/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 03fbc4938474d6d7bfa6d194b18158e744f6b4e2 */

ZEND_BEGIN_ARG_INFO_EX(arginfo_class_LiteralString___construct, 0, 0, 1)
	ZEND_ARG_TYPE_INFO(0, string, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_OBJ_INFO_EX(arginfo_class_LiteralString_from, 0, 1, LiteralString, 0)
	ZEND_ARG_OBJ_INFO(0, string, LiteralString, 0)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_class_LiteralString___toString, 0, 0, IS_STRING, 0)
ZEND_END_ARG_INFO()

ZEND_METHOD(LiteralString, __construct);
ZEND_METHOD(LiteralString, from);
ZEND_METHOD(LiteralString, __toString);

static const zend_function_entry class_LiteralString_methods[] = {
	ZEND_ME(LiteralString, __construct, arginfo_class_LiteralString___construct, ZEND_ACC_PRIVATE)
	ZEND_ME(LiteralString, from, arginfo_class_LiteralString_from, ZEND_ACC_PUBLIC|ZEND_ACC_STATIC)
	ZEND_ME(LiteralString, __toString, arginfo_class_LiteralString___toString, ZEND_ACC_PUBLIC)
	ZEND_FE_END
};

static zend_class_entry *register_class_LiteralString(zend_class_entry *class_entry_Stringable)
{
	zend_class_entry ce, *class_entry;

	INIT_CLASS_ENTRY(ce, "LiteralString", class_LiteralString_methods);
	class_entry = zend_register_internal_class_ex(&ce, NULL);
	class_entry->ce_flags |= ZEND_ACC_FINAL|ZEND_ACC_NO_DYNAMIC_PROPERTIES;
	zend_class_implements(class_entry, 1, class_entry_Stringable);

	return class_entry;
}
