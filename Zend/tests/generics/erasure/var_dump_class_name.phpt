--TEST--
Monomorphization: var_dump shows the canonical monomorph name
--FILE--
<?php
class Box<T> {
    public int $x = 1;
}
var_dump(new Box::<int>);
?>
--EXPECT--
object(Box<int>)#1 (1) {
  ["x"]=>
  int(1)
}
