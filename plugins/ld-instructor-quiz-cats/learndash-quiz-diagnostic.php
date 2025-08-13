<?php
/**
 * LearnDash Quiz Metadata Diagnostic
 * Analyzes how LearnDash stores and retrieves quiz questions
 */

// WordPress bootstrap
require_once('../../../../wp-config.php');

echo "<h2>LearnDash Quiz Metadata Diagnostic</h2>\n";

// Test quiz IDs
$test_quiz_id = 10579; // Your test quiz
$working_quiz_id = 9665; // A quiz that has questions

echo "<h3>Test 1: Compare Working Quiz vs Empty Quiz Metadata</h3>\n";

// Get all metadata for both quizzes
$test_quiz_meta = get_post_meta($test_quiz_id);
$working_quiz_meta = get_post_meta($working_quiz_id);

echo "<h4>Test Quiz ($test_quiz_id) Metadata:</h4>\n";
foreach ($test_quiz_meta as $key => $value) {
    if (strpos($key, 'quiz') !== false || strpos($key, 'question') !== false) {
        $display_value = is_array($value[0]) ? 'ARRAY with ' . count($value[0]) . ' items' : 
                        (strlen($value[0]) > 100 ? substr($value[0], 0, 100) . '...' : $value[0]);
        echo "- $key: $display_value\n";
    }
}

echo "<h4>Working Quiz ($working_quiz_id) Metadata:</h4>\n";
foreach ($working_quiz_meta as $key => $value) {
    if (strpos($key, 'quiz') !== false || strpos($key, 'question') !== false) {
        $display_value = is_array($value[0]) ? 'ARRAY with ' . count($value[0]) . ' items' : 
                        (strlen($value[0]) > 100 ? substr($value[0], 0, 100) . '...' : $value[0]);
        echo "- $key: $display_value\n";
    }
}

echo "<h3>Test 2: LearnDash Quiz Builder Integration</h3>\n";

// Check if LearnDash has specific functions for quiz questions
if (function_exists('learndash_get_quiz_questions')) {
    $test_questions = learndash_get_quiz_questions($test_quiz_id);
    $working_questions = learndash_get_quiz_questions($working_quiz_id);
    
    echo "✅ learndash_get_quiz_questions() function exists\n";
    echo "- Test Quiz Questions: " . (is_array($test_questions) ? count($test_questions) : 'Not array') . "\n";
    echo "- Working Quiz Questions: " . (is_array($working_questions) ? count($working_questions) : 'Not array') . "\n";
} else {
    echo "❌ learndash_get_quiz_questions() function not found\n";
}

// Check for ProQuiz integration
$test_pro_id = get_post_meta($test_quiz_id, 'quiz_pro_id', true);
$working_pro_id = get_post_meta($working_quiz_id, 'quiz_pro_id', true);

echo "<h3>Test 3: ProQuiz Database Integration</h3>\n";
echo "- Test Quiz Pro ID: $test_pro_id\n";
echo "- Working Quiz Pro ID: $working_pro_id\n";

if (!empty($test_pro_id)) {
    global $wpdb;
    $test_pro_questions = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}learndash_pro_quiz_question WHERE quiz_id = %d",
        $test_pro_id
    ));
    echo "- Test Quiz ProQuiz Questions: $test_pro_questions\n";
}

if (!empty($working_pro_id)) {
    global $wpdb;
    $working_pro_questions = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}learndash_pro_quiz_question WHERE quiz_id = %d",
        $working_pro_id
    ));
    echo "- Working Quiz ProQuiz Questions: $working_pro_questions\n";
}

echo "<h3>Test 4: Quiz Builder Specific Metadata</h3>\n";

// Check for quiz builder specific metadata
$quiz_builder_keys = [
    '_sfwd-quiz',
    'ld_quiz_questions',
    'quiz_pro_id',
    '_quiz_pro_id',
    'learndash_quiz_questions',
    '_learndash_quiz_questions',
    'quiz_questions',
    '_quiz_questions'
];

foreach ($quiz_builder_keys as $key) {
    $test_value = get_post_meta($test_quiz_id, $key, true);
    $working_value = get_post_meta($working_quiz_id, $key, true);
    
    $test_display = is_array($test_value) ? 'ARRAY with ' . count($test_value) . ' items' : 
                   (empty($test_value) ? 'EMPTY' : (strlen($test_value) > 50 ? substr($test_value, 0, 50) . '...' : $test_value));
    $working_display = is_array($working_value) ? 'ARRAY with ' . count($working_value) . ' items' : 
                      (empty($working_value) ? 'EMPTY' : (strlen($working_value) > 50 ? substr($working_value, 0, 50) . '...' : $working_value));
    
    echo "- $key:\n";
    echo "  - Test Quiz: $test_display\n";
    echo "  - Working Quiz: $working_display\n";
}

echo "<h3>Test 5: Simulate Our Plugin's Update</h3>\n";

// Simulate what our plugin should do
$extracted_questions = [9739, 9736, 9733, 9730, 9727]; // Sample from our debug

echo "Simulating update with questions: " . implode(', ', $extracted_questions) . "\n";

// Update the metadata
update_post_meta($test_quiz_id, 'ld_quiz_questions', $extracted_questions);
update_post_meta($test_quiz_id, '_ld_quiz_dirty', true);

// Check if it worked
$updated_questions = get_post_meta($test_quiz_id, 'ld_quiz_questions', true);
echo "✅ Updated ld_quiz_questions: " . (is_array($updated_questions) ? count($updated_questions) . ' questions' : 'FAILED') . "\n";

// Check if LearnDash recognizes it now
if (function_exists('learndash_get_quiz_questions')) {
    $ld_questions = learndash_get_quiz_questions($test_quiz_id);
    echo "✅ LearnDash function result: " . (is_array($ld_questions) ? count($ld_questions) . ' questions' : 'FAILED') . "\n";
}

echo "<h3>Test 6: ProQuiz Master Table Update</h3>\n";

if (!empty($test_pro_id)) {
    global $wpdb;
    
    // Update ProQuiz master table
    $update_result = $wpdb->update(
        $wpdb->prefix . 'learndash_pro_quiz_master',
        array('question_count' => count($extracted_questions)),
        array('id' => $test_pro_id),
        array('%d'),
        array('%d')
    );
    
    echo "✅ ProQuiz master table update: " . ($update_result !== false ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Check the result
    $pro_count = $wpdb->get_var($wpdb->prepare(
        "SELECT question_count FROM {$wpdb->prefix}learndash_pro_quiz_master WHERE id = %d",
        $test_pro_id
    ));
    echo "✅ ProQuiz question_count now: $pro_count\n";
}

echo "<h3>Recommendations</h3>\n";
echo "1. Check if quiz builder uses ProQuiz database instead of WordPress meta\n";
echo "2. Verify if additional LearnDash hooks need to be triggered\n";
echo "3. Check if quiz needs to be 'published' or 'rebuilt' after question update\n";
echo "4. Investigate if _sfwd-quiz metadata needs to be updated as well\n";

?>
