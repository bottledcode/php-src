--TEST--
test return types
--FILE--
<?php

class Outer {
    class Inner {
        public static function test(): Inner {
            return new Inner();
        }
    }
}

var_dump(Outer\Inner::test());
?>
--EXPECT--
object(Outer\Inner)#1 (0) {
}
