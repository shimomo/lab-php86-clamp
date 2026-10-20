# lab-php86-clamp

[日本語](README.md)

A test environment for checking how [`clamp()`](https://wiki.php.net/rfc/clamp_v2), added in PHP 8.6, behaves, using 8.6.0RC2 from the official Docker image. Created on September 30, 2026.

## Usage

```sh
sh run.sh        # runs every test (A–F) and overwrites their output in logs/
sh run.sh a c    # only tests A and C
```

Requirements:

- Docker (tests A–D, and the 8.6 side of F). The first run pulls `php:8.6.0RC2-cli-alpine`.
- PHP 8.0–8.5 and Composer on the host (test E, and the comparison side of F). Without them, E is skipped and F records only the 8.6 side.

Common pitfalls:

- `logs/` is overwritten on every run. A, B, C, E, and F produce the same output every time, but D's numbers shift by a few ns between runs and machines, so seeing them in `git diff` is normal.
- Override the image with `PHP86_IMAGE` and the host PHP with `PHP85` (e.g. `PHP86_IMAGE=php:8.6-rc-cli-alpine sh run.sh a`).
- E runs `composer install` in `polyfill/`. Composer's home and cache are kept in `.composer-home/` and `.composer-cache/`, so the host's settings and cache are left alone.
- The `Fatal error` in F is the expected result. `run.sh` keeps recording even when a command fails.

## Layout

| Path | Contents |
|---|---|
| `run.sh` | Runs the tests and records them in `logs/`. The 8.6 side runs in Docker and the comparison side on the host's PHP, both with `-n` (no php.ini) |
| `tests/lib.php` | Display helpers. Each expression is kept as a string and `eval`ed, and the expression and its result (or exception) are printed on one line, so what is shown never drifts from what runs |
| `tests/a-basics.php` | A: the RFC's examples and the signature |
| `tests/b-usecases.php` | B: use cases |
| `tests/c-gotchas.php` | C: behavior likely to trip you up |
| `tests/d-bench.php` | D: speed comparison. `run.sh` runs it twice: without opcache, and with opcache plus the tracing JIT |
| `tests/f-*.php` | F: when you already have your own `clamp()` (an unconditional global definition, a definition wrapped in `function_exists()`, a namespaced definition) |
| `polyfill/` | E: Composer project for installing `symfony/polyfill-php86` |
| `logs/` | Records from the tests. Each file name starts with its test's letter |

## Results (September 30, 2026, PHP 8.6.0RC2)

The comparison side is PHP 8.5.9 with `symfony/polyfill-php86` v1.43.0.

| # | What was done | Result |
|---|---|---|
| A | Ran the RFC's examples as-is | Matches the RFC. The signature is `clamp(mixed $value, mixed $min, mixed $max): mixed`. Throws `ValueError` when `$min` or `$max` is NAN, or when `$min > $max`. If `$value` is NAN, it is returned as-is |
| B | Use-case examples (clamping input values, percentages, preparing input for `acos()`, backoff, colors and indexes, dates) | All as expected. `acos(1.0000000002)` is NAN, but with `clamp()` in between it is 0 |
| C | Passing values likely to trip you up | No type conversion (`clamp("50", 1, 100)` returns the string `"50"`, `clamp("150", 1, 100)` the int `100`). `null` and `""` become the lower bound and `"abc"` the upper bound, with no error. `clamp(3, 0, count([]) - 1)` on an empty array throws `ValueError` (`max(0, min(...))` returned 0) |
| D | Speed comparison over 5 million iterations | Per call, with the loop overhead subtracted: without JIT, `clamp()` 18.4 ns, an inline ternary 7.1 ns, a helper function 32–34 ns. With JIT, 9.8 ns, 1.0 ns, and 7–8 ns. Speed isn't the deciding factor |
| E | Re-ran A and C on PHP 8.5.9 with the polyfill | Without the polyfill, `function_exists("clamp")` is false. With it, the output matches 8.6's byte for byte, apart from the header line (`logs/e-diff.log`) |
| F | Moving to 8.6 with your own `clamp()` in place | An unconditional global definition gives `Fatal error: Cannot redeclare function clamp()`. One wrapped in `function_exists()` silently switches to the native function, and the result of `clamp(5, 10, 1)` changes from `10` to a `ValueError`. Namespaced functions are unaffected |

A–C, E, and F were run three times, and all the logs matched. D shifts by a few ns on every run, but the trends don't change (`logs/` holds the last run).

### History

| Date | Event |
|---|---|
| 2021-06-23 | Kim Hallberg (@thinkverse) published [RFC: clamp](https://wiki.php.net/rfc/clamp) and [php/php-src#7191](https://github.com/php/php-src/pull/7191). The signature was limited to `int\|float` |
| 2021-07 | Missed the voting deadline for 8.1; the target moved to 8.2 |
| 2023-06-12 | Withdrawn by the author, citing a lack of resources, without ever reaching a vote. The PR was closed unmerged |
| 2025-08-09 | @kylekatarnls took it over in [php/php-src#19434](https://github.com/php/php-src/pull/19434) and published [RFC: clamp v2](https://wiki.php.net/rfc/clamp_v2) (dated 2025-08-21) |
| 2025-11-18 | Voting closed. Accepted with 23 for, 3 against, and 6 abstaining |
| 2025-12-18 | Merged (commit `06ea0e5`). The NEWS entry credits kylekatarnls and thinkverse jointly |
