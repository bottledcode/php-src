--TEST--
Monomorphization: get_class returns the canonical monomorph name
--FILE--
<?php
class Box<T> {}
$b = new Box::<int>;
echo get_class($b), "\n";
?>
--EXPECT--
Box<int>
