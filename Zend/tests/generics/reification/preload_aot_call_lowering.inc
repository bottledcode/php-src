<?php

namespace Bench;

function id<T>(T $x): T { return $x; }

function add<T : int|float>(T $a, T $b): T { return $a + $b; }

// No value params: dispatch must fire from the turbofish alone, nothing inferable from args.
function zero<T : int|float|string>(): string { return "z"; }

class Runner {
    public static function ints(int $n): int {
        $acc = 0;
        for ($i = 0; $i < $n; $i++) {
            $acc = add::<int>($acc, id::<int>($i));
        }
        return $acc;
    }

    public static function strs(): string {
        return id::<string>("hello") . ":" . zero::<int>();
    }

    public static function badType(): void {
        add::<int>(1, []);
    }
}
