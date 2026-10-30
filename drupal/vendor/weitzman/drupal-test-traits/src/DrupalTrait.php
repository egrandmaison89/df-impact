<?php

namespace weitzman\DrupalTestTraits;

use Drupal\Component\Utility\Html;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Entity\EntityInterface;
use DrupalFinder\DrupalFinderComposerRuntime;
use Symfony\Component\HttpFoundation\Request;

trait DrupalTrait
{
    /**
     * A flag to track when we've restored the error handler.
     *
     * @var bool
     */
    protected static $restoredErrorHandler = false;

    /**
     * Entities to clean up.
     *
     * @var \Drupal\Core\Entity\EntityInterface[]
     */
    protected $cleanupEntities = [];

    /**
     * The container.
     *
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    protected $container;

    /**
     * The Drupal kernel.
     *
     * @var \Drupal\Core\DrupalKernel
     */
    protected $kernel;

    /**
     * Directory name for HTML output logging.
     *
     * @var string
     */
    protected $outputDirectory;

    /**
     * The site directory of this test run.
     *
     * @var string
     *
     * @see \Drupal\Core\Test\TestSetupTrait::$siteDirectory
     */
    protected $siteDirectory = 'dtt';

    /**
     * Controls the allow_dumping parameter on the DrupalKernel.
     *
     * Set to FALSE if tests use different container yamls than the regular web site.
     *
     * See https://www.drupal.org/project/dtt/issues/3436284
     */
    protected bool $allowDumping = true;

    /**
     * Bootstrap Drupal. Call this from your setUp() method.
     */
    public function setupDrupal()
    {
        // Bootstrap Drupal so we can use Drupal's built-in functions.
        $finder = new DrupalFinderComposerRuntime();
        $classLoader = include $finder->getVendorDir() . '/autoload.php';
        $parsed_url = parse_url($this->baseUrl);

        $host = $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
        $path = isset($parsed_url['path']) ? rtrim(rtrim($parsed_url['path']), '/') : '';
        $port = isset($parsed_url['port']) ? $parsed_url['port'] : 80;

        $server = [
            'HTTP_HOST' => $host,
            'SERVER_PORT' => $port,
            'REQUEST_URI' => $path . '/',
            'SCRIPT_FILENAME' => $path . '/index.php',
            'SCRIPT_NAME' => $path . '/index.php',
            'PHP_SELF' => $path . '/index.php',
        ];
        // If the passed URL schema is 'https' then setup the $_SERVER variables
        // properly so that testing will run under HTTPS.
        if ($parsed_url['scheme'] === 'https') {
            $server['HTTPS'] = 'on';
        }

        $request = Request::create($this->baseUrl . '/', 'GET', [], [], [], $server);
        $this->kernel = DrupalKernel::createFromRequest(
            $request,
            $classLoader,
            'prod',
            $this->allowDumping,
            $finder->getDrupalRoot()
        );

        // The DrupalKernel only initializes the environment once which is where
        // it sets the Drupal error handler. We can therefore only restore it
        // once.
        if (!static::$restoredErrorHandler) {
            restore_error_handler();
            restore_exception_handler();
            static::$restoredErrorHandler = true;
        }

        chdir(DRUPAL_ROOT);
        $this->kernel->boot();
        $this->kernel->preHandle($request);
        $this->container = $this->kernel->getContainer();

        // Set up the browser test output file.
        $this->initBrowserOutputFile();
    }

    /**
     * Delete marked entities. Call this from your case's tearDown() method.
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function tearDownDrupal()
    {

        // Since invalidated cache tags are statically cached during a single
        // bootstrap, that cache is reset so that any entities deleted below
        // properly have their cache tags invalidated.
        \Drupal::service('cache_tags.invalidator')->resetChecksums();

        foreach ($this->cleanupEntities as $entity) {
            $entity->delete();
        }
      // Avoid leaking memory in test cases (which are retained for a long time)
      // by removing references to all the things.
        $this->cleanupEntities = [];
        $this->kernel->shutdown();
        $this->kernel = null;
        $this->container = null;
        $this->loggedInUser = false;
    }

    /**
     * Mark an entity for deletion.
     *
     * Any entities you create when running against an installed site should be
     * flagged for deletion to ensure isolation between tests.
     *
     * @param \Drupal\Core\Entity\EntityInterface $entity
     *   Entity to delete.
     */
    protected function markEntityForCleanup(EntityInterface $entity)
    {
        $this->cleanupEntities[] = $entity;
    }

    /**
     * Get filename for writing browser output files for debugging.
     *
     * @param string $directory
     *
     * @return string
     *   An absolute file path, without an extension.
     */
    protected function getOutputFileName($directory = '')
    {
        if (empty($directory)) {
            $directory = $this->getOutputDirectory();
        }
        // Ensure directory exists.
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $current_url = Html::cleanCssIdentifier($this->getSession()->getCurrentUrl());
        return $directory . DIRECTORY_SEPARATOR . uniqid() . '_' . $current_url;
    }

    /**
     * Get directory for writing browser output files for debugging.
     *
     * If the DTT_HTML_OUTPUT_DIRECTORY environment variable is specified we will
     * use that. Otherwise when using \Drupal\Tests\Listeners\HtmlOutputPrinter
     * the htmlOutputDirectory property should already be set and we can use it.
     *
     * @return string
     *   An absolute directory path.
     */
    protected function getOutputDirectory()
    {
        if ($directory = getenv('DTT_HTML_OUTPUT_DIRECTORY')) {
            $this->outputDirectory = $directory;
        } elseif (isset($this->htmlOutputDirectory)) {
            $this->outputDirectory = $this->htmlOutputDirectory;
        }
        return $this->outputDirectory;
    }

    /**
     * Captures and saves page content.
     *
     * The filename will contain a unique ID, the URL where
     * the screenshot was taken and the given base filename.
     *
     * @param string $base_filename (optional)
     *   The base filename to use, defaults to 'page'.
     */
    protected function capturePageContent($base_filename = 'page')
    {
        $base_filename = Html::cleanCssIdentifier($base_filename);
        $filename = $this->getOutputFileName() . '_' . $base_filename . '.html';
        $contents = $this->getCurrentPageContent();
        file_put_contents($filename, $contents);
    }
}
