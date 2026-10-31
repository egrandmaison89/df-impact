<?php

namespace weitzman\DrupalTestTraits\Exception;

/**
 * Exception thrown if a PHP warning/error is thrown during test execution.
 *
 * The exception code should be the log severity level defined in
 * \Drupal\Core\Logger\RfcLogLevel. The exception message should be the logged
 * message.
 *
 * @see \weitzman\DrupalTestTraits\ExistingSiteBase::tearDown()
 */
class PhpWatchdogException extends \Exception
{
}
