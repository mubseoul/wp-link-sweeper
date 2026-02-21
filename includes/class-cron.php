<?php
/**
 * Cron Management Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheduled scans via WP Cron.
 */
class Cron {
	/**
	 * Cron hook name.
	 */
	const HOOK_NAME = 'wp_link_sweeper_scheduled_scan';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::HOOK_NAME, array( $this, 'run_scheduled_scan' ) );
	}

	/**
	 * Schedule cron job.
	 *
	 * @param string $schedule Schedule frequency (hourly, daily, weekly).
	 */
	public static function schedule( $schedule ) {
		// Unschedule first.
		self::unschedule();

		if ( 'disabled' === $schedule ) {
			return;
		}

		// Validate schedule.
		$schedules = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
		if ( ! in_array( $schedule, $schedules, true ) ) {
			$schedule = 'daily';
		}

		// Schedule event.
		if ( ! wp_next_scheduled( self::HOOK_NAME ) ) {
			wp_schedule_event( time(), $schedule, self::HOOK_NAME );
		}
	}

	/**
	 * Unschedule cron job.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_NAME );
		}
	}

	/**
	 * Run scheduled scan.
	 */
	public function run_scheduled_scan() {
		// Check if already scanning.
		if ( Utils::is_scanning() ) {
			return;
		}

		// Start scan.
		Scanner::start_scan();

		// Process posts in batches.
		$batch_size = get_option( 'wp_link_sweeper_batch_size_posts', 20 );
		$offset     = 0;
		$has_more   = true;

		while ( $has_more ) {
			$result = Scanner::scan_posts_batch( $offset );
			$has_more = $result['has_more'];
			$offset   = $result['next_offset'];

			// Prevent infinite loops.
			if ( $offset > 10000 ) {
				break;
			}
		}

		// Check URLs in batches.
		$has_more_urls = true;
		$iterations    = 0;

		while ( $has_more_urls ) {
			$result = Scanner::check_urls_batch();
			$has_more_urls = $result['has_more'];
			$iterations++;

			// Prevent infinite loops.
			if ( $iterations > 1000 ) {
				break;
			}
		}

		// Complete scan.
		Scanner::complete_scan();

		// Apply auto-fix rules if enabled.
		if ( get_option( 'wp_link_sweeper_auto_apply_rules', false ) ) {
			Rules::apply_rules();
		}
	}

	/**
	 * Get next scheduled run time.
	 *
	 * @return string|false Next run time or false.
	 */
	public static function get_next_run() {
		$timestamp = wp_next_scheduled( self::HOOK_NAME );
		if ( ! $timestamp ) {
			return false;
		}

		return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i:s' );
	}

	/**
	 * Trigger manual run (for testing).
	 */
	public static function trigger_manual_run() {
		do_action( self::HOOK_NAME );
	}
}
