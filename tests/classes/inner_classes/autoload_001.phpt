--TEST--
ensure autoloading works
--FILE--
<?php
spl_autoload_register(static function ($class_name) {
    if (file_exists(__DIR__ . '/' . $class_name . '.inc')) {
        require_once(__DIR__ . '/' . $class_name . '.inc');
    }
    echo 'autoload(' . $class_name . ")\n";
});

$point = new inner_classes\Point(1, 2);
echo $point->x, ' ', $point->y, "\n";
?>
--EXPECT--
autoload(inner_classes)
1 2
