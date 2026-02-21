<?php
/**
 * Dashboard Template
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats    = DB::get_stats();
$scanning = Utils::is_scanning();
$progress = Utils::get_scan_progress();
?>

<div class="ls-dashboard">
	<div class="ls-stats-grid">
		<div class="ls-stat-card">
			<div class="ls-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_links'] ) ); ?></div>
			<div class="ls-stat-label"><?php esc_html_e( 'Total Links', 'wp-link-sweeper' ); ?></div>
		</div>

		<div class="ls-stat-card ls-stat-broken">
			<div class="ls-stat-value"><?php echo esc_html( number_format_i18n( $stats['broken_links'] ) ); ?></div>
			<div class="ls-stat-label"><?php esc_html_e( 'Broken Links', 'wp-link-sweeper' ); ?></div>
		</div>

		<div class="ls-stat-card ls-stat-ok">
			<div class="ls-stat-value"><?php echo esc_html( number_format_i18n( $stats['ok_links'] ) ); ?></div>
			<div class="ls-stat-label"><?php esc_html_e( 'Working Links', 'wp-link-sweeper' ); ?></div>
		</div>

		<div class="ls-stat-card ls-stat-redirect">
			<div class="ls-stat-value"><?php echo esc_html( number_format_i18n( $stats['redirects'] ) ); ?></div>
			<div class="ls-stat-label"><?php esc_html_e( 'Redirects', 'wp-link-sweeper' ); ?></div>
		</div>
	</div>

	<div class="ls-scan-section">
		<h2><?php esc_html_e( 'Scan Status', 'wp-link-sweeper' ); ?></h2>

		<?php if ( $stats['last_scan'] ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: Time since last scan */
					esc_html__( 'Last scan: %s', 'wp-link-sweeper' ),
					'<strong>' . esc_html( Utils::time_ago( $stats['last_scan'] ) ) . '</strong>'
				);
				?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'No scan has been performed yet.', 'wp-link-sweeper' ); ?></p>
		<?php endif; ?>

		<div id="ls-scan-progress" class="ls-scan-progress" style="display: <?php echo $scanning ? 'block' : 'none'; ?>;">
			<div class="ls-progress-bar">
				<div class="ls-progress-fill" id="ls-progress-fill" style="width: 0%;"></div>
			</div>
			<p class="ls-progress-text" id="ls-progress-text"><?php esc_html_e( 'Initializing...', 'wp-link-sweeper' ); ?></p>
		</div>

		<div class="ls-scan-actions">
			<?php if ( ! $scanning ) : ?>
				<button type="button" class="button button-primary button-large" id="ls-start-scan">
					<?php esc_html_e( 'Start New Scan', 'wp-link-sweeper' ); ?>
				</button>
			<?php else : ?>
				<button type="button" class="button button-secondary" id="ls-stop-scan">
					<?php esc_html_e( 'Stop Scan', 'wp-link-sweeper' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $stats['broken_links'] > 0 ) : ?>
		<div class="ls-quick-actions">
			<h2><?php esc_html_e( 'Quick Actions', 'wp-link-sweeper' ); ?></h2>
			<ul>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'broken-links', admin_url( 'tools.php?page=wp-link-sweeper' ) ) ); ?>" class="button">
						<?php esc_html_e( 'View Broken Links', 'wp-link-sweeper' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'replace', admin_url( 'tools.php?page=wp-link-sweeper' ) ) ); ?>" class="button">
						<?php esc_html_e( 'Find & Replace URLs', 'wp-link-sweeper' ); ?>
					</a>
				</li>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'rules', admin_url( 'tools.php?page=wp-link-sweeper' ) ) ); ?>" class="button">
						<?php esc_html_e( 'Manage Auto-Fix Rules', 'wp-link-sweeper' ); ?>
					</a>
				</li>
			</ul>
		</div>
	<?php endif; ?>

	<?php
	$next_run = Cron::get_next_run();
	if ( $next_run ) :
		?>
		<div class="ls-cron-info">
			<h3><?php esc_html_e( 'Scheduled Scan', 'wp-link-sweeper' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: Next scheduled run date */
					esc_html__( 'Next automatic scan: %s', 'wp-link-sweeper' ),
					'<strong>' . esc_html( $next_run ) . '</strong>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>
</div>
