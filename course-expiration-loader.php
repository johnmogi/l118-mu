<?php
/**
 * Course Expiration Manager Loader
 * 
 * This file loads the course expiration plugin from its subdirectory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the main course expiration plugin
require_once __DIR__ . '/plugins/course-expiration/course-expiration.php';
