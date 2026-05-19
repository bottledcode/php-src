--TEST--
Implements-with-args: implementing class's direct interface is the canonical monomorph
--FILE--
<?php
interface Iter<T = int> {
    public function next(): T;
}

class IntIter implements Iter<int> {
    public function __construct(public int $v) {}
    public function next(): int { return ++$this->v; }
}

class StrIter implements Iter<string> {
    public function next(): string { return "a"; }
}

$i = new IntIter(0);

// The monomorph is recorded as the directly-implemented interface; the bare
// base shows up transitively.
$rcInt = new ReflectionClass(IntIter::class);
$names = $rcInt->getInterfaceNames();
sort($names);
var_dump($names);

$rcStr = new ReflectionClass(StrIter::class);
$names = $rcStr->getInterfaceNames();
sort($names);
var_dump($names);

// instanceof against the bare base works transitively.
var_dump($i instanceof Iter);

// The monomorph and its base are registered.
var_dump(interface_exists("Iter<int>", false));
var_dump(interface_exists("Iter<string>", false));
var_dump(interface_exists("Iter", false));

// The monomorph itself reports the correct kind.
$rcMono = new ReflectionClass("Iter<int>");
var_dump($rcMono->isInterface());
?>
--EXPECT--
array(2) {
  [0]=>
  string(4) "Iter"
  [1]=>
  string(9) "Iter<int>"
}
array(2) {
  [0]=>
  string(4) "Iter"
  [1]=>
  string(12) "Iter<string>"
}
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
