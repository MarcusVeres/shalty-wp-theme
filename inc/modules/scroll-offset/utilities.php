<?php
/**
 * Scroll Offset Module - Utilities (Shortcodes, Helpers, Admin Interface)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to create an anchor point with proper offset
 * Usage: [hello_anchor id="my-section" class="my-class"]
 */
function hello_child_scroll_anchor_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'class' => '',
        'style' => '', // Additional inline styles
    ), $atts);

    if (empty($atts['id'])) {
        return '<!-- Hello Child Anchor: ID is required -->';
    }

    $classes = array('hc-anchor-point');
    if (!empty($atts['class'])) {
        $classes[] = $atts['class'];
    }

    $style_attr = '';
    if (!empty($atts['style'])) {
        $style_attr = ' style="' . esc_attr($atts['style']) . '"';
    }

    return '<div id="' . esc_attr($atts['id']) . '" class="' . esc_attr(implode(' ', $classes)) . '"' . $style_attr . '></div>';
}
add_shortcode('hello_anchor', 'hello_child_scroll_anchor_shortcode');

/**
 * Function to automatically add anchor offsets to headings
 * Call this function to automatically add the offset class to all headings with IDs
 */
function hello_child_scroll_add_heading_offsets($content) {
    if (!hello_child_scroll_is_enabled()) {
        return $content;
    }

    // Add the anchor offset class to headings that have IDs
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*)(class="[^"]*")/', '$1$2', $content);
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*class="[^"]*)"/', '$1 hc-anchor-offset"', $content);
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*)(?!.*class=)([^>]*>)/', '$1 class="hc-anchor-offset"$2', $content);

    return $content;
}
// Uncomment the next line if you want automatic heading offset (be careful with this on complex sites)
// add_filter('the_content', 'hello_child_scroll_add_heading_offsets');

/**
 * Add admin notice to point users to the settings page
 */
function hello_child_scroll_admin_notice() {
    // Only show on dashboard and key admin pages
    $screen = get_current_screen();
    if (!in_array($screen->id, ['dashboard', 'themes', 'plugins', 'edit-page', 'edit-post'])) {
        return;
    }

    // Don't show if already on our settings page
    if ($screen->id === 'settings_page_hello-child-scroll-offset') {
        return;
    }

    // Only show if scroll offset is enabled
    if (!hello_child_scroll_is_enabled()) {
        return;
    }

    // Check if user has dismissed this notice
    if (get_user_meta(get_current_user_id(), 'hello_child_scroll_notice_dismissed', true)) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible" data-notice="hello-child-scroll-offset">
        <p><strong>Scroll Offset Active!</strong> Your fixed header no longer covers content when users click anchor links. <a href="' . admin_url('options-general.php?page=hello-child-scroll-offset') . '">Adjust settings</a> or <a href="#" onclick="this.closest(\'.notice\').style.display=\'none\'; fetch(ajaxurl, {method:\'POST\', headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'}, body:\'action=hello_child_dismiss_scroll_notice&nonce=' . wp_create_nonce('hello_child_dismiss_scroll_notice') . '\'})">dismiss this notice</a>.</p>
    </div>';
}
add_action('admin_notices', 'hello_child_scroll_admin_notice');

/**
 * Handle dismissing the admin notice
 */
function hello_child_scroll_dismiss_notice() {
    if (!wp_verify_nonce($_POST['nonce'], 'hello_child_dismiss_scroll_notice')) {
        wp_die('Security check failed');
    }
    
    update_user_meta(get_current_user_id(), 'hello_child_scroll_notice_dismissed', true);
    wp_die(); // This is required to terminate immediately and return a proper response
}
add_action('wp_ajax_hello_child_dismiss_scroll_notice', 'hello_child_scroll_dismiss_notice');

/**
 * Ajax handler to test scroll offset (for debugging)
 */
function hello_child_scroll_test_offset() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    wp_send_json_success(array(
        'desktop_offset' => hello_child_scroll_get_desktop_offset(),
        'mobile_offset' => hello_child_scroll_get_mobile_offset(),
        'breakpoint' => hello_child_scroll_get_breakpoint(),
        'enabled' => hello_child_scroll_is_enabled(),
        'message' => 'Scroll offset settings retrieved successfully'
    ));
}
add_action('wp_ajax_hello_child_test_scroll_offset', 'hello_child_scroll_test_offset');

/**
 * Add settings link to admin bar
 */
function hello_child_scroll_add_admin_bar_link($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show if scroll offset is enabled
    if (!hello_child_scroll_is_enabled()) {
        return;
    }

    $args = array(
        'id'    => 'hello-child-scroll-settings',
        'title' => 'Scroll Settings',
        'href'  => admin_url('options-general.php?page=hello-child-scroll-offset'),
        'meta'  => array(
            'title' => 'Configure scroll offset settings'
        ),
    );
    $wp_admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'hello_child_scroll_add_admin_bar_link', 999);

/**
 * Add settings link on plugins page (if this were a plugin)
 */
function hello_child_scroll_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=hello-child-scroll-offset') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
// Uncomment if you want a quick settings link from elsewhere
// add_filter('theme_action_links', 'hello_child_scroll_add_settings_link');

/**
 * Helper functions for content filtering
 */

/**
 * Filter to add scroll offset classes to specific elements
 */
function hello_child_scroll_filter_content($content) {
    if (!hello_child_scroll_is_enabled()) {
        return $content;
    }

    // Add offset class to elements with specific patterns
    // This is safer than the automatic heading function above
    $patterns = array(
        // Add class to divs with id starting with "section-"
        '/<div([^>]*id="section-[^"]*"[^>]*)>/' => '<div$1 class="hc-anchor-offset">',
    );

    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}
// Uncomment to enable content filtering
// add_filter('the_content', 'hello_child_scroll_filter_content');

/**
 * Utility function to generate anchor links with proper offset
 */
function hello_child_scroll_get_anchor_link($target_id, $link_text, $classes = '') {
    if (!hello_child_scroll_is_enabled()) {
        return '<a href="#' . esc_attr($target_id) . '">' . esc_html($link_text) . '</a>';
    }

    $class_attr = !empty($classes) ? ' class="' . esc_attr($classes) . '"' : '';
    
    return '<a href="#' . esc_attr($target_id) . '"' . $class_attr . '>' . esc_html($link_text) . '</a>';
}

/**
 * Widget to display scroll offset status (optional)
 */
class Hello_Child_Scroll_Status_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'hello_child_scroll_status',
            'Scroll Offset Status',
            array('description' => 'Display scroll offset status information')
        );
    }

    public function widget($args, $instance) {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        echo '<p><strong>Scroll Offset:</strong> ' . (hello_child_scroll_is_enabled() ? 'Active' : 'Disabled') . '</p>';
        
        if (hello_child_scroll_is_enabled()) {
            echo '<p>Desktop: ' . hello_child_scroll_get_desktop_offset() . 'px<br>';
            echo 'Mobile: ' . hello_child_scroll_get_mobile_offset() . 'px</p>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Scroll Status';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Title:</label> 
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php 
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * Register the widget (uncomment to enable)
 */
// add_action('widgets_init', function() {
//     register_widget('Hello_Child_Scroll_Status_Widget');
// });
