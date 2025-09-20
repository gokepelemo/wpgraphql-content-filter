<?php
/**
 * WPGraphQL Content Filter Content Processor
 *
 * Handles content processing with pre-compiled patterns and caching.
 *
 * @package WPGraphQL_Content_Filter
 * @since 1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPGraphQL_Content_Filter_Content_Processor
 *
 * Optimized content processing with pre-compiled regex patterns, content caching,
 * and early bailout strategies for maximum performance.
 *
 * @since 1.0.0
 */
class WPGraphQL_Content_Filter_Content_Processor implements WPGraphQL_Content_Filter_Interface {
    /**
     * Pre-compiled markdown patterns cache.
     *
     * @var array|null
     */
    private $markdown_patterns = null;

    /**
     * Pre-compiled HTML strip patterns cache.
     *
     * @var array|null
     */
    private $html_strip_patterns = null;

    /**
     * Content processing cache.
     *
     * @var array
     */
    private $content_cache = [];

    /**
     * Maximum cache size to prevent memory issues.
     *
     * @var int
     */
    private $max_cache_size = 100;

    /**
     * Cache hit counter for statistics.
     *
     * @var int
     */
    private $cache_hits = 0;

    /**
     * Cache miss counter for statistics.
     *
     * @var int
     */
    private $cache_misses = 0;

    /**
     * Processing counter to detect runaway processes.
     *
     * @var int
     */
    private $processing_count = 0;

    /**
     * Maximum number of content processing operations per request.
     *
     * @var int
     */
    private $max_processing_per_request = 100;

    /**
     * Memory usage at start of request.
     *
     * @var int
     */
    private $initial_memory_usage = 0;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->initial_memory_usage = memory_get_usage(true);
    }

    /**
     * Apply content filtering with optimized processing.
     *
     * @param string $content The content to filter.
     * @param array  $options The filtering options.
     * @return string The filtered content.
     */
    public function apply($content, $options) {
        return $this->process_content($content, $options['filter_mode'] ?? 'strip_html', $options);
    }

    /**
     * Get the name of this filter strategy.
     *
     * @return string The strategy name.
     */
    public function get_name() {
        return 'content_processor';
    }

    /**
     * Check if this strategy is available.
     *
     * @return bool Always true for the main content processor.
     */
    public function is_available() {
        return true;
    }

    /**
     * Get the priority for this filter strategy.
     *
     * @return int The priority (10 = default).
     */
    public function get_priority() {
        return 10;
    }

    /**
     * Process content with caching and optimization.
     *
     * Implements multiple performance optimizations:
     * - Early bailout for invalid/empty content
     * - Content size limits to prevent memory exhaustion
     * - Content hashing for cache key generation
     * - LRU cache management
     * - Pre-compiled pattern usage
     *
     * @param string $content The content to process.
     * @param string $mode    The processing mode.
     * @param array  $options Processing options.
     * @return string Processed content.
     */
    public function process_content($content, $mode, $options) {
        // Circuit breaker: Check for runaway processing
        $this->processing_count++;
        
        if ($this->processing_count > $this->max_processing_per_request) {
            error_log('WPGraphQL Content Filter: Circuit breaker triggered - too many processing requests (' . $this->processing_count . ')');
            return $content; // Return original content to prevent runaway processing
        }

        // Early bailouts for performance
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        // Skip processing for very short content unless specifically requested
        if (strlen($content) < 10 && empty($options['force_processing'])) {
            return $content;
        }

        // Content size limit to prevent memory exhaustion (256KB default for safety)
        $max_content_size = $options['max_content_size'] ?? 262144; // 256KB
        if (strlen($content) > $max_content_size) {
            error_log('WPGraphQL Content Filter: Content exceeds maximum size (' . strlen($content) . ' bytes), truncating to prevent memory issues');
            $content = substr($content, 0, $max_content_size) . '...';
        }

        // Memory usage check before processing - more aggressive limits
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_memory_limit_to_bytes($memory_limit);
        $current_memory = memory_get_usage(true);
        $memory_increase = $current_memory - $this->initial_memory_usage;
        
        if ($memory_limit_bytes && ($current_memory / $memory_limit_bytes) > 0.7) {
            error_log('WPGraphQL Content Filter: High memory usage detected (' . round(($current_memory / $memory_limit_bytes) * 100, 2) . '%), skipping processing');
            return $content;
        }

        // Check for excessive memory increase since start
        if ($memory_increase > 50 * 1024 * 1024) { // 50MB increase
            error_log('WPGraphQL Content Filter: Excessive memory increase detected (' . round($memory_increase / 1024 / 1024, 2) . 'MB), potential memory leak');
            return $content;
        }

        // Generate cache key based on content and options
        $cache_key = $this->generate_cache_key($content, $mode, $options);

        // Check cache first
        if (isset($this->content_cache[$cache_key])) {
            $this->cache_hits++;
            return $this->content_cache[$cache_key];
        }

        $this->cache_misses++;

        // Process content based on mode with memory monitoring
        $memory_before = memory_get_usage(true);
        $result = $this->apply_filter_strategy($content, $mode, $options);
        $memory_after = memory_get_usage(true);
        
        // Log excessive memory usage for this operation
        $operation_memory = $memory_after - $memory_before;
        if ($operation_memory > 10 * 1024 * 1024) { // 10MB for single operation
            error_log('WPGraphQL Content Filter: Single operation used ' . round($operation_memory / 1024 / 1024, 2) . 'MB, content size: ' . strlen($content) . ' bytes');
        }

        // Cache the result with size management
        $this->cache_result($cache_key, $result);

        return $result;
    }

    /**
     * Apply the appropriate filter strategy based on mode.
     *
     * @param string $content The content to filter.
     * @param string $mode    The filtering mode.
     * @param array  $options Filtering options.
     * @return string Filtered content.
     */
    private function apply_filter_strategy($content, $mode, $options) {
        switch ($mode) {
            case 'markdown':
                return $this->convert_to_markdown($content, $options);
                
            case 'strip_html':
                return $this->strip_html_content($content, $options);
                
            case 'preserve_formatting':
                return $this->preserve_formatting($content, $options);
                
            case 'plain_text':
                return $this->convert_to_plain_text($content, $options);
                
            case 'none':
            default:
                return $content;
        }
    }

    /**
     * Convert HTML content to Markdown with pre-compiled patterns.
     *
     * @param string $content The HTML content.
     * @param array  $options Conversion options.
     * @return string Markdown content.
     */
    private function convert_to_markdown($content, $options) {
        $patterns = $this->get_markdown_patterns();
        
        // Apply shortcode stripping if enabled
        if (!empty($options['strip_shortcodes'])) {
            $content = strip_shortcodes($content);
        }

        // Convert headings
        if (!empty($options['preserve_headings'])) {
            foreach ($patterns['headings'] as $pattern => $replacement) {
                $content = $this->safe_preg_replace($pattern, $replacement, $content);
            }
        }

        // Convert links
        if (!empty($options['preserve_links'])) {
            foreach ($patterns['links'] as $pattern => $replacement) {
                $content = $this->safe_preg_replace($pattern, $replacement, $content);
            }
        }

        // Convert images
        if (!empty($options['preserve_images'])) {
            foreach ($patterns['images'] as $pattern => $replacement) {
                $content = $this->safe_preg_replace($pattern, $replacement, $content);
            }
        }

        // Convert lists
        if (!empty($options['preserve_lists'])) {
            foreach ($patterns['lists'] as $pattern => $replacement) {
                $content = $this->safe_preg_replace($pattern, $replacement, $content);
            }
        }

        // Convert tables if enabled
        if (!empty($options['preserve_tables'])) {
            $content = $this->convert_tables_to_markdown($content);
        }

        // Clean up extra whitespace
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = trim($content);

        return $this->apply_content_limits($content, $options);
    }

    /**
     * Strip HTML content with intelligent preservation.
     *
     * @param string $content The HTML content.
     * @param array  $options Stripping options.
     * @return string Cleaned content.
     */
    private function strip_html_content($content, $options) {
        // Strip shortcodes if enabled
        if (!empty($options['strip_shortcodes'])) {
            $content = strip_shortcodes($content);
        }

        // Build allowed tags list
        $allowed_tags = $this->build_allowed_tags($options);

        // Strip HTML while preserving specified tags
        if (!empty($allowed_tags)) {
            $content = wp_kses($content, $allowed_tags);
        } else {
            $content = wp_strip_all_tags($content);
        }

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $this->apply_content_limits($content, $options);
    }

    /**
     * Preserve formatting while cleaning content.
     *
     * @param string $content The content to clean.
     * @param array  $options Preservation options.
     * @return string Cleaned content with preserved formatting.
     */
    private function preserve_formatting($content, $options) {
        // Strip shortcodes if enabled
        if (!empty($options['strip_shortcodes'])) {
            $content = strip_shortcodes($content);
        }

        // Preserve paragraphs if enabled
        if (!empty($options['preserve_paragraphs'])) {
            $content = wpautop($content);
        }

        // Build comprehensive allowed tags for formatting preservation
        $allowed_tags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'blockquote' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        ];

        if (!empty($options['preserve_links'])) {
            $allowed_tags['a'] = ['href' => [], 'title' => [], 'target' => []];
        }

        if (!empty($options['preserve_lists'])) {
            $allowed_tags['ul'] = [];
            $allowed_tags['ol'] = [];
            $allowed_tags['li'] = [];
        }

        $content = wp_kses($content, $allowed_tags);

        return $this->apply_content_limits($content, $options);
    }

    /**
     * Convert content to plain text.
     *
     * @param string $content The content to convert.
     * @param array  $options Conversion options.
     * @return string Plain text content.
     */
    private function convert_to_plain_text($content, $options) {
        // Strip shortcodes if enabled
        if (!empty($options['strip_shortcodes'])) {
            $content = strip_shortcodes($content);
        }

        // Strip all HTML
        $content = wp_strip_all_tags($content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        return $this->apply_content_limits($content, $options);
    }

    /**
     * Get pre-compiled markdown conversion patterns.
     *
     * @return array Compiled patterns for markdown conversion.
     */
    private function get_markdown_patterns() {
        if ($this->markdown_patterns === null) {
            $this->markdown_patterns = [
                'headings' => [
                    '/<h1[^>]*+>((?:[^<]++|<(?!\/h1>))*+)<\/h1>/i' => '# $1',
                    '/<h2[^>]*+>((?:[^<]++|<(?!\/h2>))*+)<\/h2>/i' => '## $1',
                    '/<h3[^>]*+>((?:[^<]++|<(?!\/h3>))*+)<\/h3>/i' => '### $1',
                    '/<h4[^>]*+>((?:[^<]++|<(?!\/h4>))*+)<\/h4>/i' => '#### $1',
                    '/<h5[^>]*+>((?:[^<]++|<(?!\/h5>))*+)<\/h5>/i' => '##### $1',
                    '/<h6[^>]*+>((?:[^<]++|<(?!\/h6>))*+)<\/h6>/i' => '###### $1',
                ],
                'links' => [
                    '/<a[^>]*+href="([^"]*+)"[^>]*+>((?:[^<]++|<(?!\/a>))*+)<\/a>/i' => '[$2]($1)',
                ],
                'images' => [
                    '/<img[^>]*+src="([^"]*+)"[^>]*+alt="([^"]*+)"[^>]*+>/i' => '![$2]($1)',
                    '/<img[^>]*+src="([^"]*+)"[^>]*+>/i' => '![]($1)',
                ],
                'lists' => [
                    '/<ul[^>]*+>((?:[^<]++|<(?!\/ul>))*+)<\/ul>/i' => '$1',
                    '/<ol[^>]*+>((?:[^<]++|<(?!\/ol>))*+)<\/ol>/i' => '$1',
                    '/<li[^>]*+>((?:[^<]++|<(?!\/li>))*+)<\/li>/i' => '- $1',
                ],
                'formatting' => [
                    '/<strong[^>]*+>((?:[^<]++|<(?!\/strong>))*+)<\/strong>/i' => '**$1**',
                    '/<b[^>]*+>((?:[^<]++|<(?!\/b>))*+)<\/b>/i' => '**$1**',
                    '/<em[^>]*+>((?:[^<]++|<(?!\/em>))*+)<\/em>/i' => '*$1*',
                    '/<i[^>]*+>((?:[^<]++|<(?!\/i>))*+)<\/i>/i' => '*$1*',
                    '/<code[^>]*+>((?:[^<]++|<(?!\/code>))*+)<\/code>/i' => '`$1`',
                    '/<blockquote[^>]*+>((?:[^<]++|<(?!\/blockquote>))*+)<\/blockquote>/i' => '> $1',
                ],
            ];
        }
        return $this->markdown_patterns;
    }

    /**
     * Build allowed HTML tags based on options.
     *
     * @param array $options Processing options.
     * @return array Allowed tags configuration.
     */
    private function build_allowed_tags($options) {
        $allowed_tags = [];

        if (!empty($options['preserve_headings'])) {
            $allowed_tags = array_merge($allowed_tags, [
                'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []
            ]);
        }

        if (!empty($options['preserve_links'])) {
            $allowed_tags['a'] = ['href' => [], 'title' => [], 'target' => []];
        }

        if (!empty($options['preserve_images'])) {
            $allowed_tags['img'] = ['src' => [], 'alt' => [], 'title' => [], 'width' => [], 'height' => []];
        }

        if (!empty($options['preserve_lists'])) {
            $allowed_tags = array_merge($allowed_tags, [
                'ul' => [], 'ol' => [], 'li' => []
            ]);
        }

        if (!empty($options['preserve_tables'])) {
            $allowed_tags = array_merge($allowed_tags, [
                'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => []
            ]);
        }

        // Add custom HTML tags if specified
        if (!empty($options['custom_html_tags'])) {
            $custom_tags = array_map('trim', explode(',', $options['custom_html_tags']));
            foreach ($custom_tags as $tag) {
                if (!empty($tag)) {
                    $allowed_tags[$tag] = [];
                }
            }
        }

        return $allowed_tags;
    }

    /**
     * Apply content limits (word/character limits).
     *
     * @param string $content The content to limit.
     * @param array  $options Limit options.
     * @return string Limited content.
     */
    private function apply_content_limits($content, $options) {
        // Apply word limit
        if (!empty($options['word_limit']) && $options['word_limit'] > 0) {
            $words = explode(' ', $content);
            if (count($words) > $options['word_limit']) {
                $content = implode(' ', array_slice($words, 0, $options['word_limit'])) . '...';
            }
        }

        // Apply character limit
        if (!empty($options['character_limit']) && $options['character_limit'] > 0) {
            if (strlen($content) > $options['character_limit']) {
                $content = substr($content, 0, $options['character_limit']) . '...';
            }
        }

        return $content;
    }

    /**
     * Convert HTML tables to Markdown format.
     *
     * @param string $content Content with HTML tables.
     * @return string Content with Markdown tables.
     */
    private function convert_tables_to_markdown($content) {
        // This is a simplified table conversion
        // For production use, consider using a dedicated HTML to Markdown library
        $content = preg_replace('/<table[^>]*>/i', '', $content);
        $content = preg_replace('/<\/table>/i', "\n", $content);
        $content = preg_replace('/<tr[^>]*>/i', '', $content);
        $content = preg_replace('/<\/tr>/i', "|\n", $content);
        $content = preg_replace('/<t[hd][^>]*>/i', '| ', $content);
        $content = preg_replace('/<\/t[hd]>/i', ' ', $content);
        
        return $content;
    }

    /**
     * Generate cache key for content and options.
     *
     * @param string $content The content.
     * @param string $mode    The processing mode.
     * @param array  $options The options.
     * @return string Cache key.
     */
    private function generate_cache_key($content, $mode, $options) {
        $key_data = [
            'content_hash' => md5($content),
            'mode' => $mode,
            'options_hash' => md5(serialize($options))
        ];
        return md5(serialize($key_data));
    }

    /**
     * Cache processing result with size management.
     *
     * @param string $key    Cache key.
     * @param string $result Processing result.
     * @return void
     */
    private function cache_result($key, $result) {
        // Implement LRU cache behavior
        if (count($this->content_cache) >= $this->max_cache_size) {
            // Remove oldest entries (first half of cache)
            $this->content_cache = array_slice(
                $this->content_cache, 
                intval($this->max_cache_size / 2), 
                null, 
                true
            );
        }

        $this->content_cache[$key] = $result;
    }

    /**
     * Get processing statistics for monitoring.
     *
     * @return array Processing statistics.
     */
    public function get_stats() {
        $total_requests = $this->cache_hits + $this->cache_misses;
        $hit_ratio = $total_requests > 0 ? ($this->cache_hits / $total_requests) * 100 : 0;

        return [
            'cache_hits' => $this->cache_hits,
            'cache_misses' => $this->cache_misses,
            'hit_ratio' => round($hit_ratio, 2),
            'cached_items' => count($this->content_cache),
            'max_cache_size' => $this->max_cache_size,
            'memory_usage' => memory_get_usage(true)
        ];
    }

    /**
     * Clear the content cache.
     *
     * @return void
     */
    public function clear_cache() {
        $this->content_cache = [];
        $this->cache_hits = 0;
        $this->cache_misses = 0;
    }

    /**
     * Set maximum cache size.
     *
     * @param int $size Maximum number of cached items.
     * @return void
     */
    public function set_max_cache_size($size) {
        $this->max_cache_size = max(10, intval($size));
        
        // Trim cache if it exceeds new size
        if (count($this->content_cache) > $this->max_cache_size) {
            $this->content_cache = array_slice(
                $this->content_cache, 
                -$this->max_cache_size, 
                null, 
                true
            );
        }
    }

    /**
     * Convert memory limit string to bytes.
     *
     * @param string $memory_limit Memory limit string (e.g., '128M', '1G').
     * @return int Memory limit in bytes.
     */
    private function convert_memory_limit_to_bytes($memory_limit) {
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
     * Safely perform preg_replace with error handling and timeout protection.
     *
     * @param string $pattern     Regular expression pattern.
     * @param string $replacement Replacement string.
     * @param string $subject     Subject string.
     * @return string Processed string or original if error occurred.
     */
    private function safe_preg_replace($pattern, $replacement, $subject) {
        // Set PCRE limits to prevent memory exhaustion
        $old_backtrack_limit = ini_get('pcre.backtrack_limit');
        $old_recursion_limit = ini_get('pcre.recursion_limit');
        
        ini_set('pcre.backtrack_limit', '10000');
        ini_set('pcre.recursion_limit', '10000');

        $result = @preg_replace($pattern, $replacement, $subject);
        
        // Check for PCRE errors
        $preg_error = preg_last_error();
        if ($preg_error !== PREG_NO_ERROR || $result === null) {
            error_log('WPGraphQL Content Filter: PCRE error ' . $preg_error . ' for pattern: ' . $pattern);
            $result = $subject; // Return original content if regex fails
        }

        // Restore original limits
        ini_set('pcre.backtrack_limit', $old_backtrack_limit);
        ini_set('pcre.recursion_limit', $old_recursion_limit);

        return $result;
    }
}