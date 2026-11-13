<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for ExistingSite tests (df_editor).
 */

if (!class_exists(\DrupalFinder\DrupalFinderComposerRuntime::class, FALSE)) {
  require __DIR__ . '/DrupalFinderShim.php';
}

use weitzman\DrupalTestTraits\AddPsr4;

$web_root = dirname(__DIR__, 4);
$vendor = dirname(__DIR__, 5) . '/vendor';
$class_loader = require $vendor . '/autoload.php';
$class_loader->addPsr4('Drupal\\Tests\\', "$web_root/core/tests/Drupal/Tests/");
$class_loader->addPsr4('Drupal\\TestTools\\', "$web_root/core/tests/Drupal/TestTools/");
require_once "$web_root/core/tests/bootstrap.php";

list($finder, $class_loader) = AddPsr4::add();
$root = $finder->getDrupalRoot();

$class_loader->addPsr4('Drupal\\Tests\\df_editor\\', __DIR__ . '/src');

foreach (['node', 'taxonomy', 'user', 'media'] as $module) {
  $class_loader->addPsr4('Drupal\\Tests\\' . $module . '\\', "$root/core/modules/$module/tests/src");
}
$class_loader->addPsr4('Drupal\\Tests\\', "$root/core/tests/Drupal/Tests/");
$class_loader->addPsr4('Drupal\\TestTools\\', "$root/core/tests/Drupal/TestTools/");
