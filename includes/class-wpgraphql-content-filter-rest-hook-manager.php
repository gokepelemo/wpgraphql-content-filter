<?php
/**
 * REST Hook Manager for WPGraphQL Content Filter
 *
 * Handles integration with WordPress REST API for content filtering.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_REST_Hook_Manager
 *
 * Manages REST API hooks and response filtering integration.
 *
 * @since 2.1.0
 */
class WPGraphQL_Content_Filter_REST_Hook_Manager implements WPGraphQL_Content_Filter_Hook_Manager_Interface {
    
    /**
     * Content filter instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Filter
     */
    private $content_filter;
    
    /**
     * Options manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options_Manager
     */
    private $options_manager;
    
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter_REST_Hook_Manager|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_REST_Hook_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor.
     */
    private function __construct() {
        // Initialize dependencies - they will be overridden by init() if called
        $this->content_filter = WPGraphQL_Content_Filter_Content_Filter::get_instance();
        $this->options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
    }
    
    /**
     * Initialize REST API hooks.
     *
     * @param WPGraphQL_Content_Filter_Options_Manager $options_manager Options manager instance.
     * @param WPGraphQL_Content_Filter_Content_Filter $content_filter Content filter instance.
     */
    public function init($options_manager, $content_filter) {
        $this->options_manager = $options_manager;
        $this->content_filter = $content_filter;

        // Always register hooks for REST API since it's part of core WordPress
        $this->register_hooks();
    }

    /**
     * Register hooks for REST API integration.
     *
     * @return void
     */
    public function register_hooks() {
        add_action('rest_api_init', [$this, 'register_rest_hooks']);
    }

    /**
     * Unregister hooks for REST API integration.
     *
     * @return void
     */
    public function unregister_hooks() {
        remove_action('rest_api_init', [$this, 'register_rest_hooks']);
    }

    /**
     * Check if REST API hooks should be loaded.
     *
     * @return bool
     */
    public function should_load() {
        // Always load REST API hooks since they're part of core WordPress
        return true;
    }
    
    /**
     * Register REST API response hooks for all public post types.
     */
    public function register_rest_hooks() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGraphQL Content Filter: register_rest_hooks called');
        }

        $post_types = $this->get_rest_post_types();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGraphQL Content Filter: Registering hooks for post types: ' . implode(', ', $post_types));
        }

        foreach ($post_types as $post_type) {
            $this->add_rest_response_filter($post_type);
        }
    }
    
    /**
     * Get public post types for REST API.
     *
     * @return array
     */
    private function get_rest_post_types() {
        return get_post_types(['public' => true], 'names');
    }
    
    /**
     * Add REST API response filter for a specific post type.
     *
     * @param string $post_type The post type to filter.
     */
    private function add_rest_response_filter($post_type) {
        add_filter("rest_prepare_{$post_type}", [$this, 'filter_rest_response'], 10, 3);
    }
    
    /**
     * REST API response filter.
     *
     * @param WP_REST_Response $response The response object.
     * @param WP_Post $post The post object.
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function filter_rest_response($response, $post, $request) {
        // Debug logging if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGraphQL Content Filter: filter_rest_response called for post ID: ' . ($post->ID ?? 'unknown'));
        }

        // Early return if dependencies aren't properly initialized
        if (!$this->options_manager || !$this->content_filter) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPGraphQL Content Filter: Dependencies not initialized - options_manager: ' . ($this->options_manager ? 'yes' : 'no') . ', content_filter: ' . ($this->content_filter ? 'yes' : 'no'));
            }
            return $response;
        }

        // Ensure we have a valid post object
        if (!is_object($post) || !isset($post->post_type)) {
            return $response;
        }

        try {
            $options = $this->options_manager->get_options();

            // Debug logging for options
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPGraphQL Content Filter: Options - filter_mode: ' . ($options['filter_mode'] ?? 'none') .
                         ', apply_to_rest_api: ' . ($options['apply_to_rest_api'] ?? 'no') .
                         ', apply_to_content: ' . ($options['apply_to_content'] ?? 'no'));
            }

            // Check if REST API filtering is enabled
            if (empty($options['apply_to_rest_api'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WPGraphQL Content Filter: REST API filtering disabled');
                }
                return $response;
            }

            // Check if filtering is enabled for this post type
            if (!$this->options_manager->is_post_type_enabled($post->post_type)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('WPGraphQL Content Filter: Post type ' . $post->post_type . ' not enabled for filtering');
                }
                return $response;
            }
        } catch (Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPGraphQL Content Filter REST Error: ' . $e->getMessage());
            }
            return $response;
        }

        // Apply filtering with error handling
        try {
            // Filter content field if present and enabled
            if (isset($response->data['content']['rendered']) && !empty($options['apply_to_content'])) {
                $content = $this->decode_content($response->data['content']['rendered']);
                $filtered_content = $this->content_filter->filter_field_content(
                    $content,
                    'content',
                    $options
                );
                $response->data['content']['rendered'] = $filtered_content;
            }

            // Filter excerpt field if present and enabled
            if (isset($response->data['excerpt']['rendered']) && !empty($options['apply_to_excerpt'])) {
                $excerpt = $this->decode_content($response->data['excerpt']['rendered']);
                $filtered_excerpt = $this->content_filter->filter_field_content(
                    $excerpt,
                    'excerpt',
                    $options
                );
                $response->data['excerpt']['rendered'] = $filtered_excerpt;
            }
        } catch (Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WPGraphQL Content Filter REST Filtering Error: ' . $e->getMessage());
            }
            // Return original response if filtering fails
        }

        return $response;
    }

    /**
     * Decode content that may be JSON-encoded or contain HTML entities.
     *
     * @param string $content The content to decode.
     * @return string Decoded content.
     */
    private function decode_content($content) {
        if (!is_string($content)) {
            return $content;
        }

        $original_content = $content;

        // Handle JSON unicode escapes (like \u003C for <)
        if (strpos($content, '\\u') !== false) {
            // Use preg_replace_callback to decode unicode escapes
            $content = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16BE');
            }, $content);

            // If preg_replace_callback failed, fall back to json_decode method
            if ($content === null || $content === false) {
                try {
                    $content = json_decode('"' . addcslashes($original_content, '"\\') . '"');
                    if ($content === null) {
                        $content = $original_content;
                    }
                } catch (Exception $e) {
                    $content = $original_content;
                }
            }
        }

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $content;
    }
}