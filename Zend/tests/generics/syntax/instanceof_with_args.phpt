--TEST--
Generic syntax: instanceof with type arguments resolves to the monomorph canonical name
--FILE--
<?php
class C {}
$c = new C;
// C is non-generic; the canonical names C<int> and C<int,string> do not exist
// as classes, so instanceof returns false.
var_dump($c instanceof C<int>);
var_dump($c instanceof C<int, string>);
$x = new stdClass;
var_dump($x instanceof C<int>);
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
