--TEST--
Reification: Closure::call propagates the captured T-table into the fake closure that actually runs the body
--FILE--
<?php
class Holder {
    public mixed $store = null;
}

class A {}
class B extends A {}

// Generic closure captures outer T. Closure::call binds it to a different
// $this; the body must still check the value-arg against the captured T,
// not silently erase it.

function maker<T>(): Closure {
    return function (T $x): string {
        $this->store = $x;
        return get_class($x);
    };
}

$f = maker::<A>();
$h = new Holder;

// Positive: A and its subclass B both satisfy T = A.
var_dump($f->call($h, new A));
var_dump($f->call($h, new B));

// Negative: a value that doesn't satisfy T fires with the resolved T.
class Unrelated {}
try {
    $f->call($h, new Unrelated);
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}

// Variadic + call: every element checked against the captured T.
function vmaker<T>(): Closure {
    return function (T ...$xs): int { return count($xs); };
}

$vf = vmaker::<A>();
var_dump($vf->call($h, new A, new B, new A));

try {
    $vf->call($h, new A, new Unrelated, new A);
} catch (TypeError $e) {
    echo "rejected2: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(1) "A"
string(1) "B"
rejected: %s must be of type A, Unrelated given%S
int(3)
rejected2: %s must be of type A, Unrelated given%S
