--TEST--
Reflection surface for monomorphs: isGeneric, getGenericParameters, newInstance/Args/WithoutConstructor
--FILE--
<?php
class Box<T = int> {
    public function __construct(public mixed $value) {}
    public function get(): T { return $this->value; }
}

class NoDefault<T> {}

$rcBase = new ReflectionClass(Box::class);
$rcMono = new ReflectionClass("Box<int>");

// Generic-template vs concrete-instantiation flags.
var_dump($rcBase->isGeneric());
var_dump($rcMono->isGeneric());
var_dump(count($rcBase->getGenericParameters()));
var_dump(count($rcMono->getGenericParameters()));

// Reflecting the base class and calling newInstance synthesizes the defaults
// monomorph (same contract as `new Box()`).
$bb = $rcBase->newInstance(42);
var_dump($bb::class);

// Reflecting a monomorph instantiates that monomorph.
$bm = $rcMono->newInstance(7);
var_dump($bm::class);

// newInstanceArgs honours the same synthesis path.
$ba = $rcMono->newInstanceArgs([99]);
var_dump($ba::class);

// newInstanceWithoutConstructor too.
$bw = (new ReflectionClass("Box<string>"))->newInstanceWithoutConstructor();
var_dump($bw::class);

// And newInstance on a no-defaults generic throws.
try {
    (new ReflectionClass(NoDefault::class))->newInstance();
} catch (Error $e) {
    echo "err: ", $e->getMessage(), "\n";
}

// Substituted method signature is visible on the monomorph.
echo "Box<int>::get returns: ", $rcMono->getMethod('get')->getReturnType(), "\n";
?>
--EXPECT--
bool(true)
bool(false)
int(1)
int(0)
string(8) "Box<int>"
string(8) "Box<int>"
string(8) "Box<int>"
string(11) "Box<string>"
err: Cannot instantiate generic class NoDefault without type arguments; type parameter T has no default
Box<int>::get returns: int
