--TEST--
Monomorph canonicalization: union and intersection arguments collapse to the same monomorph regardless of source order
--FILE--
<?php
interface Stringable1 { public function s1(): string; }
interface Stringable2 { public function s2(): string; }

class Box<T = mixed> {
    public function __construct(public mixed $value) {}
}

// Union args canonicalize: order doesn't matter.
$a = new Box::<int|string>(0);
$b = new Box::<string|int>(0);
var_dump($a::class);
var_dump($b::class);
var_dump($a::class === $b::class);

// Intersection args canonicalize: order doesn't matter.
$c = new Box::<Stringable1&Stringable2>(new class implements Stringable1, Stringable2 {
    public function s1(): string { return "s1"; }
    public function s2(): string { return "s2"; }
});
$d = new Box::<Stringable2&Stringable1>(new class implements Stringable1, Stringable2 {
    public function s1(): string { return "s1"; }
    public function s2(): string { return "s2"; }
});
var_dump($c::class);
var_dump($d::class);
var_dump($c::class === $d::class);

// Two equivalent canonical forms produce the same monomorph class.
$x = new Box::<int|string|float>(1);
$y = new Box::<float|string|int>(1);
var_dump($x::class === $y::class);
?>
--EXPECT--
string(15) "Box<int|string>"
string(15) "Box<int|string>"
bool(true)
string(28) "Box<Stringable1&Stringable2>"
string(28) "Box<Stringable1&Stringable2>"
bool(true)
bool(true)
