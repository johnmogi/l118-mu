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
    
    // Check if user has any instructor/teacher role
    $instructor_roles = [
        'teacher',
        'wdm_instructor', 
        'instructor',
        'Instructor',
        'stm_lms_instructor',
        'group_leader'
    ];
    
    $user_roles = $user->roles;
    $has_instructor_role = array_intersect($instructor_roles, $user_roles);
    
    error_log("Teacher Login Redirect Debug: User {$user->user_login} (ID: {$user->ID}) has roles: " . implode(', ', $user_roles));
    
    if (!empty($has_instructor_role)) {
        // Redirect teachers to the school teacher dashboard in admin
        $teacher_dashboard_url = admin_url('admin.php?page=school-teacher-dashboard');
        
        error_log("Teacher Login Redirect: Redirecting instructor {$user->user_login} (ID: {$user->ID}) with roles [" . implode(', ', $has_instructor_role) . "] to school dashboard: {$teacher_dashboard_url}");
        
        return $teacher_dashboard_url;
    }
    
    // For non-teachers, return the original redirect
    return $redirect_to;
}

// Add with high priority to run before other redirects
add_filter('login_redirect', 'lilac_teacher_login_redirect', 1, 3);

// Log plugin initialization
error_log('Teacher Redirect Plugin: Loaded and hooks registered');

// Debug function to check if our mu-plugin is loaded
function lilac_debug_teacher_redirect() {
    if (isset($_GET['debug_teacher_redirect']) && current_user_can('manage_options')) {
        echo '<div class="notice notice-success"><p>Teacher Redirect Plugin is loaded and active!</p></div>';
    }
}
add_action('admin_notices', 'lilac_debug_teacher_redirect');

// Also add template_redirect hook to catch users already logged in
add_action('template_redirect', 'lilac_check_teacher_on_course_page');

/**
 * Check if a teacher is accessing a course page and redirect them
 */
function lilac_check_teacher_on_course_page() {
    // Only check on course pages
    if (!is_singular('sfwd-courses')) {
        return;
    }
    
    // Only for logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    $user = wp_get_current_user();
    
    // Check if user has any instructor/teacher role
    $instructor_roles = [
        'teacher',
        'wdm_instructor', 
        'instructor',
        'Instructor',
        'stm_lms_instructor',
        'group_leader'
    ];
    
    $user_roles = $user->roles;
    $has_instructor_role = array_intersect($instructor_roles, $user_roles);
    
    error_log("Teacher Course Access Check: User {$user->user_login} (ID: {$user->ID}) has roles: " . implode(', ', $user_roles));
    
    if (!empty($has_instructor_role)) {
        $teacher_dashboard_url = admin_url('admin.php?page=school-teacher-dashboard');
        
        error_log("Teacher Course Access: Redirecting instructor {$user->user_login} from course page to school dashboard: {$teacher_dashboard_url}");
        
        wp_redirect($teacher_dashboard_url);
        exit;
    }
}
