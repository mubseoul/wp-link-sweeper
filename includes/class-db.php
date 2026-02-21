<?php
/**
 * Database Management Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database operations for custom tables.
 */
class DB {
	/**
	 * Create custom database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		// Links table.
		$sql[] = "CREATE TABLE {$prefix}ls_links (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url text NOT NULL,
			normalized_url varchar(2048) NOT NULL,
			last_status varchar(50) DEFAULT NULL,
			last_code int(11) DEFAULT NULL,
			last_checked_at datetime DEFAULT NULL,
			final_url text DEFAULT NULL,
			redirect_count int(11) DEFAULT 0,
			error_type varchar(100) DEFAULT NULL,
			response_time_ms int(11) DEFAULT NULL,
			is_ignored tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY normalized_url (normalized_url(191)),
			KEY last_status (last_status(20)),
			KEY last_checked_at (last_checked_at),
			KEY is_ignored (is_ignored)
		) $charset_collate;";

		// Occurrences table.
		$sql[] = "CREATE TABLE {$prefix}ls_occurrences (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			link_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL,
			post_type varchar(100) NOT NULL,
			field varchar(100) DEFAULT 'content',
			context varchar(50) DEFAULT 'href',
			first_seen_at datetime DEFAULT CURRENT_TIMESTAMP,
			last_seen_at datetime DEFAULT CURRENT_TIMESTAMP,
			occurrences_count int(11) DEFAULT 1,
			PRIMARY KEY  (id),
			KEY link_id (link_id),
			KEY post_id (post_id),
			KEY post_type (post_type(20)),
			UNIQUE KEY unique_occurrence (link_id, post_id, field(20), context(20))
		) $charset_collate;";

		// Operations table (for undo functionality).
		$sql[] = "CREATE TABLE {$prefix}ls_operations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			user_id bigint(20) unsigned NOT NULL,
			payload_json longtext DEFAULT NULL,
			undo_json longtext DEFAULT NULL,
			undo_available tinyint(1) DEFAULT 1,
			PRIMARY KEY  (id),
			KEY type (type(20)),
			KEY user_id (user_id),
			KEY undo_available (undo_available)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version.
		update_option( 'wp_link_sweeper_db_version', '1.0.0' );
	}

	/**
	 * Insert or update a link.
	 *
	 * @param array $data Link data.
	 * @return int|false Link ID or false on failure.
	 */
	public static function save_link( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_links';

		$normalized = Utils::normalize_url( $data['url'] );

		// Check if link exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE normalized_url = %s",
				$normalized
			)
		);

		$link_data = array(
			'url'            => $data['url'],
			'normalized_url' => $normalized,
			'last_status'    => $data['last_status'] ?? null,
			'last_code'      => $data['last_code'] ?? null,
			'last_checked_at' => $data['last_checked_at'] ?? current_time( 'mysql' ),
			'final_url'      => $data['final_url'] ?? null,
			'redirect_count' => $data['redirect_count'] ?? 0,
			'error_type'     => $data['error_type'] ?? null,
			'response_time_ms' => $data['response_time_ms'] ?? null,
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				$link_data,
				array( 'id' => $existing->id ),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d' ),
				array( '%d' )
			);
			return $existing->id;
		} else {
			$wpdb->insert(
				$table,
				$link_data,
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d' )
			);
			return $wpdb->insert_id;
		}
	}

	/**
	 * Save an occurrence.
	 *
	 * @param array $data Occurrence data.
	 * @return bool Success.
	 */
	public static function save_occurrence( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_occurrences';

		$occurrence_data = array(
			'link_id'    => $data['link_id'],
			'post_id'    => $data['post_id'],
			'post_type'  => $data['post_type'],
			'field'      => $data['field'] ?? 'content',
			'context'    => $data['context'] ?? 'href',
			'last_seen_at' => current_time( 'mysql' ),
		);

		// Try to update first.
		$updated = $wpdb->update(
			$table,
			array(
				'last_seen_at'      => current_time( 'mysql' ),
				'occurrences_count' => $wpdb->prepare( 'occurrences_count + 1' ),
			),
			array(
				'link_id'   => $data['link_id'],
				'post_id'   => $data['post_id'],
				'field'     => $data['field'] ?? 'content',
				'context'   => $data['context'] ?? 'href',
			)
		);

		if ( ! $updated ) {
			// Insert new occurrence.
			$wpdb->insert(
				$table,
				$occurrence_data,
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Get link by URL.
	 *
	 * @param string $url URL to find.
	 * @return object|null Link object or null.
	 */
	public static function get_link_by_url( $url ) {
		global $wpdb;
		$table      = $wpdb->prefix . 'ls_links';
		$normalized = Utils::normalize_url( $url );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE normalized_url = %s",
				$normalized
			)
		);
	}

	/**
	 * Get all broken links with occurrences.
	 *
	 * @param array $args Query arguments.
	 * @return array Links with occurrence data.
	 */
	public static function get_broken_links( $args = array() ) {
		global $wpdb;
		$links_table       = $wpdb->prefix . 'ls_links';
		$occurrences_table = $wpdb->prefix . 'ls_occurrences';

		$defaults = array(
			'status'    => 'all',
			'post_type' => 'all',
			'domain'    => '',
			'orderby'   => 'last_checked_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'paged'     => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		// Filter by status.
		if ( 'broken' === $args['status'] ) {
			$where[] = "l.last_code >= 400 OR l.error_type IS NOT NULL";
		} elseif ( 'ok' === $args['status'] ) {
			$where[] = "l.last_code BETWEEN 200 AND 399";
		} elseif ( 'redirect' === $args['status'] ) {
			$where[] = "l.redirect_count > 0";
		}

		// Exclude ignored.
		$where[] = "l.is_ignored = 0";

		// Filter by domain.
		if ( ! empty( $args['domain'] ) ) {
			$where[] = $wpdb->prepare( "l.url LIKE %s", '%' . $wpdb->esc_like( $args['domain'] ) . '%' );
		}

		// Filter by post type.
		if ( 'all' !== $args['post_type'] ) {
			$where[] = $wpdb->prepare( "o.post_type = %s", $args['post_type'] );
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['paged'] - 1 ) * $args['per_page'];

		$query = "
			SELECT
				l.*,
				COUNT(DISTINCT o.post_id) as occurrence_count,
				GROUP_CONCAT(DISTINCT o.post_id ORDER BY o.post_id LIMIT 3) as sample_posts
			FROM {$links_table} l
			LEFT JOIN {$occurrences_table} o ON l.id = o.link_id
			WHERE {$where_clause}
			GROUP BY l.id
			ORDER BY {$args['orderby']} {$args['order']}
			LIMIT {$args['per_page']} OFFSET {$offset}
		";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total count of links matching criteria.
	 *
	 * @param array $args Query arguments.
	 * @return int Count.
	 */
	public static function get_links_count( $args = array() ) {
		global $wpdb;
		$links_table       = $wpdb->prefix . 'ls_links';
		$occurrences_table = $wpdb->prefix . 'ls_occurrences';

		$defaults = array(
			'status'    => 'all',
			'post_type' => 'all',
			'domain'    => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( 'broken' === $args['status'] ) {
			$where[] = "l.last_code >= 400 OR l.error_type IS NOT NULL";
		} elseif ( 'ok' === $args['status'] ) {
			$where[] = "l.last_code BETWEEN 200 AND 399";
		} elseif ( 'redirect' === $args['status'] ) {
			$where[] = "l.redirect_count > 0";
		}

		$where[] = "l.is_ignored = 0";

		if ( ! empty( $args['domain'] ) ) {
			$where[] = $wpdb->prepare( "l.url LIKE %s", '%' . $wpdb->esc_like( $args['domain'] ) . '%' );
		}

		if ( 'all' !== $args['post_type'] ) {
			$where[] = $wpdb->prepare( "o.post_type = %s", $args['post_type'] );
		}

		$where_clause = implode( ' AND ', $where );

		$query = "
			SELECT COUNT(DISTINCT l.id)
			FROM {$links_table} l
			LEFT JOIN {$occurrences_table} o ON l.id = o.link_id
			WHERE {$where_clause}
		";

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array Statistics.
	 */
	public static function get_stats() {
		global $wpdb;
		$links_table = $wpdb->prefix . 'ls_links';

		$total_links = $wpdb->get_var( "SELECT COUNT(*) FROM {$links_table} WHERE is_ignored = 0" );
		$broken_links = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$links_table}
			WHERE (last_code >= 400 OR error_type IS NOT NULL) AND is_ignored = 0"
		);
		$ok_links = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$links_table}
			WHERE last_code BETWEEN 200 AND 399 AND is_ignored = 0"
		);
		$redirects = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$links_table}
			WHERE redirect_count > 0 AND is_ignored = 0"
		);
		$last_scan = $wpdb->get_var(
			"SELECT MAX(last_checked_at) FROM {$links_table}"
		);

		return array(
			'total_links'  => (int) $total_links,
			'broken_links' => (int) $broken_links,
			'ok_links'     => (int) $ok_links,
			'redirects'    => (int) $redirects,
			'last_scan'    => $last_scan,
		);
	}

	/**
	 * Mark link as ignored.
	 *
	 * @param int $link_id Link ID.
	 * @return bool Success.
	 */
	public static function ignore_link( $link_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_links';

		return $wpdb->update(
			$table,
			array( 'is_ignored' => 1 ),
			array( 'id' => $link_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Save operation for undo functionality.
	 *
	 * @param array $data Operation data.
	 * @return int|false Operation ID or false.
	 */
	public static function save_operation( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_operations';

		// Limit undo JSON size to 5MB.
		$undo_json = isset( $data['undo_json'] ) ? wp_json_encode( $data['undo_json'] ) : null;
		if ( $undo_json && strlen( $undo_json ) > 5242880 ) {
			$undo_json        = null;
			$undo_available = 0;
		} else {
			$undo_available = 1;
		}

		$wpdb->insert(
			$table,
			array(
				'type'           => $data['type'],
				'user_id'        => get_current_user_id(),
				'payload_json'   => wp_json_encode( $data['payload'] ?? array() ),
				'undo_json'      => $undo_json,
				'undo_available' => $undo_available,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get last operation for undo.
	 *
	 * @return object|null Operation object or null.
	 */
	public static function get_last_operation() {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_operations';

		return $wpdb->get_row(
			"SELECT * FROM {$table}
			WHERE undo_available = 1
			ORDER BY created_at DESC
			LIMIT 1"
		);
	}

	/**
	 * Mark operation as used (undo no longer available).
	 *
	 * @param int $operation_id Operation ID.
	 * @return bool Success.
	 */
	public static function mark_operation_used( $operation_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_operations';

		return $wpdb->update(
			$table,
			array( 'undo_available' => 0 ),
			array( 'id' => $operation_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Clear all occurrences (before rescan).
	 *
	 * @return bool Success.
	 */
	public static function clear_occurrences() {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_occurrences';
		return $wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Delete old operations (keep last 10).
	 */
	public static function cleanup_old_operations() {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_operations';

		$wpdb->query(
			"DELETE FROM {$table}
			WHERE id NOT IN (
				SELECT id FROM (
					SELECT id FROM {$table} ORDER BY created_at DESC LIMIT 10
				) as t
			)"
		);
	}
}
