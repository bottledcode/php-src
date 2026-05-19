--TEST--
Extends-with-args: property hook on inherited T-typed property survives monomorph link
--FILE--
<?php
class Box<T> {
    public T $value {
        get => $this->value;
        set(T $v) { $this->value = $v; }
    }
    public function __construct(T $v) { $this->value = $v; }
}

class IntBox extends Box<int> {}

$b = new IntBox(42);
echo "type: ", (new ReflectionProperty($b, "value"))->getType(), "\n";
echo "value: ", $b->value, "\n";

// Set hook with the substituted type accepts an int.
$b->value = 99;
echo "after set: ", $b->value, "\n";

// And the inherited typed property is monomorph-substituted to int.
$rc = new ReflectionClass($b);
echo "parent: ", $rc->getParentClass()->getName(), "\n";

$baseRC = new ReflectionClass(Box::class);
echo "base property type: ", $baseRC->getProperty('value')->getType(), "\n";

$monoRC = new ReflectionClass("Box<int>");
echo "mono property type: ", $monoRC->getProperty('value')->getType(), "\n";
?>
--EXPECT--
type: int
value: 42
after set: 99
parent: Box<int>
base property type: mixed
mono property type: int
