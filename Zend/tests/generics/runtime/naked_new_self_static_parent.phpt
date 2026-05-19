--TEST--
Naked new self/static/parent inside generic class bodies
--FILE--
<?php
class Box<T = int> {
    public function __construct(public mixed $value) {}
    public static function makeSelf(mixed $v): self { return new self($v); }
    public static function makeStatic(mixed $v): static { return new static($v); }
    public static function makeBare(mixed $v): Box { return new Box($v); }
}

class IntBox extends Box<int> {
    public static function makeStatic(mixed $v): static { return new static($v); }
    public function makeParent(): Box { return new parent($this->value); }
}

class StrBox extends Box<string> {
    public function makeParent(): Box { return new parent($this->value); }
}

// `new self()` → lexical class with defaults applied.
var_dump(Box::makeSelf(1)::class);
// `new Box()` (literal) inside Box's static method also routes through the
// same rewrite (lexical self-reference).
var_dump(Box::makeBare(2)::class);
// `new static()` from Box → defaults mono; from IntBox → IntBox (no params).
var_dump(Box::makeStatic(3)::class);
var_dump(IntBox::makeStatic(4)::class);

// `new parent()` uses the extends args.
var_dump((new IntBox(5))->makeParent()::class);
var_dump((new StrBox("x"))->makeParent()::class);
?>
--EXPECT--
string(8) "Box<int>"
string(8) "Box<int>"
string(8) "Box<int>"
string(6) "IntBox"
string(8) "Box<int>"
string(11) "Box<string>"
