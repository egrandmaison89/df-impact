<?php

namespace weitzman\DrupalTestTraits\Tests\Mail;

use weitzman\DrupalTestTraits\ExistingSiteBase;
use weitzman\DrupalTestTraits\Mail\MailCollectionAssertTrait;
use weitzman\DrupalTestTraits\Mail\MailCollectionTrait;

/**
 * @coversDefaultClass \weitzman\DrupalTestTraits\Mail\MailCollectionTrait
 */
class MailCollectorTraitTest extends ExistingSiteBase
{
    use MailCollectionTrait;
    use MailCollectionAssertTrait;

    private string $siteName;

    /**
     * {@inheritdoc}
     *
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->siteName = $this->randomMachineName();
        $this->siteName = $this->randomMachineName();
        $this->setConfigValues([
           'user.settings' => [
               'notify' => [
                   'status_blocked' => true,
               ],
           ],
           'system.site' => [
               'name' => $this->siteName,
           ]
        ]);
        $this->startMailCollection();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->restoreMailSettings();
        $this->restoreConfigValues();
        parent::tearDown();
    }

    public function testMailCollection()
    {
        // Cause an email to be sent.
        $to = $this->randomMachineName();
        $account = $this->createUser([], $this->randomMachineName(), false, ['mail' =>  $to]);
        $result = _user_mail_notify('status_blocked', $account);

        $mails = $this->getMails();
        $this->assertCount(1, $mails);
        $this->assertMail('module', 'user');

        $this->assertMailCollection()
            ->seekToModule('user')
            ->seekToRecipient($to)
            ->countEquals(1)
            ->subjectEquals("Account details for {$account->label()} at {$this->siteName} (blocked)");
    }
}
