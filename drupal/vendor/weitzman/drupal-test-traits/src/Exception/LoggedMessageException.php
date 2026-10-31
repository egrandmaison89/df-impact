<?php

namespace weitzman\DrupalTestTraits\Exception;

/**
 * Exception thrown if a message is logged by Drupal during test execution.
 *
 * The exception code should be the log severity level defined in
 * \Drupal\Core\Logger\RfcLogLevel. The exception message should be the logged
 * message.
 *
 * @see \weitzman\DrupalTestTraits\LoggerTrait
 */
class LoggedMessageException extends \Exception
{
}
