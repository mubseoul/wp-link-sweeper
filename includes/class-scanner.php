<?php
/**
 * Content Scanner Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans WordPress content for links.
 */
class Scanner {
	/**
	 * Start a new scan.
	 *
	 * @return array Scan initialization data.
	 */
	public static function start_scan() {
		// Check if already scanning.
		if ( Utils::is_scanning() ) {
			return array(
				'success' => false,
				'message' => __( 'A scan is already in progress.', 'wp-link-sweeper' ),
			);
		}

		// Set scanning status.
		Utils::set_scanning_status( true );

		// Clear old occurrences.
		DB::clear_occurrences();

		// Get total posts to scan.
		$post_types  = get_option( 'wp_link_sweeper_scan_post_types', array( 'post', 'page' ) );
		$total_posts = self::get_total_posts( $post_types );

		// Initialize progress.
		Utils::update_scan_progress(
			array(
				'total_posts'      => $total_posts,
				'processed_posts'  => 0,
				'total_urls'       => 0,
				'processed_urls'   => 0,
				'current_step'     => 'scanning_content',
			)
		);

		return array(
			'success'     => true,
			'total_posts' => $total_posts,
			'message'     => __( 'Scan started successfully.', 'wp-link-sweeper' ),
		);
	}

	/**
	 * Scan a batch of posts.
	 *
	 * @param int $offset Offset for batch.
	 * @return array Batch results.
	 */
	public static function scan_posts_batch( $offset = 0 ) {
		$batch_size = get_option( 'wp_link_sweeper_batch_size_posts', 20 );
		$post_types = get_option( 'wp_link_sweeper_scan_post_types', array( 'post', 'page' ) );

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		$posts = get_posts( $args );

		$urls_found = array();
		$posts_processed = 0;

		foreach ( $posts as $post ) {
			$urls = self::scan_post( $post );
			$urls_found = array_merge( $urls_found, $urls );
			$posts_processed++;
		}

		// Update progress.
		$progress = Utils::get_scan_progress();
		Utils::update_scan_progress(
			array(
				'processed_posts' => $progress['processed_posts'] + $posts_processed,
				'total_urls'      => $progress['total_urls'] + count( array_unique( array_column( $urls_found, 'url' ) ) ),
			)
		);

		return array(
			'success'         => true,
			'posts_processed' => $posts_processed,
			'urls_found'      => count( $urls_found ),
			'has_more'        => count( $posts ) === $batch_size,
			'next_offset'     => $offset + $batch_size,
		);
	}

	/**
	 * Scan a single post for links.
	 *
	 * @param WP_Post $post Post object.
	 * @return array URLs found.
	 */
	public static function scan_post( $post ) {
		$content = $post->post_content;
		$urls    = Utils::extract_urls( $content );

		$found_urls = array();

		foreach ( $urls as $url_data ) {
			$url = $url_data['url'];

			// Save link to database.
			$link_id = DB::save_link(
				array(
					'url' => $url,
				)
			);

			if ( $link_id ) {
				// Save occurrence.
				DB::save_occurrence(
					array(
						'link_id'   => $link_id,
						'post_id'   => $post->ID,
						'post_type' => $post->post_type,
						'field'     => 'content',
						'context'   => $url_data['context'],
					)
				);

				$found_urls[] = array(
					'url'     => $url,
					'link_id' => $link_id,
					'context' => $url_data['context'],
				);
			}
		}

		return $found_urls;
	}

	/**
	 * Get unchecked URLs for checking.
	 *
	 * @param int $limit Number of URLs to get.
	 * @return array URLs to check.
	 */
	public static function get_unchecked_urls( $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_links';

		$urls = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, url FROM {$table}
				WHERE last_checked_at IS NULL
				AND is_ignored = 0
				ORDER BY id ASC
				LIMIT %d",
				$limit
			)
		);

		return $urls;
	}

	/**
	 * Check a batch of URLs.
	 *
	 * @return array Batch results.
	 */
	public static function check_urls_batch() {
		$batch_size = get_option( 'wp_link_sweeper_batch_size_urls', 10 );
		$urls       = self::get_unchecked_urls( $batch_size );

		if ( empty( $urls ) ) {
			return array(
				'success'   => true,
				'checked'   => 0,
				'has_more'  => false,
				'completed' => true,
			);
		}

		$checked = 0;

		foreach ( $urls as $url_obj ) {
			$result = Link_Checker::check_url( $url_obj->url );

			// Update link in database.
			$update_data = array(
				'url'              => $url_obj->url,
				'last_status'      => $result['last_status'],
				'last_code'        => $result['last_code'],
				'last_checked_at'  => current_time( 'mysql' ),
				'final_url'        => $result['final_url'],
				'redirect_count'   => $result['redirect_count'],
				'error_type'       => $result['error_type'],
				'response_time_ms' => $result['response_time_ms'],
			);

			DB::save_link( $update_data );
			$checked++;

			// Update progress.
			$progress = Utils::get_scan_progress();
			Utils::update_scan_progress(
				array(
					'processed_urls' => $progress['processed_urls'] + 1,
				)
			);
		}

		// Check if more URLs to check.
		$remaining = self::get_unchecked_urls( 1 );
		$has_more  = ! empty( $remaining );

		return array(
			'success'  => true,
			'checked'  => $checked,
			'has_more' => $has_more,
			'completed' => ! $has_more,
		);
	}

	/**
	 * Complete the scan.
	 *
	 * @return array Completion status.
	 */
	public static function complete_scan() {
		Utils::set_scanning_status( false );

		$stats = DB::get_stats();

		return array(
			'success' => true,
			'message' => __( 'Scan completed successfully.', 'wp-link-sweeper' ),
			'stats'   => $stats,
		);
	}

	/**
	 * Stop an ongoing scan.
	 *
	 * @return array Stop status.
	 */
	public static function stop_scan() {
		Utils::set_scanning_status( false );
		Utils::clear_scan_progress();

		return array(
			'success' => true,
			'message' => __( 'Scan stopped.', 'wp-link-sweeper' ),
		);
	}

	/**
	 * Get total posts to scan.
	 *
	 * @param array $post_types Post types to scan.
	 * @return int Total posts.
	 */
	private static function get_total_posts( $post_types ) {
		$counts = array();
		foreach ( $post_types as $post_type ) {
			$count = wp_count_posts( $post_type );
			if ( isset( $count->publish ) ) {
				$counts[] = $count->publish;
			}
		}
		return array_sum( $counts );
	}

	/**
	 * Get scan status.
	 *
	 * @return array Current scan status.
	 */
	public static function get_scan_status() {
		return array(
			'is_scanning' => Utils::is_scanning(),
			'progress'    => Utils::get_scan_progress(),
		);
	}
}
