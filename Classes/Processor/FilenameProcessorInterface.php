<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

/**
 * Interface for processors that modify the filename during import.
 */
interface FilenameProcessorInterface
{
    public function setOptions(array $options = []): self;

    public function process(string $filename): string;
}
