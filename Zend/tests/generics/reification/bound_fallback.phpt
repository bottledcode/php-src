--TEST--
Reification: when no binding is supplied, the parameter's class bound is the fallback
--FILE--
<?php
class Base { public string $kind = "base"; }
class Derived extends Base { public string $kind = "derived"; }

function makeDefault<T : Base>(): T {
    // No turbofish, no default, no inferable arg. T falls back to its bound.
    return new T();
}

var_dump(makeDefault()->kind);

// Inference still wins over the bound.
function makeFromArg<T : Base>(T $hint): T {
    return new T();
}
var_dump(makeFromArg(new Derived())->kind);

// Turbofish still wins over the bound.
var_dump(makeDefault::<Derived>()->kind);
?>
--EXPECT--
string(4) "base"
string(7) "derived"
string(7) "derived"
