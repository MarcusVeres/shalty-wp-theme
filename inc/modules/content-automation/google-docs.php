<?php
/**
 * Google Docs Content Fetcher - CORRECTED VERSION
 * 
 * IMPORTANT: Direct scraping of Google Docs edit URLs requires authentication.
 * This implementation provides multiple approaches for accessing Google Docs content.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Google Docs content fetcher
 * 
 * @param int $post_id Post ID
 * @param bool $force_update Force update even if content exists
 * @return array Result with success/error information
 */
function fetch_google_docs_content($post_id, $force_update = false) {
    // Check if post exists and is correct type
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'shaltazar_post') {
        return array(
            'success' => false,
            'error' => 'Invalid post or wrong post type'
        );
    }
    
    // Get Google Docs ID
    $docs_id = get_field('google_docs_id', $post_id);
    if (empty($docs_id)) {
        return array(
            'success' => false,
            'error' => 'No Google Docs ID found'
        );
    }
    
    // Check if we should skip (already has content and not forcing)
    if (!$force_update && !empty(get_post_field('post_content', $post_id))) {
        return array(
            'success' => false,
            'error' => 'Post already has content (use force update to override)'
        );
    }
    
    content_automation_log("Starting Google Docs fetch for post ID: $post_id", 'info', $post_id);
    
    // Try different methods to get content
    $content = null;
    $method_used = null;
    $error_message = null;
    
    // Method 1: Try published/public HTML export
    $result = fetch_docs_as_html_export($docs_id);
    if ($result['success']) {
        $content = $result['content'];
        $method_used = 'html_export';
    } else {
        $error_message = $result['error'];
        
        // Method 2: Try plain text export (if available)
        $result = fetch_docs_as_text_export($docs_id);
        if ($result['success']) {
            $content = $result['content'];
            $method_used = 'text_export';
        } else {
            // Method 3: Try RSS feed approach (if document is publicly shared)
            $result = fetch_docs_via_rss($docs_id);
            if ($result['success']) {
                $content = $result['content'];
                $method_used = 'rss_feed';
            }
        }
    }
    
    if (empty($content)) {
        $final_error = "Could not fetch content using any method. Last error: $error_message";
        content_automation_log($final_error, 'error', $post_id);
        
        // Store error for display
        update_post_meta($post_id, '_ca_processing_errors', $final_error);
        
        return array(
            'success' => false,
            'error' => $final_error
        );
    }
    
    // Sanitize and clean the content - IMPROVED VERSION
    $cleaned_content = content_automation_sanitize_html_content($content, $method_used);
    
    if (empty($cleaned_content)) {
        $error = "Content was fetched but became empty after sanitization";
        content_automation_log($error, 'error', $post_id);
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Update post content
    $update_result = wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $cleaned_content
    ));
    
    if (is_wp_error($update_result)) {
        $error = 'Failed to update post content: ' . $update_result->get_error_message();
        content_automation_log($error, 'error', $post_id);
        return array(
            'success' => false,
            'error' => $error
        );
    }
    
    // Store processing metadata
    update_post_meta($post_id, '_ca_last_processed', current_time('mysql'));
    update_post_meta($post_id, '_ca_docs_method', $method_used);
    update_post_meta($post_id, '_ca_processing_errors', ''); // Clear previous errors
    
    $success_message = "Successfully fetched and updated content using method: $method_used";
    content_automation_log($success_message, 'success', $post_id);
    
    return array(
        'success' => true,
        'message' => $success_message,
        'method' => $method_used,
        'content_length' => strlen($cleaned_content)
    );
}

/**
 * Method 1: Fetch Google Docs as HTML export
 * Works if document is published to web or publicly viewable
 */
function fetch_docs_as_html_export($docs_id) {
    // Google Docs HTML export URL
    $url = "https://docs.google.com/document/d/{$docs_id}/export?format=html";
    
    $response = content_automation_http_request($url);
    
    if (!$response['success']) {
        return $response;
    }
    
    // Parse HTML and extract content - IMPROVED VERSION
    $html = $response['body'];
    
    // Remove Google's extra HTML wrapper and extract main content
    $content = extract_content_from_google_html($html);
    
    if (empty($content)) {
        return array(
            'success' => false,
            'error' => 'Could not extract content from HTML export (document may not be publicly accessible)'
        );
    }
    
    return array(
        'success' => true,
        'content' => $content
    );
}

/**
 * Method 2: Fetch Google Docs as plain text export
 */
function fetch_docs_as_text_export($docs_id) {
    // Google Docs plain text export URL
    $url = "https://docs.google.com/document/d/{$docs_id}/export?format=txt";
    
    $response = content_automation_http_request($url);
    
    if (!$response['success']) {
        return $response;
    }
    
    $content = trim($response['body']);
    
    if (empty($content) || strpos($content, 'Sorry, unable to open the file') !== false) {
        return array(
            'success' => false,
            'error' => 'Document not accessible via text export (may require authentication)'
        );
    }
    
    // Convert plain text to basic HTML with paragraphs
    $content = wpautop($content);
    
    return array(
        'success' => true,
        'content' => $content
    );
}

/**
 * Method 3: Try RSS feed approach (limited success)
 */
function fetch_docs_via_rss($docs_id) {
    // This method has limited success but worth trying
    $url = "https://docs.google.com/document/d/{$docs_id}/export?format=odt";
    
    return array(
        'success' => false,
        'error' => 'RSS method not yet implemented'
    );
}

/**
 * Extract readable content from Google Docs HTML - COMPLETELY REWRITTEN
 */
function extract_content_from_google_html($html) {
    if (empty($html)) {
        return '';
    }
    
    // Create DOMDocument to parse HTML
    $dom = new DOMDocument();
    
    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);
    
    // Load HTML with UTF-8 encoding
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    
    libxml_clear_errors();
    
    // Find the main content area - Google Docs usually puts content in body
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    
    // Extract HTML content while preserving structure - NEW APPROACH
    $content = extract_html_with_structure($body);
    
    // Clean up Google-specific elements and styles
    $content = clean_google_docs_html($content);
    
    return $content;
}

/**
 * NEW FUNCTION: Extract HTML content while preserving formatting
 */
function extract_html_with_structure($node) {
    $content = '';
    
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            // Get text content and escape it properly
            $text = trim($child->textContent);
            if (!empty($text)) {
                $content .= esc_html($text);
            }
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $tag_name = strtolower($child->tagName);
            
            // Handle different HTML elements
            switch ($tag_name) {
                case 'p':
                case 'div':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<p>' . $inner_content . '</p>';
                    }
                    break;
                    
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<' . $tag_name . '>' . $inner_content . '</' . $tag_name . '>';
                    }
                    break;
                    
                case 'strong':
                case 'b':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<strong>' . $inner_content . '</strong>';
                    }
                    break;
                    
                case 'em':
                case 'i':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<em>' . $inner_content . '</em>';
                    }
                    break;
                    
                case 'u':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<u>' . $inner_content . '</u>';
                    }
                    break;
                    
                case 'br':
                    $content .= '<br>';
                    break;
                    
                case 'ul':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<ul>' . $inner_content . '</ul>';
                    }
                    break;
                    
                case 'ol':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<ol>' . $inner_content . '</ol>';
                    }
                    break;
                    
                case 'li':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<li>' . $inner_content . '</li>';
                    }
                    break;
                    
                case 'a':
                    $href = $child->getAttribute('href');
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content))) && !empty($href)) {
                        $content .= '<a href="' . esc_url($href) . '">' . $inner_content . '</a>';
                    } else if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= $inner_content;
                    }
                    break;
                    
                case 'blockquote':
                    $inner_content = extract_html_with_structure($child);
                    if (!empty(trim(strip_tags($inner_content)))) {
                        $content .= '<blockquote>' . $inner_content . '</blockquote>';
                    }
                    break;
                    
                // Skip Google-specific elements and scripts
                case 'script':
                case 'style':
                case 'meta':
                case 'link':
                case 'title':
                case 'head':
                    break;
                    
                // For any other elements, just extract their content
                default:
                    $content .= extract_html_with_structure($child);
                    break;
            }
        }
    }
    
    return $content;
}

/**
 * NEW FUNCTION: Clean up Google Docs specific HTML artifacts
 */
function clean_google_docs_html($html) {
    if (empty($html)) {
        return '';
    }
    
    // Remove Google Docs specific CSS classes and styles
    $html = preg_replace('/\s+class="[^"]*"/', '', $html);
    $html = preg_replace('/\s+style="[^"]*"/', '', $html);
    $html = preg_replace('/\s+id="[^"]*"/', '', $html);
    
    // Clean up excessive whitespace but preserve intentional spacing
    $html = preg_replace('/>\s+</', '><', $html);
    $html = preg_replace('/\s{2,}/', ' ', $html);
    
    // Remove empty paragraphs and elements
    $html = preg_replace('/<p[^>]*>\s*<\/p>/', '', $html);
    $html = preg_replace('/<div[^>]*>\s*<\/div>/', '', $html);
    
    // Fix multiple consecutive line breaks
    $html = preg_replace('/(<br\s*\/?>\s*){3,}/', '<br><br>', $html);
    
    return trim($html);
}

/**
 * NEW FUNCTION: Sanitize HTML content (renamed to avoid conflicts)
 */
function content_automation_sanitize_html_content($content, $method = 'html_export') {
    if (empty($content)) {
        return '';
    }
    
    // If it's plain text method, convert to HTML first
    if ($method === 'text_export') {
        $content = wpautop($content);
    }
    
    // Allow specific HTML tags that are safe and useful for formatting
    $allowed_tags = array(
        'p' => array(),
        'br' => array(),
        'strong' => array(),
        'b' => array(),
        'em' => array(),
        'i' => array(),
        'u' => array(),
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'ul' => array(),
        'ol' => array(),
        'li' => array(),
        'blockquote' => array(),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'target' => array(),
            'rel' => array()
        )
    );
    
    // Sanitize HTML while preserving allowed formatting
    $content = wp_kses($content, $allowed_tags);
    
    // Clean up any weird unicode characters
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    
    // Remove excessive line breaks in HTML
    $content = preg_replace('/(<\/p>\s*){2,}/', '</p>', $content);
    
    return trim($content);
}

/**
 * Alternative: Process Google Docs ID to different formats
 * This helps users understand what URLs they need
 */
function get_google_docs_urls($docs_id) {
    $urls = array(
        'edit' => "https://docs.google.com/document/d/{$docs_id}/edit",
        'view' => "https://docs.google.com/document/d/{$docs_id}/edit#gid=0",
        'html_export' => "https://docs.google.com/document/d/{$docs_id}/export?format=html",
        'text_export' => "https://docs.google.com/document/d/{$docs_id}/export?format=txt",
        'pdf_export' => "https://docs.google.com/document/d/{$docs_id}/export?format=pdf",
    );
    
    return $urls;
}

/**
 * Test Google Docs accessibility
 */
function test_google_docs_access($docs_id) {
    $urls = get_google_docs_urls($docs_id);
    $results = array();
    
    foreach ($urls as $type => $url) {
        if (in_array($type, ['html_export', 'text_export'])) {
            $response = content_automation_http_request($url);
            $results[$type] = array(
                'url' => $url,
                'accessible' => $response['success'],
                'error' => $response['success'] ? null : $response['error']
            );
        } else {
            $results[$type] = array(
                'url' => $url,
                'accessible' => null,
                'note' => 'Requires manual testing'
            );
        }
    }
    
    return $results;
}

/**
 * Get instructions for making docs accessible
 */
function get_google_docs_setup_instructions() {
    return array(
        'title' => 'Making Google Docs Accessible for Content Fetching',
        'methods' => array(
            array(
                'name' => 'Method 1: Publish to Web (Recommended)',
                'steps' => array(
                    'Open your Google Doc',
                    'Go to File > Publish to web',
                    'Click "Publish" button',
                    'This makes export URLs work without authentication',
                    'Document stays private, only exports are accessible'
                ),
                'pros' => 'Easy setup, works reliably',
                'cons' => 'Export URLs are publicly accessible'
            ),
            array(
                'name' => 'Method 2: Share with Link',
                'steps' => array(
                    'Click Share button in your Google Doc',
                    'Change access to "Anyone with the link can view"',
                    'Make sure permission is set to "Viewer"',
                    'Copy the document ID from the URL'
                ),
                'pros' => 'More control over access',
                'cons' => 'May not work with all export formats'
            ),
            array(
                'name' => 'Method 3: Google Apps Script (Advanced)',
                'steps' => array(
                    'Create a Google Apps Script project',
                    'Write script to extract document content',
                    'Send content to WordPress via REST API',
                    'Set up triggers for automatic updates'
                ),
                'pros' => 'Most reliable, can handle complex formatting',
                'cons' => 'Requires programming knowledge'
            )
        ),
        'note' => 'For best results, use Method 1 (Publish to Web) as it provides the most reliable access to document content.'
    );
}
?>
