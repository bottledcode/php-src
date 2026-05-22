--TEST--
Reification: VERIFY_RETURN_TYPE checks the reified T binding, not the erased bound
--FILE--
<?php
// Unbounded T erases the return to `mixed`. Pre-erasure return type is still T,
// so the runtime must check the actual return value against the binding for T —
// not just accept any value because the erased mask is mixed.

function id<T>(T $x): T { return $x; }
function ret_nonnumeric_string<T>(T $x): T { return "abc"; }
function ret_array<T>(T $x): T { return [1, 2]; }
function maybe<T>(T $x): ?T { return null; }
function lift<T>(T $x): array { return [$x]; }

// Positive: returning the correct concrete T passes.
var_dump(id::<int>(42));
var_dump(id::<string>("hi"));

// Negative: returning a wrong, non-coercible concrete type fires with the
// resolved T, not the erased "mixed".
//   - "abc" (non-numeric string) cannot coerce to int even in weak mode.
//   - array cannot coerce to string at all.
try {
    ret_nonnumeric_string::<int>(1);
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}
try {
    ret_array::<string>("x");
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}

// `?T`: nullable return on a T-typed function still accepts null when bound
// to a non-nullable concrete type. The reified check preserves the nullable
// bit from the outer T-ref.
var_dump(maybe::<int>(0));

// Non-T return type (`array`) is unaffected by the reified-return path —
// goes through the normal erased check.
var_dump(lift::<int>(7));

// Object T: returning the wrong class names the bound class in the error.
class A {}
class B {}
function obj<T>(T $x): T { return new B(); }
try {
    obj::<A>(new A());
} catch (TypeError $e) {
    echo "3: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
string(2) "hi"
1: ret_nonnumeric_string(): Return value must be of type int, string returned
2: ret_array(): Return value must be of type string, array returned
NULL
array(1) {
  [0]=>
  int(7)
}
3: obj(): Return value must be of type A, B returned
