<?php
/**
 * Diagnostic script to check what taxonomies questions actually use
 * Access this via: /wp-content/mu-plugins/plugins/ld-instructor-quiz-cats/debug-taxonomies.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>LearnDash Question Taxonomy Diagnostic</h2>";

// Get sample questions
$questions = get_posts(array(
    'post_type' => 'sfwd-question',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'fields' => 'ids'
));

echo "<h3>Found " . count($questions) . " sample questions</h3>";

if (empty($questions)) {
    echo "<p><strong>No questions found!</strong></p>";
    exit;
}

// Check taxonomies for each question
$taxonomy_usage = array();
$all_taxonomies = array();

foreach ($questions as $question_id) {
    echo "<h4>Question ID: $question_id</h4>";
    
    // Get all taxonomies for this post type
    $available_taxonomies = get_object_taxonomies('sfwd-question');
    echo "<p><strong>Available taxonomies:</strong> " . implode(', ', $available_taxonomies) . "</p>";
    
    foreach ($available_taxonomies as $taxonomy) {
        $all_taxonomies[$taxonomy] = true;
        
        $terms = wp_get_post_terms($question_id, $taxonomy);
        if (!empty($terms) && !is_wp_error($terms)) {
            echo "<p><strong>$taxonomy:</strong> ";
            foreach ($terms as $term) {
                echo $term->name . " (ID: $term->term_id), ";
            }
            echo "</p>";
            
            if (!isset($taxonomy_usage[$taxonomy])) {
                $taxonomy_usage[$taxonomy] = 0;
            }
            $taxonomy_usage[$taxonomy] += count($terms);
        } else {
            echo "<p><strong>$taxonomy:</strong> No terms assigned</p>";
        }
    }
    echo "<hr>";
}

echo "<h3>Summary</h3>";
echo "<p><strong>All available taxonomies:</strong> " . implode(', ', array_keys($all_taxonomies)) . "</p>";

if (!empty($taxonomy_usage)) {
    echo "<p><strong>Taxonomies with terms assigned:</strong></p>";
    foreach ($taxonomy_usage as $taxonomy => $count) {
        echo "<li>$taxonomy: $count terms total</li>";
    }
    
    arsort($taxonomy_usage);
    $most_used = array_key_first($taxonomy_usage);
    echo "<p><strong>Most used taxonomy:</strong> $most_used</p>";
} else {
    echo "<p><strong>No taxonomies have terms assigned to questions!</strong></p>";
}

// Check the categories you selected
echo "<h3>Selected Categories Check</h3>";
$selected_categories = array(183, 57, 177, 169, 162);

foreach ($selected_categories as $cat_id) {
    $term = get_term($cat_id);
    if ($term && !is_wp_error($term)) {
        echo "<p><strong>Category ID $cat_id:</strong> {$term->name} (taxonomy: {$term->taxonomy})</p>";
        
        // Check how many questions are in this category
        $questions_in_cat = get_posts(array(
            'post_type' => 'sfwd-question',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $cat_id
                )
            )
        ));
        
        echo "<p>Questions in this category: " . count($questions_in_cat) . "</p>";
    } else {
        echo "<p><strong>Category ID $cat_id:</strong> Not found or error</p>";
    }
}
?>
