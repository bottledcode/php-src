--TEST--
Turbofish: instanceof with type arguments compares against the monomorph canonical name
--FILE--
<?php
class B<T : object> {}
class Animal {}
class Dog {}

$b = new B::<Dog>();
var_dump($b instanceof B);          // true: every mono extends the bare class
var_dump($b instanceof B<Dog>);     // true: same canonical mono
var_dump($b instanceof B<Animal>);  // false: distinct mono
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
