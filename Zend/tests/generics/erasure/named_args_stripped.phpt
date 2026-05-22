--TEST--
Erasure: type arguments on a non-generic class in a parameter/return type is a compile-time error
--FILE--
<?php
class Container {}
function f(Container<int> $x): Container<string> { return $x; }
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Container in %s on line %d
