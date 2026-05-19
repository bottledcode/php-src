--TEST--
Monomorph isolation: each synthesized monomorph has its own static-property storage
--FILE--
<?php
class Counter<T = int> {
    public static int $count = 0;
    public static array $items = [];
}

// Force synthesis of two distinct monomorphs.
new Counter::<int>();
new Counter::<string>();

$intCls = "Counter<int>";
$strCls = "Counter<string>";

$intCls::$count = 5;
$strCls::$count = 10;

$intCls::$items[] = "int-a";
$intCls::$items[] = "int-b";
$strCls::$items[] = "string-only";

var_dump($intCls::$count);
var_dump($strCls::$count);
var_dump(Counter::$count);

var_dump($intCls::$items);
var_dump($strCls::$items);
var_dump(Counter::$items);
?>
--EXPECT--
int(5)
int(10)
int(0)
array(2) {
  [0]=>
  string(5) "int-a"
  [1]=>
  string(5) "int-b"
}
array(1) {
  [0]=>
  string(11) "string-only"
}
array(0) {
}
