--TEST--
Reification: instanceof Box<T> with function-level T resolves against the current frame's turbofish bindings
--FILE--
<?php
class Box<T> {}

function isBoxOf<T>(mixed $x): bool {
    return $x instanceof Box<T>;
}

var_dump(isBoxOf::<int>(new Box::<int>));      // true
var_dump(isBoxOf::<int>(new Box::<string>));   // false
var_dump(isBoxOf::<string>(new Box::<string>));// true
var_dump(isBoxOf::<int>(42));                  // false: not an object
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
