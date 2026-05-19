--TEST--
Reification: each call frame has its own T-table; recursion across different bindings does not leak
--FILE--
<?php
class A {}
class B {}

function pair<T : object, U : object>(int $depth): array {
    if ($depth === 0) {
        return ["T" => T::class, "U" => U::class];
    }
    // Recurse with T and U swapped; on return our slot must still read A/B.
    $inner = pair::<U, T>($depth - 1);
    return [
        "outer_T" => T::class,
        "outer_U" => U::class,
        "inner"   => $inner,
    ];
}

var_dump(pair::<A, B>(2));
?>
--EXPECT--
array(3) {
  ["outer_T"]=>
  string(1) "A"
  ["outer_U"]=>
  string(1) "B"
  ["inner"]=>
  array(3) {
    ["outer_T"]=>
    string(1) "B"
    ["outer_U"]=>
    string(1) "A"
    ["inner"]=>
    array(2) {
      ["T"]=>
      string(1) "A"
      ["U"]=>
      string(1) "B"
    }
  }
}
