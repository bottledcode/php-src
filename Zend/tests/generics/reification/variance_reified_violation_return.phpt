--TEST--
Reification: child overriding a generic parent method with a non-compatible return type fires the variance check against the reified T
--FILE--
<?php
class Base<T> {
    public function get(): T { throw new Exception('abstract'); }
}

// Child says get(): string but parent's reified return is int.
class SubIntBroken extends Base<int> {
    public function get(): string { return "x"; }
}
?>
--EXPECTF--
Fatal error: Declaration of SubIntBroken::get(): string must be compatible with Base::get(): int in %s on line %d
