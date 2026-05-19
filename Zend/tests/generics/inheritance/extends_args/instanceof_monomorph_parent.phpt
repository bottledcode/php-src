--TEST--
Extends-with-args: subclass's direct parent is the canonical monomorph
--FILE--
<?php
class Box<T> {
    public function __construct(public mixed $value) {}
}

class IntBox extends Box<int> {}
class StrBox extends Box<string> {}

$i = new IntBox(42);
$s = new StrBox("hi");

// instanceof at the AST level discards type arguments, so we verify the
// monomorph parent via reflection and string class comparisons instead.
var_dump($i instanceof Box);
var_dump($i instanceof IntBox);

$rcInt = new ReflectionClass(IntBox::class);
$rcStr = new ReflectionClass(StrBox::class);
var_dump($rcInt->getParentClass()->getName());
var_dump($rcStr->getParentClass()->getName());

// Each subclass's direct parent is its own distinct monomorph.
var_dump($rcInt->getParentClass()->getName() !== $rcStr->getParentClass()->getName());

// The grandparent is the bare base, shared.
var_dump($rcInt->getParentClass()->getParentClass()->getName());
var_dump($rcInt->getParentClass()->getParentClass()->getName()
    === $rcStr->getParentClass()->getParentClass()->getName());

// And the canonical monomorphs are registered with their canonical names.
var_dump(class_exists("Box<int>", false));
var_dump(class_exists("Box<string>", false));
?>
--EXPECT--
bool(true)
bool(true)
string(8) "Box<int>"
string(11) "Box<string>"
bool(true)
string(3) "Box"
bool(true)
bool(true)
bool(true)
