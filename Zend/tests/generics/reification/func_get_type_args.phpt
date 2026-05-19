--TEST--
Reification: func_get_type_args() reflects turbofish, defaults, and inference
--FILE--
<?php
class Foo {}
class Bar {}

function inspect<T : object, U : object = Foo>(T $x): array {
    return func_get_type_args();
}

var_dump(inspect::<Foo, Bar>(new Foo()));   // explicit turbofish for T and U
var_dump(inspect::<Bar>(new Bar()));        // U falls back to its default Foo
var_dump(inspect(new Bar()));               // T inferred from arg, U default
?>
--EXPECT--
array(2) {
  ["T"]=>
  string(3) "Foo"
  ["U"]=>
  string(3) "Bar"
}
array(2) {
  ["T"]=>
  string(3) "Bar"
  ["U"]=>
  string(3) "Foo"
}
array(2) {
  ["T"]=>
  string(3) "Bar"
  ["U"]=>
  string(3) "Foo"
}
