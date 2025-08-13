<?php
/**
 * Debug info template
 *
 * @package LD_Instructor_Quiz_Categories
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<hr style="margin: 15px 0;">
<div class="ld-debug-info">
    <small style="color: #666;">
        <strong><?php _e('Debug Info:', 'ld-instructor-quiz-cats'); ?></strong><br>
        <?php printf(__('Found %d question categories', 'ld-instructor-quiz-cats'), count($question_categories)); ?><br>
        <?php printf(__('Using Taxonomy: %s', 'ld-instructor-quiz-cats'), esc_html($used_taxonomy)); ?><br>
        <?php 
        // Show current quiz questions count
        $current_questions = get_post_meta($post->ID, 'ld_quiz_questions', true);
        $question_count = is_array($current_questions) ? count($current_questions) : 0;
        printf(__('Current Questions in Quiz: %d', 'ld-instructor-quiz-cats'), $question_count);
        ?><br>
        <?php printf(__('Post Type: %s', 'ld-instructor-quiz-cats'), get_post_type($post)); ?><br>
        <?php printf(__('Plugin Version: %s', 'ld-instructor-quiz-cats'), LD_INSTRUCTOR_QUIZ_CATS_VERSION); ?><br>
        <?php printf(__('Post ID: %d', 'ld-instructor-quiz-cats'), $post->ID); ?><br>
    </small>
</div>
