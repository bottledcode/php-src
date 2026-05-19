--TEST--
Reification: instanceof respects variance markers on the generic base
--FILE--
<?php
class Animal {}
class Dog extends Animal {}
class Cat extends Animal {}

class Box<T : object> {}        // invariant
class Producer<+T : object> {}  // covariant
class Consumer<-T : object> {}  // contravariant

$dogBox = new Box::<Dog>();
$dogProd = new Producer::<Dog>();
$animalCons = new Consumer::<Animal>();

// Invariant: only same-arg passes.
var_dump($dogBox instanceof Box<Dog>);       // true (identical)
var_dump($dogBox instanceof Box<Animal>);    // false
var_dump($dogBox instanceof Box<Cat>);       // false

// Covariant: Dog <: Animal, so Producer<Dog> <: Producer<Animal>.
var_dump($dogProd instanceof Producer<Animal>);  // true
var_dump($dogProd instanceof Producer<Cat>);     // false
var_dump($dogProd instanceof Producer<Dog>);     // true

// Contravariant: Animal :> Dog, so Consumer<Animal> <: Consumer<Dog>.
var_dump($animalCons instanceof Consumer<Dog>);   // true
var_dump($animalCons instanceof Consumer<Cat>);   // true
var_dump($animalCons instanceof Consumer<Animal>);// true

// Reverse contravariant fails.
$dogCons = new Consumer::<Dog>();
var_dump($dogCons instanceof Consumer<Animal>);   // false
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
