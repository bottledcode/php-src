--TEST--
Errors: bare function-level type parameter used in `instanceof T` errors at runtime when nothing pins it
--FILE--
<?php
function f<T>($x): bool {
    return $x instanceof T;
}
f(new stdClass());
?>
--EXPECTF--
Fatal error: Uncaught Error: Cannot resolve generic type parameter T at runtime: no binding was supplied and its bound is not a class in %s:%d
Stack trace:
#0 %s(%d): f(Object(stdClass))
#1 {main}
  thrown in %s on line %d
