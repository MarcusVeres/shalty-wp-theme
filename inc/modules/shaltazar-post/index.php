<?php
/**
 * Shaltazar Post Module Loader - SIMPLE VERSION
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load and register everything in one go
 */
function load_shaltazar_post_module() {
    $module_path = get_stylesheet_directory() . '/inc/modules/shaltazar-post/';
    
    // Check if module directory exists
    if (!is_dir($module_path)) {
        return;
    }
    
    // Load module files in order
    $files = array(
        'utilities.php',
        'admin.php',
        'frontend.php'
    );
    
    foreach ($files as $file) {
        $file_path = $module_path . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    // Register the post type DIRECTLY here instead of in a separate file
    register_shaltazar_post_type();
}

/**
 * Register Shaltazar Custom Post Type - MOVED HERE
 */
function register_shaltazar_post_type() {
    $labels = array(
        'name'                  => 'Shaltazar Posts',
        'singular_name'         => 'Shaltazar Post',
        'menu_name'             => 'Shaltazar Posts',
        'name_admin_bar'        => 'Shaltazar Post',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Shaltazar Post',
        'new_item'              => 'New Shaltazar Post',
        'edit_item'             => 'Edit Shaltazar Post',
        'view_item'             => 'View Shaltazar Post',
        'all_items'             => 'All Shaltazar Posts',
        'search_items'          => 'Search Shaltazar Posts',
        'not_found'             => 'No Shaltazar posts found.',
        'not_found_in_trash'    => 'No Shaltazar posts found in Trash.',
    );

    $args = array(
        'labels'                => $labels,
        'public'                => true,
        'publicly_queryable'    => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array('slug' => 'shaltazar'),
        'capability_type'       => 'post',
        'has_archive'           => true,
        'hierarchical'          => false,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-format-audio',
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'show_in_rest'          => true,
    );

    register_post_type('shaltazar_post', $args);
}

// Hook everything to init at priority 10 (nice and late)
add_action('init', 'load_shaltazar_post_module', 10);
