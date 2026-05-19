--TEST--
Reification: catch (T $e) inside a method body matches against the class-level T binding
--FILE--
<?php
class MyExc extends Exception {}
class OtherExc extends Exception {}

class Handler<T : Throwable> {
    public function trap(callable $fn): string {
        try {
            $fn();
            return "no-throw";
        } catch (T $e) {
            return "caught " . $e::class . ": " . $e->getMessage();
        }
    }
}

$h = new Handler::<MyExc>();
echo $h->trap(fn() => throw new MyExc("hi")), "\n";   // T = MyExc → caught
try {
    $h->trap(fn() => throw new OtherExc("bye"));      // T = MyExc → rethrows
} catch (Throwable $e) {
    echo "outer ", $e::class, ": ", $e->getMessage(), "\n";
}
?>
--EXPECT--
caught MyExc: hi
outer OtherExc: bye
