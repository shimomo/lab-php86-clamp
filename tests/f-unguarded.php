<?php

// Test F (1/3): a project that already ships its own global clamp(), declared unconditionally.
// The echo comes first on purpose: if it does not print, nothing in this file ran at all.

echo 'PHP ', PHP_VERSION, "\n";

function clamp(int|float $value, int|float $min, int|float $max): int|float
{
    return max($min, min($max, $value));
}

var_dump(clamp(150, 0, 100));
