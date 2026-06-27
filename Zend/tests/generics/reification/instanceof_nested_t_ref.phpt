--TEST--
Reification: nested T-refs (instanceof Outer::<Box<T>>) substitute through and resolve the outermost monomorph
--FILE--
<?php
class Box<U> {}
class Outer<V> {}

class Holder<T> {
    public function isOuterOfBox(mixed $x): bool {
        return $x instanceof Outer::<Box<T>>;
    }
}

$intHolder = new Holder::<int>();
$strHolder = new Holder::<string>();

var_dump($intHolder->isOuterOfBox(new Outer::<Box<int>>));       // true
var_dump($intHolder->isOuterOfBox(new Outer::<Box<string>>));    // false
var_dump($strHolder->isOuterOfBox(new Outer::<Box<string>>));    // true
var_dump($strHolder->isOuterOfBox(new Outer::<int>));            // false: outer mono name differs
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
