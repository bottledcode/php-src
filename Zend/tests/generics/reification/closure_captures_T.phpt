--TEST--
Reification: a closure returned from a generic function captures its T via an eagerly-resolved variable
--FILE--
<?php
class Foo {}
class Bar {}

// The closure runs in its own frame with no T-table, so referencing T directly
// inside the closure body would error. The workaround is to resolve T once
// inside the outer function (where the T-table lives) and capture the result.
function fact<T : object>(): Closure {
    $name = T::class;
    return fn() => $name;
}

echo fact::<Foo>()(), "\n";
echo fact::<Bar>()(), "\n";
?>
--EXPECT--
Foo
Bar
