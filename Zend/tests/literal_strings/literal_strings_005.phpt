--TEST--
substring is a literal
--FILE--
<?php
$a = "hello world";

var_dump(is_literal_string(substr($a, 0, 5)));
?>
--EXPECT--
bool(false)