--TEST--
Errors: T in intersection position with unbounded T
--FILE--
<?php
class Foo {}
function x<T>(): T & Foo {}
?>
--EXPECTF--
Fatal error: Type parameter T with bound mixed cannot be part of an intersection type; bound it to a class or interface (e.g. T: SomeInterface) in %s on line %d
