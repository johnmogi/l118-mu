/**
 * LearnDash Course Enhancements
 * Handles video resizing and UI translations
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('LearnDash Course Enhancements: Script loaded');
    
    // Function to adjust video height
    function adjustVideoHeight() {
        console.log('Adjusting video height...');
        
        // Target both single lesson videos and course accordion videos
        $('.ld-video, .ld-video iframe').each(function() {
            const $container = $(this).hasClass('ld-video') ? $(this) : $(this).parent();
            
            // Skip if already processed
            if ($container.hasClass('ld-video-adjusted')) {
                return;
            }
            
            console.log('Processing video container:', $container);
            
            // Make video container responsive with 16:9 aspect ratio
            $container.css({
                'position': 'relative',
                'padding-bottom': '56.25%', // 16:9 aspect ratio
                'height': '0',
                'overflow': 'hidden',
                'max-width': '100%',
                'margin': '15px 0'
            });
            
            // Make iframe fill container
            $container.find('iframe').css({
                'position': 'absolute',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'border': '0'
            });
            
            // Mark as processed
            $container.addClass('ld-video-adjusted');
            console.log('Video container adjusted:', $container);
        });
    }
    
    // Function to translate UI elements
    function translateUI() {
        console.log('Translating UI elements...');
        
        // Expand/Collapse All button
        $('.ld-expand-button .ld-text').each(function() {
            const $button = $(this);
            const currentText = $button.text().trim();
            
            if (currentText === 'Expand All') {
                console.log('Found Expand All button, updating text');
                $button.text('הרחב הכל');
                // Also update the data attribute for the toggle state
                $button.closest('.ld-expand-button')
                    .attr('data-ld-expand-text', 'הרחב הכל')
                    .attr('data-ld-collapse-text', 'סגור הכל');
            } else if (currentText === 'Collapse All') {
                console.log('Found Collapse All button, updating text');
                $button.text('סגור הכל');
            }
        });
        
        // Update the Lessons heading if it exists
        $('.ld-section-heading h2:contains("Lessons")').text('שיעורים');
    }
    
    // Run on page load
    adjustVideoHeight();
    translateUI();
    
    // Also run after AJAX loads (for dynamic content)
    $(document).ajaxComplete(function() {
        console.log('AJAX complete, running adjustments...');
        adjustVideoHeight();
        translateUI();
    });
    
    // Run after a short delay to catch any late-loading content
    setTimeout(function() {
        console.log('Delayed adjustments...');
        adjustVideoHeight();
        translateUI();
    }, 1000);
    
    // MutationObserver to catch dynamically added content
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            console.log('DOM mutation detected, running adjustments...');
            adjustVideoHeight();
            translateUI();
        });
        
        // Start observing the document with the configured parameters
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
});
