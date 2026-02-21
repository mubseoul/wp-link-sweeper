=== WP Link Sweeper - Broken Link Finder + Auto Fixer ===
Contributors: mubseoul
Tags: broken links, link checker, seo, maintenance, links
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, safe broken link scanner with bulk find/replace and auto-fix rules for WordPress content.

== Description ==

WP Link Sweeper is a comprehensive broken link detection and management plugin for WordPress. It scans your posts, pages, and WooCommerce products to find broken links, then provides powerful tools to fix them efficiently.

= Key Features =

**Smart Scanning**
* Scans posts, pages, and custom post types
* Detects links in href attributes and plain text
* Identifies 404s, timeouts, DNS errors, SSL issues
* Tracks redirects and response times
* AJAX-based batching prevents timeouts

**Link Management**
* Detailed reporting with filters
* Recheck individual links
* Ignore false positives
* View which posts contain each link
* See HTTP status codes and error types

**Bulk Operations**
* Find & Replace URLs across content
* Preview changes before applying
* Undo last operation
* Multiple match types (contains, equals, starts with, ends with)
* Code blocks protected from replacement

**Auto-Fix Rules**
* Create pattern-based replacement rules
* Wildcard support (oldsite.com/* → newsite.com/*)
* Enable/disable rules without deleting
* Preview rule application

**Performance**
* Configurable rate limiting
* Batch processing for large sites
* Scheduled automatic scans (WP Cron)
* Real-time progress tracking
* Stop scans anytime

**Developer-Friendly**
* Clean OOP structure with namespaces
* PSR-4 autoloading
* Custom database tables
* Security-first design
* Translation-ready

= Perfect For =

* Site migrations (changing domains)
* Content maintenance
* SEO optimization
* Link auditing
* Quality assurance

= WooCommerce Compatible =

Automatically detects and scans WooCommerce product descriptions when WooCommerce is active.

= Privacy & Data =

This plugin performs HTTP requests to external websites to check link validity. No data is sent to third-party services. All data is stored in your WordPress database.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-link-sweeper/` or install via WordPress plugin installer
2. Activate the plugin through the 'Plugins' menu
3. Go to Tools → Link Sweeper
4. Click "Start New Scan" to begin

== Frequently Asked Questions ==

= Does this work with page builders? =

Yes! The scanner extracts links from post_content which includes content from most page builders. Advanced page builder support (Elementor, etc.) is planned for version 1.1.

= Will scanning slow down my site? =

No. Scanning happens in the background using AJAX batches. The rate limiter ensures you don't overwhelm external servers. You can adjust batch sizes and rate limits in settings.

= Can I undo a bulk replacement? =

Yes! The plugin stores undo data for the last bulk operation. Click "Undo Last Replacement" on the Find & Replace page.

= Does this check images? =

Currently, it only checks URLs in links (href attributes and plain text URLs). Image checking is planned for a future version.

= What happens on uninstall? =

By default, data is preserved. If you enable "Delete data on uninstall" in settings, all plugin data (tables, options) will be removed when you uninstall.

= Can I schedule automatic scans? =

Yes! Go to Settings and choose a schedule (hourly, daily, weekly). The plugin uses WordPress Cron.

= Does this work on multisite? =

Currently, it works on individual sites. Full multisite network support is planned for a Pro version.

== Screenshots ==

1. Dashboard - Quick stats and scan controls
2. Broken Links - Filterable table of all broken links
3. Find & Replace - Preview and execute bulk URL replacements
4. Auto-Fix Rules - Create pattern-based replacement rules
5. Settings - Configure scanning and checking behavior

== Changelog ==

= 1.0.0 =
* Initial release
* Core scanning functionality
* Broken link detection and reporting
* Find & Replace tool with preview and undo
* Auto-fix rules with wildcard support
* Scheduled scans via WP Cron
* WooCommerce product support
* Configurable rate limiting
* Batch processing for performance
* i18n ready

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and start finding broken links!

== Privacy Policy ==

WP Link Sweeper performs HTTP requests to external websites to verify link validity. These requests:
* Include a configurable User-Agent header
* Do not send any personal data
* Do not track or store data about external sites beyond HTTP status codes
* Respect rate limiting to avoid server overload

All scan data is stored locally in your WordPress database.

== Support ==

* Documentation: [GitHub Wiki](https://github.com/mubseoul/wp-link-sweeper/wiki)
* Issues: [GitHub Issues](https://github.com/mubseoul/wp-link-sweeper/issues)
* Support Forum: [WordPress.org](https://wordpress.org/support/plugin/wp-link-sweeper)

== Roadmap ==

Planned features for future versions:
* CSV export
* Email notifications
* SEO plugin integration (Yoast, RankMath)
* Broken image detection
* Link history tracking
* Bulk ignore by domain
* Advanced scheduling

== Credits ==

Developed by [Mubseoul](https://mubseoul.com) with ❤️ for the WordPress community.
