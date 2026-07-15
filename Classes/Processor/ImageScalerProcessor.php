<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Utility\Environment;

/**
 * A resource processor that scales images to a maximum width and height if they exceed it.
 */
class ImageScalerProcessor implements ResourceProcessorInterface
{

    /**
     * @var Environment
     */
    #[Flow\Inject]
    protected $environment;

    #[Flow\InjectConfiguration(path: 'autoScale.maxWidth', package: 'Shel.Neos.ResourceImportPreprocessor')]
    protected int $maxWidth;

    #[Flow\InjectConfiguration(path: 'autoScale.maxHeight', package: 'Shel.Neos.ResourceImportPreprocessor')]
    protected int $maxHeight;

    public function __construct(
        protected ImagineInterface $imagineService,
    ) {
    }

    /**
     * Takes a local path to an image and scales it to the maximum width and height if it exceeds it.
     * @return string|false path to the processed image or false if the process failed
     */
    public function process(string $path): string|false
    {
        if (!is_file($path)) {
            return false;
        }

        $mimeType = mime_content_type($path);
        if ($mimeType === false || $mimeType === 'image/svg+xml' || !str_starts_with($mimeType, 'image/')) {
            return false;
        }

        try {
            $image = $this->imagineService->open($path);
            $size = $image->getSize();

            if ($size->getWidth() <= $this->maxWidth && $size->getHeight() <= $this->maxHeight) {
                return $path;
            }

            $scaleRatio = min(
                $this->maxWidth / $size->getWidth(),
                $this->maxHeight / $size->getHeight()
            );

            $newWidth = (int)round($size->getWidth() * $scaleRatio);
            $newHeight = (int)round($size->getHeight() * $scaleRatio);

            $image->resize(new Box($newWidth, $newHeight));
            // TODO: Do we need additional options like quality, from configuration?
            $image = $image->save($path);

            return $image ? $path : false;
        } catch (\Throwable) {
            return false;
        }
    }
}
