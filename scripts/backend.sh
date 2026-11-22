#!/usr/bin/env bash
set -euo pipefail

usage() {
    printf 'Usage: bash scripts/backend.sh [setup|serve|test|lint|prototype]\n'
    printf 'Defaults to serve. Requires PHP 8.3+ on PATH; setup also needs Composer 2.\n'
}

if (( $# > 1 )); then
    usage >&2
    exit 2
fi

action="${1:-serve}"
case "$action" in
    -h|--help) usage; exit 0 ;;
    setup|serve|test|lint|prototype) ;;
    *) usage >&2; exit 2 ;;
esac

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

if ! command -v php >/dev/null 2>&1; then
    printf 'PHP was not found. Install PHP 8.3+ with the required extensions and add it to PATH.\n' >&2
    exit 1
fi

cd -- "$project_root/backend"

case "$action" in
    setup)
        if [[ -f "$project_root/.tools/composer.phar" ]]; then
            php "$project_root/.tools/composer.phar" install --no-interaction --prefer-dist
        elif command -v composer >/dev/null 2>&1; then
            composer install --no-interaction --prefer-dist
        else
            printf 'Composer was not found. Install Composer 2 on PATH or place composer.phar in .tools/.\n' >&2
            exit 1
        fi

        if [[ ! -e .env ]]; then
            cp -- .env.example .env
        fi
        # Preserve existing configuration and never rotate an existing application key.
        if grep -Eq '^APP_KEY=[[:space:]]*$' .env; then
            php artisan key:generate
        fi
        ;;
    serve) exec php artisan serve --host=127.0.0.1 --port=8000 ;;
    test) exec php vendor/phpunit/phpunit/phpunit ;;
    lint) exec php vendor/laravel/pint/builds/pint --test ;;
    prototype) exec php -S 127.0.0.1:4173 -t "$project_root/prototype" ;;
esac
