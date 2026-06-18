--TEST--
Reification: `T|null` and `?T` are the same pre-erasure shape across abstract/interface inheritance
--FILE--
<?php
// `T|null` must normalize to the same pre-erasure shape as `?T`, so an abstract
// prototype declaring `TK|null` stays compatible with a concrete override (and
// vice-versa). Before the fix the union form collapsed to `mixed` on the proto
// side only, breaking the variance check.
interface I<TK = mixed, TV = mixed> {
    public function get(TK|null $k): ?TV;
}
class C<TK = mixed, TV = mixed> implements I<TK, TV> {
    public function get(TK|null $k): ?TV { return $k; }
}

// Same shape via an abstract class.
abstract class AB<TK = mixed, TV = mixed> {
    abstract public function get(TK|null $k): ?TV;
}
class CB<TK = mixed, TV = mixed> extends AB<TK, TV> {
    public function get(TK|null $k): ?TV { return $k; }
}

// Mixed syntax: proto uses `?TK`, child uses `TK|null` — must still match.
interface J<TK = mixed, TV = mixed> {
    public function get(?TK $k): ?TV;
}
class D<TK = mixed, TV = mixed> implements J<TK, TV> {
    public function get(TK|null $k): ?TV { return $k; }
}

echo "compiled ok\n";

// The reified binding is still enforced at runtime for the union param form.
$c = new C::<int, int>();
var_dump($c->get(null));   // null accepted by `TK|null` == `?int`
var_dump($c->get(5));

try {
    $c->get("not-an-int");
} catch (TypeError $e) {
    echo "TypeError: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
compiled ok
NULL
int(5)
TypeError: C::get(): Argument #1 ($k)%smust be of type ?int, string given%a
