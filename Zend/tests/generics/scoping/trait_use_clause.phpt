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

// On a monomorph Box<stdClass>, the trait method's X is substituted to the
// concrete argument and the runtime check enforces it.
$b = new Box::<stdClass>();
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
Box<stdClass>
bool(true)
rejected: Box::tag(): Argument #1 ($x) must be of type stdClass, int given%S
