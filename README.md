# WordPress MU Plugins Structure

This directory contains Must-Use (MU) plugins that are automatically enabled and cannot be disabled through the WordPress admin.

## Structure

```
mu-plugins/
├── composer.json          # Composer configuration
├── mu-loader.php          # Main loader file
├── src/                   # Shared PHP classes
│   ├── Core/             # Core functionality
│   │   └── Helpers.php   # Helper functions
│   └── Features/         # Feature classes
│       └── CustomLogin.php
└── plugins/              # Individual MU plugins
    └── custom-login/     # Example plugin
        └── plugin.php    # Plugin bootstrap
```

## Adding a New Plugin

1. Create a new directory in `plugins/` (e.g., `my-feature/`)
2. Create a `plugin.php` file in that directory
3. Use the autoloader to load your classes:

```php
// my-feature/plugin.php
use Project\MU\Features\MyFeature;

add_action('plugins_loaded', function() {
    if (class_exists(MyFeature::class)) {
        (new MyFeature())->init();
    }
});
```

## Development

1. Install dependencies:
   ```bash
   composer install
   ```

2. Dump autoloader after adding new classes:
   ```bash
   composer dump-autoload
   ```

## Best Practices

- Keep each plugin focused on a single feature
- Use namespaced classes in `src/`
- Add documentation for each feature
- Test thoroughly before deploying

## Example Plugin

See `plugins/custom-login/` for a complete example.
# L371-mu
# l583-2mu
# l118-mu
