<?php
/**
 * Auto-Fix Rules Template
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rules = Rules::get_rules();
?>

<div class="ls-rules">
	<div class="ls-add-rule">
		<h2><?php esc_html_e( 'Add New Rule', 'wp-link-sweeper' ); ?></h2>

		<form id="ls-add-rule-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rule-pattern"><?php esc_html_e( 'Pattern', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<input type="text" id="rule-pattern" name="pattern" class="regular-text" required>
						<p class="description">
							<?php esc_html_e( 'URL pattern to match. Use * for wildcard (e.g., "oldsite.com/*").', 'wp-link-sweeper' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule-replacement"><?php esc_html_e( 'Replacement', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<input type="url" id="rule-replacement" name="replacement" class="regular-text" required>
						<p class="description">
							<?php esc_html_e( 'New URL to replace with. Use * to preserve path (e.g., "newsite.com/*").', 'wp-link-sweeper' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rule-match-type"><?php esc_html_e( 'Match Type', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<select id="rule-match-type" name="match_type">
							<option value="contains"><?php esc_html_e( 'Contains', 'wp-link-sweeper' ); ?></option>
							<option value="equals"><?php esc_html_e( 'Equals', 'wp-link-sweeper' ); ?></option>
							<option value="starts_with"><?php esc_html_e( 'Starts With', 'wp-link-sweeper' ); ?></option>
							<option value="ends_with"><?php esc_html_e( 'Ends With', 'wp-link-sweeper' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add Rule', 'wp-link-sweeper' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="ls-rules-list">
		<h2><?php esc_html_e( 'Existing Rules', 'wp-link-sweeper' ); ?></h2>

		<?php if ( empty( $rules ) ) : ?>
			<p><?php esc_html_e( 'No rules defined yet. Add a rule above to get started.', 'wp-link-sweeper' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pattern', 'wp-link-sweeper' ); ?></th>
						<th><?php esc_html_e( 'Replacement', 'wp-link-sweeper' ); ?></th>
						<th><?php esc_html_e( 'Match Type', 'wp-link-sweeper' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wp-link-sweeper' ); ?></th>
						<th><?php esc_html_e( 'Created', 'wp-link-sweeper' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wp-link-sweeper' ); ?></th>
					</tr>
				</thead>
				<tbody id="ls-rules-tbody">
					<?php foreach ( $rules as $rule ) : ?>
						<tr data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>">
							<td><code><?php echo esc_html( $rule['pattern'] ); ?></code></td>
							<td><code><?php echo esc_html( $rule['replacement'] ); ?></code></td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $rule['match_type'] ) ) ); ?></td>
							<td>
								<?php if ( $rule['enabled'] ) : ?>
									<span class="ls-badge ls-badge-ok"><?php esc_html_e( 'Enabled', 'wp-link-sweeper' ); ?></span>
								<?php else : ?>
									<span class="ls-badge ls-badge-unknown"><?php esc_html_e( 'Disabled', 'wp-link-sweeper' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $rule['created_at'] ) ? Utils::time_ago( $rule['created_at'] ) : '-' ); ?></td>
							<td>
								<button type="button" class="button button-small ls-toggle-rule" data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>">
									<?php echo $rule['enabled'] ? esc_html__( 'Disable', 'wp-link-sweeper' ) : esc_html__( 'Enable', 'wp-link-sweeper' ); ?>
								</button>
								<button type="button" class="button button-small ls-delete-rule" data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'wp-link-sweeper' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="ls-apply-rules">
				<h3><?php esc_html_e( 'Apply Rules', 'wp-link-sweeper' ); ?></h3>
				<p><?php esc_html_e( 'Apply auto-fix rules to all broken links that match the patterns.', 'wp-link-sweeper' ); ?></p>
				<button type="button" id="ls-preview-rules" class="button button-secondary">
					<?php esc_html_e( 'Preview Rule Application', 'wp-link-sweeper' ); ?>
				</button>
				<button type="button" id="ls-apply-rules" class="button button-primary">
					<?php esc_html_e( 'Apply Rules Now', 'wp-link-sweeper' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<div class="ls-help-section">
		<h3><?php esc_html_e( 'How Auto-Fix Rules Work', 'wp-link-sweeper' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Rules automatically replace broken links based on patterns you define.', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Use wildcards (*) to match and preserve URL paths.', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Example: Pattern "oldsite.com/*" → Replacement "newsite.com/*" will change "oldsite.com/page" to "newsite.com/page".', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Rules are applied in order and only the first matching rule is used per link.', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Disable rules temporarily without deleting them.', 'wp-link-sweeper' ); ?></li>
		</ul>
	</div>
</div>
