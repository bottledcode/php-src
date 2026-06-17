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
// Scenario 9: forwarded T-refs — `id::<U>($x)` inside a generic wrapper.
// Exercises (a) per-call zend_type_arg_table build via the borrowed-pointer
// chain, (b) zend_check_user_type_slow on the substituted type, and (c) the
// VERIFY path that INSTALL can't elide (T-refs in turbofish).
// --------------------------------------------------------------------------
function idGen<T>(T $x): T { return $x; }
function nestedGen<U>(U $x): U { return idGen::<U>($x); }
interface Fooable {}
interface Barable {}
class FooBar implements Fooable, Barable {}

function forwarded_scalar(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        nestedGen::<int>($i);
    }
}
function direct_scalar(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        idGen::<int>($i);
    }
}
function forwarded_class(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        nestedGen::<Foo>($f);
    }
}
function direct_class(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) {
        idGen::<Foo>($f);
    }
}
function forwarded_union(int $n): void {
    for ($i = 0; $i < $n; $i++) {
        nestedGen::<int|string>($i);
    }
}
function forwarded_intersection(int $n): void {
    $fb = new FooBar();
    for ($i = 0; $i < $n; $i++) {
        nestedGen::<Fooable&Barable>($fb);
    }
}

// --------------------------------------------------------------------------
// Scenario 10: generic instanceof/catch in a hot inner loop — exercises the
// polymorphic inline cache on the IS_UNUSED FETCH_CLASS path. The first
// iteration misses; subsequent iterations within the same frame hit on a
// pointer compare against (type_args generation, called_scope).
// --------------------------------------------------------------------------
class Container<T> {}

function instanceof_t_hot_loop<T>(array $items): int {
    $count = 0;
    foreach ($items as $item) {
        if ($item instanceof Container<T>) {
            $count++;
        }
    }
    return $count;
}
function instanceof_concrete_hot_loop(array $items): int {
    $count = 0;
    foreach ($items as $item) {
        if ($item instanceof Container) {
            $count++;
        }
    }
    return $count;
}

function bench_instanceof_t(int $n): void {
    $items = [new Container::<int>(), new Container::<string>(), new Foo()];
    /* outer N drives the inner-loop count via a fixed batch — keeps the
     * PIC slot warm across iterations of the body. */
    instanceof_t_hot_loop::<int>(array_fill(0, $n, $items[0]));
}
function bench_instanceof_concrete(int $n): void {
    $items = [new Container::<int>(), new Container::<string>(), new Foo()];
    instanceof_concrete_hot_loop(array_fill(0, $n, $items[0]));
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

section("9. Forwarded T-refs (nested generic calls)");
$fwd_scalar_ns  = bench("nestedGen::<int>(\$i) — forwarded scalar",     'forwarded_scalar');
$dir_scalar_ns  = bench("idGen::<int>(\$i)     — direct scalar",         'direct_scalar');
compare("delta: forwarded scalar     vs direct scalar", $fwd_scalar_ns, "fwd", $dir_scalar_ns, "dir");
$fwd_class_ns   = bench("nestedGen::<Foo>(\$f) — forwarded class",       'forwarded_class');
$dir_class_ns   = bench("idGen::<Foo>(\$f)     — direct class",          'direct_class');
compare("delta: forwarded class      vs direct class", $fwd_class_ns, "fwd", $dir_class_ns, "dir");
$fwd_union_ns   = bench("nestedGen::<int|string>     — forwarded union", 'forwarded_union', ITERS_LIGHT);
$fwd_inter_ns   = bench("nestedGen::<Fooable&Barable> — forwarded ix",   'forwarded_intersection', ITERS_LIGHT);
compare("delta: forwarded union      vs forwarded scalar", $fwd_union_ns, "uni", $fwd_scalar_ns, "scl");

section("10. Generic instanceof in hot inner loop (PIC)");
$inst_t_ns        = bench("instanceof Container<T> (PIC warm)", 'bench_instanceof_t');
$inst_concrete_ns = bench("instanceof Container    (cache slot)", 'bench_instanceof_concrete');
compare("delta: instanceof T         vs instanceof bare", $inst_t_ns, "T", $inst_concrete_ns, "bare");

// --------------------------------------------------------------------------
// Scenario 11: depth scaling for nested monomorph instantiation.
// All monomorphs are synthesized + cached on first use; the hot loop is
// supposed to hit the class-table lookup at pointer-compare speed regardless
// of nesting depth. If the cost grows with depth in the hot path, something
// is being recomputed per call (canonical-name string build, arg-table
// rebuild, etc.).
// --------------------------------------------------------------------------
class L1<T> {}
class L2<T> {}
class L3<T> {}
class L4<T> {}

function deep_new_d1(int $n): void {
    for ($i = 0; $i < $n; $i++) { new L1::<int>(); }
}
function deep_new_d2(int $n): void {
    for ($i = 0; $i < $n; $i++) { new L1::<L2<int>>(); }
}
function deep_new_d3(int $n): void {
    for ($i = 0; $i < $n; $i++) { new L1::<L2<L3<int>>>(); }
}
function deep_new_d4(int $n): void {
    for ($i = 0; $i < $n; $i++) { new L1::<L2<L3<L4<int>>>>(); }
}

// --------------------------------------------------------------------------
// Scenario 12: composite type arguments (union, intersection, DNF).
// Canonicalization sorts the alternatives, so equivalent shapes converge on
// the same monomorph name.
// --------------------------------------------------------------------------
interface Ifc1 {}
interface Ifc2 {}
class Holder1<T> {}

function comp_concrete(int $n): void {
    for ($i = 0; $i < $n; $i++) { new Holder1::<int>(); }
}
function comp_union(int $n): void {
    for ($i = 0; $i < $n; $i++) { new Holder1::<int|string>(); }
}
function comp_intersection(int $n): void {
    for ($i = 0; $i < $n; $i++) { new Holder1::<Ifc1&Ifc2>(); }
}
function comp_dnf(int $n): void {
    for ($i = 0; $i < $n; $i++) { new Holder1::<(Ifc1&Ifc2)|int>(); }
}

// --------------------------------------------------------------------------
// Scenario 13: bounded inner parameter — the `A<B & C<F>>` pattern where
// the inner C declares its own bound, so synthesizing the outer monomorph
// triggers a bound-conformance check on the inner application.
// --------------------------------------------------------------------------
class FBound {}
class FSub extends FBound {}
class WithBound<D : FBound> {}
class WithoutBound<X> {}

function nested_bounded_only(int $n): void {
    for ($i = 0; $i < $n; $i++) { new WithBound::<FSub>(); }
}
function nested_outer_around_bounded(int $n): void {
    for ($i = 0; $i < $n; $i++) { new WithoutBound::<WithBound<FSub>>(); }
}
function nested_intersection_with_bounded(int $n): void {
    for ($i = 0; $i < $n; $i++) { new WithoutBound::<Ifc1&WithBound<FSub>>(); }
}

// --------------------------------------------------------------------------
// Scenario 14: instanceof on deeply-nested monomorphs. The polymorphic
// inline cache should make depth irrelevant after warmup — the slot just
// caches the resolved ce.
// --------------------------------------------------------------------------
function deep_instanceof_d1(int $n): void {
    $box = new L1::<int>();
    for ($i = 0; $i < $n; $i++) { $_ = $box instanceof L1<int>; }
}
function deep_instanceof_d3(int $n): void {
    $box = new L1::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { $_ = $box instanceof L1<L2<L3<int>>>; }
}
function deep_instanceof_d3_mismatch(int $n): void {
    $box = new L1::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { $_ = $box instanceof L1<L2<L3<string>>>; }
}
function deep_instanceof_d3_bare(int $n): void {
    $box = new L1::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { $_ = $box instanceof L1; }
}

section("11. Depth scaling — nested monomorph `new`");
$d1_ns = bench("new L1<int>            (depth 1)", 'deep_new_d1');
$d2_ns = bench("new L1<L2<int>>        (depth 2)", 'deep_new_d2');
$d3_ns = bench("new L1<L2<L3<int>>>    (depth 3)", 'deep_new_d3');
$d4_ns = bench("new L1<L2<L3<L4<int>>>> (depth 4)", 'deep_new_d4');
compare("delta: depth 2              vs depth 1", $d2_ns, "d2", $d1_ns, "d1");
compare("delta: depth 3              vs depth 1", $d3_ns, "d3", $d1_ns, "d1");
compare("delta: depth 4              vs depth 1", $d4_ns, "d4", $d1_ns, "d1");

section("12. Composite type-argument shapes");
$cc_ns = bench("new Holder1<int>",                 'comp_concrete');
$cu_ns = bench("new Holder1<int|string>",          'comp_union');
$ci_ns = bench("new Holder1<Ifc1&Ifc2>",           'comp_intersection');
$cd_ns = bench("new Holder1<(Ifc1&Ifc2)|int>",     'comp_dnf');
compare("delta: union                vs concrete",       $cu_ns, "uni", $cc_ns, "cnc");
compare("delta: intersection         vs concrete",       $ci_ns, "ix",  $cc_ns, "cnc");
compare("delta: DNF                  vs concrete",       $cd_ns, "dnf", $cc_ns, "cnc");

section("13. Bounded inner parameter (`A<B & C<F>>` pattern)");
$nb_ns  = bench("new WithBound<FSub>           (just the inner)",       'nested_bounded_only');
$nob_ns = bench("new WithoutBound<WithBound<FSub>>",                    'nested_outer_around_bounded');
$nix_ns = bench("new WithoutBound<Ifc1 & WithBound<FSub>>",             'nested_intersection_with_bounded');
compare("delta: outer wraps bounded  vs inner alone",   $nob_ns, "out", $nb_ns,  "inner");
compare("delta: intersection w/bound vs outer alone",   $nix_ns, "ix",  $nob_ns, "out");

section("14. Deep `instanceof` (PIC vs depth)");
$di1_ns = bench("instanceof L1<int>             (depth 1)",              'deep_instanceof_d1');
$di3_ns = bench("instanceof L1<L2<L3<int>>>     (depth 3, match)",       'deep_instanceof_d3');
$dim_ns = bench("instanceof L1<L2<L3<string>>>  (depth 3, mismatch)",    'deep_instanceof_d3_mismatch');
$dib_ns = bench("instanceof L1                  (bare, hits ancestor)",  'deep_instanceof_d3_bare');
compare("delta: depth 3 match        vs depth 1 match", $di3_ns, "d3", $di1_ns, "d1");
compare("delta: depth 3 mismatch     vs depth 3 match", $dim_ns, "mis", $di3_ns, "mat");
compare("delta: bare ancestor        vs depth 3 match", $dib_ns, "bare", $di3_ns, "d3");

// --------------------------------------------------------------------------
// Scenario 15: deep parameter type check at RECV. The arg_info type is now
// the canonical monomorph (`Box<L2<L3<int>>>`), so RECV's instanceof_function
// resolves and enforces the full identity — passing the wrong monomorph
// rejects, not silently accepts. After warmup the class-lookup cache makes
// the check the same speed as a non-generic typed param.
// --------------------------------------------------------------------------
class DPlain {}
class DBox<T> {}

function takes_plain(DPlain $x): void {}
function takes_d1(DBox<int> $x): void {}
function takes_d2(DBox<L2<int>> $x): void {}
function takes_d3(DBox<L2<L3<int>>> $x): void {}
function takes_d4(DBox<L2<L3<L4<int>>>> $x): void {}

function bench_param_plain(int $n): void {
    $v = new DPlain();
    for ($i = 0; $i < $n; $i++) { takes_plain($v); }
}
function bench_param_d1(int $n): void {
    $v = new DBox::<int>();
    for ($i = 0; $i < $n; $i++) { takes_d1($v); }
}
function bench_param_d2(int $n): void {
    $v = new DBox::<L2<int>>();
    for ($i = 0; $i < $n; $i++) { takes_d2($v); }
}
function bench_param_d3(int $n): void {
    $v = new DBox::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { takes_d3($v); }
}
function bench_param_d4(int $n): void {
    $v = new DBox::<L2<L3<L4<int>>>>();
    for ($i = 0; $i < $n; $i++) { takes_d4($v); }
}

section("15. Deep parameter type check (RECV reifies the full monomorph)");
$p_pl_ns = bench("f(DPlain \$x)                  (non-generic baseline)", 'bench_param_plain');
$p_d1_ns = bench("f(DBox<int> \$x)               (depth 1)",              'bench_param_d1');
$p_d2_ns = bench("f(DBox<L2<int>> \$x)           (depth 2)",              'bench_param_d2');
$p_d3_ns = bench("f(DBox<L2<L3<int>>> \$x)       (depth 3)",              'bench_param_d3');
$p_d4_ns = bench("f(DBox<L2<L3<L4<int>>>> \$x)   (depth 4)",              'bench_param_d4');
compare("delta: deep d1              vs plain", $p_d1_ns, "d1", $p_pl_ns, "plain");
compare("delta: deep d4              vs plain", $p_d4_ns, "d4", $p_pl_ns, "plain");
compare("delta: deep d4              vs deep d1", $p_d4_ns, "d4", $p_d1_ns, "d1");

// --------------------------------------------------------------------------
// Scenario 16: inference from a deep-typed value. The compiler can't
// destructure `Box<T>` into a T-binding at the call site (only bare T
// params are inferable), but `id<T>(T $x)` does pick up the value's full
// runtime monomorph as T. Compare to explicit turbofish with the same deep
// type, and to the equivalent non-generic call.
// --------------------------------------------------------------------------
function id_gen<T>(T $x): T { return $x; }

function bench_infer_d1(int $n): void {
    $v = new DBox::<int>();
    for ($i = 0; $i < $n; $i++) { id_gen($v); }
}
function bench_infer_d3(int $n): void {
    $v = new DBox::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { id_gen($v); }
}
function bench_turbofish_d3(int $n): void {
    $v = new DBox::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { id_gen::<DBox<L2<L3<int>>>>($v); }
}
function bench_plain_pass(int $n): void {
    $v = new DPlain();
    for ($i = 0; $i < $n; $i++) { takes_plain($v); }
}

section("16. Inference / turbofish with deep value");
$inf1_ns = bench("id(DBox<int>)                  — inferred",             'bench_infer_d1');
$inf3_ns = bench("id(DBox<L2<L3<int>>>)          — inferred",             'bench_infer_d3');
$tf3_ns  = bench("id::<DBox<L2<L3<int>>>>(\$v)    — turbofish",           'bench_turbofish_d3');
$plp_ns  = bench("takes_plain(\$plain)            — non-generic ref",     'bench_plain_pass');
compare("delta: inferred d1          vs plain", $inf1_ns, "infD1", $plp_ns, "plain");
compare("delta: inferred d3          vs plain", $inf3_ns, "infD3", $plp_ns, "plain");
compare("delta: turbofish d3         vs inferred d3", $tf3_ns,  "tf",   $inf3_ns, "inf");

// --------------------------------------------------------------------------
// Scenario 17: method dispatch on a deep generic receiver. The receiver's
// class is the monomorph (e.g., DReceiver<L2<L3<int>>>); the method lookup
// goes through the monomorph's function table. Hot loops with the same
// receiver class should pay zero depth penalty after warmup.
// --------------------------------------------------------------------------
class DReceiver<T> {
    public function tick(int $x): int { return $x + 1; }
}
class DReceiverPlain {
    public function tick(int $x): int { return $x + 1; }
}

function bench_recv_plain(int $n): void {
    $r = new DReceiverPlain();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) { $acc = $r->tick($acc); }
}
function bench_recv_d1(int $n): void {
    $r = new DReceiver::<int>();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) { $acc = $r->tick($acc); }
}
function bench_recv_d3(int $n): void {
    $r = new DReceiver::<L2<L3<int>>>();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) { $acc = $r->tick($acc); }
}

section("17. Method dispatch on a deep generic receiver");
$mr_pl_ns = bench("DReceiverPlain->tick()           (non-generic)",        'bench_recv_plain');
$mr_d1_ns = bench("DReceiver<int>->tick()           (depth 1)",            'bench_recv_d1');
$mr_d3_ns = bench("DReceiver<L2<L3<int>>>->tick()   (depth 3)",            'bench_recv_d3');
compare("delta: receiver d1          vs plain", $mr_d1_ns, "d1", $mr_pl_ns, "plain");
compare("delta: receiver d3          vs d1",    $mr_d3_ns, "d3", $mr_d1_ns, "d1");

// --------------------------------------------------------------------------
// Scenario 18: pass-through with deep param + deep return. Two RECV checks
// per call (param + return), each resolving a deep monomorph class.
// --------------------------------------------------------------------------
function passthrough_plain(DPlain $x): DPlain { return $x; }
function passthrough_d3(DBox<L2<L3<int>>> $x): DBox<L2<L3<int>>> { return $x; }

function bench_passthrough_plain(int $n): void {
    $v = new DPlain();
    for ($i = 0; $i < $n; $i++) { passthrough_plain($v); }
}
function bench_passthrough_d3(int $n): void {
    $v = new DBox::<L2<L3<int>>>();
    for ($i = 0; $i < $n; $i++) { passthrough_d3($v); }
}

section("18. Pass-through (param + return both deep)");
$pt_pl_ns = bench("DPlain f(DPlain \$x): DPlain",                          'bench_passthrough_plain');
$pt_d3_ns = bench("DBox<L2<L3<int>>> f(DBox<L2<L3<int>>> \$x): same",      'bench_passthrough_d3');
compare("delta: deep passthrough     vs plain passthrough", $pt_d3_ns, "d3", $pt_pl_ns, "plain");

// ============================================================================
// PATHOLOGICAL — looking for super-linear blowup.
// Each scenario varies one structural dimension while holding the others
// constant. If the cost grows worse than linearly in that dimension, the
// implementation has a problem worth flagging.
// ============================================================================

// --------------------------------------------------------------------------
// Scenario 19: canonical-name traversal depth. Building "Box<Box<Box<...>>>"
// touches every layer of the tree. Linear depth → linear cost is the goal.
// --------------------------------------------------------------------------
class CB<T> {}
function cn_d1(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CB::<int>(); }
}
function cn_d3(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CB::<CB<CB<int>>>(); }
}
function cn_d5(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CB::<CB<CB<CB<CB<int>>>>>(); }
}
function cn_d8(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CB::<CB<CB<CB<CB<CB<CB<CB<int>>>>>>>>(); }
}

// --------------------------------------------------------------------------
// Scenario 20: wide DNF — many alternatives in a union of intersections.
// Pre-erasure value check loops over alternatives; worst case is O(width).
// --------------------------------------------------------------------------
interface DA {} interface DB {} interface DC {} interface DD {}
interface DE {} interface DF {} interface DG {} interface DH {}
class Hits implements DA, DB, DC, DD, DE, DF, DG, DH {}
function dnf_w2<T : (DA&DB)|(DC&DD)>(T $x): T { return $x; }
function dnf_w4<T : (DA&DB)|(DC&DD)|(DE&DF)|(DG&DH)>(T $x): T { return $x; }
function dnf_w2_call(int $n): void {
    $v = new Hits();
    for ($i = 0; $i < $n; $i++) { dnf_w2($v); }
}
function dnf_w4_call(int $n): void {
    $v = new Hits();
    for ($i = 0; $i < $n; $i++) { dnf_w4($v); }
}

// --------------------------------------------------------------------------
// Scenario 21: diamond inheritance binding chain. D extends C<E<F<int>>>
// resolution. Bench the act of constructing instances through the chain.
// --------------------------------------------------------------------------
class CL1<T> {}
class CL2<T> extends CL1<T> {}
class CL3<T> extends CL2<T> {}
class CL4<T> extends CL3<T> {}
class CL5<T> extends CL4<T> {}
function chain_d2(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CL2::<int>(); }
}
function chain_d3(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CL3::<int>(); }
}
function chain_d4(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CL4::<int>(); }
}
function chain_d5(int $n): void {
    for ($i = 0; $i < $n; $i++) { new CL5::<int>(); }
}

// --------------------------------------------------------------------------
// Scenario 22: polymorphic inline cache thrashing. The PIC at instanceof/
// catch keys on (type_args generation, called_scope). A hot loop that
// switches the binding per iteration should miss the cache every time,
// turning O(1) into an O(class-table-lookup) cost per iteration.
// --------------------------------------------------------------------------
class PicBox<T> {}
function pic_stable<T>(array $items): int {
    $count = 0;
    foreach ($items as $item) {
        if ($item instanceof PicBox<T>) { $count++; }
    }
    return $count;
}
/* Outer loop alternates the T binding per call — every entry into the inner
 * loop sees a different type_args generation, so the PIC slot mismatches
 * and re-resolves on the first item. The inner loop then runs warm. */
function bench_pic_stable_int(int $n): void {
    $items = array_fill(0, 16, new PicBox::<int>());
    for ($i = 0; $i < $n; $i++) {
        pic_stable::<int>($items);
    }
}
function bench_pic_alternating(int $n): void {
    $items = array_fill(0, 16, new PicBox::<int>());
    /* call alternates T between int and string — each call's args_box has
     * a different cache key, so the per-call type_arg_table differs and
     * the inner-loop PIC must re-resolve at least once per call. */
    for ($i = 0; $i < $n; $i++) {
        if ($i & 1) {
            pic_stable::<int>($items);
        } else {
            pic_stable::<string>($items);
        }
    }
}

// --------------------------------------------------------------------------
// Scenario 23: bound-conformance check on deeply-nested args. Each level of
// nesting may trigger an additional recursive call into the variance/bound
// machinery.
// --------------------------------------------------------------------------
class BX<T : object> {}
function bnd_d1(int $n): void {
    for ($i = 0; $i < $n; $i++) { new BX::<DPlain>(); }
}
function bnd_d3(int $n): void {
    for ($i = 0; $i < $n; $i++) { new BX::<BX<BX<DPlain>>>(); }
}
function bnd_d5(int $n): void {
    for ($i = 0; $i < $n; $i++) { new BX::<BX<BX<BX<BX<DPlain>>>>>(); }
}

section("19. Canonical-name traversal depth");
$cn1_ns = bench("new CB<int>                          (depth 1)",   'cn_d1');
$cn3_ns = bench("new CB<CB<CB<int>>>                  (depth 3)",   'cn_d3');
$cn5_ns = bench("new CB<CB<CB<CB<CB<int>>>>>          (depth 5)",   'cn_d5');
$cn8_ns = bench("new CB<...x8 levels...<int>>         (depth 8)",   'cn_d8');
compare("delta: d3 vs d1               (3x deeper)", $cn3_ns, "d3", $cn1_ns, "d1");
compare("delta: d5 vs d1               (5x deeper)", $cn5_ns, "d5", $cn1_ns, "d1");
compare("delta: d8 vs d1               (8x deeper)", $cn8_ns, "d8", $cn1_ns, "d1");

section("20. Wide DNF bound — value check across union of intersections");
$dnf2_ns = bench("dnf<(DA&DB)|(DC&DD)>(Hits) — 2 alts",      'dnf_w2_call');
$dnf4_ns = bench("dnf<(DA&DB)|(DC&DD)|(DE&DF)|(DG&DH)> — 4", 'dnf_w4_call');
compare("delta: 4 alts vs 2 alts        (2x wider)", $dnf4_ns, "w4", $dnf2_ns, "w2");

section("21. Inheritance binding chain depth");
$ch2_ns = bench("new CL2<int>             (chain depth 2)",  'chain_d2');
$ch3_ns = bench("new CL3<int>             (chain depth 3)",  'chain_d3');
$ch4_ns = bench("new CL4<int>             (chain depth 4)",  'chain_d4');
$ch5_ns = bench("new CL5<int>             (chain depth 5)",  'chain_d5');
compare("delta: chain 3 vs chain 2",     $ch3_ns, "c3", $ch2_ns, "c2");
compare("delta: chain 5 vs chain 2",     $ch5_ns, "c5", $ch2_ns, "c2");

section("22. PIC thrashing (alternating T)");
$pic_st_ns = bench("instanceof PicBox<T> — stable T (PIC warm)",  'bench_pic_stable_int',  ITERS_LIGHT);
$pic_alt_ns = bench("instanceof PicBox<T> — alternating T",       'bench_pic_alternating', ITERS_LIGHT);
compare("delta: alternating vs stable", $pic_alt_ns, "alt", $pic_st_ns, "stable");

section("23. Bound-conformance depth");
$bd1_ns = bench("new BX<DPlain>           (depth 1, bound: object)",       'bnd_d1');
$bd3_ns = bench("new BX<BX<BX<DPlain>>>   (depth 3)",                      'bnd_d3');
$bd5_ns = bench("new BX<...x5 nested...>  (depth 5)",                      'bnd_d5');
compare("delta: d3 vs d1               (3x deeper)", $bd3_ns, "d3", $bd1_ns, "d1");
compare("delta: d5 vs d1               (5x deeper)", $bd5_ns, "d5", $bd1_ns, "d1");

// --------------------------------------------------------------------------
// Scenario 24: inference cost isolation — inferred call vs turbofish vs plain.
// The commenter's concern: "inference is expensive". Inference is ~10 lines:
// peek at Z_OBJCE_P(arg)->name for each unbound inferable slot. No
// unification, no constraint-solving, no new compiler phase.
// --------------------------------------------------------------------------

// 24a: single-param inference with different class hierarchy depths
class Shallow {}
class Mid extends Shallow {}
class Deep extends Mid {}

function infer_id<T>(T $x): T { return $x; }
function plain_id(object $x): object { return $x; }

function bench_infer_shallow(int $n): void {
    $v = new Shallow();
    for ($i = 0; $i < $n; $i++) { infer_id($v); }
}
function bench_infer_deep_hierarchy(int $n): void {
    $v = new Deep();
    for ($i = 0; $i < $n; $i++) { infer_id($v); }
}
function bench_turbofish_shallow(int $n): void {
    $v = new Shallow();
    for ($i = 0; $i < $n; $i++) { infer_id::<Shallow>($v); }
}
function bench_plain_object(int $n): void {
    $v = new Shallow();
    for ($i = 0; $i < $n; $i++) { plain_id($v); }
}

// 24b: multi-param inference — the "int and float, T could be int|float" worry.
// In this implementation, each parameter maps to exactly one T slot.
// Scalars are NOT inferred (only objects). No ambiguity exists.
class TypeA {}
class TypeB {}
class TypeC {}
class TypeD {}

function infer_two_params<T, U>(T $a, U $b): T { return $a; }
function infer_three_params<T, U, V>(T $a, U $b, V $c): T { return $a; }
function infer_four_params<T, U, V, W>(T $a, U $b, V $c, W $d): T { return $a; }
function plain_four_params(TypeA $a, TypeB $b, TypeC $c, TypeD $d): TypeA { return $a; }

function bench_infer_2param(int $n): void {
    $a = new TypeA(); $b = new TypeB();
    for ($i = 0; $i < $n; $i++) { infer_two_params($a, $b); }
}
function bench_infer_3param(int $n): void {
    $a = new TypeA(); $b = new TypeB(); $c = new TypeC();
    for ($i = 0; $i < $n; $i++) { infer_three_params($a, $b, $c); }
}
function bench_infer_4param(int $n): void {
    $a = new TypeA(); $b = new TypeB(); $c = new TypeC(); $d = new TypeD();
    for ($i = 0; $i < $n; $i++) { infer_four_params($a, $b, $c, $d); }
}
function bench_turbofish_4param(int $n): void {
    $a = new TypeA(); $b = new TypeB(); $c = new TypeC(); $d = new TypeD();
    for ($i = 0; $i < $n; $i++) { infer_four_params::<TypeA, TypeB, TypeC, TypeD>($a, $b, $c, $d); }
}
function bench_plain_4param(int $n): void {
    $a = new TypeA(); $b = new TypeB(); $c = new TypeC(); $d = new TypeD();
    for ($i = 0; $i < $n; $i++) { plain_four_params($a, $b, $c, $d); }
}

// --------------------------------------------------------------------------
// Scenario 25: inference through inheritance chains — the "expensive when
// nested in inheritance chains" worry. The monomorph for GenChild<int> is
// synthesized once and cached in the class table. Inference reads the
// already-resolved ce->name from the object — it doesn't walk chains.
// --------------------------------------------------------------------------
class GenBase<T> {
    public function id(T $x): T { return $x; }
}
class GenMid<T> extends GenBase<T> {}
class GenChild<T> extends GenMid<T> {}
class GenGrandchild<T> extends GenChild<T> {}

function infer_from_chain<T>(T $x): T { return $x; }

function bench_infer_chain_d1(int $n): void {
    $v = new GenBase::<int>();
    for ($i = 0; $i < $n; $i++) { infer_from_chain($v); }
}
function bench_infer_chain_d2(int $n): void {
    $v = new GenMid::<int>();
    for ($i = 0; $i < $n; $i++) { infer_from_chain($v); }
}
function bench_infer_chain_d3(int $n): void {
    $v = new GenChild::<int>();
    for ($i = 0; $i < $n; $i++) { infer_from_chain($v); }
}
function bench_infer_chain_d4(int $n): void {
    $v = new GenGrandchild::<int>();
    for ($i = 0; $i < $n; $i++) { infer_from_chain($v); }
}

// --------------------------------------------------------------------------
// Scenario 26: inferred call inside a generic function (forwarding). This is
// the nested case: `outer<U>` calls `inner(u)` which infers T=U from the
// runtime value. The inner call can't cache (inference depends on runtime
// value), but the work per call is still just a ce->name read.
// --------------------------------------------------------------------------
function inner_infer<T>(T $x): T { return $x; }
function outer_calls_inferred<U>(U $x): U { return inner_infer($x); }
function outer_calls_turbofish<U>(U $x): U { return inner_infer::<U>($x); }
function outer_plain(Foo $x): Foo { return $x; }

function bench_nested_infer(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) { outer_calls_inferred::<Foo>($f); }
}
function bench_nested_turbofish(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) { outer_calls_turbofish::<Foo>($f); }
}
function bench_nested_plain(int $n): void {
    $f = new Foo();
    for ($i = 0; $i < $n; $i++) { outer_plain($f); }
}

// --------------------------------------------------------------------------
// Scenario 27: bounded inference — the callee has `T : SomeBase`, so after
// inferring T from the value's class, the bound must also be checked. This
// is the same covariance check as turbofish, not an extra cost of inference.
// --------------------------------------------------------------------------
class Animal {}
class Dog extends Animal {}
class Puppy extends Dog {}

function infer_bounded_obj<T : Animal>(T $x): T { return $x; }
function turbofish_bounded_obj(Animal $x): Animal { return $x; }

function bench_infer_bounded_shallow(int $n): void {
    $v = new Dog();
    for ($i = 0; $i < $n; $i++) { infer_bounded_obj($v); }
}
function bench_infer_bounded_deep(int $n): void {
    $v = new Puppy();
    for ($i = 0; $i < $n; $i++) { infer_bounded_obj($v); }
}
function bench_turbofish_bounded_equiv(int $n): void {
    $v = new Dog();
    for ($i = 0; $i < $n; $i++) { infer_bounded_obj::<Dog>($v); }
}
function bench_plain_bounded_equiv(int $n): void {
    $v = new Dog();
    for ($i = 0; $i < $n; $i++) { turbofish_bounded_obj($v); }
}

// --------------------------------------------------------------------------
// Scenario 28: inference with monomorphised receiver in inheritance chain.
// The object's ce->name is already the resolved monomorph name (e.g.
// "GenChild<int>"). Inference just copies that string — no chain walking.
// --------------------------------------------------------------------------
function bench_infer_mono_receiver_d1(int $n): void {
    $r = new GenBase::<int>();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) { $acc = $r->id($acc); }
}
function bench_infer_mono_receiver_d4(int $n): void {
    $r = new GenGrandchild::<int>();
    $acc = 0;
    for ($i = 0; $i < $n; $i++) { $acc = $r->id($acc); }
}

section("24. Inference cost isolation");
$is_ns  = bench("infer_id(\$shallow)          — inferred, flat class",   'bench_infer_shallow');
$idh_ns = bench("infer_id(\$deep)             — inferred, 3-deep class", 'bench_infer_deep_hierarchy');
$ts_ns  = bench("infer_id::<Shallow>(\$v)     — turbofish equiv",        'bench_turbofish_shallow');
$po_ns  = bench("plain_id(\$v)                — non-generic",            'bench_plain_object');
compare("delta: inferred               vs turbofish",   $is_ns, "inf", $ts_ns, "tf");
compare("delta: inferred               vs plain",       $is_ns, "inf", $po_ns, "plain");
compare("delta: deep hierarchy class    vs shallow",     $idh_ns, "deep", $is_ns, "shallow");

section("24b. Multi-param inference scaling");
$i2_ns  = bench("infer_two_params(\$a,\$b)     — 2 params inferred",     'bench_infer_2param');
$i3_ns  = bench("infer_three_params(\$a,\$b,\$c) — 3 params inferred",   'bench_infer_3param');
$i4_ns  = bench("infer_four_params(\$a...\$d)  — 4 params inferred",     'bench_infer_4param');
$t4_ns  = bench("turbofish::<A,B,C,D>(\$a...) — 4 params turbofish",    'bench_turbofish_4param');
$p4_ns  = bench("plain_four_params(\$a...\$d)  — non-generic",           'bench_plain_4param');
compare("delta: 4-param infer          vs 2-param infer",  $i4_ns, "4p", $i2_ns, "2p");
compare("delta: 4-param infer          vs 4-param turbo",  $i4_ns, "inf", $t4_ns, "tf");
compare("delta: 4-param infer          vs 4-param plain",  $i4_ns, "inf", $p4_ns, "plain");

section("25. Inference through inheritance chains");
$ic1_ns = bench("infer_from_chain(GenBase<int>)       — chain depth 1",  'bench_infer_chain_d1');
$ic2_ns = bench("infer_from_chain(GenMid<int>)        — chain depth 2",  'bench_infer_chain_d2');
$ic3_ns = bench("infer_from_chain(GenChild<int>)      — chain depth 3",  'bench_infer_chain_d3');
$ic4_ns = bench("infer_from_chain(GenGrandchild<int>) — chain depth 4",  'bench_infer_chain_d4');
compare("delta: chain d2               vs chain d1",    $ic2_ns, "d2", $ic1_ns, "d1");
compare("delta: chain d4               vs chain d1",    $ic4_ns, "d4", $ic1_ns, "d1");

section("26. Nested generic call with inner inference");
$ni_ns  = bench("outer<Foo> → inner inferred           — inner infers",  'bench_nested_infer');
$nt_ns  = bench("outer<Foo> → inner::<U> turbofish     — inner forwarded",'bench_nested_turbofish');
$np_ns  = bench("outer_plain(\$f)                       — non-generic",   'bench_nested_plain');
compare("delta: nested infer            vs nested turbo",  $ni_ns, "inf", $nt_ns, "tf");
compare("delta: nested infer            vs plain",         $ni_ns, "inf", $np_ns, "plain");
compare("delta: nested turbo            vs plain",         $nt_ns, "tf",  $np_ns, "plain");

section("27. Bounded inference (covariance check)");
$ibs_ns = bench("infer_bounded(Dog)          — shallow subtype",         'bench_infer_bounded_shallow');
$ibd_ns = bench("infer_bounded(Puppy)        — deeper subtype",          'bench_infer_bounded_deep');
$tbe_ns = bench("infer_bounded::<Dog>(Dog)   — turbofish+bound",         'bench_turbofish_bounded_equiv');
$pbe_ns = bench("plain_bounded(Dog)          — non-generic typed",       'bench_plain_bounded_equiv');
compare("delta: infer+bound             vs turbofish+bound", $ibs_ns, "inf", $tbe_ns, "tf");
compare("delta: infer+bound             vs plain typed",     $ibs_ns, "inf", $pbe_ns, "plain");
compare("delta: deeper subtype          vs shallow subtype", $ibd_ns, "deep", $ibs_ns, "shallow");

section("28. Method dispatch on monomorphised inheritance chain receiver");
$mrd1_ns = bench("GenBase<int>->id()           — chain depth 1",         'bench_infer_mono_receiver_d1');
$mrd4_ns = bench("GenGrandchild<int>->id()     — chain depth 4",         'bench_infer_mono_receiver_d4');
compare("delta: chain d4 receiver       vs chain d1",  $mrd4_ns, "d4", $mrd1_ns, "d1");

$opcache_loaded = extension_loaded('Zend OPcache');
$opcache_on = $opcache_loaded
    && function_exists('opcache_get_status')
    && (bool) (opcache_get_status(false)['opcache_enabled'] ?? false);
$jit_on = false;
if ($opcache_on) {
    $status = opcache_get_status(false);
    /* 'enabled' = JIT is built into this opcache; 'on' = JIT is active at runtime */
    $jit_on = (bool) ($status['jit']['on'] ?? false);
}
$opcache_label = $opcache_on
    ? ('enabled' . ($jit_on ? ' + JIT' : ', JIT off'))
    : ($opcache_loaded ? 'loaded but disabled (opcache.enable_cli=0)' : 'not loaded');
echo "\nNote: opcache is $opcache_label. Same build, same interpreter\n";
echo "      path across all scenarios — deltas between rows are the signal.\n";
