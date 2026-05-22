--TEST--
Type arguments on a non-generic class in a parameter type are a compile-time error
--FILE--
<?php
class Plain {}
function f(Plain<int> $x): void {}
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
