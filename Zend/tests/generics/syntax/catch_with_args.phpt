--TEST--
Generic syntax: catch with type arguments compares against the monomorph canonical name
--FILE--
<?php
class MyErr extends Exception {}
// MyErr is non-generic, so the canonical name MyErr<int> does not exist as a
// class. catch (MyErr<int>) therefore never matches; the original exception
// propagates to the outer catch.
try {
    try {
        throw new MyErr('boom');
    } catch (MyErr<int> $e) {
        echo "inner caught: ", $e->getMessage(), "\n";
    }
} catch (MyErr $e) {
    echo "outer caught: ", $e->getMessage(), "\n";
}
?>
--EXPECT--
outer caught: boom
