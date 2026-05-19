--TEST--
Monomorph lookup: class_exists synthesizes the monomorph on demand
--FILE--
<?php
class Box<T = mixed> {
    public function __construct(public mixed $value) {}
}

// Before any synthesis, class_exists on a canonical name should synthesize.
var_dump(class_exists("Box<int>"));
var_dump(class_exists("Box<string>"));
var_dump(class_exists("Box<int|string>"));

// Once synthesized, subsequent lookups hit the cached entry.
var_dump(class_exists("Box<int>"));

// Base class still exists.
var_dump(class_exists("Box"));
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
