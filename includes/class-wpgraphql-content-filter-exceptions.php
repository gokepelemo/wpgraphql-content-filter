<?php
/**
 * Custom exceptions for WPGraphQL Content Filter plugin
 *
 * @package WPGraphQL_Content_Filter
 * @since 2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base exception class for WPGraphQL Content Filter.
 */
class WPGraphQL_Content_Filter_Exception extends Exception {
    /**
     * Error context data.
     *
     * @var array
     */
    protected $context = [];

    /**
     * Constructor.
     *
     * @param string         $message  The exception message.
     * @param int            $code     The exception code.
     * @param Exception|null $previous The previous exception.
     * @param array          $context  Additional context data.
     */
    public function __construct($message = '', $code = 0, Exception $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get error context.
     *
     * @return array The context data.
     */
    public function getContext(): array {
        return $this->context;
    }

    /**
     * Set error context.
     *
     * @param array $context The context data.
     */
    public function setContext(array $context): void {
        $this->context = $context;
    }

    /**
     * Log the exception with context.
     */
    public function logError(): void {
        if (function_exists('error_log')) {
            $log_message = sprintf(
                '[WPGraphQL Content Filter] %s: %s (Code: %d)',
                get_class($this),
                $this->getMessage(),
                $this->getCode()
            );

            if (!empty($this->context)) {
                $log_message .= ' Context: ' . wp_json_encode($this->context);
            }

            error_log($log_message);
        }
    }
}

/**
 * Exception for content processing errors.
 */
class WPGraphQL_Content_Filter_Content_Exception extends WPGraphQL_Content_Filter_Exception {
    /**
     * Create exception for invalid content.
     *
     * @param mixed $content The invalid content.
     * @return self
     */
    public static function invalidContent($content): self {
        return new self(
            'Invalid content provided for processing',
            1001,
            null,
            [
                'content_type' => gettype($content),
                'content_length' => is_string($content) ? strlen($content) : 'N/A'
            ]
        );
    }

    /**
     * Create exception for processing failures.
     *
     * @param string $mode The processing mode.
     * @param string $reason The failure reason.
     * @return self
     */
    public static function processingFailed(string $mode, string $reason = ''): self {
        return new self(
            "Content processing failed for mode: $mode" . ($reason ? " - $reason" : ''),
            1002,
            null,
            ['processing_mode' => $mode, 'reason' => $reason]
        );
    }
}

/**
 * Exception for cache-related errors.
 */
class WPGraphQL_Content_Filter_Cache_Exception extends WPGraphQL_Content_Filter_Exception {
    /**
     * Create exception for cache initialization failures.
     *
     * @param string $provider The cache provider name.
     * @param string $reason The failure reason.
     * @return self
     */
    public static function initializationFailed(string $provider, string $reason = ''): self {
        return new self(
            "Cache initialization failed for provider: $provider" . ($reason ? " - $reason" : ''),
            2001,
            null,
            ['cache_provider' => $provider, 'reason' => $reason]
        );
    }

    /**
     * Create exception for cache operation failures.
     *
     * @param string $operation The cache operation (get, set, delete, flush).
     * @param string $key The cache key.
     * @param string $reason The failure reason.
     * @return self
     */
    public static function operationFailed(string $operation, string $key, string $reason = ''): self {
        return new self(
            "Cache $operation operation failed for key: $key" . ($reason ? " - $reason" : ''),
            2002,
            null,
            ['operation' => $operation, 'cache_key' => $key, 'reason' => $reason]
        );
    }
}

/**
 * Exception for configuration and options errors.
 */
class WPGraphQL_Content_Filter_Config_Exception extends WPGraphQL_Content_Filter_Exception {
    /**
     * Create exception for invalid configuration.
     *
     * @param string $option The invalid option name.
     * @param mixed  $value The invalid value.
     * @return self
     */
    public static function invalidOption(string $option, $value): self {
        return new self(
            "Invalid configuration option: $option",
            3001,
            null,
            [
                'option_name' => $option,
                'provided_value' => $value,
                'value_type' => gettype($value)
            ]
        );
    }

    /**
     * Create exception for missing required options.
     *
     * @param array $missing_options Array of missing option names.
     * @return self
     */
    public static function missingRequiredOptions(array $missing_options): self {
        return new self(
            'Missing required configuration options: ' . implode(', ', $missing_options),
            3002,
            null,
            ['missing_options' => $missing_options]
        );
    }
}

/**
 * Exception for GraphQL and REST API integration errors.
 */
class WPGraphQL_Content_Filter_Integration_Exception extends WPGraphQL_Content_Filter_Exception {
    /**
     * Create exception for GraphQL integration failures.
     *
     * @param string $reason The failure reason.
     * @return self
     */
    public static function graphqlIntegrationFailed(string $reason = ''): self {
        return new self(
            'GraphQL integration failed' . ($reason ? " - $reason" : ''),
            4001,
            null,
            ['integration_type' => 'graphql', 'reason' => $reason]
        );
    }

    /**
     * Create exception for REST API integration failures.
     *
     * @param string $reason The failure reason.
     * @return self
     */
    public static function restIntegrationFailed(string $reason = ''): self {
        return new self(
            'REST API integration failed' . ($reason ? " - $reason" : ''),
            4002,
            null,
            ['integration_type' => 'rest', 'reason' => $reason]
        );
    }
}

/**
 * Exception for dependency and plugin compatibility errors.
 */
class WPGraphQL_Content_Filter_Dependency_Exception extends WPGraphQL_Content_Filter_Exception {
    /**
     * Create exception for missing dependencies.
     *
     * @param string $dependency The missing dependency name.
     * @param string $version The required version.
     * @return self
     */
    public static function missingDependency(string $dependency, string $version = ''): self {
        return new self(
            "Missing required dependency: $dependency" . ($version ? " (version $version)" : ''),
            5001,
            null,
            ['dependency' => $dependency, 'required_version' => $version]
        );
    }

    /**
     * Create exception for version incompatibility.
     *
     * @param string $dependency The dependency name.
     * @param string $current_version The current version.
     * @param string $required_version The required version.
     * @return self
     */
    public static function incompatibleVersion(string $dependency, string $current_version, string $required_version): self {
        return new self(
            "Incompatible version of $dependency: $current_version (required: $required_version)",
            5002,
            null,
            [
                'dependency' => $dependency,
                'current_version' => $current_version,
                'required_version' => $required_version
            ]
        );
    }
}