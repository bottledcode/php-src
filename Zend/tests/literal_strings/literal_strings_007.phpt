--TEST--
LiteralString::From should throw if not literal
--ARGS--
hello
--FILE--
<?php
try {
	LiteralString::From('hello');
	echo 'pass';
} catch (Throwable $e) {
	echo 'fail';
}

try {
	LiteralString::From($argv[1]);
	echo 'pass';
} catch (Throwable $e) {
	echo 'fail';
}
?>
--EXPECT--
passfail
