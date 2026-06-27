--TEST--
Type arguments on a non-generic class in `instanceof` are a compile-time error
--FILE--
<?php
class Plain {}
$x = new Plain;
var_dump($x instanceof Plain::<int>);
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
