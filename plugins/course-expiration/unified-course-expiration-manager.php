<?php
/**
 * Unified Course Expiration Manager
 * Combines School Manager Students functionality with Simple Course Expiration features
 * Provides a single, cohesive admin interface for managing course access
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Unified_Course_Expiration_Manager {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_unified_set_course_expiration', [$this, 'ajax_set_course_expiration']);
        add_action('wp_ajax_unified_get_user_courses', [$this, 'ajax_get_user_courses']);
        add_action('wp_ajax_get_available_courses', [$this, 'ajax_get_available_courses']);
        add_action('wp_ajax_unified_bulk_update', [$this, 'ajax_bulk_update']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Unified Course Manager',
            'Course Manager',
            'manage_options',
            'unified-course-manager',
            [$this, 'admin_page'],
            'dashicons-graduation-cap',
            25
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_unified-course-manager') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        wp_localize_script('jquery', 'unified_course_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('unified_course_nonce')
        ]);
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🎓 Unified Course Expiration Manager</h1>
            <p class="description">Manage course access and expiration dates for all students in one place.</p>
            
            <?php $this->display_notices(); ?>
            
            <div class="unified-manager-container">
                <!-- Quick Actions Panel -->
                <div class="quick-actions-panel">
                    <h2>⚡ Quick Actions</h2>
                    <div class="action-buttons">
                        <button type="button" class="button button-primary" onclick="openBulkUpdateDialog()">
                            📊 Bulk Update
                        </button>
                        <button type="button" class="button" onclick="refreshStudentsList()">
                            🔄 Refresh List
                        </button>
                        <button type="button" class="button" onclick="exportData()">
                            📤 Export Data
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="filters-section">
                        <h3>🔍 Filters</h3>
                        <select id="status-filter" onchange="filterStudents()">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="expiring">Expiring Soon</option>
                            <option value="permanent">Permanent</option>
                        </select>
                        
                        <select id="course-filter" onchange="filterStudents()">
                            <option value="">All Courses</option>
                            <?php $this->display_course_options(); ?>
                        </select>
                        
                        <input type="text" id="search-students" placeholder="Search students..." onkeyup="filterStudents()">
                    </div>
                </div>
                
                <!-- Students Table -->
                <div class="students-table-container">
                    <h2>👥 Students & Course Access</h2>
                    <div class="table-wrapper">
                        <?php $this->display_students_table(); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dialogs -->
        <?php $this->render_dialogs(); ?>
        
        <style>
        .unified-manager-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .quick-actions-panel {
            flex: 0 0 300px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            height: fit-content;
        }
        
        .students-table-container {
            flex: 1;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filters-section {
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .filters-section select,
        .filters-section input {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .unified-students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .unified-students-table th,
        .unified-students-table td {
            padding: 12px 8px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .unified-students-table th {
            background: #f1f1f1;
            font-weight: 600;
        }
        
        .unified-students-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-expiring { background: #fff3cd; color: #856404; }
        .status-permanent { background: #cff4fc; color: #055160; }
        .status-no-access { background: #f8f9fa; color: #6c757d; border: 1px dashed #dee2e6; }
        
        .action-buttons-cell {
            white-space: nowrap;
        }
        
        .action-buttons-cell button {
            margin: 0 2px;
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .course-info {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 500;
        }
        
        .user-details {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        
        .no-courses-info {
            text-align: center;
            padding: 10px;
        }
        
        .tablenav {
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .displaying-num {
            font-weight: 500;
            color: #2271b1;
        }
        
        .pagination-links a {
            text-decoration: none;
            padding: 4px 8px;
            margin: 0 2px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('Unified Course Manager loaded');
            console.log('AJAX URL:', unified_course_ajax.ajax_url);
            console.log('Nonce:', unified_course_ajax.nonce);
            
            // Initialize dialogs with error handling
            try {
                if ($.fn.dialog) {
                    $('#bulk-update-dialog').dialog({
                        autoOpen: false,
                        modal: true,
                        width: 500,
                        title: 'Bulk Update Course Access'
                    });
                    
                    $('#course-details-dialog').dialog({
                        autoOpen: false,
                        modal: true,
                        width: 600,
                        title: 'Course Details'
                    });
                    
                    $('#set-expiry-dialog').dialog({
                        autoOpen: false,
                        modal: true,
                        width: 500,
                        height: 350,
                        position: { my: 'center', at: 'center', of: window },
                        title: 'Set Course Expiration'
                    });
                    
                    $('#expiration-date-dialog').dialog({
                        autoOpen: false,
                        modal: true,
                        width: 500,
                        height: 400,
                        position: { my: 'center', at: 'center', of: window },
                        title: 'Set Expiration Date'
                    });
                    
                    console.log('All dialogs initialized successfully');
                } else {
                    console.warn('jQuery UI Dialog not available');
                }
            } catch (e) {
                console.error('Error initializing dialogs:', e);
            }
            
            // Global function definitions with improved error handling
            window.openBulkUpdateDialog = function() {
                try {
                    console.log('Opening bulk update dialog');
                    $('#bulk-update-dialog').dialog('open');
                } catch (e) {
                    console.error('Error opening bulk dialog:', e);
                    alert('Error opening dialog. Please refresh the page.');
                }
            };
            
            window.setCourseExpiration = function(userId, courseId) {
                console.log('setCourseExpiration called with userId:', userId, 'courseId:', courseId);
                
                try {
                    // Store user ID for later use
                    window.currentUserId = userId;
                    window.currentCourseId = courseId;
                    
                    // Update the current user ID in the modal
                    $('#current-user-id').text(userId);
                    
                    // If courseId is not provided, show course selection dialog first
                    if (!courseId) {
                        // Load available courses first
                        loadAvailableCourses();
                        
                        // Ensure dialog is initialized before opening
                        if (!$('#set-expiry-dialog').hasClass('ui-dialog-content')) {
                            $('#set-expiry-dialog').dialog({
                                autoOpen: false,
                                modal: true,
                                width: 500,
                                height: 350,
                                position: { my: 'center', at: 'center', of: window },
                                title: 'Select Course'
                            });
                        }
                        $('#set-expiry-dialog').dialog('open');
                        return;
                    }
                    
                    // If we have course ID, show expiration date dialog
                    $('#selected-course-id').text(courseId);
                    
                    // Ensure dialog is initialized before opening
                    if (!$('#expiration-date-dialog').hasClass('ui-dialog-content')) {
                        $('#expiration-date-dialog').dialog({
                            autoOpen: false,
                            modal: true,
                            width: 500,
                            height: 400,
                            position: { my: 'center', at: 'center', of: window },
                            title: 'Set Expiration Date'
                        });
                    }
                    $('#expiration-date-dialog').dialog('open');
                } catch (e) {
                    console.error('Error in setCourseExpiration:', e);
                    alert('❌ JavaScript error: ' + e.message);
                }
            };
            
            // Function to load available courses
            window.loadAvailableCourses = function() {
                console.log('Loading available courses...');
                
                // Show loading state
                jQuery('#course-selection').html('<option value="">Loading courses...</option>');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_available_courses',
                        nonce: '<?php echo wp_create_nonce('unified_course_nonce'); ?>'
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        
                        if (response && response.success && response.data && response.data.length > 0) {
                            var courseSelect = jQuery('#course-selection');
                            courseSelect.empty();
                            courseSelect.append('<option value="">-- Select a Course --</option>');
                            
                            response.data.forEach(function(course) {
                                courseSelect.append('<option value="' + course.id + '">' + course.title + ' (ID: ' + course.id + ')</option>');
                            });
                            
                            console.log('Successfully loaded ' + response.data.length + ' courses');
                        } else {
                            console.error('No courses found or invalid response:', response);
                            jQuery('#course-selection').html('<option value="">No courses found</option>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        jQuery('#course-selection').html('<option value="">Error: ' + error + '</option>');
                    }
                });
            };
            
            // Function to proceed from course selection to date input
            window.proceedToDateInput = function() {
                console.log('proceedToDateInput called');
                
                var courseSelect = jQuery('#course-selection');
                var courseId = courseSelect.val();
                
                console.log('Course select element:', courseSelect.length);
                console.log('Course ID value:', courseId);
                console.log('All options:', courseSelect.find('option').map(function() { return this.value + ':' + this.text; }).get());
                
                if (!courseId || courseId.trim() === '' || courseId === 'Loading courses...' || courseId === '-- Select a Course --') {
                    alert('Please select a course from the dropdown');
                    return;
                }
                
                console.log('Valid course ID selected:', courseId);
                window.currentCourseId = parseInt(courseId);
                
                // Close course selection dialog
                jQuery('#set-expiry-dialog').dialog('close');
                
                // Open expiration date dialog with proper positioning
                jQuery('#expiration-date-dialog').dialog({
                    modal: true,
                    width: 500,
                    height: 400,
                    position: { my: 'center', at: 'center', of: window },
                    title: 'Set Expiration Date'
                });
                
                console.log('Opened expiration date dialog for course:', courseId);
            };
            
            // Function to process the actual expiration setting
            window.processExpirationSetting = function(courseId, expirationDate) {
                console.log('Processing expiration with courseId:', courseId, 'date:', expirationDate);
                
                try {
                    var expirationTimestamp = 0;
                    
                    if (expirationDate === 'disable') {
                        // Set to -1 to indicate disabled/removed access
                        expirationTimestamp = -1;
                    } else if (expirationDate.toLowerCase() !== 'permanent') {
                        var dateObj = new Date(expirationDate + ' 23:59:59');
                        if (isNaN(dateObj.getTime())) {
                            alert('Invalid date format. Please use YYYY-MM-DD');
                            return;
                        }
                        expirationTimestamp = Math.floor(dateObj.getTime() / 1000);
                    }
                    
                    console.log('Sending AJAX request with timestamp:', expirationTimestamp);
                    
                    $.ajax({
                        url: unified_course_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'unified_set_course_expiration',
                            user_id: window.currentUserId,
                            course_id: courseId,
                            expiration: expirationTimestamp,
                            nonce: unified_course_ajax.nonce
                        },
                        success: function(response) {
                            console.log('AJAX response:', response);
                            if (response.success) {
                                alert('✅ Course expiration updated successfully!');
                                location.reload();
                            } else {
                                alert('❌ Error: ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', xhr, status, error);
                            alert('❌ Network error: ' + error + '. Please try again.');
                        },
                        timeout: 10000
                    });
                } catch (e) {
                    console.error('Error in processExpirationSetting:', e);
                    alert('❌ JavaScript error: ' + e.message);
                }
            };
            
            // Functions for modal dialogs
            window.proceedToDateInput = function() {
                // Get the selected course from the dropdown, not from a text input
                var courseId = jQuery('#course-selection').val();
                if (!courseId || courseId === '') {
                    alert('Please select a course from the dropdown');
                    return;
                }
                
                console.log('Proceeding with course ID:', courseId);
                window.currentCourseId = courseId;
                
                jQuery('#set-expiry-dialog').dialog('close');
                jQuery('#expiration-date-dialog').dialog('open');
            };
            
            window.toggleCustomDate = function() {
                var type = jQuery('#expiration-type').val();
                if (type === 'custom') {
                    jQuery('#custom-date-row').show();
                } else {
                    jQuery('#custom-date-row').hide();
                }
            };
            
            window.processExpirationFromDialog = function() {
                var expirationType = jQuery('#expiration-type').val();
                var expirationDate;
                
                if (expirationType === 'disable') {
                    // Confirm before disabling access
                    if (!confirm('⚠️ Are you sure you want to disable course access for this user? This will immediately remove their access to the course.')) {
                        return;
                    }
                    expirationDate = 'disable';
                } else if (expirationType === 'custom') {
                    expirationDate = jQuery('#custom-date-input').val();
                    if (!expirationDate) {
                        alert('Please select a custom date');
                        return;
                    }
                } else if (expirationType === 'permanent') {
                    expirationDate = 'permanent';
                } else {
                    // Calculate date based on selection
                    var date = new Date();
                    switch(expirationType) {
                        case '1_month':
                            date.setMonth(date.getMonth() + 1);
                            break;
                        case '3_months':
                            date.setMonth(date.getMonth() + 3);
                            break;
                        case '6_months':
                            date.setMonth(date.getMonth() + 6);
                            break;
                        case '1_year':
                            date.setFullYear(date.getFullYear() + 1);
                            break;
                    }
                    expirationDate = date.toISOString().split('T')[0];
                }
                
                console.log('Processing expiration with type:', expirationType, 'date:', expirationDate);
                
                jQuery('#expiration-date-dialog').dialog('close');
                processExpirationSetting(window.currentCourseId, expirationDate);
            };
        });
        
            // Also define viewCourseDetails globally
            window.viewCourseDetails = function(userId) {
                console.log('viewCourseDetails called with userId:', userId);
                
                try {
                    $.ajax({
                        url: unified_course_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'unified_get_user_courses',
                            user_id: userId,
                            nonce: unified_course_ajax.nonce
                        },
                        success: function(response) {
                            console.log('Course details response:', response);
                            if (response.success) {
                                $('#course-details-content').html(response.data);
                                try {
                                    $('#course-details-dialog').dialog('open');
                                } catch (e) {
                                    console.error('Error opening dialog:', e);
                                    // Fallback: show in alert if dialog fails
                                    alert('Course Details:\n' + $(response.data).text());
                                }
                            } else {
                                alert('❌ Error loading course details: ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error in viewCourseDetails:', xhr, status, error);
                            alert('❌ Network error loading course details: ' + error);
                        },
                        timeout: 10000
                    });
                } catch (e) {
                    console.error('Error in viewCourseDetails:', e);
                    alert('❌ JavaScript error: ' + e.message);
                }
            };
        
        function filterStudents() {
            var statusFilter = jQuery('#status-filter').val();
            var courseFilter = jQuery('#course-filter').val();
            var searchTerm = jQuery('#search-students').val().toLowerCase();
            
            jQuery('.unified-students-table tbody tr').each(function() {
                var row = jQuery(this);
                var show = true;
                
                // Status filter
                if (statusFilter && !row.find('.status-' + statusFilter).length) {
                    show = false;
                }
                
                // Course filter
                if (courseFilter && !row.find('[data-course-id="' + courseFilter + '"]').length) {
                    show = false;
                }
                
                // Search filter
                if (searchTerm && !row.text().toLowerCase().includes(searchTerm)) {
                    show = false;
                }
                
                row.toggle(show);
            });
        }
        
        function refreshStudentsList() {
            location.reload();
        }
        
        function exportData() {
            alert('Export functionality will be implemented in the next version.');
        }
        </script>
        
        <!-- Modal HTML Structures -->
        <div id="set-expiry-dialog" title="Select Course" style="display: none;">
            <div style="padding: 20px;">
                <p><strong>Select a course to set expiration for User ID: <span id="current-user-id"></span></strong></p>
                
                <div style="margin: 15px 0;">
                    <label for="course-selection"><strong>Choose Course:</strong></label><br>
                    <select id="course-selection" style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="">Loading courses...</option>
                    </select>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="button button-secondary" onclick="jQuery('#set-expiry-dialog').dialog('close')">Cancel</button>
                    <button type="button" class="button button-primary" onclick="proceedToDateInput()">Next: Set Date</button>
                </div>
            </div>
        </div>
        
        <div id="expiration-date-dialog" title="Set Expiration Date" style="display: none;">
            <div style="padding: 20px;">
                <p><strong>Set expiration for Course ID: <span id="selected-course-id"></span></strong></p>
                
                <div style="margin: 15px 0;">
                    <label for="expiration-type"><strong>Expiration Type:</strong></label><br>
                    <select id="expiration-type" onchange="toggleCustomDate()" style="width: 100%; padding: 8px; margin-top: 5px;">
                        <option value="permanent">♾️ Permanent Access</option>
                        <option value="1_month">📅 1 Month</option>
                        <option value="3_months">📅 3 Months</option>
                        <option value="6_months">📅 6 Months</option>
                        <option value="1_year">📅 1 Year</option>
                        <option value="custom">📅 Custom Date</option>
                        <option value="disable">❌ Disable Access</option>
                    </select>
                </div>
                
                <div id="custom-date-row" style="margin: 15px 0; display: none;">
                    <label for="custom-date-input"><strong>Custom Date:</strong></label><br>
                    <input type="date" id="custom-date-input" style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="button button-secondary" onclick="jQuery('#expiration-date-dialog').dialog('close')">Cancel</button>
                    <button type="button" class="button button-primary" onclick="processExpirationFromDialog()">Set Expiration</button>
                </div>
            </div>
        </div>
        
        <div id="course-details-dialog" title="Course Details" style="display: none;">
            <div id="course-details-content" style="padding: 20px;">
                Loading course details...
            </div>
        </div>
        
        <div id="bulk-update-dialog" title="Bulk Update" style="display: none;">
            <div style="padding: 20px;">
                <p>Bulk update functionality will be implemented in the next version.</p>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="button button-secondary" onclick="jQuery('#bulk-update-dialog').dialog('close')">Close</button>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    private function display_notices() {
        if (isset($_GET['success'])) {
            echo '<div class="notice notice-success"><p>✅ Operation completed successfully!</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error"><p>❌ Error: ' . esc_html($_GET['error']) . '</p></div>';
        }
    }
    
    private function display_course_options() {
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        foreach ($courses as $course) {
            echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . ' (ID: ' . $course->ID . ')</option>';
        }
    }
    
    private function display_students_table() {
        // Get pagination parameters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Use WordPress get_users() function which is more reliable
        $all_users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all'
        ]);
        
        $total_students = count($all_users);
        
        // Get users for current page
        $results = array_slice($all_users, $offset, $per_page);
        
        if (empty($results)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Debug Info:</strong></p>';
            echo '<p>Total users found with get_users(): ' . $total_students . '</p>';
            echo '<p>Current page: ' . $page . ', Per page: ' . $per_page . ', Offset: ' . $offset . '</p>';
            echo '<p>If you see this message, there might be an issue with pagination.</p>';
            echo '</div>';
            return;
        }
        
        // Display pagination info
        $total_pages = ceil($total_students / $per_page);
        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions">';
        echo '<span class="displaying-num">' . sprintf('%s students total', number_format($total_students)) . '</span>';
        echo '</div>';
        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            echo '<span class="pagination-links">';
            
            // Previous page link
            if ($page > 1) {
                $prev_page = $page - 1;
                echo '<a class="prev-page button" href="?page=unified-course-manager&paged=' . $prev_page . '">‹ Previous</a> ';
            }
            
            // Page numbers
            echo '<span class="paging-input">';
            echo '<span class="tablenav-paging-text">Page ' . $page . ' of ' . $total_pages . '</span>';
            echo '</span>';
            
            // Next page link
            if ($page < $total_pages) {
                $next_page = $page + 1;
                echo ' <a class="next-page button" href="?page=unified-course-manager&paged=' . $next_page . '">Next ›</a>';
            }
            
            echo '</span>';
            echo '</div>';
        }
        echo '</div>';
        
        ?>
        <table class="unified-students-table">
            <thead>
                <tr>
                    <th>👤 Student</th>
                    <th>📚 Courses & Status</th>
                    <th>⚡ Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $user): ?>
                    <?php $this->display_student_row($user); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function display_student_row($user) {
        global $wpdb;
        
        // Get course expiration data for this user (using correct edc_ prefix)
        $course_data = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value
            FROM edc_usermeta
            WHERE user_id = %d AND meta_key LIKE 'course_%_access_expires'
            ORDER BY meta_key
        ", $user->ID));
        
        // Get user roles for display
        $user_roles = implode(', ', $user->roles);
        
        ?>
        <tr data-user-id="<?php echo esc_attr($user->ID); ?>">
            <td>
                <div class="user-info">
                    <div class="user-name"><?php echo esc_html($user->display_name); ?></div>
                    <div class="user-details">
                        ID: <?php echo $user->ID; ?> | 
                        Login: <?php echo esc_html($user->user_login); ?><br>
                        📅 Registered: <?php echo date('d/m/Y', strtotime($user->user_registered)); ?><br>
                        👤 Role: <?php echo esc_html($user_roles); ?>
                    </div>
                </div>
            </td>
            <td>
                <?php $this->display_user_courses($course_data, $user->ID); ?>
            </td>
            <td class="action-buttons-cell">
                <button type="button" class="button button-small" onclick="viewCourseDetails(<?php echo $user->ID; ?>)">
                    📋 Details
                </button>
                <button type="button" class="button button-small button-primary" onclick="setCourseExpiration(<?php echo $user->ID; ?>)">
                    ⏰ Set Expiry
                </button>
            </td>
        </tr>
        <?php
    }
    
    private function display_user_courses($course_data, $user_id = null) {
        if (empty($course_data)) {
            echo '<div class="no-courses-info">';
            echo '<span class="status-badge status-no-access">🚫 No Course Access</span>';
            echo '<div class="course-info">';
            echo '<small>Click "Set Expiry" to add course access</small>';
            echo '</div>';
            echo '</div>';
            return;
        }
        
        foreach ($course_data as $data) {
            preg_match('/course_(\d+)_access_expires/', $data->meta_key, $matches);
            $course_id = $matches[1] ?? 'Unknown';
            $expires_timestamp = $data->meta_value;
            
            $course_title = get_the_title($course_id) ?: "Course $course_id";
            
            // Determine status
            $status_class = 'status-active';
            $status_text = 'Active';
            $icon = '✅';
            
            if ($expires_timestamp == 0) {
                $status_class = 'status-permanent';
                $status_text = 'Permanent';
                $icon = '♾️';
            } else {
                $current_time = current_time('timestamp');
                $days_remaining = ceil(($expires_timestamp - $current_time) / DAY_IN_SECONDS);
                
                if ($expires_timestamp < $current_time) {
                    $status_class = 'status-expired';
                    $status_text = 'Expired';
                    $icon = '❌';
                } elseif ($days_remaining <= 7) {
                    $status_class = 'status-expiring';
                    $status_text = 'Expiring';
                    $icon = '⚠️';
                }
            }
            
            echo '<div class="course-info" data-course-id="' . esc_attr($course_id) . '">';
            echo '<span class="status-badge ' . $status_class . '">' . $icon . ' ' . esc_html($status_text) . '</span> ';
            echo '<strong>' . esc_html($course_title) . '</strong>';
            if ($expires_timestamp > 0) {
                echo '<br><small>Expires: ' . date('d/m/Y', $expires_timestamp) . '</small>';
            }
            echo '</div>';
        }
    }
    
    private function render_dialogs() {
        ?>
        <!-- Bulk Update Dialog -->
        <div id="bulk-update-dialog" style="display: none;">
            <form id="bulk-update-form">
                <table class="form-table">
                    <tr>
                        <th><label for="bulk-course-id">Course ID:</label></th>
                        <td><input type="number" id="bulk-course-id" name="course_id" required></td>
                    </tr>
                    <tr>
                        <th><label for="bulk-expiration">Expiration:</label></th>
                        <td>
                            <select id="bulk-expiration" name="expiration">
                                <option value="permanent">Permanent Access</option>
                                <option value="1_month">1 Month from now</option>
                                <option value="3_months">3 Months from now</option>
                                <option value="6_months">6 Months from now</option>
                                <option value="1_year">1 Year from now</option>
                                <option value="custom">Custom Date</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="custom-date-row" style="display: none;">
                        <th><label for="bulk-custom-date">Custom Date:</label></th>
                        <td><input type="date" id="bulk-custom-date" name="custom_date"></td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" onclick="processBulkUpdate()">Apply to All Students</button>
                    <button type="button" class="button" onclick="jQuery('#bulk-update-dialog').dialog('close')">Cancel</button>
                </p>
            </form>
        </div>
        
        <!-- Course Details Dialog -->
        <div id="course-details-dialog" style="display: none;">
            <div id="course-details-content">Loading...</div>
        </div>
        
        <!-- Set Course Expiration Dialog -->
        <div id="set-expiry-dialog" style="display: none;">
            <form id="set-expiry-form">
                <table class="form-table">
                    <tr>
                        <th><label for="course-select">Select Course:</label></th>
                        <td>
                            <select id="course-select" name="course_id" required style="width: 100%; padding: 8px;">
                                <option value="">Loading courses...</option>
                            </select>
                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                Select the course you want to manage access for
                            </div>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" onclick="proceedToDateInput()">Next: Set Date</button>
                    <button type="button" class="button" onclick="jQuery('#set-expiry-dialog').dialog('close')">Cancel</button>
                </p>
            </form>
        </div>
        
        <!-- Expiration Date Dialog -->
        <div id="expiration-date-dialog" style="display: none;">
            <form id="expiration-date-form">
                <table class="form-table">
                    <tr>
                        <th><label for="expiration-input">Expiration:</label></th>
                        <td>
                            <select id="expiration-type" onchange="toggleCustomDate()" style="width: 100%; margin-bottom: 10px;">
                                <option value="permanent">Permanent Access</option>
                                <option value="1_month">1 Month from now</option>
                                <option value="3_months">3 Months from now</option>
                                <option value="6_months">6 Months from now</option>
                                <option value="1_year">1 Year from now</option>
                                <option value="custom">Custom Date</option>
                                <option value="disable" style="color: #d63638; font-weight: bold;">🚫 Disable Access (Remove Course)</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="custom-date-row" style="display: none;">
                        <th><label for="custom-date-input">Custom Date:</label></th>
                        <td><input type="date" id="custom-date-input" name="custom_date" style="width: 100%;"></td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" onclick="processExpirationFromDialog()">Set Expiration</button>
                    <button type="button" class="button" onclick="jQuery('#expiration-date-dialog').dialog('close')">Cancel</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    public function ajax_set_course_expiration() {
        check_ajax_referer('unified_course_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        $course_id = intval($_POST['course_id']);
        $expiration = intval($_POST['expiration']);
        
        if (!$user_id || !$course_id) {
            wp_send_json_error('Missing required parameters');
        }
        
        $meta_key = 'course_' . $course_id . '_access_expires';
        
        if ($expiration === -1) {
            // Disable/remove course access (using correct edc_ prefix)
            global $wpdb;
            $wpdb->delete(
                'edc_usermeta',
                [
                    'user_id' => $user_id,
                    'meta_key' => $meta_key
                ],
                ['%d', '%s']
            );
            
            $success_messages = [];
            $success_messages[] = 'User meta deleted from edc_usermeta';
            
            // Remove from LearnDash using multiple methods to ensure removal
            
            // Method 1: Remove user from course using LearnDash function
            if (function_exists('ld_update_course_access')) {
                $result1 = ld_update_course_access($user_id, $course_id, true);
                $success_messages[] = 'ld_update_course_access called (remove=true)';
            }
            
            // Method 2: Use learndash_user_unenroll_course if available
            if (function_exists('learndash_user_unenroll_course')) {
                $result2 = learndash_user_unenroll_course($user_id, $course_id);
                $success_messages[] = 'learndash_user_unenroll_course called';
            }
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'course_%_access_expires'",
                $user_id
            ));
            $success_messages[] = 'Removed all course access expiration records';
            
            // Remove all group access records
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'group_%_access_from'",
                $user_id
            ));
            $success_messages[] = 'Removed all group access records';
            
            // Remove all group enrollment timestamps
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'learndash_group_%_enrolled_at'",
                $user_id
            ));
            $success_messages[] = 'Removed all group enrollment timestamps';
            
            // Remove all group user membership records
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'learndash_group_users_%'",
                $user_id
            ));
            $success_messages[] = 'Removed all group user membership records';
            
            // Remove all course enrollment records
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'learndash_course_%_enrolled_at'",
                $user_id
            ));
            $success_messages[] = 'Removed all course enrollment records';
            
            // Remove all course progress and activity meta
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE '%sfwd%'",
                $user_id
            ));
            $success_messages[] = 'Removed all course progress and activity meta';
            
            // === PHASE 2: WOOCOMMERCE INTEGRATION CLEANUP ===
            
            // Remove WooCommerce order-based course access
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE 'course_%_order_id'",
                $user_id
            ));
            $success_messages[] = 'Removed WooCommerce order-based course access';
            
            // Remove WooCommerce-LearnDash integration meta
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND meta_key LIKE '_learndash_woocommerce_%'",
                $user_id
            ));
            $success_messages[] = 'Removed WooCommerce-LearnDash integration meta';
            
            // Remove all WooCommerce subscription-based course access
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_usermeta WHERE user_id = %d AND (meta_key LIKE '%woocommerce%' OR meta_key LIKE '%subscription%' OR meta_key LIKE '%order%') AND meta_key LIKE '%course%'",
                $user_id
            ));
            $success_messages[] = 'Removed all WooCommerce subscription-based course access';
            
            // === PHASE 3: LEARNDASH ACTIVITY CLEANUP ===
            
            // Remove all user activity records from LearnDash activity table
            $wpdb->query($wpdb->prepare(
                "DELETE FROM edc_learndash_user_activity WHERE user_id = %d",
                $user_id
            ));
            $success_messages[] = 'Removed all LearnDash activity records';
            
            // === PHASE 4: POSTMETA CLEANUP (COURSE AND GROUP LISTS) ===
            
            // Remove user from course access lists
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_key = 'course_access_list' AND meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from course access lists';
            
            // Remove user from course users lists
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_key = 'learndash_course_users' AND meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from course users lists';
            
            // Remove user from wrld course users lists
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_key = 'wrld_course_users' AND meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from wrld course users lists';
            
            // Remove user from group users lists
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_key = 'learndash_group_users' AND meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from group users lists';
            
            // Remove user from wrld group users lists
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_key = 'wrld_group_users' AND meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from wrld group users lists';
            
            // === PHASE 5: COMPREHENSIVE POSTMETA CLEANUP ===
            
            // Remove user from all serialized arrays in postmeta (multiple formats)
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 'i:%d;', '') WHERE meta_value LIKE %s",
                $user_id,
                '%i:' . $user_id . ';%'
            ));
            $success_messages[] = 'Removed from all serialized arrays in postmeta';
            
            // Remove user from string format references
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(meta_value, 's:3:\"%d\";', '') WHERE meta_value LIKE %s",
                $user_id,
                '%s:3:"' . $user_id . '";%'
            ));
            $success_messages[] = 'Removed from string format references';
            
            // Remove user from JSON and other formats
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(REPLACE(REPLACE(meta_value, '\"%d\"', ''), ':%d:', ''), '%d,', '') WHERE meta_value LIKE %s",
                $user_id,
                $user_id,
                $user_id,
                '%' . $user_id . '%'
            ));
            $success_messages[] = 'Removed from JSON and other formats';
            
            // === PHASE 6: LEARNDASH-SPECIFIC META CLEANUP ===
            
            // Remove user from quiz questions and lesson records
            $wpdb->query($wpdb->prepare(
                "UPDATE edc_postmeta SET meta_value = REPLACE(REPLACE(REPLACE(meta_value, 'i:%d;', ''), ':%d,', ''), '%d:', '') WHERE meta_key IN ('ld_quiz_questions', 'course_access_list', 'learndash_course_users', 'wrld_course_users', 'learndash_group_users') AND meta_value LIKE %s",
                $user_id,
                $user_id,
                $user_id,
                '%' . $user_id . '%'
            ));
            $success_messages[] = 'Removed from quiz questions and lesson records';
            
            // === PHASE 7: LEARNDASH FUNCTIONS (IF AVAILABLE) ===
            
            // Call LearnDash functions if available
            if (function_exists('ld_update_course_access') && $course_id) {
                ld_update_course_access($user_id, $course_id, true);
                $success_messages[] = 'Called ld_update_course_access for course ' . $course_id;
            }
            
            if (function_exists('learndash_user_unenroll_course') && $course_id) {
                learndash_user_unenroll_course($user_id, $course_id);
                $success_messages[] = 'Called learndash_user_unenroll_course for course ' . $course_id;
            }
            
            // === VERIFICATION ===
            
            // Count remaining references for verification
            $remaining_usermeta = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM edc_usermeta WHERE user_id = %d AND (meta_key LIKE '%course%' OR meta_key LIKE '%learndash%' OR meta_key LIKE '%group%')",
                $user_id
            ));
            
            $remaining_postmeta = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM edc_postmeta WHERE meta_value LIKE %s",
                '%' . $user_id . '%'
            ));
            
            $remaining_activity = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM edc_learndash_user_activity WHERE user_id = %d",
                $user_id
            ));
            
            $success_messages[] = "Verification: {$remaining_usermeta} usermeta, {$remaining_postmeta} postmeta, {$remaining_activity} activity records remaining";
            
            wp_send_json_success('COMPREHENSIVE DISCONNECTION COMPLETED: ' . implode(' | ', $success_messages));
        } elseif ($action === 'connect') {
            // COMPREHENSIVE CONNECTION LOGIC - Based on documented successful connection methods
            global $wpdb;
            $success_messages = [];
            
            // === PHASE 1: USER META SETUP ===
            
            // Set course access expiration (permanent or with expiration)
            $expiration_timestamp = ($expiration === 'permanent') ? 0 : strtotime($expiration);
            update_user_meta($user_id, "course_{$course_id}_access_expires", $expiration_timestamp);
            $success_messages[] = 'Set course access expiration: ' . ($expiration === 'permanent' ? 'Permanent' : date('Y-m-d H:i:s', $expiration_timestamp));
            
            // Set course enrollment timestamp
            update_user_meta($user_id, "learndash_course_{$course_id}_enrolled_at", current_time('timestamp'));
            $success_messages[] = 'Set course enrollment timestamp';
            
            // Add to user's course list
            $user_courses = get_user_meta($user_id, '_sfwd-courses', true);
            if (!is_array($user_courses)) {
                $user_courses = [];
            }
            if (!in_array($course_id, $user_courses)) {
                $user_courses[] = $course_id;
                update_user_meta($user_id, '_sfwd-courses', $user_courses);
                $success_messages[] = 'Added to user course list (_sfwd-courses)';
            }
            
            // === PHASE 2: LEARNDASH ACTIVITY SETUP ===
            
            // Create LearnDash activity record
            $wpdb->insert(
                'edc_learndash_user_activity',
                [
                    'user_id' => $user_id,
                    'post_id' => $course_id,
                    'course_id' => $course_id,
                    'activity_type' => 'course',
                    'activity_status' => 1,
                    'activity_started' => current_time('timestamp'),
                    'activity_completed' => null,
                    'activity_updated' => current_time('timestamp')
                ],
                ['%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d']
            );
            $success_messages[] = 'Created LearnDash activity record';
            
            // === PHASE 3: POSTMETA SETUP (COURSE LISTS) ===
            
            // Add user to course access list
            $course_access_list = get_post_meta($course_id, 'course_access_list', true);
            if (!is_array($course_access_list)) {
                $course_access_list = [];
            }
            if (!in_array($user_id, $course_access_list)) {
                $course_access_list[] = $user_id;
                update_post_meta($course_id, 'course_access_list', $course_access_list);
                $success_messages[] = 'Added to course access list';
            }
            
            // Add user to course users list (learndash_course_users)
            $course_users = get_post_meta($course_id, 'learndash_course_users', true);
            if (!is_array($course_users)) {
                $course_users = [];
            }
            if (!in_array($user_id, $course_users)) {
                $course_users[] = $user_id;
                update_post_meta($course_id, 'learndash_course_users', $course_users);
                $success_messages[] = 'Added to learndash_course_users list';
            }
            
            // Add user to wrld course users list
            $wrld_course_users = get_post_meta($course_id, 'wrld_course_users', true);
            if (!is_array($wrld_course_users)) {
                $wrld_course_users = [];
            }
            if (!in_array($user_id, $wrld_course_users)) {
                $wrld_course_users[] = $user_id;
                update_post_meta($course_id, 'wrld_course_users', $wrld_course_users);
                $success_messages[] = 'Added to wrld_course_users list';
            }
            
            // === PHASE 4: LEARNDASH FUNCTIONS (IF AVAILABLE) ===
            
            // Call LearnDash enrollment functions if available
            if (function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $course_id, false);
                $success_messages[] = 'Called ld_update_course_access for enrollment';
            }
            
            if (function_exists('learndash_user_enroll_course')) {
                learndash_user_enroll_course($user_id, $course_id);
                $success_messages[] = 'Called learndash_user_enroll_course';
            }
            
            // === PHASE 5: WOOCOMMERCE INTEGRATION (OPTIONAL) ===
            
            // If this is part of a WooCommerce order, set the order connection
            if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
                $order_id = intval($_POST['order_id']);
                update_user_meta($user_id, "course_{$course_id}_order_id", $order_id);
                update_user_meta($user_id, "_learndash_woocommerce_enrolled_{$course_id}", current_time('timestamp'));
                $success_messages[] = 'Set WooCommerce order connection: ' . $order_id;
            }
            
            // === VERIFICATION ===
            
            // Verify connection was successful
            $course_access_expires = get_user_meta($user_id, "course_{$course_id}_access_expires", true);
            $course_enrolled_at = get_user_meta($user_id, "learndash_course_{$course_id}_enrolled_at", true);
            $user_courses_check = get_user_meta($user_id, '_sfwd-courses', true);
            
            $verification_status = [];
            $verification_status[] = 'Access expires: ' . ($course_access_expires === '0' ? 'Permanent' : date('Y-m-d H:i:s', $course_access_expires));
            $verification_status[] = 'Enrolled at: ' . date('Y-m-d H:i:s', $course_enrolled_at);
            $verification_status[] = 'In user course list: ' . (is_array($user_courses_check) && in_array($course_id, $user_courses_check) ? 'Yes' : 'No');
            
            $success_messages[] = 'Verification: ' . implode(', ', $verification_status);
            
            wp_send_json_success('COMPREHENSIVE CONNECTION COMPLETED: ' . implode(' | ', $success_messages));
        } else {
            // Update user meta with new expiration (using correct edc_ prefix)
            global $wpdb;
            $wpdb->replace(
                'edc_usermeta',
                [
                    'user_id' => $user_id,
                    'meta_key' => $meta_key,
                    'meta_value' => $expiration
                ],
                ['%d', '%s', '%s']
            );
            
            // Add user to course if not already enrolled
            if (function_exists('ld_update_course_access')) {
                ld_update_course_access($user_id, $course_id, false, $expiration);
            }
            
            // Also ensure user is enrolled in LearnDash
            if (function_exists('learndash_user_enroll_course')) {
                learndash_user_enroll_course($user_id, $course_id);
            }
            
            wp_send_json_success('Course expiration updated successfully');
        }
    }
    
    public function ajax_get_user_courses() {
        check_ajax_referer('unified_course_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Missing user ID');
        }
        
        $courses = $this->get_user_course_access($user_id);
        wp_send_json_success($courses);
    }
    
    public function ajax_get_available_courses() {
        check_ajax_referer('unified_course_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all LearnDash courses
        $courses = get_posts([
            'post_type' => 'sfwd-courses',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $course_list = [];
        foreach ($courses as $course) {
            $course_list[] = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'slug' => $course->post_name
            ];
        }
        
        wp_send_json_success($course_list);
    }
    
    public function ajax_bulk_update() {
        check_ajax_referer('unified_course_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Implementation for bulk updates
        wp_send_json_success('Bulk update completed');
    }
}

// Initialize the plugin
new Unified_Course_Expiration_Manager();
