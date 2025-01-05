--TEST--
Literal strings as function arguments
--ARGS--
test
--FILE--
<?php

function str(string $s): string {
    return $s;
}

function lst(LiteralString $s): string {
    return $s;
}

function lstr(string $s): LiteralString {
    return $s;
}

function call(string $s, string $func): string {
    try {
        return $func($s);
    } catch (TypeError $e) {
        return $e->getMessage();
    }
}

$lit = LiteralString::from("hello");
$lit = 'hello';

var_dump(is_literal_string($lit));
var_dump(is_literal_string($argv[1]));

var_dump(call($lit, 'str'));
var_dump(call($lit, 'lst'));
var_dump(call($lit, 'lstr'));

$ls = $argv[1];
var_dump(call($ls, 'str'));
var_dump(call($ls, 'lst'));
var_dump(call($ls, 'lstr'));

$ls = $lit . $argv[1];
var_dump(call($ls, 'str'));
var_dump(call($ls, 'lst'));
var_dump(call($ls, 'lstr'));
?>
--EXPECT--
bool(true)
bool(false)
string(5) "hello"
string(5) "hello"
string(5) "hello"
string(4) "test"
string(173) "lst(): Argument #1 ($s) must be of type LiteralString, string given, called in /home/withinboredom/code/php-src/Zend/tests/literal_strings/literal_strings_001.php on line 17"
string(67) "lstr(): Return value must be of type LiteralString, string returned"
string(9) "hellotest"
string(173) "lst(): Argument #1 ($s) must be of type LiteralString, string given, called in /home/withinboredom/code/php-src/Zend/tests/literal_strings/literal_strings_001.php on line 17"
string(67) "lstr(): Return value must be of type LiteralString, string returned"
