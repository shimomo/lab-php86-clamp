<?php

// Test F (3/3): a namespaced clamp() is a different function from the global one, so it survives
// PHP 8.6 and unqualified calls inside the namespace still reach it. The helper takes its arguments
// in CSS order (min, value, max) to make it obvious which function answered: the native one would
// read the same call as value 0, min 150, max 100 and throw.

namespace App\Support;

function clamp(int|float $min, int|float $value, int|float $max): int|float
{
    return max($min, min($max, $value));
}

echo 'PHP ', PHP_VERSION, ', global clamp() exists: ', var_export(\function_exists('clamp'), true), "\n";
echo 'clamp(0, 150, 100) inside App\Support => ';
var_dump(clamp(0, 150, 100));
