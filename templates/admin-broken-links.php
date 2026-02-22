<?php
/**
 * Broken Links Template
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get filter values.
$status    = isset( $_GET['status_filter'] ) ? sanitize_text_field( $_GET['status_filter'] ) : 'broken';
$post_type = isset( $_GET['post_type_filter'] ) ? sanitize_text_field( $_GET['post_type_filter'] ) : 'all';
$domain    = isset( $_GET['domain_filter'] ) ? sanitize_text_field( $_GET['domain_filter'] ) : '';
$paged     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

// Get links.
$args = array(
	'status'    => $status,
	'post_type' => $post_type,
	'domain'    => $domain,
	'paged'     => $paged,
	'per_page'  => 20,
);

$links       = DB::get_broken_links( $args );
$total_links = DB::get_links_count( $args );
$total_pages = ceil( $total_links / 20 );

$available_post_types = Utils::get_available_post_types();
?>

<div class="ls-broken-links">
	<div class="ls-export-section">
		<button type="button" id="ls-export-csv" class="button button-secondary">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export to CSV', 'wp-link-sweeper' ); ?>
		</button>
		<p class="description">
			<?php esc_html_e( 'Export all links matching current filters to CSV file.', 'wp-link-sweeper' ); ?>
		</p>
	</div>

	<div class="ls-filters">
		<form method="get">
			<input type="hidden" name="page" value="wp-link-sweeper">
			<input type="hidden" name="tab" value="broken-links">

			<label for="status-filter"><?php esc_html_e( 'Status:', 'wp-link-sweeper' ); ?></label>
			<select name="status_filter" id="status-filter">
				<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'wp-link-sweeper' ); ?></option>
				<option value="broken" <?php selected( $status, 'broken' ); ?>><?php esc_html_e( 'Broken', 'wp-link-sweeper' ); ?></option>
				<option value="ok" <?php selected( $status, 'ok' ); ?>><?php esc_html_e( 'OK', 'wp-link-sweeper' ); ?></option>
				<option value="redirect" <?php selected( $status, 'redirect' ); ?>><?php esc_html_e( 'Redirects', 'wp-link-sweeper' ); ?></option>
			</select>

			<label for="post-type-filter"><?php esc_html_e( 'Post Type:', 'wp-link-sweeper' ); ?></label>
			<select name="post_type_filter" id="post-type-filter">
				<option value="all" <?php selected( $post_type, 'all' ); ?>><?php esc_html_e( 'All', 'wp-link-sweeper' ); ?></option>
				<?php foreach ( $available_post_types as $pt_name => $pt_label ) : ?>
					<option value="<?php echo esc_attr( $pt_name ); ?>" <?php selected( $post_type, $pt_name ); ?>>
						<?php echo esc_html( $pt_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="domain-filter"><?php esc_html_e( 'Domain:', 'wp-link-sweeper' ); ?></label>
			<input type="text" name="domain_filter" id="domain-filter" value="<?php echo esc_attr( $domain ); ?>" placeholder="example.com">

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-link-sweeper' ); ?></button>
		</form>
	</div>

	<?php if ( empty( $links ) ) : ?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'No links found matching your criteria.', 'wp-link-sweeper' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'wp-link-sweeper' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-link-sweeper' ); ?></th>
					<th><?php esc_html_e( 'Occurrences', 'wp-link-sweeper' ); ?></th>
					<th><?php esc_html_e( 'Found In', 'wp-link-sweeper' ); ?></th>
					<th><?php esc_html_e( 'Last Checked', 'wp-link-sweeper' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-link-sweeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $links as $link ) : ?>
					<tr data-link-id="<?php echo esc_attr( $link->id ); ?>">
						<td>
							<a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( Utils::truncate( $link->url, 60 ) ); ?>
							</a>
							<?php if ( $link->final_url && $link->final_url !== $link->url ) : ?>
								<br><small><?php esc_html_e( 'Redirects to:', 'wp-link-sweeper' ); ?>
									<a href="<?php echo esc_url( $link->final_url ); ?>" target="_blank" rel="noopener">
										<?php echo esc_html( Utils::truncate( $link->final_url, 50 ) ); ?>
									</a>
								</small>
							<?php endif; ?>
						</td>
						<td><?php echo Utils::get_status_badge( $link ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $link->occurrence_count ) ); ?></td>
						<td>
							<?php
							if ( $link->sample_posts ) {
								$post_ids = explode( ',', $link->sample_posts );
								$shown    = 0;
								foreach ( $post_ids as $post_id ) {
									$post = get_post( $post_id );
									if ( $post ) {
										if ( $shown > 0 ) {
											echo ', ';
										}
										printf(
											'<a href="%s" target="_blank">%s</a>',
											esc_url( get_edit_post_link( $post_id ) ),
											esc_html( Utils::truncate( $post->post_title, 30 ) )
										);
										$shown++;
										if ( $shown >= 3 ) {
											break;
										}
									}
								}
								if ( $link->occurrence_count > 3 ) {
									printf(
										/* translators: %d: Number of additional posts */
										esc_html__( ' and %d more', 'wp-link-sweeper' ),
										$link->occurrence_count - 3
									);
								}
							}
							?>
						</td>
						<td>
							<?php echo esc_html( Utils::time_ago( $link->last_checked_at ) ); ?>
							<?php if ( $link->response_time_ms ) : ?>
								<br><small><?php echo esc_html( $link->response_time_ms ); ?>ms</small>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small ls-recheck-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
								<?php esc_html_e( 'Recheck', 'wp-link-sweeper' ); ?>
							</button>
							<button type="button" class="button button-small ls-ignore-link" data-link-id="<?php echo esc_attr( $link->id ); ?>">
								<?php esc_html_e( 'Ignore', 'wp-link-sweeper' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo;', 'wp-link-sweeper' ),
							'next_text' => __( '&raquo;', 'wp-link-sweeper' ),
							'total'     => $total_pages,
							'current'   => $paged,
						)
					);
					if ( $page_links ) {
						echo '<span class="displaying-num">' . sprintf(
							/* translators: %s: Number of items */
							esc_html__( '%s items', 'wp-link-sweeper' ),
							number_format_i18n( $total_links )
						) . '</span>';
						echo $page_links;
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
