--TEST--
Reification: instanceof distinguishes monos by their canonical argument tuple
--FILE--
<?php
class Animal {}
class Dog {}

class Box<T : object> {}

$bd = new Box::<Dog>();
$ba = new Box::<Animal>();

// Each mono extends the bare class but is otherwise distinct.
var_dump($bd instanceof Box);            // true: parent-name chain
var_dump($bd instanceof Box<Dog>);       // true: same canonical mono
var_dump($bd instanceof Box<Animal>);    // false: invariant distinction
var_dump($ba instanceof Box<Dog>);       // false
var_dump($ba instanceof Box<Animal>);    // true
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
