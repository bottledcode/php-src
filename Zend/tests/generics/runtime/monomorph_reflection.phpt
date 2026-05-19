--TEST--
Monomorph reflection: ReflectionClass on a synthesized monomorph reports the canonical name and parent
--FILE--
<?php
class Box<T = mixed> {
    public function __construct(public mixed $value) {}
    public function get(): mixed { return $this->value; }
}

$b = new Box::<int>(42);
$mono = $b::class;

$rc = new ReflectionClass($mono);
var_dump($rc->getName());

$parent = $rc->getParentClass();
var_dump($parent->getName());

// The monomorph carries the same method shape as the base.
var_dump($rc->hasMethod('get'));
var_dump($rc->getMethod('__construct')->getNumberOfParameters());

// Reflecting via the base name still works.
$rcBase = new ReflectionClass(Box::class);
var_dump($rcBase->getName());
?>
--EXPECT--
string(8) "Box<int>"
string(3) "Box"
bool(true)
int(1)
string(3) "Box"
