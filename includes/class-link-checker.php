<?php
/**
 * Link Checker Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performs HTTP requests to check link validity.
 */
class Link_Checker {
	/**
	 * Rate limiter: track last request time.
	 *
	 * @var float
	 */
	private static $last_request_time = 0;

	/**
	 * Check a single URL.
	 *
	 * @param string $url URL to check.
	 * @return array Result data.
	 */
	public static function check_url( $url ) {
		// Rate limiting.
		self::rate_limit();

		$start_time = microtime( true );
		$result     = array(
			'url'            => $url,
			'last_status'    => 'error',
			'last_code'      => null,
			'final_url'      => $url,
			'redirect_count' => 0,
			'error_type'     => null,
			'response_time_ms' => null,
		);

		// Try HEAD request first.
		$response = self::make_request( $url, 'HEAD' );

		// If HEAD fails or returns error, try GET.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$response = self::make_request( $url, 'GET' );
		}

		// Calculate response time.
		$end_time                     = microtime( true );
		$result['response_time_ms'] = (int) ( ( $end_time - $start_time ) * 1000 );

		// Handle errors.
		if ( is_wp_error( $response ) ) {
			$result['error_type'] = self::classify_error( $response );
			$result['last_status'] = 'error';
			return $result;
		}

		// Get response code.
		$code = wp_remote_retrieve_response_code( $response );
		$result['last_code'] = $code;

		// Classify status.
		if ( $code >= 200 && $code < 400 ) {
			$result['last_status'] = 'ok';
		} elseif ( $code >= 400 ) {
			$result['last_status'] = 'broken';
		} else {
			$result['last_status'] = 'unknown';
		}

		// Get final URL after redirects.
		$final_url = wp_remote_retrieve_header( $response, 'location' );
		if ( $final_url ) {
			$result['final_url'] = $final_url;
		}

		// Count redirects (WordPress follows redirects automatically).
		$redirect_count = 0;
		if ( isset( $response['http_response'] ) ) {
			$http_response = $response['http_response'];
			if ( method_exists( $http_response, 'get_response_object' ) ) {
				$response_obj = $http_response->get_response_object();
				if ( isset( $response_obj->redirects ) ) {
					$redirect_count = count( $response_obj->redirects );
				}
			}
		}
		$result['redirect_count'] = $redirect_count;

		return $result;
	}

	/**
	 * Make HTTP request.
	 *
	 * @param string $url URL to request.
	 * @param string $method HTTP method (HEAD or GET).
	 * @return array|WP_Error Response or error.
	 */
	private static function make_request( $url, $method = 'HEAD' ) {
		$timeout    = get_option( 'wp_link_sweeper_request_timeout', 10 );
		$user_agent = get_option( 'wp_link_sweeper_user_agent', 'WP Link Sweeper/' . WP_LINK_SWEEPER_VERSION . ' (WordPress)' );

		$args = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'user-agent'  => $user_agent,
			'headers'     => array(
				'Accept' => '*/*',
			),
			'sslverify'   => true,
		);

		// For GET requests, limit body size.
		if ( 'GET' === $method ) {
			$args['stream']   = false;
			$args['filename'] = null;
		}

		return wp_remote_request( $url, $args );
	}

	/**
	 * Classify error type.
	 *
	 * @param WP_Error $error Error object.
	 * @return string Error type.
	 */
	private static function classify_error( $error ) {
		$code    = $error->get_error_code();
		$message = $error->get_error_message();

		if ( strpos( $message, 'timed out' ) !== false || strpos( $code, 'timeout' ) !== false ) {
			return 'Timeout';
		}

		if ( strpos( $message, 'Could not resolve host' ) !== false || strpos( $code, 'dns' ) !== false ) {
			return 'DNS Error';
		}

		if ( strpos( $message, 'SSL' ) !== false || strpos( $code, 'ssl' ) !== false ) {
			return 'SSL Error';
		}

		if ( strpos( $message, 'Connection refused' ) !== false ) {
			return 'Connection Refused';
		}

		return 'Network Error';
	}

	/**
	 * Rate limiting: enforce delay between requests.
	 */
	private static function rate_limit() {
		$rate_limit = get_option( 'wp_link_sweeper_rate_limit', 5 ); // Requests per second.
		if ( $rate_limit <= 0 ) {
			return;
		}

		$min_interval = 1 / $rate_limit; // Seconds between requests.
		$now          = microtime( true );

		if ( self::$last_request_time > 0 ) {
			$elapsed = $now - self::$last_request_time;
			if ( $elapsed < $min_interval ) {
				$sleep_time = (int) ( ( $min_interval - $elapsed ) * 1000000 );
				usleep( $sleep_time );
			}
		}

		self::$last_request_time = microtime( true );
	}

	/**
	 * Check multiple URLs in batch.
	 *
	 * @param array $urls Array of URLs to check.
	 * @return array Results for each URL.
	 */
	public static function check_urls_batch( $urls ) {
		$results = array();

		foreach ( $urls as $url ) {
			$results[ $url ] = self::check_url( $url );

			// Allow processing to be interrupted.
			if ( connection_aborted() ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Recheck a specific link by ID.
	 *
	 * @param int $link_id Link ID.
	 * @return array|false Result or false on failure.
	 */
	public static function recheck_link( $link_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ls_links';

		$link = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$link_id
			)
		);

		if ( ! $link ) {
			return false;
		}

		$result = self::check_url( $link->url );

		// Update link in database.
		$update_data = array(
			'last_status'      => $result['last_status'],
			'last_code'        => $result['last_code'],
			'last_checked_at'  => current_time( 'mysql' ),
			'final_url'        => $result['final_url'],
			'redirect_count'   => $result['redirect_count'],
			'error_type'       => $result['error_type'],
			'response_time_ms' => $result['response_time_ms'],
		);

		DB::save_link( array_merge( array( 'url' => $link->url ), $update_data ) );

		return $result;
	}
}
