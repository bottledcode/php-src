--TEST--
Reification: variadic T parameter is checked even when T comes from a closure's captured frame, not the closure's own turbofish
--FILE--
<?php
// A closure declared inside a generic function captures the outer T-table.
// When the closure is invoked, its variadic parameter typed `T` should be
// checked against the OUTER frame's binding for T — every element, not just
// the first.

function maker<T>(): Closure {
    return function(T ...$xs): int {
        return count($xs);
    };
}

$intMaker = maker::<int>();
var_dump($intMaker(1, 2, 3));   // positive: all int

try {
    $intMaker(1, "bad", 3);
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}

try {
    $intMaker(1, 2, [4]);
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}

class Animal {}
class Dog extends Animal {}
class Cat extends Animal {}

function dogMaker<T>(): Closure {
    return function(T ...$xs): int {
        return count($xs);
    };
}

$dm = dogMaker::<Dog>();
var_dump($dm(new Dog, new Dog));

try {
    $dm(new Dog, new Cat);
} catch (TypeError $e) {
    echo "3: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(3)
1: {closure:maker():%d}(): Argument #2 ($xs) must be of type int, string given
2: {closure:maker():%d}(): Argument #3 ($xs) must be of type int, array given
int(2)
3: {closure:dogMaker():%d}(): Argument #2 ($xs) must be of type Dog, Cat given
