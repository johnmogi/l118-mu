<?php
/**
 * Plugin Name: Simple Quiz Populator
 * Description: Simple inline quiz population tool that works without external dependencies
 * Version: 1.0.0
 * Author: Custom Development
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleQuizPopulator {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_simple_populator'], 25);
        add_action('wp_ajax_sqp_populate', [$this, 'ajax_populate']);
        add_action('admin_footer', [$this, 'add_inline_script']);
    }
    
    public function add_simple_populator() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        add_meta_box(
            'sqp-simple-tool',
            '⚡ Simple Quiz Populator',
            [$this, 'render_simple_tool'],
            'sfwd-quiz',
            'side',
            'high'
        );
    }
    
    public function render_simple_tool($post) {
        wp_nonce_field('sqp_nonce', 'sqp_nonce_field');
        
        // Get categories with question counts
        $categories = $this->get_categories_with_counts();
        
        echo '<div style="padding: 15px;">';
        echo '<p><strong>Select categories and populate your quiz:</strong></p>';
        
        if (empty($categories)) {
            echo '<p style="color: #d63638;">No categories with questions found.</p>';
            return;
        }
        
        echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
        
        foreach ($categories as $cat) {
            $checked = '';
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="checkbox" name="sqp_categories[]" value="' . $cat['id'] . '" ' . $checked . '> ';
            echo '<strong>' . esc_html($cat['name']) . '</strong> ';
            echo '<span style="color: #0073aa;">(' . $cat['count'] . ' questions)</span>';
            echo '</label>';
        }
        
        echo '</div>';
        
        echo '<div style="margin: 15px 0;">';
        echo '<label>Questions per category: ';
        echo '<input type="number" id="sqp-per-category" value="5" min="1" max="20" style="width: 60px;"></label>';
        echo '</div>';
        
        echo '<button type="button" id="sqp-populate-btn" class="button button-primary" style="width: 100%; height: 40px;">';
        echo '🚀 Populate Quiz Now';
        echo '</button>';
        
        echo '<div id="sqp-result" style="margin-top: 15px; padding: 10px; display: none;"></div>';
        echo '</div>';
    }
    
    public function add_inline_script() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        ?>
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var populateBtn = document.getElementById('sqp-populate-btn');
            var resultDiv = document.getElementById('sqp-result');
            
            if (!populateBtn) return;
            
            populateBtn.addEventListener('click', function() {
                var checkboxes = document.querySelectorAll('input[name="sqp_categories[]"]:checked');
                var perCategory = document.getElementById('sqp-per-category').value || 5;
                
                if (checkboxes.length === 0) {
                    alert('Please select at least one category');
                    return;
                }
                
                var categories = [];
                checkboxes.forEach(function(cb) {
                    categories.push(cb.value);
                });
                
                populateBtn.disabled = true;
                populateBtn.textContent = 'Populating...';
                
                var formData = new FormData();
                formData.append('action', 'sqp_populate');
                formData.append('nonce', document.getElementById('sqp_nonce_field').value);
                formData.append('quiz_id', <?php echo $post->ID; ?>);
                formData.append('categories', JSON.stringify(categories));
                formData.append('per_category', perCategory);
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    populateBtn.disabled = false;
                    populateBtn.textContent = '🚀 Populate Quiz Now';
                    
                    if (data.success) {
                        resultDiv.style.display = 'block';
                        resultDiv.style.background = '#d1edff';
                        resultDiv.style.border = '1px solid #0073aa';
                        resultDiv.innerHTML = '<strong>✅ Success!</strong><br>' + data.data.message;
                        
                        // Auto-save the post
                        if (typeof wp !== 'undefined' && wp.data) {
                            wp.data.dispatch('core/editor').savePost();
                        }
                    } else {
                        resultDiv.style.display = 'block';
                        resultDiv.style.background = '#ffebe8';
                        resultDiv.style.border = '1px solid #d63638';
                        resultDiv.innerHTML = '<strong>❌ Error:</strong><br>' + data.data;
                    }
                })
                .catch(error => {
                    populateBtn.disabled = false;
                    populateBtn.textContent = '🚀 Populate Quiz Now';
                    alert('Network error: ' + error.message);
                });
            });
        });
        </script>
        <?php
    }
    
    public function ajax_populate() {
        check_ajax_referer('sqp_nonce', 'nonce');
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $categories = json_decode($_POST['categories'] ?? '[]', true);
        $per_category = intval($_POST['per_category'] ?? 5);
        
        if (!$quiz_id || empty($categories)) {
            wp_send_json_error('Invalid data provided');
        }
        
        $question_ids = [];
        $category_names = [];
        
        foreach ($categories as $cat_id) {
            $cat_id = intval($cat_id);
            $cat = get_term($cat_id, 'ld_quiz_category');
            
            if (!$cat || is_wp_error($cat)) {
                continue;
            }
            
            $category_names[] = $cat->name;
            
            // Get questions for this category
            $questions = $this->get_questions_for_category($cat_id, $per_category);
            $question_ids = array_merge($question_ids, $questions);
        }
        
        if (empty($question_ids)) {
            wp_send_json_error('No questions found in selected categories');
        }
        
        // Update quiz with questions
        $existing = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        
        // Merge and remove duplicates
        $all_questions = array_unique(array_merge($existing, $question_ids));
        
        $success = update_post_meta($quiz_id, 'ld_quiz_questions', $all_questions);
        
        if ($success) {
            $message = sprintf(
                'Added %d questions from %s to the quiz!',
                count($question_ids),
                implode(', ', $category_names)
            );
            wp_send_json_success(['message' => $message, 'count' => count($question_ids)]);
        } else {
            wp_send_json_error('Failed to update quiz questions');
        }
    }
    
    private function get_categories_with_counts() {
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
        
        if (is_wp_error($categories) || empty($categories)) {
            return [];
        }
        
        $result = [];
        foreach ($categories as $cat) {
            $count = $this->count_questions_in_category($cat->term_id);
            if ($count > 0) {
                $result[] = [
                    'id' => $cat->term_id,
                    'name' => $cat->name,
                    'count' => $count
                ];
            }
        }
        
        return $result;
    }
    
    private function count_questions_in_category($category_id) {
        // Try taxonomy first
        $tax_questions = get_posts([
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
        
        // Try meta query as fallback
        $meta_questions = get_posts([
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
        
        // Return the higher count
        return max(count($tax_questions), count($meta_questions));
    }
    
    private function get_questions_for_category($category_id, $limit) {
        // Try taxonomy first
        $questions = get_posts([
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'rand',
            'tax_query' => [
                [
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $category_id
                ]
            ]
        ]);
        
        // If no results, try meta query
        if (empty($questions)) {
            $questions = get_posts([
                'post_type' => 'sfwd-question',
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'fields' => 'ids',
                'orderby' => 'rand',
                'meta_query' => [
                    [
                        'key' => 'question_pro_category',
                        'value' => $category_id,
                        'compare' => '='
                    ]
                ]
            ]);
        }
        
        return $questions;
    }
}

new SimpleQuizPopulator();
?>
