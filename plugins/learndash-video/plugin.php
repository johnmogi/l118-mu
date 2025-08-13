<?php
/**
 * Plugin Name: LearnDash Video Simple
 * Description: Simple video integration for LearnDash lessons
 * Version: 1.0.0
 * Author: Lilac Team
 */

/**
 * LearnDash Video Support Plugin
 * 
 * Minimal, non-destructive video support for LearnDash lessons
 * Preserves all existing design and functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test if plugin is loading and inject video script
add_action('wp_head', function() {
    echo "<!-- LearnDash Video Plugin: LOADED -->\n";
});

// Simple DOM injection approach - run in footer
add_action('wp_footer', function() {
    // Handle both lesson pages and course pages
    if (is_singular('sfwd-lessons')) {
        echo "<!-- This IS a lesson page! -->\n";
        inject_single_lesson_video();
    } elseif (is_singular('sfwd-courses')) {
        echo "<!-- This IS a course page! -->\n";
        inject_course_accordion_videos();
    } else {
        echo "<!-- Not a lesson or course page -->\n";
        return;
    }
});

// Function to inject video on single lesson page
function inject_single_lesson_video() {
    
    $lesson_id = get_the_ID();
    $lesson_settings = learndash_get_setting($lesson_id);
    $video_url = !empty($lesson_settings['lesson_video_url']) ? $lesson_settings['lesson_video_url'] : '';
    $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
    
    echo "<!-- Video URL: {$video_url} -->\n";
    echo "<!-- Video enabled: " . ($video_enabled ? 'true' : 'false') . " -->\n";
    
    if (empty($video_url) || !$video_enabled) {
        echo "<!-- No video to inject -->\n";
        return;
    }
    
    // Generate YouTube embed
    $video_html = '';
    if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
        if (!empty($matches[1])) {
            $video_id = $matches[1];
            $video_html = '<div class="ld-video"><iframe width="100%" height="400" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe></div>';
        }
    }
    
    if (empty($video_html)) {
        echo "<!-- Could not generate video HTML -->\n";
        return;
    }
    
    echo "<!-- Generated video HTML -->\n";
    
    // JavaScript DOM injection
    ?>
    <script>
    console.log('🚀 LearnDash Video Plugin: Starting injection...');
    console.log('📹 Video URL:', <?php echo json_encode($video_url); ?>);
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄 DOM loaded, injecting video...');
        
        // Try multiple injection points
        const selectors = [
            '.elementor-widget-theme-post-content .elementor-widget-container',
            '.entry-content',
            '.learndash-wrapper',
            'main',
            'article',
            'body'
        ];
        
        let injected = false;
        
        for (let selector of selectors) {
            const target = document.querySelector(selector);
            if (target && !injected) {
                console.log('✅ Found target:', selector);
                
                const videoDiv = document.createElement('div');
                videoDiv.innerHTML = <?php echo json_encode($video_html); ?>;
                videoDiv.style.margin = '0'; // Remove all margins including bottom
                videoDiv.style.padding = '0';
                
                // Make iframe responsive with larger size for desktop
                const iframe = videoDiv.querySelector('iframe');
                if (iframe) {
                    iframe.style.width = '100%';
                    iframe.style.height = '600px'; // Increased height for better desktop viewing
                    iframe.style.maxWidth = '100%';
                    iframe.style.display = 'block';
                    iframe.style.border = 'none';
                    iframe.style.borderRadius = '8px';
                    iframe.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                }
                
                target.insertBefore(videoDiv, target.firstChild);
                console.log('📺 Video injected successfully!');
                injected = true;
                break;
            }
        }
        
        if (!injected) {
            console.warn('❌ Could not find injection point');
        }
    });
    </script>
    <?php
}

// Function to inject videos in course page accordion
function inject_course_accordion_videos() {
    $course_id = get_the_ID();
    
    // Get all lessons in this course
    $lessons = learndash_get_course_lessons_list($course_id);
    
    if (empty($lessons)) {
        echo "<!-- No lessons found in course -->\n";
        return;
    }
    
    echo "<!-- Found " . count($lessons) . " lessons in course -->\n";
    
    // Prepare lesson video data for JavaScript
    $lesson_videos = array();
    
    foreach ($lessons as $lesson) {
        $lesson_id = $lesson['post']->ID;
        $lesson_settings = learndash_get_setting($lesson_id);
        $video_url = !empty($lesson_settings['lesson_video_url']) ? $lesson_settings['lesson_video_url'] : '';
        $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
        
        if (!empty($video_url) && $video_enabled) {
            // Generate YouTube embed
            if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
                if (!empty($matches[1])) {
                    $video_id = $matches[1];
                    $video_html = '<div class="ld-video"><iframe width="100%" height="450" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe></div>';
                    
                    $lesson_videos[$lesson_id] = array(
                        'url' => $video_url,
                        'html' => $video_html,
                        'slug' => $lesson['post']->post_name
                    );
                }
            }
        }
    }
    
    if (empty($lesson_videos)) {
        echo "<!-- No videos to inject in course -->\n";
        return;
    }
    
    echo "<!-- Found " . count($lesson_videos) . " videos to inject -->\n";
    
    // JavaScript to inject videos into accordion
    ?>
    <script>
    console.log('🎥 Course Page: Injecting " . count($lesson_videos) . " videos into accordion...');
    
    const lessonVideos = <?php echo json_encode($lesson_videos); ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄 Course DOM loaded, searching for lesson accordion items...');
        
        // Wait a bit for Elementor/LearnDash to fully render
        setTimeout(function() {
            Object.keys(lessonVideos).forEach(function(lessonId) {
                const videoData = lessonVideos[lessonId];
                
                // Try to find the topic accordion for this lesson
                const topicListSelectors = [
                    `.ld-topic-list-${lessonId}`,
                    `.ld-table-list-items.ld-topic-list-${lessonId}`,
                    `#ld-topic-list-${lessonId}`
                ];
                
                let topicList = null;
                for (let selector of topicListSelectors) {
                    topicList = document.querySelector(selector);
                    if (topicList) {
                        console.log('✅ Found topic accordion for lesson ' + lessonId + ':', selector);
                        break;
                    }
                }
                
                if (topicList && !topicList.querySelector('.ld-lesson-video-injection')) {
                    // Create video wrapper that looks like a topic item
                    const videoWrapper = document.createElement('div');
                    videoWrapper.className = 'ld-table-list-item ld-lesson-video-injection';
                    videoWrapper.style.marginBottom = '10px';
                    videoWrapper.style.backgroundColor = '#f9f9f9';
                    videoWrapper.style.padding = '12px';
                    videoWrapper.style.border = '1px solid #ddd';
                    videoWrapper.style.borderRadius = '4px';
                    
                    // Create video container
                    const videoDiv = document.createElement('div');
                    videoDiv.innerHTML = videoData.html;
                    
                    // Fix iframe styling to prevent tiny grey square
                    const iframe = videoDiv.querySelector('iframe');
                    if (iframe) {
                        iframe.style.width = '100%';
                        iframe.style.height = '450px'; // Increased height for better visibility
                        iframe.style.maxWidth = '100%';
                        iframe.style.display = 'block';
                        iframe.removeAttribute('height'); // Remove conflicting height attribute
                    }
                    
                    videoWrapper.appendChild(videoDiv);
                    
                    // Insert as the first element in the topic list
                    topicList.insertBefore(videoWrapper, topicList.firstChild);
                    console.log('📺 Video injected as first element in topic accordion for lesson ' + lessonId);
                    
                    injected = true;
                } else if (!topicList) {
                    console.warn('❌ Could not find topic accordion for lesson ' + lessonId);
                } else {
                    console.log('ℹ️ Video already exists for lesson ' + lessonId);
                }
                
                if (!injected) {
                    console.warn('❌ Could not find injection point for lesson ' + lessonId);
                }
            });
        }, 1000); // Wait 1 second for accordion to render
    });
    </script>
    <?php
}

// Plugin is active and working

/**
 * Simple LearnDash Video Support Class
 * 
 * This class provides minimal video support without affecting design
 */
if (!class_exists('Lilac_LearnDash_Video_Simple')) {
class Lilac_LearnDash_Video_Simple {
    
    public function __construct() {
        // Only initialize if LearnDash is active
        if (!class_exists('SFWD_LMS')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks - DOM injection approach for Elementor compatibility
     */
    private function init_hooks() {
        // Enable LearnDash video processing (required constant)
        add_action('init', array($this, 'enable_video_constant'), 1);
        
        // Test if plugin is loading at all
        add_action('wp_head', array($this, 'test_plugin_loading'));
        
        // Use DOM injection instead of content filter (Elementor bypasses the_content)
        add_action('wp_footer', array($this, 'inject_video_script'));
    }
    
    /**
     * Test if plugin is loading at all
     */
    public function test_plugin_loading() {
        echo "<!-- 🚀 LearnDash Video Plugin: LOADED AND RUNNING! -->\n";
        if (is_singular('sfwd-lessons')) {
            echo "<!-- 🎯 This is a lesson page! -->\n";
        } else {
            echo "<!-- 📄 This is NOT a lesson page -->\n";
        }
    }
    
    /**
     * Enable the required LearnDash video constant
     */
    public function enable_video_constant() {
        if (!defined('LEARNDASH_LESSON_VIDEO')) {
            define('LEARNDASH_LESSON_VIDEO', true);
        }
    }
    
    /**
     * Inject video script into footer - DOM injection approach for Elementor compatibility
     */
    public function inject_video_script() {
        // Debug: Always output this comment to verify plugin is running
        echo "<!-- 🔥 LearnDash Video Plugin: inject_video_script called -->\n";
        
        // Only run on single lesson pages
        if (!is_singular('sfwd-lessons')) {
            echo "<!-- ❌ Not a single lesson page -->\n";
            return;
        }
        
        echo "<!-- ✅ This IS a single lesson page -->\n";
        
        $lesson_id = get_the_ID();
        $video_url = $this->get_lesson_video_url($lesson_id);
        
        if (empty($video_url)) {
            echo "<!-- LearnDash Video Plugin: No video URL found for lesson {$lesson_id} -->\n";
            return;
        }
        
        $lesson_settings = learndash_get_setting($lesson_id);
        $video_enabled = !empty($lesson_settings['lesson_video_enabled']);
        $video_position = !empty($lesson_settings['lesson_video_shown']) ? $lesson_settings['lesson_video_shown'] : 'BEFORE';
        
        if (!$video_enabled) {
            echo "<!-- LearnDash Video Plugin: Video not enabled for lesson {$lesson_id} -->\n";
            return;
        }
        
        $video_html = $this->generate_video_html($video_url);
        
        // JavaScript DOM injection with console logging
        ?>
        <script>
        console.log('🚀 SCRIPT LOADED! LearnDash Video Plugin is running!');
        console.log('🎥 LearnDash Video Plugin: Starting DOM injection...');
        console.log('📹 Video URL:', <?php echo json_encode($video_url); ?>);
        console.log('⚙️ Video enabled:', <?php echo json_encode($video_enabled); ?>);
        console.log('📍 Video position:', <?php echo json_encode($video_position); ?>);
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📄 DOM loaded, searching for injection points...');
            
            // Try multiple injection points for Elementor compatibility
            const injectionPoints = [
                '.elementor-widget-theme-post-content .elementor-widget-container',
                '.entry-content',
                '.learndash-wrapper',
                '.ld-item-content',
                'main',
                'article'
            ];
            
            let injected = false;
            
            for (let selector of injectionPoints) {
                const target = document.querySelector(selector);
                if (target && !injected) {
                    console.log('✅ Found injection point:', selector);
                    
                    const videoContainer = document.createElement('div');
                    videoContainer.innerHTML = <?php echo json_encode($video_html); ?>;
                    videoContainer.style.marginBottom = '20px';
                    
                    <?php if ($video_position === 'BEFORE'): ?>
                    target.insertBefore(videoContainer, target.firstChild);
                    console.log('📺 Video injected BEFORE content');
                    <?php else: ?>
                    target.appendChild(videoContainer);
                    console.log('📺 Video injected AFTER content');
                    <?php endif; ?>
                    
                    injected = true;
                    break;
                }
            }
            
            if (!injected) {
                console.warn('❌ No suitable injection point found. Available elements:');
                injectionPoints.forEach(selector => {
                    const el = document.querySelector(selector);
                    console.log(selector + ':', el ? 'Found' : 'Not found');
                });
            }
        });
        </script>
        <!-- LearnDash Video Plugin: DOM injection script loaded -->
        <?php
    }
    
    /**
     * Get lesson video URL from LearnDash settings
     */
    private function get_lesson_video_url($lesson_id) {
        $lesson_settings = learndash_get_setting($lesson_id);
        
        if (!empty($lesson_settings['lesson_video_url'])) {
            return $lesson_settings['lesson_video_url'];
        }
        
        return '';
    }
    
    /**
     * Generate simple video HTML
     */
    private function generate_video_html($video_url) {
        // Handle YouTube URLs
        if (strpos($video_url, 'youtu.be') !== false || strpos($video_url, 'youtube.com') !== false) {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
            if (!empty($matches[1])) {
                $video_id = $matches[1];
                return '<div class="ld-video"><iframe width="100%" height="600" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allowfullscreen></iframe></div>';
            }
        }
        
        // For other videos, return simple video tag
        return '<div class="ld-video"><video width="100%" height="400" controls><source src="' . esc_url($video_url) . '"></video></div>';
    }
}

// Initialize the plugin
new Lilac_LearnDash_Video_Simple();
}
