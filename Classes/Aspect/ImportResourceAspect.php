<?php
declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Unicode\Functions as UnicodeFunctions;
use Shel\Neos\ResourceImportPreprocessor\Processor\FilenameProcessorInterface;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;

/**
 * This aspect runs during resource import and modifies the resource based on configured processors.
 */
#[Flow\Aspect]
class ImportResourceAspect
{
    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    /**
     * This aspect modifies the filename of a persistent resource when it is set. F.e. during import.
     */
    #[Flow\Around('setting(Shel.Neos.ResourceImportPreprocessor.adjustFilename.enabled) && method(Neos\Flow\ResourceManagement\PersistentResource->setFilename())')]
    public function processFilenameWhenSet(JoinPointInterface $joinPoint): void
    {
        $filename = $joinPoint->getMethodArgument('filename');
        if (is_string($filename)) {
            $pathInfo = UnicodeFunctions::pathinfo($filename);
            $extension = $pathInfo['extension'] ?? '';
            $extension = (is_string($extension) ? '.' . strtolower($extension) : '');
            $filename = $pathInfo['filename'] ?? '';
            if (is_string($filename)) {
                $newFilename = $this->processFilename($filename) . $extension;
                // Replace the filename given to the original method with the new one
                $joinPoint->setMethodArgument('filename', $newFilename);
            }
        }

        $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    /**
     * Calls the registered filename processor on the filename without extension.
     * Normalization is necessary to avoid problems with filenames containing special characters
     */
    private function processFilename(string $filename): string
    {
        $normalizedFilename = Normalizer::normalize($filename, Normalizer::FORM_C);
        /** @var FilenameProcessorInterface $filenameProcessor */
        $filenameProcessor = $this->objectManager->get(FilenameProcessorInterface::class);
        return $filenameProcessor->process(is_string($normalizedFilename) ? $normalizedFilename : $filename);
    }
}
