--TEST--
Reification: positive inheritance-variance cases — child signatures substituted against the reified T compile correctly
--FILE--
<?php
// Variance checks at inheritance time must operate on the substituted
// (reified) parent signature, not the erased bound. This file covers the
// positive cases — child declarations that ARE valid against the reified
// parent signature must compile cleanly. The negative companions live in
// variance_reified_violation_*.phpt files (compile-time fatals can't be
// caught with try/catch).

class Base<T> {
    public function get(): T { throw new Exception('abstract'); }
    public function set(T $x): void {}
}

// Child matches the substituted parent signature exactly.
class SubInt extends Base<int> {
    public function get(): int { return 42; }
    public function set(int $x): void {}
}

var_dump((new SubInt)->get());
(new SubInt)->set(99);
echo "SubInt ok\n";

// Param contravariance: child accepts a WIDER param (mixed accepts int)
// than the parent's substituted int. This is allowed.
class SubParamWide extends Base<int> {
    public function set(mixed $x): void {}
}
(new SubParamWide)->set(7);
(new SubParamWide)->set("hello");
echo "SubParamWide ok\n";

// Return-type covariance with class hierarchy: parent returns T = Animal,
// child returns Dog (a subclass of Animal). Allowed.
class Animal {}
class Dog extends Animal {}

class AnimalSrc<T : object> {
    public function get(): T { throw new Exception('abstract'); }
}

class DogSrc extends AnimalSrc<Animal> {
    public function get(): Dog { return new Dog; }
}
var_dump(get_class((new DogSrc)->get()));
?>
--EXPECT--
int(42)
SubInt ok
SubParamWide ok
string(3) "Dog"
