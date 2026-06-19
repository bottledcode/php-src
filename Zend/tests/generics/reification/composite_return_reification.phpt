--TEST--
Reification: composite return/param types (T|Other, A|B, T&B) are reified and enforced, with null/member folding
--FILE--
<?php
interface A {} interface B {}
class AB implements A, B {}
class Foo {} class Other {}

// A union mixing a type parameter with a concrete class reifies and enforces.
function ret<T>(mixed $x): T|Other { return $x; }
var_dump(ret::<Foo>(new Foo)::class);     // Foo member
var_dump(ret::<Foo>(new Other)::class);   // Other member
try { ret::<Foo>(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// When T resolves to the other member the union folds (Other|Other -> Other).
try { ret::<Other>(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// A union *binding* flattens into the union position (Foo|Other)|Other -> Foo|Other.
var_dump(ret::<Foo|Other>(new Foo)::class);
try { ret::<Foo|Other>(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// A union of two type parameters.
function two<X, Y>(mixed $x): X|Y { return $x; }
var_dump(two::<Foo, Other>(new Other)::class);
try { two::<Foo, Other>(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// Composite parameter types are enforced too.
function par<T>(T|Other $x): string { return $x::class; }
var_dump(par::<Foo>(new Foo));
try { par::<Foo>(42); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// Parameters fold/flatten through the same path as returns: T=Other folds the
// duplicate, and a union binding flattens. The reified param type is observable.
par::<Other>(new Other);
var_dump((string) (new ReflectionFunction('par<Other>'))->getParameters()[0]->getType());
par::<Foo|Other>(new Foo);
var_dump((string) (new ReflectionFunction('par<Foo|Other>'))->getParameters()[0]->getType());

// Intersection return: T bound to an interface, T&B reifies to A&B.
function inter<T: A>(mixed $x): T&B { return $x; }
var_dump(inter::<A>(new AB)::class);
try { inter::<A>(new Foo); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }

// DNF: an intersection binding spliced into a union stays nested ((A&B)|Other).
function dnf<T: A>(mixed $x): T|Other { return $x; }
var_dump(dnf::<A&B>(new AB)::class);
var_dump(dnf::<A&B>(new Other)::class);
try { dnf::<A&B>(new Foo); } catch (TypeError $e) { echo $e->getMessage(), "\n"; }
?>
--EXPECTF--
string(3) "Foo"
string(5) "Other"
ret(): Return value must be of type Foo|Other, int returned
ret(): Return value must be of type Other, int returned
string(3) "Foo"
ret(): Return value must be of type Foo|Other, int returned
string(5) "Other"
two(): Return value must be of type Foo|Other, int returned
string(3) "Foo"
par(): Argument #1 ($x) must be of type Foo|Other, int given, called in %s on line %d
string(5) "Other"
string(9) "Foo|Other"
string(2) "AB"
inter(): Return value must be of type A&B, Foo returned
string(2) "AB"
string(5) "Other"
dnf(): Return value must be of type (A&B)|Other, Foo returned
