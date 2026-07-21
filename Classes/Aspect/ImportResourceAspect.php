<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Aspect;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Utility\Algorithms;
use Neos\Flow\Utility\Environment;
use Neos\Utility\PositionalArraySorter;
use Neos\Utility\Unicode\Functions as UnicodeFunctions;
use Shel\Neos\ResourceImportPreprocessor\Processor\FilenameProcessorInterface;
use Shel\Neos\ResourceImportPreprocessor\Processor\ResourceProcessorInterface;

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

    /**
     * @var FilenameProcessorInterface[]|null
     */
    #[Flow\InjectConfiguration('processFilenames.processors', 'Shel.Neos.ResourceImportPreprocessor')]
    protected ?array $filenameProcessors = [];

    /**
     * @var ResourceProcessorInterface[]|null
     */
    #[Flow\InjectConfiguration('processResources.processors', 'Shel.Neos.ResourceImportPreprocessor')]
    protected ?array $resourceProcessors = [];

    /** @var array<string, true> */
    protected array $processedResourcePaths = [];

    /**
     * This aspect modifies the filename of a persistent resource when it is set. F.e. during import.
     */
    #[Flow\Around('setting(Shel.Neos.ResourceImportPreprocessor.processFilenames.enabled) && method(Neos\Flow\ResourceManagement\PersistentResource->setFilename())')]
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

    #[Flow\Around('setting(Shel.Neos.ResourceImportPreprocessor.processResources.enabled) && method(Neos\Flow\ResourceManagement\ResourceManager->importResource())')]
    public function processResourceBeforeImport(JoinPointInterface $joinPoint): PersistentResource
    {
        /** @var string $collectionName */
        $collectionName = $joinPoint->getMethodArgument('collectionName');
        if ($collectionName !== ResourceManager::DEFAULT_PERSISTENT_COLLECTION_NAME) {
            /** @var PersistentResource $result */
            $result = $joinPoint->getAdviceChain()->proceed($joinPoint);
            return $result;
        }

        /** @var string $source */
        $source = $joinPoint->getMethodArgument('source');
        try {
            $processedResourcePath = $this->processResourceSource($source);
            if ($processedResourcePath !== false) {
                $joinPoint->setMethodArgument('source', $processedResourcePath);
            }
        } catch (\Throwable) {
            // Processing failed — import the original resource unchanged
        }
        /** @var PersistentResource $result */
        $result = $joinPoint->getAdviceChain()->proceed($joinPoint);
        return $result;
    }

    public function shutdownObject(): void
    {
        // Clean up temporary files
        foreach (array_keys($this->processedResourcePaths) as $processedResourcePath) {
            if (is_file($processedResourcePath)) {
                @unlink($processedResourcePath);
            }
        }
    }

    /**
     * Calls the registered filename processor on the filename without extension.
     * Normalization is necessary to avoid problems with filenames containing special characters
     */
    private function processFilename(string $filename): string
    {
        /** @var array<string, null|array{class: string, options?: array<string, mixed>}> $sortedProcessors */
        $sortedProcessors = (new PositionalArraySorter($this->filenameProcessors ?? []))->toArray();
        if (!$sortedProcessors) {
            return $filename;
        }

        // Instantiate each configured processor
        /** @var FilenameProcessorInterface[] $processorInstances */
        $processorInstances = array_reduce($sortedProcessors, function (array $carry, array|null $processorConfig) {
            if ($processorConfig === null) {
                return $carry;
            }
            try {
                $processorInstance = $this->objectManager->get($processorConfig['class']);
                if ($processorInstance instanceof FilenameProcessorInterface) {
                    $processorInstance->setOptions($processorConfig['options'] ?? []);
                    $carry[] = $processorInstance;
                }
            } catch (\Exception) {
            }
            return $carry;
        }, []);

        $processedFilename = $filename;
        foreach ($processorInstances as $processor) {
            $processedFilename = $processor->process($processedFilename);
        }
        return $processedFilename;
    }

    /**
     * Calls the registered resource processors on the resource content.
     */
    private function processResourceSource(mixed $source): string|false
    {
        if (!is_string($source) && !is_resource($source)) {
            return false;
        }

        /** @var array<string, null|array{class: string, options?: array<string, mixed>}> $sortedProcessors */
        $sortedProcessors = (new PositionalArraySorter($this->resourceProcessors ?? []))->toArray();
        if (!$sortedProcessors) {
            return false;
        }

        // Instantiate each configured processor
        /** @var ResourceProcessorInterface[] $processorInstances */
        $processorInstances = array_reduce($sortedProcessors, function (array $carry, array|null $processorConfig) {
            if ($processorConfig === null) {
                return $carry;
            }
            try {
                $processorInstance = $this->objectManager->get($processorConfig['class']);
                if ($processorInstance instanceof ResourceProcessorInterface) {
                    $processorInstance->setOptions($processorConfig['options'] ?? []);
                    $carry[] = $processorInstance;
                }
            } catch (\Exception) {
            }
            return $carry;
        }, []);

        $pathToProcess = $source;
        if (is_resource($source)) {
            $content = stream_get_contents($source);
            if ($content === false) {
                return false;
            }
            $temporaryTargetPathAndFilename = $this->environment
                    ->getPathToTemporaryDirectory() . 'resource_preprocessor_' . Algorithms::generateRandomString(13);
            file_put_contents($temporaryTargetPathAndFilename, $content);
            $pathToProcess = $temporaryTargetPathAndFilename;
        }

        if (!is_string($pathToProcess)) {
            return false;
        }

        // Store path for cleanup
        $this->processedResourcePaths[$pathToProcess] = true;

        // Execute each processor
        foreach ($processorInstances as $processor) {
            $result = $processor->process($pathToProcess);
            if ($result !== false) {
                // Store possible new path for cleanup
                $this->processedResourcePaths[$result] = true;
                $pathToProcess = $result;
            }
        }

        return $pathToProcess;
    }
}
