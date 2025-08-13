<?php
/**
 * Quick plugin status check
 * Run this to verify the plugin is active and working
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "<h2>LearnDash Instructor Quiz Categories - Status Check</h2>";

// Check if plugin class exists
if (class_exists('LD_Instructor_Quiz_Categories')) {
    echo "<p style='color: green;'>✅ Plugin class loaded successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Plugin class NOT loaded</p>";
}

// Check if LearnDash is active
if (class_exists('SFWD_LMS')) {
    echo "<p style='color: green;'>✅ LearnDash is active</p>";
} else {
    echo "<p style='color: red;'>❌ LearnDash is NOT active</p>";
}

// Check if taxonomy exists
$taxonomy_exists = taxonomy_exists('ld_quiz_category');
echo "<p style='color: " . ($taxonomy_exists ? 'green' : 'orange') . ";'>" . 
     ($taxonomy_exists ? '✅' : '⚠️') . " ld_quiz_category taxonomy " . 
     ($taxonomy_exists ? 'exists' : 'does not exist') . "</p>";

// Check for question categories
$categories = get_terms(array(
    'taxonomy' => 'ld_quiz_category',
    'hide_empty' => false
));

if (!is_wp_error($categories) && !empty($categories)) {
    echo "<p style='color: green;'>✅ Found " . count($categories) . " question categories</p>";
    echo "<ul>";
    foreach (array_slice($categories, 0, 5) as $cat) {
        echo "<li>" . esc_html($cat->name) . " (ID: {$cat->term_id}, Count: {$cat->count})</li>";
    }
    if (count($categories) > 5) {
        echo "<li>... and " . (count($categories) - 5) . " more</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠️ No question categories found</p>";
}

// Check current quiz if we're on a quiz edit page
global $post;
if ($post && $post->post_type === 'sfwd-quiz') {
    echo "<h3>Current Quiz Info</h3>";
    echo "<p><strong>Quiz ID:</strong> {$post->ID}</p>";
    echo "<p><strong>Quiz Title:</strong> " . esc_html($post->post_title) . "</p>";
    
    $selected_cats = get_post_meta($post->ID, '_ld_quiz_question_categories', true);
    if (!empty($selected_cats)) {
        echo "<p style='color: green;'>✅ Selected categories: " . implode(', ', $selected_cats) . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ No categories selected for this quiz</p>";
    }
    
    $current_questions = get_post_meta($post->ID, 'ld_quiz_questions', true);
    $question_count = is_array($current_questions) ? count($current_questions) : 0;
    echo "<p><strong>Current Questions:</strong> {$question_count}</p>";
}

echo "<hr>";
echo "<p><small>Plugin re-enabled with 300 question limit and infinite loop protection.</small></p>";
