--TEST--
Reification: instanceof Box::<T> with class-level T resolves against the called scope's monomorph args
--FILE--
<?php
class Box<T> {}

class Outer<T> {
    public function isBox(mixed $x): bool {
        return $x instanceof Box::<T>;
    }
}

$animalOuter = new Outer::<Animal>();
$dogOuter    = new Outer::<Dog>();
$intOuter    = new Outer::<int>();

class Animal {}
class Dog extends Animal {}

var_dump($animalOuter->isBox(new Box::<Animal>));   // true
var_dump($animalOuter->isBox(new Box::<Dog>));      // false: nominal monos
var_dump($dogOuter->isBox(new Box::<Dog>));         // true
var_dump($dogOuter->isBox(new Box::<Animal>));      // false
var_dump($intOuter->isBox(new Box::<int>));         // true
var_dump($intOuter->isBox(new Box::<string>));      // false
var_dump($animalOuter->isBox(42));                  // false: not an object
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
