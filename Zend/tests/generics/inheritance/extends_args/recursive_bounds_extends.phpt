--TEST--
Extends-with-args: subclass with recursive-bound type parameters extending a generic parent
--FILE--
<?php
class Box<T = mixed> {}

class Pair<T: Box<U>, U: Box<T>> {
    public function __construct(public Box $left, public Box $right) {}
}

// Subclass binds Pair's parameters to two concrete monomorphs.
class StrictPair<A: Box<B>, B: Box<A>> extends Pair<A, B> {}

// Two further subclasses, both passing concrete args to StrictPair.
class IntFirst extends StrictPair<Box<int>, Box<string>> {}
class StrFirst extends StrictPair<Box<string>, Box<int>> {}

$p1 = new IntFirst(new Box(), new Box());
$p2 = new StrFirst(new Box(), new Box());

echo (new ReflectionClass($p1))->getParentClass()->getName(), "\n";
echo (new ReflectionClass($p2))->getParentClass()->getName(), "\n";

// Distinct StrictPair monos.
var_dump((new ReflectionClass($p1))->getParentClass()->getName()
    !== (new ReflectionClass($p2))->getParentClass()->getName());

// Both transitively extend Pair (bare base, two hops up).
var_dump($p1 instanceof Pair);
var_dump($p2 instanceof Pair);
?>
--EXPECT--
StrictPair<Box<int>,Box<string>>
StrictPair<Box<string>,Box<int>>
bool(true)
bool(true)
bool(true)
