<?php
/**
 * Quiz Frontend Diagnostic
 * Test if questions actually appear on the frontend quiz
 */

// WordPress bootstrap
require_once('../../../../wp-config.php');

echo "<h2>Quiz Frontend Diagnostic</h2>\n";

$test_quiz_id = 10579;
$working_quiz_id = 9665;

echo "<h3>Test 1: Current Quiz Questions Analysis</h3>\n";

// Get current questions
$test_questions = get_post_meta($test_quiz_id, 'ld_quiz_questions', true);
$working_questions = get_post_meta($working_quiz_id, 'ld_quiz_questions', true);

echo "Test Quiz ($test_quiz_id) Questions:\n";
if (is_array($test_questions) && !empty($test_questions)) {
    foreach ($test_questions as $q_id => $sort_order) {
        $question_post = get_post($q_id);
        $post_type = get_post_type($q_id);
        echo "- Question $q_id (Sort: $sort_order): " . 
             ($question_post ? $question_post->post_title : 'NOT FOUND') . 
             " (Type: $post_type)\n";
    }
} else {
    echo "- No questions found\n";
}

echo "\nWorking Quiz ($working_quiz_id) Questions (first 5):\n";
if (is_array($working_questions) && !empty($working_questions)) {
    $count = 0;
    foreach ($working_questions as $q_id => $sort_order) {
        if ($count >= 5) break;
        $question_post = get_post($q_id);
        $post_type = get_post_type($q_id);
        echo "- Question $q_id (Sort: $sort_order): " . 
             ($question_post ? $question_post->post_title : 'NOT FOUND') . 
             " (Type: $post_type)\n";
        $count++;
    }
} else {
    echo "- No questions found\n";
}

echo "<h3>Test 2: Frontend Quiz URL Test</h3>\n";

// Get quiz permalinks
$test_quiz_url = get_permalink($test_quiz_id);
$working_quiz_url = get_permalink($working_quiz_id);

echo "Test Quiz Frontend URL: <a href='$test_quiz_url' target='_blank'>$test_quiz_url</a>\n";
echo "Working Quiz Frontend URL: <a href='$working_quiz_url' target='_blank'>$working_quiz_url</a>\n";

echo "<h3>Test 3: LearnDash Quiz Settings</h3>\n";

// Check quiz settings
$test_quiz_settings = get_post_meta($test_quiz_id, '_sfwd-quiz', true);
$working_quiz_settings = get_post_meta($working_quiz_id, '_sfwd-quiz', true);

echo "Test Quiz Settings:\n";
if (is_array($test_quiz_settings)) {
    $relevant_keys = ['sfwd-quiz_quiz', 'sfwd-quiz_quiz_pro', 'sfwd-quiz_lesson', 'sfwd-quiz_course'];
    foreach ($relevant_keys as $key) {
        if (isset($test_quiz_settings[$key])) {
            echo "- $key: " . $test_quiz_settings[$key] . "\n";
        }
    }
} else {
    echo "- No settings found\n";
}

echo "<h3>Test 4: Question Post Type Validation</h3>\n";

// Check if our questions are valid LearnDash questions
if (is_array($test_questions) && !empty($test_questions)) {
    foreach ($test_questions as $q_id => $sort_order) {
        $post_type = get_post_type($q_id);
        $post_status = get_post_status($q_id);
        echo "- Question $q_id: Type=$post_type, Status=$post_status\n";
        
        // Check if it has LearnDash question metadata
        $question_meta = get_post_meta($q_id, '_sfwd-question', true);
        echo "  - Has LearnDash question meta: " . (is_array($question_meta) ? 'YES' : 'NO') . "\n";
    }
}

echo "<h3>Test 5: Quiz Builder vs Frontend Consistency</h3>\n";

// Use LearnDash function to get questions
if (function_exists('learndash_get_quiz_questions')) {
    $ld_test_questions = learndash_get_quiz_questions($test_quiz_id);
    $ld_working_questions = learndash_get_quiz_questions($working_quiz_id);
    
    echo "LearnDash Function Results:\n";
    echo "- Test Quiz: " . (is_array($ld_test_questions) ? count($ld_test_questions) . ' questions' : 'No questions') . "\n";
    echo "- Working Quiz: " . (is_array($ld_working_questions) ? count($ld_working_questions) . ' questions' : 'No questions') . "\n";
}

echo "<h3>Recommendations</h3>\n";
echo "1. Visit the frontend URLs above to see if questions actually appear for students\n";
echo "2. Check if our added questions are valid LearnDash question post types\n";
echo "3. Verify if quiz builder interface is just a display issue vs actual functionality\n";
echo "4. Test if students can actually take the quiz with the added questions\n";

?>
