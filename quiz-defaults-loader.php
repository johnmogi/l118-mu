<?php
/**
 * Quiz Defaults Loader
 * 
 * This file loads the quiz defaults plugin from its subdirectory
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load the main plugin file
require_once __DIR__ . '/plugins/quiz-defaults/quiz-defaults.php';
