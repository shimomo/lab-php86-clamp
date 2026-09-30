#!/bin/sh
# Runs the tests and records their output under logs/.
#   sh run.sh        # every test (A-F)
#   sh run.sh a c    # only tests A and C
# Tests A-D run on PHP 8.6 in the official Docker image (override it with PHP86_IMAGE).
# Tests E and F also use an older PHP on the host, 8.0 to 8.5 (override it with PHP85), and E
# installs symfony/polyfill-php86 with Composer, keeping Composer's home and cache inside this
# directory.
set -e
S="$(cd "$(dirname "$0")" && pwd)"
IMAGE="${PHP86_IMAGE:-php:8.6.0RC2-cli-alpine}"
PHP85="${PHP85:-php}"
mkdir -p "$S/logs"

# Both sides run without a php.ini, so they share PHP's built-in defaults.
php86() {
    docker run --rm -u "$(id -u):$(id -g)" -v "$S":/lab:ro -w /lab "$IMAGE" \
        php -n -d display_errors=1 -d error_reporting=-1 "$@"
}

php85() {
    (cd "$S" && "$PHP85" -n -d display_errors=1 -d error_reporting=-1 "$@")
}

# record <name> <command...>: saves stdout and stderr to logs/<name>.log and keeps going when the
# command fails, because a fatal error is a result here, not a reason to stop.
record() {
    name="$1"
    shift
    if "$@" > "$S/logs/$name.log" 2>&1; then
        echo "ok      logs/$name.log"
    else
        echo "exit $?  logs/$name.log"
    fi
}

# The test scripts need PHP 8.0, and clamp() must not be built in yet.
host_php_is_older() {
    "$PHP85" -n -r 'exit(PHP_VERSION_ID >= 80000 && PHP_VERSION_ID < 80600 ? 0 : 1);' 2> /dev/null
}

test_a() { record a-basics php86 tests/a-basics.php; }
test_b() { record b-usecases php86 tests/b-usecases.php; }
test_c() { record c-gotchas php86 tests/c-gotchas.php; }

test_d() {
    record d-bench php86 tests/d-bench.php
    record d-bench-jit php86 -d opcache.enable_cli=1 -d opcache.file_update_protection=0 \
        -d opcache.jit=tracing -d opcache.jit_buffer_size=64M tests/d-bench.php
}

without_polyfill() {
    php85 -r 'echo "PHP ", PHP_VERSION, ", function_exists(\"clamp\"): "; var_dump(function_exists("clamp"));'
}

# diff exits 1 when the files differ, which they always do in the header line; only 2 is a failure.
diff_against_native() {
    diff -u --label a-basics.log --label e-polyfill-basics.log \
        "$S/logs/a-basics.log" "$S/logs/e-polyfill-basics.log" || [ $? -eq 1 ]
    diff -u --label c-gotchas.log --label e-polyfill-gotchas.log \
        "$S/logs/c-gotchas.log" "$S/logs/e-polyfill-gotchas.log" || [ $? -eq 1 ]
}

test_e() {
    if ! host_php_is_older; then
        echo "skip    test E needs PHP 8.0-8.5 on the host (set PHP85=/path/to/php)"
        return 0
    fi
    (cd "$S/polyfill" && COMPOSER_HOME="$S/.composer-home" COMPOSER_CACHE_DIR="$S/.composer-cache" \
        composer install --no-interaction --no-progress --quiet)
    [ -f "$S/logs/a-basics.log" ] || test_a
    [ -f "$S/logs/c-gotchas.log" ] || test_c
    record e-no-polyfill without_polyfill
    record e-polyfill-basics php85 -d auto_prepend_file="$S/polyfill/vendor/autoload.php" tests/a-basics.php
    record e-polyfill-gotchas php85 -d auto_prepend_file="$S/polyfill/vendor/autoload.php" tests/c-gotchas.php
    record e-diff diff_against_native
}

own_clamp() {
    first=1
    for script in tests/f-unguarded.php tests/f-guarded.php tests/f-namespaced.php; do
        [ "$first" = 1 ] || echo
        first=0
        echo "### $script in the PHP 8.6 image"
        php86 "$script" || true
        if host_php_is_older; then
            echo
            echo "### $script on the host's older PHP"
            php85 "$script" || true
        fi
    done
}

test_f() { record f-own-clamp own_clamp; }

docker image inspect "$IMAGE" > /dev/null 2>&1 || docker pull "$IMAGE"

for t in ${*:-a b c d e f}; do
    case "$t" in
        a | b | c | d | e | f) "test_$t" ;;
        *) echo "unknown test: $t (expected a-f)" >&2; exit 1 ;;
    esac
done
