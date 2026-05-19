--TEST--
Reification: turbofish whose arg is an outer T resolves against the caller's T-table
--FILE--
<?php
class Foo {}

function inner<U : object>(): string {
    return U::class;
}

function outer<T : object>(): string {
    // The turbofish ::<T> here doesn't have a literal class name; it names
    // the enclosing parameter T. The runtime must look T up in the caller
    // frame's T-table and pass that to inner.
    return inner::<T>();
}

echo outer::<Foo>(), "\n";
?>
--EXPECT--
Foo
