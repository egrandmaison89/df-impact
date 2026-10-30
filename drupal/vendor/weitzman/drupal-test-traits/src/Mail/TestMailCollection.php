<?php

namespace weitzman\DrupalTestTraits\Mail;

use PHPUnit\Framework\Assert;

/**
 * A test mail collection class.
 */
class TestMailCollection
{
    /**
     * The collected mail.
     *
     * @var array
     */
    protected $mail;

    /**
     * TestMailCollection constructor.
     *
     * @param array $mail
     *   The mail to add to the collection.
     */
    public function __construct($mail)
    {
        $this->mail = $mail;
    }

    /**
     * Seek to a specific item in the collection.
     *
     * @param int $index
     *   The index to seek to.
     *
     * @return static
     *   A collection with just the given email in it.
     */
    public function seekToIndex(int $index)
    {
        Assert::assertArrayHasKey($index, $this->mail);
        return new static([$this->mail[$index]]);
    }

    /**
     * Seek to the last email sent.
     *
     * @return static
     *   A collection with the last sent email in it.
     */
    public function seekToLast()
    {
        $this->countGreaterThan(0);
        return new static([end($this->mail)]);
    }

    /**
     * Seek to the first email sent.
     *
     * @return static
     *   A collection with the first sent email in it.
     */
    public function seekToFirst()
    {
        $this->countGreaterThan(0);
        return new static([$this->mail[0]]);
    }

    /**
     * Seek to a specific recipients in the collection.
     *
     * @param string $recipient
     *   The recipients to seek to.
     *
     * @return static
     *   A collection with just the given email in it.
     */
    public function seekToRecipient(string $recipient)
    {
        return new static(array_filter($this->mail, function ($mail) use ($recipient) {
            return $mail['to'] === $recipient;
        }));
    }

    /**
     * Seek to mail sent by a specific module.
     *
     * @param string $module
     *   The module to seek to.
     *
     * @return static
     *   A collection with just the given email in it.
     */
    public function seekToModule(string $module)
    {
        return new static(array_filter($this->mail, function ($mail) use ($module) {
            return $mail['module'] === $module;
        }));
    }

    /**
     * Assert the number of collected emails.
     *
     * @param int $expected
     *   The expected number of emails sent.
     *
     * @return $this
     */
    public function countEquals(int $expected)
    {
        Assert::assertEquals($expected, count($this->mail));
        return $this;
    }

    /**
     * Assert the count of the collection is greater than a given count.
     *
     * @param int $expected
     *   The expected count.
     *
     * @return $this
     */
    public function countGreaterThan(int $expected)
    {
        Assert::assertGreaterThan($expected, count($this->mail));
        return $this;
    }

    /**
     * Assert all mail in the collection contains a body string.
     *
     * @param string $string
     *   The string to assert.
     *
     * @return $this
     */
    public function bodyContains(string $string)
    {
        $this->countGreaterThan(0);
        foreach ($this->mail as $mail) {
            Assert::assertTrue(strpos($mail['body'], $string) !== false, sprintf('Failed asserting string "%s" contained "%s".', $mail['body'], $string));
        }
        return $this;
    }

    /**
     * Assert all mail in the collection contains a body string.
     *
     * @param string $string
     *   The string to assert.
     *
     * @return $this
     */
    public function bodyMatchesRegexp(string $pattern)
    {
        $this->countGreaterThan(0);
        foreach ($this->mail as $mail) {
            Assert::assertMatchesRegularExpression($pattern, $mail['body'], sprintf('Failed asserting string "%s" matched the pattern "%s".', $mail['body'], $pattern));
        }
        return $this;
    }

    /**
     * Assert all mail in the collection is addressed to a recipient.
     *
     * @param string $recipient
     *   The recipient.
     *
     * @return $this
     */
    public function recipientEquals(string $recipient)
    {
        $this->countGreaterThan(0);
        foreach ($this->mail as $mail) {
            Assert::assertEquals($recipient, $mail['to']);
        }
        return $this;
    }

    /**
     * Assert all mail in the collection has the given subject.
     *
     * @param string $subject
     *   The subject to assert.
     *
     * @return $this
     */
    public function subjectEquals(string $subject)
    {
        $this->countGreaterThan(0);
        foreach ($this->mail as $mail) {
            Assert::assertEquals($subject, $mail['subject']);
        }
        return $this;
    }

    /**
     * Assert all mail in the collection matches a subject regexp.
     *
     * @param string $pattern
     *   The pattern to assert.
     *
     * @return $this
     */
    public function subjectMatchesRegexp(string $pattern)
    {
        $this->countGreaterThan(0);
        foreach ($this->mail as $mail) {
            Assert::assertMatchesRegularExpression($pattern, $mail['subject'], sprintf('Failed asserting string "%s" matched the pattern "%s".', $mail['subject'], $pattern));
        }
        return $this;
    }
}
