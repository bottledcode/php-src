--TEST--
Monomorph canonicalization: DNF type-args sort to a canonical form (intersection-sorted, union-sorted)
--FILE--
<?php
interface A {}
interface B {}
interface C {}
interface D {}
interface E {}

class Box<T = mixed> {}

// Same canonical class entry regardless of source order.
$names = [
    new Box::<(A&B)|(C&D)>(),
    new Box::<(B&A)|(D&C)>(),
    new Box::<(C&D)|(A&B)>(),
    new Box::<(A&B)|(D&C)>(),
    new Box::<(B&A)|(C&D)>(),
];

$canonical = $names[0]::class;
echo "canonical: $canonical\n";
foreach ($names as $i => $b) {
    var_dump($b::class === $canonical);
}

// Three-way union of intersections.
$x = new Box::<(A&B)|(C&D)|(D&E)>();
$y = new Box::<(D&E)|(B&A)|(D&C)>();
var_dump($x::class);
var_dump($y::class === $x::class);

// Mixed scalars and intersections in a union.
$p = new Box::<int|(A&B)|string>();
$q = new Box::<(B&A)|string|int>();
var_dump($p::class);
var_dump($q::class === $p::class);
?>
--EXPECT--
canonical: Box<(A&B)|(C&D)>
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(22) "Box<(A&B)|(C&D)|(D&E)>"
bool(true)
string(21) "Box<(A&B)|int|string>"
bool(true)
