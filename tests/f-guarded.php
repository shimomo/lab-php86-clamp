<?php

// Test F (2/3): the same helper behind function_exists(). On PHP 8.6 the guard is false, so every
// call reaches the native clamp() instead and the helper's own behaviour silently stops applying.

if (!function_exists('clamp')) {
    function clamp($value, $min, $max)
    {
        return max($min, min($max, $value));
    }
}

require __DIR__ . '/lib.php';

banner('F: a helper named clamp() behind function_exists()');

section('same result either way');
show('clamp(150, 0, 100)');

section('inverted bounds: the helper returned a value, the native function throws');
show('clamp(5, 10, 1)');
