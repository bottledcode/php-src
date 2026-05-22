--TEST--
Diamond + invariant T: arity mismatch is the one remaining rejection at the diamond stage
--FILE--
<?php
interface Multi<A, B> {}
interface Alpha extends Multi<int, string> {}
interface Beta extends Multi<int, string, float> {}
?>
--EXPECTF--
Fatal error: Too many generic type arguments to extends Multi in Beta, 3 passed and exactly 2 expected in %s on line %d
