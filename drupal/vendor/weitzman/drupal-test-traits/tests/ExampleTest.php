<?php

// Use your module's testing namespace such as the one below.
namespace Drupal\Tests\moduleName\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\Entity\EntityLoadTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * A model test case using traits from Drupal Test Traits.
 */
class ExampleTest extends ExistingSiteBase
{
    use EntityLoadTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Cause tests to fail if an error is sent to Drupal logs.
        $this->failOnLoggedErrors();
    }

    /**
     * An example test method; note that Drupal API's and Mink are available.
     *
     * @throws \Drupal\Core\Entity\EntityStorageException
     * @throws \Drupal\Core\Entity\EntityMalformedException
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function testLlama()
    {
        // Creates a user. Will be automatically cleaned up at the end of the test.
        $author = $this->createUser([], null, true);

        // Create a taxonomy term. Will be automatically cleaned up at the end of the test.
        $vocab = Vocabulary::load('tags');
        $term = $this->createTerm($vocab);

        // Create a "Llama" article. Will be automatically cleaned up at end of test.
        $this->createNode([
            'title' => 'Llama',
            'type' => 'article',
            'field_tags' => [
                'target_id' => $term->id(),
            ],
            'uid' => $author->id(),
        ]);

        // Create an "Alpaca" article. Will be automatically cleaned up at end of test.
        $this->createNode([
            'title' => 'Alpaca',
            'type' => 'article',
            'field_tags' => [
              'target_id' => $term->id(),
            ],
            'uid' => $author->id(),
        ]);

        // Check we have 2 nodes.
        $nodes = $this->loadEntityByProperty('node', ['field_tags' => $term->id()], false);
        $this->assertEquals(2, count($nodes));

        // Load the LLama article, publish it and check the author.
        $node = $this->loadEntityByProperty('node', ['title' => 'Llama']);
        $this->assertEquals('Llama', $node->getTitle());
        $node->setPublished()->save();
        $this->assertEquals($author->id(), $node->getOwnerId());

        // We can browse pages.
        $this->drupalGet($node->toUrl());
        $this->assertSession()->statusCodeEquals(200);

        // We can login and browse admin pages.
        $this->drupalLogin($author);
        $this->drupalGet($node->toUrl('edit-form'));
    }
}
