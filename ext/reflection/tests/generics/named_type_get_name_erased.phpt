--TEST--
Reflection: ReflectionNamedType::getName() returns the reified name with type arguments
--FILE--
<?php
class Box<T> {}
function f(Box<int> $x): Box<string> { return $x; }
$r = new ReflectionFunction('f');
echo $r->getParameters()[0]->getType()->getName(), "\n";
echo $r->getReturnType()->getName(), "\n";
?>
--EXPECT--
Box<int>
Box<string>
