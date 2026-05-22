--TEST--
Turbofish arity: new <Class>::<...> enforces arity against class generic parameters
--FILE--
<?php
class Box<T> {
    public function __construct(public int $v) {}
}
class Pair<K, V = mixed> {}

try { new Box::<int, string>(1); }
catch (ArgumentCountError $e) { echo $e->getMessage(), "\n"; }

// Pair: K required, V has default - so 1 or 2 args is OK, 0 or 3 is not.
// With monomorphization, the missing trailing arg is filled from the default,
// so the canonical name reflects the substituted Pair<int,mixed>.
$p = new Pair::<int>();
echo get_class($p), "\n";

try { new Pair::<int, string, float>(); }
catch (ArgumentCountError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECT--
Too many generic type arguments to new Box, 2 passed and exactly 1 expected
Pair<int,mixed>
Too many generic type arguments to new Pair, 3 passed and at most 2 expected
