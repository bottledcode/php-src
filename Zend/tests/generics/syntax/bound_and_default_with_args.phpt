--TEST--
Generic syntax: bound and default both carry type arguments, closing >>
--FILE--
<?php
class B<U> {}

// class A has a generic parameter T with bound B<int> and default B<int>.
// Same args on both so the default satisfies the bound under invariant T —
// the test exercises the parser's `>>` splitting in bound + default position.
class A<T:B<int>=B<int>> {}

$p = (new ReflectionClass('A'))->getGenericParameters()[0];

$b = $p->getBound();
echo "bound: ", $b->getName(), "<", $b->getGenericArguments()[0]->getName(), ">\n";

$d = $p->getDefault();
echo "default: ", $d->getName(), "<", $d->getGenericArguments()[0]->getName(), ">\n";
?>
--EXPECT--
bound: B<int>
default: B<int>
