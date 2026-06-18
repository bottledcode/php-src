--TEST--
Reification: an AOT-preloaded monomorph of a bare class-typed parameter accepts subclasses and rejects non-instances
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.optimization_level=-1
opcache.preload={PWD}/preload_class_typed_param.inc
--EXTENSIONS--
opcache
--SKIPIF--
<?php
if (PHP_OS_FAMILY == 'Windows') die('skip Preloading is not supported on Windows');
?>
--FILE--
<?php
use Zoo\Animal;
use Zoo\Dog;
use Zoo\Keeper;

// Regression: persisted monomorph checked global `\Animal` and rejected this valid `Zoo\Dog`.
echo Keeper::take(new Dog()), "\n";
echo Keeper::take(new Animal()), "\n";

try {
    Keeper::bad();
} catch (\TypeError $e) {
    echo $e->getMessage(), "\n";
}
echo "done\n";
?>
--EXPECTF--
Zoo\Dog
Zoo\Animal
Zoo\keep(): Argument #1 ($x) must be of type Zoo\Animal, string given, called in %s on line %d
done
