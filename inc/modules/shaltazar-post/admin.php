<?php
/**
 * Shaltazar Post Admin Functions
 * 
 * @package ChildTheme
 * @subpackage Modules/ShaltazarPost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom columns to the Shaltazar post list table
 */
function shaltazar_post_custom_columns($columns) {
    // Remove date and add our custom columns
    unset($columns['date']);
    
    $columns['theme'] = __('Theme', 'textdomain');
    $columns['content_type'] = __('Content Type', 'textdomain');
    $columns['date_channelled'] = __('Date Channelled', 'textdomain');
    $columns['duration'] = __('Duration', 'textdomain');
    $columns['date'] = __('Date', 'textdomain'); // Re-add date at the end
    
    return $columns;
}
add_filter('manage_shaltazar_post_posts_columns', 'shaltazar_post_custom_columns');

/**
 * Populate custom columns with data
 */
function shaltazar_post_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'theme':
            $theme = get_field('theme', $post_id);
            echo $theme ? esc_html($theme) : '-';
            break;
            
        case 'content_type':
            $content_type = get_field('content_type', $post_id);
            echo $content_type ? esc_html($content_type) : '-';
            break;
            
        case 'date_channelled':
            $date_channelled = get_field('date_channelled', $post_id);
            if ($date_channelled) {
                echo date('M j, Y', strtotime($date_channelled));
            } else {
                echo '-';
            }
            break;
            
        case 'duration':
            $minutes = get_field('duration_minutes', $post_id);
            $seconds = get_field('duration_seconds', $post_id);
            
            if ($minutes || $seconds) {
                $duration_parts = array();
                if ($minutes) {
                    $duration_parts[] = $minutes . 'm';
                }
                if ($seconds) {
                    $duration_parts[] = $seconds . 's';
                }
                echo implode(' ', $duration_parts);
            } else {
                echo '-';
            }
            break;
    }
}
add_action('manage_shaltazar_post_posts_custom_column', 'shaltazar_post_custom_column_content', 10, 2);

/**
 * Make custom columns sortable
 */
function shaltazar_post_sortable_columns($columns) {
    $columns['theme'] = 'theme';
    $columns['content_type'] = 'content_type';
    $columns['date_channelled'] = 'date_channelled';
    
    return $columns;
}
add_filter('manage_edit-shaltazar_post_sortable_columns', 'shaltazar_post_sortable_columns');

/**
 * Handle sorting for custom columns
 */
function shaltazar_post_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('theme' === $orderby) {
        $query->set('meta_key', 'theme');
        $query->set('orderby', 'meta_value');
    } elseif ('content_type' === $orderby) {
        $query->set('meta_key', 'content_type');
        $query->set('orderby', 'meta_value');
    } elseif ('date_channelled' === $orderby) {
        $query->set('meta_key', 'date_channelled');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'shaltazar_post_orderby');

/**
 * Add filter dropdowns to admin list
 */
function shaltazar_post_admin_filters() {
    global $typenow;
    
    if ($typenow == 'shaltazar_post') {
        // Theme filter
        $themes = get_posts(array(
            'post_type' => 'shaltazar_post',
            'numberposts' => -1,
            'post_status' => 'any',
            'meta_key' => 'theme',
            'fields' => 'ids'
        ));
        
        $theme_values = array();
        foreach ($themes as $post_id) {
            $theme = get_field('theme', $post_id);
            if ($theme && !in_array($theme, $theme_values)) {
                $theme_values[] = $theme;
            }
        }
        
        if (!empty($theme_values)) {
            sort($theme_values);
            echo '<select name="shaltazar_theme_filter">';
            echo '<option value="">' . __('All Themes', 'textdomain') . '</option>';
            $selected = isset($_GET['shaltazar_theme_filter']) ? $_GET['shaltazar_theme_filter'] : '';
            foreach ($theme_values as $theme) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($theme),
                    selected($selected, $theme, false),
                    esc_html($theme)
                );
            }
            echo '</select>';
        }
        
        // Content Type filter
        $content_types = get_posts(array(
            'post_type' => 'shaltazar_post',
            'numberposts' => -1,
            'post_status' => 'any',
            'meta_key' => 'content_type',
            'fields' => 'ids'
        ));
        
        $content_type_values = array();
        foreach ($content_types as $post_id) {
            $content_type = get_field('content_type', $post_id);
            if ($content_type && !in_array($content_type, $content_type_values)) {
                $content_type_values[] = $content_type;
            }
        }
        
        if (!empty($content_type_values)) {
            sort($content_type_values);
            echo '<select name="shaltazar_content_type_filter">';
            echo '<option value="">' . __('All Content Types', 'textdomain') . '</option>';
            $selected = isset($_GET['shaltazar_content_type_filter']) ? $_GET['shaltazar_content_type_filter'] : '';
            foreach ($content_type_values as $content_type) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($content_type),
                    selected($selected, $content_type, false),
                    esc_html($content_type)
                );
            }
            echo '</select>';
        }
    }
}
add_action('restrict_manage_posts', 'shaltazar_post_admin_filters');

/**
 * Apply admin filters to query
 */
function shaltazar_post_filter_query($query) {
    global $pagenow;
    
    if (is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'shaltazar_post') {
        $meta_query = array('relation' => 'AND');
        
        if (isset($_GET['shaltazar_theme_filter']) && $_GET['shaltazar_theme_filter'] != '') {
            $meta_query[] = array(
                'key' => 'theme',
                'value' => $_GET['shaltazar_theme_filter'],
                'compare' => '='
            );
        }
        
        if (isset($_GET['shaltazar_content_type_filter']) && $_GET['shaltazar_content_type_filter'] != '') {
            $meta_query[] = array(
                'key' => 'content_type',
                'value' => $_GET['shaltazar_content_type_filter'],
                'compare' => '='
            );
        }
        
        if (count($meta_query) > 1) {
            $query->query_vars['meta_query'] = $meta_query;
        }
    }
}
add_filter('parse_query', 'shaltazar_post_filter_query');

/**
 * Add helpful admin notices for ACF field connection
 */
function shaltazar_post_admin_notices() {
    $screen = get_current_screen();
    
    if ($screen->post_type == 'shaltazar_post' && $screen->base == 'edit') {
        if (!function_exists('get_field')) {
            echo '<div class="notice notice-warning"><p>';
            echo __('Advanced Custom Fields plugin is required for full functionality of Shaltazar Posts.', 'textdomain');
            echo '</p></div>';
        }
    }
}
add_action('admin_notices', 'shaltazar_post_admin_notices');
