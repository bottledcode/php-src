--TEST--
Inherited methods: runtime check fires across a multi-level generic chain
--FILE--
<?php
class Base<T> {
    public function take(T $x): T { return $x; }
}

class Mid<U> extends Base<U> {}

class Leaf extends Mid<int> {}

$l = new Leaf();
var_dump($l->take(42));

try {
    $l->take("nope");
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
rejected: Base::take(): Argument #1 ($x) must be of type int, string given, called in %s on line %d
