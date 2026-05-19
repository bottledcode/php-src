--TEST--
Reification: every T-keyed expression form resolves through the frame's T-table
--FILE--
<?php
interface Maker { const NAME = "default"; public static function make(): static; }

class Foo implements Maker {
    const NAME = "Foo";
    public static function make(): static { return new static(); }
}

class Bar implements Maker {
    const NAME = "Bar";
    public static function make(): static { return new static(); }
}

function show<T : Maker>($x): array {
    return [
        "new T()"   => get_class(new T()),
        "T::NAME"   => T::NAME,
        "T::class"  => T::class,
        "T::make()" => get_class(T::make()),
        "instanceof"=> $x instanceof T,
    ];
}

var_dump(show::<Foo>(new Foo()));
var_dump(show::<Bar>(new Foo()));
?>
--EXPECT--
array(5) {
  ["new T()"]=>
  string(3) "Foo"
  ["T::NAME"]=>
  string(3) "Foo"
  ["T::class"]=>
  string(3) "Foo"
  ["T::make()"]=>
  string(3) "Foo"
  ["instanceof"]=>
  bool(true)
}
array(5) {
  ["new T()"]=>
  string(3) "Bar"
  ["T::NAME"]=>
  string(3) "Bar"
  ["T::class"]=>
  string(3) "Bar"
  ["T::make()"]=>
  string(3) "Bar"
  ["instanceof"]=>
  bool(false)
}
