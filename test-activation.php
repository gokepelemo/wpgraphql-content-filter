<?php
/**
 * Test script to simulate WordPress plugin activation
 */

// Mock WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Track WordPress hooks and their execution order
$wp_hooks_executed = [];
$wp_actions = [];
$wp_filters = [];

function add_action($hook, $callback, $priority = 10, $args = 1) {
    global $wp_actions;
    if (!isset($wp_actions[$hook])) {
        $wp_actions[$hook] = [];
    }
    $wp_actions[$hook][] = ['callback' => $callback, 'priority' => $priority];
    return true;
}

function add_filter($hook, $callback, $priority = 10, $args = 1) {
    global $wp_filters;
    if (!isset($wp_filters[$hook])) {
        $wp_filters[$hook] = [];
    }
    $wp_filters[$hook][] = ['callback' => $callback, 'priority' => $priority];
    return true;
}

function do_action($hook) {
    global $wp_actions, $wp_hooks_executed;
    $wp_hooks_executed[] = $hook;
    echo "🚀 Executing action: $hook\n";

    if (isset($wp_actions[$hook])) {
        foreach ($wp_actions[$hook] as $action) {
            $callback = $action['callback'];
            if (is_callable($callback)) {
                try {
                    if (is_array($callback) && is_object($callback[0])) {
                        echo "   → " . get_class($callback[0]) . "::" . $callback[1] . "()\n";
                    } else {
                        echo "   → " . (is_string($callback) ? $callback : 'closure') . "()\n";
                    }
                    call_user_func($callback);
                    echo "   ✅ Executed successfully\n";
                } catch (Throwable $e) {
                    echo "   ❌ FAILED: " . $e->getMessage() . "\n";
                    echo "   📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
                    throw $e; // Re-throw to stop execution
                }
            }
        }
    }
    echo "\n";
}

// Mock WordPress functions
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename($file); }
function is_admin() { return false; }
function is_multisite() { return false; }
function get_option($name, $default = false) { return $default; }
function get_site_option($name, $default = false) { return $default; }
function add_option($name, $value) { return true; }
function load_plugin_textdomain() { return true; }
function register_activation_hook() { return true; }
function register_deactivation_hook() { return true; }
function register_uninstall_hook() { return true; }
function esc_attr($text) { return $text; }
function wp_kses_post($text) { return $text; }
function __($text, $domain = '') { return $text; }
function get_post_types($args = [], $output = 'names') {
    // Mock WordPress post types
    return ['post', 'page'];
}

// Note: Can't mock class_exists/function_exists as they're built-in functions
// The test will run with actual functions available

// Define constants
if (!defined('WPGRAPHQL_CONTENT_FILTER_VERSION')) {
    define('WPGRAPHQL_CONTENT_FILTER_VERSION', '2.1.13');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE')) {
    define('WPGRAPHQL_CONTENT_FILTER_PLUGIN_FILE', __FILE__);
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_OPTIONS')) {
    define('WPGRAPHQL_CONTENT_FILTER_OPTIONS', 'wpgraphql_content_filter_options');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS')) {
    define('WPGRAPHQL_CONTENT_FILTER_NETWORK_OPTIONS', 'wpgraphql_content_filter_network_options');
}
if (!defined('WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION')) {
    define('WPGRAPHQL_CONTENT_FILTER_VERSION_OPTION', 'wpgraphql_content_filter_version');
}

echo "🔄 Testing WordPress plugin activation sequence...\n\n";

try {
    // Step 1: Load plugin file (simulates WordPress loading the plugin)
    echo "📦 Loading plugin main file...\n";
    require_once __DIR__ . '/wpgraphql-content-filter.php';
    echo "✅ Plugin file loaded successfully\n\n";

    // Step 2: Simulate plugins_loaded action
    echo "📡 Simulating plugins_loaded action...\n";
    do_action('plugins_loaded');

    // Step 3: Simulate init action (this is where the conflict might occur)
    echo "📡 Simulating init action...\n";
    do_action('init');

    // Step 4: Simulate wp_loaded action
    echo "📡 Simulating wp_loaded action...\n";
    do_action('wp_loaded');

    // Step 5: Simulate rest_api_init action
    echo "📡 Simulating rest_api_init action...\n";
    do_action('rest_api_init');

    echo "🎉 Plugin activation simulation completed successfully!\n\n";

    echo "📊 Summary:\n";
    echo "Hooks executed: " . implode(', ', $wp_hooks_executed) . "\n";
    echo "Actions registered: " . count($wp_actions) . "\n";
    echo "Filters registered: " . count($wp_filters) . "\n\n";

    echo "🔍 Registered actions by hook:\n";
    foreach ($wp_actions as $hook => $actions) {
        echo "  - $hook: " . count($actions) . " callback(s)\n";
    }

} catch (Error $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "\n📚 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "\n📚 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}