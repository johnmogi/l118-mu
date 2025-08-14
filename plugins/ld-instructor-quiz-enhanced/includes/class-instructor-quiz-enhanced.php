<?php
/**
 * Enhanced LearnDash Instructor Quiz Categories
 * Specifically designed for instructor role with proper question population
 */

if (!defined('ABSPATH')) {
    exit;
}

class LD_Instructor_Quiz_Enhanced {
    
    private $instructor_roles = array('wdm_instructor', 'instructor', 'group_leader');
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register taxonomy first
        add_action('init', array($this, 'register_quiz_category_taxonomy'), 5);
        
        // Core hooks
        add_action('add_meta_boxes', array($this, 'add_quiz_categories_meta_box'));
        add_action('save_post_sfwd-quiz', array($this, 'save_quiz_categories'));
        
        // AJAX handlers
        add_action('wp_ajax_ld_enhanced_test_population', array($this, 'ajax_test_population'));
        add_action('wp_ajax_ld_enhanced_populate_quiz', array($this, 'ajax_populate_quiz'));
        
        // Instructor capabilities
        add_action('init', array($this, 'ensure_instructor_capabilities'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Register the quiz category taxonomy for both quizzes and questions
     */
    public function register_quiz_category_taxonomy() {
        // Check if taxonomy already exists and is properly registered
        $taxonomy_obj = get_taxonomy('ld_quiz_category');
        
        // If taxonomy exists but doesn't include sfwd-question, re-register it
        if ($taxonomy_obj && !in_array('sfwd-question', $taxonomy_obj->object_type)) {
            // Unregister the existing taxonomy
            global $wp_taxonomies;
            unset($wp_taxonomies['ld_quiz_category']);
        }
        
        // Register or re-register the taxonomy for both post types
        if (!taxonomy_exists('ld_quiz_category') || 
            ($taxonomy_obj && !in_array('sfwd-question', $taxonomy_obj->object_type))) {
            
            register_taxonomy(
                'ld_quiz_category',
                ['sfwd-quiz', 'sfwd-question'], // Register for both quiz and question post types
                [
                    'labels' => [
                        'name' => __('Quiz Categories', 'ld-instructor-quiz-enhanced'),
                        'singular_name' => __('Quiz Category', 'ld-instructor-quiz-enhanced'),
                        'search_items' => __('Search Quiz Categories', 'ld-instructor-quiz-enhanced'),
                        'all_items' => __('All Quiz Categories', 'ld-instructor-quiz-enhanced'),
                        'parent_item' => __('Parent Quiz Category', 'ld-instructor-quiz-enhanced'),
                        'parent_item_colon' => __('Parent Quiz Category:', 'ld-instructor-quiz-enhanced'),
                        'edit_item' => __('Edit Quiz Category', 'ld-instructor-quiz-enhanced'),
                        'update_item' => __('Update Quiz Category', 'ld-instructor-quiz-enhanced'),
                        'add_new_item' => __('Add New Quiz Category', 'ld-instructor-quiz-enhanced'),
                        'new_item_name' => __('New Quiz Category Name', 'ld-instructor-quiz-enhanced'),
                        'menu_name' => __('Quiz Categories', 'ld-instructor-quiz-enhanced'),
                    ],
                    'hierarchical' => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true,
                    'rewrite' => ['slug' => 'quiz-category'],
                    'show_in_rest' => true, // Enable for Gutenberg
                    'public' => true,
                ]
            );
            
            // Clear any existing term caches
            clean_taxonomy_cache('ld_quiz_category');
        }
    }
    
    /**
     * Add meta box for quiz categories
     */
    public function add_quiz_categories_meta_box() {
        // Only show to users who can edit quizzes
        if ($this->current_user_can_edit_quizzes()) {
            add_meta_box(
                'ld-enhanced-quiz-categories',
                __('Quiz Question Categories (Enhanced)', 'ld-instructor-quiz-enhanced'),
                array($this, 'render_quiz_categories_meta_box'),
                'sfwd-quiz',
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render the quiz categories meta box
     */
    public function render_quiz_categories_meta_box($post) {
        // Security check
        if (!$this->user_can_edit_quiz($post->ID)) {
            echo '<p style="color: #d63638;">' . __('You do not have permission to edit categories for this quiz.', 'ld-instructor-quiz-enhanced') . '</p>';
            return;
        }
        
        // Get question taxonomy
        $taxonomy = $this->get_question_taxonomy();
        
        // Get all categories
        $categories = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => 0
        ));
        
        // Get selected categories
        $selected_categories = get_post_meta($post->ID, '_ld_quiz_question_categories', true);
        if (!is_array($selected_categories)) {
            $selected_categories = array();
        }
        
        // Get current quiz questions count
        $current_questions = $this->get_quiz_questions_count($post->ID);
        
        // Nonce field
        wp_nonce_field('save_quiz_categories_enhanced', 'ld_enhanced_quiz_categories_nonce');
        
        ?>
        <div class="ld-enhanced-quiz-categories-wrapper">
            <div class="ld-enhanced-debug-info" style="background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; padding: 10px; margin-bottom: 15px;">
                <strong>📊 Debug Info:</strong><br>
                <small style="color: #666;">
                    Found <?php echo count($categories); ?> question categories<br>
                    Using Taxonomy: <?php echo esc_html($taxonomy); ?><br>
                    Current Questions in Quiz: <strong><?php echo $current_questions; ?></strong><br>
                    Post Type: <?php echo get_post_type($post); ?><br>
                    Plugin Version: <?php echo LD_INSTRUCTOR_QUIZ_ENHANCED_VERSION; ?><br>
                    Post ID: <?php echo $post->ID; ?><br>
                    User Role: <?php echo implode(', ', wp_get_current_user()->roles); ?>
                </small>
            </div>
            
            <p><strong><?php _e('Select question categories to include in this quiz:', 'ld-instructor-quiz-enhanced'); ?></strong></p>
            
            <?php if (!empty($selected_categories)): ?>
                <div class="ld-selected-info" style="background: #e7f3ff; border-left: 3px solid #0073aa; padding: 8px 12px; margin-bottom: 15px;">
                    <small style="color: #0073aa; font-weight: 500;">
                        Currently selected: <?php echo count($selected_categories); ?> categories
                    </small>
                </div>
            <?php endif; ?>
            
            <div class="ld-categories-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; margin-bottom: 20px;">
                <?php foreach ($categories as $category): 
                    $is_selected = in_array($category->term_id, $selected_categories);
                    $questions_in_category = $this->get_questions_in_category($category->term_id, $taxonomy);
                ?>
                    <label class="ld-category-item" style="display: block; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.2s; <?php echo $is_selected ? 'background: #e7f3ff; border-color: #0073aa;' : ''; ?>">
                        <input 
                            type="checkbox" 
                            name="ld_enhanced_quiz_categories[]" 
                            value="<?php echo esc_attr($category->term_id); ?>"
                            <?php checked($is_selected); ?>
                            style="margin-right: 8px;"
                        >
                        <strong><?php echo esc_html($category->name); ?></strong>
                        <small style="color: #666; display: block; margin-top: 4px;">
                            <?php echo $questions_in_category; ?> questions available
                        </small>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <div class="ld-actions" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">
                <h4 style="margin: 0 0 10px 0;">🔧 Quiz Population Actions</h4>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="ld-enhanced-test-population" class="button button-primary">
                        Test Population (Preview)
                    </button>
                    <button type="button" id="ld-enhanced-populate-quiz" class="button button-secondary">
                        Populate Quiz Now
                    </button>
                    <button type="button" id="ld-enhanced-clear-quiz" class="button" style="background: #d63638; color: white; border-color: #d63638;">
                        Clear All Questions
                    </button>
                </div>
                <div id="ld-enhanced-results" style="margin-top: 15px; display: none;"></div>
                <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                    💡 <strong>Tip:</strong> Test Population shows what questions would be added. Populate Quiz actually adds them to the quiz.
                </p>
            </div>
        </div>
        
        <style>
        .ld-category-item:hover {
            background: #f0f0f1 !important;
            border-color: #0073aa !important;
        }
        .ld-category-item input:checked + strong {
            color: #0073aa;
        }
        </style>
        <?php
    }
    
    /**
     * Save quiz categories
     */
    public function save_quiz_categories($post_id) {
        // Prevent infinite loops
        static $processing = array();
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;
        
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            unset($processing[$post_id]);
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['ld_enhanced_quiz_categories_nonce']) || 
            !wp_verify_nonce($_POST['ld_enhanced_quiz_categories_nonce'], 'save_quiz_categories_enhanced')) {
            unset($processing[$post_id]);
            return;
        }
        
        // Check permissions
        if (!$this->user_can_edit_quiz($post_id)) {
            unset($processing[$post_id]);
            return;
        }
        
        // Save selected categories
        if (isset($_POST['ld_enhanced_quiz_categories']) && is_array($_POST['ld_enhanced_quiz_categories'])) {
            $selected_categories = array_map('intval', $_POST['ld_enhanced_quiz_categories']);
            update_post_meta($post_id, '_ld_quiz_question_categories', $selected_categories);
        } else {
            delete_post_meta($post_id, '_ld_quiz_question_categories');
        }
        
        unset($processing[$post_id]);
    }
    
    /**
     * AJAX: Test population (preview)
     */
    public function ajax_test_population() {
        // Security checks
        if (!wp_verify_nonce($_POST['nonce'], 'ld_enhanced_test_population')) {
            wp_send_json_error('Security check failed');
        }
        
        $quiz_id = intval($_POST['quiz_id']);
        $categories = array_map('intval', $_POST['categories']);
        
        if (!$this->user_can_edit_quiz($quiz_id)) {
            wp_send_json_error('Permission denied');
        }
        
        if (empty($categories)) {
            wp_send_json_error('Please select at least one category');
        }
        
        // Get questions that would be added
        $questions = $this->get_questions_from_categories($categories, 300); // Limit to 300
        
        $message = sprintf(
            'Found %d questions from %d categories that would be added to the quiz.',
            count($questions),
            count($categories)
        );
        
        wp_send_json_success($message);
    }
    
    /**
     * AJAX: Actually populate the quiz
     */
    public function ajax_populate_quiz() {
        // Security checks
        if (!wp_verify_nonce($_POST['nonce'], 'ld_enhanced_populate_quiz')) {
            wp_send_json_error('Security check failed');
        }
        
        $quiz_id = intval($_POST['quiz_id']);
        $categories = array_map('intval', $_POST['categories']);
        
        if (!$this->user_can_edit_quiz($quiz_id)) {
            wp_send_json_error('Permission denied');
        }
        
        if (empty($categories)) {
            wp_send_json_error('Please select at least one category');
        }
        
        // Get questions and populate quiz
        $questions = $this->get_questions_from_categories($categories, 300);
        $result = $this->populate_quiz_with_questions($quiz_id, $questions);
        
        if ($result) {
            $message = sprintf(
                'Successfully added %d questions to the quiz from %d categories.',
                count($questions),
                count($categories)
            );
            wp_send_json_success($message);
        } else {
            wp_send_json_error('Failed to populate quiz with questions');
        }
    }
    
    /**
     * Get questions from selected categories
     */
    private function get_questions_from_categories($category_ids, $limit = 300) {
        $taxonomy = $this->get_question_taxonomy();
        
        $questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'rand',
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $category_ids,
                    'operator' => 'IN'
                )
            )
        ));
        
        return $questions;
    }
    
    /**
     * Actually populate quiz with questions
     */
    private function populate_quiz_with_questions($quiz_id, $questions) {
        if (empty($questions)) {
            return false;
        }
        
        // Get existing quiz questions
        $existing_questions = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($existing_questions)) {
            $existing_questions = array();
        }
        
        // Add new questions
        foreach ($questions as $question) {
            $question_data = array(
                'id' => $question->ID,
                'type' => get_post_meta($question->ID, 'question_type', true) ?: 'single',
                'points' => get_post_meta($question->ID, '_question_points', true) ?: 1
            );
            
            // Avoid duplicates
            $exists = false;
            foreach ($existing_questions as $existing) {
                if (isset($existing['id']) && $existing['id'] == $question->ID) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $existing_questions[] = $question_data;
            }
        }
        
        // Update quiz questions
        update_post_meta($quiz_id, 'ld_quiz_questions', $existing_questions);
        
        return true;
    }
    
    /**
     * Get current quiz questions count
     */
    private function get_quiz_questions_count($quiz_id) {
        $questions = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        return is_array($questions) ? count($questions) : 0;
    }
    
    /**
     * Get questions count in a specific category
     * Uses the same method as the working original plugin
     */
    private function get_questions_in_category($category_id, $taxonomy) {
        // Get the term object to access the built-in count
        $term = get_term($category_id, $taxonomy);
        
        if (is_wp_error($term) || !$term) {
            return 0;
        }
        
        // Use the built-in WordPress term count (same as original working plugin)
        $count = intval($term->count);
        
        // Debug logging for troubleshooting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("LD Enhanced: Category {$category_id} ({$taxonomy}) has {$count} questions (using term->count)");
        }
        
        return $count;
    }
    
    /**
     * Get the question taxonomy
     */
    private function get_question_taxonomy() {
        // Check what taxonomy is actually used by questions
        $sample_questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'fields' => 'ids'
        ));
        
        $taxonomy_usage = array();
        
        foreach ($sample_questions as $question_id) {
            $taxonomies = get_object_taxonomies('sfwd-question');
            
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($question_id, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    if (!isset($taxonomy_usage[$taxonomy])) {
                        $taxonomy_usage[$taxonomy] = 0;
                    }
                    $taxonomy_usage[$taxonomy] += count($terms);
                }
            }
        }
        
        if (!empty($taxonomy_usage)) {
            arsort($taxonomy_usage);
            return array_key_first($taxonomy_usage);
        }
        
        return 'ld_quiz_category'; // fallback
    }
    
    /**
     * Check if current user can edit quizzes
     */
    private function current_user_can_edit_quizzes() {
        return current_user_can('manage_options') || 
               current_user_can('edit_others_posts') || 
               $this->is_instructor();
    }
    
    /**
     * Check if user can edit specific quiz
     */
    private function user_can_edit_quiz($quiz_id) {
        // Admin can edit any quiz
        if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
            return true;
        }
        
        // Standard edit check
        if (current_user_can('edit_post', $quiz_id)) {
            return true;
        }
        
        // Instructor check
        if ($this->is_instructor()) {
            $user_id = get_current_user_id();
            $quiz_author = get_post_field('post_author', $quiz_id);
            return ($quiz_author == $user_id);
        }
        
        return false;
    }
    
    /**
     * Check if user is instructor
     */
    private function is_instructor() {
        $user = wp_get_current_user();
        
        foreach ($this->instructor_roles as $role) {
            if (in_array($role, (array) $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Ensure instructor capabilities
     */
    public function ensure_instructor_capabilities() {
        foreach ($this->instructor_roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('edit_sfwd-quizzes');
                $role->add_cap('edit_published_sfwd-quizzes');
                $role->add_cap('edit_sfwd-questions');
                $role->add_cap('edit_published_sfwd-questions');
                $role->add_cap('edit_posts');
                $role->add_cap('edit_published_posts');
            }
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Test Population
            $('#ld-enhanced-test-population').click(function() {
                var button = $(this);
                var results = $('#ld-enhanced-results');
                
                button.prop('disabled', true).text('Testing...');
                results.hide();
                
                var categories = [];
                $('input[name="ld_enhanced_quiz_categories[]"]:checked').each(function() {
                    categories.push($(this).val());
                });
                
                if (categories.length === 0) {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">Please select at least one category</div>').show();
                    button.prop('disabled', false).text('Test Population (Preview)');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ld_enhanced_test_population',
                        quiz_id: <?php echo get_the_ID(); ?>,
                        categories: categories,
                        nonce: '<?php echo wp_create_nonce('ld_enhanced_test_population'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data + '</div>').show();
                        } else {
                            results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data + '</div>').show();
                        }
                    },
                    error: function() {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Population (Preview)');
                    }
                });
            });
            
            // Populate Quiz
            $('#ld-enhanced-populate-quiz').click(function() {
                var button = $(this);
                var results = $('#ld-enhanced-results');
                
                var categories = [];
                $('input[name="ld_enhanced_quiz_categories[]"]:checked').each(function() {
                    categories.push($(this).val());
                });
                
                if (categories.length === 0) {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">Please select at least one category</div>').show();
                    return;
                }
                
                if (!confirm('This will add questions to the quiz. Continue?')) {
                    return;
                }
                
                button.prop('disabled', true).text('Populating...');
                results.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ld_enhanced_populate_quiz',
                        quiz_id: <?php echo get_the_ID(); ?>,
                        categories: categories,
                        nonce: '<?php echo wp_create_nonce('ld_enhanced_populate_quiz'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data + ' <strong>Please refresh the page to see updated question count.</strong></div>').show();
                        } else {
                            results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data + '</div>').show();
                        }
                    },
                    error: function() {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Populate Quiz Now');
                    }
                });
            });
            
            // Clear Quiz
            $('#ld-enhanced-clear-quiz').click(function() {
                if (!confirm('This will remove ALL questions from the quiz. Are you sure?')) {
                    return;
                }
                
                // Clear quiz questions meta
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wp_ajax_inline_save',
                        post_type: 'sfwd-quiz',
                        post_ID: <?php echo get_the_ID(); ?>,
                        ld_quiz_questions: []
                    },
                    success: function() {
                        $('#ld-enhanced-results').html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ Quiz questions cleared. Please refresh the page.</div>').show();
                    }
                });
            });
        });
        </script>
        <?php
    }
}
