<?php
/**
 * Question Filter Diagnostic
 * Check what happens with our filtering logic
 */

// WordPress bootstrap
require_once('../../../../wp-config.php');

echo "<h2>Question Filter Diagnostic</h2>\n";

// Test the same logic our plugin uses
$selected_categories = [183, 57, 177, 169, 162];

echo "<h3>Step 1: Find Quizzes in Categories</h3>\n";
$quizzes_in_categories = get_posts(array(
    'post_type' => 'sfwd-quiz',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'tax_query' => array(
        array(
            'taxonomy' => 'ld_quiz_category',
            'field' => 'term_id',
            'terms' => $selected_categories,
            'operator' => 'IN'
        )
    )
));

echo "Found " . count($quizzes_in_categories) . " quizzes in selected categories\n";

echo "<h3>Step 2: Extract Questions with Filtering</h3>\n";
$extracted_questions = array();
$debug_info = array();

if (is_array($quizzes_in_categories) && count($quizzes_in_categories) > 0) {
    $quizzes_to_process = array_slice($quizzes_in_categories, 0, 5);
    
    foreach ($quizzes_to_process as $source_quiz_id) {
        $ld_quiz_questions = get_post_meta($source_quiz_id, 'ld_quiz_questions', true);
        
        echo "Quiz $source_quiz_id:\n";
        if (!empty($ld_quiz_questions) && is_array($ld_quiz_questions)) {
            echo "- Has " . count($ld_quiz_questions) . " questions in metadata\n";
            
            $valid_questions = 0;
            foreach (array_keys($ld_quiz_questions) as $question_id) {
                $post_type = get_post_type($question_id);
                $post_status = get_post_status($question_id);
                
                echo "  - Question $question_id: Type=$post_type, Status=$post_status";
                
                if ($post_type === 'sfwd-question' && $post_status === 'publish') {
                    $extracted_questions[] = $question_id;
                    $valid_questions++;
                    echo " ✅ VALID\n";
                } else {
                    echo " ❌ FILTERED OUT\n";
                }
            }
            echo "- Valid questions from this quiz: $valid_questions\n";
        } else {
            echo "- No questions in metadata\n";
        }
        echo "\n";
    }
}

echo "<h3>Step 3: Final Results</h3>\n";
echo "Total extracted questions after filtering: " . count($extracted_questions) . "\n";

if (count($extracted_questions) > 0) {
    echo "Questions that would be added:\n";
    foreach ($extracted_questions as $q_id) {
        $question_post = get_post($q_id);
        echo "- $q_id: " . ($question_post ? $question_post->post_title : 'NOT FOUND') . "\n";
    }
} else {
    echo "❌ NO VALID QUESTIONS FOUND!\n";
    echo "\nPossible reasons:\n";
    echo "1. Source quizzes don't contain 'sfwd-question' post types\n";
    echo "2. Questions are not published\n";
    echo "3. Questions are stored differently in your LearnDash setup\n";
}

echo "<h3>Step 4: Check Source Quiz Question Types</h3>\n";
if (count($quizzes_in_categories) > 0) {
    $sample_quiz = $quizzes_in_categories[0];
    $sample_questions = get_post_meta($sample_quiz, 'ld_quiz_questions', true);
    
    echo "Sample quiz $sample_quiz question types:\n";
    if (is_array($sample_questions)) {
        foreach (array_keys($sample_questions) as $q_id) {
            $post_type = get_post_type($q_id);
            $post_status = get_post_status($q_id);
            echo "- $q_id: $post_type ($post_status)\n";
        }
    }
}

echo "<h3>Recommendation</h3>\n";
echo "If no valid questions are found, we may need to adjust the filtering logic\n";
echo "or investigate how questions are actually stored in your LearnDash setup.\n";

?>
