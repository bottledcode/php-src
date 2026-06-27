--TEST--
Turbofish: a bare `<`/`>` after an instanceof RHS is the comparison operator (BC), not a type-argument list
--FILE--
<?php
// Regression: `instanceof` binds tighter than `<`/`>`, so existing code like
// `$x instanceof A < $y` must keep parsing as `($x instanceof A) < $y`.
// Type arguments in expression position require turbofish (`A::<...>`), so a
// bare `<` here can never be reinterpreted as a generic argument list.
class A {}
$a = new A;

var_dump($a instanceof A < 5);   // (true) < 5  => false
var_dump($a instanceof A > 0);   // (true) > 0  => true
var_dump(($a instanceof A) < 5); // explicit grouping, must match the first
?>
--EXPECT--
bool(false)
bool(true)
bool(false)
