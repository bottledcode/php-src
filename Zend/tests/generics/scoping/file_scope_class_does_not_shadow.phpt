--TEST--
Scoping: a file-scope class does NOT shadow a generic type parameter
--FILE--
<?php
class T { public int $tag = 42; }
function f<T : \T>(): T {
    // Inside f, the bare name T refers to the generic parameter, not the
    // file-scope class T. With no binding supplied, the resolver falls back
    // to the parameter's bound (the file-scope class via \T) — proving the
    // parameter name shadowed the class name in the body.
    return new T();
}
$obj = f();
var_dump($obj->tag);
?>
--EXPECT--
int(42)
