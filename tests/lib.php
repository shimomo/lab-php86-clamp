<?php

// Shared helpers for the test scripts: a header that says which clamp() is in use, and a printer
// that puts an expression next to its value or the exception it throws.

function banner(string $title): void
{
    echo "# {$title}\n# PHP ", PHP_VERSION, ', clamp(): ', clamp_provider(), "\n";
}

function clamp_provider(): string
{
    if (!function_exists('clamp')) {
        return 'missing';
    }

    $function = new ReflectionFunction('clamp');
    if ($function->isInternal()) {
        return 'native';
    }

    return str_contains((string) $function->getFileName(), 'polyfill-php86') ? 'symfony/polyfill-php86' : 'userland';
}

function section(string $title): void
{
    echo "\n## {$title}\n";
}

// The expression is evaluated exactly as written, so the label cannot drift from the code that ran.
function show(string $expression): void
{
    try {
        $result = render(eval("return {$expression};"));
    } catch (Throwable $e) {
        $result = $e::class . ': ' . $e->getMessage();
    }

    echo str_pad($expression, 44), ' => ', $result, "\n";
}

function render(mixed $value): string
{
    if ($value instanceof DateTimeInterface) {
        return $value::class . '(' . $value->format('Y-m-d') . ')';
    }

    ob_start();
    var_dump($value);

    return trim(ob_get_clean());
}

function signature(string $function): string
{
    $reflection = new ReflectionFunction($function);
    $parameters = array_map(
        static fn (ReflectionParameter $p): string => ltrim($p->getType() . ' $' . $p->getName()),
        $reflection->getParameters(),
    );

    return $function . '(' . implode(', ', $parameters) . ')'
        . ($reflection->hasReturnType() ? ': ' . $reflection->getReturnType() : '');
}

// Shorthand that keeps the date examples on one line.
function d(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date);
}
