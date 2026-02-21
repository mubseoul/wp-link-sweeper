<?php
/**
 * Plugin Name: WP Link Sweeper – Broken Link Finder + Auto Fixer
 * Plugin URI: https://github.com/mubseoul/wp-link-sweeper
 * Description: Fast, safe broken link scanner with bulk find/replace and auto-fix rules for WordPress content.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Mubseoul
 * Author URI: https://mubseoul.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-link-sweeper
 * Domain Path: /languages
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_LINK_SWEEPER_VERSION', '1.0.0' );
define( 'WP_LINK_SWEEPER_PLUGIN_FILE', __FILE__ );
define( 'WP_LINK_SWEEPER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_LINK_SWEEPER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_LINK_SWEEPER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'WP_Link_Sweeper\\';
		$base_dir = WP_LINK_SWEEPER_PLUGIN_DIR . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Main plugin initialization function.
 */
function init() {
	// Load text domain.
	load_plugin_textdomain( 'wp-link-sweeper', false, dirname( WP_LINK_SWEEPER_PLUGIN_BASENAME ) . '/languages' );

	// Initialize the plugin.
	Plugin::get_instance();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Activation hook.
 */
function activate() {
	require_once WP_LINK_SWEEPER_PLUGIN_DIR . 'includes/class-db.php';
	DB::create_tables();

	// Set default options.
	$defaults = array(
		'scan_post_types'          => array( 'post', 'page' ),
		'rate_limit'               => 5,
		'user_agent'               => 'WP Link Sweeper/' . WP_LINK_SWEEPER_VERSION . ' (WordPress)',
		'request_timeout'          => 10,
		'normalize_remove_utm'     => true,
		'normalize_ignore_fragment' => true,
		'batch_size_posts'         => 20,
		'batch_size_urls'          => 10,
		'cron_schedule'            => 'disabled',
		'delete_data_on_uninstall' => false,
	);

	foreach ( $defaults as $key => $value ) {
		if ( get_option( 'wp_link_sweeper_' . $key ) === false ) {
			add_option( 'wp_link_sweeper_' . $key, $value );
		}
	}

	// Schedule cron if needed.
	$schedule = get_option( 'wp_link_sweeper_cron_schedule', 'disabled' );
	if ( $schedule !== 'disabled' ) {
		require_once WP_LINK_SWEEPER_PLUGIN_DIR . 'includes/class-cron.php';
		Cron::schedule( $schedule );
	}

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 */
function deactivate() {
	require_once WP_LINK_SWEEPER_PLUGIN_DIR . 'includes/class-cron.php';
	Cron::unschedule();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Uninstall hook (cleanup if option is set).
 */
function uninstall() {
	if ( get_option( 'wp_link_sweeper_delete_data_on_uninstall' ) ) {
		global $wpdb;

		// Drop custom tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ls_links" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ls_occurrences" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ls_operations" );

		// Delete all plugin options.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wp_link_sweeper_%'" );
	}
}

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\uninstall' );
