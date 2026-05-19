--TEST--
Inherited methods: substituted T parameter with a default still rejects bad explicit args
--FILE--
<?php
class Box<T> {
    public function take(T $x = null): mixed { return $x; }
}

class IntBox extends Box<?int> {}

$ib = new IntBox();

// Default and matching values pass
var_dump($ib->take());
var_dump($ib->take(7));
var_dump($ib->take(null));

try {
    $ib->take("nope");
} catch (TypeError $e) {
    echo "rejects string: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
NULL
int(7)
NULL
rejects string: Box::take(): Argument #1 ($x) must be of type ?int, string given, called in %s on line %d
