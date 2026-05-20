--TEST--
Reification: forwarded T-ref in turbofish (id::<U>(...) inside nested<U>) resolves the value-arg check through the caller's bindings
--FILE--
<?php
class Animal {}
class Dog extends Animal {}
class Cat extends Animal {}

function id<T>(T $x): T { return $x; }

// Same-type forwarding: U binds to Dog at the outer call, the inner
// id::<U>($x) forwards U into id's T, and id should accept Dog as Dog —
// not as a literal "U" type.
function nested<U>(U $x): U {
    return id::<U>($x);
}
var_dump(nested::<Dog>(new Dog));

// Mismatch caught against the *resolved* class, not the forwarded T-ref.
// The error message must name the bound class ("Dog"), not the parameter
// letter ("U").
function pass_through<U>(mixed $x): U {
    return id::<U>($x);
}
try {
    pass_through::<Dog>(new Cat);
} catch (TypeError $e) {
    echo "caught: ", $e->getMessage(), "\n";
}

// Forwarded scalar binding: U = int, the value 42 satisfies id's T = int.
function nested_scalar<U>(U $x): U {
    return id::<U>($x);
}
var_dump(nested_scalar::<int>(42));

// Forwarded scalar mismatch caught against the resolved scalar type:
// pass_through_scalar<U>(mixed $x) doesn't constrain $x at its own
// boundary, so the string "hello" reaches the inner id::<U>(...) call.
// id's T is resolved to int via U, and the value check fires with the
// resolved type ("int"), not the literal T-ref letter.
function pass_through_scalar<U>(mixed $x): U {
    return id::<U>($x);
}
try {
    pass_through_scalar::<int>("hello");
} catch (TypeError $e) {
    echo "caught: ", $e->getMessage(), "\n";
}

// Composite forwarded bindings: a union arg threaded through a forwarding
// wrapper still names the union ("int|string") in the error, not the
// T-ref letter. Same with intersection.
try {
    pass_through_scalar::<int|string>([1, 2]);
} catch (TypeError $e) {
    echo "caught: ", $e->getMessage(), "\n";
}

// Intersection forwarded through a wrapper: positive case (a value
// implementing both interfaces) passes; negative reports the resolved
// intersection ("Fooable&Barable"), not the T-ref letter.
interface Fooable {}
interface Barable {}
class Both implements Fooable, Barable {}
class FooOnly implements Fooable {}

function pass_through_obj<U>(object $x): U {
    return id::<U>($x);
}
var_dump(pass_through_obj::<Fooable&Barable>(new Both));
try {
    pass_through_obj::<Fooable&Barable>(new FooOnly);
} catch (TypeError $e) {
    echo "caught: ", $e->getMessage(), "\n";
}
?>
--EXPECTF--
object(Dog)#%d (0) {
}
caught: id(): Argument #1 ($x) must be of type Dog, Cat given
int(42)
caught: id(): Argument #1 ($x) must be of type int, string given
caught: id(): Argument #1 ($x) must be of type int|string, array given
object(Both)#%d (0) {
}
caught: id(): Argument #1 ($x) must be of type Fooable&Barable, FooOnly given
