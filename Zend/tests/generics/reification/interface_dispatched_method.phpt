--TEST--
Reification: a generic method called via an interface-typed variable still resolves T
--FILE--
<?php
interface Maker {
    public function build<U : object>(): U;
}

class Factory implements Maker {
    public function build<U : object>(): U {
        return new U();
    }
}

class Foo {}
class Bar {}

function callIt(Maker $m, string $which): object {
    return $which === "foo" ? $m->build::<Foo>() : $m->build::<Bar>();
}

var_dump(get_class(callIt(new Factory(), "foo")));
var_dump(get_class(callIt(new Factory(), "bar")));
?>
--EXPECT--
string(3) "Foo"
string(3) "Bar"
