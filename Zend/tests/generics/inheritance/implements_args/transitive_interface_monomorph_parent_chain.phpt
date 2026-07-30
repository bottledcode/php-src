--TEST--
Implements-with-args: transitive interface monomorphs reach through a generic parent and a plain subclass
--FILE--
<?php
interface RO<out T> {}
interface Mid<out T> extends RO<T> {}

class P<U> implements Mid<U> {}
class C<T> extends P<T> {}
class Sub extends C<int> {}

function takesReadOnly(RO<int> $x): string { return get_class($x); }

$c = new C::<int>();
$s = new Sub();

foreach ([$c, $s] as $o) {
    $names = (new ReflectionClass($o))->getInterfaceNames();
    sort($names);
    echo get_class($o), ' => ', implode(', ', $names), "\n";
    var_dump($o instanceof Mid::<int>);
    var_dump($o instanceof RO::<int>);
    echo takesReadOnly($o), "\n";
}
?>
--EXPECT--
C<int> => Mid, Mid<int>, RO, RO<int>
bool(true)
bool(true)
C<int>
Sub => Mid, Mid<int>, RO, RO<int>
bool(true)
bool(true)
Sub
