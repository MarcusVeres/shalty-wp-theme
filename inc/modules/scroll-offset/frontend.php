<?php
/**
 * Scroll Offset Module - Frontend Output (CSS & JavaScript)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add scroll offset CSS variables and styles
 */
function hello_child_scroll_add_styles() {
    // Only add if scroll offset is enabled
    if (!hello_child_scroll_is_enabled()) {
        return;
    }

    $desktop_offset = hello_child_scroll_get_desktop_offset();
    $mobile_offset = hello_child_scroll_get_mobile_offset();
    $breakpoint = hello_child_scroll_get_breakpoint();

    ?>
    <style id="hc-scroll-offset-styles">
        :root {
            --hc-scroll-offset-desktop: <?php echo $desktop_offset; ?>px;
            --hc-scroll-offset-mobile: <?php echo $mobile_offset; ?>px;
            --hc-scroll-offset-breakpoint: <?php echo $breakpoint; ?>px;
        }

        /* Modern browsers: use scroll-padding-top */
        html {
            scroll-padding-top: var(--hc-scroll-offset-desktop);
        }

        @media (max-width: <?php echo $breakpoint; ?>px) {
            html {
                scroll-padding-top: var(--hc-scroll-offset-mobile);
            }
        }

        /* Smooth scrolling for better UX */
        html {
            scroll-behavior: smooth;
        }

        /* Fallback for older browsers - add margin to targeted elements */
        .hc-anchor-offset::before {
            content: '';
            display: block;
            height: var(--hc-scroll-offset-desktop);
            margin-top: calc(var(--hc-scroll-offset-desktop) * -1);
            visibility: hidden;
            pointer-events: none;
        }

        @media (max-width: <?php echo $breakpoint; ?>px) {
            .hc-anchor-offset::before {
                height: var(--hc-scroll-offset-mobile);
                margin-top: calc(var(--hc-scroll-offset-mobile) * -1);
            }
        }

        /* Elementor anchor compatibility */
        .elementor-menu-anchor {
            margin-top: calc(var(--hc-scroll-offset-desktop) * -1);
            padding-top: var(--hc-scroll-offset-desktop);
        }
        
        @media (max-width: <?php echo $breakpoint; ?>px) {
            .elementor-menu-anchor {
                margin-top: calc(var(--hc-scroll-offset-mobile) * -1);
                padding-top: var(--hc-scroll-offset-mobile);
            }
        }

        /* Anchor point styling */
        .hc-anchor-point {
            display: block;
            position: relative;
            visibility: hidden;
            height: 0;
        }
    </style>
    <?php
}
add_action('wp_head', 'hello_child_scroll_add_styles');

/**
 * Add scroll offset JavaScript for enhanced functionality
 */
function hello_child_scroll_add_script() {
    // Only add if scroll offset is enabled
    if (!hello_child_scroll_is_enabled()) {
        return;
    }

    $desktop_offset = hello_child_scroll_get_desktop_offset();
    $mobile_offset = hello_child_scroll_get_mobile_offset();
    $breakpoint = hello_child_scroll_get_breakpoint();

    ?>
    <script id="hc-scroll-offset-script">
    (function() {
        'use strict';
        
        const scrollOffsets = {
            desktop: <?php echo $desktop_offset; ?>,
            mobile: <?php echo $mobile_offset; ?>,
            breakpoint: <?php echo $breakpoint; ?>
        };

        function getCurrentOffset() {
            return window.innerWidth <= scrollOffsets.breakpoint 
                ? scrollOffsets.mobile 
                : scrollOffsets.desktop;
        }

        // Enhanced anchor link handling
        function handleAnchorClick(e) {
            const link = e.target.closest('a[href^="#"]');
            if (!link) return;

            const href = link.getAttribute('href');
            if (href === '#' || href === '#top') return;

            const targetId = href.substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (!targetElement) return;

            e.preventDefault();

            const rect = targetElement.getBoundingClientRect();
            const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();

            // Use smooth scrolling
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });

            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, href);
            } else {
                // Fallback for older browsers
                location.hash = href;
            }
        }

        // Handle hash on page load
        function handleInitialHash() {
            const hash = window.location.hash;
            if (!hash) return;

            // Small delay to ensure page is fully loaded
            setTimeout(function() {
                const targetElement = document.querySelector(hash);
                if (targetElement) {
                    const rect = targetElement.getBoundingClientRect();
                    const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }, 100);
        }

        // Add click event listener
        document.addEventListener('click', handleAnchorClick);

        // Handle initial hash when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', handleInitialHash);
        } else {
            handleInitialHash();
        }

        // Handle browser back/forward with hash changes
        window.addEventListener('hashchange', function() {
            const hash = window.location.hash;
            if (hash) {
                const targetElement = document.querySelector(hash);
                if (targetElement) {
                    const rect = targetElement.getBoundingClientRect();
                    const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
        });

        // Expose utility functions globally for debugging
        window.helloChildScrollOffset = {
            getCurrentOffset: getCurrentOffset,
            scrollOffsets: scrollOffsets
        };

    })();
    </script>
    <?php
}
add_action('wp_footer', 'hello_child_scroll_add_script');

/**
 * Add body class when scroll offset is enabled
 */
function hello_child_scroll_body_class($classes) {
    if (hello_child_scroll_is_enabled()) {
        $classes[] = 'hc-scroll-offset-enabled';
    }
    return $classes;
}
add_filter('body_class', 'hello_child_scroll_body_class');

/**
 * Add debug styles for administrators
 */
function hello_child_scroll_debug_styles() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only add debug styles if enabled and debug parameter is present
    if (!hello_child_scroll_is_enabled() || !isset($_GET['hc_debug'])) {
        return;
    }

    echo '<style>
        /* Hello Child Debug Mode: Highlight anchor targets */
        [id] {
            position: relative;
        }
        [id]::before {
            content: "🎯 " attr(id);
            position: absolute;
            top: -25px;
            left: 0;
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            font-size: 11px;
            border-radius: 3px;
            z-index: 9999;
            font-family: monospace;
            opacity: 0.8;
        }
        
        /* Show scroll offset visualization */
        body::after {
            content: "Desktop: ' . hello_child_scroll_get_desktop_offset() . 'px | Mobile: ' . hello_child_scroll_get_mobile_offset() . 'px | Breakpoint: ' . hello_child_scroll_get_breakpoint() . 'px";
            position: fixed;
            top: 0;
            right: 0;
            background: #333;
            color: white;
            padding: 10px;
            font-size: 12px;
            z-index: 99999;
            font-family: monospace;
        }
    </style>';
}
add_action('wp_head', 'hello_child_scroll_debug_styles');

/**
 * Helper functions to get scroll offset values
 */
function hello_child_scroll_get_desktop_offset() {
    return intval(get_option('hello_child_scroll_offset_desktop', 100));
}

function hello_child_scroll_get_mobile_offset() {
    return intval(get_option('hello_child_scroll_offset_mobile', 80));
}

function hello_child_scroll_get_breakpoint() {
    return intval(get_option('hello_child_scroll_offset_breakpoint', 768));
}

function hello_child_scroll_is_enabled() {
    return (bool) get_option('hello_child_scroll_offset_enable', true);
}

/**
 * Get current scroll offset (server-side approximation)
 */
function hello_child_scroll_get_current_offset() {
    if (!hello_child_scroll_is_enabled()) {
        return 0;
    }

    // This is a server-side approximation - for exact offset, use JavaScript
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_mobile = wp_is_mobile() || (strpos($user_agent, 'Mobile') !== false);
    
    return $is_mobile ? hello_child_scroll_get_mobile_offset() : hello_child_scroll_get_desktop_offset();
}
