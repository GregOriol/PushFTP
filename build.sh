#!/bin/bash
set -e

rm -rf vendor
php composer.phar install --prefer-install=dist --no-ansi --no-dev --no-interaction --no-plugins --no-progress --no-scripts --optimize-autoloader

php -d phar.readonly=0 build.php

rm -rf vendor
php composer.phar install --prefer-install=dist
