<?php
/**
 * Debug User Course Access
 * Temporary script to check specific user's course access data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add debug action
add_action('admin_init', function() {
    if (isset($_GET['debug_user_access']) && current_user_can('manage_options')) {
        
        // Find user by display name containing "234as"
        $users = get_users([
            'search' => '*234as*',
            'search_columns' => ['display_name', 'user_login']
        ]);
        
        echo "<h2>🔍 User Course Access Debug</h2>";
        
        if (empty($users)) {
            echo "<p>❌ No users found with '234as' in name</p>";
            
            // Try broader search
            $all_users = get_users(['number' => 20]);
            echo "<h3>Recent Users:</h3><ul>";
            foreach ($all_users as $user) {
                echo "<li>ID: {$user->ID} - {$user->display_name} ({$user->user_login})</li>";
            }
            echo "</ul>";
        } else {
            foreach ($users as $user) {
                echo "<h3>👤 User: {$user->display_name} (ID: {$user->ID})</h3>";
                
                // Get all user meta related to course access
                $user_meta = get_user_meta($user->ID);
                $course_access_meta = [];
                
                foreach ($user_meta as $key => $value) {
                    if (strpos($key, 'course_') === 0 && strpos($key, '_access_expires') !== false) {
                        $course_access_meta[$key] = $value[0];
                    }
                }
                
                if (empty($course_access_meta)) {
                    echo "<p>❌ No course access expiration data found</p>";
                } else {
                    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                    echo "<tr><th>Meta Key</th><th>Timestamp</th><th>Date</th><th>Status</th></tr>";
                    
                    foreach ($course_access_meta as $key => $timestamp) {
                        $course_id = str_replace(['course_', '_access_expires'], '', $key);
                        $date = date('Y-m-d H:i:s', $timestamp);
                        $current_time = current_time('timestamp');
                        $status = $timestamp > $current_time ? '✅ Active' : '❌ Expired';
                        
                        echo "<tr>";
                        echo "<td>{$key}</td>";
                        echo "<td>{$timestamp}</td>";
                        echo "<td>{$date}</td>";
                        echo "<td>{$status}</td>";
                        echo "</tr>";
                        
                        // Test our access function
                        if (function_exists('wc_learndash_user_has_course_access')) {
                            $has_access = wc_learndash_user_has_course_access($user->ID, $course_id);
                            echo "<tr><td colspan='4'>🔧 Function Test: " . ($has_access ? 'HAS ACCESS' : 'NO ACCESS') . "</td></tr>";
                        }
                    }
                    echo "</table>";
                }
                
                // Check LearnDash course enrollment
                if (function_exists('learndash_user_get_enrolled_courses')) {
                    $enrolled_courses = learndash_user_get_enrolled_courses($user->ID);
                    echo "<h4>📚 LearnDash Enrolled Courses:</h4>";
                    if (empty($enrolled_courses)) {
                        echo "<p>No courses enrolled</p>";
                    } else {
                        echo "<ul>";
                        foreach ($enrolled_courses as $course_id) {
                            $course_title = get_the_title($course_id);
                            echo "<li>Course {$course_id}: {$course_title}</li>";
                        }
                        echo "</ul>";
                    }
                }
            }
        }
        
        echo "<hr><p><strong>Current Time:</strong> " . date('Y-m-d H:i:s', current_time('timestamp')) . "</p>";
        echo "<p><a href='" . admin_url('admin.php?page=simple-course-expiration') . "'>← Back to Course Expiration Manager</a></p>";
        
        wp_die();
    }
});
