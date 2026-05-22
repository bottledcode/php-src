--TEST--
Reification: child widening a generic parent's reified return type (int → mixed) is rejected by variance
--FILE--
<?php
class Base<T> {
    public function get(): T { throw new Exception('abstract'); }
}

// Child can't widen the parent's reified int return to mixed.
class SubIntWiden extends Base<int> {
    public function get(): mixed { return 42; }
}
?>
--EXPECTF--
Fatal error: Declaration of SubIntWiden::get(): mixed must be compatible with Base::get(): int in %s on line %d
