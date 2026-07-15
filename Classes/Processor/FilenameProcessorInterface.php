<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

/**
 *
 */
interface FilenameProcessorInterface
{
    public function process(string $filename): string;
}
