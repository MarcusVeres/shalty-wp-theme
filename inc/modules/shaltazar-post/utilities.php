<?php
/**
 * Shaltazar Post Utility Functions
 * 
 * @package ChildTheme
 * @subpackage Modules/ShaltazarPost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if ACF is active and available
 * 
 * @return bool
 */
function shaltazar_has_acf() {
    return function_exists('get_field');
}

/**
 * Validate YouTube URL
 * 
 * @param string $url The URL to validate
 * @return bool
 */
function shaltazar_is_youtube_url($url) {
    if (empty($url)) {
        return false;
    }
    
    return (bool) preg_match('/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//', $url);
}

/**
 * Extract YouTube video ID from URL
 * 
 * @param string $url YouTube URL
 * @return string|false Video ID or false if not found
 */
function shaltazar_get_youtube_id($url) {
    if (!shaltazar_is_youtube_url($url)) {
        return false;
    }
    
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    
    if (preg_match($pattern, $url, $matches)) {
        return $matches[1];
    }
    
    return false;
}

/**
 * Get YouTube thumbnail URL
 * 
 * @param string $video_id YouTube video ID
 * @param string $quality Thumbnail quality (default, mqdefault, hqdefault, sddefault, maxresdefault)
 * @return string|false Thumbnail URL or false if invalid
 */
function shaltazar_get_youtube_thumbnail($video_id, $quality = 'hqdefault') {
    if (empty($video_id)) {
        return false;
    }
    
    $valid_qualities = array('default', 'mqdefault', 'hqdefault', 'sddefault', 'maxresdefault');
    
    if (!in_array($quality, $valid_qualities)) {
        $quality = 'hqdefault';
    }
    
    return 'https://img.youtube.com/vi/' . $video_id . '/' . $quality . '.jpg';
}

/**
 * Format duration in minutes and seconds to total seconds
 * 
 * @param int $minutes
 * @param int $seconds
 * @return int Total seconds
 */
function shaltazar_duration_to_seconds($minutes = 0, $seconds = 0) {
    return (int) $minutes * 60 + (int) $seconds;
}

/**
 * Format seconds to readable duration
 * 
 * @param int $total_seconds
 * @return string Formatted duration
 */
function shaltazar_seconds_to_duration($total_seconds) {
    if ($total_seconds < 60) {
        return $total_seconds . ' second' . ($total_seconds != 1 ? 's' : '');
    }
    
    $minutes = floor($total_seconds / 60);
    $seconds = $total_seconds % 60;
    
    $parts = array();
    
    if ($minutes > 0) {
        $parts[] = $minutes . ' minute' . ($minutes != 1 ? 's' : '');
    }
    
    if ($seconds > 0) {
        $parts[] = $seconds . ' second' . ($seconds != 1 ? 's' : '');
    }
    
    return implode(', ', $parts);
}

/**
 * Sanitize and validate Google Docs ID
 * 
 * @param string $docs_id
 * @return string|false Sanitized ID or false if invalid
 */
function shaltazar_validate_google_docs_id($docs_id) {
    if (empty($docs_id)) {
        return false;
    }
    
    // Google Docs IDs are typically 44 characters long and contain letters, numbers, hyphens, and underscores
    $sanitized = sanitize_text_field($docs_id);
    
    if (preg_match('/^[a-zA-Z0-9_-]{10,50}$/', $sanitized)) {
        return $sanitized;
    }
    
    return false;
}

/**
 * Generate Google Docs URL from ID
 * 
 * @param string $docs_id Google Docs ID
 * @return string|false Google Docs URL or false if invalid
 */
function shaltazar_get_google_docs_url($docs_id) {
    $valid_id = shaltazar_validate_google_docs_id($docs_id);
    
    if (!$valid_id) {
        return false;
    }
    
    return 'https://docs.google.com/document/d/' . $valid_id . '/edit';
}

/**
 * Check if a post has any media links
 * 
 * @param int $post_id
 * @return bool
 */
function shaltazar_has_media_links($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $media_fields = array('youtube_link', 'youtube_link_old', 'insight_timer_link', 'audio_file_name');
    
    foreach ($media_fields as $field) {
        if (get_field($field, $post_id)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get the primary media link for a post (prioritizing current YouTube over old)
 * 
 * @param int $post_id
 * @return array|false Array with 'url', 'type', and 'label' or false if none found
 */
function shaltazar_get_primary_media_link($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    // Priority order for media links
    $priority_fields = array(
        'youtube_link' => array('type' => 'youtube', 'label' => 'Watch on YouTube'),
        'insight_timer_link' => array('type' => 'insight_timer', 'label' => 'Listen on Insight Timer'),
        'youtube_link_old' => array('type' => 'youtube_old', 'label' => 'Watch on YouTube (Archive)'),
    );
    
    foreach ($priority_fields as $field => $data) {
        $url = get_field($field, $post_id);
        if ($url) {
            return array(
                'url' => $url,
                'type' => $data['type'],
                'label' => $data['label'],
                'field' => $field
            );
        }
    }
    
    return false;
}

/**
 * Generate schema.org structured data for Shaltazar posts
 * 
 * @param int $post_id
 * @return array Schema data
 */
function shaltazar_get_schema_data($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'shaltazar_post') {
        return array();
    }
    
    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'name' => get_the_title($post_id),
        'description' => get_the_excerpt($post_id),
        'url' => get_permalink($post_id),
        'datePublished' => get_the_date('c', $post_id),
        'dateModified' => get_the_modified_date('c', $post_id),
    );
    
    // Add duration if available
    $minutes = get_field('duration_minutes', $post_id);
    $seconds = get_field('duration_seconds', $post_id);
    if ($minutes || $seconds) {
        $total_seconds = shaltazar_duration_to_seconds($minutes, $seconds);
        $schema['duration'] = 'PT' . $total_seconds . 'S';
    }
    
    // Add theme as keywords
    $theme = get_field('theme', $post_id);
    if ($theme) {
        $schema['keywords'] = $theme;
    }
    
    // Add content type
    $content_type = get_field('content_type', $post_id);
    if ($content_type) {
        $schema['genre'] = $content_type;
    }
    
    // Add date channelled as creation date if available
    $date_channelled = get_field('date_channelled', $post_id);
    if ($date_channelled) {
        $schema['dateCreated'] = date('c', strtotime($date_channelled));
    }
    
    return $schema;
}

/**
 * Output schema.org structured data for Shaltazar posts
 * 
 * @param int $post_id
 */
function shaltazar_output_schema($post_id = null) {
    $schema = shaltazar_get_schema_data($post_id);
    
    if (!empty($schema)) {
        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo '</script>';
    }
}

/**
 * Add structured data to single Shaltazar posts
 */
function shaltazar_add_schema_to_head() {
    if (is_singular('shaltazar_post')) {
        shaltazar_output_schema();
    }
}
add_action('wp_head', 'shaltazar_add_schema_to_head');

/**
 * Helper function to get all Shaltazar post IDs for bulk operations
 * 
 * @param array $args Additional query arguments
 * @return array Post IDs
 */
function shaltazar_get_all_post_ids($args = array()) {
    $default_args = array(
        'post_type' => 'shaltazar_post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    );
    
    $args = wp_parse_args($args, $default_args);
    
    return get_posts($args);
}
