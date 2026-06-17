--TEST--
Turbofish with a nested generic type argument erases the substituted leaf to the monomorph class
--FILE--
<?php

class L2<T> {}
class L3<T> {}
class DBox<T> {}

function id_gen<T>(T $x): T { return $x; }

$d1 = new DBox::<int>();
$d2 = new DBox::<L2<int>>();
$d3 = new DBox::<L2<L3<int>>>();

/* T is a bare leaf substituted with a concrete generic instantiation. The
 * binding arrives as a pre-erasure named-with-args type; it must be folded to
 * the monomorph's canonical class name so the reified RECV / return checks see
 * the same erased shape the value carries. Before the fix, depth >= 2 read the
 * named-with-args payload as a class-name string (bogus huge allocation) and
 * depth 1 raised a spurious TypeError. */
var_dump(id_gen::<DBox<int>>($d1) === $d1);
var_dump(id_gen::<DBox<L2<int>>>($d2) === $d2);
var_dump(id_gen::<DBox<L2<L3<int>>>>($d3) === $d3);

echo "done\n";
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
done
