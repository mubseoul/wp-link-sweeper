<?php
/**
 * Auto-Fix Rules Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages auto-fix rules for broken links.
 */
class Rules {
	/**
	 * Option name for storing rules.
	 */
	const OPTION_NAME = 'wp_link_sweeper_rules';

	/**
	 * Get all rules.
	 *
	 * @return array Rules.
	 */
	public static function get_rules() {
		$rules = get_option( self::OPTION_NAME, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Add a new rule.
	 *
	 * @param array $rule Rule data.
	 * @return array Result.
	 */
	public static function add_rule( $rule ) {
		$defaults = array(
			'pattern'     => '',
			'replacement' => '',
			'match_type'  => 'contains',
			'enabled'     => true,
		);

		$rule = wp_parse_args( $rule, $defaults );

		// Validate rule.
		$validation = self::validate_rule( $rule );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		$rules = self::get_rules();

		// Add ID and timestamp.
		$rule['id']         = uniqid( 'rule_' );
		$rule['created_at'] = current_time( 'mysql' );

		$rules[] = $rule;

		update_option( self::OPTION_NAME, $rules );

		return array(
			'success' => true,
			'message' => __( 'Rule added successfully.', 'wp-link-sweeper' ),
			'rule'    => $rule,
		);
	}

	/**
	 * Update a rule.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $updates Updated data.
	 * @return array Result.
	 */
	public static function update_rule( $rule_id, $updates ) {
		$rules = self::get_rules();
		$found = false;

		foreach ( $rules as $key => $rule ) {
			if ( $rule['id'] === $rule_id ) {
				$rules[ $key ] = array_merge( $rule, $updates );

				// Validate updated rule.
				$validation = self::validate_rule( $rules[ $key ] );
				if ( ! $validation['valid'] ) {
					return array(
						'success' => false,
						'message' => $validation['message'],
					);
				}

				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return array(
				'success' => false,
				'message' => __( 'Rule not found.', 'wp-link-sweeper' ),
			);
		}

		update_option( self::OPTION_NAME, $rules );

		return array(
			'success' => true,
			'message' => __( 'Rule updated successfully.', 'wp-link-sweeper' ),
		);
	}

	/**
	 * Delete a rule.
	 *
	 * @param string $rule_id Rule ID.
	 * @return array Result.
	 */
	public static function delete_rule( $rule_id ) {
		$rules = self::get_rules();
		$found = false;

		foreach ( $rules as $key => $rule ) {
			if ( $rule['id'] === $rule_id ) {
				unset( $rules[ $key ] );
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			return array(
				'success' => false,
				'message' => __( 'Rule not found.', 'wp-link-sweeper' ),
			);
		}

		update_option( self::OPTION_NAME, array_values( $rules ) );

		return array(
			'success' => true,
			'message' => __( 'Rule deleted successfully.', 'wp-link-sweeper' ),
		);
	}

	/**
	 * Validate a rule.
	 *
	 * @param array $rule Rule data.
	 * @return array Validation result.
	 */
	private static function validate_rule( $rule ) {
		if ( empty( $rule['pattern'] ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Pattern is required.', 'wp-link-sweeper' ),
			);
		}

		if ( empty( $rule['replacement'] ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Replacement URL is required.', 'wp-link-sweeper' ),
			);
		}

		// Validate replacement URL format.
		if ( ! filter_var( $rule['replacement'], FILTER_VALIDATE_URL ) ) {
			return array(
				'valid'   => false,
				'message' => __( 'Replacement must be a valid URL.', 'wp-link-sweeper' ),
			);
		}

		return array(
			'valid' => true,
		);
	}

	/**
	 * Apply rules to broken links.
	 *
	 * @param array $options Options for applying rules.
	 * @return array Result.
	 */
	public static function apply_rules( $options = array() ) {
		$defaults = array(
			'dry_run' => false,
		);

		$options = wp_parse_args( $options, $defaults );

		$rules = self::get_rules();
		$active_rules = array_filter(
			$rules,
			function ( $rule ) {
				return ! empty( $rule['enabled'] );
			}
		);

		if ( empty( $active_rules ) ) {
			return array(
				'success' => false,
				'message' => __( 'No active rules to apply.', 'wp-link-sweeper' ),
			);
		}

		// Get all broken links.
		$broken_links = DB::get_broken_links(
			array(
				'status'   => 'broken',
				'per_page' => 9999,
			)
		);

		$matched_links = array();
		$replacements  = array();

		foreach ( $broken_links as $link ) {
			foreach ( $active_rules as $rule ) {
				if ( self::url_matches_pattern( $link->url, $rule['pattern'], $rule['match_type'] ) ) {
					$matched_links[] = $link;

					// Calculate replacement URL.
					$new_url = self::apply_pattern_replacement( $link->url, $rule['pattern'], $rule['replacement'], $rule['match_type'] );

					$replacements[] = array(
						'old_url' => $link->url,
						'new_url' => $new_url,
						'rule_id' => $rule['id'],
					);
					break; // Apply only first matching rule.
				}
			}
		}

		if ( empty( $replacements ) ) {
			return array(
				'success' => true,
				'message' => __( 'No broken links matched any rules.', 'wp-link-sweeper' ),
				'matched' => 0,
			);
		}

		// If dry run, just return what would be replaced.
		if ( $options['dry_run'] ) {
			return array(
				'success'      => true,
				'dry_run'      => true,
				'matched'      => count( $replacements ),
				'replacements' => $replacements,
				'message'      => sprintf(
					/* translators: %d: Number of links that would be replaced */
					__( '%d links would be replaced.', 'wp-link-sweeper' ),
					count( $replacements )
				),
			);
		}

		// Apply replacements.
		$replaced_count = 0;
		foreach ( $replacements as $replacement ) {
			$result = Replacer::execute_replacement(
				array(
					'find'       => $replacement['old_url'],
					'replace'    => $replacement['new_url'],
					'match_type' => 'equals',
				)
			);

			if ( $result['success'] ) {
				$replaced_count++;
			}
		}

		return array(
			'success'        => true,
			'replaced_count' => $replaced_count,
			'message'        => sprintf(
				/* translators: %d: Number of links replaced */
				__( 'Successfully replaced %d links using rules.', 'wp-link-sweeper' ),
				$replaced_count
			),
		);
	}

	/**
	 * Check if URL matches pattern.
	 *
	 * @param string $url URL to check.
	 * @param string $pattern Pattern to match.
	 * @param string $match_type Match type.
	 * @return bool True if matches.
	 */
	private static function url_matches_pattern( $url, $pattern, $match_type ) {
		// Handle wildcard patterns.
		if ( strpos( $pattern, '*' ) !== false ) {
			$regex = '/^' . str_replace( array( '/', '*' ), array( '\/', '.*' ), $pattern ) . '$/i';
			return (bool) preg_match( $regex, $url );
		}

		// Standard matching.
		switch ( $match_type ) {
			case 'equals':
				return strcasecmp( $url, $pattern ) === 0;
			case 'starts_with':
				return stripos( $url, $pattern ) === 0;
			case 'ends_with':
				return substr( strtolower( $url ), -strlen( $pattern ) ) === strtolower( $pattern );
			case 'contains':
			default:
				return stripos( $url, $pattern ) !== false;
		}
	}

	/**
	 * Apply pattern replacement to URL.
	 *
	 * @param string $url Original URL.
	 * @param string $pattern Pattern.
	 * @param string $replacement Replacement.
	 * @param string $match_type Match type.
	 * @return string New URL.
	 */
	private static function apply_pattern_replacement( $url, $pattern, $replacement, $match_type ) {
		// Handle wildcard patterns (e.g., oldsite.com/* -> newsite.com/*).
		if ( strpos( $pattern, '*' ) !== false && strpos( $replacement, '*' ) !== false ) {
			$pattern_parts = explode( '*', $pattern );
			$replacement_parts = explode( '*', $replacement );

			// Simple wildcard replacement.
			if ( count( $pattern_parts ) === 2 && count( $replacement_parts ) === 2 ) {
				$prefix = $pattern_parts[0];
				if ( stripos( $url, $prefix ) === 0 ) {
					$captured = substr( $url, strlen( $prefix ) );
					return $replacement_parts[0] . $captured . $replacement_parts[1];
				}
			}
		}

		// Simple string replacement.
		return str_ireplace( $pattern, $replacement, $url );
	}

	/**
	 * Get rule by ID.
	 *
	 * @param string $rule_id Rule ID.
	 * @return array|null Rule or null.
	 */
	public static function get_rule( $rule_id ) {
		$rules = self::get_rules();

		foreach ( $rules as $rule ) {
			if ( $rule['id'] === $rule_id ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Toggle rule enabled status.
	 *
	 * @param string $rule_id Rule ID.
	 * @return array Result.
	 */
	public static function toggle_rule( $rule_id ) {
		$rule = self::get_rule( $rule_id );

		if ( ! $rule ) {
			return array(
				'success' => false,
				'message' => __( 'Rule not found.', 'wp-link-sweeper' ),
			);
		}

		return self::update_rule( $rule_id, array( 'enabled' => ! $rule['enabled'] ) );
	}
}
