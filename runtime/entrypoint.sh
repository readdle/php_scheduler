#!/usr/bin/env sh

cd "${TEST_DIR}"

if [ ! -f "composer.phar" ]; then
    curl -sS https://getcomposer.org/installer | php
else
    php composer.phar self-update
fi

php composer.phar install
php test.php
