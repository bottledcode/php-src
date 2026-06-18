--TEST--
Reification: a scalar literal argument monomorphizes a non-turbofish generic call at compile time (no opcache needed); the reified return type enforces the inferred T
--FILE--
<?php
declare(strict_types=1);

function retStr<T : int|float|string>(T $x): T { return "s"; }
function retInt<T : int|float|string>(T $x): T { return 7; }

function lit_int()    { return retStr(42); }
function lit_float()  { return retStr(3.14); }
function lit_string() { return retInt("hi"); }

foreach (['lit_int', 'lit_float', 'lit_string'] as $fn) {
    try {
        $fn();
        echo "$fn: NO error (not monomorphized!)\n";
    } catch (TypeError $e) {
        echo "$fn: ", $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
lit_int: retStr(): Return value must be of type int, string returned
lit_float: retStr(): Return value must be of type float, string returned
lit_string: retInt(): Return value must be of type string, int returned
