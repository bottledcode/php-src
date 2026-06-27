--TEST--
Reification: a monomorph of `B<T> implements I<T>` should report `is_a I<bound>` true
--FILE--
<?php
// Regression: when `B<T> implements I<T>` is monomorphized as `B<string>`,
// the implements binding has to be substituted so that `B<string>` is also
// known to implement `I<string>`. Otherwise `instanceof I::<string>` /
// `is_a(..., 'I<string>')` returns false even though the type relationship
// holds, and property writes typed `I<string>` reject `B<string>` values.

interface I<+T> {}
class B<T> implements I<T> {}

// 1) Direct monomorph
$b = new B::<string>();
var_dump(is_a($b, 'I<string>'));
var_dump(is_a($b, 'B<string>'));
$mono = 'B<string>';
var_dump($b instanceof $mono);

// 2) Non-generic subclass of a monomorph
class StrB extends B<string> {}
$s = new StrB();
var_dump(is_a($s, 'I<string>'));
var_dump(is_a($s, 'B<string>'));

// 3) Assignment to a property typed I<string> accepts a B<string>
class Holder<T> {
    public I<T> $val;
}
$h = new Holder::<string>();
$h->val = new B::<string>();
var_dump($h->val::class);
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
string(9) "B<string>"
