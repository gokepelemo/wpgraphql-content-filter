<?php
/**
 * Plugin Name: WPGraphQL Content Filter
 * Plugin URI: https://github.com/gokepelemo/wpgraphql-content-filter/
 * Description: Filter and sanitize content in WPGraphQL and REST API responses with configurable HTML stripping, Markdown conversion, and custom tag allowlists. Requires WPGraphQL plugin.
 * Version: 2.0.1
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
    define('WPGRAPHQL_CONTENT_FILTER_VERSION', '2.0.1');
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
 * Load core orchestrator and initialize the plugin.
 */
require_once WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR . 'includes/class-wpgraphql-content-filter-core.php';

/**
 * Get the main plugin instance.
 *
 * @return WPGraphQL_Content_Filter_Core The main plugin instance.
 */
function wpgraphql_content_filter() {
    return WPGraphQL_Content_Filter_Core::get_instance();
}

/**
 * Initialize the plugin.
 */
function wpgraphql_content_filter_init() {
    // Check for WPGraphQL dependency for GraphQL features
    if (class_exists('WPGraphQL') || !empty(get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS)['apply_to_rest_api'])) {
        wpgraphql_content_filter()->init();
    } else {
        // Show admin notice about missing dependency
        add_action('admin_notices', 'wpgraphql_content_filter_dependency_notice');
    }
}

/**
 * Display dependency notice.
 */
function wpgraphql_content_filter_dependency_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    $message = sprintf(
        __('%1$s requires %2$s for GraphQL filtering, though REST API filtering can work independently. Install %2$s for full functionality.', 'wpgraphql-content-filter'),
        '<strong>WPGraphQL Content Filter</strong>',
        '<strong>WPGraphQL</strong>'
    );
    
    printf('<div class="notice notice-info"><p>%s</p></div>', wp_kses_post($message));
}

/**
 * Plugin activation hook.
 */
function wpgraphql_content_filter_activate() {
    // Set default options
    if (!get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS)) {
        add_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, [
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
        ]);
    }
    
    // Initialize network options for multisite
    if (is_multisite() && !get_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS)) {
        add_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS, [
            'filter_mode' => 'none',
            'allow_site_overrides' => true,
            'enforce_network_settings' => false,
            'preserve_line_breaks' => true,
            'convert_headings' => true,
            'convert_links' => true,
            'convert_lists' => true,
            'convert_emphasis' => true,
            'custom_allowed_tags' => '',
            'apply_to_excerpt' => true,
            'apply_to_content' => true,
            'apply_to_rest_api' => true,
        ]);
    }
    
    // Clear caches
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Plugin deactivation hook.
 */
function wpgraphql_content_filter_deactivate() {
    $core = WPGraphQL_Content_Filter_Core::get_instance();
    if ($core->is_initialized()) {
        $core->deactivate();
    }
}

/**
 * Plugin uninstall hook.
 */
function wpgraphql_content_filter_uninstall() {
    $options = get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
    
    if (!empty($options['remove_plugin_data_on_uninstall'])) {
        // Remove all plugin data
        delete_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS);
        delete_option(WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION);
        
        if (is_multisite()) {
            delete_site_option(WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS);
        }
        
        // Clear caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

// Register hooks
register_activation_hook(__FILE__, 'wpgraphql_content_filter_activate');
register_deactivation_hook(__FILE__, 'wpgraphql_content_filter_deactivate');
register_uninstall_hook(__FILE__, 'wpgraphql_content_filter_uninstall');

// Initialize the plugin
add_action('plugins_loaded', 'wpgraphql_content_filter_init', 10);
