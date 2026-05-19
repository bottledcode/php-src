--TEST--
catch (T $e) silently does not match when T has no usable binding
--FILE--
<?php
function f<T>(): void {
    // T has no class bound and no caller-supplied binding. catch (T $e)
    // is therefore "catch nothing"; the thrown exception propagates out.
    try { throw new Exception("boom"); } catch (T $e) { echo "caught\n"; }
}
try {
    f();
} catch (Throwable $e) {
    echo "outer: ", $e->getMessage(), "\n";
}
?>
--EXPECT--
outer: boom
