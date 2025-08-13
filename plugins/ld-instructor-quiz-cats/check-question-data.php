<?php
/**
 * Comprehensive Question Data Diagnostic
 * Run this to see ALL ways questions might be categorized
 */

// Load WordPress
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>Comprehensive Question Data Analysis</h2>";

// Get sample questions
$questions = get_posts(array(
    'post_type' => 'sfwd-question',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'fields' => 'ids'
));

echo "<h3>Sample Questions Analysis (First 10)</h3>";

foreach ($questions as $q_id) {
    echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Question ID: {$q_id}</strong><br>";
    
    // Get question title
    $question = get_post($q_id);
    echo "<strong>Title:</strong> " . esc_html($question->post_title) . "<br>";
    
    // Check ALL taxonomies
    $all_taxonomies = get_object_taxonomies('sfwd-question');
    echo "<strong>Available Taxonomies:</strong> " . implode(', ', $all_taxonomies) . "<br>";
    
    $has_terms = false;
    foreach ($all_taxonomies as $taxonomy) {
        $terms = wp_get_post_terms($q_id, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            $has_terms = true;
            $term_names = array_map(function($t) { return $t->name; }, $terms);
            echo "<strong>{$taxonomy}:</strong> " . implode(', ', $term_names) . "<br>";
        }
    }
    
    if (!$has_terms) {
        echo "<span style='color: red;'>❌ NO TAXONOMY TERMS ASSIGNED</span><br>";
    }
    
    // Check ALL post meta
    $all_meta = get_post_meta($q_id);
    echo "<strong>Post Meta Keys:</strong><br>";
    foreach ($all_meta as $key => $values) {
        if (strpos($key, 'category') !== false || strpos($key, 'tag') !== false || strpos($key, 'tax') !== false) {
            echo "- {$key}: " . implode(', ', $values) . "<br>";
        }
    }
    
    echo "</div>";
}

// Check if questions are in any categories at all
echo "<h3>Global Category Usage</h3>";
$all_taxonomies = get_object_taxonomies('sfwd-question');
foreach ($all_taxonomies as $taxonomy) {
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false
    ));
    
    if (!empty($terms) && !is_wp_error($terms)) {
        echo "<strong>{$taxonomy}:</strong> " . count($terms) . " terms<br>";
        
        // Check if any questions are actually assigned
        $questions_with_terms = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'operator' => 'EXISTS'
                )
            )
        ));
        
        if (!empty($questions_with_terms)) {
            echo "  ✅ Questions ARE assigned to this taxonomy<br>";
        } else {
            echo "  ❌ NO questions assigned to this taxonomy<br>";
        }
    }
}

echo "<h3>Recommendation</h3>";
echo "<p>Based on this analysis, we can determine the best approach for your quiz auto-population.</p>";
?>
