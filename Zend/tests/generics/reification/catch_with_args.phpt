--TEST--
Reification: catch with concrete type arguments matches the corresponding monomorph
--FILE--
<?php
class Bag<T : Throwable> extends Exception {}

// Throw a synthesized monomorph of Bag and catch it via the canonical name.
try {
    throw new Bag::<RuntimeException>("payload");
} catch (Bag<RuntimeException> $e) {
    echo "caught Bag<RuntimeException>: ", $e->getMessage(), "\n";
}

// Wrong arg in the catch — the canonical names differ, so no match.
try {
    try {
        throw new Bag::<RuntimeException>("payload");
    } catch (Bag<LogicException> $e) {
        echo "inner caught\n";
    }
} catch (Bag $e) {
    echo "outer caught Bag (parent): ", $e->getMessage(), "\n";
}
?>
--EXPECT--
caught Bag<RuntimeException>: payload
outer caught Bag (parent): payload
