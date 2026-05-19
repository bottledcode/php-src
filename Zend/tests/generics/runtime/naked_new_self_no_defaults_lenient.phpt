--TEST--
Lexical `new self()` and `new ThisClass()` in a no-defaults generic class fall back to a bare instance
--FILE--
<?php
// `new self()` inside Box<+T> with no default doesn't error — it creates a
// bare Box instance, preserving the lexical-self semantic where T is in scope
// but can't be statically bound to a concrete type at the call site.
final readonly class Box<+T> {
    public function __construct(public T $value) {}
    public function cloneSelf(): self { return new self($this->value); }
    public function cloneByName(): Box { return new Box($this->value); }
}

$b = new Box::<int>(42);
$c1 = $b->cloneSelf();
$c2 = $b->cloneByName();

var_dump($c1::class);
var_dump($c2::class);
var_dump($c1->value);
var_dump($c2->value);
?>
--EXPECT--
string(3) "Box"
string(3) "Box"
int(42)
int(42)
