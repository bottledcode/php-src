--TEST--
Monomorph serialization: serialize/unserialize round-trips through the canonical class name
--FILE--
<?php
class Box<T = mixed> {
    public function __construct(public mixed $value) {}
}

$b = new Box::<int>(42);
$s = serialize($b);

// The serialized payload encodes the canonical class name.
echo $s, "\n";

// Unserialize materializes the monomorph (synthesizing if not yet present).
$b2 = unserialize($s);
var_dump($b2::class);
var_dump($b2 instanceof Box);
var_dump($b2->value);

// Round-trip identity through the canonical name.
var_dump($b::class === $b2::class);
?>
--EXPECTF--
O:8:"Box<int>":1:{s:5:"value";i:42;}
string(8) "Box<int>"
bool(true)
int(42)
bool(true)
