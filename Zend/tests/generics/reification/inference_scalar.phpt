--TEST--
Reification: T is inferred from scalar/array arguments, not just objects
--FILE--
<?php
function tparams<T = mixed>(T $a): array { return func_get_type_args(); }

var_dump(tparams(1)["T"]);
var_dump(tparams("foo")["T"]);
var_dump(tparams(1.5)["T"]);
var_dump(tparams(true)["T"]);
var_dump(tparams(false)["T"]);
var_dump(tparams([1, 2])["T"]);
var_dump(tparams(new stdClass())["T"]);
?>
--EXPECT--
string(3) "int"
string(6) "string"
string(5) "float"
string(4) "bool"
string(4) "bool"
string(5) "array"
string(8) "stdClass"
