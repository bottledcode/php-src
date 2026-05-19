--TEST--
Dynamic `new $name()`: bare generic with defaults synthesizes; without defaults throws
--FILE--
<?php
class Box<T = int> {
    public function __construct(public mixed $value) {}
}

class NoDefault<T> {
    public function __construct(public mixed $value) {}
}

// Dynamic new of a bare generic with defaults → defaults monomorph.
$cls = 'Box';
$b = new $cls(42);
var_dump($b::class);

// Dynamic new of a canonical name → that monomorph.
$cls2 = 'Box<string>';
$b2 = new $cls2("x");
var_dump($b2::class);

// Dynamic new with turbofish: the explicit args win.
$cls3 = 'Box';
$b3 = new $cls3::<float>(1.5);
var_dump($b3::class);

// Dynamic new of a no-defaults bare generic → Error.
try {
    $cls4 = 'NoDefault';
    new $cls4("oops");
} catch (Error $e) {
    echo "err: ", $e->getMessage(), "\n";
}

// But turbofish on the same bare name works.
$cls5 = 'NoDefault';
$b5 = new $cls5::<int>(7);
var_dump($b5::class);
?>
--EXPECT--
string(8) "Box<int>"
string(11) "Box<string>"
string(10) "Box<float>"
err: Cannot instantiate generic class NoDefault without type arguments via dynamic class name; no defaults declared
string(14) "NoDefault<int>"
