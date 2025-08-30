<?php
/**
 * Module loader system for Hello Child Theme
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get available modules configuration
 */
function hello_child_get_modules() {
    return array(
        'scroll-offset' => array(
            'name' => 'Scroll Offset',
            'description' => 'Prevents fixed headers from covering content when users click anchor links',
            'files' => array('admin.php', 'frontend.php', 'utilities.php'),
            'enabled' => true, // Set to false to disable this module
            'requires' => array(), // Plugin dependencies
        ),
        // Future modules can be added here
        // 'woocommerce-extras' => array(
        //     'name' => 'WooCommerce Extras',
        //     'description' => 'Additional WooCommerce functionality',
        //     'files' => array('admin.php', 'frontend.php'),
        //     'enabled' => false,
        //     'requires' => array('woocommerce'),
        // ),
    );
}

/**
 * Check if required plugins are active
 */
function hello_child_check_module_requirements($module_config) {
    if (empty($module_config['requires'])) {
        return true;
    }

    foreach ($module_config['requires'] as $plugin) {
        switch ($plugin) {
            case 'woocommerce':
                if (!class_exists('WooCommerce')) return false;
                break;
            case 'elementor':
                if (!did_action('elementor/loaded')) return false;
                break;
            case 'acf':
                if (!class_exists('ACF')) return false;
                break;
            default:
                // For other plugins, check if function exists
                if (!function_exists($plugin . '_init') && !class_exists($plugin)) {
                    return false;
                }
        }
    }

    return true;
}

/**
 * Load a single module
 */
function hello_child_load_module($module_slug, $module_config) {
    // Skip if module is disabled
    if (!$module_config['enabled']) {
        return;
    }

    // Skip if requirements not met
    if (!hello_child_check_module_requirements($module_config)) {
        return;
    }

    $module_path = HELLO_CHILD_DIR . '/inc/modules/' . $module_slug . '/';

    // Load each file in the module
    foreach ($module_config['files'] as $file) {
        $file_path = $module_path . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

/**
 * Load all enabled modules
 */
function hello_child_load_modules() {
    $modules = hello_child_get_modules();
    
    foreach ($modules as $module_slug => $module_config) {
        hello_child_load_module($module_slug, $module_config);
    }
}

/**
 * Get module status for admin display
 */
function hello_child_get_module_status($module_slug) {
    $modules = hello_child_get_modules();
    
    if (!isset($modules[$module_slug])) {
        return 'not_found';
    }

    $module_config = $modules[$module_slug];

    if (!$module_config['enabled']) {
        return 'disabled';
    }

    if (!hello_child_check_module_requirements($module_config)) {
        return 'requirements_not_met';
    }

    return 'active';
}

/**
 * Utility function to check if a specific module is loaded
 */
function hello_child_is_module_loaded($module_slug) {
    return hello_child_get_module_status($module_slug) === 'active';
}

// -----------------------------------

/* 
 * INTEGRATE MODULES 
 */

require_once get_stylesheet_directory() . '/inc/modules/shaltazar-post/index.php';
