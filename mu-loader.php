<?php
/**
 * MU Plugin Loader
 * 
 * This file loads all MU plugins and sets up autoloading.
 * Place this file in wp-content/mu-plugins/
 */

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load helper functions
if (file_exists(__DIR__ . '/src/Core/Helpers.php')) {
    require_once __DIR__ . '/src/Core/Helpers.php';
}

// Auto-load all plugin files in the plugins directory
$plugin_dirs = glob(__DIR__ . '/plugins/*', GLOB_ONLYDIR);
foreach ($plugin_dirs as $plugin_dir) {
    $plugin_file = $plugin_dir . '/plugin.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
    }
}
