<?php
/**
 * Meta box template for quiz categories
 *
 * @package LD_Instructor_Quiz_Categories
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get previously selected categories
$selected_categories = get_post_meta($post->ID, '_ld_quiz_question_categories', true);
if (!is_array($selected_categories)) {
    $selected_categories = array();
}
?>

<div class="ld-instructor-quiz-categories-wrapper">
    <?php wp_nonce_field('save_quiz_categories', 'ld_instructor_quiz_categories_nonce'); ?>
    
    <p><?php _e('Select question categories to include in this quiz:', 'ld-instructor-quiz-cats'); ?></p>
    
    <?php if (!empty($selected_categories)) : ?>
        <div class="ld-selected-categories-info">
            <small style="color: #0073aa; font-weight: 500;">
                <?php printf(__('Currently selected: %d categories', 'ld-instructor-quiz-cats'), count($selected_categories)); ?>
            </small>
        </div>
    <?php endif; ?>
    
    <?php foreach ($question_categories as $category) : 
        $is_selected = in_array($category->term_id, $selected_categories);
    ?>
        <label class="ld-quiz-category-item <?php echo $is_selected ? 'selected' : ''; ?>">
            <input 
                type="checkbox" 
                name="ld_instructor_quiz_categories[]" 
                value="<?php echo esc_attr($category->term_id); ?>"
                class="ld-quiz-category-checkbox"
                <?php checked($is_selected); ?>
            >
            <span class="ld-quiz-category-name"><?php echo esc_html($category->name); ?></span>
            <?php if (!empty($category->description)) : ?>
                <small class="ld-quiz-category-description">(<?php echo esc_html($category->description); ?>)</small>
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    
    <div class="ld-save-notice">
        <small style="color: #666; font-style: italic;">
            <?php _e('💡 Tip: Selected categories will be saved when you update the quiz. Questions from these categories can then be used to auto-populate the quiz.', 'ld-instructor-quiz-cats'); ?>
        </small>
    </div>
    
    <?php if (!empty($selected_categories)) : ?>
    <div class="ld-test-population" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px;">
        <p style="margin: 0 0 10px 0; font-weight: 500; color: #0969da;">🔧 Debug:</p>
        <div class="ld-quiz-actions">
            <button type="button" id="single-category-debug" class="button button-primary">
                <?php _e('🔧 Debug Single Category', 'ld-instructor-quiz-cats'); ?>
            </button>
            <button type="button" id="test-population" class="button button-secondary">
                <?php _e('Test Population Now', 'ld-instructor-quiz-cats'); ?>
            </button>
            <button type="button" id="bulk-categorize" class="button button-secondary">
                <?php _e('Auto-Categorize Questions', 'ld-instructor-quiz-cats'); ?>
            </button>
            <small style="color: #666; margin-left: 10px;">
                <?php _e('Debug tests first selected category and attaches 10-15 questions. Test finds all questions. Auto-Categorize assigns uncategorized questions.', 'ld-instructor-quiz-cats'); ?>
            </small>
        </div>
        <div id="population-results" style="margin-top: 10px; display: none;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-population').click(function() {
            var button = $(this);
            var results = $('#population-results');
            
            button.prop('disabled', true).text('Testing...');
            results.hide();
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'test_quiz_population',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('test_population_' . $post->ID); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data.message + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Test Population Now');
                }
            });
        });
        
        $('#bulk-categorize').click(function() {
            console.log('Bulk categorize button clicked');
            var button = $(this);
            var results = $('#population-results');
            
            var selectedCategories = [];
            $('input[name="ld_instructor_quiz_categories[]"]:checked').each(function() {
                selectedCategories.push($(this).val());
            });
            
            console.log('Selected categories:', selectedCategories);
            
            if (selectedCategories.length === 0) {
                results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ Please select at least one category first</div>').show();
                return;
            }
            
            if (!confirm('This will categorize uncategorized questions into the selected categories. Continue?')) {
                return;
            }
            
            button.prop('disabled', true).text('Categorizing...');
            results.hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bulk_categorize_questions',
                    quiz_id: <?php echo $post->ID; ?>,
                    categories: selectedCategories,
                    nonce: '<?php echo wp_create_nonce('bulk_categorize_' . $post->ID); ?>'
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        results.html('<div style="padding: 8px; background: #d1e7dd; border: 1px solid #badbcc; border-radius: 3px; color: #0f5132;">✅ ' + response.data + '</div>').show();
                    } else {
                        results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ ' + response.data + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    results.html('<div style="padding: 8px; background: #f8d7da; border: 1px solid #f5c2c7; border-radius: 3px; color: #842029;">❌ AJAX Error: ' + error + '</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).text('Auto-Categorize Questions');
                }
            });
        });
    });
    </script>
    <?php endif; ?>
</div>

<style>
.ld-instructor-quiz-categories-wrapper {
    padding: 10px 0;
}

.ld-selected-categories-info {
    margin-bottom: 15px;
    padding: 8px 12px;
    background: #e7f3ff;
    border-left: 3px solid #0073aa;
    border-radius: 3px;
}

.ld-quiz-category-item {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
    padding: 6px 8px;
    transition: all 0.2s ease;
    border-radius: 4px;
    border: 1px solid transparent;
}

.ld-quiz-category-item:hover {
    background-color: #f0f0f1;
    border-color: #ddd;
}

.ld-quiz-category-item.selected {
    background-color: #e7f3ff;
    border-color: #0073aa;
    box-shadow: 0 1px 3px rgba(0, 115, 170, 0.1);
}

.ld-quiz-category-item.selected:hover {
    background-color: #d0e7ff;
}

.ld-quiz-category-checkbox {
    margin-right: 8px;
}

.ld-quiz-category-name {
    font-weight: 500;
}

.ld-quiz-category-description {
    color: #666;
    font-style: italic;
    margin-left: 4px;
}

.ld-save-notice {
    margin-top: 15px;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}
</style>
