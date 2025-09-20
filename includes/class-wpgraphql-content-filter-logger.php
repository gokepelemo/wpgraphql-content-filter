<?php
/**
 * Error logging and monitoring utilities
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error logging and monitoring utility class.
 */
class WPGraphQL_Content_Filter_Logger {
    /**
     * Log levels.
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    /**
     * Whether logging is enabled.
     *
     * @var bool
     */
    private static $logging_enabled = null;

    /**
     * Log file path.
     *
     * @var string
     */
    private static $log_file = null;

    /**
     * Initialize the logger.
     */
    public static function init(): void {
        if (self::$logging_enabled === null) {
            $options = get_option(WPGRAPHQL_CONTENT_FILTER_OPTIONS, []);
            self::$logging_enabled = !empty($options['enable_logging']);
            
            if (self::$logging_enabled) {
                $upload_dir = wp_upload_dir();
                self::$log_file = $upload_dir['basedir'] . '/wpgraphql-content-filter.log';
            }
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message.
     * @param array  $context Additional context data.
     */
    public static function error(string $message, array $context = []): void {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message.
     * @param array  $context Additional context data.
     */
    public static function warning(string $message, array $context = []): void {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message.
     * @param array  $context Additional context data.
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message The debug message.
     * @param array  $context Additional context data.
     */
    public static function debug(string $message, array $context = []): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log a message with the specified level.
     *
     * @param string $level   The log level.
     * @param string $message The message.
     * @param array  $context Additional context data.
     */
    private static function log(string $level, string $message, array $context = []): void {
        self::init();

        if (!self::$logging_enabled) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] [%s] %s",
            $timestamp,
            strtoupper($level),
            $message
        );

        if (!empty($context)) {
            $log_entry .= ' Context: ' . self::encode_context($context);
        }

        $log_entry .= PHP_EOL;

        // Write to file
        if (self::$log_file && is_writable(dirname(self::$log_file))) {
            file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }

        // Also log to error_log for critical errors
        if ($level === self::LEVEL_ERROR) {
            error_log('[WPGraphQL Content Filter] ' . $message);
        }
    }

    /**
     * Encode context data for logging.
     *
     * @param array $context The context data.
     * @return string Encoded context.
     */
    private static function encode_context(array $context): string {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($context);
        }
        
        return json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get recent log entries.
     *
     * @param int $limit Number of entries to retrieve.
     * @return array Log entries.
     */
    public static function get_recent_logs(int $limit = 100): array {
        self::init();

        if (!self::$log_file || !file_exists(self::$log_file)) {
            return [];
        }

        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice(array_reverse($lines), 0, $limit);
    }

    /**
     * Clear the log file.
     */
    public static function clear_logs(): void {
        self::init();

        if (self::$log_file && file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }

    /**
     * Get log file size.
     *
     * @return int Log file size in bytes.
     */
    public static function get_log_size(): int {
        self::init();

        if (!self::$log_file || !file_exists(self::$log_file)) {
            return 0;
        }

        return filesize(self::$log_file);
    }
}