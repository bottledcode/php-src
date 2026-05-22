--TEST--
Type arguments on a non-generic class in a property type are a compile-time error
--FILE--
<?php
class Plain {}
class Holder {
    public Plain<int> $x;
}
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class Plain in %s on line %d
