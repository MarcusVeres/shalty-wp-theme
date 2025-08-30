<?php
/**
 * YouTube Thumbnail Processor
 * 
 * Downloads thumbnails from YouTube videos and sets them as featured images
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Process YouTube thumbnail for a post
 * 
 * @param int $post_id Post ID
 * @param bool $force_update Force update even if featured image exists
 * @return array Result with success/error information
 */
function process_youtube_thumbnail($post_id, $force_update = false) {
    // Check if post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'shaltazar_post') {
        return array(
            'success' => false,
            'error' => 'Invalid post or wrong post type'
        );
    }
    
    // Check if we should skip (already has featured image and not forcing)
    if (!$force_update && has_post_thumbnail($post_id)) {
        return array(
            'success' => false,
            'error' => 'Post already has featured image (use force update to override)'
        );
    }
    
    // Get YouTube URL (try both current and old links)
    $youtube_url = get_field('youtube_link', $post_id);
    if (empty($youtube_url)) {
        $youtube_url = get_field('youtube_link_old', $post_id);
    }
    
    if (empty($youtube_url)) {
        return array(
            'success' => false,
            'error' => 'No YouTube URL found'
        );
    }
    
    content_automation_log("Starting YouTube thumbnail processing for post ID: $post_id", 'info', $post_id);
    
    // Extract video ID from URL
    $video_id = extract_youtube_video_id($youtube_url);
    if (!$video_id) {
        $error = 'Could not extract video ID from YouTube URL: ' . $youtube_url;
        content_automation_log($error, 'error', $post_id);
        update_post_meta($post_id, '_ca_processing_errors', $error);
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Try to download the best available thumbnail
    $thumbnail_result = download_youtube_thumbnail($video_id, $post_id);
    
    if (!$thumbnail_result['success']) {
        content_automation_log($thumbnail_result['error'], 'error', $post_id);
        update_post_meta($post_id, '_ca_processing_errors', $thumbnail_result['error']);
        return $thumbnail_result;
    }
    
    // Set as featured image
    $attachment_id = $thumbnail_result['attachment_id'];
    $set_result = set_post_thumbnail($post_id, $attachment_id);
    
    if (!$set_result) {
        $error = 'Failed to set thumbnail as featured image';
        content_automation_log($error, 'error', $post_id);
        
        // Clean up uploaded file if setting as featured image failed
        wp_delete_attachment($attachment_id, true);
        
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Store processing metadata
    update_post_meta($post_id, '_ca_last_processed', current_time('mysql'));
    update_post_meta($post_id, '_ca_youtube_video_id', $video_id);
    update_post_meta($post_id, '_ca_thumbnail_attachment_id', $attachment_id);
    update_post_meta($post_id, '_ca_processing_errors', ''); // Clear previous errors
    
    $success_message = "Successfully downloaded and set YouTube thumbnail (Video ID: $video_id)";
    content_automation_log($success_message, 'success', $post_id);
    
    return array(
        'success' => true,
        'message' => $success_message,
        'video_id' => $video_id,
        'attachment_id' => $attachment_id,
        'thumbnail_url' => wp_get_attachment_url($attachment_id)
    );
}

/**
 * Extract YouTube video ID from various URL formats
 */
function extract_youtube_video_id($url) {
    if (empty($url)) {
        return false;
    }
    
    // Patterns for different YouTube URL formats
    $patterns = array(
        // Standard watch URLs
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        // Short URLs
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        // Embed URLs
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        // YouTube URLs with additional parameters
        '/youtube\.com\/.*[?&]v=([a-zA-Z0-9_-]+)/',
    );
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return false;
}

/**
 * Download YouTube thumbnail and upload to WordPress media library
 */
function download_youtube_thumbnail($video_id, $post_id) {
    // YouTube thumbnail qualities (try from best to worst)
    $qualities = array(
        'maxresdefault' => 'Maximum Resolution',
        'sddefault' => 'Standard Definition', 
        'hqdefault' => 'High Quality',
        'mqdefault' => 'Medium Quality',
        'default' => 'Default'
    );
    
    $downloaded_file = null;
    $used_quality = null;
    
    foreach ($qualities as $quality => $description) {
        $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/{$quality}.jpg";
        
        // Check if this thumbnail exists and download it
        $download_result = download_image_from_url($thumbnail_url, $video_id, $quality);
        
        if ($download_result['success']) {
            $downloaded_file = $download_result['file_path'];
            $used_quality = $quality;
            break;
        }
    }
    
    if (!$downloaded_file) {
        return array(
            'success' => false,
            'error' => 'Could not download any YouTube thumbnail quality for video: ' . $video_id
        );
    }
    
    // Upload to WordPress media library
    $upload_result = upload_image_to_media_library($downloaded_file, $video_id, $post_id);
    
    // Clean up temporary file
    if (file_exists($downloaded_file)) {
        unlink($downloaded_file);
    }
    
    if (!$upload_result['success']) {
        return $upload_result;
    }
    
    return array(
        'success' => true,
        'attachment_id' => $upload_result['attachment_id'],
        'quality_used' => $used_quality,
        'video_id' => $video_id
    );
}

/**
 * Download image from URL to temporary file
 */
function download_image_from_url($url, $video_id, $quality) {
    // Make HTTP request to get the image
    $response = content_automation_http_request($url, array(
        'timeout' => 30,
    ));
    
    if (!$response['success']) {
        return array(
            'success' => false,
            'error' => 'Failed to download thumbnail: ' . $response['error']
        );
    }
    
    $image_data = $response['body'];
    
    // Check if we got actual image data (YouTube returns small placeholder for missing images)
    if (strlen($image_data) < 1000) {
        return array(
            'success' => false,
            'error' => 'Thumbnail too small (likely placeholder) for quality: ' . $quality . ' (Size: ' . strlen($image_data) . ' bytes)'
        );
    }
    
    // Create temporary file with proper extension
    $temp_file = wp_tempnam($video_id . '_' . $quality . '_' . time());
    if (!$temp_file) {
        return array(
            'success' => false,
            'error' => 'Could not create temporary file'
        );
    }
    
    // Rename to have .jpg extension for proper validation
    $temp_file_jpg = $temp_file . '.jpg';
    if (file_exists($temp_file)) {
        rename($temp_file, $temp_file_jpg);
    }
    
    // Write image data to temporary file
    $bytes_written = file_put_contents($temp_file_jpg, $image_data);
    
    if ($bytes_written === false) {
        if (file_exists($temp_file_jpg)) {
            unlink($temp_file_jpg);
        }
        return array(
            'success' => false,
            'error' => 'Could not write image data to temporary file'
        );
    }
    
    // Verify it's a valid image
    $image_info = getimagesize($temp_file_jpg);
    if ($image_info === false) {
        unlink($temp_file_jpg);
        return array(
            'success' => false,
            'error' => 'Downloaded file is not a valid image (Size: ' . $bytes_written . ' bytes)'
        );
    }
    
    // Additional validation - check if it's actually a JPEG
    if ($image_info[2] !== IMAGETYPE_JPEG) {
        unlink($temp_file_jpg);
        return array(
            'success' => false,
            'error' => 'Downloaded file is not a JPEG image (Type: ' . $image_info[2] . ')'
        );
    }
    
    return array(
        'success' => true,
        'file_path' => $temp_file_jpg,
        'image_info' => $image_info,
        'size_bytes' => $bytes_written
    );
}

/**
 * Upload image file to WordPress media library
 * Alternative approach that bypasses wp_handle_upload validation issues
 */
function upload_image_to_media_library($file_path, $video_id, $post_id) {
    if (!file_exists($file_path)) {
        return array(
            'success' => false,
            'error' => 'File does not exist: ' . $file_path
        );
    }
    
    // Include required WordPress files
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }
    
    // Generate filename
    $post_title = get_the_title($post_id);
    $filename = sanitize_file_name($post_title . '_youtube_thumbnail_' . $video_id . '.jpg');
    
    // Get WordPress upload directory
    $upload_dir = wp_upload_dir();
    
    if ($upload_dir['error']) {
        return array(
            'success' => false,
            'error' => 'Upload directory error: ' . $upload_dir['error']
        );
    }
    
    // Create destination path
    $destination = $upload_dir['path'] . '/' . $filename;
    $destination_url = $upload_dir['url'] . '/' . $filename;
    
    // Ensure filename is unique
    $counter = 1;
    while (file_exists($destination)) {
        $name_parts = pathinfo($filename);
        $new_filename = $name_parts['filename'] . '_' . $counter . '.' . $name_parts['extension'];
        $destination = $upload_dir['path'] . '/' . $new_filename;
        $destination_url = $upload_dir['url'] . '/' . $new_filename;
        $counter++;
    }
    
    // Copy file to uploads directory
    if (!copy($file_path, $destination)) {
        return array(
            'success' => false,
            'error' => 'Failed to copy file to uploads directory'
        );
    }
    
    // Verify the copied file
    if (!file_exists($destination)) {
        return array(
            'success' => false,
            'error' => 'File was not successfully copied'
        );
    }
    
    // Get the correct MIME type
    $file_type = wp_check_filetype($destination);
    
    if (!$file_type['type']) {
        // Clean up the file we just created
        unlink($destination);
        return array(
            'success' => false,
            'error' => 'Invalid file type'
        );
    }
    
    // Create attachment
    $attachment = array(
        'guid' => $destination_url,
        'post_mime_type' => $file_type['type'],
        'post_title' => $post_title . ' - YouTube Thumbnail',
        'post_content' => '',
        'post_status' => 'inherit'
    );
    
    $attachment_id = wp_insert_attachment($attachment, $destination, $post_id);
    
    if (is_wp_error($attachment_id)) {
        // Clean up the file we created
        unlink($destination);
        return array(
            'success' => false,
            'error' => 'Failed to create attachment: ' . $attachment_id->get_error_message()
        );
    }
    
    // Generate attachment metadata (this creates different image sizes)
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $destination);
    wp_update_attachment_metadata($attachment_id, $attachment_data);
    
    return array(
        'success' => true,
        'attachment_id' => $attachment_id,
        'url' => $destination_url,
        'file_path' => $destination
    );
}

/**
 * Get YouTube video information
 */
function get_youtube_video_info($video_id) {
    // Basic info we can get without API
    $info = array(
        'video_id' => $video_id,
        'watch_url' => "https://www.youtube.com/watch?v={$video_id}",
        'embed_url' => "https://www.youtube.com/embed/{$video_id}",
        'thumbnail_urls' => array()
    );
    
    // Available thumbnail qualities
    $qualities = array('default', 'mqdefault', 'hqdefault', 'sddefault', 'maxresdefault');
    
    foreach ($qualities as $quality) {
        $info['thumbnail_urls'][$quality] = "https://img.youtube.com/vi/{$video_id}/{$quality}.jpg";
    }
    
    return $info;
}

/**
 * Test YouTube video accessibility
 */
function test_youtube_video_access($video_id) {
    // Test if the default thumbnail is accessible
    $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/default.jpg";
    
    $response = content_automation_http_request($thumbnail_url);
    
    return array(
        'video_id' => $video_id,
        'accessible' => $response['success'],
        'error' => $response['success'] ? null : $response['error'],
        'thumbnail_url' => $thumbnail_url
    );
}

/**
 * Bulk process YouTube thumbnails for multiple posts
 */
function bulk_process_youtube_thumbnails($post_ids, $force_update = false) {
    $results = array(
        'total' => count($post_ids),
        'success' => 0,
        'errors' => 0,
        'details' => array()
    );
    
    foreach ($post_ids as $post_id) {
        $result = process_youtube_thumbnail($post_id, $force_update);
        
        $results['details'][$post_id] = $result;
        
        if ($result['success']) {
            $results['success']++;
        } else {
            $results['errors']++;
        }
        
        // Add small delay to be respectful to YouTube
        usleep(250000); // 0.25 seconds
    }
    
    return $results;
}

/**
 * Debug YouTube thumbnail download process
 * Useful for troubleshooting upload issues
 */
function debug_youtube_thumbnail_process($video_id) {
    $debug_info = array(
        'video_id' => $video_id,
        'server_info' => array(),
        'download_tests' => array(),
        'upload_tests' => array()
    );
    
    // Check server capabilities
    $debug_info['server_info'] = array(
        'curl_enabled' => function_exists('curl_init'),
        'allow_url_fopen' => ini_get('allow_url_fopen'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'temp_dir' => sys_get_temp_dir(),
        'wp_temp_dir' => get_temp_dir()
    );
    
    // Test uploads directory
    $upload_dir = wp_upload_dir();
    $debug_info['upload_tests'] = array(
        'upload_dir_exists' => is_dir($upload_dir['path']),
        'upload_dir_writable' => is_writable($upload_dir['path']),
        'upload_dir_path' => $upload_dir['path'],
        'upload_dir_url' => $upload_dir['url'],
        'upload_dir_error' => $upload_dir['error']
    );
    
    // Test downloading thumbnails
    $qualities = array('default', 'hqdefault');
    foreach ($qualities as $quality) {
        $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/{$quality}.jpg";
        
        $response = content_automation_http_request($thumbnail_url);
        $debug_info['download_tests'][$quality] = array(
            'url' => $thumbnail_url,
            'success' => $response['success'],
            'error' => $response['success'] ? null : $response['error'],
            'size_bytes' => $response['success'] ? strlen($response['body']) : 0,
            'is_valid_size' => $response['success'] ? (strlen($response['body']) > 1000) : false
        );
        
        // Test if we can create a temp file
        if ($response['success']) {
            $temp_file = wp_tempnam($video_id . '_' . $quality . '_debug');
            $debug_info['download_tests'][$quality]['temp_file_created'] = $temp_file !== false;
            
            if ($temp_file) {
                $written = file_put_contents($temp_file, $response['body']);
                $debug_info['download_tests'][$quality]['temp_file_written'] = $written !== false;
                $debug_info['download_tests'][$quality]['written_bytes'] = $written;
                
                if ($written) {
                    $image_info = getimagesize($temp_file);
                    $debug_info['download_tests'][$quality]['is_valid_image'] = $image_info !== false;
                    if ($image_info) {
                        $debug_info['download_tests'][$quality]['image_dimensions'] = $image_info[0] . 'x' . $image_info[1];
                        $debug_info['download_tests'][$quality]['image_type'] = $image_info[2];
                    }
                    
                    // Clean up
                    unlink($temp_file);
                }
            }
        }
    }
    
    return $debug_info;
}

/**
 * AJAX handler for debugging YouTube thumbnails
 */
function debug_youtube_thumbnail_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'content_automation_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $video_id = sanitize_text_field($_POST['video_id']);
    
    if (empty($video_id)) {
        wp_send_json_error('Video ID is required');
    }
    
    $debug_info = debug_youtube_thumbnail_process($video_id);
    
    wp_send_json_success($debug_info);
}
add_action('wp_ajax_debug_youtube_thumbnail', 'debug_youtube_thumbnail_ajax');
