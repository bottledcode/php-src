--TEST--
Reification: a subclass `extends Box<int>` carries the parent's T binding through inherited method bodies
--FILE--
<?php
class Foo {}

class Box<T : object> {
    public function make(): T {
        return new T();
    }
}

class FooBox extends Box<Foo> {}

// FooBox::make is inherited from Box<Foo>; the binding T=Foo on FooBox's parent
// must be visible to the inherited body via the called scope.
$fb = new FooBox();
var_dump(get_class($fb->make()));
?>
--EXPECT--
string(3) "Foo"
