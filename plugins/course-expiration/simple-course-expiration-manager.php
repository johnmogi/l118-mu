<?php
/**
 * Simple Course Expiration Manager
 * Quick and easy way to add/edit course expiration dates for any user
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Course Expiration Manager',
        'Course Expiration',
        'manage_options',
        'simple-course-expiration',
        'simple_course_expiration_page',
        'dashicons-calendar-alt',
        30
    );
});

// Handle form submissions
add_action('admin_post_add_course_expiration', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'add_course_expiration')) {
        wp_die('Security check failed');
    }
    
    $user_id = intval($_POST['user_id']);
    $course_id = intval($_POST['course_id']);
    $expiration_date = sanitize_text_field($_POST['expiration_date']);
    
    if (!$user_id || !$course_id || !$expiration_date) {
        wp_redirect(add_query_arg(['page' => 'simple-course-expiration', 'error' => 'missing_fields'], admin_url('admin.php')));
        exit;
    }
    
    // Convert to timestamp (end of day)
    $expiration_timestamp = strtotime($expiration_date . ' 23:59:59');
    
    if (!$expiration_timestamp) {
        wp_redirect(add_query_arg(['page' => 'simple-course-expiration', 'error' => 'invalid_date'], admin_url('admin.php')));
        exit;
    }
    
    // Update user meta
    $meta_key = 'course_' . $course_id . '_access_expires';
    update_user_meta($user_id, $meta_key, $expiration_timestamp);
    
    // Also update LearnDash course access if function exists
    if (function_exists('ld_update_course_access')) {
        ld_update_course_access($user_id, $course_id, false, $expiration_timestamp);
    }
    
    wp_redirect(add_query_arg(['page' => 'simple-course-expiration', 'success' => '1'], admin_url('admin.php')));
    exit;
});

// Admin page content
function simple_course_expiration_page() {
    ?>
    <div class="wrap">
        <h1>Simple Course Expiration Manager</h1>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="notice notice-success"><p>Course expiration added successfully!</p></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error"><p>Error: <?php echo esc_html($_GET['error']); ?></p></div>
        <?php endif; ?>
        
        <div style="display: flex; gap: 20px;">
            <!-- Add Course Expiration Form -->
            <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Add Course Expiration</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('add_course_expiration', 'nonce'); ?>
                    <input type="hidden" name="action" value="add_course_expiration">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="user_id">Select User</label>
                            </th>
                            <td>
                                <select name="user_id" id="user_id" required style="width: 100%;">
                                    <option value="">Choose a user...</option>
                                    <?php
                                    $users = get_users(['orderby' => 'display_name', 'order' => 'ASC']);
                                    foreach ($users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (ID: ' . $user->ID . ', Login: ' . esc_html($user->user_login) . ')</option>';
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
                    </table>
                    
                    <?php submit_button('Add Course Expiration', 'primary'); ?>
                </form>
                
                <hr>
                
                <h3>Quick Test Data</h3>
                <p>Add test data for common scenarios:</p>
                <button type="button" class="button" onclick="setTestData('active')">Active (1 month)</button>
                <button type="button" class="button" onclick="setTestData('expiring')">Expiring (3 days)</button>
                <button type="button" class="button" onclick="setTestData('expired')">Expired</button>
            </div>
            
            <!-- Current Expirations -->
            <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2>Current Course Expirations</h2>
                <?php display_current_course_expirations(); ?>
            </div>
        </div>
    </div>
    
    <script>
    function setTestData(type) {
        var date = new Date();
        var courseId = Math.floor(Math.random() * 1000) + 100;
        
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
        }
        
        document.getElementById('course_id').value = courseId;
        document.getElementById('expiration_date').value = date.toISOString().split('T')[0];
        
        alert('Test data filled in the form. Select a user and click "Add Course Expiration" to save.');
    }
    </script>
    
    <style>
    .expiration-item {
        padding: 10px;
        margin: 5px 0;
        border-left: 4px solid #2271b1;
        background: #f0f6fc;
        border-radius: 3px;
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

// Display current course expirations
function display_current_course_expirations() {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT u.ID, u.display_name, u.user_login, um.meta_key, um.meta_value
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
        WHERE um.meta_key LIKE 'course_%_access_expires'
        ORDER BY u.display_name, um.meta_key
    ");
    
    if (empty($results)) {
        echo '<p>No course expirations found. Add some using the form on the left.</p>';
        return;
    }
    
    $current_user = null;
    foreach ($results as $result) {
        if ($current_user !== $result->ID) {
            if ($current_user !== null) {
                echo '</div>';
            }
            echo '<h4>' . esc_html($result->display_name) . ' (ID: ' . $result->ID . ')</h4>';
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
