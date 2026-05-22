--TEST--
Reification: variadic T parameter checks every element against the reified T binding, not just the first
--FILE--
<?php
// Each value passed to `T ...$xs` must satisfy the reified T binding; the
// erased arg_info (mixed for unbounded T) accepts anything by itself, so
// without the reified sweep elements 2+ would silently leak through.

function sum<T>(T ...$xs): T {
    $r = $xs[0];
    for ($i = 1; $i < count($xs); $i++) $r += $xs[$i];
    return $r;
}

// Positive: all three elements are ints.
var_dump(sum::<int>(1, 2, 3));

// Negative: the second element violates T; the error names that element
// (Argument #2) with the resolved T type ("int").
try {
    sum::<int>(1, "abc", 3);
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}

// Negative: the third element violates T.
try {
    sum::<int>(1, 2, "xyz");
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}

// Variadic with leading non-variadic param: positional 1 ($prefix) is a
// plain string, the variadic slot is `T ...`. The reified sweep starts at
// the variadic position and reports element index correctly.
function concat<T>(string $prefix, T ...$xs): string {
    $r = $prefix;
    foreach ($xs as $x) $r .= (string) $x;
    return $r;
}

var_dump(concat::<int>(":: ", 1, 2, 3));

try {
    concat::<int>(":: ", 1, [4, 5], 3);
} catch (TypeError $e) {
    echo "3: ", $e->getMessage(), "\n";
}

// Object T over a variadic: a value of the wrong class fires with the bound
// class name, not the parameter letter.
class Animal {}
class Dog extends Animal {}
class Cat extends Animal {}

function herd<T>(T ...$xs): int {
    return count($xs);
}

var_dump(herd::<Dog>(new Dog, new Dog, new Dog));

try {
    herd::<Dog>(new Dog, new Cat, new Dog);
} catch (TypeError $e) {
    echo "4: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(6)
1: sum(): Argument #2 ($xs) must be of type int, string given
2: sum(): Argument #3 ($xs) must be of type int, string given
string(6) ":: 123"
3: concat(): Argument #3 ($xs) must be of type int, array given
int(3)
4: herd(): Argument #2 ($xs) must be of type Dog, Cat given
