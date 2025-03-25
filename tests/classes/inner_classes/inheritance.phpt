--TEST--
inheritance
--FILE--
<?php



class Outer {
    class Middle extends Outer\Other {
        class Inner1 extends Other {}
        class Inner2 extends Inner1 {}
    }
    abstract class Other {}
}
?>
--EXPECT--
