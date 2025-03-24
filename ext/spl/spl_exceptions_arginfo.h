/* This is a generated file, edit the .stub.php file instead.
 * Stub hash: 07475caecc81ab3b38a04905f874615af1126289 */

static zend_class_entry *register_class_LogicException(zend_class_entry *class_entry_Exception)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "LogicException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_Exception, 0);

	return class_entry;
}

static zend_class_entry *register_class_BadFunctionCallException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "BadFunctionCallException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}

static zend_class_entry *register_class_BadMethodCallException(zend_class_entry *class_entry_BadFunctionCallException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "BadMethodCallException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_BadFunctionCallException, 0);

	return class_entry;
}

static zend_class_entry *register_class_DomainException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "DomainException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}

static zend_class_entry *register_class_InvalidArgumentException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "InvalidArgumentException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}

static zend_class_entry *register_class_LengthException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "LengthException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}

static zend_class_entry *register_class_OutOfRangeException(zend_class_entry *class_entry_LogicException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "OutOfRangeException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_LogicException, 0);

	return class_entry;
}

static zend_class_entry *register_class_RuntimeException(zend_class_entry *class_entry_Exception)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "RuntimeException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_Exception, 0);

	return class_entry;
}

static zend_class_entry *register_class_OutOfBoundsException(zend_class_entry *class_entry_RuntimeException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "OutOfBoundsException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_RuntimeException, 0);

	return class_entry;
}

static zend_class_entry *register_class_OverflowException(zend_class_entry *class_entry_RuntimeException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "OverflowException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_RuntimeException, 0);

	return class_entry;
}

static zend_class_entry *register_class_RangeException(zend_class_entry *class_entry_RuntimeException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "RangeException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_RuntimeException, 0);

	return class_entry;
}

static zend_class_entry *register_class_UnderflowException(zend_class_entry *class_entry_RuntimeException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "UnderflowException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_RuntimeException, 0);

	return class_entry;
}

static zend_class_entry *register_class_UnexpectedValueException(zend_class_entry *class_entry_RuntimeException)
{
	zend_class_entry ce, *class_entry;
	zend_namespaced_name namespaced_name;

	INIT_CLASS_NAME(namespaced_name, "UnexpectedValueException");
	INIT_CLASS_ENTRY(ce, namespaced_name, NULL);
	class_entry = zend_register_internal_class_with_flags(&ce, class_entry_RuntimeException, 0);

	return class_entry;
}
