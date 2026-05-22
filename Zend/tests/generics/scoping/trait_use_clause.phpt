--TEST--
Scoping: a trait `use Holder<T>` flows the using class's T-bound into the trait method's signature
--FILE--
<?php
trait Holder<X> {
    public function tag(X $x): X { return $x; }
}
class Box<T : object> {
    use Holder<T>;
}

// The trait method's X gets substituted with Box's T, which is bounded to
// object. The erased signature reflects that bound — not the trait's
// original unbounded "mixed".
echo (new ReflectionClass('Box'))->getMethod('tag')->getReturnType()->getName(), "\n";

// On a bare (unmonomorphed) Box, the runtime check enforces the bound.
$b = new Box;
echo get_class($b), "\n";
var_dump($b->tag(new stdClass) instanceof stdClass);

try {
    $b->tag(42);
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
object
Box
bool(true)
rejected: Box::tag(): Argument #1 ($x) must be of type object, int given%S
