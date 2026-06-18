<?php

// preload_fix_trait_op_array must not re-sync the monomorph's generic-inheritance method clones as trait clones.

final class Vec<T> {
    public function __construct(public array $items = []) {}
    public function with(T $x): Vec<T> {
        $n = $this->items;
        $n[] = $x;
        return new Vec::<T>($n);
    }
    public function sum(): int {
        $s = 0;
        foreach ($this->items as $v) { $s += $v; }
        return $s;
    }
    public function size(): int { return count($this->items); }
}

class Driver {
    public static function build(): string {
        $v = new Vec::<int>([1, 2]);
        $v = $v->with(3);
        $v = $v->with(4);
        return $v->sum() . ':' . $v->size() . ':' . get_class($v);
    }
}
