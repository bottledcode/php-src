--TEST--
Literal strings are strings
--FILE--
<?php

var_dump(is_string('hello'));
var_dump(is_string('hello' . 'world'));

?>
--EXPECT--
bool(true)
bool(true)