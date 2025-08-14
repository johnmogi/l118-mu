<?php
/**
 * Plugin Name: Quiz Population Tool
 * Description: Standalone popup tool for populating quizzes with questions from categories
 * Version: 1.0.0
 * Author: Custom Development
 */

if (!defined('ABSPATH')) {
    exit;
}

class QuizPopulationTool {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('add_meta_boxes', [$this, 'add_popup_button'], 20);
        add_action('wp_ajax_qpt_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_qpt_get_questions', [$this, 'ajax_get_questions']);
        add_action('wp_ajax_qpt_populate_quiz', [$this, 'ajax_populate_quiz']);
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');
        
        // Use absolute URL for assets
        $plugin_url = plugin_dir_url(__FILE__);
        
        wp_enqueue_script('qpt-popup', $plugin_url . 'assets/quiz-popup.js', ['jquery'], '1.0.1', true);
        wp_enqueue_style('qpt-popup', $plugin_url . 'assets/quiz-popup.css', [], '1.0.1');
        
        wp_localize_script('qpt-popup', 'qpt_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('qpt_nonce'),
            'quiz_id' => $post->ID
        ]);
    }
    
    public function add_popup_button() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        add_meta_box(
            'qpt-popup-tool',
            '🎯 Quiz Population Tool',
            [$this, 'render_popup_button'],
            'sfwd-quiz',
            'side',
            'high'
        );
    }
    
    public function render_popup_button($post) {
        echo '<div style="text-align: center; padding: 15px;">';
        echo '<button type="button" id="qpt-open-popup" class="button button-primary button-large" style="width: 100%; height: 50px; font-size: 16px;">';
        echo '🚀 Open Population Tool';
        echo '</button>';
        echo '<p style="margin-top: 10px; color: #666; font-size: 12px;">Bypass plugin conflicts with this standalone tool</p>';
        echo '</div>';
        
        // Hidden popup HTML
        $this->render_popup_html();
    }
    
    private function render_popup_html() {
        ?>
        <div id="qpt-popup-overlay" style="display: none;">
            <div id="qpt-popup">
                <div class="qpt-header">
                    <h2>🎯 Quiz Population Tool</h2>
                    <button id="qpt-close" class="qpt-close">&times;</button>
                </div>
                
                <div class="qpt-content">
                    <div class="qpt-step" id="qpt-step-1">
                        <h3>Step 1: Select Categories</h3>
                        <div id="qpt-categories-loading">Loading categories...</div>
                        <div id="qpt-categories-list" style="display: none;"></div>
                        <div class="qpt-actions">
                            <button id="qpt-next-step" class="button button-primary" disabled>Next: Preview Questions</button>
                        </div>
                    </div>
                    
                    <div class="qpt-step" id="qpt-step-2" style="display: none;">
                        <h3>Step 2: Preview & Configure</h3>
                        <div class="qpt-config">
                            <label>
                                Questions per category: 
                                <input type="number" id="qpt-questions-per-cat" value="5" min="1" max="50">
                            </label>
                            <label>
                                <input type="checkbox" id="qpt-random-selection" checked>
                                Random selection
                            </label>
                        </div>
                        <div id="qpt-preview-loading" style="display: none;">Loading preview...</div>
                        <div id="qpt-preview-list"></div>
                        <div class="qpt-actions">
                            <button id="qpt-back-step" class="button">Back</button>
                            <button id="qpt-populate" class="button button-primary">Populate Quiz</button>
                        </div>
                    </div>
                    
                    <div class="qpt-step" id="qpt-step-3" style="display: none;">
                        <h3>Step 3: Success!</h3>
                        <div id="qpt-success-message"></div>
                        <div class="qpt-actions">
                            <button id="qpt-finish" class="button button-primary">Close & Save Quiz</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_get_categories() {
        check_ajax_referer('qpt_nonce', 'nonce');
        
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
        
        if (is_wp_error($categories)) {
            wp_send_json_error('Failed to load categories');
        }
        
        $result = [];
        foreach ($categories as $cat) {
            // Count questions using both methods for reliability
            $tax_count = $this->count_questions_by_taxonomy($cat->term_id);
            $meta_count = $this->count_questions_by_meta($cat->term_id);
            
            $result[] = [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'count' => max($tax_count, $meta_count), // Use the higher count
                'description' => $cat->description ?: ''
            ];
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_get_questions() {
        check_ajax_referer('qpt_nonce', 'nonce');
        
        $category_ids = array_map('intval', $_POST['categories'] ?? []);
        $per_category = intval($_POST['per_category'] ?? 5);
        $random = $_POST['random'] === 'true';
        
        if (empty($category_ids)) {
            wp_send_json_error('No categories selected');
        }
        
        $questions = [];
        foreach ($category_ids as $cat_id) {
            $cat_questions = $this->get_questions_for_category($cat_id, $per_category, $random);
            if (!empty($cat_questions)) {
                $questions[$cat_id] = $cat_questions;
            }
        }
        
        wp_send_json_success($questions);
    }
    
    public function ajax_populate_quiz() {
        check_ajax_referer('qpt_nonce', 'nonce');
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $questions = $_POST['questions'] ?? [];
        
        if (!$quiz_id || empty($questions)) {
            wp_send_json_error('Invalid data');
        }
        
        // Flatten question IDs
        $question_ids = [];
        foreach ($questions as $cat_questions) {
            $question_ids = array_merge($question_ids, array_column($cat_questions, 'id'));
        }
        
        // Update quiz with questions using LearnDash format
        $success = $this->update_quiz_questions($quiz_id, $question_ids);
        
        if ($success) {
            wp_send_json_success([
                'message' => 'Successfully added ' . count($question_ids) . ' questions to the quiz!',
                'count' => count($question_ids)
            ]);
        } else {
            wp_send_json_error('Failed to update quiz');
        }
    }
    
    private function count_questions_by_taxonomy($category_id) {
        $questions = get_posts([
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $category_id
                ]
            ]
        ]);
        
        return count($questions);
    }
    
    private function count_questions_by_meta($category_id) {
        $questions = get_posts([
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'question_pro_category',
                    'value' => $category_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        return count($questions);
    }
    
    private function get_questions_for_category($category_id, $limit, $random) {
        $args = [
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => $random ? -1 : $limit,
            'tax_query' => [
                [
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $category_id
                ]
            ]
        ];
        
        if ($random) {
            $args['orderby'] = 'rand';
        }
        
        $questions = get_posts($args);
        
        if ($random && count($questions) > $limit) {
            $questions = array_slice($questions, 0, $limit);
        }
        
        $result = [];
        foreach ($questions as $q) {
            $result[] = [
                'id' => $q->ID,
                'title' => $q->post_title,
                'excerpt' => wp_trim_words(strip_tags($q->post_content), 15)
            ];
        }
        
        return $result;
    }
    
    private function update_quiz_questions($quiz_id, $question_ids) {
        // Get existing questions
        $existing = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        
        // Merge with new questions (avoid duplicates)
        $all_questions = array_unique(array_merge($existing, $question_ids));
        
        // Update the quiz
        return update_post_meta($quiz_id, 'ld_quiz_questions', $all_questions);
    }
}

new QuizPopulationTool();
?>
