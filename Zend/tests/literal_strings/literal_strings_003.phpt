--TEST--
is_literal_string works
--ARGS--
test
--FILE--
<?php

var_dump(is_literal_string($argv[1])); // false
$argv[1] = "some literal";
var_dump(is_literal_string($argv[1])); // true
?>
--EXPECT--
bool(false)
bool(true)