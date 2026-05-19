--TEST--
Reification: a generator's T-table is preserved across yield/suspend and resume
--FILE--
<?php
class Foo {}
class Bar {}

function gen<T : object>(): Generator {
    yield T::class;
    yield T::class;
    yield T::class;
}

$g = gen::<Foo>();
foreach ($g as $v) echo $v, "\n";

$g = gen::<Bar>();
foreach ($g as $v) echo $v, "\n";
?>
--EXPECT--
Foo
Foo
Foo
Bar
Bar
Bar
