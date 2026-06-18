--TEST--
Reification: an unqualified class name in a turbofish type argument resolves against the current namespace
--FILE--
<?php

namespace Acme;

class Animal {}
class Dog extends Animal {}

function identity<T>(T $x): T { return $x; }

$dog = identity::<Animal>(new Dog());
echo get_class($dog), "\n";

$animal = identity::<Animal>(new Animal());
echo get_class($animal), "\n";

try {
    identity::<Animal>("not an animal");
} catch (\TypeError $e) {
    echo $e->getMessage(), "\n";
}

function wrap<T>(T $x): T { return $x; }
$d2 = wrap::<Animal>(new Dog());
echo get_class($d2), "\n";

echo "done\n";
?>
--EXPECTF--
Acme\Dog
Acme\Animal
Acme\identity(): Argument #1 ($x) must be of type Acme\Animal, string given, called in %s on line %d
Acme\Dog
done
