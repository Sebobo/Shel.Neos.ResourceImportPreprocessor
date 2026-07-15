<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Tests\Functional;

use Imagine\Gd\Imagine;
use Neos\Flow\Utility\Environment;
use PHPUnit\Framework\TestCase;
use Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor;

/**
 *
 */
class ResizeImageResourceProcessorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for functional image tests');
        }

        $this->tempDir = sys_get_temp_dir() . '/import_preprocessor_func_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    private function createProcessor(int $maxWidth = 1920, int $maxHeight = 1080): ResizeImageResourceProcessor
    {
        $processor = (new ResizeImageResourceProcessor())
            ->setOptions(['maxWidth' => $maxWidth, 'maxHeight' => $maxHeight]);

        $environment = $this->createMock(Environment::class);
        $environment->method('getPathToTemporaryDirectory')
            ->willReturn($this->tempDir . '/');

        $reflection = new \ReflectionClass($processor);
        $prop = $reflection->getProperty('environment');
        $prop->setValue($processor, $environment);
        $prop = $reflection->getProperty('imagineService');
        $prop->setValue($processor, new Imagine());

        return $processor;
    }

    private function createPngImage(int $width, int $height): string
    {
        $path = $this->tempDir . '/source.png';
        $image = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($image, 0, 0, 255);
        imagefill($image, 0, 0, $blue);
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }

    private function getImageDimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false) {
            self::fail('Could not read image dimensions from: ' . $path);
        }
        return ['width' => $info[0], 'height' => $info[1]];
    }

    /**
     * @test
     */
    public function scalesDownLargePngImage(): void
    {
        $processor = $this->createProcessor();
        $path = $this->createPngImage(3840, 2160);

        $result = $processor->process($path);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        self::assertLessThanOrEqual(1920, $dims['width']);
        self::assertLessThanOrEqual(1080, $dims['height']);
    }

    /**
     * @test
     */
    public function maintainsAspectRatioWhenScalingWidth(): void
    {
        $processor = $this->createProcessor();
        $path = $this->createPngImage(3840, 1920);

        $result = $processor->process($path);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        $ratio = $dims['width'] / $dims['height'];
        self::assertEqualsWithDelta(2.0, $ratio, 0.01);
    }

    /**
     * @test
     */
    public function returnsSourceUnchangedWhenWithinBounds(): void
    {
        $processor = $this->createProcessor();
        $path = $this->createPngImage(800, 600);

        $result = $processor->process($path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function returnsSourceUnchangedForNonImageFile(): void
    {
        $processor = $this->createProcessor();

        $path = $this->tempDir . '/document.pdf';
        file_put_contents($path, '%PDF-1.4 fake content');

        $result = $processor->process($path);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function returnsSourceUnchangedForSvgFile(): void
    {
        $processor = $this->createProcessor();

        $path = $this->tempDir . '/vector.svg';
        file_put_contents($path, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="5000" height="5000"><rect width="5000" height="5000" fill="red"/></svg>');

        $result = $processor->process($path);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function processesJpegImage(): void
    {
        $processor = $this->createProcessor();

        $path = $this->tempDir . '/source.jpg';
        $image = imagecreatetruecolor(3840, 2160);
        $red = imagecolorallocate($image, 255, 0, 0);
        imagefill($image, 0, 0, $red);
        imagejpeg($image, $path, 85);
        imagedestroy($image);

        $result = $processor->process($path);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        self::assertLessThanOrEqual(1920, $dims['width']);
        self::assertLessThanOrEqual(1080, $dims['height']);
    }

    /**
     * @test
     */
    public function cleansUpTempFilesAfterProcessing(): void
    {
        $processor = $this->createProcessor();
        $path = $this->createPngImage(3840, 2160);

        $processor->process($path);

        $tempFiles = glob($this->tempDir . '/Neos_Flow_ResourceImport_*');
        self::assertEmpty($tempFiles);
    }

    /**
     * @test
     */
    public function scalesExactlyToMaxDimensions(): void
    {
        $processor = $this->createProcessor(100, 100);
        $path = $this->createPngImage(200, 200);

        $result = $processor->process($path);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        self::assertSame(100, $dims['width']);
        self::assertSame(100, $dims['height']);
    }
}
