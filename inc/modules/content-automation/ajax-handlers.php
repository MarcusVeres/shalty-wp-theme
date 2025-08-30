<?php
/**
 * Content Automation AJAX Handlers
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process single post (individual post actions)
 */
function content_automation_process_single_post() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_id = intval($_POST['post_id']);
    $action = sanitize_text_field($_POST['action_type']);
    $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';
    
    // Validate post
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'shaltazar_post') {
        wp_send_json_error('Invalid post');
    }
    
    $result = array();
    
    switch ($action) {
        case 'fetch_content':
            $result = fetch_google_docs_content($post_id, $force_update);
            break;
            
        case 'process_thumbnail':
            $result = process_youtube_thumbnail($post_id, $force_update);
            break;
            
        case 'sync_category':
            $result = sync_theme_to_categories($post_id, $force_update);
            break;
            
        case 'process_both':
            $content_result = fetch_google_docs_content($post_id, $force_update);
            $thumbnail_result = process_youtube_thumbnail($post_id, $force_update);
            
            $result = array(
                'success' => true,
                'content_result' => $content_result,
                'thumbnail_result' => $thumbnail_result,
                'message' => 'Processing completed'
            );
            
            // If either failed, mark as partial success
            if (!$content_result['success'] && !$thumbnail_result['success']) {
                $result['success'] = false;
                $result['message'] = 'Both operations failed';
            } elseif (!$content_result['success'] || !$thumbnail_result['success']) {
                $result['message'] = 'Partial success - some operations failed';
            }
            break;
            
        case 'process_all':
            $content_result = fetch_google_docs_content($post_id, $force_update);
            $thumbnail_result = process_youtube_thumbnail($post_id, $force_update);
            $category_result = sync_theme_to_categories($post_id, $force_update);
            
            $result = array(
                'success' => true,
                'content_result' => $content_result,
                'thumbnail_result' => $thumbnail_result,
                'category_result' => $category_result,
                'message' => 'All processing completed'
            );
            
            // Count successes
            $success_count = 0;
            if ($content_result['success']) $success_count++;
            if ($thumbnail_result['success']) $success_count++;
            if ($category_result['success']) $success_count++;
            
            if ($success_count === 0) {
                $result['success'] = false;
                $result['message'] = 'All operations failed';
            } elseif ($success_count < 3) {
                $result['message'] = 'Partial success - some operations failed';
            }
            break;
            
        default:
            wp_send_json_error('Invalid action');
    }
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

/**
 * Start batch processing - UPDATED VERSION
 */
function content_automation_start_batch_process() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Check if batch is already running
    if (is_batch_processing_active()) {
        wp_send_json_error('Batch processing is already running');
    }
    
    $process_content = isset($_POST['process_content']) && $_POST['process_content'] === 'true';
    $process_thumbnails = isset($_POST['process_thumbnails']) && $_POST['process_thumbnails'] === 'true';
    $process_categories = isset($_POST['process_categories']) && $_POST['process_categories'] === 'true';
    $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';
    
    if (!$process_content && !$process_thumbnails && !$process_categories) {
        wp_send_json_error('At least one processing type must be selected');
    }
    
    // Get posts that need processing
    $posts_to_process = array();
    $all_posts = get_posts(array(
        'post_type' => 'shaltazar_post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    foreach ($all_posts as $post_id) {
        $needs_processing = false;
        
        if ($process_content) {
            $has_content = !empty(get_post_field('post_content', $post_id));
            $has_docs_id = !empty(get_field('google_docs_id', $post_id));
            
            if (($force_update || !$has_content) && $has_docs_id) {
                $needs_processing = true;
            }
        }
        
        if ($process_thumbnails) {
            $has_thumbnail = has_post_thumbnail($post_id);
            $has_youtube = !empty(get_field('youtube_link', $post_id)) || !empty(get_field('youtube_link_old', $post_id));
            
            if (($force_update || !$has_thumbnail) && $has_youtube) {
                $needs_processing = true;
            }
        }
        
        if ($process_categories) {
            $category_status = get_theme_category_status($post_id);
            
            if (($force_update || $category_status['needs_sync']) && $category_status['has_theme']) {
                $needs_processing = true;
            }
        }
        
        if ($needs_processing) {
            $posts_to_process[] = $post_id;
        }
    }
    
    if (empty($posts_to_process)) {
        wp_send_json_error('No posts found that need processing');
    }
    
    // Clear existing queue and add posts
    clear_processing_queue();
    add_to_processing_queue($posts_to_process);
    
    // Start batch processing
    start_batch_processing(count($posts_to_process));
    
    // Store processing options
    update_option('ca_batch_options', array(
        'process_content' => $process_content,
        'process_thumbnails' => $process_thumbnails,
        'process_categories' => $process_categories, // Make sure this is stored
        'force_update' => $force_update
    ));
    
    content_automation_log("Started batch processing for " . count($posts_to_process) . " posts", 'info');
    
    wp_send_json_success(array(
        'message' => 'Batch processing started',
        'total_posts' => count($posts_to_process)
    ));
}

/**
 * Get batch processing status - UPDATED VERSION
 */
function content_automation_get_batch_status() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    $batch_status = get_batch_processing_status();
    $queue_length = count(get_processing_queue());
    
    if (!$batch_status) {
        wp_send_json_success(array(
            'running' => false,
            'message' => 'No batch processing active'
        ));
    }
    
    // Process next item if queue has items
    if ($batch_status['status'] === 'running' && $queue_length > 0) {
        $next_post_id = get_next_from_queue();
        
        if ($next_post_id) {
            // Get processing options
            $options = get_option('ca_batch_options', array());
            
            $content_result = array('success' => true, 'message' => 'Skipped');
            $thumbnail_result = array('success' => true, 'message' => 'Skipped');
            $category_result = array('success' => true, 'message' => 'Skipped');
            
            // Process content if enabled
            if (isset($options['process_content']) && $options['process_content']) {
                $content_result = fetch_google_docs_content($next_post_id, $options['force_update']);
            }
            
            // Process thumbnail if enabled
            if (isset($options['process_thumbnails']) && $options['process_thumbnails']) {
                $thumbnail_result = process_youtube_thumbnail($next_post_id, $options['force_update']);
            }
            
            // Process categories if enabled - THIS WAS POTENTIALLY MISSING
            if (isset($options['process_categories']) && $options['process_categories']) {
                $category_result = sync_theme_to_categories($next_post_id, $options['force_update']);
            }
            
            // Update progress
            $success_count = $batch_status['success'];
            $error_count = $batch_status['errors'];
            
            $operation_success_count = 0;
            if ($content_result['success']) $operation_success_count++;
            if ($thumbnail_result['success']) $operation_success_count++;
            if ($category_result['success']) $operation_success_count++;
            
            // Count as overall success if at least one operation succeeded
            if ($operation_success_count > 0) {
                $success_count++;
            } else {
                $error_count++;
            }
            
            update_batch_progress(
                $batch_status['processed'] + 1,
                $success_count,
                $error_count,
                $next_post_id
            );
            
            // Check if we're done
            $remaining = count(get_processing_queue());
            if ($remaining === 0) {
                end_batch_processing();
                
                wp_send_json_success(array(
                    'running' => false,
                    'completed' => true,
                    'message' => 'Batch processing completed',
                    'total' => $batch_status['total'],
                    'processed' => $batch_status['processed'] + 1,
                    'success' => $success_count,
                    'errors' => $error_count
                ));
            }
        }
    }
    
    // If we're still running, return current status
    if ($batch_status['status'] === 'running') {
        wp_send_json_success(array(
            'running' => true,
            'total' => $batch_status['total'],
            'processed' => $batch_status['processed'],
            'success' => $batch_status['success'],
            'errors' => $batch_status['errors'],
            'current_post' => $batch_status['current_post'],
            'remaining' => $queue_length,
            'progress_percent' => $batch_status['total'] > 0 ? ($batch_status['processed'] / $batch_status['total'] * 100) : 0
        ));
    }
    
    wp_send_json_success($batch_status);
}

/**
 * Stop batch processing
 */
function content_automation_stop_batch_process() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    // Stop batch processing
    end_batch_processing();
    clear_processing_queue();
    
    content_automation_log("Batch processing stopped by user", 'info');
    
    wp_send_json_success(array(
        'message' => 'Batch processing stopped'
    ));
}

/**
 * Test Google Docs access
 */
function content_automation_test_docs_access() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $docs_id = sanitize_text_field($_POST['docs_id']);
    
    if (empty($docs_id)) {
        wp_send_json_error('Google Docs ID is required');
    }
    
    $test_results = test_google_docs_access($docs_id);
    
    wp_send_json_success(array(
        'docs_id' => $docs_id,
        'results' => $test_results,
        'urls' => get_google_docs_urls($docs_id)
    ));
}

/**
 * Test YouTube video access
 */
function content_automation_test_youtube_access() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $youtube_url = sanitize_url($_POST['youtube_url']);
    
    if (empty($youtube_url)) {
        wp_send_json_error('YouTube URL is required');
    }
    
    $video_id = extract_youtube_video_id($youtube_url);
    
    if (!$video_id) {
        wp_send_json_error('Could not extract video ID from URL');
    }
    
    $test_results = test_youtube_video_access($video_id);
    $video_info = get_youtube_video_info($video_id);
    
    wp_send_json_success(array(
        'video_id' => $video_id,
        'test_results' => $test_results,
        'video_info' => $video_info
    ));
}

/**
 * Clear logs
 */
function content_automation_clear_logs() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    clear_content_automation_logs();
    
    wp_send_json_success(array(
        'message' => 'Logs cleared'
    ));
}

// Register AJAX handlers
add_action('wp_ajax_process_single_post', 'content_automation_process_single_post');
add_action('wp_ajax_start_batch_process', 'content_automation_start_batch_process');
add_action('wp_ajax_get_batch_status', 'content_automation_get_batch_status');
add_action('wp_ajax_stop_batch_process', 'content_automation_stop_batch_process');
add_action('wp_ajax_test_docs_access', 'content_automation_test_docs_access');
add_action('wp_ajax_test_youtube_access', 'content_automation_test_youtube_access');
add_action('wp_ajax_clear_logs', 'content_automation_clear_logs');
