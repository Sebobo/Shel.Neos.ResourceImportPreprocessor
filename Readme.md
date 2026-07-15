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

| Option      | Type  | Description              |
|-------------|-------|--------------------------|
| `maxWidth`  | `int` | Maximum width in pixels  |
| `maxHeight` | `int` | Maximum height in pixels |

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
