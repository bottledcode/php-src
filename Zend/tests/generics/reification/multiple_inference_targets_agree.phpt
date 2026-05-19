--TEST--
Reification: T appearing in multiple arg positions — the first hit wins (subsequent objects of compatible types don't update)
--FILE--
<?php
class Foo {}
class Bar extends Foo {}

function pair<T : object>(T $a, T $b): string {
    return T::class;
}

// Both args are Foo — T inferred as Foo
var_dump(pair(new Foo(), new Foo()));

// First arg pins T to Bar; second is also a Bar, fine
var_dump(pair(new Bar(), new Bar()));

// First arg Foo, second Bar — T pinned to Foo (first wins)
var_dump(pair(new Foo(), new Bar()));
?>
--EXPECT--
string(3) "Foo"
string(3) "Bar"
string(3) "Foo"
