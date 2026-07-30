--TEST--
Implements-with-args: internal interfaces behind a generic interface still get their implement handlers
--FILE--
<?php
interface Coll<T> extends IteratorAggregate, Countable {}

class Bag<T> implements Coll<T> {
    private array $items = [1, 2, 3];
    public function getIterator(): Iterator { return new ArrayIterator($this->items); }
    public function count(): int { return count($this->items); }
}

$b = new Bag::<int>();

$names = (new ReflectionClass($b))->getInterfaceNames();
sort($names);
echo implode(', ', $names), "\n";

foreach ($b as $v) {
    echo $v;
}
echo "\n";
var_dump(count($b));
var_dump($b instanceof Traversable);
var_dump($b instanceof Coll::<int>);
?>
--EXPECT--
Coll, Coll<int>, Countable, IteratorAggregate, Traversable
123
int(3)
bool(true)
bool(true)
