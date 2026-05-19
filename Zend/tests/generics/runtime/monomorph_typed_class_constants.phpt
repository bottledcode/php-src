--TEST--
Typed class constants: per-monomorph substituted type info
--FILE--
<?php
class Box<T = mixed> {
    const T DEFAULT_VALUE = 0;
    const string TAG = "Box"; // non-generic, shared as-is
}

$rcBase = new ReflectionClass(Box::class);
echo "Box::DEFAULT_VALUE type=", $rcBase->getReflectionConstant("DEFAULT_VALUE")->getType(), "\n";
echo "Box::TAG type=", $rcBase->getReflectionConstant("TAG")->getType(), "\n";

$rcInt = new ReflectionClass("Box<int>");
echo "Box<int>::DEFAULT_VALUE type=", $rcInt->getReflectionConstant("DEFAULT_VALUE")->getType(), "\n";
echo "Box<int>::TAG type=", $rcInt->getReflectionConstant("TAG")->getType(), "\n";

$rcStr = new ReflectionClass("Box<string|null>");
echo "Box<string|null>::DEFAULT_VALUE type=", $rcStr->getReflectionConstant("DEFAULT_VALUE")->getType(), "\n";

$rcUnion = new ReflectionClass("Box<int|float>");
echo "Box<int|float>::DEFAULT_VALUE type=", $rcUnion->getReflectionConstant("DEFAULT_VALUE")->getType(), "\n";

// Constant values stay shared (immutable) — substitution only changes the type.
var_dump(Box::DEFAULT_VALUE);
var_dump((new ReflectionClass("Box<int>"))->getReflectionConstant("DEFAULT_VALUE")->getValue());
?>
--EXPECT--
Box::DEFAULT_VALUE type=mixed
Box::TAG type=string
Box<int>::DEFAULT_VALUE type=int
Box<int>::TAG type=string
Box<string|null>::DEFAULT_VALUE type=?string
Box<int|float>::DEFAULT_VALUE type=int|float
int(0)
int(0)
