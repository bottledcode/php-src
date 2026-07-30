--TEST--
Implements-with-args: a generic interface's own generic parents are attached as monomorphs
--FILE--
<?php
interface ReadOnlyCollection<out T> {
    public function get(int $i): T;
}

interface Both<out T> extends ReadOnlyCollection<T> {}

class Collection<T> implements Both<T> {
    public function get(int $i): T { return 1; }
}

function takesReadOnly(ReadOnlyCollection<int> $c): string { return get_class($c); }

$c = new Collection::<int>();

$names = (new ReflectionClass($c))->getInterfaceNames();
sort($names);
echo implode("\n", $names), "\n";

// The transitive ancestor's monomorph is a real supertype, not only the erased base.
var_dump($c instanceof Both::<int>);
var_dump($c instanceof ReadOnlyCollection::<int>);
var_dump($c instanceof ReadOnlyCollection);

// A different instantiation of the same ancestor must not match.
var_dump($c instanceof ReadOnlyCollection::<string>);

// Type checks against the transitive ancestor accept the value.
echo takesReadOnly($c), "\n";
?>
--EXPECT--
Both
Both<int>
ReadOnlyCollection
ReadOnlyCollection<int>
bool(true)
bool(true)
bool(true)
bool(false)
Collection<int>
