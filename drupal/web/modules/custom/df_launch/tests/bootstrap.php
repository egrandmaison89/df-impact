<?php

declare(strict_types=1);

/**
 * @file
 * PHPUnit bootstrap for df_launch tests.
 */

$webRoot = dirname(__DIR__, 4);
$drupalRoot = dirname($webRoot);
$autoload = require $drupalRoot . '/vendor/autoload.php';
$autoload->addPsr4('Drupal\\df_launch\\', dirname(__DIR__) . '/src/');
$autoload->addPsr4('Drupal\\Tests\\df_launch\\', dirname(__DIR__) . '/tests/src/');
