#!/usr/bin/env bash
# Install Composer (if missing) and PHPUnit/DTT dev deps for df_editor tests.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT/drupal"

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi

composer config audit.block-insecure false
composer require --dev \
  phpunit/phpunit:^11.5 \
  behat/mink behat/mink-browserkit-driver symfony/browser-kit symfony/dom-crawler symfony/css-selector \
  phpspec/prophecy-phpunit mikey179/vfsstream \
  weitzman/drupal-test-traits \
  --no-interaction

echo "Run ExistingSite tests:"
echo "  cd drupal/web/modules/custom/df_editor && ../../../../vendor/bin/phpunit -c phpunit.xml.dist"
