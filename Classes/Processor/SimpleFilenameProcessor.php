<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

use Neos\Flow\Annotations as Flow;

/**
 * A simple filename processor that replaces spaces with underscores.
 */
#[Flow\Proxy(false)]
final readonly class SimpleFilenameProcessor implements FilenameProcessorInterface
{
    public function process(string $filename): string
    {
        return str_replace(' ', '_', $filename);
    }
}
