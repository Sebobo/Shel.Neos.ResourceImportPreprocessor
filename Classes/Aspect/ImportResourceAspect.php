<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Aspect;

use Doctrine\ORM\Query\AST\Join;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Utility\Algorithms;
use Neos\Flow\Utility\Environment;
use Neos\Utility\Unicode\Functions as UnicodeFunctions;
use Shel\Neos\ResourceImportPreprocessor\Processor\FilenameProcessorInterface;
use Shel\Neos\ResourceImportPreprocessor\Processor\ResourceProcessorInterface;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;

/**
 * This aspect runs during resource import and modifies the resource based on configured processors.
 */
#[Flow\Aspect]
#[Flow\Scope('singleton')]
class ImportResourceAspect
{
    #[Flow\Inject]
    protected ObjectManagerInterface $objectManager;

    #[Flow\Inject]
    protected Environment $environment;

    /** @var list<string> */
    protected array $processedResourcePaths = [];

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

    #[Flow\Around('setting(Shel.Neos.ResourceImportPreprocessor.autoScale.enabled) && method(Neos\Flow\ResourceManagement\ResourceManager->importResource())')]
    public function processResourceBeforeImport(JoinPointInterface $joinPoint): PersistentResource
    {
        /** @var string $collectionName */
        $collectionName = $joinPoint->getMethodArgument('collectionName');
        if ($collectionName !== ResourceManager::DEFAULT_PERSISTENT_COLLECTION_NAME) {
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }

        /** @var string|resource $source */
        $source = $joinPoint->getMethodArgument('source');

        try {
            $processedResourcePath = $this->processResourceSource($source);
            if ($processedResourcePath !== false) {
                $joinPoint->setMethodArgument('source', $processedResourcePath);
                $this->processedResourcePaths[] = $processedResourcePath;
                return $joinPoint->getAdviceChain()->proceed($joinPoint);
            }
        } catch (\Throwable) {
            // Processing failed — import the original resource unchanged
        }
        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }

    #[Flow\After('setting(Shel.Neos.ResourceImportPreprocessor.autoScale.enabled) && method(Neos\Flow\ResourceManagement\ResourceManager->importUploadedResource())')]
    public function processResourceAfterImport(JoinPointInterface $joinPoint): void
    {
        // Clean up temporary files
        foreach ($this->processedResourcePaths as $processedResourcePath) {
            @unlink($processedResourcePath);
        }
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

    /**
     * Calls the registered resource processors on the resource content.
     */
    private function processResourceSource($source): string|false
    {
        if (is_resource($source)) {
            $content = stream_get_contents($source);
            if ($content === false) {
                return false;
            }
            $temporaryTargetPathAndFilename = $this->environment
                    ->getPathToTemporaryDirectory() . 'resource_preprocessor_' . Algorithms::generateRandomString(13);
            file_put_contents($temporaryTargetPathAndFilename, $content);
            $processor = $this->objectManager->get(ResourceProcessorInterface::class);
            if (!$processor) {
                return false;
            }
            return $processor->process(
                $temporaryTargetPathAndFilename
            );
        }

        return $this->objectManager->get(ResourceProcessorInterface::class)->process($source);
    }
}
