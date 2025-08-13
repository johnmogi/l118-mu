<?php
/**
 * Plugin Name: LearnDash Quiz Defaults
 * Description: Sets default values for new LearnDash quizzes including Media Sidebar, Enforce Hint, and other custom settings
 * Version: 1.0.0
 * Author: LILAC Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Lilac_Quiz_Defaults {
    
    /**
     * Default settings for new quizzes
     */
    const DEFAULT_SETTINGS = [
        // Custom Lilac settings
        '_ld_quiz_toggle_sidebar' => '1',  // Enable Media Sidebar by default
        '_ld_quiz_enforce_hint' => '1',    // Enable Enforce Hint by default
        
        // LearnDash quiz settings that should be set by default
        '_sfwd-quiz' => [
            'sfwd-quiz_quiz_pro' => '', // Will be set when quiz is created
            'sfwd-quiz_course' => '',
            'sfwd-quiz_lesson' => '',
            // Add other default LearnDash settings here
        ]
    ];
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Set defaults when a new quiz is created
        add_action('wp_insert_post', [__CLASS__, 'set_quiz_defaults'], 10, 3);
        
        // Also set defaults when quiz is first saved (backup method)
        add_action('save_post_sfwd-quiz', [__CLASS__, 'ensure_quiz_defaults'], 5);
        
        // Add admin notice for successful default setting
        add_action('admin_notices', [__CLASS__, 'show_defaults_notice']);
        
        // Debug logging
        error_log('Lilac Quiz Defaults: Plugin initialized');
    }
    
    /**
     * Set default values when a new quiz is created
     */
    public static function set_quiz_defaults($post_id, $post, $update) {
        // Only process sfwd-quiz post type
        if ($post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        // Only set defaults for new posts (not updates)
        if ($update) {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        error_log("Quiz Defaults: Setting defaults for new quiz ID: {$post_id}");
        
        // Set custom Lilac defaults
        foreach (self::DEFAULT_SETTINGS as $meta_key => $meta_value) {
            if ($meta_key === '_sfwd-quiz') {
                // Handle LearnDash settings array separately
                continue;
            }
            
            // Only set if not already set
            if (!get_post_meta($post_id, $meta_key, true)) {
                update_post_meta($post_id, $meta_key, $meta_value);
                error_log("Quiz Defaults: Set {$meta_key} = {$meta_value} for quiz {$post_id}");
            }
        }
        
        // Set LearnDash quiz settings
        self::set_learndash_defaults($post_id);
        
        // Store flag that defaults were set
        update_post_meta($post_id, '_lilac_quiz_defaults_set', current_time('timestamp'));
    }
    
    /**
     * Ensure defaults are set (backup method)
     */
    public static function ensure_quiz_defaults($post_id) {
        // Skip if defaults already set
        if (get_post_meta($post_id, '_lilac_quiz_defaults_set', true)) {
            return;
        }
        
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        error_log("Quiz Defaults: Ensuring defaults for quiz ID: {$post_id}");
        
        // Set defaults
        foreach (self::DEFAULT_SETTINGS as $meta_key => $meta_value) {
            if ($meta_key === '_sfwd-quiz') {
                continue;
            }
            
            if (!get_post_meta($post_id, $meta_key, true)) {
                update_post_meta($post_id, $meta_key, $meta_value);
                error_log("Quiz Defaults: Ensured {$meta_key} = {$meta_value} for quiz {$post_id}");
            }
        }
        
        // Set LearnDash defaults
        self::set_learndash_defaults($post_id);
        
        // Mark as completed
        update_post_meta($post_id, '_lilac_quiz_defaults_set', current_time('timestamp'));
    }
    
    /**
     * Set LearnDash specific defaults
     */
    private static function set_learndash_defaults($post_id) {
        // Get existing LearnDash settings
        $quiz_settings = get_post_meta($post_id, '_sfwd-quiz', true);
        if (!is_array($quiz_settings)) {
            $quiz_settings = [];
        }
        
        // Set default LearnDash settings if not already set
        $learndash_defaults = [
            'sfwd-quiz_quiz_pro' => '', // Will be populated by LearnDash
            'sfwd-quiz_course' => '',
            'sfwd-quiz_lesson' => '',
            // Add more LearnDash defaults here as needed
        ];
        
        $updated = false;
        foreach ($learndash_defaults as $key => $default_value) {
            if (!isset($quiz_settings[$key]) || empty($quiz_settings[$key])) {
                $quiz_settings[$key] = $default_value;
                $updated = true;
            }
        }
        
        if ($updated) {
            update_post_meta($post_id, '_sfwd-quiz', $quiz_settings);
            error_log("Quiz Defaults: Updated LearnDash settings for quiz {$post_id}");
        }
    }
    
    /**
     * Show admin notice when defaults are set
     */
    public static function show_defaults_notice() {
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') {
            return;
        }
        
        $post_id = intval($_GET['post']);
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        // Check if defaults were recently set (within last 10 seconds)
        $defaults_set = get_post_meta($post_id, '_lilac_quiz_defaults_set', true);
        if ($defaults_set && (current_time('timestamp') - $defaults_set) < 10) {
            echo '<div class="notice notice-success is-dismissible">
                <p><strong>Quiz Defaults Applied:</strong> Media Sidebar and Enforce Hint have been enabled by default for this quiz.</p>
            </div>';
        }
    }
    
    /**
     * Get current default settings (for admin interface)
     */
    public static function get_default_settings() {
        return self::DEFAULT_SETTINGS;
    }
    
    /**
     * Update default settings (for future admin interface)
     */
    public static function update_default_settings($new_settings) {
        // This could be expanded to allow admin customization of defaults
        // For now, defaults are hardcoded in the class
        return false;
    }
}

// Initialize the plugin
Lilac_Quiz_Defaults::init();

/**
 * Helper function to check if quiz has defaults set
 */
function lilac_quiz_has_defaults($quiz_id) {
    return (bool) get_post_meta($quiz_id, '_lilac_quiz_defaults_set', true);
}

/**
 * Helper function to manually apply defaults to existing quiz
 */
function lilac_apply_quiz_defaults($quiz_id) {
    if (get_post_type($quiz_id) !== 'sfwd-quiz') {
        return false;
    }
    
    // Remove the flag so defaults will be reapplied
    delete_post_meta($quiz_id, '_lilac_quiz_defaults_set');
    
    // Trigger the defaults
    Lilac_Quiz_Defaults::ensure_quiz_defaults($quiz_id);
    
    return true;
}
