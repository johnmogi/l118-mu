<?php
/**
 * Plugin Name: LearnDash Course Enhancements
 * Description: Enhances LearnDash courses with better video handling and Hebrew translations
 * Version: 1.0.0
 * Author: Lilac Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class LearnDash_Course_Enhancements {
    public function __construct() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }
        
        // Enqueue our scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts() {
        // Only load on course pages
        if (is_singular('sfwd-courses') || is_singular('sfwd-lessons')) {
            wp_enqueue_script(
                'learndash-course-enhancements',
                plugins_url('js/learndash-enhancements.js', __FILE__),
                array('jquery'),
                '1.0.0',
                true
            );
            
            // Pass data to our script
            wp_localize_script('learndash-course-enhancements', 'ldEnhancements', array(
                'isCourse' => is_singular('sfwd-courses'),
                'isLesson' => is_singular('sfwd-lessons'),
                'ajaxurl' => admin_url('admin-ajax.php')
            ));
        }
    }
}

// Initialize the plugin
new LearnDash_Course_Enhancements();
