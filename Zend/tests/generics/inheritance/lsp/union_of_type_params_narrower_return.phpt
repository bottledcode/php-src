--TEST--
Parametric LSP: child narrowing Tl|Tr return down to a single Tl is accepted
--FILE--
<?php
// Companion to union_of_type_params_return_subst.phpt: a child that
// returns a narrower subset of the parent's substituted Tl|Tr — covariant.

abstract class Base<T> {
    abstract public function get(): T;
}

class LeftOnly<Tl, Tr> extends Base<Tl|Tr> {
    public function __construct(private Tl $value) {}
    public function get(): Tl { return $this->value; }
}

$a = new LeftOnly::<string, int>("hi");
var_dump($a->get());

$b = new LeftOnly::<int, string>(42);
var_dump($b->get());
?>
--EXPECT--
string(2) "hi"
int(42)
