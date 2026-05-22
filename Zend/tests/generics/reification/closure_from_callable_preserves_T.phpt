--TEST--
Reification: Closure::fromCallable on a generic closure preserves the captured T-table (returns the same closure)
--FILE--
<?php
class A {}
class B {}

function maker<T>(): Closure {
    return function (T $x): string { return get_class($x); };
}

$f = maker::<A>();
$wrapped = Closure::fromCallable($f);

// Positive: behaves like the original — T is still A.
var_dump($wrapped(new A));

// Negative: wrong concrete class still fires with the resolved T.
try {
    $wrapped(new B);
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(1) "A"
rejected: %s must be of type A, B given%S
