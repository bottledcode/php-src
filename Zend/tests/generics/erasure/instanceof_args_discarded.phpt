--TEST--
Erasure: instanceof on a non-generic class with type arguments resolves to the canonical name (which does not exist) and returns false
--FILE--
<?php
class C {}
$c = new C;
var_dump($c instanceof C);
var_dump($c instanceof C<int>);
var_dump($c instanceof C<string, int>);
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
