<?php
/**
 * Efficient Course Expiration Manager
 * Integrates directly with School Manager students table for scalable management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Efficient_Course_Expiration_Manager {
    
    public function __construct() {
        // Add bulk actions to School Manager students table
        add_filter('bulk_actions-school_manager_students', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-school_manager_students', [$this, 'handle_bulk_actions'], 10, 3);
        
        // Add AJAX handlers
        add_action('wp_ajax_quick_set_course_expiry', [$this, 'ajax_quick_set_course_expiry']);
        add_action('wp_ajax_get_user_course_data', [$this, 'ajax_get_user_course_data']);
        
        // Add admin scripts to School Manager students page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Add admin notice for instructions
        add_action('admin_notices', [$this, 'show_admin_notice']);
    }
    
    /**
     * Add bulk actions to School Manager students table
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['set_course_expiry'] = 'Set Course Expiration';
        $bulk_actions['extend_course_expiry'] = 'Extend Course Access (+30 days)';
        $bulk_actions['remove_course_expiry'] = 'Remove Course Expiration';
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if (!in_array($doaction, ['set_course_expiry', 'extend_course_expiry', 'remove_course_expiry'])) {
            return $redirect_to;
        }
        
        if (!current_user_can('manage_options')) {
            return $redirect_to;
        }
        
        $student_ids = isset($_REQUEST['student_id']) ? array_map('intval', $_REQUEST['student_id']) : [];
        
        if (empty($student_ids)) {
            return add_query_arg('bulk_error', 'no_students', $redirect_to);
        }
        
        $processed = 0;
        
        switch ($doaction) {
            case 'set_course_expiry':
                // Redirect to form for setting specific expiration
                return add_query_arg([
                    'action' => 'bulk_set_expiry',
                    'student_ids' => implode(',', $student_ids)
                ], admin_url('admin.php?page=school-manager-students'));
                
            case 'extend_course_expiry':
                foreach ($student_ids as $user_id) {
                    $this->extend_user_course_access($user_id, 30);
                    $processed++;
                }
                break;
                
            case 'remove_course_expiry':
                foreach ($student_ids as $user_id) {
                    $this->remove_user_course_access($user_id);
                    $processed++;
                }
                break;
        }
        
        return add_query_arg('bulk_processed', $processed, $redirect_to);
    }
    
    /**
     * Extend user course access by specified days
     */
    private function extend_user_course_access($user_id, $days) {
        $user_meta = get_user_meta($user_id);
        
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $current_expiry = $value[0];
                
                if ($current_expiry > 0) {
                    $new_expiry = $current_expiry + ($days * DAY_IN_SECONDS);
                    update_user_meta($user_id, $key, $new_expiry);
                    
                    // Update LearnDash if available
                    if (function_exists('ld_update_course_access')) {
                        ld_update_course_access($user_id, $matches[1], false, $new_expiry);
                    }
                }
            }
        }
    }
    
    /**
     * Remove user course access
     */
    private function remove_user_course_access($user_id) {
        $user_meta = get_user_meta($user_id);
        
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                delete_user_meta($user_id, $key);
                
                // Update LearnDash if available
                if (function_exists('ld_update_course_access')) {
                    ld_update_course_access($user_id, $matches[1], true); // Remove access
                }
            }
        }
    }
    
    /**
     * AJAX handler for quick set course expiry
     */
    public function ajax_quick_set_course_expiry() {
        if (!wp_verify_nonce($_POST['nonce'], 'quick_set_course_expiry')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $expiry_type = sanitize_text_field($_POST['expiry_type']);
        $custom_date = sanitize_text_field($_POST['custom_date']);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        $expiry_timestamp = 0;
        
        switch ($expiry_type) {
            case '1_week':
                $expiry_timestamp = current_time('timestamp') + (7 * DAY_IN_SECONDS);
                break;
            case '1_month':
                $expiry_timestamp = current_time('timestamp') + (30 * DAY_IN_SECONDS);
                break;
            case '3_months':
                $expiry_timestamp = current_time('timestamp') + (90 * DAY_IN_SECONDS);
                break;
            case '1_year':
                $expiry_timestamp = current_time('timestamp') + (365 * DAY_IN_SECONDS);
                break;
            case 'custom':
                if ($custom_date) {
                    $expiry_timestamp = strtotime($custom_date . ' 23:59:59');
                }
                break;
            case 'permanent':
                $expiry_timestamp = 0;
                break;
        }
        
        if ($expiry_type !== 'permanent' && !$expiry_timestamp) {
            wp_send_json_error('Invalid expiration date');
        }
        
        // Update user meta
        $meta_key = 'course_' . $course_id . '_access_expires';
        update_user_meta($user_id, $meta_key, $expiry_timestamp);
        
        // Update LearnDash if available
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false, $expiry_timestamp);
        }
        
        wp_send_json_success([
            'message' => 'Course expiration updated successfully',
            'new_expiry' => $expiry_timestamp ? date('d/m/Y', $expiry_timestamp) : 'Permanent'
        ]);
    }
    
    /**
     * AJAX handler to get user course data
     */
    public function ajax_get_user_course_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_user_course_data')) {
            wp_send_json_error('Security check failed');
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user_meta = get_user_meta($user_id);
        $courses = [];
        
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $course_id = $matches[1];
                $expires_timestamp = $value[0];
                
                $courses[] = [
                    'course_id' => $course_id,
                    'course_title' => get_the_title($course_id),
                    'expires' => $expires_timestamp,
                    'expires_formatted' => $expires_timestamp ? date('d/m/Y', $expires_timestamp) : 'Permanent'
                ];
            }
        }
        
        wp_send_json_success($courses);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'school-manager_page_school-manager-students') {
            return;
        }
        
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Add quick action buttons to each student row
            $(".student-row").each(function() {
                var studentId = $(this).attr("id").replace("student-", "");
                var quickActions = "<div class=\"course-expiry-quick-actions\" style=\"margin-top: 5px;\">" +
                    "<button type=\"button\" class=\"button button-small quick-set-expiry\" data-user-id=\"" + studentId + "\">⚡ Quick Set Expiry</button> " +
                    "<button type=\"button\" class=\"button button-small view-courses\" data-user-id=\"" + studentId + "\">👁️ View Courses</button>" +
                    "</div>";
                $(this).append(quickActions);
            });
            
            // Handle quick set expiry
            $(document).on("click", ".quick-set-expiry", function() {
                var userId = $(this).data("user-id");
                showQuickExpiryDialog(userId);
            });
            
            // Handle view courses
            $(document).on("click", ".view-courses", function() {
                var userId = $(this).data("user-id");
                showUserCoursesDialog(userId);
            });
            
            // Create dialog HTML
            $("body").append(`
                <div id="quick-expiry-dialog" title="Set Course Expiration" style="display: none;">
                    <form id="quick-expiry-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="course_id">Course ID:</label></th>
                                <td><input type="number" id="course_id" name="course_id" value="123" required style="width: 100%;"></td>
                            </tr>
                            <tr>
                                <th><label for="expiry_type">Expiration:</label></th>
                                <td>
                                    <select id="expiry_type" name="expiry_type" style="width: 100%;">
                                        <option value="1_week">1 Week from now</option>
                                        <option value="1_month" selected>1 Month from now</option>
                                        <option value="3_months">3 Months from now</option>
                                        <option value="1_year">1 Year from now</option>
                                        <option value="custom">Custom Date</option>
                                        <option value="permanent">Permanent Access</option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="custom_date_row" style="display: none;">
                                <th><label for="custom_date">Custom Date:</label></th>
                                <td><input type="date" id="custom_date" name="custom_date" style="width: 100%;"></td>
                            </tr>
                        </table>
                    </form>
                </div>
                
                <div id="user-courses-dialog" title="User Course Access" style="display: none;">
                    <div id="user-courses-content">Loading...</div>
                </div>
            `);
            
            // Handle expiry type change
            $(document).on("change", "#expiry_type", function() {
                if ($(this).val() === "custom") {
                    $("#custom_date_row").show();
                } else {
                    $("#custom_date_row").hide();
                }
            });
        });
        
        function showQuickExpiryDialog(userId) {
            jQuery("#quick-expiry-dialog").dialog({
                modal: true,
                width: 500,
                buttons: {
                    "Set Expiration": function() {
                        var dialog = jQuery(this);
                        var formData = {
                            action: "quick_set_course_expiry",
                            user_id: userId,
                            course_id: jQuery("#course_id").val(),
                            expiry_type: jQuery("#expiry_type").val(),
                            custom_date: jQuery("#custom_date").val(),
                            nonce: "' . wp_create_nonce('quick_set_course_expiry') . '"
                        };
                        
                        jQuery.post(ajaxurl, formData, function(response) {
                            if (response.success) {
                                alert("Course expiration set successfully!");
                                location.reload();
                            } else {
                                alert("Error: " + response.data);
                            }
                            dialog.dialog("close");
                        });
                    },
                    "Cancel": function() {
                        jQuery(this).dialog("close");
                    }
                }
            });
        }
        
        function showUserCoursesDialog(userId) {
            jQuery("#user-courses-content").html("Loading...");
            
            jQuery("#user-courses-dialog").dialog({
                modal: true,
                width: 600,
                height: 400
            });
            
            jQuery.post(ajaxurl, {
                action: "get_user_course_data",
                user_id: userId,
                nonce: "' . wp_create_nonce('get_user_course_data') . '"
            }, function(response) {
                if (response.success) {
                    var html = "<h3>Current Course Access:</h3>";
                    if (response.data.length === 0) {
                        html += "<p>No course access found for this user.</p>";
                    } else {
                        html += "<ul>";
                        response.data.forEach(function(course) {
                            html += "<li><strong>Course " + course.course_id + "</strong> (" + course.course_title + ")<br>";
                            html += "<small>Expires: " + course.expires_formatted + "</small></li>";
                        });
                        html += "</ul>";
                    }
                    jQuery("#user-courses-content").html(html);
                } else {
                    jQuery("#user-courses-content").html("Error loading course data: " + response.data);
                }
            });
        }
        ');
    }
    
    /**
     * Show admin notice with instructions
     */
    public function show_admin_notice() {
        $screen = get_current_screen();
        if ($screen->id !== 'school-manager_page_school-manager-students') {
            return;
        }
        
        echo '<div class="notice notice-info"><p>';
        echo '<strong>Course Expiration Management:</strong> ';
        echo 'Use the "⚡ Quick Set Expiry" buttons on each student row, or select multiple students and use bulk actions: ';
        echo '"Set Course Expiration", "Extend Course Access (+30 days)", or "Remove Course Expiration".';
        echo '</p></div>';
    }
}

// Initialize the efficient course expiration manager
new Efficient_Course_Expiration_Manager();
