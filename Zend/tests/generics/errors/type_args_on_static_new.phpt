--TEST--
Type arguments on `new static::<...>` are a compile-time error (static is the runtime type)
--FILE--
<?php
// `static` is the runtime type, which already carries its own type arguments
// and whose parameter list is unknown at this lexical site (it may be a generic
// subclass with a different list, or a non-generic subclass of a monomorph).
// The written args name the *enclosing* class's parameters, so applying them to
// `static` is a scoping error — rejected at compile time rather than crashing
// or throwing ArgumentCountError at runtime.
class C<TKey, T> {
    public function __construct(private array $e = []) {}
    public function dup(): static { return new static::<TKey, T>($this->e); }
}
(new C::<mixed, mixed>([1, 2]))->dup();
?>
--EXPECTF--
Fatal error: Type arguments cannot be applied to "static": "static" is the runtime type and already carries its type arguments. Use "new static()" to construct the exact runtime type, or "new self::<...>" to apply type arguments to the enclosing class in %s on line %d
