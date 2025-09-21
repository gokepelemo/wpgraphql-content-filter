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
class WPGraphQL_Content_Filter_REST_Hook_Manager {
    
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
        $this->content_filter = WPGraphQL_Content_Filter_Content_Filter::get_instance();
        $this->options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
    }
    
    /**
     * Initialize REST API hooks.
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_rest_hooks']);
    }
    
    /**
     * Register REST API response hooks for all public post types.
     */
    public function register_rest_hooks() {
        $options = $this->options_manager->get_options();
        
        // Only register REST API hooks if the setting is enabled
        if (empty($options['apply_to_rest_api'])) {
            return;
        }
        
        $post_types = $this->get_rest_post_types();
        
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
        // Filter content field if present
        if (isset($response->data['content']['rendered'])) {
            $response->data['content']['rendered'] = $this->content_filter->filter_field_content(
                $response->data['content']['rendered'], 
                'content'
            );
        }
        
        // Filter excerpt field if present
        if (isset($response->data['excerpt']['rendered'])) {
            $response->data['excerpt']['rendered'] = $this->content_filter->filter_field_content(
                $response->data['excerpt']['rendered'], 
                'excerpt'
            );
        }
        
        return $response;
    }
}