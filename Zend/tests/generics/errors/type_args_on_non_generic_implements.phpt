--TEST--
Type arguments on a non-generic interface in `implements` are a compile-time error
--FILE--
<?php
interface Plain {}
class Bad implements Plain<int> {}
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
