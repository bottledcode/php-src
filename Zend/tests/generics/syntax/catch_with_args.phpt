--TEST--
Generic syntax: catch with type arguments compares against the monomorph canonical name
--FILE--
<?php
class MyErr<T> extends Exception {}
// Throwing MyErr<int> matches a `catch (MyErr<int>)` block; a `catch (MyErr<string>)`
// would not, because the two are distinct monomorphs.
try {
    throw new MyErr::<int>('boom');
} catch (MyErr<string> $e) {
    echo "wrong-mono caught: ", $e->getMessage(), "\n";
} catch (MyErr<int> $e) {
    echo "right-mono caught: ", $e->getMessage(), "\n";
}
?>
--EXPECT--
right-mono caught: boom
