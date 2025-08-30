<?php
/**
 * Google Docs Content Fetcher
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
    
    // Sanitize and clean the content
    $cleaned_content = content_automation_sanitize_content($content);
    
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
    
    // Parse HTML and extract content
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
 * Extract readable content from Google Docs HTML
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
    
    // Extract text content while preserving some structure
    $content = extract_text_with_structure($body);
    
    // Clean up the content
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    $content = preg_replace('/\s+/', ' ', $content); // Normalize spaces
    $content = preg_replace('/\n\s*\n/', "\n\n", $content); // Clean up line breaks
    
    return trim($content);
}

/**
 * Extract text content while preserving paragraph structure
 */
function extract_text_with_structure($node) {
    $content = '';
    
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $content .= $child->textContent;
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $tag_name = strtolower($child->tagName);
            
            // Add line breaks for block elements
            if (in_array($tag_name, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'br'])) {
                $content .= "\n" . extract_text_with_structure($child) . "\n";
            } elseif (in_array($tag_name, ['li'])) {
                $content .= "\n• " . extract_text_with_structure($child) . "\n";
            } else {
                $content .= extract_text_with_structure($child);
            }
        }
    }
    
    return $content;
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
 * IMPORTANT: Instructions for making Google Docs accessible
 * 
 * For the content fetching to work, users need to:
 * 
 * 1. PUBLISH TO WEB: Go to File > Publish to web > Publish
 *    - This makes the export URLs work without authentication
 *    - The document remains private but exports are accessible
 * 
 * 2. SHARE PUBLICLY: Set sharing to "Anyone with the link can view"
 *    - Go to Share > Change to "Anyone with the link"
 *    - Set permission to "Viewer"
 * 
 * 3. ALTERNATIVE: Use Google Apps Script to push content
 *    - Create a script that sends content to WordPress via REST API
 *    - More reliable but requires more setup
 */

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
