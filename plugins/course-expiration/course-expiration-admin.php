<?php
/**
 * Course Expiration Admin Manager
 * Provides admin interface for manually managing course expiration dates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Course_Expiration_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_update_course_expiration', [$this, 'handle_form_submission']);
        add_action('wp_ajax_quick_add_course_expiry', [$this, 'ajax_quick_add_course_expiry']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'school-manager-students',
            'Course Expiration Manager',
            'Course Expiration',
            'manage_options',
            'course-expiration-manager',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Course Expiration Manager</h1>
            <p>Manually add or modify course expiration dates for testing and administration purposes.</p>
            
            <div style="display: flex; gap: 20px;">
                <!-- Quick Add Form -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2>Quick Add Course Expiration</h2>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('update_course_expiration', 'course_expiration_nonce'); ?>
                        <input type="hidden" name="action" value="update_course_expiration">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="user_id">Select Student</label>
                                </th>
                                <td>
                                    <select name="user_id" id="user_id" required style="width: 100%;">
                                        <option value="">Choose a student...</option>
                                        <?php
                                        $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
                                        foreach ($users as $user) {
                                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="course_id">Course ID</label>
                                </th>
                                <td>
                                    <input type="number" name="course_id" id="course_id" value="123" required style="width: 100%;">
                                    <p class="description">Enter the LearnDash course ID (e.g., 123, 456, etc.)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="expiration_date">Expiration Date</label>
                                </th>
                                <td>
                                    <input type="date" name="expiration_date" id="expiration_date" required style="width: 100%;" value="<?php echo date('Y-m-d', strtotime('+1 month')); ?>">
                                    <p class="description">Set the course access expiration date</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="expiration_time">Expiration Time</label>
                                </th>
                                <td>
                                    <input type="time" name="expiration_time" id="expiration_time" value="23:59" style="width: 100%;">
                                    <p class="description">Set the expiration time (default: end of day)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('Add/Update Course Expiration', 'primary', 'submit', false); ?>
                    </form>
                </div>
                
                <!-- Current Expirations List -->
                <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <h2>Current Course Expirations</h2>
                    <?php $this->display_current_expirations(); ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Quick Actions</h2>
                <p>Add test data for common scenarios:</p>
                
                <button type="button" class="button" onclick="addTestData('active')">Add Active Access (1 month)</button>
                <button type="button" class="button" onclick="addTestData('expiring')">Add Expiring Soon (3 days)</button>
                <button type="button" class="button" onclick="addTestData('expired')">Add Expired Access</button>
                <button type="button" class="button" onclick="addTestData('permanent')">Add Permanent Access</button>
            </div>
        </div>
        
        <script>
        function addTestData(type) {
            var userId = document.getElementById('user_id').value;
            if (!userId) {
                alert('Please select a student first');
                return;
            }
            
            var courseId = Math.floor(Math.random() * 1000) + 100; // Random course ID
            var date = new Date();
            
            switch(type) {
                case 'active':
                    date.setMonth(date.getMonth() + 1);
                    break;
                case 'expiring':
                    date.setDate(date.getDate() + 3);
                    break;
                case 'expired':
                    date.setMonth(date.getMonth() - 1);
                    break;
                case 'permanent':
                    // Set to 0 for permanent access
                    jQuery.post(ajaxurl, {
                        action: 'quick_add_course_expiry',
                        user_id: userId,
                        course_id: courseId,
                        expiration_timestamp: 0,
                        nonce: '<?php echo wp_create_nonce('quick_add_course_expiry'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('Permanent access added successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                    return;
            }
            
            document.getElementById('course_id').value = courseId;
            document.getElementById('expiration_date').value = date.toISOString().split('T')[0];
            
            alert('Test data filled in the form. Click "Add/Update Course Expiration" to save.');
        }
        </script>
        
        <style>
        .expiration-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #2271b1;
            background: #f0f6fc;
        }
        .expiration-expired {
            border-left-color: #d63638;
            background: #fcf0f1;
        }
        .expiration-expiring {
            border-left-color: #d68100;
            background: #fcf9e8;
        }
        .expiration-permanent {
            border-left-color: #00a32a;
            background: #f0f6fc;
        }
        </style>
        <?php
    }
    
    /**
     * Display current course expirations
     */
    private function display_current_expirations() {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_login, um.meta_key, um.meta_value
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key LIKE 'course_%_access_expires'
            ORDER BY u.display_name, um.meta_key
        ");
        
        if (empty($results)) {
            echo '<p>No course expirations found. Add some test data using the form on the left.</p>';
            return;
        }
        
        $current_user = null;
        foreach ($results as $result) {
            if ($current_user !== $result->ID) {
                if ($current_user !== null) {
                    echo '</div>';
                }
                echo '<h4>' . esc_html($result->display_name) . ' (' . esc_html($result->user_login) . ')</h4>';
                echo '<div style="margin-left: 15px;">';
                $current_user = $result->ID;
            }
            
            preg_match('/course_(\d+)_access_expires/', $result->meta_key, $matches);
            $course_id = $matches[1] ?? 'Unknown';
            $expires_timestamp = $result->meta_value;
            
            $class = 'expiration-item';
            $status = '';
            $icon = '';
            
            if ($expires_timestamp == 0) {
                $class .= ' expiration-permanent';
                $status = 'Permanent Access';
                $icon = '♾️';
            } else {
                $current_time = current_time('timestamp');
                $days_remaining = ceil(($expires_timestamp - $current_time) / DAY_IN_SECONDS);
                
                if ($expires_timestamp < $current_time) {
                    $class .= ' expiration-expired';
                    $status = 'Expired on ' . date('d/m/Y', $expires_timestamp);
                    $icon = '❌';
                } elseif ($days_remaining <= 7) {
                    $class .= ' expiration-expiring';
                    $status = 'Expires ' . date('d/m/Y', $expires_timestamp) . ' (' . $days_remaining . ' days)';
                    $icon = '⚠️';
                } else {
                    $status = 'Expires ' . date('d/m/Y', $expires_timestamp) . ' (' . $days_remaining . ' days)';
                    $icon = '✅';
                }
            }
            
            echo '<div class="' . $class . '">';
            echo '<strong>' . $icon . ' Course ' . esc_html($course_id) . '</strong><br>';
            echo '<small>' . esc_html($status) . '</small>';
            echo '</div>';
        }
        
        if ($current_user !== null) {
            echo '</div>';
        }
    }
    
    /**
     * Handle form submission
     */
    public function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['course_expiration_nonce']) || !wp_verify_nonce($_POST['course_expiration_nonce'], 'update_course_expiration')) {
            wp_die('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $expiration_date = sanitize_text_field($_POST['expiration_date']);
        $expiration_time = sanitize_text_field($_POST['expiration_time']);
        
        if (!$user_id || !$course_id || !$expiration_date) {
            wp_redirect(add_query_arg(['page' => 'course-expiration-manager', 'error' => 'missing_fields'], admin_url('admin.php')));
            exit;
        }
        
        // Convert to timestamp
        $expiration_timestamp = strtotime($expiration_date . ' ' . $expiration_time);
        
        if (!$expiration_timestamp) {
            wp_redirect(add_query_arg(['page' => 'course-expiration-manager', 'error' => 'invalid_date'], admin_url('admin.php')));
            exit;
        }
        
        // Update user meta
        $meta_key = 'course_' . $course_id . '_access_expires';
        update_user_meta($user_id, $meta_key, $expiration_timestamp);
        
        // Also update LearnDash course access if function exists
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false, $expiration_timestamp);
        }
        
        // Log the action
        error_log(sprintf(
            'Course Expiration Admin: Updated course %d expiration for user %d to %s',
            $course_id,
            $user_id,
            date('Y-m-d H:i:s', $expiration_timestamp)
        ));
        
        wp_redirect(add_query_arg(['page' => 'course-expiration-manager', 'success' => '1'], admin_url('admin.php')));
        exit;
    }
    
    /**
     * AJAX handler for quick add course expiry
     */
    public function ajax_quick_add_course_expiry() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'quick_add_course_expiry')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $expiration_timestamp = intval($_POST['expiration_timestamp']);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Update user meta
        $meta_key = 'course_' . $course_id . '_access_expires';
        update_user_meta($user_id, $meta_key, $expiration_timestamp);
        
        // Also update LearnDash course access if function exists
        if (function_exists('ld_update_course_access')) {
            ld_update_course_access($user_id, $course_id, false, $expiration_timestamp);
        }
        
        wp_send_json_success('Course expiration updated successfully');
    }
}

// Initialize the admin interface
new Course_Expiration_Admin();
