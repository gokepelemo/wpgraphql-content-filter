# WPGraphQL Content Filter Plugin

A WordPress plugin that cleans and filters HTML content in both WPGraphQL and REST API responses according to customizable settings. The plugin features a modern, responsive admin interface with intelligent conditional UI that adapts based on your filtering preferences. It includes comprehensive WordPress multisite support, allowing network administrators to set default filtering rules with complete settings parity and enforcement controls that individual site administrators can customize for their specific needs.

This plugin is particularly valuable in two scenarios: when migrating from a traditional themed WordPress site to a headless architecture, and when a themed WordPress site needs to serve clean content to external applications via API. In both cases, existing content may contain unwanted HTML markup that needs to be filtered or sanitized before being consumed by other systems.

The plugin was developed using Claude 4 Sonnet.

## Features

- **Multiple Filter Modes:**
  - None (no filtering)
  - Strip All HTML (convert to plain text)
  - Convert to Markdown
  - Custom Allowed Tags

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
  - Supports custom post types out of the box

- **Performance Optimizations:**
  - Built-in caching system for improved performance
  - Memory usage optimization and batch processing
  - Configurable cache TTL and batch sizes
  - Resolved PHP memory exhaustion issues

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

**Note:** While the plugin can function without WPGraphQL for REST API filtering only, it is designed primarily for WPGraphQL integration and will display notices if WPGraphQL is not installed.

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

The network admin interface includes the same conditional UI behavior, ensuring a consistent experience across both site-level and network-level administration.

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

### 2.0.8 - 2025-09-20

**Major Admin Interface Overhaul & Conditional UI**

- **Added:**
  - Conditional Markdown Options: Markdown-related settings (preserve_line_breaks, convert_headings, convert_links, convert_lists, convert_emphasis) now only appear when "Convert to Markdown" filter mode is selected
  - Real-time UI Updates: JavaScript-powered dynamic form field visibility that updates instantly when filter mode changes
  - Complete Network Settings Parity: All site-level settings are now available at the network level for comprehensive multisite management
  - Responsive Admin Layout: Modern CSS Grid/Flexbox design with optimal field sizing and professional appearance
  - Enhanced Typography: Improved form label sizing (15px) and better visual alignment throughout the interface

- **Enhanced:**
  - Admin Interface: Complete responsive redesign with proper width controls, better spacing, and professional styling
  - Memory Management: Resolved PHP memory exhaustion issues through systematic optimization and conditional loading
  - Release Process: Updated release.sh script to create "v2.0.x.zip" packages instead of full plugin name for cleaner distribution
  - Menu Naming: Changed admin menu from "Content Filter" to "GraphQL Content Filter" for better clarity
  - User Experience: Cleaner interface with contextual option display and improved visual hierarchy

- **Technical:**
  - CSS Framework: Implemented comprehensive responsive stylesheet with breakpoint support for desktop, tablet, and mobile
  - JavaScript Integration: Added dynamic form field management with proper event handling and state management
  - Settings Architecture: Enhanced WordPress Settings API integration with conditional field callbacks
  - Performance: Optimized hook registration and module initialization for better resource usage
  - Code Quality: Improved separation of concerns between admin, core, and hook classes

- **Fixed:**
  - Form Layout Issues: Resolved excessive field stretching and improved proportional layout (25% labels, 75% fields)
  - Memory Exhaustion: Completely resolved PHP memory limit issues that were affecting plugin functionality
  - Conditional Display: Fixed WordPress Settings API integration to properly show/hide entire form rows (labels + fields)
  - Cross-platform Compatibility: Enhanced compatibility across different WordPress environments and multisite configurations

### 1.0.9 - 2025-08-30

**Enhanced UI Design**

- **Enhanced:**
  - Consistent UI Design: REST API setting now prominently displayed at the top of BOTH site-level and network-level admin pages
  - Site-Level Admin Improvements: Added dedicated "API Targets" section with enhanced visual styling for REST API setting
  - Better Organization: Clear visual separation between API configuration and content filtering settings
  - Enhanced Descriptions: More detailed explanations about the difference between REST API and WPGraphQL filtering
  - Visual Hierarchy: Improved section headers, styling, and layout consistency across both admin interfaces

- **Added:**
  - Custom REST API Callback: New dedicated callback method for prominent REST API setting display on site-level admin
  - Section Headers: Clear visual organization with "API Targets" and "Content Filtering Settings" sections
  - Enhanced Styling: Background highlighting, larger checkboxes, and improved typography for important settings

### 1.0.8 - 2025-08-30

**Network Admin Settings Enhancement**

- **Enhanced:**
  - Network Admin Settings: All specific settings (Apply to Content Field, Apply to Excerpt Field, Apply to REST API, Preserve Line Breaks, etc.) are now fully configurable at the network level for network-wide defaults
  - Improved UI Layout: Restructured settings page with "Apply to REST API" prominently positioned at the top as a separate API target section
  - Better Visual Hierarchy: REST API setting now clearly distinguished from content field settings with enhanced styling and clearer labeling
  - Complete Settings Parity: Network admin now has complete feature parity with site-level settings

- **Added:**
  - API Targets Section: New dedicated section for API-related settings with clear visual separation
  - Enhanced Descriptions: More detailed explanations for REST API functionality

### 1.0.7 - 2025-08-30

**Network Admin Access Fixes**

- **Fixed:**
  - Network Admin Access: Fixed network-level settings not being accessible in network admin
  - Network Options Initialization: Added automatic initialization of network options when not present
  - Admin Menu Registration: Improved admin menu registration for both multisite and single-site installations
  - Constants Usage: Updated remaining hardcoded option names to use constants

- **Enhanced:**
  - Cross-Platform Compatibility: Network admin settings now available in both multisite and single-site WordPress
  - Mu-Plugin Support: Better support for installations where this is used as a must-use plugin
  - Option Management: More robust option handling and initialization

### 1.0.6 - 2025-08-30

**WPGraphQL Dependency Management**

- **Added:**
  - WPGraphQL Dependency Check: Plugin now requires WPGraphQL to be installed and activated
  - Version Compatibility Check: Ensures WPGraphQL version 1.0.0 or higher is installed
  - Enhanced Error Handling: Automatic plugin deactivation if dependencies are not met
  - User-Friendly Notices: Clear admin notices explaining dependency requirements

- **Changed:**
  - Plugin Description: Updated to clearly indicate WPGraphQL requirement
  - Plugin Headers: Added "Requires Plugins: wp-graphql" header for WordPress 6.5+ compatibility
  - Initialization Logic: Plugin only initializes when dependencies are satisfied

- **Security:**
  - Safe Deactivation: Plugin safely deactivates itself if WPGraphQL is not available

### 1.0.5 - 2025-08-30

**Plugin Action Links & REST API Control**

- **Added:**
  - Plugin Action Links: Added "Settings" links to the Installed Plugins page for easier access to plugin settings
  - REST API Control: Added option to enable/disable content filtering for WordPress REST API
  - Enhanced UI: Plugin settings are now directly accessible from both regular and network admin plugin pages

- **Changed:**
  - REST API filtering is now optional and can be controlled via plugin settings
  - Plugin action links provide direct access to settings from the Plugins page

- **Optimized:**
  - Code Constants: Added constants for option names to reduce repetition and improve maintainability
  - Helper Methods: Created `get_site_data()` helper method to reduce duplicate database calls
  - Version Consistency: Fixed version number consistency between plugin header and internal constants
  - Performance: Optimized option retrieval patterns throughout the codebase

### 1.0.4 - 2025-08-30

**Admin Menu Fixes**

- **Fixed:**
  - Admin Menu Visibility: Fixed issue where admin menus weren't showing properly in multisite environments
  - Settings Registration: Simplified logic for when site-level settings should be available
  - Network Settings Access: Ensured network admin settings are always accessible when appropriate
  - Site Override Logic: Fixed overly restrictive conditions that prevented site menus from appearing

- **Changed:**
  - Simplified admin menu and settings registration logic to be more permissive by default
  - Site-level settings now show unless network settings are explicitly enforced
  - Removed complex logic that was preventing proper menu display

### 1.0.3 - 2025-08-30

**Enhanced Multisite Management**

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
