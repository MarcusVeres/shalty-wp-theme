/**
 * Content Automation Admin JavaScript w/ DEBUG 
 */

(function($) {
    'use strict';
    
    console.log('Content Automation JS loaded');
    
    let batchInterval;
    let batchRunning = false;
    let massDeleteInterval;
    let massDeleteRunning = false;
    
    $(document).ready(function() {
        console.log('Document ready, initializing handlers');
        console.log('caAdmin object:', caAdmin);
        
        // Initialize all handlers
        initIndividualPostActions();
        initBatchProcessing();
        initMassDelete();
        initTestingTools();
        initLogActions();
        
        // Check if batch is already running on page load
        checkBatchStatus();
        
        // Check if mass delete is already running on page load
        checkMassDeleteStatus();
    });
    
    /**
     * Individual post action handlers
     */
    function initIndividualPostActions() {
        $(document).on('click', '.ca-fetch-content, .ca-process-thumbnail, .ca-sync-category, .ca-process-both, .ca-process-all', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const postId = $button.data('post-id');
            const action = $button.data('action');
            
            const forceUpdate = $button.siblings('label').find('.ca-force-update').is(':checked');
            
            // Confirm if force update is enabled
            if (forceUpdate && !confirm(caAdmin.messages.confirm_force)) {
                return;
            }
            
            processSinglePost(postId, action, forceUpdate, $button);
        });
    }
    
    /**
     * Process a single post
     */
    function processSinglePost(postId, action, forceUpdate, $button) {
        const $resultsDiv = $('#ca-processing-results-' + postId);
        
        // Update button state
        $button.prop('disabled', true).text(caAdmin.messages.processing);
        
        // Show and clear results div
        $resultsDiv.removeClass('success error').addClass('show').html('<p>Processing...</p>');
        
        // Make AJAX request
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'process_single_post',
                nonce: caAdmin.nonce,
                post_id: postId,
                action_type: action,
                force_update: forceUpdate
            },
            success: function(response) {
                if (response.success) {
                    $resultsDiv.removeClass('error').addClass('success');
                    
                    let html = '<h4>Success!</h4>';
                    
                    if (response.data.content_result && response.data.thumbnail_result && response.data.category_result) {
                        // Process all action
                        html += '<div><strong>Content:</strong> ';
                        html += response.data.content_result.success ? 
                            '✅ ' + response.data.content_result.message : 
                            '❌ ' + response.data.content_result.error;
                        html += '</div>';
                        
                        html += '<div><strong>Thumbnail:</strong> ';
                        html += response.data.thumbnail_result.success ? 
                            '✅ ' + response.data.thumbnail_result.message : 
                            '❌ ' + response.data.thumbnail_result.error;
                        html += '</div>';
                        
                        html += '<div><strong>Category:</strong> ';
                        html += response.data.category_result.success ? 
                            '✅ ' + response.data.category_result.message : 
                            '❌ ' + response.data.category_result.error;
                        html += '</div>';
                        
                    } else if (response.data.content_result && response.data.thumbnail_result) {
                        // Process both action (legacy)
                        html += '<div><strong>Content:</strong> ';
                        html += response.data.content_result.success ? 
                            '✅ ' + response.data.content_result.message : 
                            '❌ ' + response.data.content_result.error;
                        html += '</div>';
                        
                        html += '<div><strong>Thumbnail:</strong> ';
                        html += response.data.thumbnail_result.success ? 
                            '✅ ' + response.data.thumbnail_result.message : 
                            '❌ ' + response.data.thumbnail_result.error;
                        html += '</div>';
                    } else {
                        // Single action
                        html += '<p>' + response.data.message + '</p>';
                        
                        if (response.data.content_length) {
                            html += '<p><small>Content length: ' + response.data.content_length + ' characters</small></p>';
                        }
                        
                        if (response.data.video_id) {
                            html += '<p><small>YouTube Video ID: ' + response.data.video_id + '</small></p>';
                        }
                        
                        if (response.data.category && response.data.category.name) {
                            html += '<p><small>Category: ' + response.data.category.name;
                            if (response.data.category.created) {
                                html += ' (created new)';
                            }
                            html += '</small></p>';
                        }
                    }
                    
                    $resultsDiv.html(html);
                    
                    // Refresh page after a short delay to show updated meta
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    
                } else {
                    $resultsDiv.removeClass('success').addClass('error');
                    $resultsDiv.html('<h4>Error</h4><p>' + response.data.error + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $resultsDiv.removeClass('success').addClass('error');
                $resultsDiv.html('<h4>Error</h4><p>Request failed: ' + error + '</p>');
            },
            complete: function() {
                // Restore button
                $button.prop('disabled', false);
                
                // Reset button text based on action
                let buttonText = '';
                switch (action) {
                    case 'fetch_content':
                        buttonText = 'Fetch Content';
                        break;
                    case 'process_thumbnail':
                        buttonText = 'Download Thumbnail';
                        break;
                    case 'sync_category':
                        buttonText = 'Sync Category';
                        break;
                    case 'process_both':
                        buttonText = 'Process Both';
                        break;
                    case 'process_all':
                        buttonText = 'Process All';
                        break;
                }
                $button.text(buttonText);
            }
        });
    }
    
    /**
     * Batch processing handlers
     */
    function initBatchProcessing() {
        $('#ca-start-batch').on('click', function(e) {
            e.preventDefault();
            
            const processContent = $('#ca-batch-content').is(':checked');
            const processThumbnails = $('#ca-batch-thumbnails').is(':checked');
            const processCategories = $('#ca-batch-categories').is(':checked');
            const forceUpdate = $('#ca-batch-force').is(':checked');
            
            if (!processContent && !processThumbnails && !processCategories) {
                alert('Please select at least one processing type.');
                return;
            }
            
            if (!confirm(caAdmin.messages.confirm_batch)) {
                return;
            }
            
            startBatchProcessing(processContent, processThumbnails, processCategories, forceUpdate);
        });
        
        $('#ca-stop-batch').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Stop batch processing?')) {
                stopBatchProcessing();
            }
        });
    }
    
    /**
     * Start batch processing
     */
    function startBatchProcessing(processContent, processThumbnails, processCategories, forceUpdate) {
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'start_batch_process',
                nonce: caAdmin.nonce,
                process_content: processContent,
                process_thumbnails: processThumbnails,
                process_categories: processCategories,
                force_update: forceUpdate
            },
            success: function(response) {
                if (response.success) {
                    // Hide controls and show progress
                    $('#ca-batch-controls').hide();
                    $('#ca-batch-progress').show();
                    
                    // Start monitoring
                    batchRunning = true;
                    monitorBatchProgress();
                    
                } else {
                    alert('Failed to start batch processing: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Request failed: ' + error);
            }
        });
    }
    
    /**
     * Stop batch processing
     */
    function stopBatchProcessing() {
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stop_batch_process',
                nonce: caAdmin.nonce
            },
            success: function(response) {
                batchRunning = false;
                clearInterval(batchInterval);
                
                $('#ca-batch-controls').show();
                $('#ca-batch-progress').hide();
                
                alert(caAdmin.messages.stopped);
                location.reload();
            }
        });
    }
    
    /**
     * Monitor batch progress
     */
    function monitorBatchProgress() {
        if (!batchRunning) {
            return;
        }
        
        batchInterval = setInterval(function() {
            checkBatchStatus();
        }, 2000); // Check every 2 seconds
    }
    
    /**
     * Check batch processing status
     */
    function checkBatchStatus() {
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_batch_status',
                nonce: caAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.running) {
                    // Update progress display
                    const data = response.data;
                    const progressPercent = Math.round(data.progress_percent || 0);
                    
                    $('.ca-progress-fill').css('width', progressPercent + '%');
                    $('#ca-progress-text').text(
                        'Processed: ' + data.processed + ' / ' + data.total + 
                        ' (' + data.success + ' success, ' + data.errors + ' errors)'
                    );
                    
                    batchRunning = true;
                    
                    if (!$('#ca-batch-progress').is(':visible')) {
                        $('#ca-batch-controls').hide();
                        $('#ca-batch-progress').show();
                    }
                    
                } else if (response.success && response.data.completed) {
                    // Batch completed
                    batchRunning = false;
                    clearInterval(batchInterval);
                    
                    const data = response.data;
                    alert(
                        'Batch processing completed!\n' +
                        'Total: ' + data.total + '\n' +
                        'Success: ' + data.success + '\n' +
                        'Errors: ' + data.errors
                    );
                    
                    location.reload();
                    
                } else {
                    // No batch running
                    batchRunning = false;
                    clearInterval(batchInterval);
                    
                    $('#ca-batch-controls').show();
                    $('#ca-batch-progress').hide();
                }
            }
        });
    }
    
    /**
     * Mass delete handlers
     */
    function initMassDelete() {
        console.log('Initializing mass delete handlers');
        
        // Check if button exists
        const $button = $('#ca-start-mass-delete');
        console.log('Mass delete button found:', $button.length > 0);
        console.log('Button element:', $button[0]);
        
        $button.on('click', function(e) {
            console.log('Mass delete button clicked!');
            e.preventDefault();
            
            // Check if confirm_mass_delete message exists
            console.log('Confirm message:', caAdmin.messages.confirm_mass_delete);
            
            if (!confirm(caAdmin.messages.confirm_mass_delete || 'This will permanently delete ALL Shaltazar posts and their images. This cannot be undone. Are you sure?')) {
                console.log('User cancelled');
                return;
            }
            
            console.log('User confirmed, starting mass delete');
            startMassDelete();
        });
        
        $('#ca-stop-mass-delete').on('click', function(e) {
            console.log('Stop mass delete clicked');
            e.preventDefault();
            
            if (confirm('Stop mass deletion?')) {
                stopMassDelete();
            }
        });
        
        console.log('Mass delete handlers initialized');
    }
    
    /**
     * Start mass delete
     */
    function startMassDelete() {
        console.log('Starting mass delete AJAX request');
        
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'start_mass_delete',
                nonce: caAdmin.nonce
            },
            success: function(response) {
                console.log('Mass delete AJAX response:', response);
                
                if (response.success) {
                    // Hide controls and show progress
                    $('#ca-mass-delete-controls').hide();
                    $('#ca-mass-delete-progress').show();
                    
                    // Start monitoring
                    massDeleteRunning = true;
                    monitorMassDeleteProgress();
                    
                } else {
                    alert('Failed to start mass deletion: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('Mass delete AJAX error:', xhr, status, error);
                alert('Request failed: ' + error);
            }
        });
    }
    
    /**
     * Stop mass delete
     */
    function stopMassDelete() {
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'stop_mass_delete',
                nonce: caAdmin.nonce
            },
            success: function(response) {
                massDeleteRunning = false;
                clearInterval(massDeleteInterval);
                
                $('#ca-mass-delete-controls').show();
                $('#ca-mass-delete-progress').hide();
                
                alert('Mass deletion stopped');
                location.reload();
            }
        });
    }
    
    /**
     * Monitor mass delete progress
     */
    function monitorMassDeleteProgress() {
        if (!massDeleteRunning) {
            return;
        }
        
        massDeleteInterval = setInterval(function() {
            checkMassDeleteStatus();
        }, 1000); // Check every 1 second for quicker feedback
    }
    
    /**
     * Check mass delete status
     */
    function checkMassDeleteStatus() {
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mass_delete_status',
                nonce: caAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.running) {
                    // Update progress display
                    const data = response.data;
                    const progressPercent = Math.round(data.progress_percent || 0);
                    
                    $('#ca-mass-delete-progress .ca-progress-fill').css('width', progressPercent + '%');
                    $('#ca-mass-delete-text').text(
                        'Deleted: ' + data.processed + ' / ' + data.total + ' posts'
                    );
                    
                    massDeleteRunning = true;
                    
                    if (!$('#ca-mass-delete-progress').is(':visible')) {
                        $('#ca-mass-delete-controls').hide();
                        $('#ca-mass-delete-progress').show();
                    }
                    
                } else if (response.success && response.data.completed) {
                    // Mass delete completed
                    massDeleteRunning = false;
                    clearInterval(massDeleteInterval);
                    
                    const data = response.data;
                    alert(
                        'Mass deletion completed!\n' +
                        'Total deleted: ' + data.success + '\n' +
                        'Errors: ' + data.errors
                    );
                    
                    location.reload();
                    
                } else {
                    // No mass delete running
                    massDeleteRunning = false;
                    clearInterval(massDeleteInterval);
                    
                    $('#ca-mass-delete-controls').show();
                    $('#ca-mass-delete-progress').hide();
                }
            }
        });
    }
    
    /**
     * Testing tools handlers
     */
    function initTestingTools() {
        $('#ca-test-docs').on('click', function(e) {
            e.preventDefault();
            
            const docsId = $('#ca-test-docs-id').val().trim();
            
            if (!docsId) {
                alert('Please enter a Google Docs ID');
                return;
            }
            
            testGoogleDocsAccess(docsId);
        });
        
        $('#ca-test-youtube').on('click', function(e) {
            e.preventDefault();
            
            const youtubeUrl = $('#ca-test-youtube-url').val().trim();
            
            if (!youtubeUrl) {
                alert('Please enter a YouTube URL');
                return;
            }
            
            testYouTubeAccess(youtubeUrl);
        });
    }
    
    /**
     * Test Google Docs access
     */
    function testGoogleDocsAccess(docsId) {
        const $results = $('#ca-test-docs-results');
        $results.html('<p>Testing access...</p>');
        
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_docs_access',
                nonce: caAdmin.nonce,
                docs_id: docsId
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let html = '<h4>Test Results for: ' + data.docs_id + '</h4>';
                    
                    html += '<h5>Export URLs:</h5><ul>';
                    for (let type in data.results) {
                        const result = data.results[type];
                        html += '<li><strong>' + type + ':</strong> ';
                        
                        if (result.accessible === true) {
                            html += '✅ Accessible';
                        } else if (result.accessible === false) {
                            html += '❌ Not accessible - ' + result.error;
                        } else {
                            html += '⚠️ ' + (result.note || 'Manual testing required');
                        }
                        
                        html += '<br><small><a href="' + result.url + '" target="_blank">' + result.url + '</a></small>';
                        html += '</li>';
                    }
                    html += '</ul>';
                    
                    html += '<div style="background: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">';
                    html += '<strong>Note:</strong> For content fetching to work, the document needs to be "Published to web" ';
                    html += 'or shared as "Anyone with the link can view". See the instructions above for details.';
                    html += '</div>';
                    
                    $results.html(html);
                } else {
                    $results.html('<p style="color: red;">Error: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $results.html('<p style="color: red;">Request failed: ' + error + '</p>');
            }
        });
    }
    
    /**
     * Test YouTube access
     */
    function testYouTubeAccess(youtubeUrl) {
        const $results = $('#ca-test-youtube-results');
        $results.html('<p>Testing video access...</p>');
        
        $.ajax({
            url: caAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_youtube_access',
                nonce: caAdmin.nonce,
                youtube_url: youtubeUrl
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let html = '<h4>Test Results for Video: ' + data.video_id + '</h4>';
                    
                    if (data.test_results.accessible) {
                        html += '<p style="color: green;">✅ Video thumbnails are accessible</p>';
                        
                        html += '<h5>Available Thumbnails:</h5>';
                        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">';
                        
                        for (let quality in data.video_info.thumbnail_urls) {
                            const url = data.video_info.thumbnail_urls[quality];
                            html += '<div style="text-align: center;">';
                            html += '<img src="' + url + '" style="max-width: 100%; height: auto;" alt="' + quality + '" />';
                            html += '<br><small>' + quality + '</small>';
                            html += '</div>';
                        }
                        html += '</div>';
                        
                    } else {
                        html += '<p style="color: red;">❌ Video thumbnails not accessible</p>';
                        html += '<p>Error: ' + data.test_results.error + '</p>';
                    }
                    
                    html += '<h5>Video URLs:</h5>';
                    html += '<ul>';
                    html += '<li><strong>Watch:</strong> <a href="' + data.video_info.watch_url + '" target="_blank">' + data.video_info.watch_url + '</a></li>';
                    html += '<li><strong>Embed:</strong> <a href="' + data.video_info.embed_url + '" target="_blank">' + data.video_info.embed_url + '</a></li>';
                    html += '</ul>';
                    
                    $results.html(html);
                } else {
                    $results.html('<p style="color: red;">Error: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $results.html('<p style="color: red;">Request failed: ' + error + '</p>');
            }
        });
    }
    
    /**
     * Log management handlers
     */
    function initLogActions() {
        $('#ca-clear-logs').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Clear all processing logs?')) {
                $.ajax({
                    url: caAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'clear_logs',
                        nonce: caAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.ca-logs-container').html('<p><em>Logs cleared.</em></p>');
                        }
                    }
                });
            }
        });
    }
    
})(jQuery);
