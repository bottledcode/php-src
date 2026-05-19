--TEST--
Inherited methods: substituted T parameter honors weak-mode coercion like any other type
--FILE--
<?php
// No declare(strict_types=1) — weak mode applies.

class Box<T> {
    public function take(T $x): T { return $x; }
}

class IntBox extends Box<int> {}
class StrBox extends Box<string> {}

// Numeric strings coerce to int under weak mode
var_dump((new IntBox())->take("42"));

// Scalars coerce to string under weak mode
var_dump((new StrBox())->take(123));

// Non-coercible still throws
try {
    (new IntBox())->take("not a number");
} catch (TypeError $e) {
    echo "rejected: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
string(3) "123"
rejected: Box::take(): Argument #1 ($x) must be of type int, string given, called in %s on line %d
