<?php

// Test D: what one call costs compared with the ways of writing the same thing today.
// run.sh runs it twice: with PHP's defaults, and with opcache and the tracing JIT enabled.

require __DIR__ . '/lib.php';

const CALLS = 5_000_000;
const ROUNDS = 9;

function clamp_ternary(int|float $value, int|float $min, int|float $max): int|float
{
    return $value < $min ? $min : ($value > $max ? $max : $value);
}

// The shape of Laravel's Number::clamp().
function clamp_minmax(int|float $value, int|float $min, int|float $max): int|float
{
    return min(max($value, $min), $max);
}

function loop_only(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $sum += $values[$i & 1023];
    }

    return $sum;
}

function native_clamp(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $sum += clamp($values[$i & 1023], 0, 100);
    }

    return $sum;
}

function inline_minmax(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $sum += max(0, min(100, $values[$i & 1023]));
    }

    return $sum;
}

function inline_ternary(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $value = $values[$i & 1023];
        $sum += $value < 0 ? 0 : ($value > 100 ? 100 : $value);
    }

    return $sum;
}

function helper_ternary(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $sum += clamp_ternary($values[$i & 1023], 0, 100);
    }

    return $sum;
}

function helper_minmax(array $values): int
{
    $sum = 0;
    for ($i = 0; $i < CALLS; $i++) {
        $sum += clamp_minmax($values[$i & 1023], 0, 100);
    }

    return $sum;
}

banner('D: benchmark');
$status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
echo '# opcache: ', is_array($status) && $status['opcache_enabled'] ? 'on' : 'off',
    ', JIT: ', is_array($status) && ($status['jit']['on'] ?? false) ? 'on' : 'off', "\n";

// Integers from -50 to 150, so about half of the calls return the value and half return a bound.
mt_srand(1);
$values = [];
for ($i = 0; $i < 1024; $i++) {
    $values[] = mt_rand(-50, 150);
}

$variants = [
    'loop only, no clamping' => 'loop_only',
    'clamp()' => 'native_clamp',
    'inline max(min())' => 'inline_minmax',
    'inline ternary' => 'inline_ternary',
    'helper function (ternary)' => 'helper_ternary',
    'helper function (min/max)' => 'helper_minmax',
];

$best = array_fill_keys(array_keys($variants), INF);
$sums = [];
for ($round = 0; $round < ROUNDS; $round++) {
    foreach ($variants as $label => $function) {
        $start = hrtime(true);
        $sums[$label] = $function($values);
        $best[$label] = min($best[$label], (hrtime(true) - $start) / 1e6);
    }
}

// "net" is the time per call with the cost of the bare loop taken out.
$perCall = array_map(static fn (float $ms): float => $ms * 1e6 / CALLS, $best);
$loop = $perCall['loop only, no clamping'];

section(number_format(CALLS) . ' calls each, best of ' . ROUNDS . ' rounds');
printf("%-28s %8s  %8s  %8s\n", '', 'total ms', 'ns/call', 'net ns');
foreach ($best as $label => $ms) {
    $net = $label === 'loop only, no clamping' ? '-' : sprintf('%.1f', $perCall[$label] - $loop);
    printf("%-28s %8.1f  %8.1f  %8s\n", $label, $ms, $perCall[$label], $net);
}

unset($sums['loop only, no clamping']);
echo "\nevery clamping variant returned the same sum: ", var_export(count(array_unique($sums)) === 1, true), "\n";
