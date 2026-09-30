<?php

// Test C: behaviour that is easy to trip over. clamp() follows PHP's ordinary comparison rules and
// hands back one of its three arguments untouched, so it neither converts types nor forgives bounds
// that are the wrong way round.

require __DIR__ . '/lib.php';

banner('C: gotchas');

section('the returned value keeps its own type');
show('clamp("50", 1, 100)');
show('clamp("150", 1, 100)');
show('clamp(0.5, 0, 1)');
show('clamp(1.5, 0, 1)');

section('values that are not numbers still compare');
show('clamp(null, 1, 100)');
show('clamp("", 1, 100)');
show('clamp("abc", 1, 100)');
show('clamp(true, 5, 100)');

section('bounds computed at runtime can end up inverted');
show('clamp(3, 0, count([]) - 1)');
show('max(0, min(count([]) - 1, 3))');

section('NAN as the value passes through');
show('clamp(NAN, 0, 1)');
show('max(0, min(1, NAN))');

section('the idiom clamp() replaces is easy to write the wrong way round');
show('max(0, min(100, 50))');
show('min(0, max(100, 50))');
