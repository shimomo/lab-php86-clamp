<?php

// Test A: the examples from the clamp_v2 RFC (https://wiki.php.net/rfc/clamp_v2), plus the signature
// as PHP itself reports it.

require __DIR__ . '/lib.php';

banner('A: basics');

section('signature');
echo signature('clamp'), "\n";

section('value inside, below and above the range');
show('clamp(2, 1, 3)');
show('clamp(0, 1, 3)');
show('clamp(6, 1, 3)');
show('clamp(min: 0, value: 120, max: 90)');

section('int and float mixed');
show('clamp(2, 1.3, 3.4)');
show('clamp(2.5, 1, 3)');
show('clamp(2.5, 1.3, 3.4)');
show('clamp(0, 1.3, 3.4)');
show('clamp(M_PI, -INF, INF)');

section('anything comparable');
show('clamp("a", "c", "g")');
show('clamp("d", "c", "g")');
show('clamp(d("2025-08-01"), d("2025-08-15"), d("2025-09-15"))');
show('clamp(d("2025-08-20"), d("2025-08-15"), d("2025-09-15"))');

section('NAN and invalid bounds');
show('clamp(NAN, 4, 6)');
show('clamp(4, NAN, 6)');
show('clamp(4, 6, NAN)');
show('clamp(4, 8, 6)');
