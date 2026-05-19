--TEST--
Inherited methods: substituted unbounded T return type is checked at runtime on the child
--FILE--
<?php
declare(strict_types=1);

class Box<T> {
    public mixed $payload = null;
    public function get(): T { return $this->payload; }
}

class IntBox extends Box<int> {}
class StrBox extends Box<string> {}

$ib = new IntBox();
$ib->payload = 42;
var_dump($ib->get());

$sb = new StrBox();
$sb->payload = "hi";
var_dump($sb->get());

// Now return values that violate the substituted type
$ib->payload = "not an int";
try {
    $ib->get();
} catch (TypeError $e) {
    echo "IntBox return rejects string: ", $e->getMessage(), "\n";
}

$sb->payload = 123;
try {
    $sb->get();
} catch (TypeError $e) {
    echo "StrBox return rejects int: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
string(2) "hi"
IntBox return rejects string: Box::get(): Return value must be of type int, string returned
StrBox return rejects int: Box::get(): Return value must be of type string, int returned
