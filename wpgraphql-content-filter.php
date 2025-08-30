<?php
/**
 * Plugin Name: WPGraphQL Content Filter
 * Plugin URI: https://github.com/gokepelemo/wpgraphql-content-filter/
 * Description: Filter and sanitize content in WPGraphQL and REST API responses with configurable HTML stripping, Markdown conversion, and custom tag allowlists. Requires WPGraphQL plugin.
 * Version: 1.0.6
 * Author: Goke Pelemo
 * Author URI: https://github.com/gokepelemo
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Requires Plugins: wp-graphql
 * Text Domain: wpgraphql-content-filter
 * Domain Path: /languages
 *
 * WPGraphQL Content Filter is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPGraphQL Content Filter is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
if (!defined('WPGRAPHQL_CONTENT_FILTER_VERSION')) {
    define('WPGRAPHQL_CONTENT_FILTER_VERSION', '1.0.6');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE')) {
    define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE', __FILE__);
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR')) {
    define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_URL')) {
    define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Define option names
if (!defined('WPGRAPHQL_CONTENT_FILTER_OPTIONS')) {
    define('WPGRAPHQL_CONTENT_FILTER_OPTIONS', 'wpgraphql_content_filter_options');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS')) {
    define('WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS', 'wpgraphql_content_filter_network_options');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION')) {
    define('WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION', 'wpgraphql_content_filter_version');
}

/**
 * Check for WPGraphQL dependency
 */
function wpgraphql_content_filter_check_dependencies() {
    // Check if WPGraphQL is active
    if (!class_exists('WPGraphQL') && !function_exists('graphql')) {
        add_action('admin_notices', 'wpgraphql_content_filter_dependency_notice');
        add_action('network_admin_notices', 'wpgraphql_content_filter_dependency_notice');
        
        // Deactivate the plugin if WPGraphQL is not available
        add_action('admin_init', 'wpgraphql_content_filter_deactivate_self');
        
        return false;
    }
    
    // Check WPGraphQL version if available
    if (class_exists('WPGraphQL') && defined('WPGRAPHQL_VERSION')) {
        if (version_compare(WPGRAPHQL_VERSION, '1.0.0', '<')) {
            add_action('admin_notices', 'wpgraphql_content_filter_version_notice');
            add_action('network_admin_notices', 'wpgraphql_content_filter_version_notice');
            return false;
        }
    }
    
    return true;
}

/**
 * Display dependency notice
 */
function wpgraphql_content_filter_dependency_notice() {
    $class = 'notice notice-error';
    $message = sprintf(
        /* translators: %1$s: Plugin name, %2$s: Required plugin name */
        __('%1$s requires %2$s to be installed and activated. Please install and activate %2$s first.', 'wpgraphql-content-filter'),
        '<strong>WPGraphQL Content Filter</strong>',
        '<strong>WPGraphQL</strong>'
    );
    
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
}

/**
 * Display version compatibility notice
 */
function wpgraphql_content_filter_version_notice() {
    $class = 'notice notice-warning';
    $current_version = defined('WPGRAPHQL_VERSION') ? WPGRAPHQL_VERSION : 'unknown';
    $message = sprintf(
        /* translators: %1$s: Plugin name, %2$s: Required plugin name, %3$s: Current version, %4$s: Required version */
        __('%1$s requires %2$s version %4$s or higher. You are currently running version %3$s. Please update %2$s.', 'wpgraphql-content-filter'),
        '<strong>WPGraphQL Content Filter</strong>',
        '<strong>WPGraphQL</strong>',
        $current_version,
        '1.0.0'
    );
    
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
}

/**
 * Deactivate this plugin if dependencies are not met
 */
function wpgraphql_content_filter_deactivate_self() {
    if (!class_exists('WPGraphQL') && !function_exists('graphql')) {
        deactivate_plugins(plugin_basename(__FILE__));
        
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

/**
 * Initialize plugin only if dependencies are met
 */
function wpgraphql_content_filter_init() {
    if (wpgraphql_content_filter_check_dependencies()) {
        WPGraphQL_Content_Filter::getInstance();
    }
}

// Initialize on plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', 'wpgraphql_content_filter_init');

/**
 * Main plugin class
 */
class WPGraphQL_Content_Filter {
    
    private static $instance = null;
    private $options_cache = [];
    private $network_options_cache = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        
        // Add admin hooks based on multisite status
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'add_network_admin_menu']);
            add_action('network_admin_edit_wpgraphql_content_filter_network', [$this, 'save_network_options']);
            add_action('admin_menu', [$this, 'add_site_admin_menu']);
        } else {
            add_action('admin_menu', [$this, 'add_admin_menu']);
        }
        
        add_action('admin_init', [$this, 'admin_init']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
        if (is_multisite()) {
            add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_network_plugin_action_links']);
        }
        
        // Add multisite new site hook
        if (is_multisite()) {
            add_action('wp_initialize_site', [$this, 'activate_new_site'], 900);
            
            // Add sync action for manual synchronization
            add_action('wp_ajax_wpgraphql_sync_network_settings', [$this, 'ajax_sync_network_settings']);
        }
        
        // Hook to clear cache when options are updated
        add_action('updated_option', [$this, 'on_option_updated'], 10, 3);
        add_action('updated_site_option', [$this, 'on_site_option_updated'], 10, 3);
        
        // Hook into WPGraphQL if it exists
        if (class_exists('WPGraphQL')) {
            add_action('graphql_register_types', [$this, 'register_graphql_hooks']);
        } else {
            // Lazy load WPGraphQL hooks if plugin is activated later
            add_action('plugins_loaded', [$this, 'maybe_register_graphql_hooks'], 20);
        }
        
        // Hook into REST API
        add_action('rest_api_init', [$this, 'register_rest_hooks']);
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate($network_wide = false) {
        $default_options = [
            'filter_mode' => 'none',
            'preserve_line_breaks' => true,
            'convert_headings' => true,
            'convert_links' => true,
            'convert_lists' => true,
            'convert_emphasis' => true,
            'custom_allowed_tags' => '',
            'apply_to_excerpt' => true,
            'apply_to_content' => true,
            'remove_plugin_data_on_uninstall' => false,
        ];
        
        if (is_multisite() && $network_wide) {
            // Set network-wide default options
            $network_defaults = array_merge($default_options, [
                'allow_site_overrides' => true,
                'enforce_network_settings' => false
            ]);
            
            add_site_option('wpgraphql_content_filter_network_options', $network_defaults);
            
            // Initialize all existing sites with proper sync structure
            $instance = self::getInstance();
            $sites = get_sites(['fields' => 'ids', 'number' => 500]); // Limit for performance
            
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                
                // Only initialize if options don't exist
                if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                    // Initialize with synced network settings
                    $instance->sync_network_settings_to_current_site($network_defaults);
                }
                
                restore_current_blog();
            }
        } else {
            // Single site activation or individual site in multisite
            if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                if (is_multisite()) {
                    // For individual site in multisite, sync with network settings
                    $instance = self::getInstance();
                    $instance->sync_network_settings_to_current_site();
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
        
        // Clear plugin cache if instance exists
        $instance = self::getInstance();
        $instance->clear_options_cache();
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear plugin cache if instance exists
        if (self::$instance !== null) {
            self::$instance->clear_options_cache();
        }
    }
    
    /**
     * Activate plugin for newly created multisite site
     */
    public function activate_new_site($site) {
        if (!is_plugin_active_for_network(plugin_basename(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE))) {
            return;
        }
        
        switch_to_blog($site->blog_id);
        
        // Only add default options if they don't exist
        if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
            // Initialize with network settings synced
            $this->sync_network_settings_to_current_site();
        }
        
        restore_current_blog();
    }
    
    /**
     * Handle option updates to clear cache
     */
    public function on_option_updated($option_name, $old_value, $new_value) {
        if ($option_name === 'wpgraphql_content_filter_options') {
            $this->clear_current_site_cache();
        }
    }
    
    /**
     * Handle site option updates to clear cache
     */
    public function on_site_option_updated($option_name, $old_value, $new_value) {
        if ($option_name === 'wpgraphql_content_filter_network_options') {
            $this->clear_options_cache(); // Clear all caches
        }
    }
    
    /**
     * Maybe register GraphQL hooks if WPGraphQL is loaded later
     */
    public function maybe_register_graphql_hooks() {
        if (class_exists('WPGraphQL')) {
            add_action('graphql_register_types', [$this, 'register_graphql_hooks']);
        }
    }
    
    /**
     * Plugin uninstall hook
     */
    public static function uninstall() {
        if (is_multisite()) {
            // Remove network options
            delete_site_option('wpgraphql_content_filter_network_options');
            
            // Remove options from all sites
            $sites = get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                delete_option('wpgraphql_content_filter_options');
                restore_current_blog();
            }
        } else {
            delete_option('wpgraphql_content_filter_options');
        }
        
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Register GraphQL field hooks for all WPGraphQL post types
     */
    public function register_graphql_hooks() {
        $post_types = $this->get_graphql_post_types();
        
        foreach ($post_types as $post_type) {
            $this->add_graphql_field_filters($post_type);
        }
    }
    
    /**
     * Register REST API response hooks for all public post types
     */
    public function register_rest_hooks() {
        $options = $this->get_options();
        
        // Only register REST API hooks if the setting is enabled
        if (!empty($options['apply_to_rest_api'])) {
            $post_types = $this->get_rest_post_types();
            
            foreach ($post_types as $post_type) {
                $this->add_rest_response_filter($post_type);
            }
        }
    }
    
    /**
     * Get post types available in WPGraphQL
     */
    private function get_graphql_post_types() {
        return \WPGraphQL::get_allowed_post_types();
    }
    
    /**
     * Get public post types for REST API
     */
    private function get_rest_post_types() {
        return get_post_types(['public' => true], 'names');
    }
    
    /**
     * Add GraphQL field filters for a specific post type
     */
    private function add_graphql_field_filters($post_type) {
        $post_type_object = get_post_type_object($post_type);
        if (!$post_type_object) {
            return;
        }
        
        add_filter("graphql_{$post_type}_object_content", [$this, 'filter_content'], 10, 3);
        add_filter("graphql_{$post_type}_object_excerpt", [$this, 'filter_excerpt'], 10, 3);
    }
    
    /**
     * Add REST API response filter for a specific post type
     */
    private function add_rest_response_filter($post_type) {
        add_filter("rest_prepare_{$post_type}", [$this, 'filter_rest_response'], 10, 3);
    }
    
    public function init() {
        load_plugin_textdomain('wpgraphql-content-filter', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check for plugin upgrades
        $this->check_plugin_upgrade();
    }
    
    /**
     * Check for plugin upgrades and handle version changes
     */
    private function check_plugin_upgrade() {
        $current_version = get_option(WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION, '0.0.0');
        
        if (version_compare($current_version, WPGRAPHQL_CONTENT_FILTER_VERSION, '<')) {
            $this->handle_plugin_upgrade($current_version);
            update_option(WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION, WPGRAPHQL_CONTENT_FILTER_VERSION);
        }
    }
    
    /**
     * Handle plugin upgrade tasks
     */
    private function handle_plugin_upgrade($old_version) {
        // Clear all caches on upgrade
        $this->clear_options_cache();
        
        // Version-specific upgrade tasks can be added here
        if (version_compare($old_version, '1.0.6', '<')) {
            // Tasks for upgrading to 1.0.5
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
    }
    
    /**
     * Get default plugin options
     */
    private function get_default_options() {
        return [
            'filter_mode' => 'none',
            'preserve_line_breaks' => true,
            'convert_headings' => true,
            'convert_links' => true,
            'convert_lists' => true,
            'convert_emphasis' => true,
            'custom_allowed_tags' => '',
            'apply_to_excerpt' => true,
            'apply_to_content' => true,
            'apply_to_rest_api' => true,
            'remove_plugin_data_on_uninstall' => false,
        ];
    }
    
    /**
     * Get plugin options with defaults, considering multisite configuration
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
     * Calculate effective options considering multisite hierarchy
     */
    private function calculate_effective_options() {
        $defaults = $this->get_default_options();
        
        if (!is_multisite()) {
            return get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $defaults);
        }
        
        $network_options = $this->get_network_options();
        
        // If network settings are enforced, use them exclusively
        if (!empty($network_options['enforce_network_settings'])) {
            return array_merge($defaults, $network_options);
        }
        
        // If site overrides are not allowed, use network settings
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
     * Get network options for multisite
     */
    public function get_network_options() {
        // Return cached network options if available
        if ($this->network_options_cache !== null) {
            return $this->network_options_cache;
        }
        
        $defaults = array_merge($this->get_default_options(), [
            'allow_site_overrides' => true,
            'enforce_network_settings' => false
        ]);
        
        $this->network_options_cache = get_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, $defaults);
        
        return $this->network_options_cache;
    }
    
    /**
     * Get site-specific options for multisite
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
     * Get site override settings
     */
    public function get_site_overrides() {
        $site_data = $this->get_site_data();
        return $site_data['overrides'] ?? [];
    }
    
    /**
     * Helper method to get raw site data
     */
    private function get_site_data() {
        return get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
    }
    
    /**
     * Update site options with override tracking
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
     * Sync network settings to all sites
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
     * Sync network settings to current site
     */
    private function sync_network_settings_to_current_site($network_options = null) {
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
     * Clear options cache
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
     * Clear cache for current site
     */
    public function clear_current_site_cache() {
        $site_id = is_multisite() ? get_current_blog_id() : 0;
        $this->clear_options_cache($site_id);
    }
    
    /**
     * Get override status for a specific setting in multisite
     */
    private function get_override_status($setting_key) {
        if (!is_multisite()) {
            return ['is_override' => false, 'network_value' => null, 'network_display' => null];
        }
        
        $network_options = $this->get_network_options();
        $site_overrides = $this->get_site_overrides();
        $site_options = $this->get_site_options();
        
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
     * Sanitize plugin options
     */
    public function sanitize_options($input) {
        $sanitized = [];
        $defaults = $this->get_default_options();
        
        // Handle multisite override tracking
        if (is_multisite() && isset($input['_overrides'])) {
            $overrides = $input['_overrides'];
            unset($input['_overrides']);
        }
        
        // Sanitize filter mode
        $allowed_modes = ['none', 'strip_all', 'markdown', 'custom'];
        $sanitized['filter_mode'] = in_array($input['filter_mode'] ?? '', $allowed_modes) 
            ? $input['filter_mode'] 
            : $defaults['filter_mode'];
        
        // Sanitize boolean options
        $boolean_fields = ['preserve_line_breaks', 'convert_headings', 'convert_links', 'convert_lists', 'convert_emphasis', 'apply_to_excerpt', 'apply_to_content', 'apply_to_rest_api', 'remove_plugin_data_on_uninstall'];
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = !empty($input[$field]);
        }
        
        // Sanitize custom allowed tags
        $sanitized['custom_allowed_tags'] = sanitize_text_field($input['custom_allowed_tags'] ?? '');
        
        // For multisite, handle override tracking in the actual save process
        if (is_multisite() && isset($overrides)) {
            // This will be handled by the settings save callback
            add_filter('pre_update_option_wpgraphql_content_filter_options', function($value, $old_value, $option) use ($sanitized, $overrides) {
                return $this->save_site_options_with_overrides($sanitized, $overrides);
            }, 10, 3);
        }
        
        return $sanitized;
    }
    
    /**
     * Save site options with override tracking
     */
    private function save_site_options_with_overrides($options, $overrides) {
        if (!is_multisite()) {
            return $options;
        }
        
        $network_options = $this->get_network_options();
        $processed_overrides = [];
        
        // Determine which options are actually overrides
        foreach ($options as $key => $value) {
            $network_value = $network_options[$key] ?? null;
            $is_override = isset($overrides[$key]) && $overrides[$key] && $value !== $network_value;
            $processed_overrides[$key] = $is_override;
        }
        
        $site_data = [
            'options' => $options,
            'overrides' => $processed_overrides,
            'last_sync' => current_time('timestamp')
        ];
        
        // Clear cache after save
        add_action('updated_option', function($option_name) {
            if ($option_name === 'wpgraphql_content_filter_options') {
                $this->clear_current_site_cache();
            }
        });
        
        return $site_data;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('WPGraphQL Content Filter', 'wpgraphql-content-filter'),
            __('GraphQL Content Filter', 'wpgraphql-content-filter'),
            'manage_options',
            'wpgraphql-content-filter',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Add network admin menu
     */
    public function add_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('WPGraphQL Content Filter Network Settings', 'wpgraphql-content-filter'),
            __('GraphQL Content Filter', 'wpgraphql-content-filter'),
            'manage_network_options',
            'wpgraphql-content-filter-network',
            [$this, 'network_admin_page']
        );
    }
    
    /**
     * Add site admin menu for multisite
     */
    public function add_site_admin_menu() {
        $network_options = $this->get_network_options();
        
        // Only hide site menu if network settings are explicitly enforced
        if (empty($network_options['enforce_network_settings'])) {
            add_options_page(
                __('WPGraphQL Content Filter', 'wpgraphql-content-filter'),
                __('GraphQL Content Filter', 'wpgraphql-content-filter'),
                'manage_options',
                'wpgraphql-content-filter',
                [$this, 'admin_page']
            );
        }
    }
    
    /**
     * Initialize admin settings
     */
    public function admin_init() {
        // Security check
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Don't register site settings if network settings are explicitly enforced
        if (is_multisite()) {
            $network_options = $this->get_network_options();
            if (!empty($network_options['enforce_network_settings'])) {
                return;
            }
        }
        
        register_setting(
            'wpgraphql_content_filter_group', 
            WPGRAPHQL_CONTENT_FILTER_OPTIONS,
            [
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => $this->get_default_options()
            ]
        );
        
        add_settings_section(
            'wpgraphql_content_filter_section',
            __('Content Filter Settings', 'wpgraphql-content-filter'),
            [$this, 'settings_section_callback'],
            'wpgraphql-content-filter'
        );
        
        // Filter Mode
        add_settings_field(
            'filter_mode',
            __('Filter Mode', 'wpgraphql-content-filter'),
            [$this, 'filter_mode_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section'
        );
        
        // Apply to Content
        add_settings_field(
            'apply_to_content',
            __('Apply to Content Field', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'apply_to_content', 'description' => 'Filter the main content field']
        );
        
        // Apply to Excerpt
        add_settings_field(
            'apply_to_excerpt',
            __('Apply to Excerpt Field', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'apply_to_excerpt', 'description' => 'Filter the excerpt field']
        );
        
        // Apply to REST API
        add_settings_field(
            'apply_to_rest_api',
            __('Apply to REST API', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'apply_to_rest_api', 'description' => 'Enable content filtering for WordPress REST API responses']
        );
        
        // Preserve Line Breaks
        add_settings_field(
            'preserve_line_breaks',
            __('Preserve Line Breaks', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'preserve_line_breaks', 'description' => 'Convert block elements to line breaks']
        );
        
        // Markdown options (only show when markdown mode is selected)
        add_settings_field(
            'convert_headings',
            __('Convert Headings to Markdown', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'convert_headings', 'description' => 'Convert H1-H6 tags to # syntax']
        );
        
        add_settings_field(
            'convert_links',
            __('Convert Links to Markdown', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'convert_links', 'description' => 'Convert <a> tags to [text](url) syntax']
        );
        
        add_settings_field(
            'convert_lists',
            __('Convert Lists to Markdown', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'convert_lists', 'description' => 'Convert <ul>/<ol> to - syntax']
        );
        
        add_settings_field(
            'convert_emphasis',
            __('Convert Emphasis to Markdown', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'convert_emphasis', 'description' => 'Convert <strong>/<em> to **bold** and _italic_']
        );
        
        // Custom allowed tags (for custom mode)
        add_settings_field(
            'custom_allowed_tags',
            __('Custom Allowed Tags', 'wpgraphql-content-filter'),
            [$this, 'textarea_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'custom_allowed_tags', 'description' => 'Comma-separated list of allowed HTML tags (e.g., p,strong,em,a)']
        );
        
        // Plugin data cleanup option
        add_settings_field(
            'remove_plugin_data_on_uninstall',
            __('Remove Plugin Data on Uninstall', 'wpgraphql-content-filter'),
            [$this, 'checkbox_callback'],
            'wpgraphql-content-filter',
            'wpgraphql_content_filter_section',
            ['field' => 'remove_plugin_data_on_uninstall', 'description' => 'When enabled, all plugin settings and cached data will be permanently deleted when the plugin is uninstalled. <strong>Warning:</strong> This action cannot be undone.']
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('Configure how content is filtered in WPGraphQL responses.', 'wpgraphql-content-filter') . '</p>';
    }
    
    /**
     * Filter mode callback
     */
    public function filter_mode_callback() {
        $options = $this->get_options();
        $current = $options['filter_mode'];
        ?>
        <select name="wpgraphql_content_filter_options[filter_mode]" id="filter_mode">
            <option value="none" <?php selected($current, 'none'); ?>><?php _e('None (No filtering)', 'wpgraphql-content-filter'); ?></option>
            <option value="strip_all" <?php selected($current, 'strip_all'); ?>><?php _e('Strip All HTML', 'wpgraphql-content-filter'); ?></option>
            <option value="markdown" <?php selected($current, 'markdown'); ?>><?php _e('Convert to Markdown', 'wpgraphql-content-filter'); ?></option>
            <option value="custom" <?php selected($current, 'custom'); ?>><?php _e('Custom Allowed Tags', 'wpgraphql-content-filter'); ?></option>
        </select>
        <p class="description"><?php _e('Choose how to filter HTML content in GraphQL responses.', 'wpgraphql-content-filter'); ?></p>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterMode = document.getElementById('filter_mode');
            const markdownOptions = document.querySelectorAll('[data-filter-mode="markdown"]');
            const customOptions = document.querySelectorAll('[data-filter-mode="custom"]');
            
            function toggleOptions() {
                const mode = filterMode.value;
                markdownOptions.forEach(el => el.style.display = mode === 'markdown' ? 'table-row' : 'none');
                customOptions.forEach(el => el.style.display = mode === 'custom' ? 'table-row' : 'none');
            }
            
            filterMode.addEventListener('change', toggleOptions);
            toggleOptions();
        });
        </script>
        <?php
    }
    
    /**
     * Checkbox callback
     */
    public function checkbox_callback($args) {
        $options = $this->get_options();
        $checked = isset($options[$args['field']]) ? $options[$args['field']] : false;
        $override_info = $this->get_override_status($args['field']);
        ?>
        <label>
            <input type="checkbox" name="wpgraphql_content_filter_options[<?php echo $args['field']; ?>]" value="1" <?php checked($checked, 1); ?> />
            <?php echo $args['description']; ?>
        </label>
        <?php if (is_multisite()): ?>
            <input type="hidden" name="wpgraphql_content_filter_options[_overrides][<?php echo $args['field']; ?>]" value="<?php echo $override_info['is_override'] ? '1' : '0'; ?>" />
            <?php if (!empty($override_info['network_value']) || $override_info['network_value'] === false): ?>
                <p class="description">
                    <?php if ($override_info['is_override']): ?>
                        <span class="override-indicator override"><?php esc_html_e('🔄 Overridden', 'wpgraphql-content-filter'); ?></span>
                        <?php printf(__('Network setting: %s', 'wpgraphql-content-filter'), '<code>' . esc_html($override_info['network_display']) . '</code>'); ?>
                    <?php else: ?>
                        <span class="override-indicator inherited"><?php esc_html_e('✓ Inherited from network', 'wpgraphql-content-filter'); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Textarea callback
     */
    public function textarea_callback($args) {
        $options = $this->get_options();
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        $override_info = $this->get_override_status($args['field']);
        ?>
        <textarea name="wpgraphql_content_filter_options[<?php echo $args['field']; ?>]" rows="3" cols="50"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php echo $args['description']; ?></p>
        <?php if (is_multisite()): ?>
            <input type="hidden" name="wpgraphql_content_filter_options[_overrides][<?php echo $args['field']; ?>]" value="<?php echo $override_info['is_override'] ? '1' : '0'; ?>" />
            <?php if (!empty($override_info['network_value'])): ?>
                <p class="description">
                    <?php if ($override_info['is_override']): ?>
                        <span class="override-indicator override"><?php esc_html_e('🔄 Overridden', 'wpgraphql-content-filter'); ?></span>
                        <?php printf(__('Network setting: %s', 'wpgraphql-content-filter'), '<code>' . esc_html($override_info['network_display']) . '</code>'); ?>
                    <?php else: ?>
                        <span class="override-indicator inherited"><?php esc_html_e('✓ Inherited from network', 'wpgraphql-content-filter'); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpgraphql-content-filter'));
        }
        
        $is_multisite = is_multisite();
        $network_options = $is_multisite ? $this->get_network_options() : [];
        $show_settings_form = true;
        
        if ($is_multisite) {
            if (!empty($network_options['enforce_network_settings'])) {
                $show_settings_form = false;
            } elseif (empty($network_options['allow_site_overrides'])) {
                $show_settings_form = false;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!class_exists('WPGraphQL')): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('WPGraphQL plugin is not active. GraphQL filtering requires WPGraphQL to be installed and activated. REST API filtering will still work.', 'wpgraphql-content-filter'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($is_multisite && !empty($network_options['enforce_network_settings'])): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('Content filtering settings are managed at the network level and cannot be overridden on individual sites.', 'wpgraphql-content-filter'); ?></p>
                    <?php if (current_user_can('manage_network_options')): ?>
                        <p><a href="<?php echo esc_url(network_admin_url('settings.php?page=wpgraphql-content-filter-network')); ?>"><?php esc_html_e('Manage Network Settings', 'wpgraphql-content-filter'); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php elseif ($is_multisite && empty($network_options['allow_site_overrides'])): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('Site-level overrides are not allowed. Settings are inherited from the network configuration.', 'wpgraphql-content-filter'); ?></p>
                    <?php if (current_user_can('manage_network_options')): ?>
                        <p><a href="<?php echo esc_url(network_admin_url('settings.php?page=wpgraphql-content-filter-network')); ?>"><?php esc_html_e('Manage Network Settings', 'wpgraphql-content-filter'); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php settings_errors(); ?>
            
            <?php if (is_multisite() && ($this->get_network_options()['allow_site_overrides'] ?? false)): ?>
                <style>
                    .override-indicator.override { color: #d63384; font-weight: bold; }
                    .override-indicator.inherited { color: #198754; font-weight: bold; }
                    .wpgraphql-content-filter .description .override-indicator {
                        display: inline-block;
                        margin-right: 8px;
                    }
                </style>
            <?php endif; ?>
            
            <?php if ($show_settings_form): ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wpgraphql_content_filter_group');
                    do_settings_sections('wpgraphql-content-filter');
                    submit_button(__('Save Settings', 'wpgraphql-content-filter'), 'primary', 'submit', true, ['id' => 'submit']);
                    ?>
                </form>
            <?php else: ?>
                <div class="card">
                    <h2><?php esc_html_e('Current Settings', 'wpgraphql-content-filter'); ?></h2>
                    <p><?php esc_html_e('These settings are currently being managed at the network level:', 'wpgraphql-content-filter'); ?></p>
                    <?php $this->display_current_settings(); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Usage Examples', 'wpgraphql-content-filter'); ?></h2>
                <h3><?php _e('GraphQL Query', 'wpgraphql-content-filter'); ?></h3>
                <pre><code>query GetPosts {
  posts {
    nodes {
      id
      title
      content   # Filtered based on plugin settings
      excerpt   # Also filtered if enabled
    }
  }
}</code></pre>
            </div>
        </div>
        
        <style>
        .card { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-top: 20px; }
        .card h2 { margin-top: 0; }
        pre { background: #f6f7f7; padding: 15px; border-left: 4px solid #0073aa; overflow-x: auto; }
        </style>
        <?php
    }
    
    /**
     * Get effective options (considering multisite inheritance)
     */
    private function get_effective_options() {
        if (!is_multisite()) {
            return get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $this->get_default_options());
        }
        
        $network_options = $this->get_network_options();
        $site_options = $this->get_site_data();
        
        // If network settings are enforced, use network options
        if (!empty($network_options['enforce_network_settings'])) {
            return array_merge($this->get_default_options(), $network_options);
        }
        
        // If site overrides are not allowed, use network options
        if (empty($network_options['allow_site_overrides'])) {
            return array_merge($this->get_default_options(), $network_options);
        }
        
        // Otherwise, merge network and site options (site options take precedence)
        return array_merge($this->get_default_options(), $network_options, $site_options);
    }
    
    /**
     * Display current settings (read-only)
     */
    private function display_current_settings() {
        $current_settings = $this->get_effective_options();
        
        echo '<table class="form-table">';
        
        // Filter Mode
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Filter Mode', 'wpgraphql-content-filter') . '</th>';
        echo '<td>';
        $modes = [
            'disabled' => __('Disabled', 'wpgraphql-content-filter'),
            'strip_html' => __('Strip HTML Tags', 'wpgraphql-content-filter'),
            'summary' => __('Generate Summary', 'wpgraphql-content-filter'),
            'custom' => __('Custom Filter', 'wpgraphql-content-filter')
        ];
        echo esc_html($modes[$current_settings['filter_mode']] ?? $current_settings['filter_mode']);
        echo '</td>';
        echo '</tr>';
        
        // Summary Length
        if ($current_settings['filter_mode'] === 'summary') {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Summary Length', 'wpgraphql-content-filter') . '</th>';
            echo '<td>' . esc_html($current_settings['summary_length']) . ' ' . esc_html__('words', 'wpgraphql-content-filter') . '</td>';
            echo '</tr>';
        }
        
        // Custom Filter
        if ($current_settings['filter_mode'] === 'custom' && !empty($current_settings['custom_filter'])) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Custom Filter Function', 'wpgraphql-content-filter') . '</th>';
            echo '<td><code>' . esc_html($current_settings['custom_filter']) . '</code></td>';
            echo '</tr>';
        }
        
        // Post Types
        if (!empty($current_settings['post_types'])) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Post Types', 'wpgraphql-content-filter') . '</th>';
            echo '<td>';
            $post_type_objects = get_post_types(['public' => true], 'objects');
            $enabled_types = [];
            foreach ($current_settings['post_types'] as $post_type) {
                if (isset($post_type_objects[$post_type])) {
                    $enabled_types[] = $post_type_objects[$post_type]->labels->name;
                }
            }
            echo esc_html(implode(', ', $enabled_types));
            echo '</td>';
            echo '</tr>';
        }
        
        // Content Fields
        if (!empty($current_settings['content_fields'])) {
            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Content Fields', 'wpgraphql-content-filter') . '</th>';
            echo '<td>' . esc_html(implode(', ', $current_settings['content_fields'])) . '</td>';
            echo '</tr>';
        }
        
        // Debug Mode
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Debug Mode', 'wpgraphql-content-filter') . '</th>';
        echo '<td>' . ($current_settings['debug_mode'] ? esc_html__('Enabled', 'wpgraphql-content-filter') : esc_html__('Disabled', 'wpgraphql-content-filter')) . '</td>';
        echo '</tr>';
        
        echo '</table>';
    }
    
    /**
     * Network admin page
     */
    public function network_admin_page() {
        // Security check
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wpgraphql-content-filter'));
        }
        
        $network_options = $this->get_network_options();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WPGraphQL Content Filter Network Settings', 'wpgraphql-content-filter'); ?></h1>
            
            <?php if (!class_exists('WPGraphQL')): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('WPGraphQL plugin is not active. GraphQL filtering requires WPGraphQL to be installed and activated. REST API filtering will still work.', 'wpgraphql-content-filter'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('Network settings saved.', 'wpgraphql-content-filter'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=wpgraphql_content_filter_network')); ?>">
                <?php wp_nonce_field('wpgraphql_content_filter_network_nonce'); ?>
                
                <div class="card">
                    <h2><?php esc_html_e('Network Configuration', 'wpgraphql-content-filter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Allow Site Overrides', 'wpgraphql-content-filter'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="allow_site_overrides" value="1" <?php checked(!empty($network_options['allow_site_overrides'])); ?> />
                                    <?php esc_html_e('Allow individual sites to override these network settings', 'wpgraphql-content-filter'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enforce Network Settings', 'wpgraphql-content-filter'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enforce_network_settings" value="1" <?php checked(!empty($network_options['enforce_network_settings'])); ?> />
                                    <?php esc_html_e('Force all sites to use these network settings (overrides site settings)', 'wpgraphql-content-filter'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card">
                    <h2><?php esc_html_e('Default Content Filter Settings', 'wpgraphql-content-filter'); ?></h2>
                    <?php $this->render_filter_settings($network_options, 'network'); ?>
                </div>
                
                <?php submit_button(__('Save Network Settings', 'wpgraphql-content-filter')); ?>
            </form>
            
            <div class="card">
                <h2><?php esc_html_e('Synchronization', 'wpgraphql-content-filter'); ?></h2>
                <p><?php esc_html_e('Network settings are automatically synced to all sites when saved. Use the button below to manually sync current settings to all sites.', 'wpgraphql-content-filter'); ?></p>
                <button type="button" id="sync-network-settings" class="button button-secondary">
                    <?php esc_html_e('Sync Settings to All Sites', 'wpgraphql-content-filter'); ?>
                </button>
                <div id="sync-result" style="margin-top: 10px;"></div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const syncButton = document.getElementById('sync-network-settings');
                    const syncResult = document.getElementById('sync-result');
                    
                    syncButton.addEventListener('click', function() {
                        syncButton.disabled = true;
                        syncButton.textContent = '<?php esc_html_e('Syncing...', 'wpgraphql-content-filter'); ?>';
                        
                        const formData = new FormData();
                        formData.append('action', 'wpgraphql_sync_network_settings');
                        formData.append('_wpnonce', '<?php echo wp_create_nonce('wpgraphql_sync_nonce'); ?>');
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                syncResult.innerHTML = '<div class="notice notice-success inline"><p>' + data.data.message + '</p></div>';
                            } else {
                                syncResult.innerHTML = '<div class="notice notice-error inline"><p>' + (data.data.message || '<?php esc_html_e('Sync failed', 'wpgraphql-content-filter'); ?>') + '</p></div>';
                            }
                        })
                        .catch(error => {
                            syncResult.innerHTML = '<div class="notice notice-error inline"><p><?php esc_html_e('Sync failed: Network error', 'wpgraphql-content-filter'); ?></p></div>';
                        })
                        .finally(() => {
                            syncButton.disabled = false;
                            syncButton.textContent = '<?php esc_html_e('Sync Settings to All Sites', 'wpgraphql-content-filter'); ?>';
                        });
                    });
                });
                </script>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save network options
     */
    public function save_network_options() {
        // Security check
        if (!current_user_can('manage_network_options') || !wp_verify_nonce($_POST['_wpnonce'], 'wpgraphql_content_filter_network_nonce')) {
            wp_die(__('Security check failed.', 'wpgraphql-content-filter'));
        }
        
        $network_options = $this->sanitize_network_options($_POST);
        $updated = update_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, $network_options);
        
        if ($updated) {
            // Clear all option caches since network settings affect all sites
            $this->clear_options_cache();
            
            // Sync network settings to all sites
            $this->sync_network_settings_to_sites();
            
            // Clear WordPress object cache
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
        
        wp_redirect(add_query_arg(['updated' => 'true'], network_admin_url('settings.php?page=wpgraphql-content-filter-network')));
        exit;
    }
    
    /**
     * Sanitize network options
     */
    public function sanitize_network_options($input) {
        $sanitized = $this->sanitize_options($input);
        
        // Add network-specific options
        $sanitized['allow_site_overrides'] = !empty($input['allow_site_overrides']);
        $sanitized['enforce_network_settings'] = !empty($input['enforce_network_settings']);
        
        return $sanitized;
    }
    
    /**
     * AJAX handler to sync network settings to all sites
     */
    public function ajax_sync_network_settings() {
        // Security check
        if (!current_user_can('manage_network_options') || !wp_verify_nonce($_POST['_wpnonce'], 'wpgraphql_sync_nonce')) {
            wp_die(__('Security check failed.', 'wpgraphql-content-filter'));
        }
        
        $this->sync_network_settings_to_sites();
        
        wp_send_json_success([
            'message' => __('Network settings synchronized to all sites successfully.', 'wpgraphql-content-filter')
        ]);
    }
    
    /**
     * Render filter settings (reusable for both network and site admin)
     */
    public function render_filter_settings($options, $context = 'site') {
        $prefix = ($context === 'network') ? '' : 'wpgraphql_content_filter_options';
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Filter Mode', 'wpgraphql-content-filter'); ?></th>
                <td>
                    <select name="<?php echo $context === 'network' ? 'filter_mode' : $prefix . '[filter_mode]'; ?>" id="filter_mode">
                        <option value="none" <?php selected($options['filter_mode'], 'none'); ?>><?php esc_html_e('None (No filtering)', 'wpgraphql-content-filter'); ?></option>
                        <option value="strip_all" <?php selected($options['filter_mode'], 'strip_all'); ?>><?php esc_html_e('Strip All HTML', 'wpgraphql-content-filter'); ?></option>
                        <option value="markdown" <?php selected($options['filter_mode'], 'markdown'); ?>><?php esc_html_e('Convert to Markdown', 'wpgraphql-content-filter'); ?></option>
                        <option value="custom" <?php selected($options['filter_mode'], 'custom'); ?>><?php esc_html_e('Custom Allowed Tags', 'wpgraphql-content-filter'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose how to filter content in API responses.', 'wpgraphql-content-filter'); ?></p>
                </td>
            </tr>
            <!-- Add more filter settings as needed -->
        </table>
        <?php
    }
    
    /**
     * Universal content filter - handles both content and excerpt
     */
    public function filter_field_content($content, $field_type = 'content') {
        // Early return for empty content
        if (empty($content) || !is_string($content)) {
            return $content;
        }
        
        // Get cached options for better performance
        $options = $this->get_options();
        
        // Early return if filtering is disabled
        if ($options['filter_mode'] === 'none') {
            return $content;
        }
        
        // Check if filtering is enabled for this field type
        $field_setting = ($field_type === 'content') ? 'apply_to_content' : 'apply_to_excerpt';
        if (empty($options[$field_setting])) {
            return $content;
        }
        
        try {
            return $this->apply_filter($content, $options);
        } catch (Exception $e) {
            // Log error in debug mode and return original content
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'WPGraphQL Content Filter Error [%s]: %s',
                    $field_type,
                    $e->getMessage()
                ));
            }
            return $content;
        }
    }
    
    /**
     * GraphQL content field filter
     */
    public function filter_content($content, $post, $context) {
        return $this->filter_field_content($content, 'content');
    }
    
    /**
     * GraphQL excerpt field filter
     */
    public function filter_excerpt($excerpt, $post, $context) {
        return $this->filter_field_content($excerpt, 'excerpt');
    }
    
    /**
     * REST API response filter
     */
    public function filter_rest_response($response, $post, $request) {
        // Filter content field if present
        if (isset($response->data['content']['rendered'])) {
            $response->data['content']['rendered'] = $this->filter_field_content(
                $response->data['content']['rendered'], 
                'content'
            );
        }
        
        // Filter excerpt field if present
        if (isset($response->data['excerpt']['rendered'])) {
            $response->data['excerpt']['rendered'] = $this->filter_field_content(
                $response->data['excerpt']['rendered'], 
                'excerpt'
            );
        }
        
        return $response;
    }
    
    /**
     * Apply the actual filtering based on mode
     */
    private function apply_filter($content, $options) {
        // Early return for empty content
        if (empty($content) || !is_string($content)) {
            return $content;
        }
        
        // Cache frequently used options to avoid array lookups
        $filter_mode = $options['filter_mode'] ?? 'none';
        
        switch ($filter_mode) {
            case 'strip_all':
                return $this->strip_all_tags($content, $options['preserve_line_breaks'] ?? true);
                
            case 'markdown':
                return $this->convert_to_markdown($content, $options);
                
            case 'custom':
                $allowed_tags = $options['custom_allowed_tags'] ?? '';
                return $this->strip_custom_tags($content, $allowed_tags);
                
            case 'none':
            default:
                return $content;
        }
    }
    
    /**
     * Strip all HTML tags
     */
    private function strip_all_tags($content, $preserve_line_breaks = true) {
        // Convert HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        if ($preserve_line_breaks) {
            // Convert block elements to line breaks
            $content = preg_replace('/<\/?(p|div|br|h[1-6]|li|ul|ol)[^>]*>/i', "\n", $content);
            $content = preg_replace('/\n+/', "\n", $content);
        }
        
        // Strip all HTML tags
        $content = wp_strip_all_tags($content);
        
        // Clean up whitespace
        $content = trim($content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        return $content;
    }
    
    /**
     * Convert HTML to Markdown
     */
    private function convert_to_markdown($content, $options) {
        $replacements = [];
        
        if ($options['convert_headings']) {
            $replacements = array_merge($replacements, [
                '/<h1[^>]*>(.*?)<\/h1>/i' => '# $1',
                '/<h2[^>]*>(.*?)<\/h2>/i' => '## $1',
                '/<h3[^>]*>(.*?)<\/h3>/i' => '### $1',
                '/<h4[^>]*>(.*?)<\/h4>/i' => '#### $1',
                '/<h5[^>]*>(.*?)<\/h5>/i' => '##### $1',
                '/<h6[^>]*>(.*?)<\/h6>/i' => '###### $1',
            ]);
        }
        
        if ($options['convert_emphasis']) {
            $replacements = array_merge($replacements, [
                '/<strong[^>]*>(.*?)<\/strong>/i' => '**$1**',
                '/<b[^>]*>(.*?)<\/b>/i' => '**$1**',
                '/<em[^>]*>(.*?)<\/em>/i' => '_$1_',
                '/<i[^>]*>(.*?)<\/i>/i' => '_$1_',
            ]);
        }
        
        if ($options['convert_links']) {
            $replacements = array_merge($replacements, [
                '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i' => '[$2]($1)',
            ]);
        }
        
        if ($options['convert_lists']) {
            $replacements = array_merge($replacements, [
                '/<ul[^>]*>/i' => '',
                '/<\/ul>/i' => '',
                '/<ol[^>]*>/i' => '',
                '/<\/ol>/i' => '',
                '/<li[^>]*>(.*?)<\/li>/i' => '- $1',
            ]);
        }
        
        // Basic paragraph and line break handling
        $replacements = array_merge($replacements, [
            '/<p[^>]*>/i' => '',
            '/<\/p>/i' => "\n\n",
            '/<br[^>]*>/i' => "\n",
            '/<code[^>]*>(.*?)<\/code>/i' => '`$1`',
            '/<pre[^>]*>(.*?)<\/pre>/i' => "```\n$1\n```",
        ]);
        
        foreach ($replacements as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Strip remaining HTML tags
        $content = wp_strip_all_tags($content);
        
        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Strip tags except allowed ones
     */
    private function strip_custom_tags($content, $allowed_tags) {
        if (empty($allowed_tags)) {
            return wp_strip_all_tags($content);
        }
        
        // Parse allowed tags
        $tags = array_map('trim', explode(',', $allowed_tags));
        $allowed = '<' . implode('><', $tags) . '>';
        
        return strip_tags($content, $allowed);
    }
    
    /**
     * Add plugin action links for single site
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wpgraphql-content-filter')),
            __('Settings', 'wpgraphql-content-filter')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add plugin action links for network admin
     */
    public function add_network_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(network_admin_url('admin.php?page=wpgraphql-content-filter')),
            __('Settings', 'wpgraphql-content-filter')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'activate']);
register_deactivation_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'deactivate']);
register_uninstall_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'uninstall']);
