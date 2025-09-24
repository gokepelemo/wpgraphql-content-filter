<?php
/**
 * Options Manager for WPGraphQL Content Filter
 *
 * Handles all plugin settings, caching, and multisite configuration.
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Options_Manager
 *
 * Manages plugin options, caching, and multisite synchronization.
 *
 * @since 2.1.0
 */
class WPGraphQL_Content_Filter_Options_Manager {
    
    /**
     * Options cache for performance.
     *
     * @var array
     */
    private $options_cache = [];
    
    /**
     * Network options cache.
     *
     * @var array|null
     */
    private $network_options_cache = null;
    
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter_Options_Manager|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter_Options_Manager
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
        // Hook registration moved to init() method to avoid WordPress function calls during class loading
    }
    
    /**
     * Initialize the options manager and register hooks.
     *
     * @return void
     */
    public function init() {
        // Hook to clear cache when options are updated
        add_action('updated_option', [$this, 'on_option_updated'], 10, 3);
        add_action('updated_site_option', [$this, 'on_site_option_updated'], 10, 3);
    }
    
    /**
     * Get default plugin options.
     *
     * @return array
     */
    public function get_default_options() {
        return [
            'filter_mode' => 'strip_all',
            'preserve_line_breaks' => true,
            'convert_headings' => true,
            'convert_links' => true,
            'convert_lists' => true,
            'convert_emphasis' => true,
            'custom_allowed_tags' => '',
            'apply_to_excerpt' => true,
            'apply_to_content' => true,
            'apply_to_rest_api' => true,
            'enabled_post_types' => ['post', 'page'],
            'remove_plugin_data_on_uninstall' => false,
        ];
    }
    
    /**
     * Get plugin options with defaults, considering multisite configuration.
     *
     * @return array
     */
    public function get_options() {
        $site_id = is_multisite() ? get_current_blog_id() : 0;
        
        // Return cached options if available
        if (isset($this->options_cache[$site_id])) {
            return $this->options_cache[$site_id];
        }
        
        $options = $this->calculate_effective_options();
        
        // Cache the result
        $this->options_cache[$site_id] = $options;
        
        return $options;
    }
    
    /**
     * Calculate effective options considering multisite hierarchy.
     *
     * @return array
     */
    private function calculate_effective_options() {
        $defaults = $this->get_default_options();
        
        if (!is_multisite()) {
            return get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $defaults);
        }
        
        $network_options = $this->get_network_options();
        
        // If site overrides are not allowed, use network settings exclusively
        if (empty($network_options['allow_site_overrides'])) {
            return array_merge($defaults, $network_options);
        }
        
        // Get site options with override tracking
        $site_data = $this->get_site_data();
        $site_options = $site_data['options'] ?? [];
        $site_overrides = $site_data['overrides'] ?? [];
        
        // Start with defaults, then apply network settings
        $effective_options = array_merge($defaults, $network_options);
        
        // Apply only the explicitly overridden site settings
        foreach ($site_overrides as $key => $is_overridden) {
            if ($is_overridden && isset($site_options[$key])) {
                $effective_options[$key] = $site_options[$key];
            }
        }
        
        return $effective_options;
    }
    
    /**
     * Get network options for multisite.
     *
     * @return array
     */
    public function get_network_options() {
        // Return cached network options if available
        if ($this->network_options_cache !== null) {
            return $this->network_options_cache;
        }
        
        $defaults = array_merge($this->get_default_options(), [
            'allow_site_overrides' => true
        ]);
        
        $this->network_options_cache = get_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, $defaults);
        
        return $this->network_options_cache;
    }
    
    /**
     * Get site-specific options for multisite.
     *
     * @return array
     */
    public function get_site_options() {
        $site_data = $this->get_site_data();
        
        // Handle legacy format (direct options array)
        if (!isset($site_data['options']) && !isset($site_data['overrides'])) {
            return $site_data;
        }
        
        return $site_data['options'] ?? [];
    }
    
    /**
     * Get site override settings.
     *
     * @return array
     */
    public function get_site_overrides() {
        $site_data = $this->get_site_data();
        return $site_data['overrides'] ?? [];
    }
    
    /**
     * Helper method to get raw site data.
     *
     * @return array
     */
    private function get_site_data() {
        return get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
    }
    
    /**
     * Update site options with override tracking.
     *
     * @param array $options
     * @param array $overrides
     * @return bool
     */
    public function update_site_options($options, $overrides = []) {
        if (!is_multisite()) {
            return update_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $options);
        }
        
        $site_data = [
            'options' => $options,
            'overrides' => $overrides,
            'last_sync' => current_time('timestamp')
        ];
        
        return update_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $site_data);
    }
    
    /**
     * Sync network settings to all sites.
     */
    public function sync_network_settings_to_sites() {
        if (!is_multisite()) {
            return;
        }
        
        global $wpdb;
        $network_options = $this->get_network_options();
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        $original_blog_id = get_current_blog_id();
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            $this->sync_network_settings_to_current_site($network_options);
        }
        
        switch_to_blog($original_blog_id);
    }
    
    /**
     * Sync network settings to current site.
     *
     * @param array|null $network_options
     */
    public function sync_network_settings_to_current_site($network_options = null) {
        if (!is_multisite()) {
            return;
        }
        
        if ($network_options === null) {
            $network_options = $this->get_network_options();
        }
        
        $site_data = $this->get_site_data();
        
        // Handle legacy format conversion
        if (!isset($site_data['options']) && !isset($site_data['overrides'])) {
            $legacy_options = $site_data;
            $site_data = [
                'options' => [],
                'overrides' => [],
                'last_sync' => 0
            ];
            
            // Convert legacy options to overrides
            $defaults = $this->get_default_options();
            foreach ($legacy_options as $key => $value) {
                if (isset($defaults[$key]) && $value !== $defaults[$key]) {
                    $site_data['options'][$key] = $value;
                    $site_data['overrides'][$key] = true;
                }
            }
        }
        
        $site_options = $site_data['options'] ?? [];
        $site_overrides = $site_data['overrides'] ?? [];
        
        // Update site options with network settings, preserving overrides
        $updated_options = [];
        $defaults = $this->get_default_options();
        
        foreach ($defaults as $key => $default_value) {
            if (isset($site_overrides[$key]) && $site_overrides[$key]) {
                // Keep the overridden value
                $updated_options[$key] = $site_options[$key] ?? $default_value;
            } else {
                // Use network setting or default
                $updated_options[$key] = $network_options[$key] ?? $default_value;
            }
        }
        
        $site_data['options'] = $updated_options;
        $site_data['last_sync'] = current_time('timestamp');
        
        update_option('wpgraphql_content_filter_options', $site_data);
        
        // Clear cache for this site
        $this->clear_current_site_cache();
    }
    
    /**
     * Get override status for a specific setting in multisite.
     *
     * @param string $setting_key
     * @return array
     */
    public function get_override_status($setting_key) {
        if (!is_multisite()) {
            return ['is_override' => false, 'network_value' => null, 'network_display' => null];
        }
        
        $network_options = $this->get_network_options();
        $site_overrides = $this->get_site_overrides();
        
        $is_override = isset($site_overrides[$setting_key]) && $site_overrides[$setting_key];
        $network_value = $network_options[$setting_key] ?? null;
        
        // Get display value for network setting
        $network_display = $network_value;
        if ($setting_key === 'filter_mode') {
            $mode_labels = [
                'none' => __('None (No filtering)', 'wpgraphql-content-filter'),
                'strip_all' => __('Strip All HTML', 'wpgraphql-content-filter'),
                'markdown' => __('Convert to Markdown', 'wpgraphql-content-filter'),
                'custom' => __('Custom Allowed Tags', 'wpgraphql-content-filter')
            ];
            $network_display = $mode_labels[$network_value] ?? $network_value;
        } elseif (is_bool($network_value)) {
            $network_display = $network_value ? __('Yes', 'wpgraphql-content-filter') : __('No', 'wpgraphql-content-filter');
        }
        
        return [
            'is_override' => $is_override,
            'network_value' => $network_value,
            'network_display' => $network_display
        ];
    }
    
    /**
     * Clear options cache.
     *
     * @param int|null $site_id
     */
    public function clear_options_cache($site_id = null) {
        if ($site_id !== null) {
            unset($this->options_cache[$site_id]);
        } else {
            $this->options_cache = [];
        }
        $this->network_options_cache = null;
    }
    
    /**
     * Clear cache for current site.
     */
    public function clear_current_site_cache() {
        $site_id = is_multisite() ? get_current_blog_id() : 0;
        $this->clear_options_cache($site_id);
    }
    
    /**
     * Handle option updates to clear cache.
     *
     * @param string $option_name
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function on_option_updated($option_name, $old_value, $new_value) {
        if ($option_name === 'wpgraphql_content_filter_options') {
            $this->clear_current_site_cache();
        }
    }
    
    /**
     * Handle site option updates to clear cache.
     *
     * @param string $option_name
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function on_site_option_updated($option_name, $old_value, $new_value) {
        if ($option_name === WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS) {
            $this->clear_options_cache(); // Clear all caches
        }
    }
    
    /**
     * Initialize default options on plugin activation.
     *
     * @param bool $network_wide
     */
    public function initialize_default_options($network_wide = false) {
        $default_options = $this->get_default_options();
        
        if (is_multisite() && $network_wide) {
            // Set network-wide default options
            $network_defaults = array_merge($default_options, [
                'allow_site_overrides' => true
            ]);
            
            add_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, $network_defaults);
            
            // Initialize all existing sites with proper sync structure
            $sites = get_sites(['fields' => 'ids', 'number' => 500]); // Limit for performance
            
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                
                // Only initialize if options don't exist
                if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                    // Initialize with synced network settings
                    $this->sync_network_settings_to_current_site($network_defaults);
                }
                
                restore_current_blog();
            }
        } else {
            // Single site activation or individual site in multisite
            if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                if (is_multisite()) {
                    // For individual site in multisite, sync with network settings
                    $this->sync_network_settings_to_current_site();
                } else {
                    // Single site installation
                    add_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $default_options);
                }
            }
        }
        
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear plugin cache
        $this->clear_options_cache();
    }

    /**
     * Check if filtering is enabled for a specific post type.
     *
     * @param string $post_type The post type to check.
     * @return bool True if filtering is enabled for the post type.
     */
    public function is_post_type_enabled($post_type) {
        $options = $this->get_options();
        $enabled_post_types = isset($options['enabled_post_types']) ? $options['enabled_post_types'] : ['post', 'page'];

        // Ensure enabled_post_types is an array
        if (!is_array($enabled_post_types)) {
            $enabled_post_types = ['post', 'page'];
        }

        return in_array($post_type, $enabled_post_types);
    }
}