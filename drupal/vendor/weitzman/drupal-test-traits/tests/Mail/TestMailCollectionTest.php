<?php

namespace weitzman\DrupalTestTraits\Tests\Mail;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\ExpectationFailedException;
use weitzman\DrupalTestTraits\Mail\TestMailCollection;

/**
 * @coversDefaultClass \weitzman\DrupalTestTraits\Mail\TestMailCollection
 */
class TestMailCollectionTest extends UnitTestCase
{
    /**
     * A test mail collection.
     *
     * @var \weitzman\DrupalTestTraits\Mail\TestMailCollection
     */
    protected $collection;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->collection = new TestMailCollection([
            [
                'id' => 'moderation_alerts',
                'module' => 'moderation',
                'to' => 'baz@example.com',
                'from' => 'system@localhost',
                'subject' => 'Your content published',
                'body' => "Thanks for submitting content to the site.\n\nIt has been published.",
                'headers' => [],
                'params' => [],
                'language' => 'en',
                'send' => true,
            ],
            [
                'id' => 'content_notifications',
                'module' => 'notifications',
                'to' => 'biz@example.com',
                'from' => 'system@localhost',
                'subject' => 'New content created',
                'body' => "New content was created.\n\nVisit the site to see what's up.",
                'headers' => [],
                'params' => [],
                'language' => 'en',
                'send' => true,
            ],
            [
                'id' => 'content_notifications',
                'module' => 'notifications',
                'to' => 'fiz@example.com',
                'from' => 'system@localhost',
                'subject' => 'New content created',
                'body' => "New content was created.\n\nVisit the site to see what's up.",
                'headers' => [],
                'params' => [],
                'language' => 'en',
                'send' => true,
            ],
        ]);
    }

    /**
     * @covers ::recipientEquals
     */
    public function testRecipientEquals()
    {
        $this->collection->seekToIndex(0)->recipientEquals('baz@example.com');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(0)->recipientEquals('zip@example.com');
    }

    /**
     * @covers ::countEquals
     */
    public function testCountEquals()
    {
        $this->collection->countEquals(3);
        $this->expectException(ExpectationFailedException::class);
        $this->collection->countEquals(4);
    }

    /**
     * @covers ::seekToRecipient
     */
    public function testSeekToRecipient()
    {
        $this->collection->seekToRecipient('baz@example.com')->subjectEquals('Your content published');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToRecipient('wiz@example.com')->countGreaterThan(0);
    }

    /**
     * @covers ::subjectEquals
     */
    public function testSubjectEquals()
    {
        $this->collection->seekToIndex(0)->subjectEquals('Your content published');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(0)->subjectEquals('Not a real subject');
    }

    /**
     * @covers ::seekToIndex
     */
    public function testSeekToIndex()
    {
        $this->collection->seekToIndex(0)->subjectEquals('Your content published');
        $this->collection->seekToIndex(1)->subjectEquals('New content created');
        $this->collection->seekToIndex(2)->subjectEquals('New content created');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(12);
    }

    /**
     * @covers ::seekToFirst
     */
    public function testSeekToFirst()
    {
        $this->collection->seekToFirst()->recipientEquals('baz@example.com');
    }

    /**
     * @covers ::bodyContains
     */
    public function testBodyContains()
    {
        $this->collection->seekToIndex(0)->bodyContains('Thanks');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(0)->bodyContains('Boo');
    }

    /**
     * @covers ::subjectMatchesRegexp
     */
    public function testSubjectMatchesRegexp()
    {
        $this->collection->seekToIndex(0)->subjectMatchesRegexp('/Your(.*)published/');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(0)->subjectMatchesRegexp('/foo/');
    }

    /**
     * @covers ::seekToLast
     */
    public function testSeekToLast()
    {
        $this->collection->seekToLast()->recipientEquals('fiz@example.com');
    }

    /**
     * @covers ::seekToModule
     */
    public function testSeekToModule()
    {
        $this->collection->seekToModule('notifications')->countEquals(2);
    }

    /**
     * @covers ::countGreaterThan
     */
    public function testCountGreaterThan()
    {
        $this->collection->countGreaterThan(0);
        $this->collection->countGreaterThan(1);
        $this->collection->countGreaterThan(2);
        $this->expectException(ExpectationFailedException::class);
        $this->collection->countGreaterThan(3);
    }

    /**
     * @covers ::bodyMatchesRegexp
     */
    public function testBodyMatchesRegexp()
    {
        $this->collection->seekToIndex(0)->bodyMatchesRegexp('/Thanks/');
        $this->expectException(ExpectationFailedException::class);
        $this->collection->seekToIndex(0)->bodyMatchesRegexp('/Boo/');
    }
}
