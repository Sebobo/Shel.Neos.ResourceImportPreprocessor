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
class ResizeImageResourceProcessor implements ResourceProcessorInterface
{

    #[Flow\Inject]
    protected Environment $environment;

    #[Flow\Inject]
    protected ImagineInterface $imagineService;

    protected int|null $maxWidth = null;
    protected int|null $maxHeight = null;

    /**
     * @param array{maxWidth?: int|null, maxHeight?: int|null} $options
     */
    public function setOptions(array $options = []): self
    {
        $this->maxWidth = $options['maxWidth'] ?? null;
        $this->maxHeight = $options['maxHeight'] ?? null;
        return $this;
    }

    private const MIME_TYPE_EXTENSION_MAP = [
        'image/png' => '.png',
        'image/jpeg' => '.jpg',
        'image/gif' => '.gif',
        'image/webp' => '.webp',
        'image/avif' => '.avif',
        'image/bmp' => '.bmp',
    ];

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
            // Ensure file has an extension so Imagine can determine the save format
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            if ($extension === '' && isset(self::MIME_TYPE_EXTENSION_MAP[$mimeType])) {
                $newPath = $path . self::MIME_TYPE_EXTENSION_MAP[$mimeType];
                if (rename($path, $newPath)) {
                    $path = $newPath;
                }
            }

            $image = $this->imagineService->open($path);
            $size = $image->getSize();

            $maxWidth = $this->maxWidth ?? $size->getWidth();
            $maxHeight = $this->maxHeight ?? $size->getHeight();

            if ($size->getWidth() <= $maxWidth && $size->getHeight() <= $maxHeight) {
                return $path;
            }

            $scaleRatio = min(
                $maxWidth / $size->getWidth(),
                $maxHeight / $size->getHeight()
            );

            $newWidth = (int)round($size->getWidth() * $scaleRatio);
            $newHeight = (int)round($size->getHeight() * $scaleRatio);

            $image->resize(new Box($newWidth, $newHeight));
            // TODO: Do we need additional options like quality, from configuration?
            $image->save($path);
            return $path;
        } catch (\Throwable) {
            return false;
        }
    }
}
