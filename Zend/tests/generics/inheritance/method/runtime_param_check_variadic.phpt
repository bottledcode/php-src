--TEST--
Inherited methods: substituted variadic T parameter is checked at runtime on the child
--FILE--
<?php
class Box<T> {
    public function takeMany(T ...$xs): array { return $xs; }
}

class IntBox extends Box<int> {}

$ib = new IntBox();
var_dump($ib->takeMany(1, 2, 3));

try {
    $ib->takeMany(1, "two", 3);
} catch (TypeError $e) {
    echo "rejects mixed: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
rejects mixed: Box::takeMany(): Argument #2 must be of type int, string given, called in %s on line %d
