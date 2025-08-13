<?php
/**
 * Single Category Debug Script
 * Debug and test quiz auto-population from one category
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "<h2>🔧 Single Category Debug & Test</h2>";

// Step 1: Get the first selected category from quiz meta
$quiz_id = 10592; // From the debug info
$selected_categories = get_post_meta($quiz_id, '_ld_quiz_question_categories', true);

if (empty($selected_categories)) {
    echo "<p style='color: red;'>❌ No categories selected for quiz {$quiz_id}</p>";
    return;
}

$test_category_id = $selected_categories[0]; // First selected category
$category = get_term($test_category_id, 'ld_quiz_category');

echo "<h3>📋 Test Setup</h3>";
echo "<p><strong>Quiz ID:</strong> {$quiz_id}</p>";
echo "<p><strong>Test Category:</strong> {$category->name} (ID: {$test_category_id})</p>";
echo "<p><strong>Category Count:</strong> {$category->count}</p>";

// Step 2: Try different approaches to find questions in this category

echo "<h3>🔍 Method 1: Direct Question Query</h3>";
$questions_method1 = get_posts(array(
    'post_type' => 'sfwd-question',
    'post_status' => 'publish',
    'posts_per_page' => 20,
    'fields' => 'ids',
    'tax_query' => array(
        array(
            'taxonomy' => 'ld_quiz_category',
            'field' => 'term_id',
            'terms' => $test_category_id
        )
    )
));

echo "<p>Found " . count($questions_method1) . " questions directly in category</p>";
if (!empty($questions_method1)) {
    echo "<p>Sample IDs: " . implode(', ', array_slice($questions_method1, 0, 5)) . "</p>";
}

echo "<h3>🔍 Method 2: Check All Questions for This Category</h3>";
$all_questions = get_posts(array(
    'post_type' => 'sfwd-question',
    'post_status' => 'publish',
    'posts_per_page' => 100,
    'fields' => 'ids'
));

$questions_with_category = array();
foreach ($all_questions as $question_id) {
    $terms = wp_get_post_terms($question_id, 'ld_quiz_category', array('fields' => 'ids'));
    if (!is_wp_error($terms) && in_array($test_category_id, $terms)) {
        $questions_with_category[] = $question_id;
    }
}

echo "<p>Found " . count($questions_with_category) . " questions with category assigned</p>";
if (!empty($questions_with_category)) {
    echo "<p>Sample IDs: " . implode(', ', array_slice($questions_with_category, 0, 5)) . "</p>";
}

echo "<h3>🔍 Method 3: Find Questions from Quizzes in This Category</h3>";
$quizzes_in_category = get_posts(array(
    'post_type' => 'sfwd-quiz',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'fields' => 'ids',
    'tax_query' => array(
        array(
            'taxonomy' => 'ld_quiz_category',
            'field' => 'term_id',
            'terms' => $test_category_id
        )
    )
));

echo "<p>Found " . count($quizzes_in_category) . " quizzes in category</p>";

$questions_from_quizzes = array();
if (!empty($quizzes_in_category)) {
    foreach (array_slice($quizzes_in_category, 0, 3) as $source_quiz_id) {
        $quiz_title = get_the_title($source_quiz_id);
        echo "<p>📝 Quiz: {$quiz_title} (ID: {$source_quiz_id})</p>";
        
        $quiz_questions = get_post_meta($source_quiz_id, 'ld_quiz_questions', true);
        if (!empty($quiz_questions) && is_array($quiz_questions)) {
            $valid_questions = array();
            foreach (array_keys($quiz_questions) as $question_id) {
                if (get_post_type($question_id) === 'sfwd-question' && get_post_status($question_id) === 'publish') {
                    $valid_questions[] = $question_id;
                    $questions_from_quizzes[] = $question_id;
                }
            }
            echo "<p>  → Found " . count($valid_questions) . " valid questions</p>";
            if (!empty($valid_questions)) {
                echo "<p>  → Sample: " . implode(', ', array_slice($valid_questions, 0, 3)) . "</p>";
            }
        }
    }
}

$questions_from_quizzes = array_unique($questions_from_quizzes);
echo "<p><strong>Total unique questions from quizzes: " . count($questions_from_quizzes) . "</strong></p>";

// Step 3: Choose the best method and attach questions
$questions_to_attach = array();

if (!empty($questions_method1)) {
    $questions_to_attach = array_slice($questions_method1, 0, 15);
    $method_used = "Direct Category Query";
} elseif (!empty($questions_with_category)) {
    $questions_to_attach = array_slice($questions_with_category, 0, 15);
    $method_used = "Manual Category Check";
} elseif (!empty($questions_from_quizzes)) {
    $questions_to_attach = array_slice($questions_from_quizzes, 0, 15);
    $method_used = "Questions from Quizzes in Category";
} else {
    echo "<p style='color: red;'>❌ No questions found using any method!</p>";
    return;
}

echo "<h3>🚀 Attaching Questions to Quiz</h3>";
echo "<p><strong>Method Used:</strong> {$method_used}</p>";
echo "<p><strong>Questions to Attach:</strong> " . count($questions_to_attach) . "</p>";
echo "<p><strong>Question IDs:</strong> " . implode(', ', $questions_to_attach) . "</p>";

// Step 4: Attach questions to quiz using LearnDash format
if (!empty($questions_to_attach)) {
    $formatted_questions = array();
    foreach ($questions_to_attach as $index => $question_id) {
        $formatted_questions[$question_id] = $index + 1; // Sort order
    }
    
    // Update quiz with questions
    update_post_meta($quiz_id, 'ld_quiz_questions', $formatted_questions);
    update_post_meta($quiz_id, 'ld_quiz_questions_dirty', time());
    
    // Clear caches
    wp_cache_delete($quiz_id, 'posts');
    if (function_exists('learndash_delete_quiz_cache')) {
        learndash_delete_quiz_cache($quiz_id);
    }
    
    echo "<p style='color: green;'>✅ Successfully attached " . count($questions_to_attach) . " questions to quiz {$quiz_id}</p>";
    echo "<p style='color: blue;'>🔄 Refresh the quiz edit page to see the questions in the builder</p>";
    
    // Log to debug
    error_log("LD Quiz Categories DEBUG: Found " . count($questions_to_attach) . " questions in Category {$category->name}. Attached to Quiz #{$quiz_id}.");
    
} else {
    echo "<p style='color: red;'>❌ No questions to attach</p>";
}

echo "<hr>";
echo "<p><small>Debug completed. Check quiz builder for results.</small></p>";
