#!/usr/bin/env bash
# Vercel runs buildCommand before vercel-php installs Composer packages, and the
# build image has no PHP. app.css imports Flux's stylesheet and scans its Blade
# stubs from vendor/, so fetch a static PHP + Composer and install vendor first.
set -euo pipefail

PHP_VERSION=8.4.23

if [ ! -f vendor/livewire/flux/dist/flux.css ]; then
    tools="$(mktemp -d)"
    curl -fsSL "https://dl.static-php.dev/static-php-cli/common/php-${PHP_VERSION}-cli-linux-x86_64.tar.gz" | tar -xz -C "$tools"
    curl -fsSL https://getcomposer.org/download/latest-stable/composer.phar -o "$tools/composer.phar"
    "$tools/php" "$tools/composer.phar" install \
        --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist
fi

npm run build
