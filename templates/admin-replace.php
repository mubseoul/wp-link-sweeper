<?php
/**
 * Find & Replace Template
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$available_post_types = Utils::get_available_post_types();
$last_operation       = DB::get_last_operation();
?>

<div class="ls-replace">
	<div class="ls-replace-form">
		<h2><?php esc_html_e( 'Find & Replace URLs', 'wp-link-sweeper' ); ?></h2>

		<form id="ls-replace-form">
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="find-url"><?php esc_html_e( 'Find URL', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<input type="text" id="find-url" name="find" class="large-text" required>
						<p class="description"><?php esc_html_e( 'Enter the URL or part of URL to find.', 'wp-link-sweeper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="replace-url"><?php esc_html_e( 'Replace With', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<input type="url" id="replace-url" name="replace" class="large-text" required>
						<p class="description"><?php esc_html_e( 'Enter the new URL to replace with.', 'wp-link-sweeper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="match-type"><?php esc_html_e( 'Match Type', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<select id="match-type" name="match_type">
							<option value="contains"><?php esc_html_e( 'Contains', 'wp-link-sweeper' ); ?></option>
							<option value="equals"><?php esc_html_e( 'Equals', 'wp-link-sweeper' ); ?></option>
							<option value="starts_with"><?php esc_html_e( 'Starts With', 'wp-link-sweeper' ); ?></option>
							<option value="ends_with"><?php esc_html_e( 'Ends With', 'wp-link-sweeper' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How to match the find URL.', 'wp-link-sweeper' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Post Types', 'wp-link-sweeper' ); ?></label>
					</th>
					<td>
						<fieldset>
							<?php foreach ( $available_post_types as $pt_name => $pt_label ) : ?>
								<label>
									<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $pt_name ); ?>" checked>
									<?php echo esc_html( $pt_label ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Select which post types to search in.', 'wp-link-sweeper' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="button" id="ls-preview-replacement" class="button button-secondary">
					<?php esc_html_e( 'Preview Changes', 'wp-link-sweeper' ); ?>
				</button>
				<button type="button" id="ls-execute-replacement" class="button button-primary" disabled>
					<?php esc_html_e( 'Execute Replacement', 'wp-link-sweeper' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div id="ls-preview-results" class="ls-preview-results" style="display: none;">
		<h3><?php esc_html_e( 'Preview', 'wp-link-sweeper' ); ?></h3>
		<div id="ls-preview-content"></div>
	</div>

	<div id="ls-replacement-results" class="ls-replacement-results" style="display: none;">
		<h3><?php esc_html_e( 'Results', 'wp-link-sweeper' ); ?></h3>
		<div id="ls-replacement-content"></div>
	</div>

	<?php if ( $last_operation && $last_operation->undo_available ) : ?>
		<div class="ls-undo-section">
			<h3><?php esc_html_e( 'Undo Last Operation', 'wp-link-sweeper' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: Time of last operation */
					esc_html__( 'Last operation performed: %s', 'wp-link-sweeper' ),
					'<strong>' . esc_html( Utils::time_ago( $last_operation->created_at ) ) . '</strong>'
				);
				?>
			</p>
			<button type="button" id="ls-undo-operation" class="button button-secondary">
				<?php esc_html_e( 'Undo Last Replacement', 'wp-link-sweeper' ); ?>
			</button>
		</div>
	<?php endif; ?>

	<div class="ls-help-section">
		<h3><?php esc_html_e( 'How It Works', 'wp-link-sweeper' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Find: Enter the URL or portion you want to find (e.g., "http://oldsite.com" or "oldsite.com").', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Replace: Enter the complete new URL.', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Match Type: Choose how to match URLs (Contains is most common).', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Preview: Always preview first to see what will be changed.', 'wp-link-sweeper' ); ?></li>
			<li><?php esc_html_e( 'Undo: You can undo the last replacement operation if needed.', 'wp-link-sweeper' ); ?></li>
		</ul>
		<p class="description">
			<?php esc_html_e( 'Important: Code blocks are protected from replacement. WordPress creates automatic revisions for changed posts.', 'wp-link-sweeper' ); ?>
		</p>
	</div>
</div>
