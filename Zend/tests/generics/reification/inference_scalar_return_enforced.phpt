--TEST--
Reification: a :T return type reified from an inferred scalar T is enforced
--FILE--
<?php
// T is inferred from the argument. A method declared `: T` must therefore
// enforce the inferred scalar type on its return value rather than silently
// erasing T to mixed.
function bad<T = mixed>(T $a): T { return "not the same type"; }

echo "calling bad(1) — T inferred as int, returning a string must fail\n";
try {
    var_dump(bad(1));
} catch (TypeError $e) {
    echo "TypeError: ", $e->getMessage(), "\n";
}

// Returning a matching type is fine.
function good<T = mixed>(T $a): T { return $a; }
var_dump(good(42));
var_dump(good("ok"));
?>
--EXPECT--
calling bad(1) — T inferred as int, returning a string must fail
TypeError: bad(): Return value must be of type int, string returned
int(42)
string(2) "ok"
