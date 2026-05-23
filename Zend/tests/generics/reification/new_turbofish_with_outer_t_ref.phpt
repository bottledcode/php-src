--TEST--
Reification: `new Box::<T>(...)` inside `function<T>(...)` resolves T against the caller frame's T-table at synth time, not the literal ref
--FILE--
<?php
// Direct repro of the Psl\Graph bug: a generic function's body constructs a
// generic class with a turbofish whose args reference the function's own
// type parameters. At runtime, the synth must substitute T against the
// frame's bindings — not embed the literal TYPE_PARAMETER refs into the
// monomorph args (which would produce a "Box<T,U>" class with un-resolvable
// arg_info and runtime TypeErrors mentioning the literal parameter name).

final readonly class Box<TNode = mixed, TWeight = mixed> {
    public function __construct(public array $items = []) {}
    public function get(TNode $key): mixed { return $this->items[$key] ?? null; }
}

function make<TNode = mixed, TWeight = mixed>(): Box {
    return new Box::<TNode, TWeight>([]);
}

function withItem<TNode = mixed, TWeight = mixed>(Box<TNode, TWeight> $b, TNode $key, mixed $val): Box {
    $items = $b->items;
    $items[$key] = $val;
    return new Box::<TNode, TWeight>($items);
}

// Concrete turbofish on the outer call — inner `new Box::<TNode, TWeight>(...)`
// must resolve to <string, int>, not <TNode, TWeight>.
$b = make::<string, int>();
var_dump($b::class);

$b = withItem::<string, int>($b, 'a', 1);
$b = withItem::<string, int>($b, 'b', 2);
var_dump($b::class);

// Method that's substituted on the monomorph should accept the bound key type.
var_dump($b->get('a'));
var_dump($b->get('b'));

// Repeat with different turbofish — distinct monomorph, no stale binding leak.
$s = make::<int, string>();
var_dump($s::class);
$s = withItem::<int, string>($s, 0, 'zero');
var_dump($s::class);
var_dump($s->get(0));

// Defaults case: turbofish-less call falls back to declared defaults
// (mixed, mixed), and inner `new Box::<TNode, TWeight>(...)` resolves against
// those — producing Box<mixed,mixed>, not Box<TNode,TWeight>.
$m = make();
var_dump($m::class);
?>
--EXPECT--
string(15) "Box<string,int>"
string(15) "Box<string,int>"
int(1)
int(2)
string(15) "Box<int,string>"
string(15) "Box<int,string>"
string(4) "zero"
string(16) "Box<mixed,mixed>"
