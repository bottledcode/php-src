--TEST--
Reification: `new SelfClass::<T>(...)` inside a generic class method resolves T against the instance's monomorph args at synth time
--FILE--
<?php
// Companion to new_turbofish_with_outer_t_ref.phpt — same fix but the T-ref
// origin is CLASS_LIKE: a generic class's method does `new Self::<T>(...)`
// using its own class-level T's. Runtime synth must walk the called scope
// up to the lexical class's monomorph descendant to resolve the binding.

final readonly class Bag<T = mixed> {
    public function __construct(public array $items = []) {}

    public function withItem(T $item): Bag {
        $items = $this->items;
        $items[] = $item;
        // Forward T explicitly — must resolve against the current instance's
        // monomorph (Bag<int> stays Bag<int>, not Bag<T>).
        return new Bag::<T>($items);
    }
}

$b = new Bag::<int>([1, 2, 3]);
var_dump($b::class);
$b = $b->withItem(4);
var_dump($b::class);
$b = $b->withItem(5);
var_dump($b::class);

$s = new Bag::<string>(['a']);
var_dump($s::class);
$s = $s->withItem('b');
var_dump($s::class);

// Calling withItem on a bare-default Bag<mixed> still works — forwarding
// resolves T to its (default) mixed binding from the monomorph.
$d = new Bag();
var_dump($d::class);
$d = $d->withItem(42);
var_dump($d::class);
?>
--EXPECT--
string(8) "Bag<int>"
string(8) "Bag<int>"
string(8) "Bag<int>"
string(11) "Bag<string>"
string(11) "Bag<string>"
string(10) "Bag<mixed>"
string(10) "Bag<mixed>"
