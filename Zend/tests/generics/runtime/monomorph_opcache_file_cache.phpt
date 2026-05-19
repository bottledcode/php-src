--TEST--
Opcache file_cache: monomorphs synthesize freshly on each request; cached classes preserve canonical parent_name
--EXTENSIONS--
opcache
--INI--
opcache.enable_cli=1
opcache.file_cache={TMP}
opcache.file_cache_only=1
--FILE--
<?php
class Box<T = int> {
    public function __construct(public mixed $value) {}
}

class IntBox extends Box<int> {}

// Force the same set of synthesis events on every request.
$b = new IntBox(42);
echo "class: ", $b::class, "\n";
echo "parent: ", (new ReflectionClass($b))->getParentClass()->getName(), "\n";

// Defaults monomorph also synthesizes.
$b2 = new Box(7);
echo "defaults: ", $b2::class, "\n";

// Dynamic name still hits the lookup hook on the second-load path.
$name = "Box<string>";
$b3 = new $name("hi");
echo "dyn: ", $b3::class, "\n";
?>
--EXPECT--
class: IntBox
parent: Box<int>
defaults: Box<int>
dyn: Box<string>
