--TEST--
Errors: a forwarded TYPE_PARAMETER ref in a `new C::<T>()` turbofish errors at the synth site when nothing pins T and its bound is not a class
--FILE--
<?php
// Companion to type_param_in_new_expression.phpt (`new T()`). Same shape, but
// the unresolvable T sits inside a turbofish on `new C::<T>(...)` instead of
// being the class itself. Both must produce the same diagnostic, fired at the
// `new` site — not silently produce a broken-refs monomorph that crashes far
// from the cause when a method is later called on it.
final readonly class Box<U = mixed> {
    public function __construct(public array $items = []) {}
}

function makeBox<T>(): Box {
    return new Box::<T>([]);
}

try {
    makeBox();
    echo "no error??\n";
} catch (Error $e) {
    echo "ok: " . $e->getMessage() . "\n";
}

// A class-bound on the outer T gives a fallback target.
class Base {}
function makeBoxFromBound<T : Base>(): Box {
    return new Box::<T>([]);
}
$b = makeBoxFromBound();
var_dump($b::class);

// Turbofish supplied: no error, T resolves to the supplied type.
$b = makeBox::<int>();
var_dump($b::class);
?>
--EXPECT--
ok: Cannot resolve generic type parameter T at runtime: no binding was supplied and its bound is not a class
string(9) "Box<Base>"
string(8) "Box<int>"
