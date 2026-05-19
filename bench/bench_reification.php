<?php
/**
 * Benchmark harness for the bound-erased-generic-types reification work.
 *
 * Each scenario is implemented as `function(int $n)` that runs a hot loop
 * of $n iterations. The harness warms up once with a small N, then times
 * the full N. All scenarios use the SAME interpreter build, so absolute
 * numbers are debug-build-skewed but relative comparisons are meaningful.
 */

const ITERS_LIGHT = 200_000;
const ITERS_HEAVY = 1_000_000;
const WARMUP_FRAC = 20;

function bench(string $name, callable $fn, int $iters = ITERS_HEAVY): float {
    $fn(intdiv($iters, WARMUP_FRAC));
    $t0 = hrtime(true);
    $fn($iters);
    $t1 = hrtime(true);
    $ns = ($t1 - $t0) / $iters;
    printf("  %-55s %9.1f ns/op  (%d iters)\n", $name, $ns, $iters);
    return $ns;
}

function section(string $title): void {
    echo "\n", str_repeat("=", 70), "\n", $title, "\n", str_repeat("=", 70), "\n";
}

function compare(string $label, float $a_ns, string $a_name, float $b_ns, string $b_name): void {
    $delta = $a_ns - $b_ns;
    $ratio = $b_ns > 0 ? $a_ns / $b_ns : 0;
    printf("  %-55s %+9.1f ns/op delta  (%.2fx %s vs %s)\n",
        $label, $delta, $ratio, $a_name, $b_name);
}

// --------------------------------------------------------------------------
// Scenario 1: non-generic baseline — instantiation and method dispatch
// --------------------------------------------------------------------------
class PlainFoo { public int $x = 0; }
class PlainBox {
    public function __construct(public mixed $value = null) {}
    public function tick(int $x): int { return $x + 1; }
}

function plain_new(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        new PlainFoo();
    }
}
function plain_method(int $n): void {
    $b = new PlainBox();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) {
        $acc = $b->tick($acc);
    }
}

// --------------------------------------------------------------------------
// Scenario 2: generic-class instantiation
// --------------------------------------------------------------------------
class GenBox<T = int> {
    public function __construct(public mixed $value = null) {}
    public function tick(int $x): int { return $x + 1; }
}
class IntBox extends GenBox<int> {}

function gen_new_turbofish(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        new GenBox::<int>();
    }
}
function gen_new_default(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        new GenBox();
    }
}
function gen_new_extends(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        new IntBox();
    }
}

// --------------------------------------------------------------------------
// Scenario 3: method dispatch on generic-class instance
// --------------------------------------------------------------------------
function gen_method_on_mono(int $n): void {
    $b = new GenBox::<int>();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) {
        $acc = $b->tick($acc);
    }
}
function gen_method_on_default(int $n): void {
    $b = new GenBox();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) {
        $acc = $b->tick($acc);
    }
}

// --------------------------------------------------------------------------
// Scenario 4: T-keyed expressions inside the body
// --------------------------------------------------------------------------
class Marker {}
class TBox<T : object = Marker> {
    public function makeT(): object   { return new T(); }
    public function classT(): string  { return T::class; }
    public function isT(object $x): bool { return $x instanceof T; }
}
function tbox_makeT(int $n): void {
    $b = new TBox::<Marker>();
    for ($i = 0; $i < $n; $i++) {
        $b->makeT();
    }
}
function tbox_classT(int $n): void {
    $b = new TBox::<Marker>();
    for ($i = 0; $i < $n; $i++) {
        $b->classT();
    }
}
function tbox_isT(int $n): void {
    $b = new TBox::<Marker>();
    $m = new Marker();
    for ($i = 0; $i < $n; $i++) {
        $b->isT($m);
    }
}
function plain_makeT(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        new Marker();
    }
}
function plain_classT(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        $_ = Marker::class;
    }
}
function plain_isT(int $n): void {
    $m = new Marker();
    for ($i = 0; $i < $n; $i++) {
        $_ = $m instanceof Marker;
    }
}

// --------------------------------------------------------------------------
// Scenario 5: inference vs turbofish vs non-generic
// --------------------------------------------------------------------------
class Foo { public int $tag = 0; }
function makeGen<T>(T $x): T { return $x; }
function makeNonGen(Foo $x): Foo { return $x; }

function infer_call(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        makeGen($f);
    }
}
function turbofish_call(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        makeGen::<Foo>($f);
    }
}
function plain_call(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        makeNonGen($f);
    }
}

// --------------------------------------------------------------------------
// Scenario 6: multi-parameter inference
// --------------------------------------------------------------------------
class Bar { public int $tag = 0; }
function makeTwoGen<T, U>(T $x, U $y): T { return $x; }
function makeTwoNonGen(Foo $x, Bar $y): Foo { return $x; }

function infer_two(int $n): void {
    $f = new Foo();
    $b = new Bar();
    for ($i = 0; $i < $n; $i++) {
        makeTwoGen($f, $b);
    }
}
function turbofish_two(int $n): void {
    $f = new Foo();
    $b = new Bar();
    for ($i = 0; $i < $n; $i++) {
        makeTwoGen::<Foo, Bar>($f, $b);
    }
}
function plain_two(int $n): void {
    $f = new Foo();
    $b = new Bar();
    for ($i = 0; $i < $n; $i++) {
        makeTwoNonGen($f, $b);
    }
}

// --------------------------------------------------------------------------
// Scenario 7: inference + bound conformance check
// --------------------------------------------------------------------------
function makeBounded<T : object>(T $x): T { return $x; }

function infer_bounded(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        makeBounded($f);
    }
}
function turbofish_bounded(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        makeBounded::<Foo>($f);
    }
}

// --------------------------------------------------------------------------
// Scenario 8: reified arg-type coercion (callee has T $x and turbofish)
// --------------------------------------------------------------------------
function takesT<T>(T $x): T { return $x; }
function takesTBounded<T : Foo>(T $x): T { return $x; }
function takesPlain(Foo $x): Foo { return $x; }

function reified_arg_check(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        takesTBounded::<Foo>($f);
    }
}
function plain_arg_check(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        takesPlain($f);
    }
}

// --------------------------------------------------------------------------
// Driver
// --------------------------------------------------------------------------
echo "PHP ", PHP_VERSION, " (", PHP_BINARY, ")\n";
echo "Reified bound-erased generics benchmark\n";

section("1. Non-generic baseline");
$plain_new_ns    = bench("plain new Foo()",              'plain_new');
$plain_method_ns = bench("plain \$box->tick()",           'plain_method');

section("2. Generic-class instantiation");
$gen_tf_ns       = bench("new GenBox::<int>()",          'gen_new_turbofish');
$gen_def_ns      = bench("new GenBox() (default T=int)", 'gen_new_default');
$gen_ext_ns      = bench("new IntBox() (extends GenBox<int>)", 'gen_new_extends');
compare("delta: GenBox<int>          vs PlainFoo", $gen_tf_ns,  "gen",   $plain_new_ns, "plain");
compare("delta: GenBox (default)     vs PlainFoo", $gen_def_ns, "gen",   $plain_new_ns, "plain");
compare("delta: IntBox extends       vs PlainFoo", $gen_ext_ns, "gen",   $plain_new_ns, "plain");

section("3. Method dispatch on generic-class receiver");
$gen_mono_method_ns    = bench("GenBox<int>->tick()",          'gen_method_on_mono');
$gen_default_method_ns = bench("GenBox(default)->tick()",      'gen_method_on_default');
compare("delta: GenBox<int>->tick    vs PlainBox->tick", $gen_mono_method_ns, "gen", $plain_method_ns, "plain");

section("4. T-keyed body expressions");
$tbox_makeT_ns  = bench("TBox<Marker>->makeT()",   'tbox_makeT');
$plain_makeT_ns = bench("new Marker() direct",     'plain_makeT');
compare("delta: new T()              vs new Marker()", $tbox_makeT_ns, "T", $plain_makeT_ns, "direct");
$tbox_classT_ns  = bench("TBox<Marker>->classT()",  'tbox_classT');
$plain_classT_ns = bench("Marker::class direct",    'plain_classT');
compare("delta: T::class             vs Marker::class", $tbox_classT_ns, "T", $plain_classT_ns, "direct");
$tbox_isT_ns  = bench("TBox<Marker>->isT()",     'tbox_isT');
$plain_isT_ns = bench("\$x instanceof Marker direct", 'plain_isT');
compare("delta: instanceof T         vs instanceof Marker", $tbox_isT_ns, "T", $plain_isT_ns, "direct");

section("5. Inference vs turbofish vs non-generic call");
$infer_ns    = bench("makeGen(\$x)  — inference",       'infer_call');
$turbo_ns    = bench("makeGen::<Foo>(\$x) — turbofish", 'turbofish_call');
$plain_fn_ns = bench("makeNonGen(\$x) — no generics",   'plain_call');
compare("delta: inference            vs plain", $infer_ns, "infer", $plain_fn_ns, "plain");
compare("delta: turbofish            vs plain", $turbo_ns, "turbo", $plain_fn_ns, "plain");
compare("delta: inference            vs turbofish", $infer_ns, "infer", $turbo_ns, "turbo");

section("6. Multi-parameter inference");
$infer2_ns = bench("makeTwoGen(\$f,\$b)  — infer T,U",   'infer_two');
$turbo2_ns = bench("makeTwoGen::<Foo,Bar>  — turbofish", 'turbofish_two');
$plain2_ns = bench("makeTwoNonGen(\$f,\$b)",             'plain_two');
compare("delta: infer T,U            vs plain", $infer2_ns, "infer2", $plain2_ns, "plain");
compare("delta: turbofish T,U        vs plain", $turbo2_ns, "turbo2", $plain2_ns, "plain");

section("7. Inference + bound check");
$infer_bd_ns = bench("makeBounded(\$x) — infer + bound",   'infer_bounded');
$turbo_bd_ns = bench("makeBounded::<Foo>(\$x) — turbo+bd", 'turbofish_bounded');
compare("delta: infer+bound          vs infer (no bound)", $infer_bd_ns, "ibnd", $infer_ns,  "infer");
compare("delta: turbo+bound          vs turbo (no bound)", $turbo_bd_ns, "tbnd", $turbo_ns, "turbo");

section("8. Reified arg-type coercion");
$reified_arg_ns = bench("takesTBounded::<Foo>(\$f)", 'reified_arg_check');
$plain_arg_ns   = bench("takesPlain(\$f)",            'plain_arg_check');
compare("delta: reified arg check    vs plain typed arg", $reified_arg_ns, "reif", $plain_arg_ns, "plain");

echo "\nNote: opcache is disabled (php -n). Same build, same interpreter\n";
echo "      path across all scenarios — deltas between rows are the signal.\n";
