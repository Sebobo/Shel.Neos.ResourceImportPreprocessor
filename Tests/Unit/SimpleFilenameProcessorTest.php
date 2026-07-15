<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shel\Neos\ResourceImportPreprocessor\Processor\SimpleFilenameProcessor;

class SimpleFilenameProcessorTest extends TestCase
{
    private SimpleFilenameProcessor $subject;

    protected function setUp(): void
    {
        $this->subject = new SimpleFilenameProcessor();
    }

    #[Test]
    public function replacesSpacesWithUnderscores(): void
    {
        self::assertSame('my_document', $this->subject->process('my document'));
    }

    #[Test]
    public function returnsFilenameUnchangedWhenNoSpaces(): void
    {
        self::assertSame('document.pdf', $this->subject->process('document.pdf'));
    }

    #[Test]
    public function handlesMultipleSpaces(): void
    {
        self::assertSame('a_b_c', $this->subject->process('a b c'));
    }

    #[Test]
    public function handlesEmptyString(): void
    {
        self::assertSame('', $this->subject->process(''));
    }
}
