--TEST--
Reification: `new static()` reproduces the exact runtime type; `new self::<...>` re-applies the lexical template
--FILE--
<?php
// `new static()` (no turbofish) is the correct way to construct "another of
// exactly me": the runtime type already carries its type arguments, so a
// monomorph clones to the same monomorph and a subclass clones to itself.
// `new self::<...>` remains the way to apply type arguments to the enclosing
// (lexical) class — including re-binding to different args.
class C<TKey, T> {
    public function __construct(private array $e = []) {}

    public function dupStatic(): static  { return new static($this->e); }
    public function dupSelf(): static    { return new self::<TKey, T>($this->e); }
    public function swapSelf(): object   { return new self::<T, TKey>($this->e); }
    public function reSelf(): object     { return new self::<float, float>($this->e); }
}

$o = new C::<int, string>([1, 2]);
echo "orig:      ", $o::class, "\n";
echo "dupStatic: ", $o->dupStatic()::class, "\n";
echo "dupSelf:   ", $o->dupSelf()::class, "\n";
echo "swapSelf:  ", $o->swapSelf()::class, "\n";
echo "reSelf:    ", $o->reSelf()::class, "\n";

// `new static()` through a non-generic subclass of a monomorph reproduces the
// subclass, exactly as plain LSB does.
class Box<T> {
    public function __construct(public mixed $v = null) {}
    public function copy(): static { return new static($this->v); }
}
class IntBox extends Box<int> {}

$b = new IntBox(5);
echo "IntBox:      ", $b::class, "\n";
echo "IntBox copy: ", $b->copy()::class, "\n";
?>
--EXPECT--
orig:      C<int,string>
dupStatic: C<int,string>
dupSelf:   C<int,string>
swapSelf:  C<string,int>
reSelf:    C<float,float>
IntBox:      IntBox
IntBox copy: IntBox
