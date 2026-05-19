--TEST--
Reification: T is inferred from an argument whose declared type is exactly T
--FILE--
<?php
class Foo { public string $kind = "foo"; }
class Bar { public string $kind = "bar"; }

function kind<T : object>(T $x): string {
    return T::class;
}

echo kind(new Foo()), "\n";
echo kind(new Bar()), "\n";

// Turbofish overrides inference.
echo kind::<Foo>(new Bar()), "\n";
?>
--EXPECT--
Foo
Bar
Foo
