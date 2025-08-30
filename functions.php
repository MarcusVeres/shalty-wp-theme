<?php
/**
 * Hello Child Theme Starter - Main Functions File
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('HELLO_CHILD_DIR', get_stylesheet_directory());
define('HELLO_CHILD_URL', get_stylesheet_directory_uri());

/**
 * Core theme setup - always loaded
 */
require_once HELLO_CHILD_DIR . '/inc/core/theme-setup.php';
require_once HELLO_CHILD_DIR . '/inc/core/loader.php';

/**
 * Initialize modules
 */
hello_child_load_modules();
