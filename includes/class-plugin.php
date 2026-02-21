<?php
/**
 * Main Plugin Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Controller.
 */
class Plugin {
	/**
	 * Single instance of the class.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Cron instance.
	 *
	 * @var Cron
	 */
	private $cron;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		// Initialize admin interface.
		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		// Initialize cron.
		$this->cron = new Cron();
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-link-sweeper',
			false,
			dirname( WP_LINK_SWEEPER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return WP_LINK_SWEEPER_VERSION;
	}
}
