<?php
/**
 * Scroll Offset Module - Admin Interface
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu page for scroll offset settings
 */
function hello_child_scroll_add_settings_menu() {
    add_options_page(
        'Scroll Offset Settings',                    // Page title
        'Scroll Offset',                            // Menu title
        'manage_options',                           // Capability
        'hello-child-scroll-offset',                // Menu slug
        'hello_child_scroll_settings_page',         // Callback function
        30                                          // Position
    );
}
add_action('admin_menu', 'hello_child_scroll_add_settings_menu');

/**
 * Register settings for scroll offset
 */
function hello_child_scroll_register_settings() {
    // Register settings group
    register_setting('hello_child_scroll_settings', 'hello_child_scroll_offset_desktop', array(
        'type' => 'integer',
        'default' => 100,
        'sanitize_callback' => 'hello_child_scroll_sanitize_offset_value'
    ));
    
    register_setting('hello_child_scroll_settings', 'hello_child_scroll_offset_mobile', array(
        'type' => 'integer',
        'default' => 80,
        'sanitize_callback' => 'hello_child_scroll_sanitize_offset_value'
    ));
    
    register_setting('hello_child_scroll_settings', 'hello_child_scroll_offset_breakpoint', array(
        'type' => 'integer',
        'default' => 768,
        'sanitize_callback' => 'hello_child_scroll_sanitize_breakpoint_value'
    ));
    
    register_setting('hello_child_scroll_settings', 'hello_child_scroll_offset_enable', array(
        'type' => 'boolean',
        'default' => true
    ));

    // Add settings section
    add_settings_section(
        'hello_child_scroll_section',
        'Scroll Offset Configuration',
        'hello_child_scroll_section_callback',
        'hello-child-scroll-offset'
    );

    // Add settings fields
    add_settings_field(
        'hello_child_scroll_offset_enable',
        'Enable Scroll Offset',
        'hello_child_scroll_enable_field_callback',
        'hello-child-scroll-offset',
        'hello_child_scroll_section'
    );

    add_settings_field(
        'hello_child_scroll_offset_desktop',
        'Desktop Scroll Offset',
        'hello_child_scroll_desktop_field_callback',
        'hello-child-scroll-offset',
        'hello_child_scroll_section'
    );

    add_settings_field(
        'hello_child_scroll_offset_mobile',
        'Mobile Scroll Offset',
        'hello_child_scroll_mobile_field_callback',
        'hello-child-scroll-offset',
        'hello_child_scroll_section'
    );

    add_settings_field(
        'hello_child_scroll_offset_breakpoint',
        'Mobile Breakpoint',
        'hello_child_scroll_breakpoint_field_callback',
        'hello-child-scroll-offset',
        'hello_child_scroll_section'
    );
}
add_action('admin_init', 'hello_child_scroll_register_settings');

/**
 * Sanitize offset values (0-500px)
 */
function hello_child_scroll_sanitize_offset_value($value) {
    $value = intval($value);
    return max(0, min(500, $value));
}

/**
 * Sanitize breakpoint values (320-1200px)
 */
function hello_child_scroll_sanitize_breakpoint_value($value) {
    $value = intval($value);
    return max(320, min(1200, $value));
}

/**
 * Settings section callback
 */
function hello_child_scroll_section_callback() {
    echo '<p>Configure scroll offset settings to prevent your fixed header from covering content when users click on anchor links.</p>';
}

/**
 * Enable field callback
 */
function hello_child_scroll_enable_field_callback() {
    $value = get_option('hello_child_scroll_offset_enable', true);
    echo '<label>';
    echo '<input type="checkbox" name="hello_child_scroll_offset_enable" value="1" ' . checked(1, $value, false) . ' />';
    echo ' Enable scroll offset functionality';
    echo '</label>';
    echo '<p class="description">Enable or disable the scroll offset functionality globally.</p>';
}

/**
 * Desktop offset field callback
 */
function hello_child_scroll_desktop_field_callback() {
    $value = get_option('hello_child_scroll_offset_desktop', 100);
    echo '<input type="number" name="hello_child_scroll_offset_desktop" value="' . esc_attr($value) . '" min="0" max="500" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Height in pixels to offset when scrolling to anchors on desktop (default: 100px).</p>';
}

/**
 * Mobile offset field callback
 */
function hello_child_scroll_mobile_field_callback() {
    $value = get_option('hello_child_scroll_offset_mobile', 80);
    echo '<input type="number" name="hello_child_scroll_offset_mobile" value="' . esc_attr($value) . '" min="0" max="500" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Height in pixels to offset when scrolling to anchors on mobile (default: 80px).</p>';
}

/**
 * Breakpoint field callback
 */
function hello_child_scroll_breakpoint_field_callback() {
    $value = get_option('hello_child_scroll_offset_breakpoint', 768);
    echo '<input type="number" name="hello_child_scroll_offset_breakpoint" value="' . esc_attr($value) . '" min="320" max="1200" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Screen width below which mobile offset is used (default: 768px).</p>';
}

/**
 * Settings page content
 */
function hello_child_scroll_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Show success message if settings were saved
    if (isset($_GET['settings-updated'])) {
        add_settings_error('hello_child_messages', 'hello_child_message', 'Settings Saved', 'updated');
    }

    // Show error/update messages
    settings_errors('hello_child_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <h3>Scroll Offset Feature</h3>
            <p><strong>What this does:</strong> Prevents your fixed header from covering content when users click on anchor links (like menu items that jump to page sections).</p>
            
            <h4>Features:</h4>
            <ul style="margin-left: 20px;">
                <li>Responsive: Different settings for desktop and mobile</li>
                <li>Automatic: Works with all existing anchor links</li>
                <li>Compatible: Works with Elementor and other page builders</li>
                <li>Modern: Uses CSS scroll-padding-top for smooth performance</li>
                <li>Fallback: JavaScript backup for older browsers</li>
            </ul>

            <h4>How to test:</h4>
            <ol style="margin-left: 20px;">
                <li>Save your settings below</li>
                <li>Create anchor links on your pages (like <code>&lt;a href="#section1"&gt;Go to Section 1&lt;/a&gt;</code>)</li>
                <li>Add corresponding anchor targets (like <code>&lt;div id="section1"&gt;</code>)</li>
                <li>Click the links to see the offset in action!</li>
            </ol>
        </div>

        <form action="options.php" method="post">
            <?php
            settings_fields('hello_child_scroll_settings');
            do_settings_sections('hello-child-scroll-offset');
            submit_button('Save Settings');
            ?>
        </form>

        <div class="card" style="margin-top: 20px;">
            <h3>Advanced Usage</h3>
            <p>Need more control? Use these shortcodes and classes:</p>
            
            <h4>Shortcodes:</h4>
            <ul>
                <li><code>[hello_anchor id="my-section"]</code> - Creates an invisible anchor point</li>
            </ul>

            <h4>CSS Classes:</h4>
            <ul>
                <li><code>.hc-anchor-offset</code> - Add to any element to give it proper scroll offset</li>
                <li><code>.hc-scroll-offset-enabled</code> - Added to body when feature is active</li>
            </ul>

            <h4>CSS Variables Available:</h4>
            <ul>
                <li><code>--hc-scroll-offset-desktop</code> - Current desktop offset value</li>
                <li><code>--hc-scroll-offset-mobile</code> - Current mobile offset value</li>
                <li><code>--hc-scroll-offset-breakpoint</code> - Current breakpoint value</li>
            </ul>
        </div>

        <?php if (get_option('hello_child_scroll_offset_enable', true)): ?>
        <div class="card" style="margin-top: 20px; border-left: 4px solid #00a0d2;">
            <h3>Current Settings Active</h3>
            <p><strong>Desktop Offset:</strong> <?php echo esc_html(get_option('hello_child_scroll_offset_desktop', 100)); ?>px</p>
            <p><strong>Mobile Offset:</strong> <?php echo esc_html(get_option('hello_child_scroll_offset_mobile', 80)); ?>px</p>
            <p><strong>Mobile Breakpoint:</strong> <?php echo esc_html(get_option('hello_child_scroll_offset_breakpoint', 768)); ?>px</p>
            <p><em>Test by creating anchor links on your pages!</em></p>
        </div>
        <?php else: ?>
        <div class="card" style="margin-top: 20px; border-left: 4px solid #dc3232;">
            <h3>Scroll Offset Disabled</h3>
            <p>Enable the feature above to start using scroll offset functionality.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 20px;
        }
        .card h3 { margin-top: 0; }
        .card h4 { margin-bottom: 5px; }
        .card ul { margin-top: 5px; }
        .card code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        .notice h3 { margin-top: 0; }
        .notice h4 {
            margin-bottom: 8px;
            margin-top: 15px;
        }
    </style>
    <?php
}
