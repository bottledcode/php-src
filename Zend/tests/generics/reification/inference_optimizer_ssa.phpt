--TEST--
Reification: the opcache DFA optimizer monomorphizes non-turbofish generic calls from each argument's SSA-inferred type; reassignment binds the actual type, not the declared one, and unknown types stay generic
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
--FILE--
<?php
declare(strict_types=1);

function retStr<T : int|float|string>(T $x): T { return "s"; }
function retInt<T : int|float|string>(T $x): T { return 7; }

function from_int(int $i)       { return retStr($i); }
function from_float(float $f)   { return retStr($f); }
function local_literal()        { $v = 1.5; return retStr($v); }

// Reassignment must bind T from the SSA type (string), not the declared int.
function reassigned(int $val)   { $val = 'x'; return retInt($val); }

function from_unknown(array $a) { $v = $a[0]; return retInt($v); }

function check(string $name, callable $fn): void {
    try {
        $r = $fn();
        echo "$name: ", var_export($r, true), "\n";
    } catch (TypeError $e) {
        echo "$name: TypeError\n";
    }
}

check('from_int',      fn() => from_int(1));
check('from_float',    fn() => from_float(1.5));
check('local_literal', fn() => local_literal());
check('reassigned',    fn() => reassigned(1));
check('from_unknown',  fn() => from_unknown([42]));
?>
--EXPECT--
from_int: TypeError
from_float: TypeError
local_literal: TypeError
reassigned: TypeError
from_unknown: 7
