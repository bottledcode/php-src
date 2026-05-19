--TEST--
Errors: naked `new GenericClass()` is rejected if any type parameter has no default
--FILE--
<?php
class Box<T> {}
new Box();
?>
--EXPECTF--
Fatal error: Cannot instantiate generic class Box without type arguments; type parameter T has no default in %s on line %d
