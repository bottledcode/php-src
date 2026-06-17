--TEST--
class_exists()/interface_exists() return false (never raise) for out-of-bound or non-generic monomorph names
--FILE--
<?php

class Plain {}
class Box<T : object> {}
interface Iface<T : object> {}

// Bound violation: int is not an object, so "Box<int>" names no class. A
// passive existence probe must report that as false, not raise (the `new` /
// type-declaration paths still enforce the bound with a fatal).
var_dump(class_exists('Box<int>'));
// Bound satisfied -> the monomorph is synthesized on demand.
var_dump(class_exists('Box<stdClass>'));
// Type arguments on a non-generic class name no existing class either.
var_dump(class_exists('Plain<int>'));
// Plain lookups are unaffected.
var_dump(class_exists('Plain'));
var_dump(class_exists('Box'));
// interface_exists() behaves the same way.
var_dump(interface_exists('Iface<int>'));
var_dump(interface_exists('Iface<stdClass>'));

echo "done\n";
?>
--EXPECT--
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
done
