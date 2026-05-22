--TEST--
Type arguments on a non-generic class in `new` (turbofish) are a compile-time error
--FILE--
<?php
class Plain {}
$x = new Plain::<int>();
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
