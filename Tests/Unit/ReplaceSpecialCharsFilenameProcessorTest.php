<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shel\Neos\ResourceImportPreprocessor\Processor\ReplaceSpecialCharsFilenameProcessor;

class ReplaceSpecialCharsFilenameProcessorTest extends TestCase
{
    private ReplaceSpecialCharsFilenameProcessor $subject;

    protected function setUp(): void
    {
        $this->subject = (new ReplaceSpecialCharsFilenameProcessor())
            ->setOptions(
                ['pattern' => '/[^a-zA-Z0-9._-]/', 'replacement' => '_']
            );
    }

    /**
     * @test
     */
    public function replacesSpacesWithUnderscores(): void
    {
        self::assertSame('my_document', $this->subject->process('my document'));
    }

    /**
     * @test
     */
    public function returnsFilenameUnchangedWhenNoSpaces(): void
    {
        self::assertSame('document.pdf', $this->subject->process('document.pdf'));
    }

    /**
     * @test
     */
    public function handlesMultipleSpaces(): void
    {
        self::assertSame('a_b_c', $this->subject->process('a b c'));
    }

    /**
     * @test
     */
    public function handlesEmptyString(): void
    {
        self::assertSame('', $this->subject->process(''));
    }
}
