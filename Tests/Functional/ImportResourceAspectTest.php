<?php

declare(strict_types=1);

namespace Shel\Neos\ResourceImportPreprocessor\Tests\Functional;

use Imagine\Gd\Imagine;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Utility\Environment;
use PHPUnit\Framework\TestCase;
use Shel\Neos\ResourceImportPreprocessor\Aspect\ImportResourceAspect;
use Shel\Neos\ResourceImportPreprocessor\Processor\ReplaceSpecialCharsFilenameProcessor;
use Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor;

class ImportResourceAspectTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for functional tests');
        }

        $this->tempDir = sys_get_temp_dir() . '/import_preprocessor_aspect_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    private function createEnvironmentMock(): Environment
    {
        $environment = $this->createMock(Environment::class);
        $environment->method('getPathToTemporaryDirectory')
            ->willReturn($this->tempDir . '/');
        return $environment;
    }

    private function createAspect(
        array $filenameProcessors = [],
        array $resourceProcessors = [],
        ?ObjectManagerInterface $objectManager = null,
    ): ImportResourceAspect {
        $aspect = new ImportResourceAspect();

        $reflection = new \ReflectionClass($aspect);

        $prop = $reflection->getProperty('environment');
        $prop->setValue($aspect, $this->createEnvironmentMock());

        $prop = $reflection->getProperty('filenameProcessors');
        $prop->setValue($aspect, $filenameProcessors);

        $prop = $reflection->getProperty('resourceProcessors');
        $prop->setValue($aspect, $resourceProcessors);

        if ($objectManager !== null) {
            $prop = $reflection->getProperty('objectManager');
            $prop->setValue($aspect, $objectManager);
        }

        return $aspect;
    }

    private function createFilenameAspect(array $filenameProcessors): ImportResourceAspect
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')
            ->willReturnCallback(static fn (string $class) => new $class());

        return $this->createAspect(
            filenameProcessors: $filenameProcessors,
            objectManager: $objectManager,
        );
    }

    private function createResourceAspect(array $resourceProcessors): ImportResourceAspect
    {
        $environment = $this->createEnvironmentMock();

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')
            ->willReturnCallback(function (string $class) use ($environment) {
                $instance = new $class();
                if ($instance instanceof ResizeImageResourceProcessor) {
                    $reflection = new \ReflectionClass($instance);
                    $prop = $reflection->getProperty('environment');
                    $prop->setValue($instance, $environment);
                    $prop = $reflection->getProperty('imagineService');
                    $prop->setValue($instance, new Imagine());
                }
                return $instance;
            });

        return $this->createAspect(
            resourceProcessors: $resourceProcessors,
            objectManager: $objectManager,
        );
    }

    private function invokeProcessFilename(ImportResourceAspect $aspect, string $filename): string
    {
        $reflection = new \ReflectionMethod($aspect, 'processFilename');
        $reflection->setAccessible(true);
        return $reflection->invoke($aspect, $filename);
    }

    private function invokeProcessResourceSource(ImportResourceAspect $aspect, mixed $source): string|false
    {
        $reflection = new \ReflectionMethod($aspect, 'processResourceSource');
        $reflection->setAccessible(true);
        return $reflection->invoke($aspect, $source);
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

    //
    // processFilename tests
    //

    /**
     * @test
     */
    public function processFilenameReturnsOriginalWhenNoProcessorsConfigured(): void
    {
        $aspect = $this->createAspect();

        $result = $this->invokeProcessFilename($aspect, 'hello world');

        self::assertSame('hello world', $result);
    }

    /**
     * @test
     */
    public function processFilenameReplacesSpecialChars(): void
    {
        $aspect = $this->createFilenameAspect([
            'replaceSpecialChars' => [
                'class' => ReplaceSpecialCharsFilenameProcessor::class,
                'options' => [
                    'pattern' => '/[^a-zA-Z0-9._-]/',
                    'replacement' => '-',
                ],
            ],
        ]);

        $result = $this->invokeProcessFilename($aspect, 'Hello World! Test (1)');

        self::assertSame('Hello-World-Test-1', $result);
    }

    /**
     * @test
     */
    public function processFilenameReturnsFallbackWhenProcessorProducesEmpty(): void
    {
        $aspect = $this->createFilenameAspect([
            'replaceSpecialChars' => [
                'class' => ReplaceSpecialCharsFilenameProcessor::class,
                'options' => [
                    'pattern' => '/[a-zA-Z0-9]/',
                    'replacement' => '-',
                ],
            ],
        ]);

        $result = $this->invokeProcessFilename($aspect, 'abc');

        self::assertStringStartsWith('file_', $result);
    }

    /**
     * @test
     */
    public function processFilenameHandlesUnicodeNormalisation(): void
    {
        $aspect = $this->createFilenameAspect([
            'replaceSpecialChars' => [
                'class' => ReplaceSpecialCharsFilenameProcessor::class,
                'options' => [
                    'pattern' => '/[^a-zA-Z0-9._-]/',
                    'replacement' => '-',
                ],
            ],
        ]);

        // Umlaut ü (U+00FC) is a single non-ascii character that gets replaced
        $result = $this->invokeProcessFilename($aspect, mb_convert_encoding('München', 'UTF-8', 'ISO-8859-1'));

        self::assertSame('M-nchen', $result);
    }

    /**
     * @test
     */
    public function processFilenameNormalisesDecomposedCharacters(): void
    {
        $aspect = $this->createFilenameAspect([
            'replaceSpecialChars' => [
                'class' => ReplaceSpecialCharsFilenameProcessor::class,
                'options' => [
                    'pattern' => '/[^a-zA-Z0-9._-]/',
                    'replacement' => '-',
                ],
            ],
        ]);

        // Decomposed ü = u (U+0075) + combining diaeresis (U+0308) — normalised to single character
        $decomposed = "Mu" . "\u{0308}" . "nchen";
        $result = $this->invokeProcessFilename($aspect, $decomposed);

        self::assertSame('M-nchen', $result);
    }

    //
    // processResourceSource tests
    //

    /**
     * @test
     */
    public function processResourceSourceReturnsFalseWhenNoProcessorsConfigured(): void
    {
        $aspect = $this->createAspect();

        $result = $this->invokeProcessResourceSource($aspect, '/some/file.png');

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function processResourceSourceReturnsFalseForInvalidSourceType(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $result = $this->invokeProcessResourceSource($aspect, 12345);

        self::assertSame(false, $result);
    }

    /**
     * @test
     */
    public function processResourceSourceScalesLargeImage(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->createPngImage(3840, 2160);
        $result = $this->invokeProcessResourceSource($aspect, $path);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        self::assertLessThanOrEqual(1920, $dims['width']);
        self::assertLessThanOrEqual(1080, $dims['height']);
    }

    /**
     * @test
     */
    public function processResourceSourceReturnsPathUnchangedForSmallImage(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->createPngImage(800, 600);
        $result = $this->invokeProcessResourceSource($aspect, $path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function processResourceSourceReturnsPathForNonImageFile(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->tempDir . '/document.pdf';
        file_put_contents($path, '%PDF-1.4 fake content');

        $result = $this->invokeProcessResourceSource($aspect, $path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function processResourceSourceHandlesResourceInput(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->createPngImage(3840, 2160);
        $resource = fopen($path, 'r');
        self::assertIsResource($resource);

        $result = $this->invokeProcessResourceSource($aspect, $resource);

        self::assertIsString($result);
        $dims = $this->getImageDimensions($result);

        self::assertLessThanOrEqual(1920, $dims['width']);
        self::assertLessThanOrEqual(1080, $dims['height']);
    }

    /**
     * @test
     */
    public function processResourceSourcePreservesOriginalWhenProcessorReturnsFalse(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->tempDir . '/document.pdf';
        file_put_contents($path, '%PDF-1.4 fake content');

        $result = $this->invokeProcessResourceSource($aspect, $path);

        self::assertSame($path, $result);
    }

    /**
     * @test
     */
    public function processResourceSourceCreatesTempFileWithExtension(): void
    {
        $aspect = $this->createResourceAspect([
            'resize' => [
                'class' => ResizeImageResourceProcessor::class,
                'options' => ['maxWidth' => 1920, 'maxHeight' => 1080],
            ],
        ]);

        $path = $this->createPngImage(3840, 2160);
        $resource = fopen($path, 'r');
        self::assertIsResource($resource);

        $result = $this->invokeProcessResourceSource($aspect, $resource);

        self::assertIsString($result);
        self::assertNotSame($path, $result);
        self::assertStringEndsWith('.png', $result);
        self::assertFileExists($result);
    }
}
