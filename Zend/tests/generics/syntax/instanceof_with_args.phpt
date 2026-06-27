--TEST--
Generic syntax: instanceof with type arguments resolves to the monomorph canonical name
--FILE--
<?php
class C<T> {}
$c = new C::<int>();
var_dump($c instanceof C);
var_dump($c instanceof C::<int>);
var_dump($c instanceof C::<string>);
$x = new stdClass;
var_dump($x instanceof C::<int>);
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
