<?php
/**
 * Settings Template
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$available_post_types       = Utils::get_available_post_types();
$scan_post_types            = get_option( 'wp_link_sweeper_scan_post_types', array( 'post', 'page' ) );
$rate_limit                 = get_option( 'wp_link_sweeper_rate_limit', 5 );
$user_agent                 = get_option( 'wp_link_sweeper_user_agent', 'WP Link Sweeper/' . WP_LINK_SWEEPER_VERSION . ' (WordPress)' );
$request_timeout            = get_option( 'wp_link_sweeper_request_timeout', 10 );
$normalize_remove_utm       = get_option( 'wp_link_sweeper_normalize_remove_utm', true );
$normalize_ignore_fragment  = get_option( 'wp_link_sweeper_normalize_ignore_fragment', true );
$batch_size_posts           = get_option( 'wp_link_sweeper_batch_size_posts', 20 );
$batch_size_urls            = get_option( 'wp_link_sweeper_batch_size_urls', 10 );
$cron_schedule              = get_option( 'wp_link_sweeper_cron_schedule', 'disabled' );
$delete_data_on_uninstall   = get_option( 'wp_link_sweeper_delete_data_on_uninstall', false );

if ( get_transient( 'wp_link_sweeper_settings_updated' ) ) {
	delete_transient( 'wp_link_sweeper_settings_updated' );
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Settings saved successfully.', 'wp-link-sweeper' ); ?></p>
	</div>
	<?php
}
?>

<div class="ls-settings">
	<form method="post" action="">
		<?php wp_nonce_field( 'wp_link_sweeper_save_settings', 'wp_link_sweeper_settings_nonce' ); ?>

		<h2><?php esc_html_e( 'Scanning Settings', 'wp-link-sweeper' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Post Types to Scan', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<fieldset>
						<?php foreach ( $available_post_types as $pt_name => $pt_label ) : ?>
							<label>
								<input type="checkbox" name="scan_post_types[]" value="<?php echo esc_attr( $pt_name ); ?>"
									<?php checked( in_array( $pt_name, $scan_post_types, true ) ); ?>>
								<?php echo esc_html( $pt_label ); ?>
							</label><br>
						<?php endforeach; ?>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Select which post types to scan for links.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="batch-size-posts"><?php esc_html_e( 'Posts Batch Size', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<input type="number" id="batch-size-posts" name="batch_size_posts" value="<?php echo esc_attr( $batch_size_posts ); ?>" min="5" max="100" class="small-text">
					<p class="description">
						<?php esc_html_e( 'Number of posts to process per batch (5-100). Lower values prevent timeouts on slow servers.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="batch-size-urls"><?php esc_html_e( 'URLs Batch Size', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<input type="number" id="batch-size-urls" name="batch_size_urls" value="<?php echo esc_attr( $batch_size_urls ); ?>" min="5" max="50" class="small-text">
					<p class="description">
						<?php esc_html_e( 'Number of URLs to check per batch (5-50).', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Link Checking Settings', 'wp-link-sweeper' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rate-limit"><?php esc_html_e( 'Rate Limit', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<input type="number" id="rate-limit" name="rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" min="1" max="20" class="small-text">
					<span><?php esc_html_e( 'requests per second', 'wp-link-sweeper' ); ?></span>
					<p class="description">
						<?php esc_html_e( 'Maximum number of HTTP requests per second (1-20). Lower values are more polite to target servers.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="request-timeout"><?php esc_html_e( 'Request Timeout', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<input type="number" id="request-timeout" name="request_timeout" value="<?php echo esc_attr( $request_timeout ); ?>" min="5" max="60" class="small-text">
					<span><?php esc_html_e( 'seconds', 'wp-link-sweeper' ); ?></span>
					<p class="description">
						<?php esc_html_e( 'How long to wait for a response before timing out (5-60 seconds).', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="user-agent"><?php esc_html_e( 'User Agent', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<input type="text" id="user-agent" name="user_agent" value="<?php echo esc_attr( $user_agent ); ?>" class="large-text">
					<p class="description">
						<?php esc_html_e( 'User agent string sent with HTTP requests.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'URL Normalization', 'wp-link-sweeper' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Normalization Options', 'wp-link-sweeper' ); ?>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="normalize_remove_utm" value="1" <?php checked( $normalize_remove_utm ); ?>>
							<?php esc_html_e( 'Remove UTM parameters (utm_source, utm_medium, etc.)', 'wp-link-sweeper' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="normalize_ignore_fragment" value="1" <?php checked( $normalize_ignore_fragment ); ?>>
							<?php esc_html_e( 'Ignore URL fragments (#section)', 'wp-link-sweeper' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'These options help avoid duplicate URLs that differ only in parameters or fragments.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Scheduled Scans', 'wp-link-sweeper' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="cron-schedule"><?php esc_html_e( 'Schedule', 'wp-link-sweeper' ); ?></label>
				</th>
				<td>
					<select id="cron-schedule" name="cron_schedule">
						<option value="disabled" <?php selected( $cron_schedule, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'wp-link-sweeper' ); ?></option>
						<option value="hourly" <?php selected( $cron_schedule, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'wp-link-sweeper' ); ?></option>
						<option value="twicedaily" <?php selected( $cron_schedule, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'wp-link-sweeper' ); ?></option>
						<option value="daily" <?php selected( $cron_schedule, 'daily' ); ?>><?php esc_html_e( 'Daily', 'wp-link-sweeper' ); ?></option>
						<option value="weekly" <?php selected( $cron_schedule, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wp-link-sweeper' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Automatically scan for broken links on a schedule.', 'wp-link-sweeper' ); ?>
					</p>
					<?php
					$next_run = Cron::get_next_run();
					if ( $next_run ) :
						?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Next scheduled run time */
								esc_html__( 'Next scheduled scan: %s', 'wp-link-sweeper' ),
								'<strong>' . esc_html( $next_run ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Data Management', 'wp-link-sweeper' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Uninstall Options', 'wp-link-sweeper' ); ?>
				</th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_data_on_uninstall ); ?>>
						<?php esc_html_e( 'Delete all plugin data when uninstalling', 'wp-link-sweeper' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'WARNING: This will permanently delete all scanned links, rules, and settings when you uninstall the plugin.', 'wp-link-sweeper' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'wp-link-sweeper' ) ); ?>
	</form>
</div>
