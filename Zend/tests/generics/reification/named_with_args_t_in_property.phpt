--TEST--
Reification: a property typed `I<T>` (T inside a generic class type arg) substitutes T → binding for the monomorph
--FILE--
<?php
// Regression: `public I<T> $val` on a generic class. When the class is
// monomorphized (e.g. `new Box::<string>()`), the property type should
// substitute T → string and the runtime check should accept values that
// satisfy `I<string>`. The bug was that `zend_substitute_leaf_type_param`
// didn't recurse into NAMED_WITH_ARGS payloads, so the property stayed
// typed as the unsubstituted `I<T>` and any assignment failed with
// "Cannot assign ... of type I<T>".

interface I<out T> {}

class StrImpl implements I<string> {}
class IntImpl implements I<int> {}

class Box<T> {
    public I<T> $val;
}

// Direct monomorph use.
$b = new Box::<string>();
$b->val = new StrImpl();
var_dump($b->val::class);

// Wrong-arg implementation rejected.
try {
    $b->val = new IntImpl();
} catch (TypeError $e) {
    echo "1: ", $e->getMessage(), "\n";
}

// Inherited subclass case: same substitution applies when the property is
// touched on an instance of a non-generic child. Use `mixed` on the ctor
// so the bad value reaches the property assignment site, exercising the
// inherited property's substituted type rather than the ctor's own type.
class StringBox extends Box<string> {
    public function __construct(mixed $val) {
        $this->val = $val;
    }
}
$sb = new StringBox(new StrImpl());
var_dump($sb->val::class);

try {
    new StringBox(new IntImpl());
} catch (TypeError $e) {
    echo "2: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
string(7) "StrImpl"
1: Cannot assign IntImpl to property %s::$val of type I<string>
string(7) "StrImpl"
2: Cannot assign IntImpl to property %s::$val of type I<string>
