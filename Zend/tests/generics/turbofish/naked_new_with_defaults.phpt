--TEST--
Naked `new GenericClass()` synthesizes the monomorph when every parameter has a default
--FILE--
<?php
class Box<T = int> {
    public function __construct(public mixed $value) {}
}

class Pair<L = int, R = string> {
    public function __construct(public mixed $left, public mixed $right) {}
}

$b = new Box(42);
var_dump($b::class);
var_dump($b instanceof Box);

$p = new Pair(1, "hi");
var_dump($p::class);
var_dump($p instanceof Pair);

// Identity: two naked-new's of the same class produce instances of the same monomorph.
var_dump((new Box(1))::class === (new Box(2))::class);
?>
--EXPECT--
string(8) "Box<int>"
bool(true)
string(16) "Pair<int,string>"
bool(true)
bool(true)
