--TEST--
Implements-with-args: every level of an interface chain contributes its monomorph
--FILE--
<?php
interface RO<out T> {}
interface Mid<out T> extends RO<T> {}
interface Deep<out T> extends Mid<T> {}

class C<T> implements Deep<T> {}

$o = new C::<int>();

foreach (['C<int>', 'Deep<int>', 'Mid<int>', 'RO<int>'] as $name) {
    $names = (new ReflectionClass($name))->getInterfaceNames();
    sort($names);
    echo $name, ' => ', implode(', ', $names) ?: '(none)', "\n";
}

var_dump($o instanceof Deep::<int>);
var_dump($o instanceof Mid::<int>);
var_dump($o instanceof RO::<int>);
?>
--EXPECT--
C<int> => Deep, Deep<int>, Mid, Mid<int>, RO, RO<int>
Deep<int> => Deep, Mid, Mid<int>, RO, RO<int>
Mid<int> => Mid, RO, RO<int>
RO<int> => RO
bool(true)
bool(true)
bool(true)
