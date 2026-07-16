<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Tests\Unit;

use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Neos\Flow\Utility\Environment;
use PHPUnit\Framework\TestCase;
use Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor;

class ResizeImageResourceProcessorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/import_preprocessor_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    private function createProcessor(?ImagineInterface $imagineService = null): ResizeImageResourceProcessor
    {
        $processor = (new ResizeImageResourceProcessor())
            ->setOptions(['maxWidth' => 1920, 'maxHeight' => 1080]);

        $environment = $this->createMock(Environment::class);
        $environment->method('getPathToTemporaryDirectory')
            ->willReturn($this->tempDir . '/');

        $reflection = new \ReflectionClass($processor);
        $prop = $reflection->getProperty('environment');
        $prop->setValue($processor, $environment);

        if ($imagineService !== null) {
            $prop = $reflection->getProperty('imagineService');
            $prop->setValue($processor, $imagineService);
        }

        return $processor;
    }

    private function createTestImage(int $width, int $height, string $extension = 'png'): string
    {
        $path = $this->tempDir . '/test.' . $extension;
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $path);
        imagedestroy($image);
        return $path;
    }

    private function createMockImagine(int $newWidth, int $newHeight): ImagineInterface
    {
        $size = $this->createMock(BoxInterface::class);
        $size->method('getWidth')->willReturn($newWidth);
        $size->method('getHeight')->willReturn($newHeight);

        $image = $this->createMock(ImageInterface::class);
        $image->method('getSize')->willReturn($size);
        $image->method('resize')->willReturnSelf();
        $image->expects($this->any())->method('save')->willReturn(true);

        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')->willReturn($image);

        return $imagine;
    }

    /**
     * @test
     */
    public function returnsSourceWhenImageIsWithinBounds(): void
    {
        $processor = $this->createProcessor($this->createMockImagine(100, 100));

        $path = $this->createTestImage(100, 100);
        $result = $processor->process($path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function returnsSourceWhenWidthMatchesExactly(): void
    {
        $processor = $this->createProcessor($this->createMockImagine(1920, 500));

        $path = $this->createTestImage(1920, 500);
        $result = $processor->process($path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function returnsSourceWhenHeightMatchesExactly(): void
    {
        $processor = $this->createProcessor($this->createMockImagine(500, 1080));

        $path = $this->createTestImage(500, 1080);
        $result = $processor->process($path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function returnsSourceForNonImageFile(): void
    {
        $processor = $this->createProcessor();

        $path = $this->tempDir . '/document.txt';
        file_put_contents($path, 'This is not an image');

        $result = $processor->process($path);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function returnsSourceForSvgFile(): void
    {
        $processor = $this->createProcessor();

        $path = $this->tempDir . '/image.svg';
        file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100"/></svg>'
        );

        $result = $processor->process($path);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function returnsResizedContentWhenImageExceedsBounds(): void
    {
        $size = $this->createMock(BoxInterface::class);
        $size->method('getWidth')->willReturn(3840);
        $size->method('getHeight')->willReturn(2160);

        $image = $this->createMock(ImageInterface::class);
        $image->method('getSize')->willReturn($size);
        $image->expects($this->once())->method('resize')->with(
            $this->callback(fn(Box $box) => $box->getWidth() <= 1920 && $box->getHeight() <= 1080)
        );
        $image->expects($this->once())->method('save');

        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')->willReturn($image);

        $processor = $this->createProcessor($imagine);

        $path = $this->createTestImage(3840, 2160);
        $processor->process($path);
    }

    /**
     * @test
     */
    public function cleansUpTempFileOnSuccess(): void
    {
        $processor = $this->createProcessor($this->createMockImagine(960, 540));

        $path = $this->createTestImage(3840, 2160);
        $processor->process($path);

        $tempFiles = glob($this->tempDir . '/Neos_Flow_ResourceImport_*');
        self::assertEmpty($tempFiles);
    }

    /**
     * @test
     */
    public function cleansUpTempFileOnException(): void
    {
        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')
            ->willThrowException(new \RuntimeException('Driver error'));

        $processor = $this->createProcessor($imagine);

        $path = $this->createTestImage(3840, 2160);
        $result = $processor->process($path);

        self::assertSame(false, $result);

        $tempFiles = glob($this->tempDir . '/Neos_Flow_ResourceImport_*');
        self::assertEmpty($tempFiles);
    }

    /**
     * @test
     */
    public function returnsSourceWhenCopyFails(): void
    {
        $processor = $this->createProcessor();

        $nonExistentPath = '/nonexistent/path/image.png';
        $result = $processor->process($nonExistentPath);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function passesSaveOptionsToImagine(): void
    {
        $size = $this->createMock(BoxInterface::class);
        $size->method('getWidth')->willReturn(3840);
        $size->method('getHeight')->willReturn(2160);

        $image = $this->createMock(ImageInterface::class);
        $image->method('getSize')->willReturn($size);
        $image->method('resize')->willReturnSelf();
        $image->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                $this->equalTo(['quality' => 80, 'png_compression_level' => 9])
            );

        $imagine = $this->createMock(ImagineInterface::class);
        $imagine->method('open')->willReturn($image);

        $processor = (new ResizeImageResourceProcessor())
            ->setOptions([
                'maxWidth' => 1920,
                'maxHeight' => 1080,
                'saveOptions' => ['quality' => 80, 'png_compression_level' => 9],
            ]);

        $environment = $this->createMock(Environment::class);
        $environment->method('getPathToTemporaryDirectory')
            ->willReturn($this->tempDir . '/');

        $reflection = new \ReflectionClass($processor);
        $prop = $reflection->getProperty('environment');
        $prop->setValue($processor, $environment);
        $prop = $reflection->getProperty('imagineService');
        $prop->setValue($processor, $imagine);

        $path = $this->createTestImage(3840, 2160);
        $processor->process($path);
    }
}
