--TEST--
Reification: AOT-lowered preloaded generic calls (INIT_FCALL/DO_UCALL + concrete RECV + by-value SEND) stay correct and type-safe
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/preload_aot_call_lowering.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
use Bench\Runner;

var_dump(Runner::ints(5));
var_dump(Runner::strs());

// Weak-mode coercion still applies on the synthesized RECV: "5" -> 5.
var_dump(Bench\add::<int>("5", 2));

try {
    Runner::badType();
} catch (\TypeError $e) {
    echo $e->getMessage(), "\n";
}
echo "done\n";
?>
--EXPECTF--
int(10)
string(7) "hello:z"
int(7)
Bench\add(): Argument #2 ($b) must be of type int, array given, called in %s on line %d
done
