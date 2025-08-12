<?php
/**
 * Plugin Name: Teacher Login Redirect
 * Description: Handles redirects for teacher roles after login
 * Version: 1.0.0
 * Author: Lilac Support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle teacher login redirects
 */
function lilac_teacher_login_redirect($redirect_to, $requested_redirect_to, $user) {
    // Skip if no user or error
    if (!is_a($user, 'WP_User') || is_wp_error($user)) {
        return $redirect_to;
    }
    
    // Check if user has teacher role
    if (in_array('teacher', $user->roles)) {
        // Redirect teachers to their dashboard or specific page
        $teacher_dashboard_url = home_url('/teacher-dashboard/');
        
        // Check if the teacher dashboard page exists
        if (get_page_by_path('teacher-dashboard')) {
            return $teacher_dashboard_url;
        } else {
            // Fallback to admin dashboard if teacher dashboard doesn't exist
            return admin_url();
        }
    }
    
    // For non-teachers, return the original redirect
    return $redirect_to;
}

// Add with high priority to run before other redirects
add_filter('login_redirect', 'lilac_teacher_login_redirect', 1, 3);

// Debug function to check if our mu-plugin is loaded
function lilac_debug_teacher_redirect() {
    if (isset($_GET['debug_teacher_redirect']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-success"><p>Teacher Redirect Plugin is loaded and active!</p></div>';
    }
}
add_action('admin_notices', 'lilac_debug_teacher_redirect');
