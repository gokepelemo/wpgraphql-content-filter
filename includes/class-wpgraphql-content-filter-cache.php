<?php
/**
 * WPGraphQL Content Filter Cache Manager
 *
 * Handles caching with provider pattern and cache warming capabilities.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Cache
 *
 * Advanced cache management system with support for multiple cache providers,
 * intelligent cache warming, and performance monitoring.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_Cache {
    /**
     * Registered cache providers.
     *
     * @var array
     */
    private $providers = [];

    /**
     * Active cache provider.
     *
     * @var WPGraphQL_Content_Filter_Cache_Provider_Interface|null
     */
    private $active_provider = null;

    /**
     * Cache statistics.
     *
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0
    ];

    /**
     * Cache key prefix.
     *
     * @var string
     */
    private $key_prefix = 'wpgcf_';

    /**
     * Default cache expiration.
     *
     * @var int
     */
    private $default_expiration = 86400; // 24 hours

    /**
     * Constructor.
     */
    public function __construct() {
        $this->register_default_providers();
        $this->set_active_provider();
    }

    /**
     * Register a cache provider.
     *
     * @param string                                            $name     Provider name.
     * @param WPGraphQL_Content_Filter_Cache_Provider_Interface $provider Provider instance.
     * @return void
     */
    public function register_provider($name, WPGraphQL_Content_Filter_Cache_Provider_Interface $provider) {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a cached value.
     *
     * @param string $key Cache key.
     * @return mixed Cached value or false if not found.
     */
    public function get($key) {
        if (!$this->active_provider) {
            return false;
        }

        $full_key = $this->build_cache_key($key);
        $result = $this->active_provider->get($full_key);

        if ($result !== false) {
            $this->stats['hits']++;
        } else {
            $this->stats['misses']++;
        }

        return $result;
    }

    /**
     * Set a cached value.
     *
     * @param string $key        Cache key.
     * @param mixed  $value      Value to cache.
     * @param int    $expiration Expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function set($key, $value, $expiration = null) {
        if (!$this->active_provider) {
            return false;
        }

        $full_key = $this->build_cache_key($key);
        $exp = $expiration ?? $this->default_expiration;
        
        $result = $this->active_provider->set($full_key, $value, $exp);
        
        if ($result) {
            $this->stats['sets']++;
        }

        return $result;
    }

    /**
     * Delete a cached value.
     *
     * @param string $key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete($key) {
        if (!$this->active_provider) {
            return false;
        }

        $full_key = $this->build_cache_key($key);
        $result = $this->active_provider->delete($full_key);
        
        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    /**
     * Flush all cached values.
     *
     * @return bool True on success, false on failure.
     */
    public function flush() {
        if (!$this->active_provider) {
            return false;
        }

        $result = $this->active_provider->flush();
        
        if ($result) {
            $this->stats['flushes']++;
        }

        return $result;
    }

    /**
     * Get filtered content from cache or generate and cache it.
     *
     * @param string $content_hash Original content hash.
     * @param string $options_hash Processing options hash.
     * @param callable $generator  Function to generate content if not cached.
     * @return string Filtered content.
     */
    public function get_filtered_content($content_hash, $options_hash, $generator = null) {
        $cache_key = "filtered_content_{$content_hash}_{$options_hash}";
        $cached_content = $this->get($cache_key);

        if ($cached_content !== false) {
            return $cached_content;
        }

        // Generate content if generator provided
        if ($generator && is_callable($generator)) {
            $content = $generator();
            $this->set($cache_key, $content);
            return $content;
        }

        return false;
    }

    /**
     * Warm cache for specific content or post IDs.
     *
     * @param array $post_ids Array of post IDs to warm cache for.
     * @param array $options  Processing options to use for warming.
     * @return array Results of cache warming operation.
     */
    public function warm_cache($post_ids = [], $options = []) {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => []
        ];

        if (empty($post_ids)) {
            // Get recent posts if no specific IDs provided
            $post_ids = $this->get_recent_post_ids();
        }

        foreach ($post_ids as $post_id) {
            try {
                $success = $this->warm_post_cache($post_id, $options);
                
                if ($success) {
                    $results['success'][] = $post_id;
                } else {
                    $results['failed'][] = $post_id;
                }
            } catch (Exception $e) {
                $results['failed'][] = $post_id;
                
                // Log error if WP debug logging is enabled
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log("WPGraphQL Content Filter: Cache warming failed for post {$post_id}: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Warm cache for a specific post.
     *
     * @param int   $post_id Post ID.
     * @param array $options Processing options.
     * @return bool True on success, false on failure.
     */
    private function warm_post_cache($post_id, $options = []) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }

        // Get default options if none provided
        if (empty($options)) {
            $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        }

        // Generate cache keys for common content fields
        $fields_to_cache = ['post_content', 'post_excerpt'];
        $modes_to_cache = ['strip_html', 'markdown', 'plain_text'];

        foreach ($fields_to_cache as $field) {
            $content = $post->$field ?? '';
            
            if (empty($content)) {
                continue;
            }

            foreach ($modes_to_cache as $mode) {
                $temp_options = array_merge($options, ['filter_mode' => $mode]);
                $content_hash = md5($content);
                $options_hash = md5(serialize($temp_options));
                
                // Check if already cached
                $cache_key = "filtered_content_{$content_hash}_{$options_hash}";
                
                if ($this->get($cache_key) === false) {
                    // Generate and cache the filtered content
                    $processor = new WPGraphQL_Content_Filter_Content_Processor();
                    $filtered_content = $processor->process_content($content, $mode, $temp_options);
                    $this->set($cache_key, $filtered_content);
                }
            }
        }

        return true;
    }

    /**
     * Get recent post IDs for cache warming.
     *
     * @param int $limit Number of posts to retrieve.
     * @return array Array of post IDs.
     */
    private function get_recent_post_ids($limit = 50) {
        $posts = get_posts([
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        return $posts ?: [];
    }

    /**
     * Register default cache providers.
     *
     * @return void
     */
    private function register_default_providers() {
        // Register WordPress object cache provider
        $this->register_provider('wp_object_cache', new WPGraphQL_Content_Filter_WP_Object_Cache_Provider());
        
        // Register transient cache provider as fallback
        $this->register_provider('transient', new WPGraphQL_Content_Filter_Transient_Cache_Provider());
    }

    /**
     * Set the active cache provider based on availability and configuration.
     *
     * @return void
     */
    private function set_active_provider() {
        $options = WPGraphQL_Content_Filter_Options::get_effective_options();
        $preferred_provider = $options['cache_provider'] ?? 'auto';

        if ($preferred_provider === 'auto') {
            // Auto-select best available provider
            foreach ($this->providers as $name => $provider) {
                if ($provider->is_available()) {
                    $this->active_provider = $provider;
                    break;
                }
            }
        } elseif (isset($this->providers[$preferred_provider]) && $this->providers[$preferred_provider]->is_available()) {
            $this->active_provider = $this->providers[$preferred_provider];
        } else {
            // Fallback to first available provider
            foreach ($this->providers as $provider) {
                if ($provider->is_available()) {
                    $this->active_provider = $provider;
                    break;
                }
            }
        }
    }

    /**
     * Build full cache key with prefix and site context.
     *
     * @param string $key Base cache key.
     * @return string Full cache key.
     */
    private function build_cache_key($key) {
        $site_id = is_multisite() ? get_current_blog_id() : 1;
        return $this->key_prefix . $site_id . '_' . $key;
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics.
     */
    public function get_stats() {
        $total_operations = array_sum($this->stats);
        $hit_rate = $total_operations > 0 ? ($this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses'])) * 100 : 0;

        return array_merge($this->stats, [
            'hit_rate' => round($hit_rate, 2),
            'total_operations' => $total_operations,
            'active_provider' => $this->active_provider ? $this->active_provider->get_name() : 'none',
            'available_providers' => array_keys(array_filter($this->providers, function($provider) {
                return $provider->is_available();
            }))
        ]);
    }

    /**
     * Get the active cache provider.
     *
     * @return WPGraphQL_Content_Filter_Cache_Provider_Interface|null
     */
    public function get_active_provider() {
        return $this->active_provider;
    }

    /**
     * Set the cache key prefix.
     *
     * @param string $prefix Cache key prefix.
     * @return void
     */
    public function set_key_prefix($prefix) {
        $this->key_prefix = sanitize_key($prefix);
    }

    /**
     * Set default cache expiration.
     *
     * @param int $expiration Expiration time in seconds.
     * @return void
     */
    public function set_default_expiration($expiration) {
        $this->default_expiration = max(60, intval($expiration)); // Minimum 1 minute
    }

    /**
     * Clear cache for specific post.
     *
     * @param int $post_id Post ID.
     * @return bool True on success, false on failure.
     */
    public function clear_post_cache($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }

        // Clear cache for all possible content variations
        $fields = ['post_content', 'post_excerpt'];
        $modes = ['strip_html', 'markdown', 'plain_text', 'preserve_formatting', 'none'];

        foreach ($fields as $field) {
            $content = $post->$field ?? '';
            
            if (empty($content)) {
                continue;
            }

            $content_hash = md5($content);
            
            foreach ($modes as $mode) {
                // We need to try common option combinations
                $option_combinations = $this->get_common_option_combinations($mode);
                
                foreach ($option_combinations as $options) {
                    $options_hash = md5(serialize($options));
                    $cache_key = "filtered_content_{$content_hash}_{$options_hash}";
                    $this->delete($cache_key);
                }
            }
        }

        return true;
    }

    /**
     * Get common option combinations for cache clearing.
     *
     * @param string $mode Filter mode.
     * @return array Array of option combinations.
     */
    private function get_common_option_combinations($mode) {
        $base_options = [
            'filter_mode' => $mode,
            'strip_shortcodes' => true,
            'preserve_links' => true,
            'preserve_images' => true,
            'preserve_headings' => true
        ];

        return [
            $base_options,
            array_merge($base_options, ['convert_to_markdown' => true]),
            array_merge($base_options, ['word_limit' => 50]),
            array_merge($base_options, ['character_limit' => 200])
        ];
    }
}

/**
 * WordPress Object Cache Provider
 */
class WPGraphQL_Content_Filter_WP_Object_Cache_Provider implements WPGraphQL_Content_Filter_Cache_Provider_Interface {
    public function get($key) {
        return wp_cache_get($key, 'wpgraphql_content_filter');
    }

    public function set($key, $value, $expiration = 0) {
        return wp_cache_set($key, $value, 'wpgraphql_content_filter', $expiration);
    }

    public function delete($key) {
        return wp_cache_delete($key, 'wpgraphql_content_filter');
    }

    public function flush() {
        return wp_cache_flush_group('wpgraphql_content_filter');
    }

    public function is_available() {
        return function_exists('wp_cache_get');
    }

    public function get_name() {
        return 'WP Object Cache';
    }

    public function get_multiple($keys) {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    public function set_multiple($data, $expiration = 0) {
        $success = true;
        foreach ($data as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                $success = false;
            }
        }
        return $success;
    }
}

/**
 * WordPress Transient Cache Provider
 */
class WPGraphQL_Content_Filter_Transient_Cache_Provider implements WPGraphQL_Content_Filter_Cache_Provider_Interface {
    public function get($key) {
        return get_transient($key);
    }

    public function set($key, $value, $expiration = 0) {
        return set_transient($key, $value, $expiration);
    }

    public function delete($key) {
        return delete_transient($key);
    }

    public function flush() {
        // Transients don't have a built-in flush, so we'll use a different approach
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpgcf_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpgcf_%'");
        return true;
    }

    public function is_available() {
        return function_exists('get_transient');
    }

    public function get_name() {
        return 'WordPress Transients';
    }

    public function get_multiple($keys) {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    public function set_multiple($data, $expiration = 0) {
        $success = true;
        foreach ($data as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                $success = false;
            }
        }
        return $success;
    }
}