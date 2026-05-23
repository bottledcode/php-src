--TEST--
Parametric LSP: method-level type-parameter bound that references a class-scope T substitutes when the subclass binds T
--FILE--
<?php
// Regression: `class A<T> { function set<U : T>(U $x) }` — the method's
// own U has a bound that's a class-scope T-ref. When `class B extends A<string>`
// supplies T = string, the inheritance check on the inherited (or overridden)
// method must see U's bound substituted from T → string, otherwise the parent
// renders as `set(mixed $x)` (T erased) and the child's `set<U:string>(U $x)`
// gets rejected as narrower than mixed.

class A<T> {
    public function set<U : T>(U $x): void {}
}

class B extends A<string> {
    public function set<U : string>(U $x): void {}
}

class C<Tl, Tr> {
    public function set<U : Tl|Tr>(U $x): void {}
}

class D extends C<string, int> {
    public function set<U : string|int>(U $x): void {}
}

echo "OK\n";
?>
--EXPECT--
OK
