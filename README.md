# WPGraphQL Content Filter Plugin

A WordPress plugin that cleans and filters HTML content in both WPGraphQL and REST API responses according to customizable settings. The plugin features a modern, responsive admin interface with intelligent conditional UI that adapts based on your filtering preferences. It includes comprehensive WordPress multisite support, allowing network administrators to set default filtering rules with complete settings parity and enforcement controls that individual site administrators can customize for their specific needs.

This plugin is particularly valuable in two scenarios: when migrating from a traditional themed WordPress site to a headless architecture, and when a themed WordPress site needs to serve clean content to external applications via API. In both cases, existing content may contain unwanted HTML markup that needs to be filtered or sanitized before being consumed by other systems.

**Version 2.1.25** features a completely refactored modular architecture with professional-grade HTML processing libraries. Integrated `league/html-to-markdown` for robust HTML-to-Markdown conversion and `ezyang/htmlpurifier` for comprehensive HTML sanitization with XSS protection. The plugin now handles all HTML tag attributes completely, including id, classes, data-* attributes, and other complex attributes. Recent versions include enhanced multisite network administration with improved error handling and yoast_head field filtering support.

The plugin was developed using Claude 4 Sonnet.

## Features

- **Multiple Filter Modes:**
  - None (no filtering)
  - Strip All HTML (using HTMLPurifier for comprehensive sanitization)
  - Convert to Markdown (using league/html-to-markdown for professional conversion)
  - Custom Allowed Tags (with HTMLPurifier-based filtering)

- **Intelligent Conditional UI:**
  - Markdown options dynamically shown/hidden based on selected filter mode
  - Clean, responsive admin interface with professional styling
  - Real-time form field visibility updates

- **Configurable Options:**
  - Apply filtering to content and/or excerpt fields
  - Preserve line breaks when stripping HTML
  - Selective Markdown conversion (headings, links, lists, emphasis)
  - Custom allowed HTML tags

- **Advanced Multisite Support:**
  - Complete network-level administration with enforcement controls
  - All site-level settings available at network level
  - Bulk settings synchronization across all sites
  - Override protection and inheritance system

- **API Integration:**
  - Automatically works with all post types registered with WPGraphQL
  - Works with WordPress REST API for all public post types
  - Filters the main `content` field in place
  - Filters the `excerpt` field (optional)
  - Filters the `yoast_head` field (removes HTML comments and applies content filtering)
  - Supports custom post types out of the box

- **Performance Optimizations:**
  - Built-in caching system for improved performance
  - Memory usage optimization and batch processing
  - Professional-grade libraries with optimized HTML parsing
  - Graceful fallbacks to regex-based methods when libraries unavailable
  - Enhanced security through HTMLPurifier's XSS protection

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the plugin files to `/wp-content/plugins/wpgraphql-content-filter/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > GraphQL Content Filter to configure your filtering options

### Development Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/gokepelemo/wpgraphql-content-filter.git
   ```

2. Copy to your WordPress plugins directory
3. Activate and configure as above

## Requirements

- WordPress 5.0+
- PHP 7.4+
- **WPGraphQL plugin** (required for GraphQL filtering functionality)
- Custom post types must be registered with WPGraphQL to be filtered in GraphQL (REST API works with all public post types)
- **Composer dependencies** (automatically included in releases):
  - `league/html-to-markdown` ^5.0 for professional HTML-to-Markdown conversion
  - `ezyang/htmlpurifier` ^4.16 for comprehensive HTML sanitization and XSS protection

**Note:** While the plugin can function without WPGraphQL for REST API filtering only, it is designed primarily for WPGraphQL integration and will display notices if WPGraphQL is not installed. The Composer dependencies are bundled with release packages, so no manual installation is required.

**Multisite Support:** Full network administration capabilities are available for WordPress multisite installations, with enhanced error handling and debug logging in recent versions.

## Usage

### Admin Configuration

Navigate to **Settings > GraphQL Content Filter** in your WordPress admin to configure:

1. **Filter Mode**: Choose how to process content
   - When "Convert to Markdown" is selected, additional Markdown-specific options will appear
   - Other modes hide Markdown options for a cleaner interface
2. **Apply to Fields**: Select which fields to filter (content, excerpt)
3. **Markdown Options**: Configure Markdown conversion (automatically shown/hidden based on filter mode)
   - Preserve Line Breaks
   - Convert Headings to Markdown
   - Convert Links to Markdown  
   - Convert Lists to Markdown
   - Convert Emphasis to Markdown
4. **Custom Tags**: Define allowed HTML tags (for custom mode)

#### Multisite Network Administration

For multisite installations, network administrators can access **Network Admin > Settings > GraphQL Content Filter** to:

- Set network-wide default settings for all sites
- Enable enforcement to prevent individual sites from overriding settings
- Configure all the same options available at the site level
- Bulk synchronize settings across all sites in the network
- Access enhanced error handling and debug logging for troubleshooting

The network admin interface includes the same conditional UI behavior, ensuring a consistent experience across both site-level and network-level administration. Recent improvements include better error handling, comprehensive debug logging, and optimized form processing to prevent blank page issues.

### Performance Settings

The plugin includes several performance optimization features:

#### Cache Settings

- **Enable Cache**: Enables content filtering cache for improved performance by storing processed results
- **Cache TTL (seconds)**: Controls how long cached results are kept (default: 3600 seconds / 1 hour)

#### Batch Processing

- **Batch Processing Size**: Controls how many items are processed together in bulk operations
  - **Default**: 100 items per batch
  - **Range**: 10-1000 items per batch
  - **Purpose**: Prevents memory exhaustion and timeouts during large-scale operations
  - **Tuning**:
    - Lower values (10-50): Better for shared hosting or limited resources
    - Higher values (200-500): Suitable for dedicated servers with more processing power
    - Default (100): Good balance for most WordPress installations

This setting is particularly important for:

- Bulk content filtering operations
- Cache warming processes
- Mass content updates that trigger the content filter
- Any operation processing multiple posts/pages at once

### API Queries

The plugin filters content in place, so your existing queries work without changes. It works with both GraphQL and REST API:

#### GraphQL (requires WPGraphQL plugin)

```graphql
query GetContent {
  # Regular posts
  posts {
    nodes {
      id
      title
      content      # Filtered based on plugin settings
      excerpt      # Filtered if enabled in settings
    }
  }
  
  # Custom post types (example: products)
  products {
    nodes {
      id
      title
      content      # Also filtered based on plugin settings
      excerpt      # Also filtered if enabled
    }
  }
}
```

#### REST API

```bash
# Get posts via REST API (content will be filtered)
GET /wp-json/wp/v2/posts

# Get custom post type via REST API (content will be filtered)
GET /wp-json/wp/v2/products

# Response structure (filtered content):
{
  "id": 123,
  "title": {"rendered": "Post Title"},
  "content": {"rendered": "Filtered content here..."},  # Filtered
  "excerpt": {"rendered": "Filtered excerpt..."},        # Filtered if enabled
  "yoast_head": "Filtered meta tags..."                  # Filtered (HTML comments removed)
}
```

## Filter Modes

### Strip All HTML

Uses HTMLPurifier for comprehensive HTML sanitization and tag removal:

```html
<p class="content">Hello <strong data-id="123">world</strong>!</p>
↓
Hello world!
```

**Features:**
- Complete HTML tag and attribute removal
- XSS protection through HTMLPurifier
- Malformed HTML handling
- Unicode content preservation

### Convert to Markdown

Uses league/html-to-markdown for professional HTML-to-Markdown conversion:

```html
<h2 id="title">Title</h2><p class="content">Hello <strong>world</strong>!</p>
↓
## Title

Hello **world**!
```

**Features:**
- Complete HTML attribute handling (id, class, data-*, etc.)
- Robust nested structure processing
- Configurable conversion options
- Fallback to regex patterns if library unavailable

### Custom Allowed Tags

Uses HTMLPurifier for precise tag allowlisting with comprehensive attribute handling:

```html
Settings: p,strong,em
<p class="content">Hello <strong data-weight="bold">world</strong>! <script>alert('x')</script></p>
↓
<p class="content">Hello <strong data-weight="bold">world</strong>! alert('x')</p>
```

**Features:**
- Complete HTML attribute preservation for allowed tags
- XSS protection against malicious content
- Configurable attribute allowlists
- Professional-grade HTML parsing

## Advanced Usage

### Plugin Architecture

The plugin uses a **modular architecture** with clean separation of concerns and dependency injection (v2.1.0+):

**Core Bootstrap Class:**
- `WPGraphQL_Content_Filter` - Main plugin orchestrator that coordinates all components

**Manager Classes:**
- `WPGraphQL_Content_Filter_Options_Manager` - Handles all plugin settings, caching, and multisite support
- `WPGraphQL_Content_Filter_Content_Filter` - Universal content filtering engine with multiple modes
- `WPGraphQL_Content_Filter_GraphQL_Hook_Manager` - WPGraphQL integration and hook management
- `WPGraphQL_Content_Filter_REST_Hook_Manager` - WordPress REST API integration
- `WPGraphQL_Content_Filter_Admin` - Complete admin interface with conditional UI

**Key Benefits:**
- **Singleton Pattern**: Consistent performance across all managers
- **Dependency Injection**: Clean dependency management between components
- **Separation of Concerns**: Each class has a single, well-defined responsibility
- **Maintainability**: Modular code is easier to debug, test, and extend
- **Error Prevention**: Proper initialization sequence eliminates potential fatal errors

**Legacy Architecture (pre-v2.1.0):**
Previous versions used a monolithic structure. The v2.1.0 refactor maintains complete backward compatibility while providing a foundation for future development.

### Custom Post Type Support

The plugin automatically detects and filters content for:

**GraphQL**: All post types registered with WPGraphQL  
**REST API**: All public post types (built-in WordPress functionality)

No additional configuration is needed - if your custom post type is public or registered with GraphQL, the content filtering will be applied.

**Example custom post types that work:**

- Products (WooCommerce)
- Events
- Portfolio items
- Testimonials
- Any custom post type with `public => true` (REST API) or `show_in_graphql => true` (GraphQL)

### REST API vs GraphQL

The plugin works slightly differently for each API:

- **REST API**: Filters all public post types automatically
- **GraphQL**: Only filters post types explicitly registered with WPGraphQL
- **Settings**: Same filtering options apply to both APIs
- **Fields**: Both APIs filter `content` and `excerpt` fields

### Programmatic Filtering

You can also filter content programmatically using the new modular architecture:

```php
// Get manager instances (v2.1.0+)
$options_manager = WPGraphQL_Content_Filter_Options_Manager::get_instance();
$content_filter = WPGraphQL_Content_Filter_Content_Filter::get_instance();

// Get current options
$options = $options_manager->get_options();

// Apply custom filtering directly
$filtered_content = $content_filter->filter_field_content(
    $raw_content, 
    'content', 
    $options
);

// Hook into GraphQL filtering
add_filter('graphql_post_object_content', function($content, $post, $context) {
    if ($post->post_type === 'custom_post_type') {
        $content_filter = WPGraphQL_Content_Filter_Content_Filter::get_instance();
        $options = WPGraphQL_Content_Filter_Options_Manager::get_instance()->get_options();
        return $content_filter->filter_field_content($content, 'content', $options);
    }
    return $content;
}, 5, 3); // Priority 5 to run before the plugin's filter
```

### Conditional Filtering

You can apply different filters based on context:

```php
add_filter('wpgraphql_content_filter_options', function($options) {
    // Change filter mode based on user role or other conditions
    if (current_user_can('edit_posts')) {
        $options['filter_mode'] = 'none'; // No filtering for editors
    }
    return $options;
});
```

## Hooks and Filters

### Filters

- `wpgraphql_content_filter_options` - Modify plugin options
- `wpgraphql_content_filter_before_apply` - Modify content before filtering
- `wpgraphql_content_filter_after_apply` - Modify content after filtering

### Actions

- `wpgraphql_content_filter_loaded` - Fired when plugin is loaded
- `wpgraphql_content_filter_settings_saved` - Fired when settings are saved

For detailed version history and release notes, see [CHANGELOG.md](CHANGELOG.md).

## License

This plugin is licensed under the GPL v2 or later.

```text
WPGraphQL Content Filter
Copyright (C) 2025 Goke Pelemo

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

## Support

For issues and feature requests, please visit the plugin repository.
