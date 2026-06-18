--TEST--
Static properties are shared between a generic base and all of its monomorphs
--FILE--
<?php
// A static property can never be typed `T` (static context has no T binding),
// so there is no per-instantiation state to isolate: the base and every
// monomorph share one storage, exactly as a normal class hierarchy does. This
// is what makes the static-registry-on-a-base pattern (Macroable et al.) work.
class Counter<T = int> {
    public static int $count = 0;
    public static array $items = [];
    public static function bump(): void { static::$count++; }
}

new Counter::<int>();
new Counter::<string>();

$intCls = "Counter<int>";
$strCls = "Counter<string>";

// Writes through any view land in the same storage.
$intCls::$count = 5;
$strCls::$count = 10;   // overwrites the shared slot
var_dump($intCls::$count, $strCls::$count, Counter::$count);

// Appends accumulate into one array regardless of the view used.
$intCls::$items[] = "via-int";
$strCls::$items[] = "via-string";
Counter::$items[] = "via-base";
var_dump(Counter::$items);

// LSB `static::` from a monomorph instance reaches the same storage as the base.
$c = new Counter::<int>();
$c::bump();          // static = Counter<int> -> shared slot (now 11)
Counter::bump();     // base -> same slot (now 12)
var_dump($c::$count, Counter::$count);
?>
--EXPECT--
int(10)
int(10)
int(10)
array(3) {
  [0]=>
  string(7) "via-int"
  [1]=>
  string(10) "via-string"
  [2]=>
  string(8) "via-base"
}
int(12)
int(12)
