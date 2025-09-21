<?php
/**
 * GraphQL Hook Manager for WPGraphQL Content Filter
 *
 * Handles integration with WPGraphQL for content filtering.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_GraphQL_Hook_Manager
 *
 * Manages GraphQL hooks and field filtering integration.
 *
 * @since 2.1.0
 */
class WPGraphQL_Content_Filter_GraphQL_Hook_Manager {
    
    /**
     * Content filter instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Filter
     */
    private $content_filter;
    
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter_GraphQL_Hook_Manager|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_GraphQL_Hook_Manager
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
    }
    
    /**
     * Initialize GraphQL hooks.
     */
    public function init() {
        // Only register hooks if WPGraphQL is available
        if (!class_exists('WPGraphQL')) {
            return;
        }
        
        add_action('graphql_register_types', [$this, 'register_graphql_hooks']);
    }
    
    /**
     * Register GraphQL field hooks for all WPGraphQL post types.
     */
    public function register_graphql_hooks() {
        $post_types = $this->get_graphql_post_types();
        
        foreach ($post_types as $post_type) {
            // Use WPGraphQL's object field filters for each post type
            add_filter("graphql_resolve_field_value_{$post_type}_content", [$this, 'filter_graphql_content_field'], 10, 4);
            add_filter("graphql_resolve_field_value_{$post_type}_excerpt", [$this, 'filter_graphql_excerpt_field'], 10, 4);
            
            // Also try the post object field filters as fallback
            add_filter("graphql_{$post_type}_object_content", [$this, 'filter_content'], 10, 3);
            add_filter("graphql_{$post_type}_object_excerpt", [$this, 'filter_excerpt'], 10, 3);
        }
        
        // Also add a general field resolver filter as backup
        add_filter('graphql_resolve_field', [$this, 'filter_graphql_field'], 10, 9);
    }
    
    /**
     * Get post types available in WPGraphQL.
     *
     * @return array
     */
    private function get_graphql_post_types() {
        if (class_exists('WPGraphQL') && method_exists('WPGraphQL', 'get_allowed_post_types')) {
            return \WPGraphQL::get_allowed_post_types();
        }
        
        // Fallback to common post types if WPGraphQL method not available
        return ['post', 'page'];
    }
    
    /**
     * Filter GraphQL content field using specific field value filter.
     *
     * @param mixed $value The field value.
     * @param mixed $source The source object.
     * @param array $args The field arguments.
     * @param mixed $context The GraphQL context.
     * @return mixed
     */
    public function filter_graphql_content_field($value, $source, $args, $context) {
        if (is_string($value)) {
            return $this->content_filter->filter_field_content($value, 'content');
        }
        return $value;
    }
    
    /**
     * Filter GraphQL excerpt field using specific field value filter.
     *
     * @param mixed $value The field value.
     * @param mixed $source The source object.
     * @param array $args The field arguments.
     * @param mixed $context The GraphQL context.
     * @return mixed
     */
    public function filter_graphql_excerpt_field($value, $source, $args, $context) {
        if (is_string($value)) {
            return $this->content_filter->filter_field_content($value, 'excerpt');
        }
        return $value;
    }
    
    /**
     * Filter GraphQL fields using the modern graphql_resolve_field filter.
     *
     * @param mixed $result The field result.
     * @param mixed $source The source object.
     * @param array $args The field arguments.
     * @param mixed $context The GraphQL context.
     * @param mixed $info The GraphQL resolve info.
     * @param string $type_name The type name.
     * @param string $field_key The field key.
     * @param mixed $field The field object.
     * @param mixed $field_resolver The field resolver.
     * @return mixed
     */
    public function filter_graphql_field($result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver) {
        // Only filter post-type fields for content and excerpt
        if (!in_array($field_key, ['content', 'excerpt'])) {
            return $result;
        }
        
        // Check if this is a post object
        if (!isset($source->post_type) || !is_object($source)) {
            return $result;
        }
        
        // Get post type and check if it's in our allowed types
        $post_type = $source->post_type;
        $allowed_post_types = $this->get_graphql_post_types();
        
        if (!in_array($post_type, $allowed_post_types)) {
            return $result;
        }
        
        // Apply filtering to the field content
        if (is_string($result)) {
            return $this->content_filter->filter_field_content($result, $field_key);
        }
        
        return $result;
    }
    
    /**
     * GraphQL content field filter (legacy support).
     *
     * @param mixed $content The content.
     * @param mixed $post The post object.
     * @param mixed $context The GraphQL context.
     * @return mixed
     */
    public function filter_content($content, $post, $context) {
        return $this->content_filter->filter_field_content($content, 'content');
    }
    
    /**
     * GraphQL excerpt field filter.
     *
     * @param mixed $excerpt The excerpt.
     * @param mixed $post The post object.
     * @param mixed $context The GraphQL context.
     * @return mixed
     */
    public function filter_excerpt($excerpt, $post, $context) {
        return $this->content_filter->filter_field_content($excerpt, 'excerpt');
    }
}