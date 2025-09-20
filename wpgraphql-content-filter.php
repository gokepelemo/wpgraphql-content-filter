<?php
/**
 * Plugin Name: WPGraphQL Content Filter
 * Plugin URI: https://github.com/gokepelemo/wpgraphql-content-filter/
 * Description: Filter and sanitize content in WPGraphQL and REST API responses with configurable HTML stripping, Markdown conversion, and custom tag allowlists. Requires WPGraphQL plugin.
 * Version: 2.0.5
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
    define('WPGRAPHQL_CONTENT_FILTER_VERSION', '2.0.5');
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
 * Get the main plugin instance.
 *
 * @return WPGraphQL_Content_Filter_Core The main plugin instance.
 */
function wpgraphql_content_filter() {
    // Load core orchestrator only when needed
    if (!class_exists('WPGraphQL_Content_Filter_Core')) {
        require_once WPGRAPHQL_CONTENT_FILTER_PLUGIN_DIR . 'includes/class-wpgraphql-content-filter-core.php';
    }
    return WPGraphQL_Content_Filter_Core::get_instance();
}

/**
 * Convert memory limit string to bytes.
 *
 * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
 * @return int Memory limit in bytes.
 */
function wpgraphql_content_filter_convert_memory_to_bytes($memory_limit) {
    if (empty($memory_limit) || $memory_limit === '-1') {
        return 0; // Unlimited
    }

    $memory_limit = trim($memory_limit);
    $unit = strtolower(substr($memory_limit, -1));
    $value = (int) substr($memory_limit, 0, -1);

    switch ($unit) {
        case 'g':
            return $value * 1024 * 1024 * 1024;
        case 'm':
            return $value * 1024 * 1024;
        case 'k':
            return $value * 1024;
        default:
            return (int) $memory_limit;
    }
}

/**
 * Display low memory warning notice.
 */
function wpgraphql_content_filter_low_memory_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    $message = sprintf(
        'WPGraphQL Content Filter: Low memory limit detected (%s). Consider increasing PHP memory_limit to 256M or higher for optimal performance.',
        ini_get('memory_limit')
    );
    
    printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html($message));
}

/**
 * Emergency memory monitor that disables processing if memory gets too high.
 */
function wpgraphql_content_filter_memory_monitor() {
    static $monitoring_disabled = false;
    
    if ($monitoring_disabled) {
        return; // Already disabled processing
    }
    
    $memory_limit = ini_get('memory_limit');
    $memory_bytes = wpgraphql_content_filter_convert_memory_to_bytes($memory_limit);
    $current_memory = memory_get_usage(true);
    
    if ($memory_bytes && ($current_memory / $memory_bytes) > 0.85) {
        error_log('WPGraphQL Content Filter: Memory usage critical (' . round(($current_memory / $memory_bytes) * 100, 2) . '%), disabling content processing');
        
        // Remove all GraphQL filters to prevent further processing
        remove_all_filters('graphql_post_object_fields');
        remove_all_filters('graphql_page_object_fields');
        remove_all_filters('graphql_resolve_field');
        
        // Set flag to prevent re-enabling
        $monitoring_disabled = true;
        
        // Log detailed memory information
        error_log('WPGraphQL Content Filter Memory Status: Current=' . round($current_memory / 1024 / 1024, 2) . 'MB, Limit=' . round($memory_bytes / 1024 / 1024, 2) . 'MB');
    }
}

/**
 * Initialize the plugin.
 */
function wpgraphql_content_filter_init() {
    // EMERGENCY DISABLE - temporary for debugging memory issues
    if (defined('WPGRAPHQL_CONTENT_FILTER_DISABLE') && WPGRAPHQL_CONTENT_FILTER_DISABLE) {
        error_log('WPGraphQL Content Filter: Plugin disabled via WPGRAPHQL_CONTENT_FILTER_DISABLE constant');
        return;
    }

    // Emergency memory protection - disable plugin if memory is critically low
    $memory_limit = ini_get('memory_limit');
    $memory_bytes = wpgraphql_content_filter_convert_memory_to_bytes($memory_limit);
    $current_memory = memory_get_usage(true);
    
    // If we're already using more than 90% of memory, disable the plugin
    if ($memory_bytes && ($current_memory / $memory_bytes) > 0.9) {
        error_log('WPGraphQL Content Filter: EMERGENCY - Memory usage at ' . round(($current_memory / $memory_bytes) * 100, 2) . '%, disabling plugin');
        return; // Exit early to prevent further memory usage
    }
    
    // Memory limit safety check
    if ($memory_bytes && $memory_bytes < 134217728) { // Less than 128MB
        error_log('WPGraphQL Content Filter: Low memory limit detected (' . $memory_limit . '). Consider increasing to 256M or higher.');
        add_action('admin_notices', 'wpgraphql_content_filter_low_memory_notice');
    }

    // Add memory monitoring hook that runs on every request
    add_action('init', 'wpgraphql_content_filter_memory_monitor', 1);

    // Check for WPGraphQL dependency for GraphQL features
    if (class_exists('WPGraphQL') || !empty(get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS)['apply_to_rest_api'])) {
        try {
            wpgraphql_content_filter()->init();
        } catch (Exception $e) {
            error_log('WPGraphQL Content Filter initialization error: ' . $e->getMessage());
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>';
                echo sprintf(
                    'WPGraphQL Content Filter encountered an error: %s',
                    esc_html($e->getMessage())
                );
                echo '</p></div>';
            });
        }
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
