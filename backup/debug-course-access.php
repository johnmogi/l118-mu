<?php
/**
 * Debug Course Access for Specific User and Course
 * Temporary script to debug access for a specific user and course
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add debug action
add_action('admin_init', function() {
    if (isset($_GET['debug_course_access']) && current_user_can('manage_options')) {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
        
        if (!$user_id || !$course_id) {
            echo "<h2>Error: Missing user_id or course_id parameter</h2>";
            wp_die();
        }
        
        $user = get_user_by('ID', $user_id);
        $course_title = get_the_title($course_id);
        
        if (!$user) {
            echo "<h2>Error: User not found</h2>";
            wp_die();
        }
        
        echo "<h2>🔍 Course Access Debug</h2>";
        echo "<h3>User: {$user->display_name} (ID: {$user_id})</h3>";
        echo "<h3>Course: {$course_title} (ID: {$course_id})</h3>";
        
        // 1. Check user meta for expiration
        $expire_key = "course_{$course_id}_access_expires";
        $expires = get_user_meta($user_id, $expire_key, true);
        
        echo "<h4>1. User Meta Check</h4>";
        echo "<p><strong>Meta Key:</strong> {$expire_key}</p>";
        echo "<p><strong>Expiration Timestamp:</strong> " . ($expires ? $expires : 'Not set') . "</p>";
        
        if ($expires) {
            $current_time = current_time('timestamp');
            $expired = $expires < $current_time;
            echo "<p><strong>Expiration Date:</strong> " . date('Y-m-d H:i:s', $expires) . "</p>";
            echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s', $current_time) . "</p>";
            echo "<p><strong>Status:</strong> " . ($expired ? '❌ EXPIRED' : '✅ ACTIVE') . "</p>";
        } else {
            echo "<p>No expiration date set for this course.</p>";
        }
        
        // 2. Check LearnDash enrollment
        echo "<h4>2. LearnDash Enrollment</h4>";
        if (function_exists('ld_course_check_user_access')) {
            $has_access = ld_course_check_user_access($course_id, $user_id);
            echo "<p><strong>LearnDash Access Check:</strong> " . ($has_access ? '✅ Has Access' : '❌ No Access') . "</p>";
            
            // Check course access from and to dates
            $course_access_from = get_user_meta($user_id, 'course_' . $course_id . '_access_from', true);
            $course_access_to = get_user_meta($user_id, 'course_' . $course_id . '_access_to', true);
            
            echo "<p><strong>Course Access From:</strong> " . ($course_access_from ? date('Y-m-d H:i:s', $course_access_from) : 'Not set') . "</p>";
            echo "<p><strong>Course Access To:</strong> " . ($course_access_to ? date('Y-m-d H:i:s', $course_access_to) : 'Not set') . "</p>";
        } else {
            echo "<p>LearnDash functions not available.</p>";
        }
        
        // 3. Check if user is enrolled in course
        if (function_exists('learndash_user_get_enrolled_courses')) {
            $enrolled_courses = learndash_user_get_enrolled_courses($user_id);
            echo "<p><strong>Enrolled in Course:</strong> " . (in_array($course_id, $enrolled_courses) ? '✅ Yes' : '❌ No') . "</p>";
        }
        
        // 4. Check all user meta for this course
        echo "<h4>3. All User Meta for This Course</h4>";
        $all_meta = get_user_meta($user_id);
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Meta Key</th><th>Value</th></tr>";
        
        foreach ($all_meta as $key => $values) {
            if (strpos($key, 'course_' . $course_id) !== false) {
                foreach ((array)$values as $value) {
                    if (is_serialized($value)) {
                        $value = print_r(maybe_unserialize($value), true);
                    }
                    echo "<tr><td>{$key}</td><td>" . esc_html($value) . "</td></tr>";
                }
            }
        }
        echo "</table>";
        
        // 5. Check course products
        echo "<h4>4. Products Providing Access to This Course</h4>";
        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_related_course',
                    'value' => '"' . $course_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
            'posts_per_page' => -1,
        ]);
        
        if (!empty($products)) {
            echo "<ul>";
            foreach ($products as $product) {
                $product_id = $product->ID;
                $access_duration = get_post_meta($product_id, '_course_access_duration', true);
                echo "<li>Product: <a href='" . get_edit_post_link($product_id) . "'>{$product->post_title} (ID: {$product_id})</a>";
                echo "<br>Access Duration: " . ($access_duration ? $access_duration : 'Not set') . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No products found that provide access to this course.</p>";
        }
        
        // 6. Check user's orders that might have granted access
        echo "<h4>5. User's Orders with Access to This Course</h4>";
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer' => $user->user_email,
                'status' => ['completed', 'processing'],
                'limit' => 20,
            ]);
            
            if (!empty($orders)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>Order</th><th>Date</th><th>Status</th><th>Products</th><th>Access Info</th></tr>";
                
                foreach ($orders as $order) {
                    $order_id = $order->get_id();
                    $order_products = [];
                    $access_info = [];
                    
                    foreach ($order->get_items() as $item) {
                        $product_id = $item->get_product_id();
                        $product = wc_get_product($product_id);
                        $order_products[] = $product->get_name() . ' (ID: ' . $product_id . ')';
                        
                        // Check if this product gives access to our course
                        $related_courses = get_post_meta($product_id, '_related_course', true);
                        if (is_array($related_courses) && in_array($course_id, $related_courses)) {
                            $access_duration = get_post_meta($product_id, '_course_access_duration', true);
                            $access_info[] = "<strong>" . $product->get_name() . "</strong> grants access to this course" . 
                                           ($access_duration ? " for $access_duration" : "") . 
                                           ". <a href='" . get_edit_post_link($product_id) . "'>Edit product</a>";
                        }
                    }
                    
                    echo "<tr>";
                    echo "<td><a href='" . get_edit_post_link($order_id) . "'>#" . $order->get_order_number() . "</a></td>";
                    echo "<td>" . $order->get_date_created()->date('Y-m-d H:i:s') . "</td>";
                    echo "<td>" . $order->get_status() . "</td>";
                    echo "<td>" . implode("<br>", $order_products) . "</td>";
                    echo "<td>" . (!empty($access_info) ? implode("<br>", $access_info) : "No access to this course") . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No orders found for this user.</p>";
            }
        } else {
            echo "<p>WooCommerce functions not available.</p>";
        }
        
        // 7. Check if user has admin/teacher role that might bypass restrictions
        echo "<h4>6. User Roles and Capabilities</h4>";
        echo "<p><strong>Roles:</strong> " . implode(', ', $user->roles) . "</p>";
        
        // 8. Check for any user groups that might grant access
        if (function_exists('learndash_get_users_group_ids')) {
            $group_ids = learndash_get_users_group_ids($user_id, true);
            if (!empty($group_ids)) {
                echo "<p><strong>LearnDash Groups:</strong></p><ul>";
                foreach ($group_ids as $group_id) {
                    echo "<li>" . get_the_title($group_id) . " (ID: $group_id)</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>User is not in any LearnDash groups.</p>";
            }
        }
        
        // Add link to test the access function directly
        echo "<h4>7. Test Access Function</h4>";
        if (function_exists('wc_learndash_user_has_course_access')) {
            $has_access = wc_learndash_user_has_course_access($user_id, $course_id);
            echo "<p><strong>wc_learndash_user_has_course_access({$user_id}, {$course_id}):</strong> " . 
                 ($has_access ? '✅ TRUE' : '❌ FALSE') . "</p>";
            
            // Test the access function with different timestamps
            echo "<p><strong>Access check with current time:</strong> " . 
                 (wc_learndash_user_has_course_access($user_id, $course_id) ? '✅ Access' : '❌ No Access') . "</p>";
        } else {
            echo "<p>Access function not available.</p>";
        }
        
        echo "<hr><p><a href='" . admin_url('users.php') . "'>← Back to Users</a> | ";
        echo "<a href='" . admin_url('post.php?post=' . $course_id . '&action=edit') . "'>Edit Course</a></p>";
        
        wp_die();
    }
});
