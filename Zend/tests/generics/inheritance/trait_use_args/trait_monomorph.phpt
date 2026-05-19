--TEST--
Trait-use-with-args: each `use Foo<X>;` binds to the canonical monomorph trait
--FILE--
<?php
trait Box<T = mixed> {
    public T $value;
    public function get(): T { return $this->value; }
    public function set(T $v): void { $this->value = $v; }
}

class IntHolder {
    use Box<int>;
}

class StrHolder {
    use Box<string>;
}

$i = new IntHolder();
$i->set(42);
var_dump($i->get());

$s = new StrHolder();
$s->set("hi");
var_dump($s->get());

// Substituted property type is visible per use-site.
$rcI = new ReflectionClass(IntHolder::class);
var_dump($rcI->getTraitNames());
echo "IntHolder::value type: ", $rcI->getProperty('value')->getType(), "\n";

$rcS = new ReflectionClass(StrHolder::class);
var_dump($rcS->getTraitNames());
echo "StrHolder::value type: ", $rcS->getProperty('value')->getType(), "\n";

// The trait monomorphs are registered as traits.
var_dump(trait_exists("Box<int>", false));
var_dump(trait_exists("Box<string>", false));
var_dump((new ReflectionClass("Box<int>"))->isTrait());

// Wrong-type set throws TypeError on the substituted signature.
try {
    $i->set("not int");
} catch (TypeError $e) {
    echo "ok: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
int(42)
string(2) "hi"
array(1) {
  [0]=>
  string(8) "Box<int>"
}
IntHolder::value type: int
array(1) {
  [0]=>
  string(11) "Box<string>"
}
StrHolder::value type: string
bool(true)
bool(true)
bool(true)
ok: %sset()%sint%s
