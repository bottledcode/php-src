--TEST--
Reification: explicit turbofish populates the T-table; `new T()` in body resolves to the supplied class
--FILE--
<?php
class Foo { public string $kind = "foo"; }
class Bar { public string $kind = "bar"; }

function make<T : object>(): T {
    return new T();
}

var_dump(make::<Foo>()->kind);
var_dump(make::<Bar>()->kind);
?>
--EXPECT--
string(3) "foo"
string(3) "bar"
