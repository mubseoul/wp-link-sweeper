<?php
/**
 * Admin Interface Class
 *
 * @package WP_Link_Sweeper
 */

namespace WP_Link_Sweeper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin interface and AJAX requests.
 */
class Admin {
	/**
	 * Current page tab.
	 *
	 * @var string
	 */
	private $current_tab = 'dashboard';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_ls_start_scan', array( $this, 'ajax_start_scan' ) );
		add_action( 'wp_ajax_ls_scan_posts_batch', array( $this, 'ajax_scan_posts_batch' ) );
		add_action( 'wp_ajax_ls_check_urls_batch', array( $this, 'ajax_check_urls_batch' ) );
		add_action( 'wp_ajax_ls_stop_scan', array( $this, 'ajax_stop_scan' ) );
		add_action( 'wp_ajax_ls_get_scan_status', array( $this, 'ajax_get_scan_status' ) );
		add_action( 'wp_ajax_ls_recheck_link', array( $this, 'ajax_recheck_link' ) );
		add_action( 'wp_ajax_ls_ignore_link', array( $this, 'ajax_ignore_link' ) );
		add_action( 'wp_ajax_ls_preview_replacement', array( $this, 'ajax_preview_replacement' ) );
		add_action( 'wp_ajax_ls_execute_replacement', array( $this, 'ajax_execute_replacement' ) );
		add_action( 'wp_ajax_ls_undo_operation', array( $this, 'ajax_undo_operation' ) );
		add_action( 'wp_ajax_ls_add_rule', array( $this, 'ajax_add_rule' ) );
		add_action( 'wp_ajax_ls_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_ls_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
		add_action( 'wp_ajax_ls_apply_rules', array( $this, 'ajax_apply_rules' ) );
		add_action( 'wp_ajax_ls_export_csv', array( $this, 'ajax_export_csv' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Link Sweeper', 'wp-link-sweeper' ),
			__( 'Link Sweeper', 'wp-link-sweeper' ),
			'manage_options',
			'wp-link-sweeper',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'tools_page_wp-link-sweeper' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wp-link-sweeper-admin',
			WP_LINK_SWEEPER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_LINK_SWEEPER_VERSION
		);

		wp_enqueue_script(
			'wp-link-sweeper-admin',
			WP_LINK_SWEEPER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_LINK_SWEEPER_VERSION,
			true
		);

		wp_localize_script(
			'wp-link-sweeper-admin',
			'wpLinkSweeper',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_link_sweeper_nonce' ),
				'strings' => array(
					'scanStarted'    => __( 'Scan started...', 'wp-link-sweeper' ),
					'scanning'       => __( 'Scanning...', 'wp-link-sweeper' ),
					'checking'       => __( 'Checking URLs...', 'wp-link-sweeper' ),
					'scanComplete'   => __( 'Scan complete!', 'wp-link-sweeper' ),
					'scanStopped'    => __( 'Scan stopped.', 'wp-link-sweeper' ),
					'confirmStop'    => __( 'Are you sure you want to stop the scan?', 'wp-link-sweeper' ),
					'confirmDelete'  => __( 'Are you sure you want to delete this rule?', 'wp-link-sweeper' ),
					'confirmReplace' => __( 'Are you sure you want to replace these URLs? This action can be undone.', 'wp-link-sweeper' ),
					'error'          => __( 'An error occurred. Please try again.', 'wp-link-sweeper' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get current tab.
		$this->current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

		$tabs = array(
			'dashboard'    => __( 'Dashboard', 'wp-link-sweeper' ),
			'broken-links' => __( 'Broken Links', 'wp-link-sweeper' ),
			'replace'      => __( 'Find & Replace', 'wp-link-sweeper' ),
			'rules'        => __( 'Auto-Fix Rules', 'wp-link-sweeper' ),
			'settings'     => __( 'Settings', 'wp-link-sweeper' ),
		);

		?>
		<div class="wrap wp-link-sweeper">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, admin_url( 'tools.php?page=wp-link-sweeper' ) ) ); ?>"
					   class="nav-tab <?php echo $this->current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content">
				<?php $this->render_tab_content(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render current tab content.
	 */
	private function render_tab_content() {
		$template_file = WP_LINK_SWEEPER_PLUGIN_DIR . 'templates/admin-' . $this->current_tab . '.php';

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			echo '<p>' . esc_html__( 'Tab content not found.', 'wp-link-sweeper' ) . '</p>';
		}
	}

	/**
	 * Handle settings save.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST['wp_link_sweeper_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wp_link_sweeper_settings_nonce'], 'wp_link_sweeper_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save settings.
		$settings = array(
			'scan_post_types'           => isset( $_POST['scan_post_types'] ) ? array_map( 'sanitize_text_field', $_POST['scan_post_types'] ) : array(),
			'rate_limit'                => isset( $_POST['rate_limit'] ) ? absint( $_POST['rate_limit'] ) : 5,
			'user_agent'                => isset( $_POST['user_agent'] ) ? sanitize_text_field( $_POST['user_agent'] ) : '',
			'request_timeout'           => isset( $_POST['request_timeout'] ) ? absint( $_POST['request_timeout'] ) : 10,
			'normalize_remove_utm'      => isset( $_POST['normalize_remove_utm'] ),
			'normalize_ignore_fragment' => isset( $_POST['normalize_ignore_fragment'] ),
			'batch_size_posts'          => isset( $_POST['batch_size_posts'] ) ? absint( $_POST['batch_size_posts'] ) : 20,
			'batch_size_urls'           => isset( $_POST['batch_size_urls'] ) ? absint( $_POST['batch_size_urls'] ) : 10,
			'cron_schedule'             => isset( $_POST['cron_schedule'] ) ? sanitize_text_field( $_POST['cron_schedule'] ) : 'disabled',
			'delete_data_on_uninstall'  => isset( $_POST['delete_data_on_uninstall'] ),
		);

		foreach ( $settings as $key => $value ) {
			update_option( 'wp_link_sweeper_' . $key, $value );
		}

		// Update cron schedule.
		Cron::schedule( $settings['cron_schedule'] );

		add_settings_error(
			'wp_link_sweeper_settings',
			'settings_updated',
			__( 'Settings saved successfully.', 'wp-link-sweeper' ),
			'success'
		);

		set_transient( 'wp_link_sweeper_settings_updated', true, 10 );
	}

	/**
	 * AJAX: Start scan.
	 */
	public function ajax_start_scan() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$result = Scanner::start_scan();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Scan posts batch.
	 */
	public function ajax_scan_posts_batch() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$result = Scanner::scan_posts_batch( $offset );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Check URLs batch.
	 */
	public function ajax_check_urls_batch() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$result = Scanner::check_urls_batch();

		if ( $result['completed'] ) {
			$complete_result = Scanner::complete_scan();
			$result = array_merge( $result, $complete_result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Stop scan.
	 */
	public function ajax_stop_scan() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$result = Scanner::stop_scan();
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get scan status.
	 */
	public function ajax_get_scan_status() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$status = Scanner::get_scan_status();
		wp_send_json_success( $status );
	}

	/**
	 * AJAX: Recheck link.
	 */
	public function ajax_recheck_link() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$result  = Link_Checker::recheck_link( $link_id );

		if ( $result ) {
			wp_send_json_success( array( 'result' => $result ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Link not found.', 'wp-link-sweeper' ) ) );
		}
	}

	/**
	 * AJAX: Ignore link.
	 */
	public function ajax_ignore_link() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$link_id = isset( $_POST['link_id'] ) ? absint( $_POST['link_id'] ) : 0;
		$result  = DB::ignore_link( $link_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Link ignored.', 'wp-link-sweeper' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to ignore link.', 'wp-link-sweeper' ) ) );
		}
	}

	/**
	 * AJAX: Preview replacement.
	 */
	public function ajax_preview_replacement() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$args = array(
			'find'       => isset( $_POST['find'] ) ? sanitize_text_field( $_POST['find'] ) : '',
			'replace'    => isset( $_POST['replace'] ) ? sanitize_text_field( $_POST['replace'] ) : '',
			'post_types' => isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', $_POST['post_types'] ) : array(),
			'match_type' => isset( $_POST['match_type'] ) ? sanitize_text_field( $_POST['match_type'] ) : 'contains',
		);

		$result = Replacer::preview_replacement( $args );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Execute replacement.
	 */
	public function ajax_execute_replacement() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$args = array(
			'find'       => isset( $_POST['find'] ) ? sanitize_text_field( $_POST['find'] ) : '',
			'replace'    => isset( $_POST['replace'] ) ? sanitize_text_field( $_POST['replace'] ) : '',
			'post_types' => isset( $_POST['post_types'] ) ? array_map( 'sanitize_text_field', $_POST['post_types'] ) : array(),
			'match_type' => isset( $_POST['match_type'] ) ? sanitize_text_field( $_POST['match_type'] ) : 'contains',
		);

		$result = Replacer::execute_replacement( $args );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Undo operation.
	 */
	public function ajax_undo_operation() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$result = Replacer::undo_last_operation();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Add rule.
	 */
	public function ajax_add_rule() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$rule = array(
			'pattern'     => isset( $_POST['pattern'] ) ? sanitize_text_field( $_POST['pattern'] ) : '',
			'replacement' => isset( $_POST['replacement'] ) ? sanitize_text_field( $_POST['replacement'] ) : '',
			'match_type'  => isset( $_POST['match_type'] ) ? sanitize_text_field( $_POST['match_type'] ) : 'contains',
			'enabled'     => true,
		);

		$result = Rules::add_rule( $rule );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Delete rule.
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( $_POST['rule_id'] ) : '';
		$result  = Rules::delete_rule( $rule_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Toggle rule.
	 */
	public function ajax_toggle_rule() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( $_POST['rule_id'] ) : '';
		$result  = Rules::toggle_rule( $rule_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Apply rules.
	 */
	public function ajax_apply_rules() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-link-sweeper' ) ) );
		}

		$dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === 'true';
		$result  = Rules::apply_rules( array( 'dry_run' => $dry_run ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Export to CSV.
	 */
	public function ajax_export_csv() {
		check_ajax_referer( 'wp_link_sweeper_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-link-sweeper' ) );
		}

		$args = array(
			'status'    => isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'all',
			'post_type' => isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'all',
			'domain'    => isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '',
		);

		Exporter::export_to_csv( $args );
	}
}
