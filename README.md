# WP Link Sweeper  Broken Link Finder + Auto Fixer

A fast, safe WordPress plugin that scans your content for broken links and provides powerful tools to fix them efficiently.

## Features

### Core Functionality

- **Smart Link Scanner**: Scans posts, pages, and WooCommerce products for broken links
- **Comprehensive Detection**: Finds links in `<a href>` tags and plain URLs in content
- **Intelligent Checking**: Uses HEAD requests first, falls back to GET when needed
- **Status Reporting**: Detects 404s, timeouts, DNS errors, SSL issues, and redirects
- **Batch Processing**: AJAX-based scanning prevents timeouts on large sites

### Link Management

- **Detailed Reporting**: View all broken links with status codes, occurrence counts, and affected posts
- **Advanced Filtering**: Filter by status, post type, domain, and more
- **Quick Actions**: Recheck individual links, ignore false positives
- **Response Metrics**: See response times and redirect chains

### Bulk Operations

- **Find & Replace**: Safely replace URLs across your content
- **Match Types**: Contains, equals, starts with, ends with
- **Preview Changes**: See exactly what will be modified before applying
- **Undo Support**: Revert the last bulk operation if needed
- **Safe Replacement**: Protects code blocks from modification

### Auto-Fix Rules

- **Pattern Matching**: Create rules for automatic URL replacement
- **Wildcard Support**: Use `*` to preserve URL paths (e.g., `oldsite.com/*` ’ `newsite.com/*`)
- **Rule Management**: Enable/disable rules without deleting them
- **Dry Run**: Preview rule application before executing

### Performance & Reliability

- **Rate Limiting**: Configurable request throttling (be kind to servers)
- **Batch Processing**: Processes posts and URLs in manageable chunks
- **WP Cron Support**: Schedule automatic scans (hourly, daily, weekly)
- **Progress Tracking**: Real-time progress updates during scans
- **Stop Anytime**: Safely interrupt long-running scans

### Developer-Friendly

- **Clean OOP Structure**: PSR-4 autoloading, namespaces
- **Custom DB Tables**: Efficient storage with proper indexing
- **Security First**: Capability checks, nonces, input sanitization
- **i18n Ready**: Translation-ready strings
- **Hooks & Filters**: Extensible architecture (coming in v1.1)

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.6 or higher

## Installation

### From ZIP File

1. Download the plugin ZIP file
2. Go to WordPress admin ’ Plugins ’ Add New ’ Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin
5. Navigate to Tools ’ Link Sweeper

### Manual Installation

1. Upload the `wp-link-sweeper` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Navigate to Tools ’ Link Sweeper

### From Source (Development)

```bash
git clone https://github.com/yourusername/wp-link-sweeper.git
cd wp-link-sweeper
# If using Composer (optional)
composer install
```

## Usage Guide

### Running Your First Scan

1. Go to **Tools ’ Link Sweeper**
2. Click **Start New Scan**
3. The scanner will:
   - Extract all URLs from your content
   - Check each URL's availability
   - Report broken links, redirects, and errors

### Viewing Broken Links

1. Click the **Broken Links** tab
2. Use filters to narrow results:
   - Status (Broken, OK, Redirects)
   - Post Type
   - Domain
3. See which posts contain each broken link
4. Recheck or ignore individual links

### Finding & Replacing URLs

1. Go to the **Find & Replace** tab
2. Enter the URL to find (e.g., `http://oldsite.com`)
3. Enter the replacement URL (e.g., `https://newsite.com`)
4. Select match type and post types
5. Click **Preview Changes** to see what will happen
6. Click **Execute Replacement** to apply changes
7. Use **Undo** if you need to revert

### Creating Auto-Fix Rules

1. Go to the **Auto-Fix Rules** tab
2. Add a new rule:
   - Pattern: `oldsite.com/*`
   - Replacement: `newsite.com/*`
   - Match Type: Contains
3. Click **Add Rule**
4. Preview or apply rules to broken links

### Configuring Settings

1. Go to the **Settings** tab
2. Configure:
   - **Post Types**: Which content to scan
   - **Rate Limit**: Requests per second (default: 5)
   - **Timeout**: How long to wait for responses
   - **Normalization**: UTM removal, fragment handling
   - **Scheduled Scans**: Automatic scanning frequency
   - **Batch Sizes**: Adjust for server performance

## Testing Checklist

### Basic Functionality

- [ ] Install and activate plugin
- [ ] Access admin page at Tools ’ Link Sweeper
- [ ] All tabs load without errors
- [ ] Settings save successfully

### Scanning

- [ ] Start a new scan
- [ ] Progress bar updates in real-time
- [ ] Scan completes without timeout
- [ ] Stop scan button works
- [ ] Dashboard shows accurate statistics
- [ ] Broken links appear in table
- [ ] Can filter by status, post type, domain
- [ ] Pagination works for large result sets

### Link Checking

- [ ] Recheck individual link updates status
- [ ] Ignore link removes from list
- [ ] Status badges display correctly
- [ ] HTTP codes are accurate
- [ ] Response times are recorded
- [ ] Redirects are detected and counted

### Find & Replace

- [ ] Preview shows affected posts
- [ ] Preview shows sample changes
- [ ] Execute replacement works
- [ ] Code blocks are protected
- [ ] Undo restores original content
- [ ] Match types work (contains, equals, etc.)

### Auto-Fix Rules

- [ ] Can add new rule
- [ ] Can delete rule
- [ ] Can enable/disable rule
- [ ] Preview shows what will change
- [ ] Apply rules replaces URLs correctly
- [ ] Wildcard patterns work

### WooCommerce Integration

- [ ] Product post type appears if WooCommerce active
- [ ] Can scan product descriptions
- [ ] Can replace URLs in products

### Scheduled Scans

- [ ] Can set cron schedule
- [ ] Next run time displays correctly
- [ ] Scheduled scan executes (wait for schedule or test with WP-CLI)

### Performance

- [ ] Large sites (1000+ posts) scan without timeout
- [ ] Batch processing works correctly
- [ ] Rate limiting prevents server overload
- [ ] Database queries are efficient

### Security

- [ ] Non-admin users cannot access
- [ ] AJAX requests require nonce
- [ ] SQL injection attempts fail
- [ ] XSS attempts are escaped

### Uninstall

- [ ] Can enable "Delete data on uninstall"
- [ ] Deactivate plugin
- [ ] Uninstall plugin
- [ ] Database tables removed (if option enabled)
- [ ] Options removed (if option enabled)

## Roadmap

### Version 1.1 (Planned)

- [ ] Export broken links to CSV
- [ ] Email notifications for broken links
- [ ] Scan custom fields (Yoast, RankMath)
- [ ] Deeper Elementor/page builder support
- [ ] Hooks and filters for developers
- [ ] Bulk ignore by domain
- [ ] Link status history tracking
- [ ] Multi-language support improvements

### Pro Ideas (Not in Free Version)

- [ ] Multi-site network support
- [ ] Advanced scheduling (specific times)
- [ ] Automatic replacement with dry-run logs
- [ ] Integration with Google Search Console
- [ ] Broken image detection
- [ ] External link monitoring service
- [ ] Priority support
- [ ] White-label options

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use PHP 8.0+ features where appropriate
- Write clear, documented code
- Include PHPDoc blocks for classes and methods

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/wp-link-sweeper/issues)
- **Documentation**: [Wiki](https://github.com/yourusername/wp-link-sweeper/wiki)
- **WordPress.org**: [Support Forum](https://wordpress.org/support/plugin/wp-link-sweeper)

## License

GPLv2 or later. See [LICENSE](LICENSE) file.

## Credits

Developed by [Your Name](https://yourwebsite.com)

Special thanks to:
- WordPress community
- Contributors (see [CONTRIBUTORS.md](CONTRIBUTORS.md))

## Changelog

### 1.0.0 - 2024-01-XX

- Initial release
- Core scanning functionality
- Find & replace tool
- Auto-fix rules
- Scheduled scans
- WooCommerce support

---

**Note**: This plugin performs HTTP requests to external websites. Always respect robots.txt and be mindful of rate limiting.
