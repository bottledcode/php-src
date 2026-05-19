--TEST--
Variance enforcement at synthesis: extends-with-args composes through the monomorph
--FILE--
<?php
class Animal {}
class Dog extends Animal {}

// Covariant + bound — the synthesizer's bounds check honours the bound.
class Box<+T : Animal> {
    public function get(): T { return new Animal(); }
}

class DogBox extends Box<Dog> {}
$d = new DogBox();
var_dump($d instanceof Box);
var_dump((new ReflectionClass($d))->getParentClass()->getName());

// Two synthesized monos extending the same base both succeed; their own
// concrete signatures honour covariance.
class AnimalBox extends Box<Animal> {}
$rcAnimalMono = new ReflectionClass("Box<Animal>");
echo "Box<Animal>::get returns: ", $rcAnimalMono->getMethod('get')->getReturnType(), "\n";
$rcDogMono = new ReflectionClass("Box<Dog>");
echo "Box<Dog>::get returns: ", $rcDogMono->getMethod('get')->getReturnType(), "\n";

// Bound violation at extends-with-args fires the standard error.
try {
    eval('class IntBox extends Box<int> {}');
} catch (\Throwable $e) {
    echo "bound violation: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
bool(true)
string(8) "Box<Dog>"
Box<Animal>::get returns: Animal
Box<Dog>::get returns: Dog
%aType argument 1 to extends Box in IntBox does not satisfy the bound Animal on parameter T, int given%a
