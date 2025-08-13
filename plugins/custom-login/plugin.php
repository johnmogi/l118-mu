<?php
/**
 * Custom Login MU Plugin
 * 
 * This is an example MU plugin that demonstrates the proper structure.
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Only load if our autoloader exists
if (!class_exists('Project\\MU\\Features\\CustomLogin')) {
    return;
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Create and initialize the plugin
    $custom_login = new Project\MU\Features\CustomLogin();
    $custom_login->init();
}, 20);
