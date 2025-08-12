<?php
/**
 * Plugin Name: WooCommerce LearnDash Access Manager
 * Description: Adds custom access duration fields to WooCommerce products and automatically manages LearnDash course access
 * Version: 1.0.0
 * Author: LILAC Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_LearnDash_Access_Manager {
    
    private $access_options = [
        'paused_2weeks' => 'מנוי מושהה (גישה ל-2 שבועות לאחר הפעלה)',
        'trial_2weeks' => 'ניסיון 2 שבועות',
        'access_1month' => 'גישה לחודש',
        'access_1year' => 'גישה לשנה'
    ];
    
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        
        // Add AJAX handlers for course expiry editing
        add_action('wp_ajax_update_course_expiry', [$this, 'ajax_update_course_expiry']);
        add_action('wp_ajax_nopriv_update_course_expiry', [$this, 'ajax_update_course_expiry']);
        add_action('wp_ajax_quick_set_course_expiry', array($this, 'ajax_quick_set_course_expiry'));
        add_action('wp_ajax_get_user_course_data', array($this, 'ajax_get_user_course_data'));
        add_action('wp_ajax_toggle_course_access', array($this, 'ajax_toggle_course_access'));
        add_action('wp_ajax_get_simple_course_status', array($this, 'ajax_get_simple_course_status'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // Add product fields
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_custom_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_fields']);
        
        // Handle order completion
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completion']);
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_completion']);
        
        // Add custom columns to product list
        add_filter('manage_edit-product_columns', [$this, 'add_product_columns']);
        add_action('manage_product_posts_custom_column', [$this, 'show_product_columns'], 10, 2);
        
        // Add order meta display
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_access_info']);
        
        // Add user profile fields
        add_action('show_user_profile', [$this, 'show_user_access_fields']);
        add_action('edit_user_profile', [$this, 'show_user_access_fields']);
        
        // Debug action to verify plugin is loading
        add_action('admin_init', [$this, 'debug_plugin_loaded']);
    }
    
    public function debug_plugin_loaded() {
        if (isset($_GET['debug_wc_learndash']) && current_user_can('manage_options')) {
            error_log('WC LearnDash Access Manager: Plugin loaded successfully');
            wp_die('Plugin is loaded! Check error log for confirmation.');
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>WooCommerce LearnDash Access Manager requires WooCommerce to be installed and active.</p></div>';
    }
    
    /**
     * Add custom fields to product edit page
     */
    public function add_custom_fields() {
        global $post;
        
        echo '<div class="options_group wc-learndash-access-manager">';
        echo '<h4 style="color: #2271b1; margin: 15px 0 10px 0; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">🎓 הגדרות גישת LearnDash</h4>';
        
        // Access Duration Type
        woocommerce_wp_select([
            'id' => '_learndash_access_duration',
            'label' => 'משך הגישה',
            'description' => 'בחר כמה זמן ללקוחות תהיה גישה לקורסים',
            'desc_tip' => true,
            'options' => ['' => 'בחר משך זמן...'] + $this->access_options,
            'wrapper_class' => 'form-field-wide'
        ]);
        
        // Custom End Date (optional override)
        woocommerce_wp_text_input([
            'id' => '_learndash_custom_end_date',
            'label' => 'תאריך סיום מותאם אישית (אופציונלי)',
            'description' => 'קבע תאריך סיום ספציפי (שששש-חח-יי). זה יעקוף את הגדרת המשך זמן למעלה.',
            'desc_tip' => true,
            'type' => 'date',
            'wrapper_class' => 'form-field-wide'
        ]);
        
        // Associated LearnDash Courses
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        if (!empty($courses)) {
            echo '<p class="form-field form-field-wide">';
            echo '<label for="_learndash_courses"><strong>קורסי LearnDash משויכים</strong></label>';
            echo '<select id="_learndash_courses" name="_learndash_courses[]" multiple="multiple" style="width: 100%; height: 120px;">';
            
            $selected_courses = get_post_meta($post->ID, '_learndash_courses', true);
            $selected_courses = is_array($selected_courses) ? $selected_courses : [];
            
            foreach ($courses as $course) {
                $selected = in_array($course->ID, $selected_courses) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($course->ID) . '" ' . $selected . '>' . esc_html($course->post_title) . '</option>';
            }
            echo '</select>';
            echo '<span class="description">החזק Ctrl/Cmd לבחירת קורסים מרובים. לקוחות יקבלו גישה לכל הקורסים הנבחרים.</span>';
            echo '</p>';
        } else {
            echo '<p class="form-field form-field-wide">';
            echo '<label><strong>לא נמצאו קורסי LearnDash</strong></label>';
            echo '<span class="description">אנא צור קורסי LearnDash תחילה, ולאחר מכן חזור להגדיר גישה.</span>';
            echo '</p>';
        }
        
        // Display current settings preview
        $current_duration = get_post_meta($post->ID, '_learndash_access_duration', true);
        $current_custom_date = get_post_meta($post->ID, '_learndash_custom_end_date', true);
        $current_courses = get_post_meta($post->ID, '_learndash_courses', true);
        
        if ($current_duration || $current_custom_date || $current_courses) {
            echo '<div class="wc-learndash-preview" style="background: #fff; border: 1px solid #c3c4c7; padding: 12px; margin: 10px 0; border-radius: 4px;">';
            echo '<h4 style="margin: 0 0 8px 0; color: #1d2327;">תצוגה מקדימה של הגדרות נוכחיות:</h4>';
            
            if ($current_duration) {
                echo '<p><strong>משך זמן:</strong> ' . esc_html($this->access_options[$current_duration] ?? $current_duration) . '</p>';
            }
            
            if ($current_custom_date) {
                echo '<p><strong>תאריך סיום מותאם אישית:</strong> ' . esc_html($current_custom_date) . ' <em>(עוקף את משך הזמן)</em></p>';
            }
            
            if ($current_courses && is_array($current_courses)) {
                echo '<p><strong>קורסים (' . count($current_courses) . '):</strong><br>';
                foreach ($current_courses as $course_id) {
                    $course_title = get_the_title($course_id);
                    echo '• ' . esc_html($course_title) . '<br>';
                }
                echo '</p>';
            }
            
            if (!$current_duration && !$current_custom_date) {
                echo '<p style="color: #d63638;"><strong>⚠️ אזהרה:</strong> לא הוגדר משך זמן גישה - לקוחות יקבלו גישה קבועה!</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add inline CSS for better styling
        echo '<style>
        .wc-learndash-access-manager {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
        }
        .wc-learndash-access-manager .form-field-wide {
            width: 100%;
        }
        .wc-learndash-access-manager select[multiple] {
            min-height: 120px;
        }
        </style>';
    }
    
    /**
     * Save custom fields
     */
    public function save_custom_fields($post_id) {
        // Verify nonce for security
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $duration = sanitize_text_field($_POST['_learndash_access_duration'] ?? '');
        $custom_date = sanitize_text_field($_POST['_learndash_custom_end_date'] ?? '');
        $courses = isset($_POST['_learndash_courses']) ? array_map('intval', $_POST['_learndash_courses']) : [];
        
        update_post_meta($post_id, '_learndash_access_duration', $duration);
        update_post_meta($post_id, '_learndash_custom_end_date', $custom_date);
        update_post_meta($post_id, '_learndash_courses', $courses);
        
        // Log the save action
        error_log("WC LearnDash: Saved product {$post_id} - Duration: {$duration}, Custom Date: {$custom_date}, Courses: " . implode(',', $courses));
    }
    
    /**
     * Handle order completion
     */
    public function handle_order_completion($order_id) {
        $this->process_learndash_access($order_id, 'order_completed');
    }
    
    /**
     * Handle payment completion
     */
    public function handle_payment_completion($order_id) {
        $this->process_learndash_access($order_id, 'payment_completed');
    }
    
    /**
     * Process LearnDash access for completed orders
     */
    private function process_learndash_access($order_id, $trigger = '') {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("WC LearnDash: Order {$order_id} not found");
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            error_log("WC LearnDash: No user ID found for order {$order_id}");
            return;
        }
        
        $processed_courses = [];
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $duration = get_post_meta($product_id, '_learndash_access_duration', true);
            $custom_date = get_post_meta($product_id, '_learndash_custom_end_date', true);
            $courses = get_post_meta($product_id, '_learndash_courses', true);
            
            if ((!$duration && !$custom_date) || !$courses || !is_array($courses)) {
                continue;
            }
            
            // Calculate end date
            $end_date = $this->calculate_end_date($duration, $custom_date);
            
            // Enroll user in courses
            foreach ($courses as $course_id) {
                if (!in_array($course_id, $processed_courses)) {
                    $this->enroll_user_in_course($user_id, $course_id, $end_date, $order_id, $trigger);
                    $processed_courses[] = $course_id;
                }
            }
        }
        
        if (!empty($processed_courses)) {
            $order->add_order_note("LearnDash Access Manager: Processed " . count($processed_courses) . " course enrollments via {$trigger}");
        }
    }
    
    /**
     * Calculate end date based on duration or custom date
     */
    private function calculate_end_date($duration, $custom_date = '') {
        if ($custom_date) {
            return strtotime($custom_date . ' 23:59:59');
        }
        
        $current_time = current_time('timestamp');
        
        switch ($duration) {
            case 'paused_2weeks':
            case 'trial_2weeks':
                return strtotime('+2 weeks', $current_time);
            case 'access_1month':
                return strtotime('+1 month', $current_time);
            case 'access_1year':
                return strtotime('+1 year', $current_time);
            default:
                return 0; // No expiration
        }
    }
    
    /**
     * Enroll user in LearnDash course with access control
     */
    private function enroll_user_in_course($user_id, $course_id, $end_date, $order_id, $trigger = '') {
        // Use LearnDash function to enroll user
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false);
            error_log("WC LearnDash: Used ld_update_course_access for user {$user_id}, course {$course_id}");
        } else {
            // Fallback: manually add user meta
            $enrolled_courses = get_user_meta($user_id, '_sfwd-course_progress', true);
            if (!is_array($enrolled_courses)) {
                $enrolled_courses = [];
            }
            if (!isset($enrolled_courses[$course_id])) {
                $enrolled_courses[$course_id] = ['completed' => 0, 'total' => 0];
                update_user_meta($user_id, '_sfwd-course_progress', $enrolled_courses);
            }
            error_log("WC LearnDash: Used fallback enrollment for user {$user_id}, course {$course_id}");
        }
        
        // Set custom access metadata
        $access_key = "course_{$course_id}_access_from";
        $expire_key = "course_{$course_id}_access_expires";
        $order_key = "course_{$course_id}_order_id";
        
        update_user_meta($user_id, $access_key, current_time('timestamp'));
        update_user_meta($user_id, $order_key, $order_id);
        
        if ($end_date > 0) {
            update_user_meta($user_id, $expire_key, $end_date);
        } else {
            delete_user_meta($user_id, $expire_key); // Remove expiration if set to permanent
        }
        
        // Log enrollment
        $course_title = get_the_title($course_id);
        $expire_text = $end_date > 0 ? date('Y-m-d H:i:s', $end_date) : 'No expiration';
        error_log("WC LearnDash: Enrolled user {$user_id} in course '{$course_title}' (ID: {$course_id}), expires: {$expire_text}, trigger: {$trigger}");
        
        // Add order note
        $order = wc_get_order($order_id);
        if ($order) {
            $expire_note = $end_date > 0 ? ' (expires: ' . date('Y-m-d', $end_date) . ')' : ' (no expiration)';
            $order->add_order_note("LearnDash: Enrolled in '{$course_title}'{$expire_note}");
        }
    }
    
    /**
     * Add admin scripts for enhanced UI
     */
    public function admin_scripts($hook) {
        // Add CSS for product list column styling
        if ($hook === 'edit.php' && get_current_screen()->post_type === 'product') {
            wp_add_inline_style('wp-admin', '
                .wp-list-table .column-learndash_access {
                    width: 180px;
                    min-width: 160px;
                }
                .wp-list-table .column-learndash_access strong {
                    color: #2271b1;
                    font-weight: 600;
                    display: block;
                    margin-bottom: 2px;
                }
                .wp-list-table .column-learndash_access small {
                    color: #646970;
                    font-size: 12px;
                    line-height: 1.4;
                }
            ');
        }
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'product') {
            wp_enqueue_script('jquery');
            
            // Add inline JavaScript for better UX
            wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                // Duration change handler
                $("#_learndash_access_duration").on("change", function() {
                    var duration = $(this).val();
                    var preview = $(".wc-learndash-preview");
                    
                    if (duration) {
                        var info = "";
                        switch(duration) {
                            case "paused_2weeks":
                                info = "⏱️ הגישה מתחילה כאשר המנוי מופעל ונמשכת 2 שבועות";
                                break;
                            case "trial_2weeks":
                                info = "🆓 גישת ניסיון ל-2 שבועות מתאריך הרכישה";
                                break;
                            case "access_1month":
                                info = "📅 גישה לחודש אחד מתאריך הרכישה";
                                break;
                            case "access_1year":
                                info = "🗓️ גישה לשנה אחת מתאריך הרכישה";
                                break;
                        }
                        
                        if (!$(".wc-learndash-duration-info").length) {
                            $(this).after("<div class=\"wc-learndash-duration-info\" style=\"background: #e7f3ff; border-left: 4px solid #2271b1; padding: 8px 12px; margin: 8px 0; font-size: 12px;\">" + info + "</div>");
                        } else {
                            $(".wc-learndash-duration-info").html(info);
                        }
                    } else {
                        $(".wc-learndash-duration-info").remove();
                    }
                });
                
                // Custom date change handler
                $("#_learndash_custom_end_date").on("change", function() {
                    var customDate = $(this).val();
                    if (customDate) {
                        if (!$(".wc-learndash-custom-warning").length) {
                            $(this).after("<div class=\"wc-learndash-custom-warning\" style=\"background: #fff3cd; border-left: 4px solid #ffc107; padding: 8px 12px; margin: 8px 0; font-size: 12px;\">⚠️ תאריך סיום מותאם אישית יעקוף את הגדרת המשך הזמן למעלה.</div>");
                        }
                    } else {
                        $(".wc-learndash-custom-warning").remove();
                    }
                });
                
                // Initialize on page load
                $("#_learndash_access_duration").trigger("change");
                $("#_learndash_custom_end_date").trigger("change");
                
                console.log("WC LearnDash Access Manager: Admin scripts loaded");
            });
            ');
        }
    }
    
    /**
     * AJAX handler for updating course expiration dates
     */
    public function ajax_update_course_expiry() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_course_expiry')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_users')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get and validate parameters
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $new_date = isset($_POST['new_date']) ? sanitize_text_field($_POST['new_date']) : '';
        
        if (!$user_id || !$new_date) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $new_date);
        if (!$date_obj) {
            wp_send_json_error('Invalid date format');
        }
        
        // Convert to timestamp (end of day)
        $new_timestamp = strtotime($new_date . ' 23:59:59');
        if (!$new_timestamp) {
            wp_send_json_error('Invalid date');
        }
        
        // Get user's current course access data
        $user_meta = get_user_meta($user_id);
        $updated_courses = [];
        
        // Update all course expiration dates for this user
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $course_id = $matches[1];
                $old_timestamp = $value[0];
                
                // Update the course access expiration
                update_user_meta($user_id, $key, $new_timestamp);
                
                // Also update LearnDash course access if function exists
                if (function_exists('ld_update_course_access')) {
                    ld_update_course_access($user_id, $course_id, false, $new_timestamp);
                }
                
                $updated_courses[] = [
                    'course_id' => $course_id,
                    'course_title' => get_the_title($course_id),
                    'old_date' => date('d/m/Y', $old_timestamp),
                    'new_date' => date('d/m/Y', $new_timestamp)
                ];
            }
        }
        
        if (empty($updated_courses)) {
            wp_send_json_error('No course access found for this user');
        }
        
        // Log the update for debugging
        error_log(sprintf(
            'WC LearnDash Access Manager: Updated course expiration for user %d. Courses: %s',
            $user_id,
            json_encode($updated_courses)
        ));
        
        wp_send_json_success([
            'message' => sprintf('Updated %d course(s) expiration date', count($updated_courses)),
            'updated_courses' => $updated_courses,
            'new_date' => date('d/m/Y', $new_timestamp)
        ]);
    }
    
    /**
     * AJAX handler for quick set course expiry
     */
    public function ajax_quick_set_course_expiry() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quick_set_course_expiry')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_users')) {
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
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_user_course_data')) {
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
     * AJAX handler for simple toggle course access
     */
    public function ajax_toggle_course_access() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'toggle_course_access')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_users')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $course_id = 123; // Default course ID - can be made configurable
        $meta_key = 'course_' . $course_id . '_access_expires';
        
        // Check current access status
        $current_expiry = get_user_meta($user_id, $meta_key, true);
        $has_active_access = !$current_expiry || $current_expiry == 0 || $current_expiry > current_time('timestamp');
        
        if ($has_active_access) {
            // Deactivate: Set to expired (yesterday)
            $yesterday = current_time('timestamp') - DAY_IN_SECONDS;
            update_user_meta($user_id, $meta_key, $yesterday);
            
            if (function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $course_id, true); // Remove access
            }
            
            wp_send_json_success([
                'message' => 'Course access deactivated',
                'new_status' => 'inactive'
            ]);
        } else {
            // Activate: Set to permanent (0 = no expiration)
            update_user_meta($user_id, $meta_key, 0);
            
            if (function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $course_id, false, 0); // Grant permanent access
            }
            
            wp_send_json_success([
                'message' => 'Course access activated',
                'new_status' => 'active'
            ]);
        }
    }
    
    /**
     * AJAX handler to get simple course status
     */
    public function ajax_get_simple_course_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_simple_course_status')) {
            wp_send_json_error('Security check failed');
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user_meta = get_user_meta($user_id);
        $has_active_access = false;
        $course_count = 0;
        
        // Check for course access
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $course_count++;
                $expires_timestamp = $value[0];
                
                if (!$expires_timestamp || $expires_timestamp == 0 || $expires_timestamp > current_time('timestamp')) {
                    $has_active_access = true;
                    break;
                }
            }
        }
        
        // Check LearnDash course enrollment as fallback
        if (!$has_active_access && function_exists('learndash_user_get_enrolled_courses')) {
            $enrolled_courses = learndash_user_get_enrolled_courses($user_id);
            if (!empty($enrolled_courses)) {
                $has_active_access = true;
                $course_count = count($enrolled_courses);
            }
        }
        
        wp_send_json_success([
            'has_access' => $has_active_access,
            'course_count' => $course_count,
            'status' => $has_active_access ? 'active' : 'inactive'
        ]);
    }
    
    /**
     * Add custom columns to product list
     */
    public function add_product_columns($columns) {
        $columns['learndash_access'] = 'גישת LearnDash';
        return $columns;
    }
    
    /**
     * Show custom columns content
     */
    public function show_product_columns($column, $post_id) {
        if ($column === 'learndash_access') {
            $duration = get_post_meta($post_id, '_learndash_access_duration', true);
            $courses = get_post_meta($post_id, '_learndash_courses', true);
            
            if ($duration) {
                echo '<strong style="color: #2271b1;">' . esc_html($this->access_options[$duration] ?? $duration) . '</strong><br>';
            }
            
            if ($courses && is_array($courses)) {
                echo '<small style="color: #646970; font-size: 12px;">' . count($courses) . ' קורס(ים) מוקצה</small>';
            } else {
                echo '<small style="color: #646970; font-size: 12px;">לא הוגדרה גישה</small>';
            }
        }
    }
    
    /**
     * Display access info in order admin
     */
    public function display_order_access_info($order) {
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        echo '<div class="address">';
        echo '<p><strong>' . __('🎓 LearnDash Course Access:', 'wc-learndash') . '</strong></p>';
        
        $has_access = false;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $courses = get_post_meta($product_id, '_learndash_courses', true);
            
            if ($courses && is_array($courses)) {
                $has_access = true;
                foreach ($courses as $course_id) {
                    $expire_key = "course_{$course_id}_access_expires";
                    $expires = get_user_meta($user_id, $expire_key, true);
                    $course_title = get_the_title($course_id);
                    
                    echo '<p style="margin: 5px 0;">';
                    echo '<strong>' . esc_html($course_title) . '</strong><br>';
                    if ($expires) {
                        $is_expired = $expires < current_time('timestamp');
                        $status = $is_expired ? '<span style="color: #d63638;">Expired</span>' : '<span style="color: #00a32a;">Active</span>';
                        echo '<small>Expires: ' . date('Y-m-d H:i:s', $expires) . ' - ' . $status . '</small>';
                    } else {
                        echo '<small style="color: #2271b1;">No expiration (permanent access)</small>';
                    }
                    echo '</p>';
                }
            }
        }
        
        if (!$has_access) {
            echo '<p><small style="color: #646970;">No LearnDash access configured for this order</small></p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Show user access fields in profile
     */
    public function show_user_access_fields($user) {
        echo '<h3>' . __('🎓 LearnDash Course Access', 'wc-learndash') . '</h3>';
        echo '<table class="form-table">';
        
        // Get all user's course access
        $user_meta = get_user_meta($user->ID);
        $course_access = [];
        
        foreach ($user_meta as $key => $value) {
            if (preg_match('/^course_(\d+)_access_expires$/', $key, $matches)) {
                $course_id = $matches[1];
                $course_access[$course_id] = [
                    'expires' => $value[0],
                    'title' => get_the_title($course_id)
                ];
            }
        }
        
        if (!empty($course_access)) {
            foreach ($course_access as $course_id => $data) {
                echo '<tr>';
                echo '<th><label>' . esc_html($data['title']) . '</label></th>';
                echo '<td>';
                if ($data['expires']) {
                    $expires_date = date('Y-m-d H:i:s', $data['expires']);
                    $is_expired = $data['expires'] < current_time('timestamp');
                    $status = $is_expired ? '<span style="color: #d63638; font-weight: bold;">Expired</span>' : '<span style="color: #00a32a; font-weight: bold;">Active</span>';
                    echo "Expires: {$expires_date} - {$status}";
                } else {
                    echo '<span style="color: #2271b1; font-weight: bold;">No expiration (permanent access)</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="2"><em>No course access found</em></td></tr>';
        }
        
        echo '</table>';
    }
    

}

// Initialize the plugin
new WC_LearnDash_Access_Manager();

/**
 * Helper function to check if user has access to course
 */
function wc_learndash_user_has_course_access($user_id, $course_id) {
    $expire_key = "course_{$course_id}_access_expires";
    $expires = get_user_meta($user_id, $expire_key, true);
    
    if (!$expires) {
        return true; // No expiration set
    }
    
    return $expires > current_time('timestamp');
}

/**
 * Helper function to get user's course access end date
 */
function wc_learndash_get_course_access_end_date($user_id, $course_id) {
    $expire_key = "course_{$course_id}_access_expires";
    return get_user_meta($user_id, $expire_key, true);
}

/**
 * Debug function - add ?debug_wc_learndash=1 to any admin page to test if plugin is loaded
 */
add_action('admin_init', function() {
    if (isset($_GET['debug_wc_learndash']) && current_user_can('manage_options')) {
        wp_die('✅ WC LearnDash Access Manager is loaded and working! You can now refresh your product edit page to see the new fields.');
    }
});
