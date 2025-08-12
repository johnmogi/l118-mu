<?php
/**
 * MySQL Diagnostic Script for LearnDash Quiz Categories
 * Run this directly to test MySQL queries outside of WordPress
 */

// WordPress database connection (from .env file)
$db_host = '127.0.0.1';
$db_port = '10125';
$db_name = 'local';
$db_user = 'root';
$db_pass = 'root';
$table_prefix = 'edc_';

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>MySQL Diagnostic for LearnDash Quiz Categories</h2>\n";
    
    // Test 1: Check if we can find quizzes in categories
    echo "<h3>Test 1: Quizzes in Category 162 (רכב פרטי)</h3>\n";
    $sql = "
        SELECT p.ID, p.post_title
        FROM {$table_prefix}posts p
        INNER JOIN {$table_prefix}term_relationships tr ON p.ID = tr.object_id
        INNER JOIN {$table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'sfwd-quiz'
        AND p.post_status = 'publish'
        AND tt.taxonomy = 'ld_quiz_category'
        AND tt.term_id = 162
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($quizzes) {
        echo "✅ Found " . count($quizzes) . " quizzes in category 162:\n";
        foreach ($quizzes as $quiz) {
            echo "- Quiz {$quiz['ID']}: {$quiz['post_title']}\n";
        }
    } else {
        echo "❌ No quizzes found in category 162\n";
    }
    
    // Test 2: Check quiz metadata for questions
    echo "<h3>Test 2: Quiz Metadata for Quiz 9665</h3>\n";
    $sql = "
        SELECT meta_key, meta_value
        FROM {$table_prefix}postmeta
        WHERE post_id = 9665
        AND meta_key IN ('ld_quiz_questions', 'quiz_pro_id', '_sfwd-quiz')
    ";
    
    $stmt = $pdo->query($sql);
    $metadata = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($metadata) {
        echo "✅ Found metadata for quiz 9665:\n";
        foreach ($metadata as $meta) {
            $value = strlen($meta['meta_value']) > 100 ? 
                substr($meta['meta_value'], 0, 100) . '...' : 
                $meta['meta_value'];
            echo "- {$meta['meta_key']}: $value\n";
        }
    } else {
        echo "❌ No metadata found for quiz 9665\n";
    }
    
    // Test 3: Check ProQuiz questions table
    echo "<h3>Test 3: ProQuiz Questions for Quiz Pro ID 93</h3>\n";
    $sql = "
        SELECT id, title, question
        FROM {$table_prefix}learndash_pro_quiz_question
        WHERE quiz_id = 93
        LIMIT 5
    ";
    
    try {
        $stmt = $pdo->query($sql);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($questions) {
            echo "✅ Found " . count($questions) . " ProQuiz questions:\n";
            foreach ($questions as $question) {
                $title = strlen($question['title']) > 50 ? 
                    substr($question['title'], 0, 50) . '...' : 
                    $question['title'];
                echo "- Question {$question['id']}: $title\n";
            }
        } else {
            echo "❌ No ProQuiz questions found for quiz_id 93\n";
        }
    } catch (Exception $e) {
        echo "❌ ProQuiz table query failed: " . $e->getMessage() . "\n";
    }
    
    // Test 4: Check questions in categories (the failing query)
    echo "<h3>Test 4: Questions Directly in Categories (This Should Fail)</h3>\n";
    $sql = "
        SELECT p.ID, p.post_title
        FROM {$table_prefix}posts p
        INNER JOIN {$table_prefix}term_relationships tr ON p.ID = tr.object_id
        INNER JOIN {$table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'sfwd-question'
        AND p.post_status = 'publish'
        AND tt.taxonomy = 'ld_quiz_category'
        AND tt.term_id = 162
        LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($questions) {
        echo "✅ Found " . count($questions) . " questions directly in category 162:\n";
        foreach ($questions as $question) {
            echo "- Question {$question['ID']}: {$question['post_title']}\n";
        }
    } else {
        echo "❌ No questions found directly in category 162 (this is expected)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}
?>
