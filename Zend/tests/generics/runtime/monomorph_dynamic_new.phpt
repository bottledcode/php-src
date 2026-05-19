--TEST--
Monomorph lookup: `new $name()` with a canonical name string synthesizes on demand
--FILE--
<?php
class Box<T = mixed> {
    public function __construct(public mixed $value) {}
}

// Pre-warm so the class entry exists for the explicit form too.
new Box::<int>(0);

$name = "Box<int>";
$obj = new $name(42);
var_dump($obj::class);
var_dump($obj instanceof Box);
var_dump($obj->value);

// Names not yet synthesized are created on demand.
$other = "Box<string>";
$obj2 = new $other("hi");
var_dump($obj2::class);
var_dump($obj2 instanceof Box);

// Identity: two dynamic news of the same canonical name produce instances of the same class.
$obj3 = new $name(7);
var_dump($obj3::class === $obj::class);
?>
--EXPECT--
string(8) "Box<int>"
bool(true)
int(42)
string(11) "Box<string>"
bool(true)
bool(true)
