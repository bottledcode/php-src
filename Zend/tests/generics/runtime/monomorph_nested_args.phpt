--TEST--
Monomorph nesting: type arguments may themselves be generic, canonicalized recursively
--FILE--
<?php
class Container<T = mixed> {
    public function __construct(public mixed $value) {}
}

class Pair<L = mixed, R = mixed> {
    public function __construct(public mixed $left, public mixed $right) {}
}

// Nested generic args canonicalize.
$a = new Container::<Container<int>>(new Container::<int>(1));
var_dump($a::class);
var_dump($a instanceof Container);
var_dump($a->value::class);

// Mixed nesting and unions.
$b = new Container::<Pair<int, string>>(new Pair::<int, string>(1, "x"));
var_dump($b::class);

// Same nested form yields the same monomorph.
$c = new Container::<Container<int>>(new Container::<int>(2));
var_dump($a::class === $c::class);

// Deeply nested.
$d = new Container::<Container<Container<int>>>(new Container::<Container<int>>(new Container::<int>(3)));
var_dump($d::class);
?>
--EXPECT--
string(25) "Container<Container<int>>"
bool(true)
string(14) "Container<int>"
string(27) "Container<Pair<int,string>>"
bool(true)
string(36) "Container<Container<Container<int>>>"
