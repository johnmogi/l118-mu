// Wait for both jQuery and DOM to be ready
(function() {
    function initializePopup() {
        if (typeof jQuery === 'undefined') {
            console.log('QPT: jQuery not loaded, waiting...');
            setTimeout(initializePopup, 100);
            return;
        }
        
        if (typeof qpt_ajax === 'undefined') {
            console.log('QPT: AJAX object not loaded, waiting...');
            setTimeout(initializePopup, 100);
            return;
        }
        
        console.log('QPT: Initializing popup tool...');
        
        jQuery(document).ready(function($) {
            let selectedCategories = [];
            let previewData = {};
            
            console.log('QPT: DOM ready, setting up event handlers...');
    
    // Open popup
    $('#qpt-open-popup').on('click', function(e) {
        e.preventDefault();
        $('#qpt-popup-overlay').fadeIn(300);
        loadCategories();
    });
    
    // Close popup
    $('#qpt-close, #qpt-popup-overlay').on('click', function(e) {
        if (e.target === this) {
            $('#qpt-popup-overlay').fadeOut(300);
            resetPopup();
        }
    });
    
    // Navigation
    $('#qpt-next-step').on('click', function() {
        if (selectedCategories.length === 0) {
            alert('Please select at least one category');
            return;
        }
        showStep(2);
        loadPreview();
    });
    
    $('#qpt-back-step').on('click', function() {
        showStep(1);
    });
    
    $('#qpt-populate').on('click', function() {
        populateQuiz();
    });
    
    $('#qpt-finish').on('click', function() {
        $('#qpt-popup-overlay').fadeOut(300);
        // Trigger save or refresh
        if (typeof wp !== 'undefined' && wp.data) {
            wp.data.dispatch('core/editor').savePost();
        } else {
            location.reload();
        }
    });
    
    // Category selection
    $(document).on('change', '.qpt-category-checkbox', function() {
        const categoryId = parseInt($(this).val());
        const isChecked = $(this).is(':checked');
        
        if (isChecked) {
            selectedCategories.push(categoryId);
        } else {
            selectedCategories = selectedCategories.filter(id => id !== categoryId);
        }
        
        $('#qpt-next-step').prop('disabled', selectedCategories.length === 0);
        updateSelectedCount();
    });
    
    // Questions per category change
    $('#qpt-questions-per-cat').on('change', function() {
        if (Object.keys(previewData).length > 0) {
            loadPreview();
        }
    });
    
    $('#qpt-random-selection').on('change', function() {
        if (Object.keys(previewData).length > 0) {
            loadPreview();
        }
    });
    
    function showStep(stepNumber) {
        $('.qpt-step').hide();
        $('#qpt-step-' + stepNumber).show();
    }
    
    function resetPopup() {
        selectedCategories = [];
        previewData = {};
        showStep(1);
        $('#qpt-categories-list').empty().hide();
        $('#qpt-categories-loading').show();
        $('#qpt-preview-list').empty();
        $('#qpt-next-step').prop('disabled', true);
    }
    
    function loadCategories() {
        $.ajax({
            url: qpt_ajax.url,
            type: 'POST',
            data: {
                action: 'qpt_get_categories',
                nonce: qpt_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCategories(response.data);
                } else {
                    showError('Failed to load categories: ' + response.data);
                }
            },
            error: function() {
                showError('Network error while loading categories');
            }
        });
    }
    
    function renderCategories(categories) {
        $('#qpt-categories-loading').hide();
        
        if (categories.length === 0) {
            $('#qpt-categories-list').html('<p>No categories found. Please create some quiz categories first.</p>').show();
            return;
        }
        
        let html = '<div class="qpt-categories-grid">';
        
        categories.forEach(function(cat) {
            const countText = cat.count > 0 ? cat.count + ' questions' : 'No questions';
            const countClass = cat.count > 0 ? 'has-questions' : 'no-questions';
            
            html += `
                <div class="qpt-category-item ${countClass}">
                    <label>
                        <input type="checkbox" class="qpt-category-checkbox" value="${cat.id}" ${cat.count === 0 ? 'disabled' : ''}>
                        <div class="qpt-category-info">
                            <strong>${cat.name}</strong>
                            <span class="qpt-question-count">${countText}</span>
                            ${cat.description ? '<p class="qpt-category-desc">' + cat.description + '</p>' : ''}
                        </div>
                    </label>
                </div>
            `;
        });
        
        html += '</div>';
        html += '<div class="qpt-selection-summary">Selected: <span id="qpt-selected-count">0</span> categories</div>';
        
        $('#qpt-categories-list').html(html).show();
    }
    
    function updateSelectedCount() {
        $('#qpt-selected-count').text(selectedCategories.length);
    }
    
    function loadPreview() {
        const perCategory = parseInt($('#qpt-questions-per-cat').val()) || 5;
        const random = $('#qpt-random-selection').is(':checked');
        
        $('#qpt-preview-loading').show();
        $('#qpt-preview-list').empty();
        
        $.ajax({
            url: qpt_ajax.url,
            type: 'POST',
            data: {
                action: 'qpt_get_questions',
                nonce: qpt_ajax.nonce,
                categories: selectedCategories,
                per_category: perCategory,
                random: random
            },
            success: function(response) {
                $('#qpt-preview-loading').hide();
                
                if (response.success) {
                    previewData = response.data;
                    renderPreview(response.data);
                } else {
                    showError('Failed to load questions: ' + response.data);
                }
            },
            error: function() {
                $('#qpt-preview-loading').hide();
                showError('Network error while loading questions');
            }
        });
    }
    
    function renderPreview(data) {
        let html = '<div class="qpt-preview-summary">';
        let totalQuestions = 0;
        
        Object.keys(data).forEach(function(catId) {
            totalQuestions += data[catId].length;
        });
        
        html += `<h4>📊 Preview: ${totalQuestions} questions will be added</h4>`;
        html += '</div>';
        
        html += '<div class="qpt-preview-details">';
        
        Object.keys(data).forEach(function(catId) {
            const questions = data[catId];
            if (questions.length === 0) return;
            
            html += `<div class="qpt-category-preview">
                <h5>Category ${catId} (${questions.length} questions)</h5>
                <ul class="qpt-questions-list">`;
            
            questions.forEach(function(q, index) {
                if (index < 3) { // Show first 3
                    html += `<li><strong>${q.title}</strong><br><small>${q.excerpt}</small></li>`;
                } else if (index === 3 && questions.length > 3) {
                    html += `<li><em>... and ${questions.length - 3} more questions</em></li>`;
                }
            });
            
            html += '</ul></div>';
        });
        
        html += '</div>';
        
        $('#qpt-preview-list').html(html);
    }
    
    function populateQuiz() {
        if (Object.keys(previewData).length === 0) {
            showError('No questions to add');
            return;
        }
        
        $('#qpt-populate').prop('disabled', true).text('Adding questions...');
        
        $.ajax({
            url: qpt_ajax.url,
            type: 'POST',
            data: {
                action: 'qpt_populate_quiz',
                nonce: qpt_ajax.nonce,
                quiz_id: qpt_ajax.quiz_id,
                questions: previewData
            },
            success: function(response) {
                if (response.success) {
                    showStep(3);
                    $('#qpt-success-message').html(`
                        <div class="qpt-success">
                            <h4>🎉 Success!</h4>
                            <p>${response.data.message}</p>
                            <p><strong>Total questions added:</strong> ${response.data.count}</p>
                        </div>
                    `);
                } else {
                    showError('Failed to populate quiz: ' + response.data);
                    $('#qpt-populate').prop('disabled', false).text('Populate Quiz');
                }
            },
            error: function() {
                showError('Network error while populating quiz');
                $('#qpt-populate').prop('disabled', false).text('Populate Quiz');
            }
        });
    }
    
    function showError(message) {
        alert('Error: ' + message);
    }
        }); // End jQuery document ready
    }
    
    // Start initialization
    initializePopup();
})(); // End IIFE
