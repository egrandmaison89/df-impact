<?php

namespace weitzman\DrupalTestTraits;

use DrupalFinder\DrupalFinderComposerRuntime;

class AddPsr4
{
    /**
     * @return array
     */
    public static function add()
    {
        $finder = new DrupalFinderComposerRuntime();
        $root = $finder->getDrupalRoot();
        $vendor = $finder->getVendorDir();

        // Require Drupal's autoloader.
        $class_loader = require "$vendor/autoload.php";

        // Register the Drupal core 'Test' namespaces that we use.
        $modules = ['node', 'taxonomy', 'user', 'media'];
        foreach ($modules as $module) {
            $class_loader->addPsr4('Drupal\Tests\\' . $module . '\\', "$root/core/modules/$module/tests/src");
        }
        $class_loader->addPsr4('Drupal\\', "$root/core/tests/Drupal");

        return [$finder, $class_loader];
    }
}
