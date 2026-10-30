## Introduction

Traits for testing Drupal sites that have user content (versus unpopulated sites).

[Behat](http://behat.org) is great for facilitating conversations between business managers and developers. Those are useful conversations, but many organizations simply can't or won't converse via Gherkin. When you are on the hook for product quality and not conversations, this is a testing approach for you.

- Blog: [Introducing Drupal Test Traits](https://medium.com/massgovdigital/introducing-drupal-test-traits-9fe09e84384c)
- Blog: [Introducing Drupal Test Traits: Drupal extension for testing existing sites](https://www.previousnext.com.au/blog/introducing-drupal-testing-traits-drupal-extension-testing-existing-sites)
- Video: [Drupalcon presentation - Introducing Drupal Test Traits](https://www.tag1consulting.com/blog/introducing-drupal-test-traits).
- Video: [The road to zero friction testing with Drupal Test Traits](https://www.previousnext.com.au/blog/road-zero-friction-testing-drupal-test-traits)

## Installation

- Install via Composer: `composer require weitzman/drupal-test-traits --dev`

## Authoring tests

Pick a test type:
- **ExistingSite**. See [ExampleTest.php](./tests/ExampleTest.php). Start here. These tests can be small unit tests up to larger functional tests (via [BrowserKit](https://symfony.com/doc/current/components/browser_kit.html)). Tests of this type should be placed in `tests/src/ExistingSite`.
- **ExistingSiteSelenium2DriverTest**. See [ExampleSelenium2DriverTest.php](tests/ExampleSelenium2DriverTest.php). These tests make use of any browser which can run in [WebDriver] mode (Chrome, Firefox or Edge) via [Selenium](https://www.selenium.dev) or browser specific drivers like [chromedriver](https://chromedriver.chromium.org). They are suited to testing Ajax and similar client side interactions. This browser setup can also be used to run Drupal 8 core JS testing using [nightwatch](https://www.drupal.org/node/2968570). These tests run slower than ExistingSite. To use this test type you need to `composer require 'behat/mink-selenium2-driver' --dev`. Tests of this type should be placed in `tests/src/ExistingSiteJavascript`.
- **ExistingSitePerformance**. See [ExamplePerformanceTest.php](./tests/ExamplePerformanceTest.php). These are ExistingSiteSelenium2DriverTest tests, whose goal is to collect performance metrics and assert number of database queries, TTFB, LCP, etc. See the [Gander docs](https://www.drupal.org/docs/develop/automated-testing/performance-tests).

Extend the base class that corresponds to your pick above: [ExistingSiteBase.php](src/ExistingSiteBase.php), [ExistingSiteSelenium2DriverTestBase.php](src/ExistingSiteSelenium2DriverTestBase.php), [ExistingSitePerformanceBase.php](src/ExistingSitePerformanceBase.php). You may extend it directly from your test class or create a base class for your project that extends one of these.

Scaffold your tests quickly via our built-in [Drush generators](https://www.drush.org/latest/generators/): `drush generate test:existing` or `drush generate test:existing-js`.

Your tests run against a site that already has content, so never assert on a total count, or on the absence of something. Give the content you create a randomized name (`$this->randomMachineName()`) and look it up by that name, so your assertions are about your own content and not about whatever else the site holds.

## Running tests

1. Create or edit `phpunit.xml` to include new testsuites for `existing-site` and `existing-site-javascript` ([example phpunit.xml](docs/phpunit.xml)).
2. Specify the URL to your existing site with `DTT_BASE_URL=http://example.com`. For ExistingSiteSelenium2DriverTest tests, also specify `DTT_MINK_DRIVER_ARGS=["firefox", null, "http://selenium:4444/wd/hub"]`. You can also change timeouts like this: `DTT_API_OPTIONS={"socketTimeout": 360, "domWaitTimeout": 3600000}` (note the JSON string). You can specify these environment variables one of three ways:
    - Add them to your `phpunit.xml` ([example phpunit.xml](docs/phpunit.xml)).
    - Add them to your `.env` (supported by [drupal-project](https://github.com/drupal-composer/drupal-project/blob/8.x/.env.example) and [Docker](https://docs.docker.com/compose/env-file/)).
    - Specify them at runtime: `DTT_BASE_URL=http://127.0.0.1:8888; DTT_API_URL=http://localhost:9222 vendor/bin/phpunit ...`
3. Run `phpunit` with the `--bootstrap` option: `vendor/bin/phpunit --bootstrap=vendor/weitzman/drupal-test-traits/src/bootstrap-fast.php ...`. This bootstrap can also be referenced in your `phpunit.xml` ([example phpunit.xml](docs/phpunit.xml)). Depending on your setup, you may wish to run `phpunit` as the web server user: `su -s /bin/bash www-data -c "vendor/bin/phpunit ..."`. If you get 'Class not found' errors, please see comments at top of [bootstrap-fast.php](src/bootstrap-fast.php)

You should limit these test runs to your custom functionality only; testing Core and Contrib modules is already the purview of Drupal's Gitlab CI'. To do so, tell `phpunit` to only look for tests in a certain path, or use `@group` annotations to limit the scope. The following examples depend on a properly configured `phpunit.xml` ([example phpunit.xml](docs/phpunit.xml)):

```
vendor/bin/phpunit web/modules/custom
vendor/bin/phpunit --group CustomTestGroup
```

## Debugging tests

- HTML requests can be logged. To do so, set the environment variable `BROWSERTEST_OUTPUT_DIRECTORY=/tmp` and _either_ of 
    - Drupal 10-: Add `--printer '\\Drupal\\Tests\\Listeners\\HtmlOutputPrinter'` to the `phpunit` call.
    - Drupal 11+: [Add a PHPUnit extension](https://www.drupal.org/node/3453468) 
- To disable deprecation notices, set the environment variable `SYMFONY_DEPRECATIONS_HELPER=disabled`. Alternatively, you can specify these in your `phpunit.xml` ([example phpunit.xml](docs/phpunit.xml)).
- To write the current HTML of the page to a file, use `$this->capturePageContent()`. If using HtmlOutputPrinter this will be saved to the browser_output directory. Alternatively you can specify `DTT_HTML_OUTPUT_DIRECTORY=/path/to/output_directory` which is required when using a different printer, such as Teamcity, which is enforced by PHPStorm.
- To take a screenshot of the current page under ExistingSiteSelenium2DriverTest, use `\weitzman\DrupalTestTraits\ScreenShotTrait::captureScreenshot`. Be careful when using this to debug tests that are "randomly" failing. Most likely, these tests are failing due to missing [`waitForElementVisible`](https://api.drupal.org/api/drupal/core%21tests%21Drupal%21FunctionalJavascriptTests%21JSWebAssert.php/function/JSWebAssert%3A%3AwaitForElementVisible/9.3.x) checks, as the act of taking a screenshot gives the browser additional time to finish rendering the page.
- By default, tests that throw PHP warnings/errors to the Drupal watchdog will fail (requires dblog module). If you want
 to disable this, set `$failOnPhpWatchdogMessages` to `FALSE` in your test or custom base class.

## Available traits

- **DrupalTrait**
  Bootstraps Drupal so that its APIs are available. Also offers an entity cleanup API so databases are kept relatively free of testing content.

- **BrowserKitTrait**
  Makes BrowserKitTrait available for browser control, and offers a few helper methods.

- **Selenium2DriverTrait**
  Makes [Selenium2Driver](https://github.com/minkphp/MinkSelenium2Driver) available for browser control with Selenium. Suitable for functional JS testing.

- **NodeCreationTrait**
  Create nodes that are deleted at the end of the test method.

- **TaxonomyCreationTrait**
  Create terms and vocabularies that are deleted at the end of the test method.

- **UserCreationTrait**
  Create users and roles that are deleted at the end of the test method.

- **ConfigTrait**
  Set Drupal config that persists just for the duration of a test. An example usage may be found in \weitzman\DrupalTestTraits\Mail\MailCollectionTrait::startMailCollection.

- **EntityCrawlerTrait**
  Enables rendering of entities (without http) and then offers output assertions powered by [Symfony Crawler](https://symfony.com/doc/6.4/components/dom_crawler.html). [See example test](tests/EntityCrawlerTraitTest.php).

- **MailCollectionTrait**
  Enables the collection of emails during tests. Assertions can be made against contents of these as core's `AssertMailTrait` is included.

- **ScreenShotTrait**
  Allows for the capture of screenshots when the Selenium2 driver. The destination directory can be set with `DTT_SCREENSHOT_REPORT_DIRECTORY`, which can also be set in `phpunit.xml` ([example phpunit.xml](docs/phpunit.xml)). Defaults to `/sites/simpletest/browser_output`.

- **AuthTrait**
  Override drupalLogout() so that it is compatible with sites bypass the Drupal login form (e.g. OpenID Connect and similar). Replaces https://gitlab.com/weitzman/logintrait.

- **PerformanceTrait**
  Use [Drupal's Gander performance instrumentation framework](https://www.drupal.org/docs/develop/automated-testing/performance-tests).

- **EntityLoadTrait**
  Convenience methods for loading entities created in tests. You can find example usages in the tests. 

## Contributed Packages

Submit a [merge request](https://docs.gitlab.com/ee/user/project/merge_requests/) to get your trait added to this list.

Use `type="drupal-dtt"` in your package's `composer.json` in order to appear in [this list](https://packagist.org/?type=drupal-dtt).

- [LoginTrait](https://gitlab.com/weitzman/logintrait.git). Provides login/logout via user reset URL instead of forms. Useful when TFA/SAML are enabled. This package is not needed since Drupal 10.3+ avoids forms by default.
- [QueueRunnerTrait](https://github.com/drupaltest/queue-runner-trait/). Provides methods to clear and run queues during tests.
- [Drupal Test Utils](https://github.com/previousnext/drupal-test-utils). A trait to add Dynamic Page Cache cacheability testing.

## Contributing

Contributions to this project are welcome! Please file issues and merge requests.

- All merge requests are automatically tested via [GitLab CI](https://gitlab.com/weitzman/drupal-test-traits/pipelines).
- Discuss DTT at the [#testing channel on Drupal Slack](https://drupal.slack.com/messages/C223PR743).
- DDEV
  - See `.ddev` directory for a handy development environment. [DDEV Docs](ddev.readthedocs.io/). We also include [ddev expand-composer-json](https://git.drupalcode.org/project/dtt/-/blob/2.x/.ddev/commands/web/expand-composer-json?ref_type=heads) and [ddev poser](https://git.drupalcode.org/project/dtt/-/blob/2.x/.ddev/commands/web/poser?ref_type=heads) commands which temporarily adds Drupal to composer.json and does a `composer install`. 
  - You may watch browser tests (e.g. ExistingSiteSelenium2DriverTestBase) at https://dtt.ddev.site:7900.

## Colophon

- **Author**: [Moshe Weitzman](http://weitzman.github.io).
- **Maintainers**: [Moshe Weitzman](http://weitzman.github.io), [Jibran Ijaz (jibran)](https://www.drupal.org/u/jibran), [Adam Bramley (acbramley)](https://www.drupal.org/u/acbramley), [Michael Strelan (mstrelan)](https://www.drupal.org/u/mstrelan), and the community.
- **License**: [MIT license][mit]

[mit]: ./LICENSE.md
[license-badge]: https://img.shields.io/badge/License-MIT-blue.svg
[project-stage-badge]: http://img.shields.io/badge/Project%20Stage-Development-yellowgreen.svg
[project-stage-page]: http://bl.ocks.org/potherca/raw/a2ae67caa3863a299ba0/
