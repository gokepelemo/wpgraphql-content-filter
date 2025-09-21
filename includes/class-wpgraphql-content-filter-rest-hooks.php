<?php
/**
 * WPGraphQL Content Filter REST API Hooks Manager
 *
 * Handles REST API integration with performance optimizations.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_REST_Hooks
 *
 * Manages REST API hook registration with performance optimizations and
 * conditional loading based on request context.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_REST_Hooks implements WPGraphQL_Content_Filter_Hook_Manager_Interface {
    /**
     * Flag to track if hooks are registered.
     *
     * @var bool
     */
    private $hooks_registered = false;

    /**
     * Content processor instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Processor|null
     */
    private $content_processor = null;

    /**
     * Cache manager instance.
     *
     * @var WPGraphQL_Content_Filter_Cache|null
     */
    private $cache_manager = null;

    /**
     * Registered hooks tracking.
     *
     * @var array
     */
    private $registered_hooks = [];

    /**
     * Constructor.
     *
     * @param WPGraphQL_Content_Filter_Content_Processor|null $content_processor Content processor instance.
     * @param WPGraphQL_Content_Filter_Cache|null             $cache_manager     Cache manager instance.
     */
    public function __construct($content_processor = null, $cache_manager = null) {
        $this->content_processor = $content_processor ?: new WPGraphQL_Content_Filter_Content_Processor();
        $this->cache_manager = $cache_manager; // Accept null if no cache is available
    }

    /**
     * Check if cache is available.
     *
     * @return bool True if cache manager is available, false otherwise.
     */
    private function is_cache_available() {
        return $this->cache_manager !== null;
    }

    /**
     * Register REST API hooks.
     *
     * @return void
     */
    public function register_hooks() {
        if ($this->hooks_registered) {
            return;
        }

        // Only register if REST API filtering is enabled
        if (!$this->should_load()) {
            return;
        }

        $this->register_rest_field_filters();
        $this->register_rest_endpoints();
        
        $this->hooks_registered = true;
    }

    /**
     * Unregister REST API hooks.
     *
     * @return void
     */
    public function unregister_hooks() {
        if (!$this->hooks_registered) {
            return;
        }

        // Remove all registered hooks
        foreach ($this->registered_hooks as $hook_data) {
            remove_action($hook_data['hook'], $hook_data['callback'], $hook_data['priority']);
        }

        $this->registered_hooks = [];
        $this->hooks_registered = false;
    }

    /**
     * Check if hooks are registered.
     *
     * @return bool True if registered, false otherwise.
     */
    public function are_hooks_registered() {
        return $this->hooks_registered;
    }

    /**
     * Get the hook manager name.
     *
     * @return string The manager name.
     */
    public function get_name() {
        return 'REST API Hooks Manager';
    }

    /**
     * Check if the hook manager should be loaded.
     *
     * @return bool True if should be loaded, false otherwise.
     */
    public function should_load() {
        // Check if REST API filtering is enabled in options
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        if (empty($options['enable_rest_api'])) {
            return false;
        }

        // Check if we're in a REST request context or should pre-register
        return $this->is_rest_context() || $this->should_pre_register();
    }

    /**
     * Get the priority for hook registration.
     *
     * @return int The priority (10 = default).
     */
    public function get_priority() {
        return 10;
    }

    /**
     * Conditionally register hooks based on current context.
     *
     * @return void
     */
    public function maybe_register_hooks() {
        if ($this->should_load()) {
            $this->register_hooks();
        }
    }

    /**
     * Register REST API field filters for content processing.
     *
     * @return void
     */
    private function register_rest_field_filters() {
        // Memory protection - limit post types and add safety checks
        $memory_before = memory_get_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        
        if ($memory_before > ($memory_limit * 0.7)) {
            error_log("WPGraphQL Content Filter REST: Skipping registration - memory usage too high");
            return;
        }
        
        // Get post types with safety limit
        $post_types = get_post_types(['public' => true], 'names');
        
        // Safety limit to prevent memory exhaustion
        if (count($post_types) > 20) {
            error_log("WPGraphQL Content Filter REST: Limiting post types from " . count($post_types) . " to 20 for memory safety");
            $post_types = array_slice($post_types, 0, 20);
        }
        
        foreach ($post_types as $post_type) {
            // Check memory per iteration
            if (memory_get_usage(true) > ($memory_limit * 0.8)) {
                error_log("WPGraphQL Content Filter REST: Stopping registration - memory limit approaching");
                break;
            }
            
            // Register filtered content field
            register_rest_field($post_type, 'filtered_content', [
                'get_callback' => [$this, 'get_filtered_content_callback'],
                'schema' => [
                    'description' => __('Filtered content based on plugin settings', 'wpgraphql-content-filter'),
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]);

            // Register filtered excerpt field
            register_rest_field($post_type, 'filtered_excerpt', [
                'get_callback' => [$this, 'get_filtered_excerpt_callback'],
                'schema' => [
                    'description' => __('Filtered excerpt based on plugin settings', 'wpgraphql-content-filter'),
                    'type' => 'string',
                    'context' => ['view', 'edit'],
                ],
            ]);
        }

        // Register response filter for existing content fields with memory-safe hook registration
        $this->register_hook_action_safe(
            'rest_prepare_post',
            [$this, 'filter_post_response'],
            10,
            3
        );
        
        $memory_after = memory_get_usage(true);
        $memory_used = $memory_after - $memory_before;
        
        if ($memory_used > 5242880) { // 5MB
            error_log("WPGraphQL Content Filter REST: WARNING - Registration used " . number_format($memory_used / 1048576, 2) . "MB of memory");
        }
    }

    /**
     * Register custom REST API endpoints.
     *
     * @return void
     */
    private function register_rest_endpoints() {
        $this->register_hook_action(
            'rest_api_init',
            [$this, 'register_custom_endpoints']
        );
    }

    /**
     * Register custom REST API endpoints.
     *
     * @return void
     */
    public function register_custom_endpoints() {
        // Register endpoint for filtered content with custom options
        register_rest_route('wpgraphql-content-filter/v1', '/filter', [
            'methods' => 'POST',
            'callback' => [$this, 'filter_content_endpoint'],
            'permission_callback' => [$this, 'check_filter_permissions'],
            'args' => [
                'content' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Content to filter', 'wpgraphql-content-filter'),
                ],
                'mode' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'strip_html',
                    'enum' => ['strip_html', 'markdown', 'plain_text', 'preserve_formatting', 'none'],
                    'description' => __('Filtering mode', 'wpgraphql-content-filter'),
                ],
                'options' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => __('Additional filtering options', 'wpgraphql-content-filter'),
                ],
            ],
        ]);

        // Register endpoint for cache management
        register_rest_route('wpgraphql-content-filter/v1', '/cache', [
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'clear_cache_endpoint'],
                'permission_callback' => [$this, 'check_admin_permissions'],
                'args' => [
                    'post_id' => [
                        'required' => false,
                        'type' => 'integer',
                        'description' => __('Post ID to clear cache for', 'wpgraphql-content-filter'),
                    ],
                    'clear_all' => [
                        'required' => false,
                        'type' => 'boolean',
                        'default' => false,
                        'description' => __('Whether to clear all cache', 'wpgraphql-content-filter'),
                    ],
                ],
            ],
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_cache_stats_endpoint'],
                'permission_callback' => [$this, 'check_admin_permissions'],
            ],
        ]);

        // Register endpoint for cache warming
        register_rest_route('wpgraphql-content-filter/v1', '/cache/warm', [
            'methods' => 'POST',
            'callback' => [$this, 'warm_cache_endpoint'],
            'permission_callback' => [$this, 'check_admin_permissions'],
            'args' => [
                'post_ids' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => __('Post IDs to warm cache for', 'wpgraphql-content-filter'),
                ],
                'limit' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 200,
                    'description' => __('Maximum number of posts to warm', 'wpgraphql-content-filter'),
                ],
            ],
        ]);
    }

    /**
     * Get filtered content callback for REST API.
     *
     * @param array           $post    Post data.
     * @param string          $field   Field name.
     * @param WP_REST_Request $request REST request.
     * @return string Filtered content.
     */
    public function get_filtered_content_callback($post, $field, $request) {
        if (empty($post['content']['rendered'])) {
            return '';
        }

        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $content = $post['content']['rendered'];

        // Generate cache key
        $content_hash = md5($content);
        $options_hash = md5(serialize($options));

        // Try to get from cache first if cache is available
        if ($this->is_cache_available()) {
            $cached_content = $this->cache_manager->get_filtered_content(
                $content_hash,
                $options_hash,
                function() use ($content, $options) {
                    return $this->content_processor->process_content(
                        $content,
                        $options['filter_mode'] ?? 'strip_html',
                        $options
                    );
                }
            );
            return $cached_content ?: '';
        }

        // Process content directly without cache
        return $this->content_processor->process_content(
            $content,
            $options['filter_mode'] ?? 'strip_html',
            $options
        ) ?: '';
    }

    /**
     * Get filtered excerpt callback for REST API.
     *
     * @param array           $post    Post data.
     * @param string          $field   Field name.
     * @param WP_REST_Request $request REST request.
     * @return string Filtered excerpt.
     */
    public function get_filtered_excerpt_callback($post, $field, $request) {
        $excerpt = $post['excerpt']['rendered'] ?? '';
        
        if (empty($excerpt)) {
            return '';
        }

        $options = WPGraphQL_Content_Filter_Options::get_effective_options();

        // Generate cache key
        $content_hash = md5($excerpt);
        $options_hash = md5(serialize($options));

        // Try to get from cache first if cache is available
        if ($this->is_cache_available()) {
            $cached_content = $this->cache_manager->get_filtered_content(
                $content_hash,
                $options_hash,
                function() use ($excerpt, $options) {
                    return $this->content_processor->process_content(
                        $excerpt,
                        $options['filter_mode'] ?? 'strip_html',
                        $options
                    );
                }
            );
            return $cached_content ?: '';
        }

        // Process content directly without cache
        return $this->content_processor->process_content(
            $excerpt,
            $options['filter_mode'] ?? 'strip_html',
            $options
        ) ?: '';
    }

    /**
     * Filter post response to modify existing fields.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_Post          $post     Post object.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response Modified response.
     */
    public function filter_post_response($response, $post, $request) {
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        
        // Only filter if auto-filtering is enabled
        if (empty($options['auto_filter_rest_content'])) {
            return $response;
        }

        $data = $response->get_data();

        // Filter content field
        if (!empty($data['content']['rendered'])) {
            $content = $data['content']['rendered'];
            $content_hash = md5($content);
            $options_hash = md5(serialize($options));

            $filtered_content = $this->cache_manager->get_filtered_content(
                $content_hash,
                $options_hash,
                function() use ($content, $options) {
                    return $this->content_processor->process_content(
                        $content,
                        $options['filter_mode'] ?? 'strip_html',
                        $options
                    );
                }
            );

            if ($filtered_content !== false) {
                $data['content']['rendered'] = $filtered_content;
            }
        }

        // Filter excerpt field
        if (!empty($data['excerpt']['rendered'])) {
            $excerpt = $data['excerpt']['rendered'];
            $content_hash = md5($excerpt);
            $options_hash = md5(serialize($options));

            $filtered_excerpt = $this->cache_manager->get_filtered_content(
                $content_hash,
                $options_hash,
                function() use ($excerpt, $options) {
                    return $this->content_processor->process_content(
                        $excerpt,
                        $options['filter_mode'] ?? 'strip_html',
                        $options
                    );
                }
            );

            if ($filtered_excerpt !== false) {
                $data['excerpt']['rendered'] = $filtered_excerpt;
            }
        }

        $response->set_data($data);
        return $response;
    }

    /**
     * Filter content endpoint handler.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function filter_content_endpoint($request) {
        $content = $request->get_param('content');
        $mode = $request->get_param('mode') ?: 'strip_html';
        $custom_options = $request->get_param('options') ?: [];

        if (empty($content)) {
            return new WP_Error('empty_content', __('Content parameter is required', 'wpgraphql-content-filter'), ['status' => 400]);
        }

        // Merge custom options with defaults
        $default_options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $options = array_merge($default_options, $custom_options, ['filter_mode' => $mode]);

        try {
            $filtered_content = $this->content_processor->process_content($content, $mode, $options);
            
            return new WP_REST_Response([
                'success' => true,
                'filtered_content' => $filtered_content,
                'original_length' => strlen($content),
                'filtered_length' => strlen($filtered_content),
                'mode' => $mode,
            ]);
        } catch (Exception $e) {
            return new WP_Error('filtering_failed', 
                sprintf(__('Content filtering failed: %s', 'wpgraphql-content-filter'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Clear cache endpoint handler.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function clear_cache_endpoint($request) {
        $post_id = $request->get_param('post_id');
        $clear_all = $request->get_param('clear_all');

        // Check if cache is available
        if (!$this->is_cache_available()) {
            return new WP_Error('cache_unavailable',
                __('Cache manager not available', 'wpgraphql-content-filter'),
                ['status' => 400]
            );
        }

        try {
            if ($clear_all) {
                $this->cache_manager->flush();
                $message = __('All cache cleared successfully', 'wpgraphql-content-filter');
            } elseif ($post_id) {
                $this->cache_manager->clear_post_cache($post_id);
                $message = sprintf(__('Cache cleared for post ID %d', 'wpgraphql-content-filter'), $post_id);
            } else {
                return new WP_Error('invalid_params', 
                    __('Either post_id or clear_all parameter is required', 'wpgraphql-content-filter'),
                    ['status' => 400]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            return new WP_Error('cache_clear_failed',
                sprintf(__('Cache clear failed: %s', 'wpgraphql-content-filter'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Get cache statistics endpoint handler.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response Response.
     */
    public function get_cache_stats_endpoint($request) {
        // Get processor stats (always available)
        $processor_stats = $this->content_processor->get_stats();
        
        // Get cache stats if cache is available
        $cache_stats = $this->is_cache_available() 
            ? $this->cache_manager->get_stats()
            : ['hits' => 0, 'misses' => 0, 'hit_rate' => 0];

        return new WP_REST_Response([
            'cache' => $cache_stats,
            'processor' => $processor_stats,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time(),
        ]);
    }

    /**
     * Warm cache endpoint handler.
     *
     * @param WP_REST_Request $request REST request.
     * @return WP_REST_Response Response.
     */
    public function warm_cache_endpoint($request) {
        $post_ids = $request->get_param('post_ids') ?: [];
        $limit = $request->get_param('limit') ?: 50;

        // Check if cache is available
        if (!$this->is_cache_available()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Cache manager not available',
                'results' => [],
                'total_processed' => 0,
                'successful' => 0,
                'failed' => 0,
            ]);
        }

        try {
            $results = $this->cache_manager->warm_cache($post_ids, []);
            
            return new WP_REST_Response([
                'success' => true,
                'results' => $results,
                'total_processed' => count($results['success']) + count($results['failed']),
                'successful' => count($results['success']),
                'failed' => count($results['failed']),
                'skipped' => count($results['skipped']),
            ]);
        } catch (Exception $e) {
            return new WP_Error('cache_warm_failed',
                sprintf(__('Cache warming failed: %s', 'wpgraphql-content-filter'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Check filter permissions for REST API endpoints.
     *
     * @param WP_REST_Request $request REST request.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function check_filter_permissions($request) {
        // Allow public access to content filtering by default
        // But check if authentication is required in options
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        
        if (!empty($options['require_auth_for_filtering'])) {
            return current_user_can('read');
        }

        return true;
    }

    /**
     * Check admin permissions for REST API endpoints.
     *
     * @param WP_REST_Request $request REST request.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function check_admin_permissions($request) {
        return current_user_can('manage_options');
    }

    /**
     * Check if we're in a REST request context.
     *
     * @return bool True if in REST context.
     */
    private function is_rest_context() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Check if we should pre-register hooks.
     *
     * @return bool True if should pre-register.
     */
    private function should_pre_register() {
        // Pre-register in admin for REST API discovery
        return is_admin() || (defined('WP_CLI') && WP_CLI);
    }

    /**
     * Register an action hook and track it for cleanup.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    /**
     * Register hook action with memory protection.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    private function register_hook_action_safe($hook, $callback, $priority = 10, $args = 1) {
        add_action($hook, $callback, $priority, $args);
        
        // Store only lightweight signature instead of full callback data
        $callback_sig = is_array($callback) ? get_class($callback[0]) . '::' . $callback[1] : $callback;
        
        $this->registered_hooks[] = [
            'hook' => $hook,
            'callback_sig' => $callback_sig,
            'priority' => $priority,
        ];
    }

    /**
     * Register hook action with duplicate callback storage (legacy - causes memory issues).
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    private function register_hook_action($hook, $callback, $priority = 10, $args = 1) {
        add_action($hook, $callback, $priority, $args);
        
        $this->registered_hooks[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }
}