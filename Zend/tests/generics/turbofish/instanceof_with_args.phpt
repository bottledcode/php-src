--TEST--
Turbofish: instanceof with type arguments compares against the monomorph canonical name
--FILE--
<?php
class B<T : object> {}
class C {}
class Animal {}
class Dog {}

$c = new C;
var_dump($c instanceof C);          // true
var_dump($c instanceof C<int>);     // false: C is non-generic; C<int> does not exist

$b = new B::<Dog>();
var_dump($b instanceof B);          // true: every mono extends the bare class
var_dump($b instanceof B<Dog>);     // true: same canonical mono
var_dump($b instanceof B<Animal>);  // false: distinct mono
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
