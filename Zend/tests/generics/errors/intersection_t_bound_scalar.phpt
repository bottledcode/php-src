--TEST--
Errors: T in intersection position with scalar bound
--FILE--
<?php
class Foo {}
function x<T : string>(): T & Foo {}
?>
--EXPECTF--
Fatal error: Type parameter T with bound string cannot be part of an intersection type; bound it to a class or interface (e.g. T: SomeInterface) in %s on line %d
