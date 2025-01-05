--TEST--
eval escape
--ARGS--
hello
--FILE--
<?php



$toEval = '$a = "' . $argv[1] . '";';
var_dump(is_literal_string($toEval));

eval($toEval);

var_dump(is_literal_string($a));
?>
--EXPECT--
bool(false)
bool(true)