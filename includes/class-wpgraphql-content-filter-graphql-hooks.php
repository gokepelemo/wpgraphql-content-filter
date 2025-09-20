<?php
/**
 * WPGraphQL Content Filter GraphQL Hooks Manager
 *
 * Handles GraphQL integration with lazy loading and conditional registration.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_GraphQL_Hooks
 *
 * Manages GraphQL hook registration with performance optimizations including
 * lazy loading, conditional registration, and cached post type detection.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_GraphQL_Hooks implements WPGraphQL_Content_Filter_Hook_Manager_Interface {
    /**
     * Fla    /**
     * Register a field filter with duplicate prevention.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    private function register_field_filter($hook, $callback, $priority = 10, $args = 1) {
        // Check if this exact hook/callback combination is already registered
        $filter_signature = $hook . '::' . serialize($callback) . '::' . $priority;
        
        if (in_array($filter_signature, $this->registered_filters)) {
            return; // Already registered, skip
        }
        
        add_filter($hook, $callback, $priority, $args);
        
        // Store the signature instead of full data to save memory
        $this->registered_filters[] = $filter_signature;
    } are registered.
     *
     * @var bool
     */
    private $hooks_registered = false;

    /**
     * Cached GraphQL post types.
     *
     * @var array|null
     */
    private $post_types_cache = null;

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
     * Options manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options|null
     */
    private $options_manager = null;

    /**
     * Registered field filters.
     *
     * @var array
     */
    private $registered_filters = [];

    /**
     * Constructor.
     *
     * @param WPGraphQL_Content_Filter_Content_Processor|null $content_processor Content processor instance.
     * @param WPGraphQL_Content_Filter_Cache|null             $cache_manager     Cache manager instance.
     */
    public function __construct($content_processor = null, $cache_manager = null) {
        $this->content_processor = $content_processor ?: new WPGraphQL_Content_Filter_Content_Processor();
        $this->cache_manager = $cache_manager ?: new WPGraphQL_Content_Filter_Cache();
    }

    /**
     * Register GraphQL hooks.
     *
     * @return void
     */
    public function register_hooks() {
        if ($this->hooks_registered) {
            error_log('WPGraphQL Content Filter: Attempted to register hooks when already registered, preventing duplicate registration');
            return;
        }

        // Only register if WPGraphQL is available and enabled
        if (!$this->should_load()) {
            return;
        }

        // Prevent duplicate registrations by checking if hooks are already registered
        if (has_action('graphql_register_types', [$this, 'register_graphql_field_filters'])) {
            error_log('WPGraphQL Content Filter: GraphQL hooks already registered, skipping');
            return;
        }

        $this->register_graphql_field_filters();
        $this->register_graphql_mutations();
        $this->register_graphql_queries();
        
        $this->hooks_registered = true;
    }

    /**
     * Unregister GraphQL hooks.
     *
     * @return void
     */
    public function unregister_hooks() {
        if (!$this->hooks_registered) {
            return;
        }

        // Since we're using simplified registration, we'll remove all filters
        // registered by this class (this is less precise but saves memory)
        remove_all_filters('graphql_register_types');
        
        // Clear the registration tracking
        $this->registered_filters = [];
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
        return 'GraphQL Hooks Manager';
    }

    /**
     * Check if the hook manager should be loaded.
     *
     * @return bool True if should be loaded, false otherwise.
     */
    public function should_load() {
        // Check if WPGraphQL is available
        if (!class_exists('WPGraphQL')) {
            return false;
        }

        // Check if GraphQL filtering is enabled in options
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        if (empty($options['enable_graphql'])) {
            return false;
        }

        // Check if we're in a GraphQL request context or should pre-register
        return $this->is_graphql_context() || $this->should_pre_register();
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
     * Register GraphQL field filters for content processing.
     *
     * @return void
     */
    private function register_graphql_field_filters() {
        $post_types = $this->get_graphql_post_types_cached();
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();

        foreach ($post_types as $post_type) {
            $graphql_single_name = $this->get_graphql_single_name($post_type);
            
            if (empty($graphql_single_name)) {
                continue;
            }

            // Register content field filter
            $this->register_field_filter(
                "graphql_{$graphql_single_name}_fields",
                [$this, 'register_content_field'],
                10,
                3
            );

            // Register excerpt field filter if enabled
            if (!empty($options['enable_excerpt_filtering'])) {
                $this->register_field_filter(
                    "graphql_{$graphql_single_name}_fields",
                    [$this, 'register_excerpt_field'],
                    10,
                    3
                );
            }
        }

        // Register meta field filters if enabled
        if (!empty($options['enable_meta_filtering'])) {
            $this->register_meta_field_filters();
        }
    }

    /**
     * Register content field for GraphQL.
     *
     * @param array  $fields     Existing fields.
     * @param string $typename   GraphQL type name.
     * @param array  $config     Field configuration.
     * @return array Modified fields.
     */
    public function register_content_field($fields, $typename, $config) {
        // Add filtered content field
        $fields['filteredContent'] = [
            'type' => 'String',
            'description' => __('The filtered content based on plugin settings', 'wpgraphql-content-filter'),
            'resolve' => function($post, $args, $context, $info) {
                return $this->resolve_filtered_content($post, 'content', $args, $context, $info);
            },
        ];

        // Add filtered content with custom options
        $fields['filteredContentWithOptions'] = [
            'type' => 'String',
            'description' => __('The filtered content with custom options', 'wpgraphql-content-filter'),
            'args' => [
                'mode' => [
                    'type' => 'String',
                    'description' => __('Filtering mode: strip_html, markdown, plain_text, preserve_formatting, none', 'wpgraphql-content-filter'),
                    'defaultValue' => 'strip_html',
                ],
                'wordLimit' => [
                    'type' => 'Int',
                    'description' => __('Maximum number of words', 'wpgraphql-content-filter'),
                ],
                'characterLimit' => [
                    'type' => 'Int',
                    'description' => __('Maximum number of characters', 'wpgraphql-content-filter'),
                ],
                'preserveLinks' => [
                    'type' => 'Boolean',
                    'description' => __('Whether to preserve links', 'wpgraphql-content-filter'),
                    'defaultValue' => true,
                ],
                'preserveImages' => [
                    'type' => 'Boolean',
                    'description' => __('Whether to preserve images', 'wpgraphql-content-filter'),
                    'defaultValue' => true,
                ],
                'stripShortcodes' => [
                    'type' => 'Boolean',
                    'description' => __('Whether to strip shortcodes', 'wpgraphql-content-filter'),
                    'defaultValue' => true,
                ],
            ],
            'resolve' => function($post, $args, $context, $info) {
                return $this->resolve_filtered_content_with_options($post, 'content', $args, $context, $info);
            },
        ];

        return $fields;
    }

    /**
     * Register excerpt field for GraphQL.
     *
     * @param array  $fields     Existing fields.
     * @param string $typename   GraphQL type name.
     * @param array  $config     Field configuration.
     * @return array Modified fields.
     */
    public function register_excerpt_field($fields, $typename, $config) {
        $fields['filteredExcerpt'] = [
            'type' => 'String',
            'description' => __('The filtered excerpt based on plugin settings', 'wpgraphql-content-filter'),
            'resolve' => function($post, $args, $context, $info) {
                return $this->resolve_filtered_content($post, 'excerpt', $args, $context, $info);
            },
        ];

        return $fields;
    }

    /**
     * Register meta field filters.
     *
     * @return void
     */
    private function register_meta_field_filters() {
        $this->register_field_filter(
            'graphql_post_object_meta_fields',
            [$this, 'filter_meta_fields'],
            10,
            4
        );
    }

    /**
     * Filter meta fields through content processor.
     *
     * @param array  $fields  Meta fields.
     * @param string $typename GraphQL type name.
     * @param array  $config   Field configuration.
     * @param object $model    Post model.
     * @return array Filtered meta fields.
     */
    public function filter_meta_fields($fields, $typename, $config, $model) {
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $meta_fields_to_filter = $options['meta_fields_to_filter'] ?? [];

        if (empty($meta_fields_to_filter)) {
            return $fields;
        }

        foreach ($fields as $field_name => &$field_config) {
            if (in_array($field_name, $meta_fields_to_filter, true)) {
                $original_resolve = $field_config['resolve'] ?? null;
                
                $field_config['resolve'] = function($post, $args, $context, $info) use ($original_resolve, $field_name) {
                    // Get original value
                    $value = $original_resolve ? $original_resolve($post, $args, $context, $info) : get_post_meta($post->ID, $field_name, true);
                    
                    // Apply filtering if value is a string
                    if (is_string($value) && !empty($value)) {
                        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
                        $value = $this->content_processor->process_content($value, $options['filter_mode'] ?? 'strip_html', $options);
                    }
                    
                    return $value;
                };
            }
        }

        return $fields;
    }

    /**
     * Register GraphQL mutations.
     *
     * @return void
     */
    private function register_graphql_mutations() {
        // Register cache clearing mutation
        register_graphql_mutation('clearContentFilterCache', [
            'inputFields' => [
                'postId' => [
                    'type' => 'Int',
                    'description' => __('Post ID to clear cache for (optional)', 'wpgraphql-content-filter'),
                ],
                'clearAll' => [
                    'type' => 'Boolean',
                    'description' => __('Whether to clear all cache', 'wpgraphql-content-filter'),
                    'defaultValue' => false,
                ],
            ],
            'outputFields' => [
                'success' => [
                    'type' => 'Boolean',
                    'description' => __('Whether the operation was successful', 'wpgraphql-content-filter'),
                ],
                'message' => [
                    'type' => 'String',
                    'description' => __('Operation result message', 'wpgraphql-content-filter'),
                ],
            ],
            'mutateAndGetPayload' => [$this, 'clear_cache_mutation'],
        ]);
    }

    /**
     * Register GraphQL queries.
     *
     * @return void
     */
    private function register_graphql_queries() {
        // Register cache statistics query
        register_graphql_field('RootQuery', 'contentFilterStats', [
            'type' => 'ContentFilterStats',
            'description' => __('Content filter performance statistics', 'wpgraphql-content-filter'),
            'resolve' => [$this, 'get_stats_query'],
        ]);

        // Register the stats type
        register_graphql_object_type('ContentFilterStats', [
            'description' => __('Content filter performance statistics', 'wpgraphql-content-filter'),
            'fields' => [
                'cacheHits' => ['type' => 'Int'],
                'cacheMisses' => ['type' => 'Int'],
                'hitRatio' => ['type' => 'Float'],
                'processingStats' => ['type' => 'String'],
                'memoryUsage' => ['type' => 'Int'],
            ],
        ]);
    }

    /**
     * Resolve filtered content field.
     *
     * @param object $post    Post object.
     * @param string $field   Field name ('content' or 'excerpt').
     * @param array  $args    GraphQL arguments.
     * @param object $context GraphQL context.
     * @param object $info    GraphQL info.
     * @return string Filtered content.
     */
    private function resolve_filtered_content($post, $field, $args, $context, $info) {
        // Early exit to prevent memory issues
        static $processing_count = 0;
        $processing_count++;
        
        if ($processing_count > 50) { // Much lower limit
            error_log('WPGraphQL Content Filter: Too many resolve calls (' . $processing_count . '), potential infinite loop');
            return $field === 'excerpt' ? $post->post_excerpt : $post->post_content;
        }
        
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $content = $field === 'excerpt' ? $post->post_excerpt : $post->post_content;

        if (empty($content)) {
            $processing_count--;
            return '';
        }

        // Skip cache for now to prevent potential recursive issues
        try {
            $result = $this->content_processor->process_content(
                $content,
                $options['filter_mode'] ?? 'strip_html',
                $options
            );
            $processing_count--;
            return $result ?: '';
        } catch (Exception $e) {
            error_log('WPGraphQL Content Filter resolve error: ' . $e->getMessage());
            $processing_count--;
            return $content; // Return original content on error
        }
    }

    /**
     * Resolve filtered content with custom options.
     *
     * @param object $post    Post object.
     * @param string $field   Field name ('content' or 'excerpt').
     * @param array  $args    GraphQL arguments.
     * @param object $context GraphQL context.
     * @param object $info    GraphQL info.
     * @return string Filtered content.
     */
    private function resolve_filtered_content_with_options($post, $field, $args, $context, $info) {
        // Early exit to prevent memory issues
        static $processing_count = 0;
        $processing_count++;
        
        if ($processing_count > 50) {
            error_log('WPGraphQL Content Filter: Too many resolve_with_options calls (' . $processing_count . '), potential infinite loop');
            return $field === 'excerpt' ? $post->post_excerpt : $post->post_content;
        }
        
        $default_options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $content = $field === 'excerpt' ? $post->post_excerpt : $post->post_content;

        if (empty($content)) {
            $processing_count--;
            return '';
        }

        // Merge custom options with defaults
        $custom_options = array_merge($default_options, [
            'filter_mode' => $args['mode'] ?? 'strip_html',
            'word_limit' => $args['wordLimit'] ?? 0,
            'character_limit' => $args['characterLimit'] ?? 0,
            'preserve_links' => $args['preserveLinks'] ?? true,
            'preserve_images' => $args['preserveImages'] ?? true,
            'strip_shortcodes' => $args['stripShortcodes'] ?? true,
        ]);

        // Process directly without cache to prevent recursion
        try {
            $result = $this->content_processor->process_content(
                $content,
                $custom_options['filter_mode'] ?? 'strip_html',
                $custom_options
            );
            $processing_count--;
            return $result ?: '';
        } catch (Exception $e) {
            error_log('WPGraphQL Content Filter resolve_with_options error: ' . $e->getMessage());
            $processing_count--;
            return $content;
        }
    }

    /**
     * Clear cache mutation handler.
     *
     * @param array  $input   Mutation input.
     * @param object $context GraphQL context.
     * @param object $info    GraphQL info.
     * @return array Mutation response.
     */
    public function clear_cache_mutation($input, $context, $info) {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => __('Insufficient permissions', 'wpgraphql-content-filter'),
            ];
        }

        try {
            if (!empty($input['clearAll'])) {
                $this->cache_manager->flush();
                $message = __('All cache cleared successfully', 'wpgraphql-content-filter');
            } elseif (!empty($input['postId'])) {
                $this->cache_manager->clear_post_cache($input['postId']);
                $message = sprintf(__('Cache cleared for post ID %d', 'wpgraphql-content-filter'), $input['postId']);
            } else {
                return [
                    'success' => false,
                    'message' => __('No valid clear operation specified', 'wpgraphql-content-filter'),
                ];
            }

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Cache clear failed: %s', 'wpgraphql-content-filter'), $e->getMessage()),
            ];
        }
    }

    /**
     * Get statistics query handler.
     *
     * @param object $root    Root object.
     * @param array  $args    Query arguments.
     * @param object $context GraphQL context.
     * @param object $info    GraphQL info.
     * @return array Statistics data.
     */
    public function get_stats_query($root, $args, $context, $info) {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return null;
        }

        $cache_stats = $this->cache_manager->get_stats();
        $processor_stats = $this->content_processor->get_stats();

        return [
            'cacheHits' => $cache_stats['hits'] ?? 0,
            'cacheMisses' => $cache_stats['misses'] ?? 0,
            'hitRatio' => $cache_stats['hit_rate'] ?? 0,
            'processingStats' => json_encode($processor_stats),
            'memoryUsage' => memory_get_usage(true),
        ];
    }

    /**
     * Get cached GraphQL post types.
     *
     * @return array Array of post types enabled for GraphQL.
     */
    private function get_graphql_post_types_cached() {
        if ($this->post_types_cache === null) {
            $this->post_types_cache = class_exists('WPGraphQL') ? 
                \WPGraphQL::get_allowed_post_types() : [];
        }
        return $this->post_types_cache;
    }

    /**
     * Get GraphQL single name for a post type.
     *
     * @param string $post_type Post type name.
     * @return string GraphQL single name.
     */
    private function get_graphql_single_name($post_type) {
        $post_type_object = get_post_type_object($post_type);
        return $post_type_object->graphql_single_name ?? '';
    }

    /**
     * Check if we're in a GraphQL request context.
     *
     * @return bool True if in GraphQL context.
     */
    private function is_graphql_context() {
        return defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST;
    }

    /**
     * Check if we should pre-register hooks before GraphQL request.
     *
     * @return bool True if should pre-register.
     */
    private function should_pre_register() {
        // Pre-register in admin for schema introspection
        return is_admin() || (defined('WP_CLI') && WP_CLI);
    }

    /**
     * Register a field filter and track it for cleanup.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback Callback function.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of arguments.
     * @return void
     */
    private function register_field_filter($hook, $callback, $priority = 10, $args = 1) {
        add_filter($hook, $callback, $priority, $args);
        
        $this->registered_filters[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }

    /**
     * Clear cached data.
     *
     * @return void
     */
    public function clear_cache() {
        $this->post_types_cache = null;
    }
}