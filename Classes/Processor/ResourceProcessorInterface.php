<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

/**
 * Interface for processors that modify resource content during import.
 */
interface ResourceProcessorInterface
{
    /**
     * Process the resource content and return the modified content.
     * @param string $path the path to the resource file
     * @return string|false the path to the processed file or false if the process failed
     */
    public function process(string $path): string|false;
}
