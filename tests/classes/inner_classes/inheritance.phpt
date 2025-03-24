--TEST--
inheritance
--FILE--
<?php

class Outer {
    abstract class Other {}
    class Middle extends Other {
        class Inner1 extends Other {}
        class Inner2 extends Middle {}
    }
}
?>
--EXPECT--
