--TEST--
Generic syntax: three levels of nesting (>>> splitting)
--FILE--
<?php
class C<T> {}
class B<T> {}
class A<T> {}
function f(): A<B<C<int>>> { return new A::<B<C<int>>>(); }
$rt = (new ReflectionFunction('f'))->getReturnType();
echo $rt->getName(), "\n";
$arg1 = $rt->getGenericArguments()[0];
echo $arg1->getName(), "\n";
$arg2 = $arg1->getGenericArguments()[0];
echo $arg2->getName(), "\n";
echo $arg2->getGenericArguments()[0]->getName(), "\n";
?>
--EXPECT--
A<B<C<int>>>
B
C
int
