<?php
/**
 * Content Automation Admin Interface
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu for content automation
 */
function content_automation_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=shaltazar_post',
        'Content Automation',
        'Content Automation',
        'manage_options',
        'content-automation',
        'content_automation_admin_page'
    );
}

/**
 * Add meta boxes to individual Shaltazar posts
 */
function content_automation_add_meta_boxes() {
    add_meta_box(
        'content_automation_actions',
        'Content Automation',
        'content_automation_meta_box_callback',
        'shaltazar_post',
        'side',
        'high'
    );
}

/**
 * Meta box callback for individual post actions
 */
function content_automation_meta_box_callback($post) {
    wp_nonce_field('content_automation_meta_box', 'content_automation_meta_box_nonce');
    
    $status = get_content_automation_status($post->ID);
    $requirements = content_automation_requirements_met();
    
    if (!empty($requirements)) {
        echo '<div class="notice notice-error"><p><strong>Requirements not met:</strong><br>';
        foreach ($requirements as $req) {
            echo '• ' . esc_html($req) . '<br>';
        }
        echo '</p></div>';
        return;
    }
    
    echo '<div id="ca-individual-actions" data-post-id="' . $post->ID . '">';
    
    // Current Status
    echo '<div class="ca-status-section">';
    echo '<h4>Current Status</h4>';
    echo '<ul>';
    echo '<li>Content: ' . ($status['has_content'] ? '✅ Present' : '❌ Missing') . '</li>';
    echo '<li>Featured Image: ' . ($status['has_featured_image'] ? '✅ Present' : '❌ Missing') . '</li>';
    echo '<li>Google Docs ID: ' . ($status['has_google_docs_id'] ? '✅ Present' : '❌ Missing') . '</li>';
    echo '<li>YouTube Link: ' . ($status['has_youtube_link'] ? '✅ Present' : '❌ Missing') . '</li>';
    echo '</ul>';
    echo '</div>';
    
    // Action Buttons
    echo '<div class="ca-action-buttons">';
    echo '<h4>Actions</h4>';
    
    // Google Docs Content
    if ($status['has_google_docs_id']) {
        $docs_id = get_field('google_docs_id', $post->ID);
        echo '<p><strong>Google Docs ID:</strong> ' . esc_html($docs_id) . '</p>';
        
        echo '<button type="button" class="button ca-fetch-content" ';
        echo 'data-action="fetch_content" data-post-id="' . $post->ID . '">';
        echo $status['has_content'] ? 'Update Content' : 'Fetch Content';
        echo '</button>';
        
        echo '<label><input type="checkbox" class="ca-force-update" /> Force update</label>';
    } else {
        echo '<p><em>No Google Docs ID found. Add one to enable content fetching.</em></p>';
    }
    
    echo '<hr>';
    
    // YouTube Thumbnail
    if ($status['has_youtube_link']) {
        $youtube_url = get_field('youtube_link', $post->ID) ?: get_field('youtube_link_old', $post->ID);
        $video_id = extract_youtube_video_id($youtube_url);
        
        echo '<p><strong>YouTube Video ID:</strong> ' . esc_html($video_id) . '</p>';
        
        echo '<button type="button" class="button ca-process-thumbnail" ';
        echo 'data-action="process_thumbnail" data-post-id="' . $post->ID . '">';
        echo $status['has_featured_image'] ? 'Update Thumbnail' : 'Download Thumbnail';
        echo '</button>';
        
        echo '<label><input type="checkbox" class="ca-force-update" /> Force update</label>';
    } else {
        echo '<p><em>No YouTube URL found. Add one to enable thumbnail processing.</em></p>';
    }
    
    // ADD THIS HOOK - This was missing!
    do_action('content_automation_meta_box_after', $post);
    
    echo '<hr>';
    
    // Process All Button
    if ($status['has_google_docs_id'] || $status['has_youtube_link']) {
        echo '<button type="button" class="button button-primary ca-process-all" ';
        echo 'data-action="process_all" data-post-id="' . $post->ID . '">';
        echo 'Process All (Content + Thumbnail + Category)';
        echo '</button>';
        
        echo '<label><input type="checkbox" class="ca-force-update" /> Force update</label>';
    }
    
    echo '</div>';
    
    // Processing Results
    echo '<div id="ca-processing-results-' . $post->ID . '" class="ca-processing-results"></div>';
    
    // Last Processed Info
    if ($status['last_processed']) {
        echo '<div class="ca-last-processed">';
        echo '<small><strong>Last processed:</strong> ' . date('M j, Y g:i A', strtotime($status['last_processed'])) . '</small>';
    }
    
    if ($status['processing_errors']) {
        echo '<div class="notice notice-error"><p><strong>Last Error:</strong><br>';
        echo esc_html($status['processing_errors']);
        echo '</p></div>';
    }
    
    echo '</div>';
}

/**
 * Main admin page for batch operations
 */
function content_automation_admin_page() {
    $requirements = content_automation_requirements_met();
    $stats = get_processing_statistics();
    $batch_status = get_batch_processing_status();
    $logs = array_slice(get_content_automation_logs(), -20); // Last 20 entries
    
    ?>
    <div class="wrap">
        <h1>Content Automation</h1>
        
        <?php if (!empty($requirements)): ?>
            <div class="notice notice-error">
                <h3>Requirements Not Met</h3>
                <ul>
                    <?php foreach ($requirements as $req): ?>
                        <li><?php echo esc_html($req); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php return; ?>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="card">
            <h2>Processing Statistics</h2>
            <div class="ca-stats-grid">
                <div class="ca-stat">
                    <div class="ca-stat-number"><?php echo $stats['total_posts']; ?></div>
                    <div class="ca-stat-label">Total Posts</div>
                </div>
                <div class="ca-stat">
                    <div class="ca-stat-number"><?php echo $stats['with_content']; ?></div>
                    <div class="ca-stat-label">With Content</div>
                </div>
                <div class="ca-stat">
                    <div class="ca-stat-number"><?php echo $stats['with_featured_image']; ?></div>
                    <div class="ca-stat-label">With Featured Image</div>
                </div>
                <div class="ca-stat">
                    <div class="ca-stat-number"><?php echo $stats['fully_processed']; ?></div>
                    <div class="ca-stat-label">Fully Processed</div>
                </div>
            </div>
        </div>
        
        <!-- Important Notice about Google Docs -->
        <div class="notice notice-warning">
            <h3>⚠️ Important: Google Docs Access Requirements</h3>
            <p><strong>Google Docs content fetching requires special setup.</strong> Regular edit URLs cannot be scraped due to authentication requirements.</p>
            
            <details>
                <summary>Click to see setup instructions</summary>
                <?php 
                $instructions = get_google_docs_setup_instructions();
                echo '<h4>' . $instructions['title'] . '</h4>';
                
                foreach ($instructions['methods'] as $method) {
                    echo '<div style="margin: 15px 0; padding: 10px; border: 1px solid #ddd;">';
                    echo '<h5>' . $method['name'] . '</h5>';
                    echo '<ol>';
                    foreach ($method['steps'] as $step) {
                        echo '<li>' . $step . '</li>';
                    }
                    echo '</ol>';
                    echo '<p><strong>Pros:</strong> ' . $method['pros'] . ' | <strong>Cons:</strong> ' . $method['cons'] . '</p>';
                    echo '</div>';
                }
                
                echo '<p><strong>' . $instructions['note'] . '</strong></p>';
                ?>
            </details>
        </div>
        
        <!-- Batch Processing -->
        <div class="card">
            <h2>Batch Processing</h2>
            
            <?php if ($batch_status && $batch_status['status'] === 'running'): ?>
                <div class="notice notice-info">
                    <p><strong>Batch processing is currently running...</strong></p>
                    <div id="ca-batch-progress">
                        <div class="ca-progress-bar">
                            <div class="ca-progress-fill" style="width: <?php echo ($batch_status['processed'] / $batch_status['total'] * 100); ?>%"></div>
                        </div>
                        <p>
                            Processed: <?php echo $batch_status['processed']; ?> / <?php echo $batch_status['total']; ?> 
                            (<?php echo $batch_status['success']; ?> success, <?php echo $batch_status['errors']; ?> errors)
                        </p>
                    </div>
                    <button type="button" class="button" id="ca-stop-batch">Stop Processing</button>
                </div>
            <?php else: ?>
                <div id="ca-batch-controls">
                    <h3>Start Batch Processing</h3>
                    <p>Process all Shaltazar posts that need content or thumbnails.</p>
                    
                    <div class="ca-batch-options">
                        <label>
                            <input type="checkbox" id="ca-batch-content" checked />
                            Fetch Google Docs content for posts missing content
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" id="ca-batch-thumbnails" checked />
                            Download YouTube thumbnails for posts missing featured images
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" id="ca-batch-categories" checked />
                            Sync theme categories for posts with theme values
                        </label>
                        <br />
                        <label>
                            <input type="checkbox" id="ca-batch-force" />
                            Force update (overwrite existing content/images/categories)
                        </label>
                    </div>
                    
                    <button type="button" class="button button-primary" id="ca-start-batch">
                        Start Batch Processing
                    </button>
                    
                    <p><small>Processing will run at 1 post per second to avoid rate limiting.</small></p>
                </div>
                
                <div id="ca-batch-progress" style="display: none;">
                    <div class="ca-progress-bar">
                        <div class="ca-progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="ca-progress-text">Preparing...</p>
                    <button type="button" class="button" id="ca-stop-batch">Stop Processing</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Mass Delete Section -->
        <div class="card ca-danger-zone">
            <h2 style="color: #d63638;">⚠️ Mass Delete All Posts</h2>           
            <?php 
            $mass_delete_status = get_transient('content_automation_mass_delete_active');
            if ($mass_delete_status): ?>
                <div class="notice notice-info">
                    <p><strong>Mass deletion is currently running...</strong></p>
                    <div id="ca-mass-delete-progress">
                        <div class="ca-progress-bar">
                            <div class="ca-progress-fill" style="width: <?php echo ($mass_delete_status['processed'] / $mass_delete_status['total'] * 100); ?>%"></div>
                        </div>
                        <p id="ca-mass-delete-text">
                            Deleted: <?php echo $mass_delete_status['processed']; ?> / <?php echo $mass_delete_status['total']; ?> posts
                        </p>
                    </div>
                    <button type="button" class="button" id="ca-stop-mass-delete">Stop Deletion</button>
                </div>
            <?php else: ?>
                <div id="ca-mass-delete-controls">
                    <p>This will delete <strong><?php echo $stats['total_posts']; ?> posts</strong> and all their associated featured images.</p>
                    
                    <button type="button" class="button button-delete" id="ca-start-mass-delete" style="background: #d63638; border-color: #d63638; color: white;">
                        Delete All Shaltazar Posts
                    </button>
                </div>
                
                <div id="ca-mass-delete-progress" style="display: none;">
                    <div class="ca-progress-bar">
                        <div class="ca-progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="ca-mass-delete-text">Preparing deletion...</p>
                    <button type="button" class="button" id="ca-stop-mass-delete">Stop Deletion</button>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Processing Logs -->
        <div class="card">
            <h2>Recent Activity</h2>
            <div class="ca-logs-container">
                <?php if (empty($logs)): ?>
                    <p><em>No recent activity.</em></p>
                <?php else: ?>
                    <div class="ca-logs">
                        <?php foreach (array_reverse($logs) as $log): ?>
                            <div class="ca-log-entry ca-log-<?php echo esc_attr($log['type']); ?>">
                                <span class="ca-log-time"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></span>
                                <span class="ca-log-message"><?php echo esc_html($log['message']); ?></span>
                                <?php if ($log['post_id']): ?>
                                    <a href="<?php echo get_edit_post_link($log['post_id']); ?>" class="ca-log-post-link">
                                        Post <?php echo $log['post_id']; ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button" id="ca-clear-logs">Clear Logs</button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Testing Tools -->
        <div class="card">
            <h2>Testing Tools</h2>
            <div class="ca-testing-tools">
                <h3>Test Google Docs Access</h3>
                <p>Test if a Google Docs document is accessible for content fetching.</p>
                <input type="text" id="ca-test-docs-id" placeholder="Google Docs ID" style="width: 300px;" />
                <button type="button" class="button" id="ca-test-docs">Test Access</button>
                <div id="ca-test-docs-results"></div>
                
                <hr />
                
                <h3>Test YouTube Video</h3>
                <p>Test if a YouTube video's thumbnail is accessible.</p>
                <input type="text" id="ca-test-youtube-url" placeholder="YouTube URL" style="width: 300px;" />
                <button type="button" class="button" id="ca-test-youtube">Test Video</button>
                <div id="ca-test-youtube-results"></div>
            </div>
        </div>
    </div>
    
    <style>
        .ca-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .ca-stat {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .ca-stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        
        .ca-stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .ca-progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .ca-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005a87);
            transition: width 0.3s ease;
        }
        
        .ca-logs {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            background: #f9f9f9;
            padding: 10px;
        }
        
        .ca-log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 12px;
        }
        
        .ca-log-time {
            color: #666;
            margin-right: 10px;
        }
        
        .ca-log-success .ca-log-message {
            color: #008a00;
        }
        
        .ca-log-error .ca-log-message {
            color: #d63638;
        }
        
        .ca-log-post-link {
            margin-left: 10px;
            font-size: 11px;
        }
        
        .ca-batch-options {
            margin: 15px 0;
        }
        
        .ca-batch-options label {
            display: block;
            margin: 8px 0;
        }
        
        .ca-processing-results {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
            display: none;
        }
        
        .ca-processing-results.show {
            display: block;
        }
        
        .ca-processing-results.success {
            border-left-color: #008a00;
            background: #f0f8f0;
        }
        
        .ca-processing-results.error {
            border-left-color: #d63638;
            background: #f8f0f0;
        }
        
        .ca-testing-tools h3 {
            margin-top: 20px;
        }
        
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        
        .card h2 {
            margin-top: 0;
        }
        
        .ca-danger-zone {
            border-left: 4px solid #d63638;
        }
        
        .ca-danger-zone h2 {
            border-bottom: 1px solid #d63638;
            padding-bottom: 10px;
        }
    </style>
    <?php
}

/**
 * Enqueue admin scripts and styles
 */
function content_automation_enqueue_scripts($hook_suffix) {
    // Only enqueue on our admin pages
    $target_pages = array(
        'shaltazar_post_page_content-automation',
        'post.php'
    );
    
    if (!in_array($hook_suffix, $target_pages)) {
        return;
    }
    
    // Check if we're editing a Shaltazar post
    if ($hook_suffix === 'post.php') {
        global $post;
        if (!$post || $post->post_type !== 'shaltazar_post') {
            return;
        }
    }
    
    wp_enqueue_script(
        'content-automation-admin',
        get_stylesheet_directory_uri() . '/inc/modules/content-automation/admin.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    wp_localize_script('content-automation-admin', 'caAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('content_automation_nonce'),
        'messages' => array(
            'confirm_batch' => 'This will process multiple posts. Continue?',
            'confirm_force' => 'This will overwrite existing content. Are you sure?',
            'confirm_mass_delete' => 'This will permanently delete ALL Shaltazar posts and their images. This cannot be undone. Are you sure?',
            'processing' => 'Processing...',
            'success' => 'Success!',
            'error' => 'Error occurred',
            'stopped' => 'Processing stopped'
        )
    ));
}
