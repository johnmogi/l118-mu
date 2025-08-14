<?php
/**
 * Plugin Name: LearnDash Instructor Quiz Categories Enhanced
 * Description: Enhanced version specifically designed for instructor role quiz category management with proper question population
 * Version: 2.0.0
 * Author: Custom Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LD_INSTRUCTOR_QUIZ_ENHANCED_VERSION', '2.0.0');
define('LD_INSTRUCTOR_QUIZ_ENHANCED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LD_INSTRUCTOR_QUIZ_ENHANCED_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main class
require_once LD_INSTRUCTOR_QUIZ_ENHANCED_PLUGIN_DIR . 'includes/class-instructor-quiz-enhanced.php';

// Initialize the plugin
function ld_instructor_quiz_enhanced_init() {
    // Check if LearnDash is active before initializing
    if (class_exists('SFWD_LMS')) {
        new LD_Instructor_Quiz_Enhanced();
    }
}

// Initialize on init hook
add_action('init', 'ld_instructor_quiz_enhanced_init');
