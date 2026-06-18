<?php

// destroy_op_array must not read the preloaded generic op's already-freed shared runtime cache (UAF).
function inner<T : int|float|string>(T $x): T {
    return $x;
}

function outer<T : int|float|string>(T $x): T {
    return inner($x);
}
