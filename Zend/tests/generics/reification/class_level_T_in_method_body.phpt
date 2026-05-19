--TEST--
Reification: class-level T resolves at runtime inside instance method bodies
--FILE--
<?php
interface Tag { const KIND = "default"; public static function make(): static; }

class Foo implements Tag {
    const KIND = "foo";
    public static function make(): static { return new static(); }
}

class Bar implements Tag {
    const KIND = "bar";
    public static function make(): static { return new static(); }
}

class Box<T : Tag> {
    public function summary(object $x): array {
        return [
            "new T()"     => get_class(new T()),
            "T::KIND"     => T::KIND,
            "T::class"    => T::class,
            "T::make()"   => get_class(T::make()),
            "instanceof"  => $x instanceof T,
        ];
    }
}

var_dump((new Box::<Foo>())->summary(new Foo()));
var_dump((new Box::<Bar>())->summary(new Foo()));
?>
--EXPECT--
array(5) {
  ["new T()"]=>
  string(3) "Foo"
  ["T::KIND"]=>
  string(3) "foo"
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
  ["T::KIND"]=>
  string(3) "bar"
  ["T::class"]=>
  string(3) "Bar"
  ["T::make()"]=>
  string(3) "Bar"
  ["instanceof"]=>
  bool(false)
}
