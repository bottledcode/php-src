--TEST--
Erasure: instanceof on a non-generic class with type arguments is a compile-time error
--FILE--
<?php
class C {}
$c = new C;
var_dump($c instanceof C::<int>);
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class C in %s on line %d
