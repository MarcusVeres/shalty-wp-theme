<?php
/**
 * Theme Category Automation
 * 
 * Automatically creates and assigns categories based on the 'theme' ACF field
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main function to sync theme field with categories
 */
function sync_theme_to_categories($post_id, $force_update = false) {
    // Check if post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'shaltazar_post') {
        return array(
            'success' => false,
            'error' => 'Invalid post or wrong post type'
        );
    }
    
    // Get theme value
    $theme = get_field('theme', $post_id);
    if (empty($theme)) {
        return array(
            'success' => false,
            'error' => 'No theme value found'
        );
    }
    
    // Clean up theme name for category
    $theme_clean = trim($theme);
    
    // Check if we should skip (already has correct category and not forcing)
    if (!$force_update) {
        $current_categories = wp_get_post_categories($post_id, array('fields' => 'names'));
        if (in_array($theme_clean, $current_categories)) {
            return array(
                'success' => true, // Changed from false to true since it's already correct
                'message' => 'Post already has correct category: ' . $theme_clean
            );
        }
    }
    
    content_automation_log("Starting theme category sync for post ID: $post_id", 'info', $post_id);
    
    // Find or create category
    $category = get_or_create_theme_category($theme_clean);
    
    if (!$category) {
        $error = 'Failed to find or create category for theme: ' . $theme_clean;
        content_automation_log($error, 'error', $post_id);
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Assign category to post (append to existing categories)
    $result = wp_set_post_categories($post_id, array($category['term_id']), true);
    
    if (is_wp_error($result)) {
        $error = 'Failed to assign category: ' . $result->get_error_message();
        content_automation_log($error, 'error', $post_id);
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Store processing metadata
    update_post_meta($post_id, '_ca_theme_category_synced', current_time('mysql'));
    update_post_meta($post_id, '_ca_theme_category_id', $category['term_id']);
    
    $success_message = "Successfully synced theme '{$theme_clean}' to category (ID: {$category['term_id']})";
    content_automation_log($success_message, 'success', $post_id);
    
    return array(
        'success' => true,
        'message' => $success_message,
        'category' => $category,
        'theme' => $theme_clean
    );
}

/**
 * Find existing category or create new one
 */
function get_or_create_theme_category($theme_name) {
    // First, try to find existing category by name (case insensitive)
    $existing_category = get_term_by('name', $theme_name, 'category');
    
    if ($existing_category) {
        return array(
            'term_id' => $existing_category->term_id,
            'name' => $existing_category->name,
            'slug' => $existing_category->slug,
            'created' => false
        );
    }
    
    // Category doesn't exist, create it
    $new_category = wp_insert_category(array(
        'cat_name' => $theme_name,
        'category_description' => 'Posts with theme: ' . $theme_name,
        'category_nicename' => sanitize_title($theme_name)
    ));
    
    if (is_wp_error($new_category)) {
        content_automation_log('Failed to create category: ' . $new_category->get_error_message(), 'error');
        return false;
    }
    
    $category = get_category($new_category);
    
    content_automation_log("Created new category: {$category->name} (ID: {$category->term_id})", 'info');
    
    return array(
        'term_id' => $category->term_id,
        'name' => $category->name,
        'slug' => $category->slug,
        'created' => true
    );
}

/**
 * Get category sync status for a post
 */
function get_theme_category_status($post_id) {
    $theme = get_field('theme', $post_id);
    $current_categories = wp_get_post_categories($post_id, array('fields' => 'names'));
    $last_synced = get_post_meta($post_id, '_ca_theme_category_synced', true);
    
    $status = array(
        'has_theme' => !empty($theme),
        'theme_value' => $theme,
        'has_categories' => !empty($current_categories),
        'current_categories' => $current_categories,
        'theme_matches_category' => !empty($theme) && in_array(trim($theme), $current_categories),
        'last_synced' => $last_synced,
        'needs_sync' => !empty($theme) && !in_array(trim($theme), $current_categories)
    );
    
    return $status;
}

/**
 * Add theme category sync to individual post meta box
 */
function add_theme_category_to_meta_box($post) {
    if ($post->post_type !== 'shaltazar_post') {
        return;
    }
    
    $status = get_theme_category_status($post->ID);
    
    echo '<hr>';
    echo '<h4>Theme Category Sync</h4>';
    
    if ($status['has_theme']) {
        echo '<p><strong>Theme:</strong> ' . esc_html($status['theme_value']) . '</p>';
        echo '<p><strong>Current Categories:</strong> ';
        
        if ($status['has_categories']) {
            echo esc_html(implode(', ', $status['current_categories']));
        } else {
            echo '<em>None</em>';
        }
        echo '</p>';
        
        if ($status['theme_matches_category']) {
            echo '<p style="color: green;">✅ Theme category is assigned</p>';
        } else {
            echo '<p style="color: orange;">⚠️ Theme category needs sync</p>';
        }
        
        echo '<button type="button" class="button ca-sync-category" ';
        echo 'data-action="sync_category" data-post-id="' . $post->ID . '">';
        echo $status['needs_sync'] ? 'Sync Category' : 'Update Category';
        echo '</button>';
        
        echo '<label><input type="checkbox" class="ca-force-update" /> Force update</label>';
        
    } else {
        echo '<p><em>No theme value found. Add a theme to enable category sync.</em></p>';
    }
    
    if ($status['last_synced']) {
        echo '<p><small>Last synced: ' . date('M j, Y g:i A', strtotime($status['last_synced'])) . '</small></p>';
    }
}

// Hook into existing content automation meta box
add_action('content_automation_meta_box_after', 'add_theme_category_to_meta_box');

/**
 * Initialize category automation
 */
function init_category_automation() {
    // Make sure category support is enabled
    add_post_type_support('shaltazar_post', 'category');
    register_taxonomy_for_object_type('category', 'shaltazar_post');
}
add_action('init', 'init_category_automation', 11);

/**
 * Get all unique themes from posts
 */
function get_all_shaltazar_themes() {
    global $wpdb;
    
    $themes = $wpdb->get_col(
        "SELECT DISTINCT meta_value 
         FROM {$wpdb->postmeta} pm 
         INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
         WHERE pm.meta_key = 'theme' 
         AND p.post_type = 'shaltazar_post' 
         AND p.post_status = 'publish' 
         AND pm.meta_value != '' 
         ORDER BY pm.meta_value ASC"
    );
    
    return $themes ? array_map('trim', $themes) : array();
}

/**
 * Batch process theme categories for multiple posts
 */
function batch_sync_theme_categories($post_ids = null, $force_update = false) {
    if (is_null($post_ids)) {
        // Get all Shaltazar posts
        $post_ids = get_posts(array(
            'post_type' => 'shaltazar_post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
    }
    
    $results = array(
        'total' => count($post_ids),
        'success' => 0,
        'errors' => 0,
        'skipped' => 0,
        'categories_created' => 0,
        'details' => array()
    );
    
    foreach ($post_ids as $post_id) {
        $result = sync_theme_to_categories($post_id, $force_update);
        
        $results['details'][$post_id] = $result;
        
        if ($result['success']) {
            $results['success']++;
            if (isset($result['category']) && $result['category']['created']) {
                $results['categories_created']++;
            }
        } else {
            if (strpos($result['error'], 'already has correct category') !== false) {
                $results['skipped']++;
            } else {
                $results['errors']++;
            }
        }
    }
    
    return $results;
}

/**
 * Clean up unused theme categories
 */
function cleanup_unused_theme_categories() {
    // Get all categories that were created for themes
    $all_categories = get_categories(array(
        'hide_empty' => false
    ));
    
    $themes_in_use = get_all_shaltazar_themes();
    $deleted_count = 0;
    
    foreach ($all_categories as $category) {
        // Check if this category corresponds to a theme that's no longer in use
        if (!in_array($category->name, $themes_in_use)) {
            // Only delete if category description indicates it was created for themes
            if (strpos($category->description, 'Posts with theme:') === 0) {
                // Check if category is empty
                if ($category->count == 0) {
                    if (wp_delete_category($category->term_id)) {
                        $deleted_count++;
                        content_automation_log("Deleted unused theme category: {$category->name}", 'info');
                    }
                }
            }
        }
    }
    
    return $deleted_count;
}
