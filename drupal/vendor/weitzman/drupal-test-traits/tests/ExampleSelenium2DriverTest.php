<?php

// Use your module's testing namespace such as the one below.
namespace Drupal\Tests\moduleName\ExampleSelenium2DriverTest;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\EntityLoadTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;

/**
 * A browser test suitable for testing Ajax and client-side interactions.
 */
class ExampleSelenium2DriverTest extends ExistingSiteSelenium2DriverTestBase
{
    use EntityLoadTrait;

    public function testContentCreation()
    {
      // Create a taxonomy term. Will be automatically cleaned up at the end of the test.
        $web_assert = $this->assertSession();
        $vocab = Vocabulary::load('tags');
      // Names are randomized so that the assertions below identify the content
      // this test created, and not similarly named content that the site
      // already had.
        $term_name = $this->randomMachineName();
        $this->createTerm($vocab, ['name' => $term_name]);
        $this->createTerm($vocab, ['name' => $this->randomMachineName()]);
        $admin = User::load(1);
        $admin->passRaw = 'password';
        $this->drupalLogin($admin);

      // @codingStandardsIgnoreStart
      // These lines are left here as examples of how to debug requests.
      // \weitzman\DrupalTestTraits\ScreenShotTrait::captureScreenshot();
      // $this->capturePageContent();
      // @codingStandardsIgnoreStop

      // Test autocomplete on article creation.
      $title = $this->randomMachineName();
      $this->drupalGet('/node/add/article');
      $page = $this->getCurrentPage();
      $page->fillField('title[0][value]', $title);
      $tags = $page->findField('field_tags[target_id]');
      $tags->setValue(substr($term_name, 0, -1));
      $tags->keyDown(substr($term_name, -1));
      $result = $web_assert->waitForElementVisible('css', '.ui-autocomplete li');
      $this->assertNotNull($result);
      // Click the autocomplete option
      $result->click();
      // Verify that correct the input is selected.
      $this->assertStringContainsString($term_name, $tags->getValue());
      // attachFileToField() will upload the file to the Selenium instance
      // before attaching it to the form. This makes it the preferred approach.
      $page->attachFileToField('files[field_image_0]', __DIR__ . '/fixtures/drupal.png');
      $web_assert->waitForElementVisible('css', '.form-managed-file__image-preview');
      $page->fillField('field_image[0][alt]', 'Drupal logo');
      $submit_button = $page->findButton('Save');
      $submit_button->press();
      $this->assertNotNull($web_assert->waitForElementVisible('css', '.messages--status'));
      // Get the node we just created.
      $node = $this->loadEntityByProperty('node', ['title' => $title]);
      $this->assertNotNull($node);
      $this->markEntityForCleanup($node);
      // Verify we are viewing the node, not editing it.
      $url = $this->getUrl();
      $path = str_replace($this->baseUrl, '', $url);
      $this->assertEquals('/node/' . $node->id(), $path);
      // Verify the text on the page.
      $web_assert->pageTextContains($title);
      $web_assert->pageTextContains($term_name);
      $web_assert->elementContains('css', '.field--name-field-image', 'alt="Drupal logo"');
      $web_assert->responseMatches('#drupal(_[0-9]+)?\.png#');
  }
}
