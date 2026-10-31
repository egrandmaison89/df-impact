<?php

declare(strict_types=1);

namespace weitzman\DrupalTestTraits;

/**
 * Trait for setting config values in tests.
 *
 * Usage:
 *   From ::setUp call $this->setConfigValues()
 *   In ::tearDown call $this->restoreConfigValues()
 *
 * @property \Symfony\Component\DependencyInjection\ContainerInterface $container
 */
trait ConfigTrait
{
    /**
     * Original configuration values for restoration after the test run.
     *
     * @var array
     *   An array of configuration data, keyed by name.
     */
    protected array $originalConfiguration = [];

    /**
     * Set config values noting future values if required.
     *
     * @param array $config_values
     *   Array of array values keyed by config object key.
     * @param bool $mark
     *   TRUE to record value for resetting later.
     */
    protected function setConfigValues(array $config_values, bool $mark = true): void
    {
        $config = $this->container->get('config.factory');
        foreach ($config_values as $key => $values) {
            /** @var \Drupal\Core\Config\Config $entry */
            $entry = $config->getEditable($key);
            if ($mark && !isset($this->originalConfiguration[$key])) {
                $this->originalConfiguration[$key] = [];
            }
            foreach ($values as $value_key => $value) {
                if ($mark) {
                    $this->originalConfiguration[$key][$value_key] = $entry->get($value_key);
                }
                $entry->set($value_key, $value);
            }
            $entry->save();
        }
    }

    /**
     * Restore config values.
     */
    protected function restoreConfigValues(): void
    {
        $this->setConfigValues($this->originalConfiguration, false);
        $this->originalConfiguration = [];
    }
}
