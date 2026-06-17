--TEST--
JIT: turbofish monomorphs sharing a base op_array must not alias each other's type checks
--EXTENSIONS--
opcache
--INI--
opcache.enable=1
opcache.enable_cli=1
opcache.jit=tracing
opcache.jit_buffer_size=64M
--FILE--
<?php

function bench(callable $fn, int $iters): void {
    $fn(intdiv($iters, 20)); // warmup
    $fn($iters);
}

class Foo {}
interface Fooable {}
interface Barable {}
class FooBar implements Fooable, Barable {}

function idGen<T>(T $x): T { return $x; }
function nestedGen<U>(U $x): U { return idGen::<U>($x); }

function call_scalar(int $n): void { for ($i = 0; $i < $n; $i++) { nestedGen::<int>($i); } }
function call_class(int $n): void  { $f = new Foo(); for ($i = 0; $i < $n; $i++) { nestedGen::<Foo>($f); } }
function call_union(int $n): void  { for ($i = 0; $i < $n; $i++) { nestedGen::<int|string>($i); } }
function call_inter(int $n): void  { $fb = new FooBar(); for ($i = 0; $i < $n; $i++) { nestedGen::<Fooable&Barable>($fb); } }

/* Each concrete turbofish call synthesizes a monomorph of nestedGen with its
 * own substituted arg_info, but all monomorphs share the base's opcode buffer.
 * The tracing JIT keys compiled code on opline addresses and bakes arg_info as
 * a constant; before the fix it compiled one binding's trace and reused it for
 * the others, so the intersection call below was checked against the earlier
 * union binding (int|string) and wrongly threw. Run the bindings in the order
 * that made nestedGen hot under the scalar/class bindings first. */
bench('call_scalar', 100000);
bench('call_class',  100000);
bench('call_union',  20000);
bench('call_inter',  20000);

$fb = new FooBar();
var_dump(nestedGen::<Fooable&Barable>($fb) === $fb);
var_dump(nestedGen::<int|string>(7));
echo "done\n";
?>
--EXPECT--
bool(true)
int(7)
done
