<?php
/**
 * Content Automation Utilities
 * 
 * @package ChildTheme
 * @subpackage Modules/ContentAutomation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if content automation requirements are met
 */
function content_automation_requirements_met() {
    $requirements = array();
    
    // Check if ACF is active
    if (!function_exists('get_field')) {
        $requirements[] = 'Advanced Custom Fields plugin is required';
    }
    
    // Check if cURL is available
    if (!function_exists('curl_init')) {
        $requirements[] = 'cURL extension is required';
    }
    
    // Check if allow_url_fopen is enabled (alternative to cURL)
    if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) {
        $requirements[] = 'Either cURL or allow_url_fopen must be enabled';
    }
    
    return $requirements;
}

/**
 * Get content automation status for a post
 */
function get_content_automation_status($post_id) {
    $status = array(
        'has_content' => !empty(get_post_field('post_content', $post_id)),
        'has_featured_image' => has_post_thumbnail($post_id),
        'has_google_docs_id' => !empty(get_field('google_docs_id', $post_id)),
        'has_youtube_link' => !empty(get_field('youtube_link', $post_id)) || !empty(get_field('youtube_link_old', $post_id)),
        'last_processed' => get_post_meta($post_id, '_ca_last_processed', true),
        'processing_errors' => get_post_meta($post_id, '_ca_processing_errors', true)
    );
    
    return $status;
}

/**
 * Log processing activity
 */
function content_automation_log($message, $type = 'info', $post_id = null) {
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'message' => $message,
        'type' => $type,
        'post_id' => $post_id
    );
    
    // Store in transient (expires after 24 hours)
    $existing_logs = get_transient('content_automation_logs') ?: array();
    
    // Keep only last 100 entries
    if (count($existing_logs) >= 100) {
        array_shift($existing_logs);
    }
    
    $existing_logs[] = $log_entry;
    set_transient('content_automation_logs', $existing_logs, DAY_IN_SECONDS);
    
    // Also log to WordPress debug if enabled
    if (WP_DEBUG_LOG) {
        error_log('Content Automation: ' . $message);
    }
}

/**
 * Get processing logs
 */
function get_content_automation_logs() {
    return get_transient('content_automation_logs') ?: array();
}

/**
 * Clear processing logs
 */
function clear_content_automation_logs() {
    delete_transient('content_automation_logs');
}

/**
 * Make HTTP request with proper error handling
 */
function content_automation_http_request($url, $args = array()) {
    $defaults = array(
        'timeout' => 30,
        'redirection' => 5,
        'user-agent' => 'WordPress Content Automation Bot 1.0'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'error' => $response->get_error_message()
        );
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code !== 200) {
        return array(
            'success' => false,
            'error' => 'HTTP Error: ' . $status_code
        );
    }
    
    return array(
        'success' => true,
        'body' => wp_remote_retrieve_body($response),
        'headers' => wp_remote_retrieve_headers($response)
    );
}

/**
 * Check if batch processing is currently running
 */
function is_batch_processing_active() {
    return get_transient('content_automation_batch_active') !== false;
}

/**
 * Start batch processing session
 */
function start_batch_processing($total_posts) {
    $batch_data = array(
        'total' => $total_posts,
        'processed' => 0,
        'success' => 0,
        'errors' => 0,
        'started' => current_time('mysql'),
        'current_post' => null,
        'status' => 'running'
    );
    
    set_transient('content_automation_batch_active', $batch_data, HOUR_IN_SECONDS);
    return true;
}

/**
 * Update batch processing progress
 */
function update_batch_progress($processed, $success, $errors, $current_post_id = null) {
    $batch_data = get_transient('content_automation_batch_active');
    
    if ($batch_data) {
        $batch_data['processed'] = $processed;
        $batch_data['success'] = $success;
        $batch_data['errors'] = $errors;
        $batch_data['current_post'] = $current_post_id;
        $batch_data['last_update'] = current_time('mysql');
        
        set_transient('content_automation_batch_active', $batch_data, HOUR_IN_SECONDS);
    }
}

/**
 * End batch processing session
 */
function end_batch_processing() {
    $batch_data = get_transient('content_automation_batch_active');
    
    if ($batch_data) {
        $batch_data['status'] = 'completed';
        $batch_data['completed'] = current_time('mysql');
        
        // Store in completed batches for history
        $completed_batches = get_option('content_automation_completed_batches', array());
        
        // Keep only last 10 batch runs
        if (count($completed_batches) >= 10) {
            array_shift($completed_batches);
        }
        
        $completed_batches[] = $batch_data;
        update_option('content_automation_completed_batches', $completed_batches);
    }
    
    delete_transient('content_automation_batch_active');
}

/**
 * Get batch processing status
 */
function get_batch_processing_status() {
    return get_transient('content_automation_batch_active');
}

/**
 * Get processing queue
 */
function get_processing_queue() {
    return get_option('content_automation_queue', array());
}

/**
 * Add posts to processing queue
 */
function add_to_processing_queue($post_ids) {
    $queue = get_processing_queue();
    
    // Add new post IDs, avoiding duplicates
    foreach ((array) $post_ids as $post_id) {
        if (!in_array($post_id, $queue)) {
            $queue[] = (int) $post_id;
        }
    }
    
    update_option('content_automation_queue', $queue);
    return count($queue);
}

/**
 * Get next post from queue
 */
function get_next_from_queue() {
    $queue = get_processing_queue();
    
    if (empty($queue)) {
        return false;
    }
    
    $next_post_id = array_shift($queue);
    update_option('content_automation_queue', $queue);
    
    return $next_post_id;
}

/**
 * Clear processing queue
 */
function clear_processing_queue() {
    delete_option('content_automation_queue');
}

// =============================================================================
// MASS DELETE FUNCTIONS
// =============================================================================

/**
 * Start mass delete session
 */
function start_mass_delete_session($total_posts) {
    $mass_delete_data = array(
        'total' => $total_posts,
        'processed' => 0,
        'success' => 0,
        'errors' => 0,
        'started' => current_time('mysql'),
        'current_post' => null,
        'status' => 'running'
    );
    
    set_transient('content_automation_mass_delete_active', $mass_delete_data, HOUR_IN_SECONDS);
    return true;
}

/**
 * Update mass delete progress
 */
function update_mass_delete_progress($processed, $success, $errors, $current_post_id = null) {
    $mass_delete_data = get_transient('content_automation_mass_delete_active');
    
    if ($mass_delete_data) {
        $mass_delete_data['processed'] = $processed;
        $mass_delete_data['success'] = $success;
        $mass_delete_data['errors'] = $errors;
        $mass_delete_data['current_post'] = $current_post_id;
        $mass_delete_data['last_update'] = current_time('mysql');
        
        set_transient('content_automation_mass_delete_active', $mass_delete_data, HOUR_IN_SECONDS);
    }
}

/**
 * End mass delete session
 */
function end_mass_delete_session() {
    $mass_delete_data = get_transient('content_automation_mass_delete_active');
    
    if ($mass_delete_data) {
        $mass_delete_data['status'] = 'completed';
        $mass_delete_data['completed'] = current_time('mysql');
        
        // Store in completed deletions for history
        $completed_deletions = get_option('content_automation_completed_deletions', array());
        
        // Keep only last 5 deletion runs
        if (count($completed_deletions) >= 5) {
            array_shift($completed_deletions);
        }
        
        $completed_deletions[] = $mass_delete_data;
        update_option('content_automation_completed_deletions', $completed_deletions);
    }
    
    delete_transient('content_automation_mass_delete_active');
}

/**
 * Get mass delete queue
 */
function get_mass_delete_queue() {
    return get_option('content_automation_mass_delete_queue', array());
}

/**
 * Add posts to mass delete queue
 */
function add_to_mass_delete_queue($post_ids) {
    $queue = get_mass_delete_queue();
    
    // Add new post IDs, avoiding duplicates
    foreach ((array) $post_ids as $post_id) {
        if (!in_array($post_id, $queue)) {
            $queue[] = (int) $post_id;
        }
    }
    
    update_option('content_automation_mass_delete_queue', $queue);
    return count($queue);
}

/**
 * Get next post from mass delete queue
 */
function get_next_from_mass_delete_queue() {
    $queue = get_mass_delete_queue();
    
    if (empty($queue)) {
        return false;
    }
    
    $next_post_id = array_shift($queue);
    update_option('content_automation_mass_delete_queue', $queue);
    
    return $next_post_id;
}

/**
 * Clear mass delete queue
 */
function clear_mass_delete_queue() {
    delete_option('content_automation_mass_delete_queue');
}

// =============================================================================
// END MASS DELETE FUNCTIONS
// =============================================================================

/**
 * Get processing statistics
 */
function get_processing_statistics() {
    $shaltazar_posts = get_posts(array(
        'post_type' => 'shaltazar_post',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    $stats = array(
        'total_posts' => count($shaltazar_posts),
        'with_content' => 0,
        'with_featured_image' => 0,
        'with_google_docs' => 0,
        'with_youtube_links' => 0,
        'fully_processed' => 0
    );
    
    foreach ($shaltazar_posts as $post_id) {
        $status = get_content_automation_status($post_id);
        
        if ($status['has_content']) $stats['with_content']++;
        if ($status['has_featured_image']) $stats['with_featured_image']++;
        if ($status['has_google_docs_id']) $stats['with_google_docs']++;
        if ($status['has_youtube_link']) $stats['with_youtube_links']++;
        
        if ($status['has_content'] && $status['has_featured_image']) {
            $stats['fully_processed']++;
        }
    }
    
    return $stats;
}

/**
 * Check if post needs processing
 */
function post_needs_processing($post_id, $force = false) {
    if ($force) {
        return true;
    }
    
    $status = get_content_automation_status($post_id);
    
    // Process if missing content and has Google Docs ID
    if (!$status['has_content'] && $status['has_google_docs_id']) {
        return true;
    }
    
    // Process if missing featured image and has YouTube link
    if (!$status['has_featured_image'] && $status['has_youtube_link']) {
        return true;
    }
    
    return false;
}
