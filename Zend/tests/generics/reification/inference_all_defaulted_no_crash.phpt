--TEST--
Reification: a non-turbofish call to an all-defaulted generic callee with non-literal args infers nothing and stays generic (must not build an empty type-arg box)
--FILE--
<?php
// Inferring zero type args must bail, not build a zero-length box (underflow -> crash).
function neighbors<TNode = mixed, TWeight = mixed>(mixed $graph, TNode $node): array {
    return [$node];
}

function caller(mixed $g, mixed $n): array {
    return neighbors($g, $n);
}

var_dump(caller('graph', 'x'));
var_dump(caller('graph', 42));
echo "ok\n";
?>
--EXPECT--
array(1) {
  [0]=>
  string(1) "x"
}
array(1) {
  [0]=>
  int(42)
}
ok
