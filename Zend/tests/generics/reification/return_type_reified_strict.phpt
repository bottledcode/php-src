--TEST--
Reification: VERIFY_RETURN_TYPE under strict_types catches coercible mismatches the weak path would silently accept
--FILE--
<?php
declare(strict_types=1);

function ret_int<T>(T $x): T { return 999; }
function ret_str<T>(T $x): T { return "1.5"; }
function ret_float<T>(T $x): T { return 3.14; }

// Under strict_types, an int return from a function bound to T=string must
// throw — weak mode would silently coerce to "999".
try {
    ret_int::<string>("a");
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}

// Numeric string returned where T=int — weak would coerce; strict throws.
try {
    ret_str::<int>(0);
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}

// Float returned where T=int — weak would coerce; strict throws.
try {
    ret_float::<int>(0);
} catch (TypeError $e) {
    echo "3: ", $e->getMessage(), "\n";
}

// Positive: correct concrete types still pass.
var_dump(ret_int::<int>(0));
var_dump(ret_str::<string>("a"));
var_dump(ret_float::<float>(0.0));
?>
--EXPECTF--
1: ret_int(): Return value must be of type string, int returned
2: ret_str(): Return value must be of type int, string returned
3: ret_float(): Return value must be of type int, float returned
int(999)
string(3) "1.5"
float(3.14)
