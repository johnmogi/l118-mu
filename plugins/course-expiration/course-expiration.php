<?php
/**
 * Plugin Name: Course Expiration Manager
 * Description: Unified course expiration management system for LearnDash
 * Version: 1.0.0
 * Author: LILAC Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the unified course expiration manager (main functionality)
require_once __DIR__ . '/unified-course-expiration-manager.php';

// Load additional components if needed
// require_once __DIR__ . '/course-expiration-admin.php';
// require_once __DIR__ . '/simple-course-expiration-manager.php';
// require_once __DIR__ . '/efficient-course-expiration-manager.php';
