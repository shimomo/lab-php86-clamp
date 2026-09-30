<?php

// Test B: places where a value should be pulled back into a range rather than rejected.

require __DIR__ . '/lib.php';

banner('B: use cases');

section('rounding user input into a range (page size, quantity, slider)');
show('clamp(250, 1, 100)');
show('clamp(0, 1, 100)');
show('clamp(25, 1, 100)');

section('keeping a computed value in range (percentage, probability)');
show('clamp(104.2, 0, 100)');
show('clamp(-0.003, 0.0, 1.0)');

section('absorbing floating-point error before a domain-restricted function');
show('acos(1.0000000002)');
show('acos(clamp(1.0000000002, -1, 1))');

section('capped waiting time (retry backoff in milliseconds)');
show('clamp(100 * 2 ** 3, 100, 30_000)');
show('clamp(100 * 2 ** 12, 100, 30_000)');

section('colours, coordinates, list indexes');
show('clamp(271, 0, 255)');
show('clamp(-8, 0, 255)');
show('clamp(9, 0, count(["a", "b", "c"]) - 1)');

section('dates pulled into a period');
show('clamp(d("2026-09-28"), d("2026-10-01"), d("2026-10-31"))');
show('clamp(d("2026-10-15"), d("2026-10-01"), d("2026-10-31"))');
