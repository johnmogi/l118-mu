<?php
/**
 * Teacher Redirect Loader
 * 
 * This file loads the teacher redirect plugin from its subdirectory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the main plugin file
require_once __DIR__ . '/plugins/teacher-redirect/teacher-redirect.php';
