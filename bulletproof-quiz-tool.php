<?php
/**
 * Plugin Name: Bulletproof Quiz Tool
 * Description: Dead simple, guaranteed working quiz population tool
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BulletproofQuizTool {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('wp_ajax_bqt_populate', [$this, 'ajax_populate']);
        add_action('wp_ajax_bqt_get_counts', [$this, 'ajax_get_counts']);
    }
    
    public function add_meta_box() {
        global $post;
        if (!$post || $post->post_type !== 'sfwd-quiz') {
            return;
        }
        
        add_meta_box(
            'bulletproof-quiz-tool',
            '🔧 Bulletproof Quiz Tool',
            [$this, 'render_meta_box'],
            'sfwd-quiz',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        // First, let's show what we actually have in the system
        echo '<div style="background: #f0f8ff; padding: 15px; border: 1px solid #0073aa; margin-bottom: 20px;">';
        echo '<h3>🔍 System Status</h3>';
        
        // Count total questions
        $total_questions = wp_count_posts('sfwd-question');
        echo '<p><strong>Total Questions:</strong> ' . ($total_questions->publish ?? 0) . '</p>';
        
        // Check taxonomy
        $taxonomy_exists = taxonomy_exists('ld_quiz_category');
        echo '<p><strong>Quiz Category Taxonomy:</strong> ' . ($taxonomy_exists ? '✅ Exists' : '❌ Missing') . '</p>';
        
        // Count categories
        if ($taxonomy_exists) {
            $categories = get_terms(['taxonomy' => 'ld_quiz_category', 'hide_empty' => false]);
            $cat_count = is_array($categories) ? count($categories) : 0;
            echo '<p><strong>Total Categories:</strong> ' . $cat_count . '</p>';
        }
        
        // Current quiz questions
        $current_questions = get_post_meta($post->ID, 'ld_quiz_questions', true);
        $current_count = is_array($current_questions) ? count($current_questions) : 0;
        echo '<p><strong>Current Quiz Questions:</strong> ' . $current_count . '</p>';
        
        echo '</div>';
        
        // Show categories with REAL counts
        echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd;">';
        echo '<h3>📊 Categories & Question Counts</h3>';
        echo '<div id="bqt-categories">Loading...</div>';
        echo '<button type="button" id="bqt-refresh" class="button">🔄 Refresh Counts</button>';
        echo '</div>';
        
        // Simple population tool
        echo '<div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 15px;">';
        echo '<h3>⚡ Quick Population</h3>';
        echo '<p>Add questions from ALL categories:</p>';
        echo '<label>Questions per category: <input type="number" id="bqt-per-cat" value="3" min="1" max="10" style="width: 60px;"></label><br><br>';
        echo '<button type="button" id="bqt-populate-all" class="button button-primary">🚀 Add Questions Now</button>';
        echo '<div id="bqt-result" style="margin-top: 10px;"></div>';
        echo '</div>';
        
        // Add inline script
        $this->add_inline_script($post->ID);
    }
    
    private function add_inline_script($quiz_id) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const refreshBtn = document.getElementById('bqt-refresh');
            const populateBtn = document.getElementById('bqt-populate-all');
            const categoriesDiv = document.getElementById('bqt-categories');
            const resultDiv = document.getElementById('bqt-result');
            
            function loadCounts() {
                categoriesDiv.innerHTML = 'Loading...';
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=bqt_get_counts&nonce=<?php echo wp_create_nonce('bqt_nonce'); ?>'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        let html = '<table style="width: 100%; border-collapse: collapse;">';
                        html += '<tr><th style="border: 1px solid #ddd; padding: 8px;">Category</th><th style="border: 1px solid #ddd; padding: 8px;">Questions</th></tr>';
                        
                        data.data.forEach(cat => {
                            html += `<tr>
                                <td style="border: 1px solid #ddd; padding: 8px;">${cat.name}</td>
                                <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">
                                    <strong style="color: ${cat.count > 0 ? '#0073aa' : '#d63638'};">${cat.count}</strong>
                                </td>
                            </tr>`;
                        });
                        
                        html += '</table>';
                        categoriesDiv.innerHTML = html;
                    } else {
                        categoriesDiv.innerHTML = '<p style="color: red;">Error: ' + data.data + '</p>';
                    }
                })
                .catch(e => {
                    categoriesDiv.innerHTML = '<p style="color: red;">Network error: ' + e.message + '</p>';
                });
            }
            
            function populateQuiz() {
                const perCat = document.getElementById('bqt-per-cat').value || 3;
                
                populateBtn.disabled = true;
                populateBtn.textContent = 'Adding questions...';
                resultDiv.innerHTML = '';
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=bqt_populate&nonce=<?php echo wp_create_nonce('bqt_nonce'); ?>&quiz_id=<?php echo $quiz_id; ?>&per_category=${perCat}`
                })
                .then(r => r.json())
                .then(data => {
                    populateBtn.disabled = false;
                    populateBtn.textContent = '🚀 Add Questions Now';
                    
                    if (data.success) {
                        resultDiv.innerHTML = `<div style="background: #d1edff; padding: 10px; border: 1px solid #0073aa;">
                            <strong>✅ Success!</strong><br>${data.data.message}
                        </div>`;
                        
                        // Reload page to show updated question count
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        resultDiv.innerHTML = `<div style="background: #ffebe8; padding: 10px; border: 1px solid #d63638;">
                            <strong>❌ Error:</strong><br>${data.data}
                        </div>`;
                    }
                })
                .catch(e => {
                    populateBtn.disabled = false;
                    populateBtn.textContent = '🚀 Add Questions Now';
                    resultDiv.innerHTML = `<div style="background: #ffebe8; padding: 10px; border: 1px solid #d63638;">
                        <strong>❌ Network Error:</strong><br>${e.message}
                    </div>`;
                });
            }
            
            refreshBtn.addEventListener('click', loadCounts);
            populateBtn.addEventListener('click', populateQuiz);
            
            // Load counts on page load
            loadCounts();
        });
        </script>
        <?php
    }
    
    public function ajax_get_counts() {
        check_ajax_referer('bqt_nonce', 'nonce');
        
        $result = [];
        
        // Get all categories
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false,
            'orderby' => 'name'
        ]);
        
        if (is_wp_error($categories)) {
            wp_send_json_error('Failed to get categories: ' . $categories->get_error_message());
        }
        
        if (empty($categories)) {
            wp_send_json_error('No categories found. Please create some quiz categories first.');
        }
        
        foreach ($categories as $cat) {
            // Try multiple methods to count questions
            $counts = [];
            
            // Method 1: Taxonomy query
            $tax_questions = get_posts([
                'post_type' => 'sfwd-question',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [[
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $cat->term_id
                ]]
            ]);
            $counts[] = count($tax_questions);
            
            // Method 2: Meta query
            $meta_questions = get_posts([
                'post_type' => 'sfwd-question',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => 'question_pro_category',
                    'value' => $cat->term_id,
                    'compare' => '='
                ]]
            ]);
            $counts[] = count($meta_questions);
            
            // Method 3: Direct DB query
            global $wpdb;
            $db_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p 
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                WHERE p.post_type = 'sfwd-question' 
                AND p.post_status = 'publish' 
                AND tt.taxonomy = 'ld_quiz_category' 
                AND tt.term_id = %d
            ", $cat->term_id));
            $counts[] = intval($db_count);
            
            // Use the highest count found
            $final_count = max($counts);
            
            $result[] = [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'count' => $final_count,
                'methods' => $counts // Debug info
            ];
        }
        
        wp_send_json_success($result);
    }
    
    public function ajax_populate() {
        check_ajax_referer('bqt_nonce', 'nonce');
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        $per_category = intval($_POST['per_category'] ?? 3);
        
        if (!$quiz_id) {
            wp_send_json_error('Invalid quiz ID');
        }
        
        // Get all categories with questions
        $categories = get_terms([
            'taxonomy' => 'ld_quiz_category',
            'hide_empty' => false
        ]);
        
        if (is_wp_error($categories) || empty($categories)) {
            wp_send_json_error('No categories found');
        }
        
        $all_question_ids = [];
        $added_from_categories = [];
        
        foreach ($categories as $cat) {
            // Try to get questions from this category
            $questions = get_posts([
                'post_type' => 'sfwd-question',
                'post_status' => 'publish',
                'posts_per_page' => $per_category,
                'fields' => 'ids',
                'orderby' => 'rand',
                'tax_query' => [[
                    'taxonomy' => 'ld_quiz_category',
                    'field' => 'term_id',
                    'terms' => $cat->term_id
                ]]
            ]);
            
            // If no results from taxonomy, try meta
            if (empty($questions)) {
                $questions = get_posts([
                    'post_type' => 'sfwd-question',
                    'post_status' => 'publish',
                    'posts_per_page' => $per_category,
                    'fields' => 'ids',
                    'orderby' => 'rand',
                    'meta_query' => [[
                        'key' => 'question_pro_category',
                        'value' => $cat->term_id,
                        'compare' => '='
                    ]]
                ]);
            }
            
            if (!empty($questions)) {
                $all_question_ids = array_merge($all_question_ids, $questions);
                $added_from_categories[] = $cat->name . ' (' . count($questions) . ')';
            }
        }
        
        if (empty($all_question_ids)) {
            wp_send_json_error('No questions found in any category');
        }
        
        // Get existing questions
        $existing = get_post_meta($quiz_id, 'ld_quiz_questions', true);
        if (!is_array($existing)) {
            $existing = [];
        }
        
        // Merge and remove duplicates
        $final_questions = array_unique(array_merge($existing, $all_question_ids));
        
        // Update quiz
        $success = update_post_meta($quiz_id, 'ld_quiz_questions', $final_questions);
        
        if ($success) {
            $message = sprintf(
                'Added %d questions from %d categories: %s',
                count($all_question_ids),
                count($added_from_categories),
                implode(', ', $added_from_categories)
            );
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error('Failed to update quiz');
        }
    }
}

new BulletproofQuizTool();
?>
