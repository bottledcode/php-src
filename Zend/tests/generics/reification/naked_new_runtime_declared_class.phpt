--TEST--
Reification: naked `new C()` synthesizes the defaults monomorph even when C is declared at runtime (trait / parameterized implements)
--FILE--
<?php
// A generic-with-defaults class that uses a trait (or a parameterized
// `implements`) is declared at runtime, so the compile-time canonical-name
// rewrite of `new C()` can't see it. The runtime ZEND_NEW path must still
// synthesize the defaults monomorph, so `new C()` and `new static()` agree on
// identity (and value-equal instances compare ==).

trait AnyTrait {}

class C<TK = mixed, TV = mixed> {
    use AnyTrait;
    public array $items = [];
    public function __construct($i = []) { $this->items = (array) $i; }
    public function copy(): static { return new static($this->items); }
}

$a = new C([1, 2]);
echo $a::class, "\n";                 // C<mixed,mixed>, not bare C
echo $a->copy()::class, "\n";         // C<mixed,mixed>
var_dump($a::class === $a->copy()::class);
var_dump((new C([1, 2])) == (new C([1, 2]))->copy());

// Parameterized implements also defers declaration to runtime.
interface Iface<T = mixed> {}
class D<T = mixed> implements Iface<T> {
    public function __construct(public int $n = 0) {}
    public function dup(): static { return new static($this->n); }
}
$d = new D(7);
echo $d::class, "\n";                 // D<mixed>
var_dump($d::class === $d->dup()::class);
var_dump($d instanceof Iface);

// Control: a class with neither trait nor parameterized implements is
// early-bound; the compile-time rewrite already produces the monomorph.
class E<TK = mixed, TV = mixed> {
    public function __construct(public array $items = []) {}
    public function copy(): static { return new static($this->items); }
}
$e = new E([1]);
echo $e::class, "\n";                 // E<mixed,mixed>
var_dump($e::class === $e->copy()::class);
?>
--EXPECT--
C<mixed,mixed>
C<mixed,mixed>
bool(true)
bool(true)
D<mixed>
bool(true)
bool(true)
E<mixed,mixed>
bool(true)
