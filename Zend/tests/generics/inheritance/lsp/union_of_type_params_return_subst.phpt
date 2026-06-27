--TEST--
Parametric LSP: child returning union of two type parameters (Tl|Tr) satisfies parent's T when parent is bound to Tl|Tr
--FILE--
<?php
// Regression for: child override declaring `: Tl|Tr` was reported as
// `: mixed` against the parent's substituted `: T = Tl|Tr`, even though
// the two are structurally identical.
//
// Triggered originally by Psl\Type\Internal\UnionType extending
// Psl\Type\Type<Tl|Tr> with overrides returning Tl|Tr.

abstract class Base<T> {
    abstract public function get(): T;
}

class Pair<Tl, Tr> extends Base<Tl|Tr> {
    public function __construct(private Tl|Tr $value) {}
    public function get(): Tl|Tr { return $this->value; }
}

$p = new Pair::<string, int>("hi");
var_dump($p->get());

$q = new Pair::<string, int>(42);
var_dump($q->get());

// Also exercise the interface-implements path, which is what the Psl
// failure actually hit (UnionType extends Type which implements TypeInterface).
interface I<out T> {
    public function read(mixed $v): T;
}

abstract class IBase<T> implements I<T> {
    abstract public function read(mixed $v): T;
}

class Union<Tl, Tr> extends IBase<Tl|Tr> {
    public function read(mixed $v): Tl|Tr {
        if ($v instanceof Tl || $v instanceof Tr) return $v;
        throw new TypeError("nope");
    }
}

echo "loaded\n";
?>
--EXPECT--
string(2) "hi"
int(42)
loaded
