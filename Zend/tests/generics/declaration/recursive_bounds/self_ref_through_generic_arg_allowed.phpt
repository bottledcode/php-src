--TEST--
Recursive bounds: F-bounded polymorphism (T appears as a type argument inside its own bound)
--FILE--
<?php
// Classic F-bounded form `T: Box<T>` — T isn't its own bound, it's a type
// *argument* to the bound. This is the pattern PHP supports for self-referential
// constraints (analogous to Java `<T extends Comparable<T>>`).
class Box<T> {}
class Foo<T: Box<T>> {}

// A direct self-bound (`T: T`) would be rejected; verify the F-bounded form
// is in fact distinct by reflecting it.
$bound = (new ReflectionClass('Foo'))->getGenericParameters()[0];
echo $bound, "\n";
?>
--EXPECT--
T : Box
