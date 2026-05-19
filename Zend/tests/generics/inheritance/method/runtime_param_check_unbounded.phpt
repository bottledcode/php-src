--TEST--
Inherited methods: substituted unbounded T parameter is checked at runtime on the child
--FILE--
<?php
declare(strict_types=1);

class Box<T> {
    public function take(T $x): T { return $x; }
}

class IntBox extends Box<int> {}
class StrBox extends Box<string> {}

$ib = new IntBox();
$sb = new StrBox();

// Happy paths
var_dump($ib->take(42));
var_dump($sb->take("hi"));

// Cross-type rejections
try {
    $ib->take("not an int");
} catch (TypeError $e) {
    echo "IntBox rejects string: ", $e->getMessage(), "\n";
}

try {
    $sb->take(123);
} catch (TypeError $e) {
    echo "StrBox rejects int: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
string(2) "hi"
IntBox rejects string: Box::take(): Argument #1 ($x) must be of type int, string given, called in %s on line %d
StrBox rejects int: Box::take(): Argument #1 ($x) must be of type string, int given, called in %s on line %d
