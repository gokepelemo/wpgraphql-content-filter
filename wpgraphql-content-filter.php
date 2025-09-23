<?php
/**
 * Plugin Name: WPGraphQL Content Filter
 * Plugin URI: https://github.com/gokepelemo/wpgraphql-content-filter/
 * Description: Filter and sanitize content in WPGraphQL and REST API responses with configurable HTML stripping, Markdown conversion, and custom tag allowlists. Requires WPGraphQL plugin.
 * Version: 2.1.20
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
    define('WPGRAPHQL_CONTENT_FILTER_VERSION', '2.1.20');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE')) {
    define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE', __FILE__);
}

/**
 * Define WordPress-dependent constants when WordPress functions are available
 */
function wpgraphql_content_filter_define_constants() {
    if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR')) {
        define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR', plugin_dir_path(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE));
    }
    if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_URL')) {
        define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_URL', plugin_dir_url(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE));
    }
}

// Define WordPress-dependent constants when WordPress is ready
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'wpgraphql_content_filter_define_constants', 1);
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
    // Note: Plugin now works with REST API even without WPGraphQL
    // Only show warning notices, don't deactivate the plugin

    if (!class_exists('WPGraphQL') && !function_exists('graphql')) {
        add_action('admin_notices', 'wpgraphql_content_filter_dependency_notice');
        add_action('network_admin_notices', 'wpgraphql_content_filter_dependency_notice');

        // Don't deactivate - let the plugin work with REST API only
        // add_action('admin_init', 'wpgraphql_content_filter_deactivate_self');

        // Return true to allow plugin to load for REST API functionality
        return true;
    }

    // Check WPGraphQL version if available
    if (class_exists('WPGraphQL') && defined('WPGRAPHQL_VERSION')) {
        if (version_compare(WPGRAPHQL_VERSION, '1.0.0', '<')) {
            add_action('admin_notices', 'wpgraphql_content_filter_version_notice');
            add_action('network_admin_notices', 'wpgraphql_content_filter_version_notice');
            // Still allow plugin to work with REST API
            return true;
        }
    }

    return true;
}

/**
 * Display dependency notice
 */
function wpgraphql_content_filter_dependency_notice() {
    $class = 'notice notice-warning';
    $message = sprintf(
        /* translators: %1$s: Plugin name, %2$s: Required plugin name */
        __('%1$s works best with %2$s installed. While the plugin will work with WordPress REST API, install %2$s for GraphQL functionality.', 'wpgraphql-content-filter'),
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGraphQL Content Filter: Initializing plugin');
        }
        WPGraphQL_Content_Filter::getInstance();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPGraphQL Content Filter: Plugin initialized successfully');
        }
    }
}

// Initialize on plugins_loaded to ensure all plugins are loaded
if (function_exists('add_action')) {
    add_action('plugins_loaded', 'wpgraphql_content_filter_init');
}

/**
 * Main plugin class - refactored to use modular architecture
 */
class WPGraphQL_Content_Filter {
    
    /**
     * Singleton instance.
     *
     * @var WPGraphQL_Content_Filter
     */
    private static $instance = null;

    /**
     * Options Manager instance.
     *
     * @var WPGraphQL_Content_Filter_Options_Manager
     */
    private $options_manager;

    /**
     * Content Filter instance.
     *
     * @var WPGraphQL_Content_Filter_Content_Filter
     */
    private $content_filter;

    /**
     * GraphQL Hook Manager instance.
     *
     * @var WPGraphQL_Content_Filter_GraphQL_Hook_Manager
     */
    private $graphql_hook_manager;

    /**
     * REST Hook Manager instance.
     *
     * @var WPGraphQL_Content_Filter_REST_Hook_Manager
     */
    private $rest_hook_manager;

    /**
     * Admin Manager instance.
     *
     * @var WPGraphQL_Content_Filter_Admin
     */
    private $admin_manager;

    /**
     * Get singleton instance.
     *
     * @return WPGraphQL_Content_Filter
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_managers();
        $this->init_hooks();
    }

    /**
     * Load all required class files.
     *
     * @return void
     */
    private function load_dependencies() {
        // Get plugin directory - use constant if available, otherwise calculate it
        $plugin_dir = defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR')
            ? WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR
            : plugin_dir_path(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE);

        // Load Composer autoloader for external dependencies
        $autoload_file = $plugin_dir . 'vendor/autoload.php';
        if (file_exists($autoload_file)) {
            require_once $autoload_file;
        }

        $includes_dir = $plugin_dir . 'includes/';

        require_once $includes_dir . 'interface-wpgraphql-content-filter-hook-manager.php';
        require_once $includes_dir . 'class-wpgraphql-content-filter-options-manager.php';
        require_once $includes_dir . 'class-wpgraphql-content-filter-content-filter.php';
        require_once $includes_dir . 'class-wpgraphql-content-filter-graphql-hook-manager.php';
        require_once $includes_dir . 'class-wpgraphql-content-filter-rest-hook-manager.php';
        require_once $includes_dir . 'class-wpgraphql-content-filter-admin.php';
    }

    /**
     * Load Composer autoloader for HTML processing libraries.
     *
     * @return void
     */
    private function load_composer_autoloader() {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        // Get plugin directory - use constant if available, otherwise calculate it
        $plugin_dir = defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR')
            ? WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR
            : plugin_dir_path(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE);

        $autoload_file = $plugin_dir . 'vendor/autoload.php';

        if (file_exists($autoload_file)) {
            require_once $autoload_file;
            $loaded = true;
        }
    }

    /**
     * Initialize all manager instances.
     *
     * @return void
     */
    private function init_managers() {
        // Load Composer autoloader early for HTML processing libraries
        $this->load_composer_autoloader();

        // Initialize Options Manager first (core dependency)
        $this->options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
        $this->options_manager->init();

        // Initialize Content Filter (gets its own Options Manager instance)
        $this->content_filter = WPGraphQL_Content_Filter_Content_Filter::get_instance();

        // Initialize Hook Managers with their dependencies
        $this->graphql_hook_manager = WPGraphQL_Content_Filter_GraphQL_Hook_Manager::get_instance();
        $this->graphql_hook_manager->init($this->options_manager, $this->content_filter);

        $this->rest_hook_manager = WPGraphQL_Content_Filter_REST_Hook_Manager::get_instance();
        $this->rest_hook_manager->init($this->options_manager, $this->content_filter);

        // Initialize Admin Manager (only in admin context)
        if (is_admin()) {
            $this->admin_manager = WPGraphQL_Content_Filter_Admin::get_instance();
            $this->admin_manager->init($this->options_manager, $this->content_filter);
        }
    }

    /**
     * Initialize plugin hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
        if (is_multisite()) {
            add_filter('network_admin_plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_network_plugin_action_links']);
        }

        // Multisite new site support
        if (is_multisite()) {
            add_action('wp_initialize_site', [$this, 'activate_new_site'], 900);
        }
    }
    
    /**
     * Plugin activation hook.
     *
     * @param bool $network_wide Whether activation is network-wide.
     * @return void
     */
    public static function activate($network_wide = false) {
        $default_options = [
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
            'remove_plugin_data_on_uninstall' => false,
        ];
        
        if (is_multisite() && $network_wide) {
            // Set network-wide default options
            $network_defaults = array_merge($default_options, [
                'allow_site_overrides' => true,
                'enforce_network_settings' => false
            ]);
            
            add_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, $network_defaults);
            
            // Initialize all existing sites with proper sync structure
            $sites = get_sites(['fields' => 'ids', 'number' => 500]); // Limit for performance
            
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                
                // Only initialize if options don't exist
                if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                    add_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $default_options);
                }
                
                restore_current_blog();
            }
        } else {
            // Single site activation or individual site in multisite
            if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
                add_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $default_options);
            }
        }
        
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Plugin deactivation hook.
     *
     * @return void
     */
    public static function deactivate() {
        // Clear any existing caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Plugin uninstall hook.
     *
     * @return void
     */
    public static function uninstall() {
        if (is_multisite()) {
            // Remove network options
            delete_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS);
            
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
     * Initialize plugin.
     *
     * @return void
     */
    public function init() {
        load_plugin_textdomain('wpgraphql-content-filter', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Activate plugin for newly created multisite site.
     *
     * @param WP_Site $site Site object.
     * @return void
     */
    public function activate_new_site($site) {
        if (!is_plugin_active_for_network(plugin_basename(WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE))) {
            return;
        }
        
        switch_to_blog($site->blog_id);
        
        // Only add default options if they don't exist
        if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, false)) {
            $default_options = [
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
                'remove_plugin_data_on_uninstall' => false,
            ];
            
            add_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, $default_options);
        }
        
        restore_current_blog();
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=wpgraphql-content-filter')),
            __('Settings', 'wpgraphql-content-filter')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add network plugin action links.
     *
     * @param array $links Existing network plugin action links.
     * @return array Modified network plugin action links.
     */
    public function add_network_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(network_admin_url('settings.php?page=wpgraphql-content-filter-network')),
            __('Network Settings', 'wpgraphql-content-filter')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get Options Manager instance.
     *
     * @return WPGraphQL_Content_Filter_Options_Manager
     */
    public function get_options_manager() {
        return $this->options_manager;
    }

    /**
     * Get Content Filter instance.
     *
     * @return WPGraphQL_Content_Filter_Content_Filter
     */
    public function get_content_filter() {
        return $this->content_filter;
    }

    /**
     * Get GraphQL Hook Manager instance.
     *
     * @return WPGraphQL_Content_Filter_GraphQL_Hook_Manager
     */
    public function get_graphql_hook_manager() {
        return $this->graphql_hook_manager;
    }

    /**
     * Get REST Hook Manager instance.
     *
     * @return WPGraphQL_Content_Filter_REST_Hook_Manager
     */
    public function get_rest_hook_manager() {
        return $this->rest_hook_manager;
    }

    /**
     * Get Admin Manager instance.
     *
     * @return WPGraphQL_Content_Filter_Admin|null
     */
    public function get_admin_manager() {
        return $this->admin_manager;
    }
}

// Register activation/deactivation hooks
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'activate']);
    register_deactivation_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'deactivate']);
    register_uninstall_hook(__FILE__, [WPGraphQL_Content_Filter::class, 'uninstall']);
}