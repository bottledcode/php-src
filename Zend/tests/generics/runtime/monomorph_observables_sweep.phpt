--TEST--
Monomorph observables sweep: var_dump/export, exceptions, __toString, WeakMap/WeakRef, FCC, class_parents, class_implements
--FILE--
<?php
class Box<T = int> {
    public function __construct(public T $value) {}
    public function __toString(): string { return "Box(" . $this->value . ")"; }
}

class IntBoxChild extends Box<int> {}

$b = new Box::<int>(42);

// var_dump and var_export use the canonical mono name.
var_dump($b);
var_export($b); echo "\n";

// __toString shows the substituted T-typed property.
echo "toString: ", $b, "\n";

// TypeError when the substituted constructor signature is violated. The error
// message references the property's declaring class (the bare base), not the
// monomorph — that's the standard PHP behaviour for typed-property errors and
// matches where the slot's metadata lives.
try {
    new Box::<int>("not int");
} catch (TypeError $e) {
    echo "type-err raised: int property rejects string\n";
}

// get_class / ::class / get_parent_class.
var_dump(get_class($b));
var_dump($b::class);
var_dump(get_parent_class("Box<int>"));

// class_parents / class_implements walk the mono chain.
$c = new IntBoxChild(1);
$parents = class_parents($c);
ksort($parents);
print_r(array_keys($parents));
print_r(array_keys(class_implements($c)));

// WeakMap keyed by a mono instance.
$wm = new WeakMap();
$wm[$b] = "tag";
var_dump($wm[$b]);

// WeakReference also.
$wr = WeakReference::create($b);
var_dump($wr->get() === $b);

// First-class callable on a mono method.
$f = $b->__toString(...);
var_dump($f());

// get_object_vars / get_class_vars / get_class_methods.
print_r(get_object_vars($b));
print_r(get_class_vars("Box<int>"));
$methods = get_class_methods("Box<int>");
sort($methods);
print_r($methods);
?>
--EXPECTF--
object(Box<int>)#%d (1) {
  ["value"]=>
  int(42)
}
\Box<int>::__set_state(array(
   'value' => 42,
))
toString: Box(42)
type-err raised: int property rejects string
string(8) "Box<int>"
string(8) "Box<int>"
string(3) "Box"
Array
(
    [0] => Box
    [1] => Box<int>
)
Array
(
    [0] => Stringable
)
string(3) "tag"
bool(true)
string(7) "Box(42)"
Array
(
    [value] => 42
)
Array
(
    [value] => %w
)
Array
(
    [0] => __construct
    [1] => __toString
)
