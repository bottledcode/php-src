--TEST--
Parametric LSP: child returning Tl|Tr|string (a widening of the parent's substituted Tl|Tr) is rejected
--FILE--
<?php
// After the pre-erasure mask aggregation, the covariant check sees that
// the child returns a strictly wider set than the parent.

abstract class Base<T> {
    abstract public function get(): T;
}

class BadPair<Tl, Tr> extends Base<Tl|Tr> {
    public function get(): Tl|Tr|string { throw new Exception(); }
}
?>
--EXPECTF--
Fatal error: Declaration of BadPair::get(): Tl|Tr|string must be compatible with Base::get(): Tl|Tr in %s on line %d
