--TEST--
Implements-with-args: a shared generic ancestor reached through two paths is attached once
--FILE--
<?php
interface RO<out T> {
    public function get(): T;
}
interface A<out T> extends RO<T> {}
interface B<out T> extends RO<T> {}

class D<T> implements A<T>, B<T> {
    public function get(): T { return 1; }
}

$d = new D::<int>();

$names = (new ReflectionClass($d))->getInterfaceNames();
sort($names);
echo implode(', ', $names), "\n";

// Reached via both A<int> and B<int>, but listed a single time.
$counts = array_count_values($names);
var_dump($counts['RO<int>']);

var_dump($d instanceof A::<int>);
var_dump($d instanceof B::<int>);
var_dump($d instanceof RO::<int>);
?>
--EXPECT--
A, A<int>, B, B<int>, RO, RO<int>
int(1)
bool(true)
bool(true)
bool(true)
