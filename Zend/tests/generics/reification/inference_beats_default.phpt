--TEST--
Reification: value-directed inference wins over a type parameter's declared default
--FILE--
<?php
// A is both inferable (appears as the value-parameter type `A $a`) AND has a
// declared default (`mixed`). Calling foo(new Foo()) must infer A = Foo from
// the argument; the default is only a fall-back for when inference can't fire.
class Foo {}
class Bar {}
class Sub extends Bar {}

class Box<T> {
    public function __construct(public T $value) {}
}

function foo<A = mixed>(A $a): array {
    return func_get_type_args();
}

// Inferred from the argument, not the `mixed` default.
var_dump(foo(new Foo()));
var_dump(foo(new Bar()));

// Explicit turbofish still wins over inference: A is pinned to Bar even though
// the argument's runtime class is Sub (which satisfies the Bar binding).
var_dump(foo::<Bar>(new Sub()));

// The default is still used when nothing pins the slot: U is not a value
// parameter, so it can't be inferred and falls back to its default.
function pick<A = mixed, U = Foo>(A $a): array {
    return func_get_type_args();
}
var_dump(pick(new Bar()));

// Partial turbofish: A is given explicitly, B is inferred from its argument
// even though B has a default — the monomorph fast path must defer to the
// inference path here.
function two<A, B = mixed>(A $a, B $b): array {
    return func_get_type_args();
}
var_dump(two::<Bar>(new Bar(), new Foo()));

// Return type Box<A> reifies to the inferred A, not Box<mixed>. The forwarded
// turbofish `new Box::<A>` resolves A against the (now inferred) binding.
function wrap<A = mixed>(A $a): Box<A> {
    return new Box::<A>($a);
}
$b = wrap(new Foo());
var_dump($b::class);
?>
--EXPECT--
array(1) {
  ["A"]=>
  string(3) "Foo"
}
array(1) {
  ["A"]=>
  string(3) "Bar"
}
array(1) {
  ["A"]=>
  string(3) "Bar"
}
array(2) {
  ["A"]=>
  string(3) "Bar"
  ["U"]=>
  string(3) "Foo"
}
array(2) {
  ["A"]=>
  string(3) "Bar"
  ["B"]=>
  string(3) "Foo"
}
string(8) "Box<Foo>"
