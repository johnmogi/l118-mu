<?php
/**
 * Plugin Name: LearnDash Instructor Quiz Categories (DISABLED)
 * Description: TEMPORARILY DISABLED - Allows instructors to select question categories for quizzes and auto-populate quizzes with questions from those categories.
 * Version: 1.0.0
 * Author: Custom Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin re-enabled with fixes

// Define plugin constants
define('LD_INSTRUCTOR_QUIZ_CATS_VERSION', '1.0.0');
define('LD_INSTRUCTOR_QUIZ_CATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LD_INSTRUCTOR_QUIZ_CATS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main class
require_once LD_INSTRUCTOR_QUIZ_CATS_PLUGIN_DIR . 'includes/class-ld-instructor-quiz-categories.php';

// Initialize the plugin immediately for MU plugins
// MU plugins don't need to wait for plugins_loaded hook
function ld_instructor_quiz_cats_init() {
    // Check if LearnDash is active before initializing
    if (class_exists('SFWD_LMS')) {
        new LD_Instructor_Quiz_Categories();
    }
}

// For MU plugins, we can initialize immediately or use init hook
add_action('init', 'ld_instructor_quiz_cats_init');
