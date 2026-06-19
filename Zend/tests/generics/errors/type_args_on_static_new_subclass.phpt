--TEST--
`new static::<...>` is rejected even when the runtime type is a non-generic subclass of a monomorph
--FILE--
<?php
// Here `static` would resolve to IntBox, which extends the monomorph Box<int>
// but is itself non-generic — it has no type parameters to apply `<T>` to. The
// rejection is the same compile-time scoping error: use `new static()`.
class Box<T> {
    public function __construct(private mixed $v = null) {}
    public function make(): object { return new static::<T>($this->v); }
}
class IntBox extends Box<int> {}

(new IntBox(5))->make();
?>
--EXPECTF--
Fatal error: Type arguments cannot be applied to "static": "static" is the runtime type and already carries its type arguments. Use "new static()" to construct the exact runtime type, or "new self::<...>" to apply type arguments to the enclosing class in %s on line %d
