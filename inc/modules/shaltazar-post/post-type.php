<?php
/**
 * Shaltazar Post Type Registration
 * 
 * @package ChildTheme
 * @subpackage Modules/ShaltazarPost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Shaltazar Custom Post Type
 */
function register_shaltazar_post_type() {
    $labels = array(
        'name'                  => _x('Shaltazar Posts', 'Post Type General Name', 'textdomain'),
        'singular_name'         => _x('Shaltazar Post', 'Post Type Singular Name', 'textdomain'),
        'menu_name'             => __('Shaltazar Posts', 'textdomain'),
        'name_admin_bar'        => __('Shaltazar Post', 'textdomain'),
        'archives'              => __('Shaltazar Archives', 'textdomain'),
        'attributes'            => __('Shaltazar Attributes', 'textdomain'),
        'parent_item_colon'     => __('Parent Shaltazar:', 'textdomain'),
        'all_items'             => __('All Shaltazar Posts', 'textdomain'),
        'add_new_item'          => __('Add New Shaltazar Post', 'textdomain'),
        'add_new'               => __('Add New', 'textdomain'),
        'new_item'              => __('New Shaltazar Post', 'textdomain'),
        'edit_item'             => __('Edit Shaltazar Post', 'textdomain'),
        'update_item'           => __('Update Shaltazar Post', 'textdomain'),
        'view_item'             => __('View Shaltazar Post', 'textdomain'),
        'view_items'            => __('View Shaltazar Posts', 'textdomain'),
        'search_items'          => __('Search Shaltazar Posts', 'textdomain'),
        'not_found'             => __('Not found', 'textdomain'),
        'not_found_in_trash'    => __('Not found in Trash', 'textdomain'),
        'featured_image'        => __('Featured Image', 'textdomain'),
        'set_featured_image'    => __('Set featured image', 'textdomain'),
        'remove_featured_image' => __('Remove featured image', 'textdomain'),
        'use_featured_image'    => __('Use as featured image', 'textdomain'),
        'insert_into_item'      => __('Insert into Shaltazar post', 'textdomain'),
        'uploaded_to_this_item' => __('Uploaded to this Shaltazar post', 'textdomain'),
        'items_list'            => __('Shaltazar posts list', 'textdomain'),
        'items_list_navigation' => __('Shaltazar posts list navigation', 'textdomain'),
        'filter_items_list'     => __('Filter Shaltazar posts list', 'textdomain'),
    );

    $args = array(
        'label'                 => __('Shaltazar Post', 'textdomain'),
        'description'           => __('Channeled content and teachings', 'textdomain'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'taxonomies'            => array(), // Add taxonomies if needed later
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-format-audio', // Appropriate icon for audio content
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'rewrite'               => array(
            'slug'              => 'shaltazar',
            'with_front'        => false,
            'pages'             => true,
            'feeds'             => true,
        ),
        'capability_type'       => 'post',
        'show_in_rest'          => true, // Enable Gutenberg editor
        'rest_base'             => 'shaltazar-posts',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    );

    register_post_type('shaltazar_post', $args);
}

// Hook into the 'init' action
add_action('init', 'register_shaltazar_post_type', 0);

/**
 * Flush rewrite rules on activation
 */
function shaltazar_post_flush_rewrite_rules() {
    register_shaltazar_post_type();
    flush_rewrite_rules();
}

// Hook for theme activation (you might want to call this manually once)
// register_activation_hook(__FILE__, 'shaltazar_post_flush_rewrite_rules');
