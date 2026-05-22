--TEST--
Reification: a union `T|null` (and other unions containing T) on a property reifies the T-ref the same way `?T` does
--FILE--
<?php
declare(strict_types=1);

// `?T` already works because the nullable bit lives on the same zend_type as
// the T-ref, so the leaf-substitution path resolves it. `T|null` written as
// a union must also resolve: T inside the union list needs to be replaced
// with the monomorph binding, otherwise property writes blow up with
// "Cannot assign int to property X::$w of type T".

class TNull<T> {
    public T|null $w;
    public function __construct(T|null $w) { $this->w = $w; }
}

class TInt<T> {
    public T|int $w;
    public function __construct(T|int $w) { $this->w = $w; }
}

class Foo {}
class Bar {}
class Baz {}

class TFoo<T> {
    public T|Foo $w;
    public function __construct(T|Foo $w) { $this->w = $w; }
}

// T|null with primitive T accepts T and null and rejects others.
$a = new TNull::<int>(5);
var_dump($a->w);
$a->w = null;
var_dump($a->w);
try { $a->w = "x"; } catch (TypeError $e) { echo "1: ", $e->getMessage(), "\n"; }

// T|int: T=string accepts strings and ints; under strict_types, a float is
// rejected outright.
$b = new TInt::<string>("hi");
var_dump($b->w);
$b->w = 7;
var_dump($b->w);
try { $b->w = 1.5; } catch (TypeError $e) { echo "2: ", $e->getMessage(), "\n"; }

// T|Foo: T=Bar accepts Bar or Foo, rejects an unrelated class.
$c = new TFoo::<Bar>(new Bar);
var_dump($c->w::class);
$c->w = new Foo;
var_dump($c->w::class);
try { $c->w = new Baz; } catch (TypeError $e) { echo "3: ", $e->getMessage(), "\n"; }
?>
--EXPECT--
int(5)
NULL
1: Cannot assign string to property TNull::$w of type ?int
string(2) "hi"
int(7)
2: Cannot assign float to property TInt::$w of type string|int
string(3) "Bar"
string(3) "Foo"
3: Cannot assign Baz to property TFoo::$w of type Bar|Foo
