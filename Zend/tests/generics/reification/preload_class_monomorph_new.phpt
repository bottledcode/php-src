--TEST--
Reification: a compile-time class monomorph (`new C::<int>`) inside a preloaded method preloads cleanly and behaves correctly
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/preload_class_monomorph_new.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
// Regression: preload_fix_trait_op_array deref'd a missing xlat "original" -> SEGV at preload.
echo Driver::build(), "\n";

$s = new Vec::<string>(['a']);
$s = $s->with('b');
echo $s->size(), ':', get_class($s), "\n";
echo "done\n";
?>
--EXPECT--
10:4:Vec<int>
2:Vec<string>
done
