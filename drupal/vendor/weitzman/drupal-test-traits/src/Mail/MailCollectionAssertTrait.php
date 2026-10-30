<?php

namespace weitzman\DrupalTestTraits\Mail;

/**
 * A trait for accessing advanced assertions for mail.
 */
trait MailCollectionAssertTrait
{
  /**
   * Assert conditions related to the mail sent during a test.
   *
   * @return \weitzman\DrupalTestTraits\Mail\TestMailCollection
   *   A collection of emails with assert methods.
   */
    public function assertMailCollection()
    {
        $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
        return new TestMailCollection($captured_emails);
    }
}
