<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

use Neos\Flow\Annotations as Flow;
use Normalizer;

/**
 * A simple filename processor that replaces spaces with underscores.
 */
#[Flow\Proxy(false)]
final class ReplaceSpecialCharsFilenameProcessor implements FilenameProcessorInterface
{
    private string|null $pattern = null;
    private string $replacement = '';

    /**
     * @param array{pattern?: string|null, replacement?: string|null} $options
     */
    public function setOptions(array $options = []): self
    {
        $this->pattern = $options['pattern'] ?? null;
        $this->replacement = $options['replacement'] ?? '';
        return $this;
    }

    /**
     * Replaces characters in the filename given the provided pattern and replacement.
     */
    public function process(string $filename): string
    {
        if (!$this->pattern || !$filename) {
            return $filename;
        }
        $filename = Normalizer::normalize($filename, Normalizer::FORM_C);
        $filename = preg_replace($this->pattern, $this->replacement, $filename);
        $filename = preg_replace('/' . $this->replacement . '+/', $this->replacement, $filename);
        $filename = trim($filename, '.-_');
        if (empty($filename)) {
            $filename = 'file_' . time();
        }
        return $filename;
    }
}
