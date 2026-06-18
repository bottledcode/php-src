--TEST--
Late static binding from inside a monomorph: static::class / static::$prop / static::FOO
--FILE--
<?php
class Box<T = int> {
    public static int $count = 0;
    const TAG = "Box";

    public static function whoAmI(): string { return static::class; }
    public static function bump(): void { static::$count++; }
    public static function tag(): string { return static::TAG; }
    public static function self_(): static { return new static(0); }

    public function __construct(public mixed $value) {}
}

class StrBox extends Box<string> {
    public static int $count = 100;
    const TAG = "StrBox";
}

$intCls = "Box<int>";
$strCls = "Box<string>";

// static::class returns the late-bound class.
var_dump($intCls::whoAmI());
var_dump($strCls::whoAmI());
var_dump(StrBox::whoAmI());

// static::$prop sees the late-bound class's storage. Monomorphs share the
// base's static property (Box<int> and Box<string> are one slot); StrBox
// redeclares $count, so it gets its own — exactly as a subclass does in
// stock PHP.
$intCls::bump();
$intCls::bump();
$strCls::bump();
StrBox::bump();
var_dump($intCls::$count);
var_dump($strCls::$count);
var_dump(StrBox::$count);

// static::FOO honours overrides via LSB.
var_dump($intCls::tag());
var_dump(StrBox::tag());

// new static() returns the late-bound monomorph instance.
$a = $intCls::self_();
$b = StrBox::self_();
var_dump($a::class);
var_dump($b::class);
?>
--EXPECT--
string(8) "Box<int>"
string(11) "Box<string>"
string(6) "StrBox"
int(3)
int(3)
int(101)
string(3) "Box"
string(6) "StrBox"
string(8) "Box<int>"
string(6) "StrBox"
