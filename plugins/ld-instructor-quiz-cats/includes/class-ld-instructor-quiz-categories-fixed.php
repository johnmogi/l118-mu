<?php
/**
 * Main plugin class for LearnDash Instructor Quiz Categories
 */

if (!defined('ABSPATH')) {
    exit;
}

class LD_Instructor_Quiz_Categories {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add meta box to quiz edit screen
        add_action('add_meta_boxes', array($this, 'add_quiz_categories_meta_box'));
        
        // Save selected categories when quiz is saved
        add_action('save_post_sfwd-quiz', array($this, 'save_quiz_categories'));
        
        // Register AJAX handlers
        add_action('wp_ajax_test_quiz_population', array($this, 'ajax_test_quiz_population'));
        add_action('wp_ajax_nopriv_test_quiz_population', array($this, 'ajax_test_quiz_population'));
        add_action('wp_ajax_bulk_categorize_questions', array($this, 'ajax_bulk_categorize_questions'));
        add_action('wp_ajax_nopriv_bulk_categorize_questions', array($this, 'ajax_bulk_categorize_questions'));
        
        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('ld-instructor-quiz-cats', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Add meta box to quiz edit screen
     */
    public function add_quiz_categories_meta_box() {
        add_meta_box(
            'ld-instructor-quiz-categories',
            __('Quiz Question Categories', 'ld-instructor-quiz-cats'),
            array($this, 'render_quiz_categories_meta_box'),
            'sfwd-quiz',
            'normal',
            'high'
        );
    }
    
    /**
     * Render the quiz categories meta box
     */
    public function render_quiz_categories_meta_box($post) {
        // Get the taxonomy that questions actually use
        $used_taxonomy = $this->get_used_taxonomy();
        
        // Get all question categories
        $question_categories = get_terms(array(
            'taxonomy' => $used_taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        // Get currently selected categories
        $selected_categories = get_post_meta($post->ID, '_ld_quiz_question_categories', true);
        if (!is_array($selected_categories)) {
            $selected_categories = array();
        }
        
        // Include the template
        include LD_INSTRUCTOR_QUIZ_CATS_PLUGIN_DIR . 'templates/meta-box-quiz-categories.php';
        
        // Include debug info
        include LD_INSTRUCTOR_QUIZ_CATS_PLUGIN_DIR . 'templates/debug-info.php';
    }
    
    /**
     * Save selected quiz categories
     */
    public function save_quiz_categories($post_id) {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['ld_instructor_quiz_categories_nonce']) || 
            !wp_verify_nonce($_POST['ld_instructor_quiz_categories_nonce'], 'save_quiz_categories')) {
            return;
        }
        
        // Save selected categories
        if (isset($_POST['ld_instructor_quiz_categories']) && is_array($_POST['ld_instructor_quiz_categories'])) {
            $selected_categories = array_map('intval', $_POST['ld_instructor_quiz_categories']);
            update_post_meta($post_id, '_ld_quiz_question_categories', $selected_categories);
            
            // Auto-populate quiz with questions from selected categories
            $this->populate_quiz_with_questions($post_id, $selected_categories);
        } else {
            // No categories selected, clear the meta and questions
            delete_post_meta($post_id, '_ld_quiz_question_categories');
            $this->clear_quiz_questions($post_id);
        }
    }
    
    /**
     * Get the taxonomy that questions actually use
     */
    private function get_used_taxonomy() {
        // Get a sample of actual questions to see what taxonomies they use
        $sample_questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'fields' => 'ids'
        ));
        
        if (empty($sample_questions)) {
            return 'ld_quiz_category'; // fallback
        }
        
        // Check what taxonomies these questions actually have terms in
        $taxonomy_usage = array();
        
        foreach ($sample_questions as $question_id) {
            $question_taxonomies = get_object_taxonomies('sfwd-question');
            
            foreach ($question_taxonomies as $taxonomy) {
                $terms = wp_get_post_terms($question_id, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    if (!isset($taxonomy_usage[$taxonomy])) {
                        $taxonomy_usage[$taxonomy] = 0;
                    }
                    $taxonomy_usage[$taxonomy] += count($terms);
                }
            }
        }
        
        // Return the taxonomy with the most usage
        if (!empty($taxonomy_usage)) {
            arsort($taxonomy_usage);
            $most_used_taxonomy = array_key_first($taxonomy_usage);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LD Quiz Categories: Detected taxonomy usage: ' . print_r($taxonomy_usage, true));
                error_log('LD Quiz Categories: Using taxonomy: ' . $most_used_taxonomy);
            }
            
            return $most_used_taxonomy;
        }
        
        // Fallback to ld_quiz_category
        return 'ld_quiz_category';
    }
    
    /**
     * Populate quiz with questions from selected categories
     */
    private function populate_quiz_with_questions($quiz_id, $selected_categories) {
        if (empty($selected_categories)) {
            return;
        }
        
        $taxonomy = $this->get_used_taxonomy();
        
        // Get questions from selected categories
        $questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'operator' => 'IN'
                )
            ),
            'fields' => 'ids'
        ));
        
        if (!empty($questions)) {
            // Get existing quiz questions
            $existing_questions = get_post_meta($quiz_id, 'ld_quiz_questions', true);
            if (!is_array($existing_questions)) {
                $existing_questions = array();
            }
            
            // Merge with new questions (avoid duplicates)
            $updated_questions = array_unique(array_merge($existing_questions, $questions));
            
            // Update quiz questions
            update_post_meta($quiz_id, 'ld_quiz_questions', $updated_questions);
            
            // Mark quiz as dirty for LearnDash to rebuild
            update_post_meta($quiz_id, 'ld_quiz_questions_dirty', time());
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('LD Quiz Categories: Added ' . count($questions) . ' questions to quiz ' . $quiz_id . '. Total questions: ' . count($updated_questions));
            }
        }
    }
    
    /**
     * Clear quiz questions
     */
    private function clear_quiz_questions($quiz_id) {
        delete_post_meta($quiz_id, 'ld_quiz_questions');
        delete_post_meta($quiz_id, 'ld_quiz_questions_dirty');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('LD Quiz Categories: Cleared questions from quiz ' . $quiz_id);
        }
    }
    
    /**
     * AJAX handler for testing quiz population
     */
    public function ajax_test_quiz_population() {
        // Verify nonce
        $quiz_id = intval($_POST['quiz_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'test_population_' . $quiz_id)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $quiz_id)) {
            wp_die('Permission denied');
        }
        
        $selected_categories = array_map('intval', $_POST['categories']);
        
        if (empty($selected_categories)) {
            wp_send_json_error(array('message' => 'No categories selected'));
        }
        
        // Test question retrieval
        $taxonomy = $this->get_used_taxonomy();
        $questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $selected_categories,
                    'operator' => 'IN'
                )
            )
        ));
        
        // Get total questions for comparison
        $all_questions = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));
        
        // Check what taxonomies the first few questions actually have
        $sample_question_taxonomies = array();
        if (!empty($all_questions)) {
            $sample_questions = array_slice($all_questions, 0, 5);
            foreach ($sample_questions as $question) {
                $question_taxonomies = get_object_taxonomies($question->post_type);
                foreach ($question_taxonomies as $tax) {
                    $terms = wp_get_post_terms($question->ID, $tax);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $sample_question_taxonomies[$tax] = count($terms);
                    }
                }
            }
        }
        
        $taxonomy_info = '';
        if (!empty($sample_question_taxonomies)) {
            $taxonomy_info = ' | Questions actually use: ' . implode(', ', array_keys($sample_question_taxonomies));
        }
        
        $message = sprintf(
            'Found %d questions in selected categories (out of %d total questions). Using taxonomy: %s. Categories: %s%s',
            count($questions),
            count($all_questions),
            $taxonomy,
            implode(', ', $selected_categories),
            $taxonomy_info
        );
        
        if (count($questions) > 0) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => $message));
        }
    }
    
    /**
     * AJAX handler for bulk categorizing questions (not needed - questions are already categorized)
     */
    public function ajax_bulk_categorize_questions() {
        wp_send_json_error('Bulk categorization is not needed. Questions are already properly categorized. The issue is taxonomy detection.');
    }
    
    /**
     * Get plugin version
     */
    public function get_version() {
        return LD_INSTRUCTOR_QUIZ_CATS_VERSION;
    }
}
