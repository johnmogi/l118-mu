<?php
/**
 * School Manager Import/Export Enhancement
 * 
 * Temporary MU plugin that enhances the School Manager's import/export functionality.
 * Exits gracefully if School Manager plugin is not active.
 * 
 * @package    School_Manager_Import_Export
 * @since      1.0.0
 * @copyright  (c) 2025, Your Name
 * @license    GPL-2.0+
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Check if School Manager plugin is active
if (!class_exists('School_Manager')) {
    // Add admin notice if School Manager is not active
    add_action('admin_notices', function() {
        if (current_user_can('activate_plugins')) {
            echo '<div class="error"><p>';
            echo 'School Manager Import/Export MU Plugin requires the <strong>School Manager</strong> plugin to be installed and activated.';
            echo '</p></div>';
        }
    });
    return; // Exit if School Manager is not active
}

// Include the import-export enhancement files
require_once __DIR__ . '/import-export-enhancement.php';
require_once __DIR__ . '/chunked-student-import.php';

// Initialize the enhancement classes
add_action('plugins_loaded', function() {
    // Only initialize if School Manager is active
    if (class_exists('School_Manager')) {
        // Initialize the import/export enhancement
        if (class_exists('Import_Export_Enhancement')) {
            Import_Export_Enhancement::instance();
        }
        
        // Initialize the chunked student import
        if (class_exists('Chunked_Student_Import')) {
            Chunked_Student_Import::instance();
        }
    }
}, 20);
