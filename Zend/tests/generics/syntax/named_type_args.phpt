--TEST--
Generic syntax: type arguments on a named type at use site
--FILE--
<?php
class Container<T> {}
function f(Container<int> $x): Container<string> { return $x; }
$r = new ReflectionFunction('f');
$pt = $r->getParameters()[0]->getType();
// With reified params, the type's name is the canonical monomorph, not the
// bare base. Generic args are still queryable via getGenericArguments().
echo $pt->getName(), "\n";
var_dump($pt->hasGenericArguments());
foreach ($pt->getGenericArguments() as $a) echo $a->getName(), "\n";
?>
--EXPECT--
Container<int>
bool(true)
int
