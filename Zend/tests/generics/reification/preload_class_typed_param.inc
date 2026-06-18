<?php

namespace Zoo;

class Animal {}
class Dog extends Animal {}

// The persisted monomorph's arg_info must name `Zoo\Animal`, not `\Animal`.
function keep<T>(T $x): T { return $x; }

class Keeper {
    public static function take(Animal $a): string {
        return get_class(keep::<Animal>($a));
    }
    public static function bad(): void {
        keep::<Animal>("not an animal");
    }
}
