<?php
declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Unicode\Functions as UnicodeFunctions;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;

/**
 * This aspect runs during resource import and modifies the resource based on configured processors.
 */
#[Flow\Aspect]
class ImportResourceAspect
{
    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    #[Flow\Around('setting(Shel.Neos.ResourceImportPreprocessor.adjustFilename.enabled) && method(Neos\Flow\ResourceManagement\PersistentResource->setFilename())')]
    public function processFilenameOnImport(JoinPointInterface $joinPoint): void
    {
        $filename = $joinPoint->getMethodArgument('filename');

        $pathInfo = UnicodeFunctions::pathinfo($filename);
        $extension = (isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '');
        $newFilename = $this->processFilename($pathInfo['filename']) . $extension;

        // Replace the filename given to the original method with the new one
        $joinPoint->setMethodArgument('filename', $newFilename);

        $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    /**
     * Calls the registered filename processor on the filename without extension.
     * Normalization is necessary to avoid problems with filenames containing special characters
     */
    private function processFilename(string $filename): string
    {
        $normalizedFilename = Normalizer::normalize($filename, Normalizer::FORM_C);
        $filenameProcessor = $this->objectManager->get('Shel\Neos\ResourceImportPreprocessor\Processor\FilenameProcessorInterface');
        return $filenameProcessor->process($normalizedFilename);
    }
}
