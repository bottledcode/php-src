--TEST--
Reification: a generic method on a non-generic class can use T inside its body
--FILE--
<?php
class Foo { public string $tag = "foo"; }
class Bar { public string $tag = "bar"; }

class Factory {
    public function build<T : object>(): T {
        return new T();
    }
}

$f = new Factory();
var_dump($f->build::<Foo>()->tag);
var_dump($f->build::<Bar>()->tag);
?>
--EXPECT--
string(3) "foo"
string(3) "bar"
