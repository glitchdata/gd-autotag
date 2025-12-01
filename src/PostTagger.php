<?php
namespace WpPlugin;

class PostTagger
{
    public function register(): void
    {
        // Add bulk action to posts list
        add_filter('bulk_actions-edit-post', [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_action'], 10, 3);
        
        // Add row action to individual posts
        add_filter('post_row_actions', [$this, 'add_row_action'], 10, 2);
        add_action('admin_action_wp_plugin_generate_single_tag', [$this, 'handle_single_tag_generation']);
        
        // Add admin notice
        add_action('admin_notices', [$this, 'bulk_action_admin_notice']);
        
        // Add meta box to individual posts
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        
        // Add AJAX handler for generating tags
        add_action('wp_ajax_wp_plugin_generate_tags', [$this, 'ajax_generate_tags']);
    }

    public function add_bulk_action($bulk_actions): array
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['auto_tag_enabled']) ? $options['auto_tag_enabled'] : false;
        
        if ($enabled) {
            $bulk_actions['wp_plugin_generate_tags'] = 'Generate Tags';
        }
        
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $action, $post_ids)
    {
        if ($action !== 'wp_plugin_generate_tags') {
            return $redirect_to;
        }

        $processed = 0;
        foreach ($post_ids as $post_id) {
            if ($this->generate_tags_for_post($post_id)) {
                $processed++;
            }
        }

        $redirect_to = add_query_arg('wp_plugin_tags_generated', $processed, $redirect_to);
        return $redirect_to;
    }

    public function add_row_action($actions, $post)
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['auto_tag_enabled']) ? $options['auto_tag_enabled'] : false;
        
        // Only add for published posts and when feature is enabled
        if ($enabled && $post->post_status === 'publish' && $post->post_type === 'post') {
            $url = wp_nonce_url(
                admin_url('admin.php?action=wp_plugin_generate_single_tag&post=' . $post->ID),
                'wp_plugin_generate_single_tag_' . $post->ID
            );
            
            $actions['wp_plugin_generate_tags'] = sprintf(
                '<a href="%s" title="%s">%s</a>',
                esc_url($url),
                esc_attr__('Generate tags for this post', 'wp-plugin'),
                esc_html__('Generate Tags', 'wp-plugin')
            );
        }
        
        return $actions;
    }

    public function handle_single_tag_generation(): void
    {
        // Check if post ID is provided
        if (!isset($_GET['post'])) {
            wp_die('No post specified.');
        }

        $post_id = intval($_GET['post']);

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'wp_plugin_generate_single_tag_' . $post_id)) {
            wp_die('Security check failed.');
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('You do not have permission to edit this post.');
        }

        // Generate tags
        $success = $this->generate_tags_for_post($post_id);

        // Redirect back to posts list with message
        $redirect_url = admin_url('edit.php?post_type=post');
        
        if ($success) {
            $redirect_url = add_query_arg('wp_plugin_single_tag_generated', '1', $redirect_url);
        } else {
            $redirect_url = add_query_arg('wp_plugin_single_tag_failed', '1', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    public function bulk_action_admin_notice(): void
    {
        if (!empty($_REQUEST['wp_plugin_tags_generated'])) {
            $count = intval($_REQUEST['wp_plugin_tags_generated']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>Generated tags for %d post(s).</p></div>',
                $count
            );
        }
        
        if (!empty($_REQUEST['wp_plugin_single_tag_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Tags generated successfully for the post.</p></div>';
        }
        
        if (!empty($_REQUEST['wp_plugin_single_tag_failed'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to generate tags for the post.</p></div>';
        }
    }

    public function add_meta_box(): void
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['auto_tag_enabled']) ? $options['auto_tag_enabled'] : false;
        
        // Only add meta box if feature is enabled
        if ($enabled) {
            add_meta_box(
                'wp_plugin_tag_generator',
                'Auto Tag Generator',
                [$this, 'render_meta_box'],
                'post',
                'side',
                'default'
            );
        }
    }

    public function render_meta_box($post): void
    {
        ?>
        <div class="wp-plugin-tag-generator">
            <p>Automatically generate tags based on post content.</p>
            <button type="button" class="button button-secondary wp-plugin-generate-tags-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                Generate Tags
            </button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <div class="wp-plugin-tags-result" style="margin-top: 10px;"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.wp-plugin-generate-tags-btn').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post-id');
                var spinner = btn.siblings('.spinner');
                var result = $('.wp-plugin-tags-result');
                
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wp_plugin_generate_tags',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('wp_plugin_generate_tags'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                            // Reload tags in the post editor
                            if (typeof wp !== 'undefined' && wp.data) {
                                location.reload();
                            }
                        } else {
                            result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;">✗ Error generating tags</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_generate_tags(): void
    {
        check_ajax_referer('wp_plugin_generate_tags', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }

        $success = $this->generate_tags_for_post($post_id);
        
        if ($success) {
            wp_send_json_success(['message' => 'Tags generated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to generate tags']);
        }
    }

    private function generate_tags_for_post(int $post_id): bool
    {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'post') {
            return false;
        }

        // Get plugin options
        $options = get_option('wp_plugin_options', []);
        
        // Extract potential tags from title and content
        $tags = $this->extract_tags($post);
        
        if (empty($tags)) {
            return false;
        }

        // Apply AI optimization if enabled
        $ai_enabled = isset($options['ai_optimization_enabled']) ? $options['ai_optimization_enabled'] : false;
        if ($ai_enabled && !empty($options['ai_api_key'])) {
            $ai_optimizer = new AITagOptimizer();
            $tags = $ai_optimizer->optimize_tags($tags, $post->post_content, $post->post_title);
        }

        // Set tags for the post
        wp_set_post_tags($post_id, $tags, false);
        
        return true;
    }

    private function extract_tags($post): array
    {
        $text = $post->post_title . ' ' . strip_tags($post->post_content);
        
        // Get plugin options for exclusion list
        $options = get_option('wp_plugin_options', []);
        $exclusion_list = isset($options['tag_exclusion_list']) ? $options['tag_exclusion_list'] : '';
        
        // Build common words list
        $common_words = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
            'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this',
            'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'
        ];
        
        // Add custom exclusion words
        if (!empty($exclusion_list)) {
            $custom_exclusions = array_filter(
                array_map('trim', explode("\n", strtolower($exclusion_list))),
                function($word) {
                    return !empty($word);
                }
            );
            $common_words = array_merge($common_words, $custom_exclusions);
        }
        
        // Make exclusion list unique and lowercase
        $common_words = array_unique(array_map('strtolower', $common_words));
        
        // Extract words (2+ characters)
        preg_match_all('/\b[a-z]{2,}\b/i', $text, $matches);
        $words = $matches[0];
        
        // Count word frequency
        $word_freq = array_count_values(array_map('strtolower', $words));
        
        // Remove common words and exclusion list
        foreach ($common_words as $common) {
            unset($word_freq[$common]);
        }
        
        // Sort by frequency
        arsort($word_freq);
        
        // Get max tags setting (default 10)
        $max_tags = isset($options['max_tags_per_post']) ? intval($options['max_tags_per_post']) : 10;
        if ($max_tags < 1) {
            $max_tags = 10;
        } elseif ($max_tags > 50) {
            $max_tags = 50;
        }
        
        // Get top N words based on setting
        $tags = array_slice(array_keys($word_freq), 0, $max_tags);
        
        // Capitalize first letter
        $tags = array_map('ucfirst', $tags);
        
        return $tags;
    }
}
