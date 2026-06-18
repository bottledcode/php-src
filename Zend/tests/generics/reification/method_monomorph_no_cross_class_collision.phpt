--TEST--
Reification: same-named generic methods in different classes get distinct monomorphs
--FILE--
<?php
// Each generic method monomorphizes to a name built from the method name + type
// args. Without the declaring scope in the function-table key, E::pick<mixed>
// and F::pick<mixed> collide and the second class's call dispatches to the
// first class's body.
class E<TK = mixed> { public function pick<U = mixed>($x) { return 'E::pick'; } }
class F<TK = mixed> { public function pick<U = mixed>($x) { return 'F::pick'; } }

var_dump((new E)->pick(1));
var_dump((new F)->pick(1));

// Order-independent: resolving F first must not poison E either.
class G<TK = mixed> { public function take<U = mixed>($x) { return 'G::take'; } }
class H<TK = mixed> { public function take<U = mixed>($x) { return 'H::take'; } }

var_dump((new H)->take(1));
var_dump((new G)->take(1));

// Same method called twice on the same class still reuses one monomorph.
var_dump((new E)->pick(2));

// Turbofish form must also stay class-distinct.
var_dump((new E)->pick::<int>(1));
var_dump((new F)->pick::<int>(1));
?>
--EXPECT--
string(7) "E::pick"
string(7) "F::pick"
string(7) "H::take"
string(7) "G::take"
string(7) "E::pick"
string(7) "E::pick"
string(7) "F::pick"
