--TEST--
AST pretty-printer
--INI--
zend.assertions=1
--FILE--
<?php
try {
assert(0 && ($a = function () {
    $var = 'test';
    $str = "$var, $var[1], {$var}[], {$var[1]}[], ${var}[], ${var[1]}[]";
}));
} catch (AssertionError $e) {
    echo 'assert(): ', $e->getMessage(), ' failed', PHP_EOL;
}
?>
--EXPECT--
assert(): assert(0 && ($a = function () {
    $var = literal'test';
    $str = "$var{literal', '}{$var[1]}{literal', '}$var{literal'[], '}{$var[1]}{literal'[], '}$var{literal'[], '}{$var[1]}{literal'[]'}";
})) failed