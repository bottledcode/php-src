--TEST--
Provenance of string is kept
--ARGS--
hello
--FILE--
<?php

function inner(LiteralString $a) {
	$a .= " world";
	var_dump($a);
}

function outer(string $a) {
	inner($a);
}

outer('hello');
outer($argv[1]);
--EXPECTF--
string(11) "hello world"

Fatal error: Uncaught TypeError: inner(): Argument #1 ($a) must be of type LiteralString, string given, called in %s:%d
Stack trace:
#0 %s(%d): inner('hello')
#1 %s(%d): outer('hello')
#2 {main}
  thrown in %s on line %d