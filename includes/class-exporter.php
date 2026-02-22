<?php
/**
 * CSV Exporter Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV export functionality.
 */
class Exporter {
	/**
	 * Export broken links to CSV.
	 *
	 * @param array $args Export arguments.
	 * @return void
	 */
	public static function export_to_csv( $args = array() ) {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-link-sweeper' ) );
		}

		$defaults = array(
			'status'    => 'all',
			'post_type' => 'all',
			'domain'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get all matching links (no pagination).
		$links = DB::get_broken_links(
			array_merge(
				$args,
				array(
					'per_page' => 9999,
					'paged'    => 1,
				)
			)
		);

		if ( empty( $links ) ) {
			wp_die( esc_html__( 'No links found to export.', 'wp-link-sweeper' ) );
		}

		// Prepare CSV data.
		$csv_data = self::prepare_csv_data( $links );

		// Send headers.
		self::send_csv_headers();

		// Output CSV.
		self::output_csv( $csv_data );

		exit;
	}

	/**
	 * Prepare CSV data from links.
	 *
	 * @param array $links Links to export.
	 * @return array CSV data.
	 */
	private static function prepare_csv_data( $links ) {
		$data = array();

		// Add header row.
		$data[] = array(
			__( 'URL', 'wp-link-sweeper' ),
			__( 'Status Code', 'wp-link-sweeper' ),
			__( 'Status', 'wp-link-sweeper' ),
			__( 'Error Type', 'wp-link-sweeper' ),
			__( 'Redirects', 'wp-link-sweeper' ),
			__( 'Final URL', 'wp-link-sweeper' ),
			__( 'Response Time (ms)', 'wp-link-sweeper' ),
			__( 'Occurrences', 'wp-link-sweeper' ),
			__( 'Found In Posts', 'wp-link-sweeper' ),
			__( 'Last Checked', 'wp-link-sweeper' ),
		);

		// Add data rows.
		foreach ( $links as $link ) {
			$status = self::get_link_status_text( $link );
			$posts  = self::get_posts_list( $link );

			$data[] = array(
				$link->url,
				$link->last_code ?? 'N/A',
				$status,
				$link->error_type ?? '',
				$link->redirect_count ?? 0,
				$link->final_url ?? '',
				$link->response_time_ms ?? '',
				$link->occurrence_count ?? 0,
				$posts,
				$link->last_checked_at ?? '',
			);
		}

		return $data;
	}

	/**
	 * Get link status as text.
	 *
	 * @param object $link Link object.
	 * @return string Status text.
	 */
	private static function get_link_status_text( $link ) {
		if ( $link->error_type ) {
			return 'Error: ' . $link->error_type;
		}

		if ( $link->last_code >= 400 ) {
			return 'Broken';
		}

		if ( $link->redirect_count > 0 ) {
			return 'Redirect';
		}

		if ( $link->last_code >= 200 && $link->last_code < 400 ) {
			return 'OK';
		}

		return 'Unknown';
	}

	/**
	 * Get list of posts containing the link.
	 *
	 * @param object $link Link object.
	 * @return string Comma-separated post titles.
	 */
	private static function get_posts_list( $link ) {
		if ( empty( $link->sample_posts ) ) {
			return '';
		}

		$post_ids = explode( ',', $link->sample_posts );
		$titles   = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$titles[] = $post->post_title . ' (ID: ' . $post_id . ')';
			}
		}

		// If there are more posts than shown.
		if ( $link->occurrence_count > count( $titles ) ) {
			$remaining = $link->occurrence_count - count( $titles );
			$titles[]  = sprintf(
				/* translators: %d: Number of additional posts */
				__( '... and %d more', 'wp-link-sweeper' ),
				$remaining
			);
		}

		return implode( ', ', $titles );
	}

	/**
	 * Send CSV headers.
	 */
	private static function send_csv_headers() {
		$filename = sprintf(
			'broken-links-%s.csv',
			gmdate( 'Y-m-d-His' )
		);

		// Prevent caching.
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Cache-Control: private', false );

		// Set CSV headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// UTF-8 BOM for Excel compatibility.
		echo "\xEF\xBB\xBF";
	}

	/**
	 * Output CSV data.
	 *
	 * @param array $data CSV data array.
	 */
	private static function output_csv( $data ) {
		$output = fopen( 'php://output', 'w' );

		foreach ( $data as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
	}

	/**
	 * Export stats summary to CSV.
	 *
	 * @return void
	 */
	public static function export_stats_to_csv() {
		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-link-sweeper' ) );
		}

		$stats = DB::get_stats();

		$data = array();

		// Add header.
		$data[] = array(
			__( 'Metric', 'wp-link-sweeper' ),
			__( 'Value', 'wp-link-sweeper' ),
		);

		// Add stats.
		$data[] = array( __( 'Total Links', 'wp-link-sweeper' ), $stats['total_links'] );
		$data[] = array( __( 'Broken Links', 'wp-link-sweeper' ), $stats['broken_links'] );
		$data[] = array( __( 'Working Links', 'wp-link-sweeper' ), $stats['ok_links'] );
		$data[] = array( __( 'Redirects', 'wp-link-sweeper' ), $stats['redirects'] );
		$data[] = array( __( 'Last Scan', 'wp-link-sweeper' ), $stats['last_scan'] ?? 'Never' );

		// Send headers.
		$filename = sprintf(
			'link-stats-%s.csv',
			gmdate( 'Y-m-d-His' )
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo "\xEF\xBB\xBF";

		// Output CSV.
		self::output_csv( $data );

		exit;
	}
}
