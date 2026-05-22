--TEST--
Reification: a closure created inside a generic frame captures that frame's T-table, so the closure's body resolves T to the binding in effect at creation time
--FILE--
<?php
class Foo {}
class Bar {}

// T::class inside the body needs to resolve to the captured binding (no
// turbofish at the closure's own call site).
function nameOf<T : object>(): Closure {
    return fn() => T::class;
}
echo nameOf::<Foo>()(), "\n";
echo nameOf::<Bar>()(), "\n";

// A closure with a T-typed parameter rejects values that don't satisfy the
// CAPTURED binding, not just the parameter's erased bound.
function id<T : object>(): Closure {
    return function(T $x): T { return $x; };
}
$fooId = id::<Foo>();
$f = $fooId(new Foo);
var_dump(get_class($f));

try {
    $fooId(new Bar);
} catch (TypeError $e) {
    echo "rejected wrong class: ", $e->getMessage(), "\n";
}

// Scalars are rejected against the resolved T (Foo) — the captured binding
// is more specific than the parameter's erased object bound.
try {
    $fooId(42);
} catch (TypeError $e) {
    echo "rejected scalar: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
Foo
Bar
string(3) "Foo"
rejected wrong class: %s must be of type Foo, Bar given%S
rejected scalar: %s must be of type Foo, int given%S
