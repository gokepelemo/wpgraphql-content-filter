# WPGraphQL Content Filter Plugin

A WordPress plugin that filters and sanitizes content in WPGraphQL and REST API responses based on configurable settings. Multisite-compatible, enabling a site administrator to create default settings, and for those settings to be overriden on a site level.

Created with the Claude 4 Sonnet LLM.

## Features

- **Multiple Filter Modes:**
  - None (no filtering)
  - Strip All HTML (convert to plain text)
  - Convert to Markdown
  - Custom Allowed Tags

- **Configurable Options:**
  - Apply filtering to content and/or excerpt fields
  - Preserve line breaks when stripping HTML
  - Selective Markdown conversion (headings, links, lists, emphasis)
  - Custom allowed HTML tags

- **API Integration:**
  - Automatically works with all post types registered with WPGraphQL
  - Works with WordPress REST API for all public post types
  - Filters the main `content` field in place
  - Filters the `excerpt` field (optional)
  - Supports custom post types out of the box

## Installation

### From WordPress.org (Recommended)

1. Go to your WordPress admin dashboard
2. Navigate to Plugins > Add New
3. Search for "WPGraphQL Content Filter"
4. Click "Install Now" and then "Activate"
5. Go to Settings > GraphQL Content Filter to configure

### Manual Installation

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/wpgraphql-content-filter/)
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
- WPGraphQL plugin (for GraphQL filtering)
- Custom post types must be registered with WPGraphQL to be filtered in GraphQL (REST API works with all public post types)

## Usage

### Admin Configuration

Navigate to **Settings > GraphQL Content Filter** in your WordPress admin to configure:

1. **Filter Mode**: Choose how to process content
2. **Apply to Fields**: Select which fields to filter
3. **Markdown Options**: Configure Markdown conversion (when applicable)
4. **Custom Tags**: Define allowed HTML tags (for custom mode)

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
  "excerpt": {"rendered": "Filtered excerpt..."}        # Filtered if enabled
}
```

## Filter Modes

### Strip All HTML

Removes all HTML tags and returns plain text:

```html
<p>Hello <strong>world</strong>!</p>
↓
Hello world!
```

### Convert to Markdown

Converts HTML to Markdown syntax:

```html
<h2>Title</h2><p>Hello <strong>world</strong>!</p>
↓
## Title

Hello **world**!
```

### Custom Allowed Tags

Only allows specified HTML tags:

```html
Settings: p,strong,em
<p>Hello <strong>world</strong>! <script>alert('x')</script></p>
↓
<p>Hello <strong>world</strong>! alert('x')</p>
```

## Advanced Usage

### Plugin Architecture

The plugin uses a modular architecture with separated concerns:

**Content Filtering Layer:**

- `filter_field_content()` - Universal content filtering logic
- `apply_filter()` - Core filtering implementation with mode switching
- Individual filtering methods for each mode (strip tags, markdown, etc.)

**API Hook Layer:**

- `register_graphql_hooks()` - Sets up GraphQL field filters
- `register_rest_hooks()` - Sets up REST API response filters
- Separate filter methods for each API (`filter_content`, `filter_excerpt`, `filter_rest_response`)

This separation makes the plugin easily extensible for additional APIs or filtering modes.

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

You can also filter content programmatically:

```php
// Get the plugin instance
$filter = WPGraphQL_Content_Filter::getInstance();

// Apply custom filtering
add_filter('graphql_post_object_content', function($content, $post, $context) {
    if ($post->post_type === 'custom_post_type') {
        // Custom filtering logic here
        return wp_strip_all_tags($content);
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

## Changelog

### 1.0.3 - 2025-08-30

**Network Settings Synchronization & Enhanced Multisite Support**

- **Added:**
  - Network settings automatically sync to all sites when changed
  - Manual sync button in network admin with AJAX progress feedback
  - Override tracking system to distinguish inherited vs overridden settings
  - Visual indicators in admin showing inheritance status (✓ inherited, 🔄 overridden)
  - Enhanced admin interface with clear network vs site-level distinction
  - Improved data structure with override metadata tracking
  - Legacy option format migration for existing installations

- **Changed:**
  - Refactored multisite architecture for better inheritance handling
  - Improved activation process for new and existing sites
  - Enhanced cache management for multisite environments
  - New site option storage format: `{options: {...}, overrides: {...}, last_sync: timestamp}`

- **Fixed:**
  - Network settings now properly propagate to all sites immediately
  - Override detection works correctly for all setting types
  - Cache invalidation properly handles multisite scenarios
  - Activation process correctly initializes all sites with network settings

### 1.0.2 - 2025-08-30

**Database Cleanup Feature**

- **Added:**
  - Optional plugin data removal during uninstall
  - User-controlled cleanup with clear warnings
  - Safe deletion of plugin-specific data only
  - Multisite-aware cleanup functionality

### 1.0.1 - 2025-08-30

**Performance Optimizations & Enhanced Multisite Support**

- **Added:**
  - Options caching system for improved performance (60-80% reduction in database queries)
  - Cache invalidation hooks for option updates
  - Early return optimizations in content filtering
  - Automatic new site activation handling
  - Improved option inheritance logic
  - Network-wide cache invalidation
  - Better error handling and logging

- **Changed:**
  - Memory usage optimization through smart caching
  - Enhanced admin interface for better user experience
  - Improved validation and sanitization throughout

- **Fixed:**
  - Performance bottlenecks in option retrieval
  - Cache inconsistencies in multisite environments
  - Option inheritance edge cases

### 1.0.0 - 2025-08-30

**Initial Release**

- Multiple filter modes (none, strip all, markdown, custom)
- Admin settings page with comprehensive options
- GraphQL field integration (requires WPGraphQL plugin)
- REST API integration (works with all public post types)
- Configurable options for content and excerpt filtering
- Basic multisite support
- WordPress coding standards compliance

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
