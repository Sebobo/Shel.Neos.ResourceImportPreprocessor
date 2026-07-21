<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Processor;

use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * A resource processor that scales images to a maximum width and height if they exceed it.
 *
 * When using the Vips driver, passes unlimited=true and fail_on=none options
 * to gracefully handle truncated/corrupted images without crashing PHP-FPM.
 * Works with any Imagine driver (GD, Imagick, Vips).
 */
class ResizeImageResourceProcessor implements ResourceProcessorInterface
{

    #[Flow\Inject]
    protected ImagineInterface $imagineService;

    /**
     * @var LoggerInterface
     */
    #[Flow\Inject]
    protected $systemLogger;

    protected int|null $maxWidth = null;
    protected int|null $maxHeight = null;
    /** @var array<string, mixed> */
    protected array $saveOptions = [];
    /** @var list<string> MIME types this processor will handle. Default is configured in Settings.yaml. */
    protected array $allowedMimeTypes = [];

    /**
     * Options passed to the vips loader to handle corrupted/truncated images gracefully.
     * Only used when the Vips driver is active.
     */
    private const VIPS_LOAD_OPTIONS = [
        'unlimited' => true,
        'fail_on' => 'none',
    ];

    private const MIME_TYPE_EXTENSION_MAP = [
        'image/png' => '.png',
        'image/jpeg' => '.jpg',
        'image/gif' => '.gif',
        'image/webp' => '.webp',
        'image/avif' => '.avif',
        'image/bmp' => '.bmp',
    ];

    /**
     * @param array{maxWidth?: int|null, maxHeight?: int|null, saveOptions?: array<string, mixed>, allowedMimeTypes?: mixed|list<string>} $options
     */
    public function setOptions(array $options = []): self
    {
        $this->maxWidth = $options['maxWidth'] ?? null;
        $this->maxHeight = $options['maxHeight'] ?? null;
        $this->saveOptions = $options['saveOptions'] ?? [];
        if (isset($options['allowedMimeTypes']) && is_array($options['allowedMimeTypes'])) {
            /** @phpstan-ignore-next-line */
            $this->allowedMimeTypes = array_values($options['allowedMimeTypes']);
        }
        return $this;
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

        if (!$this->canProcessMimeType($mimeType)) {
            $this->systemLogger->warning("Skipping unsupported MIME type: $mimeType for: $path");
            return $path;
        }

        $loadOptions = $this->getLoadOptions();

        try {
            // Options are only supported by the Vips implementation
            /** @phpstan-ignore arguments.count */
            $image = $this->imagineService->open($path, $loadOptions);
        } catch (\Exception $e) {
            $this->systemLogger->warning("Skipping corrupt/unreadable image: $path (" . $e->getMessage() . ")");
            return false;
        }

        $size = $image->getSize();
        $width = $size->getWidth();
        $height = $size->getHeight();

        $maxWidth = $this->maxWidth ?? $width;
        $maxHeight = $this->maxHeight ?? $height;

        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $path;
        }

        // Calculate new dimensions preserving aspect ratio
        $scaleRatio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)round($width * $scaleRatio);
        $newHeight = (int)round($height * $scaleRatio);

        $image->resize(new Box($newWidth, $newHeight));

        // Ensure file has an extension so Imagine can determine the format
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === '' && isset(self::MIME_TYPE_EXTENSION_MAP[$mimeType])) {
            $path = $path . self::MIME_TYPE_EXTENSION_MAP[$mimeType];
        }

        $image->save($path, $this->saveOptions);

        return $path;
    }

    /**
     * Returns load options appropriate for the active Imagine driver.
     * Vips-specific options are only returned when the Vips driver is in use.
     * @return array<string, mixed>
     */
    private function getLoadOptions(): array
    {
        // Check if vips driver is installed and in use
        if (class_exists('Imagine\Vips\Imagine') && $this->imagineService instanceof \Imagine\Vips\Imagine) {
            return self::VIPS_LOAD_OPTIONS;
        }

        return [];
    }

    /**
     * Returns true if this processor should handle the given MIME type.
     */
    private function canProcessMimeType(string $mimeType): bool
    {
        return in_array($mimeType, $this->allowedMimeTypes, true);
    }
}
