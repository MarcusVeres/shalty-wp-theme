<?php
/**
 * Core theme setup - enqueue styles and basic functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue parent and child theme styles
 */
function hello_child_enqueue_styles() {
    // Parent theme CSS
    wp_enqueue_style(
        'hello-theme-parent',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme(get_template())->get('Version')
    );
    
    // Child theme CSS
    wp_enqueue_style(
        'hello-child-theme',
        get_stylesheet_directory_uri() . '/style.css',
        array('hello-theme-parent'),
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'hello_child_enqueue_styles');

/**
 * Add theme support
 */
function hello_child_theme_support() {
    // Add any theme support declarations here
    // add_theme_support('post-thumbnails');
    // add_theme_support('custom-logo');
}
add_action('after_setup_theme', 'hello_child_theme_support');

/**
 * Add body class for child theme identification
 */
function hello_child_body_classes($classes) {
    $classes[] = 'hello-child-theme';
    return $classes;
}
add_filter('body_class', 'hello_child_body_classes');
