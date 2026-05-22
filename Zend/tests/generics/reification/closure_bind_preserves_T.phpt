--TEST--
Reification: Closure::bind / Closure::bindTo carry the captured T-table to the rebound closure
--FILE--
<?php
class A { public int $tag = 0; }
class B { public int $tag = 0; }

// Closure captures outer T at declaration time. After re-binding to a new
// object/scope, the body must still resolve T against the originally
// captured binding — not silently erase it.

function maker<T>(): Closure {
    return function (T $x): T { return $x; };
}

$f = maker::<A>();

// Closure::bindTo to a new instance of A (compatible) — same scope.
$rb = $f->bindTo(new A);
var_dump(get_class($rb(new A)));            // accepts A

try {
    $rb(new B);                              // wrong concrete class
} catch (TypeError $e) {
    echo "bindTo wrong: ", $e->getMessage(), "\n";
}

// Closure::bind static form — also propagates.
$rb2 = Closure::bind($f, new A);
var_dump(get_class($rb2(new A)));

try {
    $rb2(new B);
} catch (TypeError $e) {
    echo "bind wrong: ", $e->getMessage(), "\n";
}

// bindTo(null) — strip the $this — still keeps the T-table.
$rb3 = $f->bindTo(null);
var_dump(get_class($rb3(new A)));
try {
    $rb3(new B);
} catch (TypeError $e) {
    echo "bindTo(null) wrong: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(1) "A"
bindTo wrong: %s must be of type A, B given%S
string(1) "A"
bind wrong: %s must be of type A, B given%S
string(1) "A"
bindTo(null) wrong: %s must be of type A, B given%S
