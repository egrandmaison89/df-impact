<?php

namespace weitzman\DrupalTestTraits\Mail;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\mailsystem\MailsystemManager;
use weitzman\DrupalTestTraits\ConfigTrait;

/**
 * Trait for enabling and disabling mail collection during tests.
 *
 * Usage:
 *   From ::setUp call $this->startMailCollection()
 *   In ::tearDown call $this->restoreMailSettings()
 *
 * @property \Symfony\Component\DependencyInjection\ContainerInterface $container
 */
trait MailCollectionTrait
{
    use AssertMailTrait;
    use ConfigTrait;

    /**
     * Capture emails sent during tests.
     */
    protected function startMailCollection()
    {
        // Start from an empty mailbox. Mail from an earlier test may still be
        // in state, for instance when that test did not reach its tearDown.
        $this->container->get('state')->set('system.test_mail_collector', []);

        $this->setConfigValues([
            'system.mail' => [
                'interface' => [
                    'default' => 'test_mail_collector',
                ],
            ],
        ]);

        // Also change mailsystem.
        $this->startMailSystemCollection();
    }

    /**
     * Stop mail collection/restore settings.
     */
    protected function restoreMailSettings()
    {
        // Restore original configurations.
        $this->restoreConfigValues();

        // Empty out email collection.
        $this->container->get('state')->set('system.test_mail_collector', []);
    }

    /**
     * Capture mailsystem emails.
     */
    protected function startMailSystemCollection()
    {
        if ($this->container->get('module_handler')->moduleExists('mailsystem')) {
            $config = $this->container->get('config.factory')->getEditable(
                'mailsystem.settings'
            );
            $data = $config->getRawData();
            // Convert all 'senders' to the test collector.
            $data = $this->findMailSystemSenders($data);
            $this->setConfigValues([
                'mailsystem.settings' => $data,
            ]);
        }
    }

    /**
     * Find and replace all the mail system sender plugins with the test plugin.
     *
     * This method calls itself recursively.
     */
    protected function findMailSystemSenders(array $data)
    {
        foreach ($data as $key => $values) {
            if (is_array($values)) {
                if (isset($values[MailsystemManager::MAILSYSTEM_TYPE_SENDING])) {
                    $data[$key][MailsystemManager::MAILSYSTEM_TYPE_SENDING] = 'test_mail_collector';
                } else {
                    $data[$key] = $this->findMailSystemSenders($values);
                }
            }
        }

        return $data;
    }
}
