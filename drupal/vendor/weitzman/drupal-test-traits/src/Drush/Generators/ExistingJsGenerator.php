<?php

namespace weitzman\DrupalTestTraits\Drush\Generators;

use DrupalCodeGenerator\Asset\AssetCollection as Assets;
use DrupalCodeGenerator\Attribute\Generator;
use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\GeneratorType;
use DrupalCodeGenerator\Validator\RequiredClassName;

#[Generator(
    name: 'test:existing-js',
    description: 'Generates an ExistingSiteSelenium2Driver test',
    aliases: ['exjs-test'],
    templatePath: __DIR__,
    type: GeneratorType::MODULE_COMPONENT,
)]
class ExistingJsGenerator extends BaseGenerator
{
    protected function generate(array &$vars, Assets $assets): void
    {
        $ir = $this->createInterviewer($vars);
        $vars['machine_name'] = $ir->askMachineName();
        $vars['name'] = $ir->askName();
        $vars['class'] = $ir->ask('Class', 'ExampleTest', new RequiredClassName());
        $assets->addFile('tests/src/ExistingSiteJavascript/{class}.php', 'existing-js.php.twig');
    }
}
