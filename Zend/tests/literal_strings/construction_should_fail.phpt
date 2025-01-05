--TEST--
Cannot construct LiteralString
--FILE--
<?php

$b = new LiteralString('hello');
?>
--EXPECTF--
Fatal error: Uncaught Error: Call to private LiteralString::__construct() from global scope in %s:%d
Stack trace:
#0 {main}
  thrown in %s on line %d