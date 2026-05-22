--TEST--
Reification: typed properties whose declared type is `T` check the reified binding on assignment
--FILE--
<?php
// Monomorphized class case: new Box::<int>() — assignments to $value must
// check against int, not the erased "mixed" bound.

class Box<T> {
    public T $value;
}

$b = new Box::<int>();
$b->value = 42;
var_dump($b->value);

try {
    $b->value = "not-an-int";
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}

// Object T case.
class Item {}
class Other {}

class Holder<T : object> {
    public T $thing;
}

$h = new Holder::<Item>();
$h->thing = new Item;
var_dump(get_class($h->thing));

try {
    $h->thing = new Other;
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}

// Nullable T.
class MaybeBox<T> {
    public ?T $value = null;
}

$mb = new MaybeBox::<int>();
$mb->value = 7;
var_dump($mb->value);
$mb->value = null;
var_dump($mb->value);

try {
    $mb->value = "wrong";
} catch (TypeError $e) {
    echo "3: ", $e->getMessage(), "\n";
}
?>
--EXPECT--
int(42)
1: Cannot assign string to property Box::$value of type int
string(4) "Item"
2: Cannot assign Other to property Holder::$thing of type Item
int(7)
NULL
3: Cannot assign string to property MaybeBox::$value of type ?int
