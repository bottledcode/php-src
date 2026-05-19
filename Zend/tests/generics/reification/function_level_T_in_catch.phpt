--TEST--
Reification: catch (T $e) inside a function body matches against the function-level T binding
--FILE--
<?php
class MyExc extends Exception {}
class OtherExc extends Exception {}

function trap<T : Throwable>(callable $fn): string {
    try {
        $fn();
        return "no-throw";
    } catch (T $e) {
        return "caught " . $e::class . ": " . $e->getMessage();
    }
}

echo trap::<MyExc>(fn() => throw new MyExc("a")), "\n";   // matches
try {
    trap::<MyExc>(fn() => throw new OtherExc("b"));      // doesn't match
} catch (Throwable $e) {
    echo "outer ", $e::class, ": ", $e->getMessage(), "\n";
}
?>
--EXPECT--
caught MyExc: a
outer OtherExc: b
