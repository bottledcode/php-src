--TEST--
Reification: a preloaded generic function that contains a generic call op resolves correctly and shuts down cleanly (smoke test for the destroy_op_array runtime-cache cleanup; the underlying UAF is caught deterministically under valgrind/ASAN)
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/preload_generic_call_ops.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
var_dump(outer::<int>(5));
var_dump(outer(7));
var_dump(inner::<string>("ok"));
echo "done\n";
?>
--EXPECT--
int(5)
int(7)
string(2) "ok"
done
