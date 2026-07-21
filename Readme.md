# Neos CMS plugin for preprocessing resources during import

Processes filenames and resources during Neos CMS import to standardize naming and resize images.
The processors are configurable and extendable.

## Installation

```console
composer require shel/neos-resource-import-preprocessor
```

## Configuration

Copy the following configuration into your `Settings.yaml` to adjust the plugin's behavior:

```yaml
Shel:
  Neos:
    ResourceImportPreprocessor:
      processFilenames:
        enabled: true
        processors:
          'replaceSpecialChars':
            class: 'Shel\Neos\ResourceImportPreprocessor\Processor\ReplaceSpecialCharsFilenameProcessor'
            options:
              # Regex pattern for characters to replace (default: replaces everything except a-zA-Z0-9._-)
              pattern: '/[^a-zA-Z0-9._-]/'
              # Replacement character (default: '-')
              replacement: '-'
      processResources:
        enabled: true
        processors:
          'resizeImages':
            class: 'Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor'
            options:
              # Maximum width & height in pixels (images exceeding this will be scaled down, aspect ratio is preserved)
              maxWidth: 1920
              maxHeight: 1920
              # Options passed to Imagine's save() method (e.g. JPEG quality, PNG compression)
              saveOptions:
                quality: 90
```

### Available processors

#### `ReplaceSpecialCharsFilenameProcessor`

Replaces special characters in filenames using a regex pattern. Useful to ensure filenames only contain safe characters.

| Option        | Type     | Description                                 |
|---------------|----------|---------------------------------------------|
| `pattern`     | `string` | PHP regex pattern for characters to replace |
| `replacement` | `string` | Replacement character                       |

#### `ResizeImageResourceProcessor`

Scales images down if they exceed the configured maximum dimensions. SVG images are skipped. Aspect ratio is always preserved.

| Option            | Type               | Description                                                                 |
|-------------------|--------------------|-----------------------------------------------------------------------------|
| `maxWidth`        | `int`              | Maximum width in pixels                                                     |
| `maxHeight`       | `int`              | Maximum height in pixels                                                    |
| `saveOptions`     | `array<string,mixed>` | Options passed to Imagine's `save()` method (see below)                  |
| `allowedMimeTypes`| `list<string>`     | MIME types to process (default: `['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp']`) |

The `allowedMimeTypes` option lets you restrict which image formats are processed. Any image whose MIME type is not in this list will be skipped and returned unchanged. This is useful if you only want to resize certain formats or want to avoid processing formats that might cause issues.

The processor uses the Imagine library with the vips driver and passes `unlimited: true` and `fail_on: none` options to vips when loading images. This allows it to gracefully handle truncated or corrupted images without crashing the PHP-FPM worker.

The `saveOptions` are passed directly to [Imagine's save method](https://imagine.readthedocs.io/en/latest/usage/introduction.html#saving-images). Common options:

| Option                    | Type    | Supported by          | Description                               |
|---------------------------|---------|-----------------------|-------------------------------------------|
| `quality`                 | `int`   | JPEG, WebP, AVIF      | Image quality (0–100)                     |
| `png_compression_level`   | `int`   | PNG                   | Compression level (0–9, default: 6)      |
| `jpeg_sampling_factor`    | `string`| JPEG                  | Chroma subsampling (e.g. `'4:2:0'`)      |

Example with custom save options:

```yaml
Shel:
  Neos:
    ResourceImportPreprocessor:
      processResources:
        processors:
          'resizeImages':
            class: 'Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor'
            options:
              maxWidth: 1920
              maxHeight: 1920
              saveOptions:
                quality: 85
                png_compression_level: 9
```

Example restricting to JPEG and PNG only:

```yaml
Shel:
  Neos:
    ResourceImportPreprocessor:
      processResources:
        processors:
          'resizeImages':
            class: 'Shel\Neos\ResourceImportPreprocessor\Processor\ResizeImageResourceProcessor'
            options:
              maxWidth: 1920
              maxHeight: 1920
              allowedMimeTypes:
                - 'image/jpeg'
                - 'image/png'
```

Note: When using the vips driver, AVIF files are supported if your vips installation has AVIF support. The `allowedMimeTypes` list includes `image/avif` by default.

### Custom processors

Custom processors can be added by implementing either the `Shel\Neos\ResourceImportPreprocessor\Processor\ResourceProcessorInterface` or the `Shel\Neos\ResourceImportPreprocessor\Processor\FilenameProcessorInterface` interfaces.

Then they can be registered in the configuration.

### Disabling processors

Individual processors can be disabled by setting them to `null` in the configuration:

```yaml
Shel:
  Neos:
    ResourceImportPreprocessor:
      processFilenames:
        enabled: true
        processors:
          'replaceSpecialChars': ~
```

A whole processor group can be disabled by setting `enabled` to `false`:
```yaml
Shel:
  Neos:
    ResourceImportPreprocessor:
      processFilenames:
        enabled: false
```

## Sponsors

The public first release of this package was generously sponsored by [Vogel communications group](https://www.vogel.de).

## License

See [License](LICENSE.txt)
