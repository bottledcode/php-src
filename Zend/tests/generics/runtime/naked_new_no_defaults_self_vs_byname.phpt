--TEST--
Naked new in a no-defaults generic class: `new self()` binds from the frame, by-name `new C()` is an error
--FILE--
<?php
// In a no-defaults generic class, `new self()` resolves to the monomorph the
// current frame is running as (the clone idiom) — `self` means "this class with
// my type arguments", so it is unambiguous and keeps the live binding instead
// of collapsing to a bare instance. `new static()` likewise tracks the called
// scope. A by-name `new C()` is rejected: its type arguments are ambiguous, so
// it must be written `new C::<...>()`.
final readonly class Box<+T> {
    public function __construct(public T $value) {}
    public function cloneSelf(): self { return new self($this->value); }
    public function cloneStatic(): static { return new static($this->value); }
    public function cloneByName(): Box { return new Box($this->value); }
}

$b = new Box::<int>(42);

// `new self()` -> the live monomorph, not a bare Box.
$s = $b->cloneSelf();
var_dump($s::class);
var_dump($s->value);

// `new static()` -> the called scope's monomorph.
$t = $b->cloneStatic();
var_dump($t::class);

// `new Box()` by name (lexical self-reference) -> ambiguous type args -> a
// catchable error at runtime. (An external by-name `new Box()` the compiler can
// see is rejected at compile time instead; see naked_new_without_defaults.phpt.)
try {
    $b->cloneByName();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
string(8) "Box<int>"
int(42)
string(8) "Box<int>"
Cannot instantiate generic class Box without type arguments; type parameter T has no default
