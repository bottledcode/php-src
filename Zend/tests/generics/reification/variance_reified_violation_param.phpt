--TEST--
Reification: child narrowing a generic parent's reified parameter type (int → string) is rejected by variance (contravariance)
--FILE--
<?php
class Base<T> {
    public function set(T $x): void {}
}

// Child accepts NARROWER (string) than parent's reified int — not contravariant.
class SubParam extends Base<int> {
    public function set(string $x): void {}
}
?>
--EXPECTF--
Fatal error: Declaration of SubParam::set(string $x): void must be compatible with Base::set(int $x): void in %s on line %d
