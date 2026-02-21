<?php
/**
 * Utility Functions Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility helper functions.
 */
class Utils {
	/**
	 * Normalize URL for comparison.
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	public static function normalize_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		$url = trim( $url );

		// Parse URL.
		$parts = wp_parse_url( $url );
		if ( ! $parts ) {
			return $url;
		}

		// Lowercase scheme and host.
		$normalized = '';
		if ( isset( $parts['scheme'] ) ) {
			$normalized .= strtolower( $parts['scheme'] ) . '://';
		}
		if ( isset( $parts['host'] ) ) {
			$normalized .= strtolower( $parts['host'] );
		}
		if ( isset( $parts['port'] ) ) {
			// Omit default ports.
			if ( ! ( ( $parts['scheme'] === 'http' && $parts['port'] == 80 ) ||
					( $parts['scheme'] === 'https' && $parts['port'] == 443 ) ) ) {
				$normalized .= ':' . $parts['port'];
			}
		}
		if ( isset( $parts['path'] ) ) {
			$path = $parts['path'];
			// Remove trailing slash except for root.
			if ( $path !== '/' ) {
				$path = rtrim( $path, '/' );
			}
			$normalized .= $path;
		}

		// Handle query string.
		if ( isset( $parts['query'] ) ) {
			$query = $parts['query'];

			// Remove UTM parameters if setting enabled.
			if ( get_option( 'wp_link_sweeper_normalize_remove_utm', true ) ) {
				parse_str( $query, $params );
				$params = array_filter(
					$params,
					function ( $key ) {
						return strpos( $key, 'utm_' ) !== 0;
					},
					ARRAY_FILTER_USE_KEY
				);
				$query = http_build_query( $params );
			}

			if ( ! empty( $query ) ) {
				$normalized .= '?' . $query;
			}
		}

		// Optionally ignore fragments.
		if ( ! get_option( 'wp_link_sweeper_normalize_ignore_fragment', true ) ) {
			if ( isset( $parts['fragment'] ) ) {
				$normalized .= '#' . $parts['fragment'];
			}
		}

		return $normalized;
	}

	/**
	 * Extract all URLs from HTML content.
	 *
	 * @param string $content HTML content.
	 * @return array Array of URLs with context.
	 */
	public static function extract_urls( $content ) {
		$urls = array();

		// Extract URLs from href attributes.
		preg_match_all(
			'/<a[^>]+href=["\']([^"\']+)["\']/i',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $match ) {
			$url = trim( $match[1] );
			if ( self::is_valid_url( $url ) ) {
				$urls[] = array(
					'url'     => $url,
					'context' => 'href',
				);
			}
		}

		// Extract plain URLs from text (not in code blocks).
		$content_without_code = preg_replace( '/<(pre|code)[^>]*>.*?<\/\1>/is', '', $content );

		preg_match_all(
			'#\bhttps?://[^\s<>"{}|\\^`\[\]]+#i',
			$content_without_code,
			$matches
		);

		foreach ( $matches[0] as $url ) {
			$url = trim( $url, '.,;:!?' );
			if ( self::is_valid_url( $url ) ) {
				$urls[] = array(
					'url'     => $url,
					'context' => 'plain',
				);
			}
		}

		return $urls;
	}

	/**
	 * Check if URL is valid and should be scanned.
	 *
	 * @param string $url URL to check.
	 * @return bool True if valid.
	 */
	public static function is_valid_url( $url ) {
		// Must be http or https.
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		// Filter out localhost and local IPs.
		if ( preg_match( '#^https?://(localhost|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)#i', $url ) ) {
			return false;
		}

		// Filter out relative URLs that somehow passed.
		if ( strpos( $url, '//' ) === 0 ) {
			return false;
		}

		// Validate URL format.
		$parsed = wp_parse_url( $url );
		if ( ! isset( $parsed['host'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get available post types for scanning.
	 *
	 * @return array Post types.
	 */
	public static function get_available_post_types() {
		$post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);

		$available = array();
		foreach ( $post_types as $post_type ) {
			// Skip attachments.
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$available[ $post_type->name ] = $post_type->label;
		}

		return $available;
	}

	/**
	 * Format HTTP status code with label.
	 *
	 * @param int $code HTTP status code.
	 * @return string Formatted status.
	 */
	public static function format_status_code( $code ) {
		$codes = array(
			200 => 'OK',
			201 => 'Created',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			410 => 'Gone',
			429 => 'Too Many Requests',
			500 => 'Internal Server Error',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
		);

		$label = isset( $codes[ $code ] ) ? $codes[ $code ] : 'Unknown';
		return sprintf( '%d %s', $code, $label );
	}

	/**
	 * Get status badge HTML.
	 *
	 * @param object $link Link object.
	 * @return string HTML badge.
	 */
	public static function get_status_badge( $link ) {
		if ( $link->error_type ) {
			return '<span class="ls-badge ls-badge-error">' . esc_html( $link->error_type ) . '</span>';
		}

		if ( $link->last_code >= 400 ) {
			return '<span class="ls-badge ls-badge-broken">' . self::format_status_code( $link->last_code ) . '</span>';
		}

		if ( $link->redirect_count > 0 ) {
			return '<span class="ls-badge ls-badge-redirect">' . self::format_status_code( $link->last_code ) . ' (' . $link->redirect_count . ' redirects)</span>';
		}

		if ( $link->last_code >= 200 && $link->last_code < 400 ) {
			return '<span class="ls-badge ls-badge-ok">' . self::format_status_code( $link->last_code ) . '</span>';
		}

		return '<span class="ls-badge ls-badge-unknown">Not checked</span>';
	}

	/**
	 * Truncate text.
	 *
	 * @param string $text Text to truncate.
	 * @param int    $length Maximum length.
	 * @return string Truncated text.
	 */
	public static function truncate( $text, $length = 50 ) {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}
		return substr( $text, 0, $length ) . '...';
	}

	/**
	 * Get time ago string.
	 *
	 * @param string $datetime MySQL datetime string.
	 * @return string Time ago.
	 */
	public static function time_ago( $datetime ) {
		if ( ! $datetime ) {
			return __( 'Never', 'wp-link-sweeper' );
		}

		return sprintf(
			/* translators: %s: Human-readable time difference */
			__( '%s ago', 'wp-link-sweeper' ),
			human_time_diff( strtotime( $datetime ), current_time( 'timestamp' ) )
		);
	}

	/**
	 * Check if scanning is currently in progress.
	 *
	 * @return bool True if scanning.
	 */
	public static function is_scanning() {
		return (bool) get_transient( 'wp_link_sweeper_scanning' );
	}

	/**
	 * Set scanning status.
	 *
	 * @param bool $status Scanning status.
	 */
	public static function set_scanning_status( $status ) {
		if ( $status ) {
			set_transient( 'wp_link_sweeper_scanning', true, HOUR_IN_SECONDS );
		} else {
			delete_transient( 'wp_link_sweeper_scanning' );
		}
	}

	/**
	 * Get scan progress.
	 *
	 * @return array Progress data.
	 */
	public static function get_scan_progress() {
		$progress = get_transient( 'wp_link_sweeper_scan_progress' );
		if ( ! $progress ) {
			return array(
				'total_posts'      => 0,
				'processed_posts'  => 0,
				'total_urls'       => 0,
				'processed_urls'   => 0,
				'current_step'     => 'idle',
			);
		}
		return $progress;
	}

	/**
	 * Update scan progress.
	 *
	 * @param array $data Progress data to update.
	 */
	public static function update_scan_progress( $data ) {
		$current = self::get_scan_progress();
		$updated = array_merge( $current, $data );
		set_transient( 'wp_link_sweeper_scan_progress', $updated, HOUR_IN_SECONDS );
	}

	/**
	 * Clear scan progress.
	 */
	public static function clear_scan_progress() {
		delete_transient( 'wp_link_sweeper_scan_progress' );
	}

	/**
	 * Sanitize URL for replacement.
	 *
	 * @param string $url URL to sanitize.
	 * @return string Sanitized URL.
	 */
	public static function sanitize_url_for_replacement( $url ) {
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}
}
