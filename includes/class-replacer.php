<?php
/**
 * URL Replacer Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles find and replace operations for URLs.
 */
class Replacer {
	/**
	 * Preview replacement.
	 *
	 * @param array $args Replacement arguments.
	 * @return array Preview data.
	 */
	public static function preview_replacement( $args ) {
		$defaults = array(
			'find'       => '',
			'replace'    => '',
			'post_types' => array(),
			'match_type' => 'contains', // contains, equals, starts_with, ends_with.
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate inputs.
		if ( empty( $args['find'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Find URL is required.', 'wp-link-sweeper' ),
			);
		}

		// Find matching posts.
		$affected_posts = self::find_affected_posts( $args );

		// Get sample diffs.
		$sample_diffs = array();
		$sample_count = min( 3, count( $affected_posts ) );

		for ( $i = 0; $i < $sample_count; $i++ ) {
			$post_id = $affected_posts[ $i ];
			$post    = get_post( $post_id );

			if ( $post ) {
				$old_content = $post->post_content;
				$new_content = self::replace_in_content( $old_content, $args['find'], $args['replace'], $args['match_type'] );

				$sample_diffs[] = array(
					'post_id'    => $post_id,
					'post_title' => $post->post_title,
					'old_sample' => Utils::truncate( $old_content, 200 ),
					'new_sample' => Utils::truncate( $new_content, 200 ),
				);
			}
		}

		return array(
			'success'        => true,
			'affected_count' => count( $affected_posts ),
			'sample_diffs'   => $sample_diffs,
			'post_ids'       => $affected_posts,
		);
	}

	/**
	 * Execute replacement.
	 *
	 * @param array $args Replacement arguments.
	 * @return array Execution result.
	 */
	public static function execute_replacement( $args ) {
		$defaults = array(
			'find'       => '',
			'replace'    => '',
			'post_types' => array(),
			'match_type' => 'contains',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate inputs.
		if ( empty( $args['find'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Find URL is required.', 'wp-link-sweeper' ),
			);
		}

		// Sanitize replacement URL.
		$args['replace'] = Utils::sanitize_url_for_replacement( $args['replace'] );

		// Find affected posts.
		$affected_posts = self::find_affected_posts( $args );

		if ( empty( $affected_posts ) ) {
			return array(
				'success' => false,
				'message' => __( 'No posts found matching the criteria.', 'wp-link-sweeper' ),
			);
		}

		// Store undo data.
		$undo_data = array();

		$replaced_count = 0;

		foreach ( $affected_posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$old_content = $post->post_content;
			$new_content = self::replace_in_content( $old_content, $args['find'], $args['replace'], $args['match_type'] );

			// Only update if content changed.
			if ( $old_content !== $new_content ) {
				// Store original content for undo.
				$undo_data[ $post_id ] = array(
					'content' => $old_content,
				);

				// Update post.
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $new_content,
					)
				);

				$replaced_count++;
			}
		}

		// Save operation for undo.
		DB::save_operation(
			array(
				'type'    => 'replace',
				'payload' => $args,
				'undo_json' => $undo_data,
			)
		);

		// Trigger rescan of affected URLs.
		self::update_affected_links( $args['find'], $args['replace'] );

		return array(
			'success'        => true,
			'replaced_count' => $replaced_count,
			'message'        => sprintf(
				/* translators: %d: Number of posts updated */
				__( 'Successfully updated %d posts.', 'wp-link-sweeper' ),
				$replaced_count
			),
		);
	}

	/**
	 * Undo last replacement operation.
	 *
	 * @return array Undo result.
	 */
	public static function undo_last_operation() {
		$operation = DB::get_last_operation();

		if ( ! $operation ) {
			return array(
				'success' => false,
				'message' => __( 'No operation available to undo.', 'wp-link-sweeper' ),
			);
		}

		if ( ! $operation->undo_available ) {
			return array(
				'success' => false,
				'message' => __( 'This operation cannot be undone (undo data too large or unavailable).', 'wp-link-sweeper' ),
			);
		}

		$undo_data = json_decode( $operation->undo_json, true );
		if ( empty( $undo_data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Undo data is invalid.', 'wp-link-sweeper' ),
			);
		}

		$restored_count = 0;

		foreach ( $undo_data as $post_id => $data ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $data['content'],
				)
			);
			$restored_count++;
		}

		// Mark operation as used.
		DB::mark_operation_used( $operation->id );

		return array(
			'success'        => true,
			'restored_count' => $restored_count,
			'message'        => sprintf(
				/* translators: %d: Number of posts restored */
				__( 'Successfully restored %d posts.', 'wp-link-sweeper' ),
				$restored_count
			),
		);
	}

	/**
	 * Find affected posts.
	 *
	 * @param array $args Search arguments.
	 * @return array Post IDs.
	 */
	private static function find_affected_posts( $args ) {
		global $wpdb;

		$post_types = ! empty( $args['post_types'] ) ? $args['post_types'] : get_option( 'wp_link_sweeper_scan_post_types', array( 'post', 'page' ) );
		$post_types_in = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";

		// Build LIKE clause based on match type.
		$like_clause = self::build_like_clause( $args['find'], $args['match_type'] );

		$query = "
			SELECT DISTINCT ID
			FROM {$wpdb->posts}
			WHERE post_type IN ({$post_types_in})
			AND post_status = 'publish'
			AND post_content {$like_clause}
			ORDER BY ID ASC
		";

		$results = $wpdb->get_col( $query );

		return array_map( 'intval', $results );
	}

	/**
	 * Build LIKE clause for SQL query.
	 *
	 * @param string $search Search string.
	 * @param string $match_type Match type.
	 * @return string SQL LIKE clause.
	 */
	private static function build_like_clause( $search, $match_type ) {
		global $wpdb;

		$escaped = $wpdb->esc_like( $search );

		switch ( $match_type ) {
			case 'equals':
				return $wpdb->prepare( "LIKE %s", $escaped );
			case 'starts_with':
				return $wpdb->prepare( "LIKE %s", $escaped . '%' );
			case 'ends_with':
				return $wpdb->prepare( "LIKE %s", '%' . $escaped );
			case 'contains':
			default:
				return $wpdb->prepare( "LIKE %s", '%' . $escaped . '%' );
		}
	}

	/**
	 * Replace URLs in content.
	 *
	 * @param string $content Content to process.
	 * @param string $find URL to find.
	 * @param string $replace Replacement URL.
	 * @param string $match_type Match type.
	 * @return string Updated content.
	 */
	private static function replace_in_content( $content, $find, $replace, $match_type ) {
		// Protect code blocks from replacement.
		$protected_blocks = array();
		$content = preg_replace_callback(
			'/<(pre|code)[^>]*>.*?<\/\1>/is',
			function ( $matches ) use ( &$protected_blocks ) {
				$placeholder = '___PROTECTED_BLOCK_' . count( $protected_blocks ) . '___';
				$protected_blocks[ $placeholder ] = $matches[0];
				return $placeholder;
			},
			$content
		);

		// Build regex pattern based on match type.
		$pattern = self::build_regex_pattern( $find, $match_type );

		// Replace in href attributes.
		$content = preg_replace(
			'/<a([^>]+)href=["\'](' . $pattern . ')["\']/i',
			'<a$1href="' . $replace . '"',
			$content
		);

		// Replace plain URLs.
		$content = preg_replace(
			'/\b(' . $pattern . ')\b/i',
			$replace,
			$content
		);

		// Restore protected blocks.
		foreach ( $protected_blocks as $placeholder => $original ) {
			$content = str_replace( $placeholder, $original, $content );
		}

		return $content;
	}

	/**
	 * Build regex pattern for URL matching.
	 *
	 * @param string $url URL to match.
	 * @param string $match_type Match type.
	 * @return string Regex pattern.
	 */
	private static function build_regex_pattern( $url, $match_type ) {
		$escaped = preg_quote( $url, '/' );

		switch ( $match_type ) {
			case 'equals':
				return '^' . $escaped . '$';
			case 'starts_with':
				return '^' . $escaped;
			case 'ends_with':
				return $escaped . '$';
			case 'contains':
			default:
				return $escaped;
		}
	}

	/**
	 * Update affected links in database after replacement.
	 *
	 * @param string $old_url Old URL.
	 * @param string $new_url New URL.
	 */
	private static function update_affected_links( $old_url, $new_url ) {
		// Get link by old URL.
		$old_link = DB::get_link_by_url( $old_url );

		if ( $old_link ) {
			// Mark old link for recheck.
			global $wpdb;
			$table = $wpdb->prefix . 'ls_links';
			$wpdb->update(
				$table,
				array( 'last_checked_at' => null ),
				array( 'id' => $old_link->id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Ensure new URL exists in database.
		DB::save_link( array( 'url' => $new_url ) );
	}
}
