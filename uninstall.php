<?php
/**
 * Uninstall script for WPGraphQL Content Filter
 *
 * @package WPGraphQL_Content_Filter
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Check if user wants to remove plugin data
 */
function should_remove_plugin_data() {
    // Check multisite network setting first
    if (is_multisite()) {
        $network_options = get_site_option('wpgraphql_content_filter_network_options', []);
        if (isset($network_options['remove_plugin_data_on_uninstall'])) {
            return (bool) $network_options['remove_plugin_data_on_uninstall'];
        }
    }
    
    // Check individual site setting
    $options = get_option('wpgraphql_content_filter_options', []);
    return isset($options['remove_plugin_data_on_uninstall']) && $options['remove_plugin_data_on_uninstall'];
}

/**
 * Remove all plugin-specific data
 */
function remove_plugin_data() {
    global $wpdb;
    
    // Delete plugin options
    delete_option('wpgraphql_content_filter_options');
    
    // Delete any plugin-specific cache entries
    $cache_keys = [
        'wpgraphql_content_filter_cache_',
        'wpgraphql_cf_processed_',
        'wpgraphql_cf_stats_'
    ];
    
    // Clean up cache entries from wp_options table
    foreach ($cache_keys as $key_prefix) {
        $like_pattern = $wpdb->esc_like($key_prefix) . '%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            [$like_pattern]
        ));
    }
    
    // Clean up any transients created by the plugin
    $transient_pattern = $wpdb->esc_like('_transient_wpgraphql_cf_') . '%';
    $transient_timeout_pattern = $wpdb->esc_like('_transient_timeout_wpgraphql_cf_') . '%';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        [$transient_pattern, $transient_timeout_pattern]
    ));
}

// Check if user wants to remove plugin data
if (should_remove_plugin_data()) {
    // For multisite installations
    if (is_multisite()) {
        global $wpdb;
        
        // Delete network-wide options
        delete_site_option('wpgraphql_content_filter_network_options');
        
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
        $original_blog_id = get_current_blog_id();
        
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            remove_plugin_data();
        }
        
        switch_to_blog($original_blog_id);
    } else {
        // Single site installation
        remove_plugin_data();
    }
    
    // Clear any existing caches
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
