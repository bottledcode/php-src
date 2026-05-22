--TEST--
Type arguments on a non-generic class in `catch` are a compile-time error
--FILE--
<?php
class MyErr extends Exception {}
try {
    throw new MyErr('boom');
} catch (MyErr<int> $e) {
}
?>
--EXPECTF--
Fatal error: Type arguments are not allowed on non-generic class MyErr in %s on line %d
