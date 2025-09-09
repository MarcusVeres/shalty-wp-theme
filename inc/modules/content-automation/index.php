<?php
/**
 * Content Automation Module - Main Index File
 * 
 * This file should be placed at:
 * /inc/modules/content-automation/index.php
 * 
 * Handles Google Docs content fetching and YouTube thumbnail processing
 * for Shaltazar posts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load and register content automation functionality
 */
function load_content_automation_module() {
    $module_path = get_stylesheet_directory() . '/inc/modules/content-automation/';
    
    // Check if module directory exists
    if (!is_dir($module_path)) {
        return;
    }
    
    // Load module files in order
    $files = array(
        'utilities.php',
        'google-docs.php',
        'youtube-processor.php',
        'admin.php',
        'ajax-handlers.php',
        'category-automation.php'
    );
    
    foreach ($files as $file) {
        $file_path = $module_path . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    // Initialize the module
    content_automation_init();
}

/**
 * Initialize content automation features
 */
function content_automation_init() {
    // Add admin menu
    add_action('admin_menu', 'content_automation_add_admin_menu');
    
    // Add AJAX handlers
    add_action('wp_ajax_process_single_post', 'content_automation_process_single_post');
    add_action('wp_ajax_start_batch_process', 'content_automation_start_batch_process');
    add_action('wp_ajax_get_batch_status', 'content_automation_get_batch_status');
    
    // Add meta boxes to individual posts
    add_action('add_meta_boxes', 'content_automation_add_meta_boxes');
    
    // Enqueue admin scripts
    add_action('admin_enqueue_scripts', 'content_automation_enqueue_scripts');
}

// Hook everything to init
add_action('init', 'load_content_automation_module', 15);

/**
 * Add admin column buttons to Shaltazar post list
 */
function content_automation_add_admin_columns($columns) {
    // Add content automation column before date
    $new_columns = array();
    foreach ($columns as $key => $value) {
        if ($key === 'date') {
            $new_columns['content_automation'] = 'Automation';
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}
add_filter('manage_shaltazar_post_posts_columns', 'content_automation_add_admin_columns');

/**
 * Populate admin column with quick action buttons
 */
function content_automation_admin_column_content($column, $post_id) {
    if ($column === 'content_automation') {
        $status = get_content_automation_status($post_id);
        
        echo '<div class="ca-quick-actions" data-post-id="' . $post_id . '">';
        
            // Status indicators
            echo '<div class="ca-status-indicators">';
            echo '<span title="Content" class="ca-indicator ca-content-' . ($status['has_content'] ? 'yes' : 'no') . '">C</span>';
            echo '<span title="Featured Image" class="ca-indicator ca-image-' . ($status['has_featured_image'] ? 'yes' : 'no') . '">I</span>';
            echo '</div>';
        
        echo '</div>';
    }
}
add_action('manage_shaltazar_post_posts_custom_column', 'content_automation_admin_column_content', 10, 2);

/**
 * Add CSS for admin columns
 */
function content_automation_admin_styles() {
    echo '<style>
        .ca-quick-actions { font-size: 12px; }
        .ca-status-indicators { margin-bottom: 3px; }
        .ca-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            border-radius: 2px;
            font-size: 10px;
            font-weight: bold;
            margin-right: 2px;
        }
        .ca-content-yes, .ca-image-yes { background: #00a32a; color: white; }
        .ca-content-no, .ca-image-no { background: #ddd; color: #666; }
        .button-small { 
            padding: 2px 6px !important; 
            font-size: 11px !important; 
            height: auto !important; 
            line-height: 1.2 !important;
            margin: 1px !important;
        }
    </style>';
}
add_action('admin_head', 'content_automation_admin_styles');
