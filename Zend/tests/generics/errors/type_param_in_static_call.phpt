--TEST--
Errors: bare function-level type parameter used as a static-call target errors at runtime when nothing pins it
--FILE--
<?php
function f<T>(): void {
    T::foo();
}
f();
?>
--EXPECTF--
Fatal error: Uncaught Error: Cannot resolve generic type parameter T at runtime: no binding was supplied and its bound is not a class in %s:%d
Stack trace:
#0 %s(%d): f()
#1 {main}
  thrown in %s on line %d
