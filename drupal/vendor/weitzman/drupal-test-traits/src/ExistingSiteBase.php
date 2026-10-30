<?php

namespace weitzman\DrupalTestTraits;

use Drupal\Tests\RandomGeneratorTrait;
use Drupal\Tests\UiHelperTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\Exception\PhpWatchdogException;

/**
 * You may use this class in any of these ways:
 * - Copy its code into your own base class.
 * - Have your base class extend this class.
 * - Your tests may extend this class directly.
 */
abstract class ExistingSiteBase extends TestCase implements LoggerInterface
{
    use DrupalTrait;
    use BrowserKitTrait;
    use EntityCrawlerTrait;
    use AuthTrait {
        AuthTrait::drupalLogout insteadof UiHelperTrait;
    }
    use LoggerTrait;
    use NodeCreationTrait;
    use UserCreationTrait;
    use TaxonomyCreationTrait;
    use UiHelperTrait;

    // The entity creation traits need this.
    use RandomGeneratorTrait;

    /**
     * The database prefix of this test run.
     */
    protected string $databasePrefix;

    /**
     * Fail the test if its execution generates new PHP warnings/errors.
     *
     * @var bool
     */
    protected $failOnPhpWatchdogMessages = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinkSession();
        $this->setupDrupal();

        if ($this->failOnPhpWatchdogMessages && \Drupal::database()->schema()->tableExists('watchdog')) {
            // Remove all PHP messages from watchdog before starting the test.
            \Drupal::database()
                ->delete('watchdog')
                ->condition('type', 'PHP', '=')
                ->execute();
        }
    }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
    protected function tearDown(): void
    {
        $messages = [];
        if ($this->failOnPhpWatchdogMessages && \Drupal::database()->schema()->tableExists('watchdog')) {
            $messages = \Drupal::database()
                ->select('watchdog', 'w')
                ->fields('w')
                ->condition('w.type', 'PHP', '=')
                ->execute()
                ->fetchAll();
            if (!empty($messages)) {
                foreach ($messages as $error) {
                    // Perform replacements so the error message is easier to
                    // read in the log.
                    // @codingStandardsIgnoreLine
                    $error->variables = unserialize($error->variables);
                    $error->message = str_replace(array_keys($error->variables), $error->variables, $error->message);
                    unset($error->variables);
                    print_r($error);
                }
            }
        }

        parent::tearDown();
        $this->tearDownDrupal();
        $this->tearDownMinkSession();

        if (!empty($messages)) {
            throw new PhpWatchdogException('PHP errors or warnings are introduced when running this test.');
        }
    }

    /**
     * Override \Drupal\Tests\UiHelperTrait::prepareRequest since it generates
     * an error, and does nothing useful for DTT. @see https://www.drupal.org/node/2246725.
     */
    protected function prepareRequest()
    {
    }
}
