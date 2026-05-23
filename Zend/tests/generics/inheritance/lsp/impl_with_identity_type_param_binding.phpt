--TEST--
Parametric LSP: child implementing `I<TN>` with its own TN forwarded to parent's TN
--FILE--
<?php
// Regression: when both parent and child bind the same parameter shape
// (parent's TN, child's TN, both bare type-parameter refs), the
// inheritance check needs to treat them as equivalent. The parent's
// substituted return is itself a type-parameter ref so the substitute
// helper falls back to the erased form; the child must follow the same
// fall-back, or the check sees `mixed` vs `TN` and rejects what's
// structurally an identity binding.

interface GI<TN = mixed, TW = mixed> {
    public function getEdgesFrom(TN $from): array;
}

class DG<TN = mixed, TW = mixed> implements GI<TN, TW> {
    public function getEdgesFrom(TN $from): array { return []; }
}

echo "OK\n";
?>
--EXPECT--
OK
