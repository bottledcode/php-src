--TEST--
Inheritance: `extends Base<int>` makes the direct parent the synthesized monomorph
--FILE--
<?php
class Base<T> {}
class Derived extends Base<int> {}
echo (new ReflectionClass('Derived'))->getParentClass()->getName(), "\n";
$d = new Derived;
var_dump($d instanceof Base);
?>
--EXPECT--
Base<int>
bool(true)
