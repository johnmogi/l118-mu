jQuery(document).ready(function($) {
    'use strict';

    // Handle test CSV generation
    $(document).on('click', '#generate-test-csv', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.siblings('.spinner');
        var $message = $button.siblings('.notice');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $message.remove();
        
        $.ajax({
            url: importExportVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_test_csv',
                nonce: importExportVars.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.after('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    if (response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }
                } else {
                    $button.after('<div class="notice notice-error"><p>' + (response.data || importExportVars.error) + '</p></div>');
                }
            },
            error: function() {
                $button.after('<div class="notice notice-error"><p>' + importExportVars.error + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Handle chunked import start
    $(document).on('submit', '#chunked-import-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submit = $form.find('input[type="submit"]');
        var $progress = $form.find('.import-progress');
        var $progressBar = $progress.find('.progress-bar');
        var $status = $form.find('.import-status');;
        
        var formData = new FormData($form[0]);
        formData.append('action', 'start_chunked_import');
        formData.append('nonce', importExportVars.nonce);
        
        $submit.prop('disabled', true);
        $progress.removeClass('hidden');
        $status.html(importExportVars.processing);
        
        // Start the import process
        startChunkedImport(formData, $progressBar, $status, $submit);
    });
    
    /**
     * Start the chunked import process
     */
    function startChunkedImport(formData, $progressBar, $status, $submit) {
        $.ajax({
            url: importExportVars.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $status.html(importExportVars.processing + ' (0%)');
                    processImportChunk(response.data.import_id, 0, $progressBar, $status, $submit);
                } else {
                    $status.html('<span class="error">' + (response.data || importExportVars.error) + '</span>');
                    $submit.prop('disabled', false);
                }
            },
            error: function() {
                $status.html('<span class="error">' + importExportVars.error + '</span>');
                $submit.prop('disabled', false);
            }
        });
    }
    
    /**
     * Process a single chunk of the import
     */
    function processImportChunk(importId, offset, $progressBar, $status, $submit) {
        $.ajax({
            url: importExportVars.ajaxurl,
            type: 'POST',
            data: {
                action: 'process_import_chunk',
                import_id: importId,
                offset: offset,
                nonce: importExportVars.nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var progress = response.data.progress;
                    $progressBar.css('width', progress + '%').attr('aria-valuenow', progress);
                    $status.html(importExportVars.processing + ' (' + progress + '%)');
                    
                    if (response.data.complete) {
                        $status.html(importExportVars.complete);
                        $submit.prop('disabled', false);
                        
                        // Show results if available
                        if (response.data.results) {
                            $status.after('<div class="import-results"><pre>' + JSON.stringify(response.data.results, null, 2) + '</pre></div>');
                        }
                    } else {
                        // Process next chunk
                        processImportChunk(importId, response.data.offset, $progressBar, $status, $submit);
                    }
                } else {
                    $status.html('<span class="error">' + (response.data || importExportVars.error) + '</span>');
                    $submit.prop('disabled', false);
                }
            },
            error: function() {
                $status.html('<span class="error">' + importExportVars.error + '</span>');
                $submit.prop('disabled', false);
            }
        });
    }
    
    // Check import progress
    function checkImportProgress(importId) {
        // Implementation for checking import progress
    }
});
