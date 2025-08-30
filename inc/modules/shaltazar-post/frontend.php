<?php
/**
 * Shaltazar Post Frontend Functions
 * 
 * @package ChildTheme
 * @subpackage Modules/ShaltazarPost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get formatted duration for a Shaltazar post
 * 
 * @param int $post_id The post ID
 * @return string Formatted duration string
 */
function get_shaltazar_duration($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $minutes = get_field('duration_minutes', $post_id);
    $seconds = get_field('duration_seconds', $post_id);
    
    if (!$minutes && !$seconds) {
        return '';
    }
    
    $duration_parts = array();
    
    if ($minutes) {
        $duration_parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    
    if ($seconds) {
        $duration_parts[] = $seconds . ' second' . ($seconds > 1 ? 's' : '');
    }
    
    return implode(', ', $duration_parts);
}

/**
 * Display formatted duration for a Shaltazar post
 * 
 * @param int $post_id The post ID
 */
function shaltazar_duration($post_id = null) {
    echo get_shaltazar_duration($post_id);
}

/**
 * Get all available links for a Shaltazar post
 * 
 * @param int $post_id The post ID
 * @return array Array of links with their types
 */
function get_shaltazar_links($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $links = array();
    
    $link_fields = array(
        'youtube_link' => 'YouTube',
        'youtube_link_old' => 'YouTube (Archive)',
        'insight_timer_link' => 'Insight Timer',
        'pdf_link' => 'PDF Download',
        'edit_link' => 'Edit'
    );
    
    foreach ($link_fields as $field => $label) {
        $url = get_field($field, $post_id);
        if ($url) {
            $links[$field] = array(
                'url' => $url,
                'label' => $label,
                'type' => $field
            );
        }
    }
    
    return $links;
}

/**
 * Display links for a Shaltazar post
 * 
 * @param int $post_id The post ID
 * @param string $wrapper_class CSS class for wrapper
 */
function shaltazar_links($post_id = null, $wrapper_class = 'shaltazar-links') {
    $links = get_shaltazar_links($post_id);
    
    if (empty($links)) {
        return;
    }
    
    echo '<div class="' . esc_attr($wrapper_class) . '">';
    
    foreach ($links as $link) {
        $icon_class = '';
        
        // Add appropriate icons based on link type
        switch ($link['type']) {
            case 'youtube_link':
            case 'youtube_link_old':
                $icon_class = 'fab fa-youtube';
                break;
            case 'pdf_link':
                $icon_class = 'fas fa-file-pdf';
                break;
            case 'edit_link':
                $icon_class = 'fas fa-edit';
                break;
            default:
                $icon_class = 'fas fa-external-link-alt';
        }
        
        printf(
            '<a href="%s" class="shaltazar-link shaltazar-link-%s" target="_blank" rel="noopener noreferrer">%s%s</a>',
            esc_url($link['url']),
            esc_attr($link['type']),
            $icon_class ? '<i class="' . esc_attr($icon_class) . '" aria-hidden="true"></i> ' : '',
            esc_html($link['label'])
        );
    }
    
    echo '</div>';
}

/**
 * Get Shaltazar post meta information
 * 
 * @param int $post_id The post ID
 * @return array Array of meta information
 */
function get_shaltazar_meta($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    
    $meta = array();
    
    $fields = array(
        'theme' => 'Theme',
        'content_type' => 'Content Type',
        'date_channelled' => 'Date Channelled',
        'audio_file_name' => 'Audio File',
        'google_docs_id' => 'Google Docs ID'
    );
    
    foreach ($fields as $field => $label) {
        $value = get_field($field, $post_id);
        if ($value) {
            if ($field === 'date_channelled') {
                $value = date('F j, Y', strtotime($value));
            }
            $meta[$field] = array(
                'label' => $label,
                'value' => $value
            );
        }
    }
    
    // Add duration if available
    $duration = get_shaltazar_duration($post_id);
    if ($duration) {
        $meta['duration'] = array(
            'label' => 'Duration',
            'value' => $duration
        );
    }
    
    return $meta;
}

/**
 * Display Shaltazar post meta information
 * 
 * @param int $post_id The post ID
 * @param string $wrapper_class CSS class for wrapper
 */
function shaltazar_meta($post_id = null, $wrapper_class = 'shaltazar-meta') {
    $meta = get_shaltazar_meta($post_id);
    
    if (empty($meta)) {
        return;
    }
    
    echo '<div class="' . esc_attr($wrapper_class) . '">';
    
    foreach ($meta as $key => $item) {
        printf(
            '<div class="shaltazar-meta-item shaltazar-meta-%s"><span class="meta-label">%s:</span> <span class="meta-value">%s</span></div>',
            esc_attr($key),
            esc_html($item['label']),
            esc_html($item['value'])
        );
    }
    
    echo '</div>';
}

/**
 * Get Shaltazar posts by theme
 * 
 * @param string $theme Theme name
 * @param array $args Additional query arguments
 * @return WP_Query Query object
 */
function get_shaltazar_posts_by_theme($theme, $args = array()) {
    $default_args = array(
        'post_type' => 'shaltazar_post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'theme',
                'value' => $theme,
                'compare' => '='
            )
        )
    );
    
    $args = wp_parse_args($args, $default_args);
    
    return new WP_Query($args);
}

/**
 * Get all unique themes from Shaltazar posts
 * 
 * @return array Array of unique themes
 */
function get_shaltazar_themes() {
    global $wpdb;
    
    $themes = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = 'theme' 
             AND p.post_type = 'shaltazar_post' 
             AND p.post_status = 'publish' 
             AND pm.meta_value != '' 
             ORDER BY pm.meta_value ASC"
        )
    );
    
    return $themes ? $themes : array();
}

/**
 * Custom query modifications for Shaltazar post archives
 */
function modify_shaltazar_archive_query($query) {
    if (!is_admin() && $query->is_main_query()) {
        if (is_post_type_archive('shaltazar_post')) {
            // Order by date channelled if available, otherwise by post date
            $query->set('meta_key', 'date_channelled');
            $query->set('orderby', 'meta_value');
            $query->set('order', 'DESC');
            
            // Set posts per page if needed
            $query->set('posts_per_page', 12);
        }
    }
}
add_action('pre_get_posts', 'modify_shaltazar_archive_query');

/**
 * Add custom body classes for Shaltazar posts
 */
function shaltazar_body_classes($classes) {
    if (is_singular('shaltazar_post')) {
        $classes[] = 'single-shaltazar-post';
        
        $content_type = get_field('content_type');
        if ($content_type) {
            $classes[] = 'shaltazar-content-type-' . sanitize_html_class(strtolower($content_type));
        }
        
        $theme = get_field('theme');
        if ($theme) {
            $classes[] = 'shaltazar-theme-' . sanitize_html_class(strtolower($theme));
        }
    } elseif (is_post_type_archive('shaltazar_post')) {
        $classes[] = 'archive-shaltazar-post';
    }
    
    return $classes;
}
add_filter('body_class', 'shaltazar_body_classes');
