<?php
/**
 * Admin UI and actions.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Admin {
	/**
	 * Option key for latest connection check.
	 *
	 * @var string
	 */
	const CONNECTION_STATUS_OPTION_KEY = 'ai_alt_text_connection_status';


	/**
	 * Settings.
	 *
	 * @var WPAI_Alt_Text_Settings
	 */
	private $settings;

	/**
	 * Queue.
	 *
	 * @var WPAI_Alt_Text_Queue_Repo
	 */
	private $queue_repo;

	/**
	 * Processor.
	 *
	 * @var WPAI_Alt_Text_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param WPAI_Alt_Text_Settings   $settings Settings.
	 * @param WPAI_Alt_Text_Queue_Repo $queue_repo Queue.
	 * @param WPAI_Alt_Text_Processor  $processor Processor.
	 */
	public function __construct( $settings, $queue_repo, $processor ) {
		$this->settings   = $settings;
		$this->queue_repo = $queue_repo;
		$this->processor  = $processor;
	}

	/**
	 * Register menus.
	 *
	 * @return void
	 */
	public function register_menus() {
		if ( $this->current_user_can_view_settings() ) {
			add_submenu_page(
				'options-general.php',
				__( 'Dynamic Alt Tags Settings', 'dynamic-alt-tags' ),
				__( 'Dynamic Alt Tags', 'dynamic-alt-tags' ),
				'manage_options',
				'ai-alt-text-settings',
				array( $this, 'render_settings_page' )
			);
		}

		if ( $this->current_user_can_view_queue() ) {
			add_submenu_page(
				'upload.php',
				__( 'Dynamic Alt Tags', 'dynamic-alt-tags' ),
				__( 'Dynamic Alt Tags', 'dynamic-alt-tags' ),
				WPAI_ALT_TEXT_QUEUE_CAP,
				'ai-alt-text-queue',
				array( $this, 'render_queue_page' )
			);
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed_hooks = array( 'upload.php', 'post.php', 'post-new.php' );
		if ( false === strpos( $hook_suffix, 'ai-alt-text' ) && ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}
		$options = $this->settings->get_options();

		wp_enqueue_style(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.css',
			array(),
			file_exists( WPAI_ALT_TEXT_DIR . 'assets/admin.css' ) ? (string) filemtime( WPAI_ALT_TEXT_DIR . 'assets/admin.css' ) : WPAI_ALT_TEXT_VERSION
		);

		wp_enqueue_script(
			'dynamic-alt-tags-admin',
			WPAI_ALT_TEXT_URL . 'assets/admin.js',
			array(),
			file_exists( WPAI_ALT_TEXT_DIR . 'assets/admin.js' ) ? (string) filemtime( WPAI_ALT_TEXT_DIR . 'assets/admin.js' ) : WPAI_ALT_TEXT_VERSION,
			true
		);

		wp_localize_script(
			'dynamic-alt-tags-admin',
			'aiAltAdmin',
				array(
					'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
					'processNowNonce'    => wp_create_nonce( 'ai_alt_process_now_ajax' ),
					'queueProcessNonce'  => wp_create_nonce( 'ai_alt_queue_process_ajax' ),
					'queueLoadMoreNonce' => wp_create_nonce( 'ai_alt_queue_load_more_ajax' ),
					'queueAddNoAltNonce' => wp_create_nonce( 'ai_alt_queue_add_no_alt_ajax' ),
					'queueAddBrowseNonce' => wp_create_nonce( 'ai_alt_queue_add_browse_ajax' ),
					'queueBrowseNonce' => wp_create_nonce( 'ai_alt_queue_browse_ajax' ),
					'settingsMetricsNonce' => wp_create_nonce( 'ai_alt_settings_metrics_ajax' ),
				'uploadActionNonce'  => wp_create_nonce( 'ai_alt_upload_action_ajax' ),
				'syncTitleFromAlt'   => ! isset( $options['sync_title_from_alt'] ) || ! empty( $options['sync_title_from_alt'] ),
				'syncDescriptionFromAlt' => ! empty( $options['sync_description_from_alt'] ),
				'i18n'               => array(
					'processing'         => __( 'Processing queue...', 'dynamic-alt-tags' ),
					'success'            => __( 'Manual processing finished. %d items processed.', 'dynamic-alt-tags' ),
					'error'              => __( 'Queue processing failed. Please try again.', 'dynamic-alt-tags' ),
						'partial'            => __( 'Processing stopped early after %d items. You can run it again to continue.', 'dynamic-alt-tags' ),
						'providerPaused'     => __( 'Processing paused because the provider is temporarily unavailable.', 'dynamic-alt-tags' ),
						'rowProcessing'      => __( 'Processing image...', 'dynamic-alt-tags' ),
						'rowSuccess'         => __( 'Image successfully processed', 'dynamic-alt-tags' ),
						'rowError'           => __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' ),
						'loadingMore'        => __( 'Loading more...', 'dynamic-alt-tags' ),
						'loadMoreError'      => __( 'Unable to load more items. Please try again.', 'dynamic-alt-tags' ),
						'browseLoading'     => __( 'Loading images...', 'dynamic-alt-tags' ),
						'browseError'       => __( 'Unable to load browse results. Please try again.', 'dynamic-alt-tags' ),
						'browseNoResults'   => __( 'No images matched your filters.', 'dynamic-alt-tags' ),
					'queueAddSuccess'    => __( 'Added to queue', 'dynamic-alt-tags' ),
					'queueAddError'      => __( 'Unable to add image to queue.', 'dynamic-alt-tags' ),
					'queueAddSelectedSuccess' => __( 'Selected images added to queue.', 'dynamic-alt-tags' ),
					'queueAddSelectedError' => __( 'Unable to add the selected images to queue.', 'dynamic-alt-tags' ),
					'bulkSelect'        => __( 'Bulk Select', 'dynamic-alt-tags' ),
					'cancelSelection'   => __( 'Cancel Selection', 'dynamic-alt-tags' ),
					'selectedCount'     => __( '%d selected', 'dynamic-alt-tags' ),
					'selectUploadAction' => __( 'Please choose an action first.', 'dynamic-alt-tags' ),
					'customAltRequired'  => __( 'Enter custom alt text before applying.', 'dynamic-alt-tags' ),
					'uploadActionFailed' => __( 'Unable to apply upload action. Please try again.', 'dynamic-alt-tags' ),
					'confirmSkip'        => __( 'Skip this image and move it to History?', 'dynamic-alt-tags' ),
					'confirmReject'      => __( 'Reject this generated alt text?', 'dynamic-alt-tags' ),
				),
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! $this->current_user_can_view_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dynamic-alt-tags' ) );
		}

		$connection_status = $this->get_connection_status();
		$metrics           = $this->settings->get_metrics();
		$coverage          = $this->queue_repo->get_image_alt_coverage_counts();

		include WPAI_ALT_TEXT_DIR . 'admin/views-page-settings.php';
	}

	/**
	 * Render queue page.
	 *
	 * @return void
	 */
	public function render_queue_page() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dynamic-alt-tags' ) );
		}

		$status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$view         = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
		$view         = in_array( $view, array( 'dashboard', 'active', 'history', 'no_alt', 'search', 'browse' ), true ) ? $view : 'dashboard';
		if ( 'browse' === $view ) {
			$view = 'search';
		}
		$focused_queue_ids         = array();
		$queue_has_more            = null;
		$page                      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$queue_load_more_next_page = $page + 1;
		$per_page                  = 20;
		if ( 'dashboard' === $view ) {
			$data = array(
				'total'    => 0,
				'page'     => $page,
				'per_page' => $per_page,
				'rows'     => array(),
			);
		} elseif ( 'search' === $view ) {
			$browse_filters = array(
				'date'            => isset( $_GET['browse_date'] ) ? sanitize_text_field( wp_unslash( $_GET['browse_date'] ) ) : '',
				'search'          => isset( $_GET['browse_search'] ) ? sanitize_text_field( wp_unslash( $_GET['browse_search'] ) ) : '',
				'alt_filter'      => isset( $_GET['browse_alt_filter'] ) ? sanitize_key( wp_unslash( $_GET['browse_alt_filter'] ) ) : ( isset( $_GET['browse_no_alt_only'] ) ? 'no_alt' : 'all' ),
				'category'        => isset( $_GET['browse_category'] ) ? absint( wp_unslash( $_GET['browse_category'] ) ) : 0,
				'filebird_folder' => isset( $_GET['browse_filebird_folder'] ) ? absint( wp_unslash( $_GET['browse_filebird_folder'] ) ) : 0,
			);
			$data = $this->browse_media_attachments( $browse_filters, $page, 24 );
			$browse_month_options = $this->get_browse_month_options();
			$browse_category_taxonomy = $this->get_media_category_taxonomy();
			$browse_category_options  = $this->get_browse_category_options( $browse_category_taxonomy );
			$browse_filebird_options  = $this->get_filebird_folder_options();
		} else {
			$queued_ids_raw    = isset( $_GET['queued_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['queued_ids'] ) ) : '';
			$focused_queue_ids = 'active' === $view && '' === $status ? $this->parse_attachment_ids_list( $queued_ids_raw ) : array();
			if ( 'active' === $view && ! empty( $focused_queue_ids ) && 1 === $page ) {
				$focused_rows    = $this->queue_repo->get_active_rows_by_attachment_ids( $focused_queue_ids );
				$remaining_data  = $this->queue_repo->get_paginated( 1, $per_page, $status, $view, $focused_queue_ids );
				$data            = array(
					'total'    => count( $focused_rows ) + ( isset( $remaining_data['total'] ) ? absint( $remaining_data['total'] ) : 0 ),
					'page'     => 1,
					'per_page' => $per_page,
					'rows'     => $focused_rows,
				);
				$queue_has_more            = ! empty( $remaining_data['total'] );
				$queue_load_more_next_page = 1;
			} else {
				$data = 'no_alt' === $view ? $this->queue_repo->get_no_alt_paginated( $page, $per_page ) : $this->queue_repo->get_paginated( $page, $per_page, $status, $view );
			}
		}
		$total_images = $this->queue_repo->get_total_no_alt_images();
		$metrics      = $this->settings->get_metrics();
		$coverage     = $this->queue_repo->get_image_alt_coverage_counts();

		include WPAI_ALT_TEXT_DIR . 'admin/views-page-queue.php';
	}

	/**
	 * Run backfill.
	 *
	 * @return void
	 */
	public function handle_run_backfill() {
		if ( ! $this->current_user_can_view_settings() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$count = $this->queue_repo->enqueue_missing_alts( 500 );

		$redirect = add_query_arg(
			array(
				'page'     => 'ai-alt-text-settings',
				'notice'   => 'backfill_done',
				'enqueued' => $count,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Run backfill from Queue page.
	 *
	 * @return void
	 */
	public function handle_run_backfill_queue() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$count = $this->queue_repo->enqueue_missing_alts( 500 );

		$redirect = add_query_arg(
			array(
				'page'     => 'ai-alt-text-queue',
				'notice'   => 'queue_backfill_done',
				'enqueued' => $count,
				'view'     => 'active',
			),
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process now.
	 *
	 * @return void
	 */
	public function handle_process_now() {
		if ( ! $this->current_user_can_view_settings() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$options   = $this->settings->get_options();
		$before    = $this->queue_repo->get_active_status_counts();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after     = $this->queue_repo->get_active_status_counts();

		if ( $processed > 0 ) {
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-settings',
					'notice'    => 'process_done',
					'processed' => $processed,
				),
				admin_url( 'options-general.php' )
			);
		} else {
			$message  = $this->get_zero_processed_message( $before, $after );
			$redirect = add_query_arg(
				array(
					'page'        => 'ai-alt-text-settings',
					'notice'      => 'process_error',
					'process_msg' => rawurlencode( $message ),
				),
				admin_url( 'options-general.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process now from Queue page.
	 *
	 * @return void
	 */
	public function handle_process_now_queue() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$options   = $this->settings->get_options();
		$before    = $this->queue_repo->get_active_status_counts();
		$processed = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after     = $this->queue_repo->get_active_status_counts();

		if ( $processed > 0 ) {
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-queue',
					'notice'    => 'queue_batch_done',
					'processed' => $processed,
					'view'      => 'active',
				),
				admin_url( 'upload.php' )
			);
		} else {
			$message  = $this->get_zero_processed_message( $before, $after );
			$redirect = add_query_arg(
				array(
					'page'      => 'ai-alt-text-queue',
					'notice'    => 'queue_error',
					'queue_msg' => rawurlencode( $message ),
					'view'      => 'active',
				),
				admin_url( 'upload.php' )
			);
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Delete all history rows from Queue page.
	 *
	 * @return void
	 */
	public function handle_clear_history_queue() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$deleted = $this->queue_repo->delete_history_rows();

		$redirect = add_query_arg(
			array(
				'page'    => 'ai-alt-text-settings',
				'notice'  => 'history_cleared',
				'deleted' => $deleted,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Reset cumulative metrics.
	 *
	 * @return void
	 */
	public function handle_reset_metrics() {
		if ( ! $this->current_user_can_view_settings() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );
		$this->settings->reset_metrics();
		$return_tab = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'metrics';
		$return_tab = in_array( $return_tab, array( 'settings', 'tools', 'access-control', 'metrics' ), true ) ? $return_tab : 'metrics';

		$redirect = add_query_arg(
			array(
				'page'   => 'ai-alt-text-settings',
				'notice' => 'metrics_reset',
				'tab'    => $return_tab,
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Process now via AJAX.
	 *
	 * @return void
	 */
	public function handle_process_now_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		check_ajax_referer( 'ai_alt_process_now_ajax' );

		$options             = $this->settings->get_options();
		$started_at          = current_time( 'mysql' );
		$before              = $this->queue_repo->get_active_status_counts();
		$processed           = $this->processor->process_batch( isset( $options['batch_size'] ) ? absint( $options['batch_size'] ) : 10 );
		$after               = $this->queue_repo->get_active_status_counts();
		$message             = '';
		$provider_failure    = $this->get_latest_provider_wide_failure( $started_at );
		$remaining_claimable = isset( $after['queued'] ) ? absint( $after['queued'] ) : 0;
		$has_more            = $remaining_claimable > 0;

		if ( $provider_failure['message'] ) {
			$message = $provider_failure['message'];
		} elseif ( $processed <= 0 ) {
			$message = $this->get_zero_processed_message( $before, $after );
		}

		wp_send_json_success(
			array(
				'processed'           => $processed,
				'message'             => $message,
				'remaining_claimable' => $remaining_claimable,
				'has_more'            => $has_more,
				'provider_wide'       => $provider_failure['provider_wide'],
				'provider_error_code' => $provider_failure['code'],
			)
		);
	}

	/**
	 * Process one queue row via AJAX.
	 *
	 * @return void
	 */
	public function handle_queue_process_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		check_ajax_referer( 'ai_alt_queue_process_ajax' );

		$row_id = isset( $_POST['row_id'] ) ? absint( wp_unslash( $_POST['row_id'] ) ) : 0;
		if ( ! $row_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid queue row.', 'dynamic-alt-tags' ),
				),
				400
			);
		}

		$message = '';
		$ok      = $this->process_queue_row( $row_id, $message );
		$row     = $this->queue_repo->get_row( $row_id );

		if ( ! $ok ) {
			wp_send_json_error(
				array(
					'message' => '' !== $message ? $message : __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' ),
					'provider_wide' => $this->is_provider_wide_queue_row( $row ),
					'provider_error_code' => is_array( $row ) && ! empty( $row['error_code'] ) ? sanitize_key( (string) $row['error_code'] ) : '',
				),
				200
			);
		}

		wp_send_json_success(
			array(
				'message'       => __( 'Image successfully processed', 'dynamic-alt-tags' ),
				'status'        => is_array( $row ) && isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '',
				'confidence'    => is_array( $row ) && isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0,
				'suggested_alt' => is_array( $row ) && isset( $row['suggested_alt'] ) ? sanitize_text_field( (string) $row['suggested_alt'] ) : '',
			)
		);
	}

	/**
	 * Load more queue rows for current tab view.
	 *
	 * @return void
	 */
	public function handle_queue_load_more_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_load_more_ajax' );

		$view     = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'active';
		$view     = in_array( $view, array( 'active', 'history', 'no_alt' ), true ) ? $view : 'active';
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 100, absint( wp_unslash( $_POST['per_page'] ) ) ) ) : 20;
		$exclude_attachment_ids_raw = isset( $_POST['exclude_attachment_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_attachment_ids'] ) ) : '';
		$exclude_attachment_ids     = $this->parse_attachment_ids_list( $exclude_attachment_ids_raw );

		$data     = 'no_alt' === $view ? $this->queue_repo->get_no_alt_paginated( $page, $per_page ) : $this->queue_repo->get_paginated( $page, $per_page, $status, $view, $exclude_attachment_ids );
		$rows     = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
		$total    = isset( $data['total'] ) ? absint( $data['total'] ) : 0;
		$max_page = max( 1, (int) ceil( $total / $per_page ) );

		$html = 'no_alt' === $view ? $this->render_no_alt_rows_html( $rows ) : $this->render_queue_rows_html( $rows, 'history' === $view );

		wp_send_json_success(
			array(
				'html'      => $html,
				'has_more'  => $page < $max_page,
				'next_page' => $page + 1,
			)
		);
	}

	/**
	 * Add one no-alt image to queue.
	 *
	 * @return void
	 */
	public function handle_queue_add_no_alt_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_add_no_alt_ajax' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'dynamic-alt-tags' ) ), 400 );
		}

		$attachment = get_post( $attachment_id );
		$mime_type  = (string) get_post_mime_type( $attachment_id );
		if ( ! ( $attachment instanceof WP_Post ) || 'attachment' !== $attachment->post_type || 0 !== strpos( $mime_type, 'image/' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only image attachments can be queued.', 'dynamic-alt-tags' ) ), 400 );
		}
		if ( $this->is_svg_attachment( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'SVG images are not supported by the configured provider.', 'dynamic-alt-tags' ) ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this attachment.', 'dynamic-alt-tags' ) ), 403 );
		}

		$ok = $this->queue_repo->enqueue_or_requeue( $attachment_id, 0 );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Unable to add image to queue.', 'dynamic-alt-tags' ) ), 200 );
		}

		wp_send_json_success( array( 'message' => __( 'Added to queue', 'dynamic-alt-tags' ) ) );
	}

	/**
	 * Add selected Search tab images to queue.
	 *
	 * @return void
	 */
	public function handle_queue_add_browse_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_add_browse_ajax' );

		$attachment_ids = array();
		if ( isset( $_POST['attachment_ids'] ) ) {
			$raw_ids = wp_unslash( $_POST['attachment_ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $raw_ids ) ) {
				$attachment_ids = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );
			}
		}

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one image first.', 'dynamic-alt-tags' ) ), 400 );
		}

		$queued_ids = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			$mime_type  = (string) get_post_mime_type( $attachment_id );

			if ( ! ( $attachment instanceof WP_Post ) || 'attachment' !== $attachment->post_type || 0 !== strpos( $mime_type, 'image/' ) ) {
				continue;
			}
			if ( $this->is_svg_attachment( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}
			if ( $this->queue_repo->enqueue_or_requeue( $attachment_id, 0 ) ) {
				$queued_ids[] = $attachment_id;
			}
		}

		if ( empty( $queued_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to add the selected images to queue.', 'dynamic-alt-tags' ) ), 200 );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Selected images added to queue.', 'dynamic-alt-tags' ),
				'queued_count' => count( $queued_ids ),
				'redirect_url' => add_query_arg(
					array(
						'page'      => 'ai-alt-text-queue',
						'view'      => 'active',
						'queued_ids' => implode( ',', $queued_ids ),
					),
					admin_url( 'upload.php' )
				),
			)
		);
	}

	/**
	 * Get settings metrics via AJAX.
	 *
	 * @return void
	 */
	public function handle_settings_metrics_ajax() {
		if ( ! $this->current_user_can_view_settings() && ! $this->current_user_can_view_queue() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ),
				),
				403
			);
		}

		check_ajax_referer( 'ai_alt_settings_metrics_ajax' );

		wp_send_json_success(
			array(
				'fields' => $this->get_settings_metrics_fields(),
			)
		);
	}


	/**
	 * Get month options for media-style browse dropdown.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_browse_month_options() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT DISTINCT YEAR(post_date) AS y, MONTH(post_date) AS m
			 FROM {$wpdb->posts}
			 WHERE post_type = 'attachment'
			 AND post_mime_type LIKE 'image/%'
			 AND post_mime_type <> 'image/svg+xml'
			 AND post_status = 'inherit'
			 ORDER BY post_date DESC", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$options = array();
		foreach ( (array) $rows as $row ) {
			$year  = isset( $row['y'] ) ? absint( $row['y'] ) : 0;
			$month = isset( $row['m'] ) ? absint( $row['m'] ) : 0;
			if ( $year < 1 || $month < 1 || $month > 12 ) {
				continue;
			}
			$value = sprintf( '%04d%02d', $year, $month );
			$label = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );
			$options[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $options;
	}

	/**
	 * Get the configured attachment taxonomy for Search filters, if available.
	 *
	 * @return string
	 */
	private function get_media_category_taxonomy() {
		$options             = $this->settings->get_options();
		$configured_taxonomy = isset( $options['search_media_taxonomy'] ) ? sanitize_key( (string) $options['search_media_taxonomy'] ) : '';

		if ( '' !== $configured_taxonomy && taxonomy_exists( $configured_taxonomy ) && is_object_in_taxonomy( 'attachment', $configured_taxonomy ) ) {
			return $configured_taxonomy;
		}

		$candidates = array( 'media_category' );
		foreach ( $candidates as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( 'attachment', $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Get term options for the media category dropdown.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_browse_category_options( $taxonomy ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		if ( '' === $taxonomy ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		usort(
			$terms,
			static function ( $left, $right ) {
				if ( ! ( $left instanceof WP_Term ) || ! ( $right instanceof WP_Term ) ) {
					return 0;
				}

				return strcasecmp( (string) $left->name, (string) $right->name );
			}
		);

		$terms_by_parent = array();
		foreach ( $terms as $term ) {
			if ( ! ( $term instanceof WP_Term ) ) {
				continue;
			}

			$parent_id = max( 0, (int) $term->parent );
			if ( ! isset( $terms_by_parent[ $parent_id ] ) ) {
				$terms_by_parent[ $parent_id ] = array();
			}

			$terms_by_parent[ $parent_id ][] = $term;
		}

		$options = array();
		$append_term_option = static function ( $parent_id, $depth ) use ( &$append_term_option, &$options, $terms_by_parent ) {
			if ( empty( $terms_by_parent[ $parent_id ] ) ) {
				return;
			}

			foreach ( $terms_by_parent[ $parent_id ] as $term ) {
				if ( ! ( $term instanceof WP_Term ) ) {
					continue;
				}

				$prefix = $depth > 0 ? str_repeat( '- ', $depth ) : '';
				$options[] = array(
					'value' => (int) $term->term_id,
					'label' => $prefix . (string) $term->name,
				);

				$append_term_option( (int) $term->term_id, $depth + 1 );
			}
		};

		$append_term_option( 0, 0 );

		return $options;
	}

	/**
	 * Get FileBird folder options when FileBird tables and folders exist.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_filebird_folder_options() {
		global $wpdb;

		$table_names = $this->get_filebird_table_names();
		if ( empty( $table_names['folders'] ) || empty( $table_names['attachments'] ) ) {
			return array();
		}

		$previous_suppress = $wpdb->suppress_errors( true );
		$results           = $wpdb->get_results(
			"SELECT id, name
			FROM {$table_names['folders']}
			WHERE name IS NOT NULL AND name <> ''
			ORDER BY name ASC",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->suppress_errors( $previous_suppress );

		if ( ! is_array( $results ) ) {
			return array();
		}

		$options = array();
		foreach ( $results as $row ) {
			$folder_id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$label     = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( $folder_id <= 0 || '' === $label ) {
				continue;
			}

			$options[] = array(
				'value' => $folder_id,
				'label' => $label,
			);
		}

		return $options;
	}

	/**
	 * Get FileBird table names if both expected tables exist.
	 *
	 * @return array<string,string>
	 */
	private function get_filebird_table_names() {
		global $wpdb;

		$folders_table     = $wpdb->prefix . 'fbv';
		$attachments_table = $wpdb->prefix . 'fbv_attachment_folder';

		$previous_suppress = $wpdb->suppress_errors( true );
		$folders_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $folders_table ) );
		$attachments_exist = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $attachments_table ) );
		$wpdb->suppress_errors( $previous_suppress );

		if ( $folders_exists !== $folders_table || $attachments_exist !== $attachments_table ) {
			return array();
		}

		return array(
			'folders'     => $folders_table,
			'attachments' => $attachments_table,
		);
	}

	/**
	 * Browse media attachments with filters.
	 *
	 * @return void
	 */
	public function handle_queue_browse_ajax() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) ), 403 );
		}

		check_ajax_referer( 'ai_alt_queue_browse_ajax' );

		$filters = array(
			'date'            => isset( $_POST['browse_date'] ) ? sanitize_text_field( wp_unslash( $_POST['browse_date'] ) ) : '',
			'search'          => isset( $_POST['browse_search'] ) ? sanitize_text_field( wp_unslash( $_POST['browse_search'] ) ) : '',
			'alt_filter'      => isset( $_POST['browse_alt_filter'] ) ? sanitize_key( wp_unslash( $_POST['browse_alt_filter'] ) ) : ( isset( $_POST['browse_no_alt_only'] ) ? 'no_alt' : 'all' ),
			'category'        => isset( $_POST['browse_category'] ) ? absint( wp_unslash( $_POST['browse_category'] ) ) : 0,
			'filebird_folder' => isset( $_POST['browse_filebird_folder'] ) ? absint( wp_unslash( $_POST['browse_filebird_folder'] ) ) : 0,
		);
		$page     = isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 1, min( 60, absint( wp_unslash( $_POST['per_page'] ) ) ) ) : 24;

		$data = $this->browse_media_attachments( $filters, $page, $per_page );
		$rows = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();

		wp_send_json_success(
			array(
				'html'      => $this->render_browse_cards_html( $rows ),
				'total'     => isset( $data['total'] ) ? absint( $data['total'] ) : 0,
				'count'     => count( $rows ),
				'page'      => isset( $data['page'] ) ? absint( $data['page'] ) : $page,
				'per_page'  => isset( $data['per_page'] ) ? absint( $data['per_page'] ) : $per_page,
				'has_more'  => isset( $data['has_more'] ) ? (bool) $data['has_more'] : false,
				'next_page' => isset( $data['next_page'] ) ? absint( $data['next_page'] ) : ( $page + 1 ),
			)
		);
	}

	/**
	 * Browse media attachments with date and search filters.
	 *
	 * @param array<string,string> $filters Browse filters.
	 * @param int                  $page Page number.
	 * @param int                  $per_page Results per page.
	 * @return array<string,mixed>
	 */
	private function browse_media_attachments( $filters, $page = 1, $per_page = 24 ) {
		global $wpdb;

		$page            = max( 1, absint( $page ) );
		$per_page        = max( 1, min( 60, absint( $per_page ) ) );
		$offset          = ( $page - 1 ) * $per_page;
		$date_val        = isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '';
		$search          = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
		$category        = isset( $filters['category'] ) ? absint( $filters['category'] ) : 0;
		$filebird_folder = isset( $filters['filebird_folder'] ) ? absint( $filters['filebird_folder'] ) : 0;
		$alt_filter      = isset( $filters['alt_filter'] ) ? sanitize_key( (string) $filters['alt_filter'] ) : 'all';
		$alt_filter      = in_array( $alt_filter, array( 'all', 'no_alt' ), true ) ? $alt_filter : 'all';
		$category_taxonomy = $this->get_media_category_taxonomy();
		$filebird_tables   = $this->get_filebird_table_names();

		$where = array(
			"p.post_type = 'attachment'",
			"p.post_mime_type LIKE 'image/%'",
			"p.post_mime_type <> 'image/svg+xml'",
			"p.post_status = 'inherit'",
		);
		$joins  = array(
			"LEFT JOIN {$wpdb->postmeta} file_meta ON (file_meta.post_id = p.ID AND file_meta.meta_key = '_wp_attached_file')",
			"LEFT JOIN {$wpdb->postmeta} alt_meta ON (alt_meta.post_id = p.ID AND alt_meta.meta_key = '_wp_attachment_image_alt')",
			"LEFT JOIN {$wpdb->term_relationships} term_rel ON (term_rel.object_id = p.ID)",
			"LEFT JOIN {$wpdb->term_taxonomy} term_tax ON (term_tax.term_taxonomy_id = term_rel.term_taxonomy_id)",
		);
		$params = array();

		if ( preg_match( '/^\d{6}$/', $date_val ) ) {
			$where[]  = "DATE_FORMAT(p.post_date, '%Y%m') = %s";
			$params[] = $date_val;
		}

		if ( '' !== $search ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(p.post_title LIKE %s OR file_meta.meta_value LIKE %s OR alt_meta.meta_value LIKE %s OR CAST(p.ID AS CHAR) = %s)';
			$params[] = $search_like;
			$params[] = $search_like;
			$params[] = $search_like;
			$params[] = $search;
		}
		if ( 'no_alt' === $alt_filter ) {
			$where[] = "(alt_meta.meta_value IS NULL OR TRIM(alt_meta.meta_value) = '')";
		}
		if ( $category > 0 && '' !== $category_taxonomy ) {
			$where[]  = 'term_tax.taxonomy = %s';
			$where[]  = 'term_tax.term_id = %d';
			$params[] = $category_taxonomy;
			$params[] = $category;
		}
		if ( $filebird_folder > 0 && ! empty( $filebird_tables['attachments'] ) ) {
			$joins[]  = "INNER JOIN {$filebird_tables['attachments']} filebird_rel ON (filebird_rel.attachment_id = p.ID)";
			$where[]  = 'filebird_rel.folder_id = %d';
			$params[] = $filebird_folder;
		}

		$join_sql  = implode( "\n\t\t\t", $joins );
		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			{$join_sql}
			WHERE {$where_sql}";
		$rows_sql  = "SELECT DISTINCT p.ID AS attachment_id, p.post_date
			FROM {$wpdb->posts} p
			{$join_sql}
			WHERE {$where_sql}
			ORDER BY p.post_date DESC
			LIMIT %d OFFSET %d";

		if ( empty( $params ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows_raw = $wpdb->get_results( $wpdb->prepare( $rows_sql, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows_raw = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = array();
		foreach ( (array) $rows_raw as $row ) {
			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			$rows[] = array(
				'attachment_id' => $attachment_id,
				'title'         => get_the_title( $attachment_id ),
				'edit_url'      => add_query_arg(
					array(
						'item' => $attachment_id,
					),
					admin_url( 'upload.php' )
				),
				'thumb_html'    => wp_get_attachment_image( $attachment_id, 'medium' ),
			);
		}

		$max_page = max( 1, (int) ceil( $total / $per_page ) );

		return array(
			'total'     => $total,
			'page'      => $page,
			'per_page'  => $per_page,
			'rows'      => $rows,
			'has_more'  => $page < $max_page,
			'next_page' => $page + 1,
		);
	}

	/**
	 * Render browse cards for media attachments.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return string
	 */
	private function render_browse_cards_html( $rows ) {
		if ( empty( $rows ) ) {
			return '<div class="ai-alt-browse-empty">' . esc_html__( 'No images matched your filters.', 'dynamic-alt-tags' ) . '</div>';
		}

		ob_start();
		foreach ( $rows as $row ) {
			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$title         = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			$edit_url      = isset( $row['edit_url'] ) ? esc_url_raw( (string) $row['edit_url'] ) : '';
			$thumb_html    = isset( $row['thumb_html'] ) ? (string) $row['thumb_html'] : '';
			?>
			<article class="ai-alt-browse-card" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
				<span class="ai-alt-browse-selection-indicator" aria-hidden="true">
					<span class="ai-alt-browse-select-check"></span>
				</span>
				<a class="ai-alt-browse-thumb-link" href="<?php echo esc_url( $edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open attachment details for %s', 'dynamic-alt-tags' ), '' !== $title ? $title : '#' . $attachment_id ) ); ?>">
					<?php if ( '' !== $thumb_html ) : ?>
						<?php echo wp_kses_post( $thumb_html ); ?>
					<?php else : ?>
						<span class="ai-alt-browse-thumb-fallback"><?php esc_html_e( 'No image', 'dynamic-alt-tags' ); ?></span>
					<?php endif; ?>
				</a>
			</article>
			<?php
		}
		return (string) ob_get_clean();
	}

	/**
	 * Parse a comma-separated attachment ID list.
	 *
	 * @param mixed $value Raw list.
	 * @return array<int,int>
	 */
	private function parse_attachment_ids_list( $value ) {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = preg_split( '/[\s,]+/', (string) $value );
		}

		if ( ! is_array( $parts ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $parts ) ) ) );
	}

	/**
	 * Render active/history queue rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @param bool                           $is_history History tab.
	 * @return string
	 */
	private function render_queue_rows_html( $rows, $is_history = false ) {
		$label_select    = __( 'Select', 'dynamic-alt-tags' );
		$label_image     = __( 'Image', 'dynamic-alt-tags' );
		$label_status    = __( 'Status', 'dynamic-alt-tags' );
		$label_conf      = __( 'Confidence', 'dynamic-alt-tags' );
		$label_suggested = __( 'Suggested Alt Text', 'dynamic-alt-tags' );
		$label_alt       = __( 'Alt Text', 'dynamic-alt-tags' );
		$label_processed = __( 'Processed On', 'dynamic-alt-tags' );
		$label_actions   = __( 'Actions', 'dynamic-alt-tags' );

		ob_start();
		foreach ( $rows as $row ) {
			$row_id        = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$status        = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			$needs_generation = in_array( $status, array( 'queued', 'failed' ), true );
			$is_queued        = 'queued' === $status;
			$confidence    = isset( $row['confidence'] ) ? (float) $row['confidence'] : 0.0;
			$suggested     = isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
			$final_alt     = isset( $row['final_alt'] ) ? (string) $row['final_alt'] : '';
			$display_alt   = $is_history && '' !== trim( $final_alt ) ? $final_alt : $suggested;
			$display_alt_lines = max( 2, substr_count( wordwrap( $display_alt, 64, "\n", true ), "\n" ) + 1 );
			$display_alt_rows  = min( 16, $display_alt_lines );
			$processed_on  = '';
			if ( isset( $row['updated_at'] ) && '' !== trim( (string) $row['updated_at'] ) ) {
				try {
					$processed_dt = new DateTime( (string) $row['updated_at'], wp_timezone() );
					$processed_on = $processed_dt->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T' );
				} catch ( Exception $e ) {
					$processed_on = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['updated_at'], false );
				}
			}
			$thumb         = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
			$image_url     = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			?>
			<tr>
				<th scope="row" class="check-column" data-label="<?php echo esc_attr( $label_select ); ?>">
					<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( (string) $row_id ); ?>"><?php esc_html_e( 'Select item', 'dynamic-alt-tags' ); ?></label>
					<input id="cb-select-<?php echo esc_attr( (string) $row_id ); ?>" type="checkbox" class="ai-alt-row-checkbox" data-needs-generation="<?php echo $needs_generation ? '1' : '0'; ?>" data-is-queued="<?php echo $is_queued ? '1' : '0'; ?>" name="selected_row_ids[]" value="<?php echo esc_attr( (string) $row_id ); ?>" />
				</th>
				<td class="ai-alt-col-image" data-label="<?php echo esc_attr( $label_image ); ?>">
					<?php if ( $thumb ) : ?>
						<?php if ( ! empty( $image_url ) ) : ?>
							<a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses_post( $thumb ); ?></a>
						<?php else : ?>
							<?php echo wp_kses_post( $thumb ); ?>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( 'N/A', 'dynamic-alt-tags' ); ?>
					<?php endif; ?>
					<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
				</td>
				<td class="ai-alt-col-status" data-label="<?php echo esc_attr( $label_status ); ?>"><code class="ai-alt-row-status"><?php echo esc_html( $status ); ?></code></td>
				<?php if ( ! $is_history ) : ?>
					<td class="ai-alt-row-confidence ai-alt-col-confidence" data-label="<?php echo esc_attr( $label_conf ); ?>"><?php echo esc_html( number_format_i18n( $confidence, 2 ) ); ?></td>
				<?php endif; ?>
				<?php if ( $is_history ) : ?>
					<td class="ai-alt-col-alt" data-label="<?php echo esc_attr( $label_alt ); ?>"><?php echo '' !== trim( $display_alt ) ? esc_html( $display_alt ) : esc_html__( 'None', 'dynamic-alt-tags' ); ?></td>
				<?php endif; ?>
				<td class="<?php echo esc_attr( $is_history ? 'ai-alt-col-processed' : 'ai-alt-col-suggested' ); ?>" data-label="<?php echo esc_attr( $is_history ? $label_processed : $label_suggested ); ?>">
					<?php if ( $is_history ) : ?>
						<?php echo '' !== $processed_on ? esc_html( $processed_on ) : '-'; ?>
					<?php else : ?>
						<textarea class="regular-text ai-alt-row-suggested" name="bulk_final_alt[<?php echo esc_attr( (string) $row_id ); ?>]" rows="<?php echo esc_attr( (string) $display_alt_rows ); ?>"><?php echo esc_textarea( $display_alt ); ?></textarea>
					<?php endif; ?>
				</td>
					<td class="ai-alt-col-actions" data-label="<?php echo esc_attr( $label_actions ); ?>">
						<?php if ( ! $is_history ) : ?>
							<button class="button button-primary" type="submit" name="single_action" value="<?php echo esc_attr( 'approve|' . $row_id ); ?>"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></button>
							<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'skip|' . $row_id ); ?>"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></button>
							<?php if ( in_array( $status, array( 'queued', 'failed', 'generated' ), true ) ) : ?>
								<button class="button ai-alt-row-process" type="button" data-row-id="<?php echo esc_attr( (string) $row_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ai_alt_queue_process_ajax' ) ); ?>"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></button>
							<?php endif; ?>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:8px;">
								<input type="hidden" name="action" value="ai_alt_queue_action" />
								<input type="hidden" name="return_view" value="history" />
								<?php wp_nonce_field( 'ai_alt_queue_action', 'ai_alt_queue_nonce' ); ?>
								<button class="button" type="submit" name="single_action" value="<?php echo esc_attr( 'requeue|' . $row_id ); ?>"><?php esc_html_e( 'Re-queue', 'dynamic-alt-tags' ); ?></button>
							</form>
						<?php endif; ?>
					<?php if ( ! empty( $image_url ) ) : ?>
						<a class="button" href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Image', 'dynamic-alt-tags' ); ?></a>
					<?php endif; ?>
					<?php if ( ! $is_history ) : ?>
						<div class="ai-alt-progress-wrap ai-alt-row-progress-wrap" hidden>
							<div class="ai-alt-progress-bar ai-alt-row-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
						</div>
						<p class="description ai-alt-row-process-message" aria-live="polite"></p>
					<?php endif; ?>
				</td>
			</tr>
			<?php
		}
		return (string) ob_get_clean();
	}

	/**
	 * Render no-alt rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return string
	 */
	private function render_no_alt_rows_html( $rows ) {
		$label_image   = __( 'Image', 'dynamic-alt-tags' );
		$label_alt     = __( 'Alt Text', 'dynamic-alt-tags' );
		$label_status  = __( 'Queue Status', 'dynamic-alt-tags' );
		$label_actions = __( 'Actions', 'dynamic-alt-tags' );

		ob_start();
		foreach ( $rows as $row ) {
			$attachment_id    = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			$queue_status     = isset( $row['queue_status'] ) ? sanitize_key( (string) $row['queue_status'] ) : '';
			$is_active_status = in_array( $queue_status, array( 'queued', 'processing', 'generated' ), true );
			$button_label     = __( 'Add to Queue', 'dynamic-alt-tags' );
			if ( '' !== $queue_status && ! $is_active_status ) {
				$button_label = __( 'Requeue', 'dynamic-alt-tags' );
			} elseif ( $is_active_status ) {
				$button_label = __( 'Queued', 'dynamic-alt-tags' );
			}
			$thumb     = $attachment_id ? wp_get_attachment_image( $attachment_id, array( 80, 80 ), false, array( 'style' => 'max-width:80px;height:auto;' ) ) : '';
			$image_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			?>
			<tr>
				<td class="ai-alt-col-image" data-label="<?php echo esc_attr( $label_image ); ?>">
					<?php if ( $thumb ) : ?>
						<?php if ( ! empty( $image_url ) ) : ?>
							<a href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses_post( $thumb ); ?></a>
						<?php else : ?>
							<?php echo wp_kses_post( $thumb ); ?>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( 'N/A', 'dynamic-alt-tags' ); ?>
					<?php endif; ?>
					<div>#<?php echo esc_html( (string) $attachment_id ); ?></div>
				</td>
				<td class="ai-alt-col-alt" data-label="<?php echo esc_attr( $label_alt ); ?>"><?php esc_html_e( 'None', 'dynamic-alt-tags' ); ?></td>
				<td class="ai-alt-col-status" data-label="<?php echo esc_attr( $label_status ); ?>"><code class="ai-alt-no-alt-queue-status"><?php echo '' !== $queue_status ? esc_html( $queue_status ) : esc_html__( 'not_queued', 'dynamic-alt-tags' ); ?></code></td>
					<td class="ai-alt-col-actions" data-label="<?php echo esc_attr( $label_actions ); ?>">
						<button class="button ai-alt-add-no-alt" type="button" data-attachment-id="<?php echo esc_attr( (string) $attachment_id ); ?>" <?php echo $is_active_status ? 'disabled' : ''; ?>><?php echo esc_html( $button_label ); ?></button>
					<?php if ( ! empty( $image_url ) ) : ?>
						<a class="button" href="<?php echo esc_url( $image_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Image', 'dynamic-alt-tags' ); ?></a>
					<?php endif; ?>
					<p class="description ai-alt-no-alt-message" aria-live="polite"></p>
				</td>
			</tr>
			<?php
		}
		return (string) ob_get_clean();
	}

	/**
	 * Build diagnostic message when processing returns zero.
	 *
	 * @param array<string,int> $before Status counts before run.
	 * @param array<string,int> $after Status counts after run.
	 * @return string
	 */
	private function get_zero_processed_message( $before, $after ) {
		$provider_failure = $this->get_latest_provider_wide_failure();
		if ( $provider_failure['message'] ) {
			return $provider_failure['message'];
		}

		$queued_before     = isset( $before['queued'] ) ? absint( $before['queued'] ) : 0;
		$failed_before     = isset( $before['failed'] ) ? absint( $before['failed'] ) : 0;
		$generated_before  = isset( $before['generated'] ) ? absint( $before['generated'] ) : 0;
		$processing_before = isset( $before['processing'] ) ? absint( $before['processing'] ) : 0;

		$queued_after     = isset( $after['queued'] ) ? absint( $after['queued'] ) : 0;
		$failed_after     = isset( $after['failed'] ) ? absint( $after['failed'] ) : 0;
		$generated_after  = isset( $after['generated'] ) ? absint( $after['generated'] ) : 0;
		$processing_after = isset( $after['processing'] ) ? absint( $after['processing'] ) : 0;

		if ( 0 === $queued_before && 0 === $failed_before && 0 === $generated_before && 0 === $processing_before ) {
			return __( 'No queue items were available to process.', 'dynamic-alt-tags' );
		}

		if ( 0 === $queued_before && 0 === $failed_before && $generated_before > 0 ) {
			return __( 'No items were processed because active items are already generated and waiting for review.', 'dynamic-alt-tags' );
		}

		if ( 0 === $queued_before && 0 === $failed_before && $processing_before > 0 ) {
			return __( 'No items were processed because queue jobs are currently locked in processing. Try again shortly.', 'dynamic-alt-tags' );
		}

		$latest_failed = $this->queue_repo->get_latest_failed_row();
		if ( is_array( $latest_failed ) ) {
			$error_message = isset( $latest_failed['error_message'] ) ? sanitize_text_field( (string) $latest_failed['error_message'] ) : '';
			if ( '' !== $error_message ) {
				return sprintf(
					/* translators: %s provider error detail */
					__( 'No images were processed. Latest provider error: %s', 'dynamic-alt-tags' ),
					$error_message
				);
			}
		}

		if ( $queued_after < $queued_before && $failed_after === $failed_before ) {
			return __( 'No items were processed because claimed queue items were skipped.', 'dynamic-alt-tags' );
		}

		return __( 'No items were processed. Check queue item status and provider connectivity details.', 'dynamic-alt-tags' );
	}

	/**
	 * Test provider connection.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! $this->current_user_can_view_settings() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_tools_action', 'ai_alt_tools_nonce' );

		$options  = $this->settings->get_options();
		$use_url_mode = ! empty( $options['use_url_mode'] );
		if ( ! array_key_exists( 'use_url_mode', $options ) && array_key_exists( 'direct_upload_mode', $options ) ) {
			$use_url_mode = empty( $options['direct_upload_mode'] );
		}

		$status   = 'success';
		$messages = array();
		$provider = new WPAI_Alt_Text_Provider_Cloudflare( $this->settings );
		$result   = $provider->generate_caption(
			'https://s.w.org/style/images/about/WordPress-logotype-wmark.png',
			array(
				'attachment_title' => 'WordPress logo',
				'post_title'       => 'Provider test',
			)
		);

		if ( is_wp_error( $result ) ) {
			$status     = 'error';
			$messages[] = sprintf(
				/* translators: %s error message */
				__( 'Baseline test failed: %s', 'dynamic-alt-tags' ),
				$result->get_error_message()
			);
		} elseif ( ! is_array( $result ) || empty( $result['caption'] ) ) {
			$status     = 'error';
			$messages[] = __( 'Baseline test failed: provider responded without a usable caption.', 'dynamic-alt-tags' );
		} else {
			$messages[] = __( 'Baseline test succeeded.', 'dynamic-alt-tags' );
		}

		$row = $this->queue_repo->get_latest_active_non_svg_row();
		if ( ! is_array( $row ) || empty( $row['attachment_id'] ) ) {
			$messages[] = __( 'Latest queued image test skipped: no non-SVG active queue item found.', 'dynamic-alt-tags' );
		} else {
			$attachment_id = absint( $row['attachment_id'] );
			$image_url     = wp_get_attachment_url( $attachment_id );

			if ( ! $image_url ) {
				$status     = 'error';
				$messages[] = __( 'Latest queued image test failed: attachment URL not found.', 'dynamic-alt-tags' );
			} elseif ( $use_url_mode && ! $this->is_provider_reachable_url( $image_url ) ) {
				$messages[] = sprintf(
					/* translators: %d attachment id */
					__( 'Latest queued image test skipped (attachment #%d): image URL appears local/private and is not reachable from the provider in URL mode.', 'dynamic-alt-tags' ),
					$attachment_id
				);
			} else {
				$latest_result = $provider->generate_caption(
					$image_url,
					array(
						'attachment_id'    => $attachment_id,
						'attachment_title' => get_the_title( $attachment_id ),
						'post_title'       => 'Provider latest-image test',
					)
				);

					if ( is_wp_error( $latest_result ) ) {
						if ( 'ai_alt_svg_not_supported' === $latest_result->get_error_code() ) {
							$messages[] = sprintf(
								/* translators: 1: attachment id, 2: error message */
								__( 'Latest queued image test skipped (attachment #%1$d): %2$s', 'dynamic-alt-tags' ),
								$attachment_id,
								$latest_result->get_error_message()
							);
						} else {
							$status     = 'error';
							$messages[] = sprintf(
								/* translators: 1: attachment id, 2: error message */
								__( 'Latest queued image test failed (attachment #%1$d): %2$s', 'dynamic-alt-tags' ),
								$attachment_id,
								$latest_result->get_error_message()
							);
						}
					} elseif ( ! is_array( $latest_result ) || empty( $latest_result['caption'] ) ) {
					$status     = 'error';
					$messages[] = sprintf(
						/* translators: %d attachment id */
						__( 'Latest queued image test failed (attachment #%d): no usable caption returned.', 'dynamic-alt-tags' ),
						$attachment_id
					);
				} else {
					$messages[] = sprintf(
						/* translators: %d attachment id */
						__( 'Latest queued image test succeeded (attachment #%d).', 'dynamic-alt-tags' ),
						$attachment_id
					);
				}
			}
		}

		$message = implode( ' ', $messages );
		$this->record_connection_check( $status, $message );

		$redirect = add_query_arg(
			array(
				'page'        => 'ai-alt-text-settings',
				'notice'      => 'provider_test',
				'test_status' => rawurlencode( $status ),
				'test_msg'    => rawurlencode( $message ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Save latest provider connection check result.
	 *
	 * @param string $status Test status.
	 * @param string $message Test message.
	 * @return void
	 */
	private function record_connection_check( $status, $message ) {
		$status  = 'success' === $status ? 'success' : 'error';
		$message = sanitize_text_field( (string) $message );

		update_option(
			self::CONNECTION_STATUS_OPTION_KEY,
			array(
				'status'     => $status,
				'message'    => $message,
				'checked_at' => current_time( 'mysql' ),
			),
			false
		);
	}

	/**
	 * Build settings connection status payload for UI.
	 *
	 * @return array<string,mixed>
	 */
	private function get_connection_status() {
		$options = $this->settings->get_options();
		$state   = 'unknown';
		$title   = __( 'Not Checked', 'dynamic-alt-tags' );
		$message = __( 'Run "Test Provider Connection" to verify connectivity.', 'dynamic-alt-tags' );

		if ( empty( $options['worker_url'] ) ) {
			$state   = 'error';
			$title   = __( 'Not Configured', 'dynamic-alt-tags' );
			$message = __( 'Cloudflare Worker URL is required.', 'dynamic-alt-tags' );
		}

		$saved = get_option( self::CONNECTION_STATUS_OPTION_KEY, array() );
		$checked_at = '';
		if ( is_array( $saved ) && ! empty( $saved['checked_at'] ) ) {
			$checked_at = sanitize_text_field( (string) $saved['checked_at'] );
		}

		if ( is_array( $saved ) && ! empty( $saved['status'] ) ) {
			$saved_status  = 'success' === sanitize_key( (string) $saved['status'] ) ? 'success' : 'error';
			$saved_message = isset( $saved['message'] ) ? sanitize_text_field( (string) $saved['message'] ) : '';

			if ( 'success' === $saved_status ) {
				$state = 'ok';
				$title = __( 'Connected', 'dynamic-alt-tags' );
				if ( '' !== $saved_message ) {
					$message = $saved_message;
				}
			} else {
				$state   = 'error';
				$title   = __( 'Connection Error', 'dynamic-alt-tags' );
				$message = '' !== $saved_message ? $saved_message : __( 'Provider check failed.', 'dynamic-alt-tags' );
			}
		}

		$latest_failed = $this->queue_repo->get_latest_failed_row();
		$queue_error   = '';
		if ( is_array( $latest_failed ) ) {
			$failed_at_raw = isset( $latest_failed['updated_at'] ) ? sanitize_text_field( (string) $latest_failed['updated_at'] ) : '';
			$show_failure  = true;
			if ( '' !== $checked_at && '' !== $failed_at_raw ) {
				$checked_ts = strtotime( $checked_at );
				$failed_ts  = strtotime( $failed_at_raw );
				if ( false !== $checked_ts && false !== $failed_ts && $failed_ts <= $checked_ts ) {
					$show_failure = false;
				}
			}

			if ( $show_failure ) {
				$queue_error = isset( $latest_failed['error_message'] ) ? sanitize_text_field( (string) $latest_failed['error_message'] ) : '';
				if ( '' === $queue_error && ! empty( $latest_failed['error_code'] ) ) {
					$queue_error = sprintf(
						/* translators: %s error code */
						__( 'Latest queue failure code: %s', 'dynamic-alt-tags' ),
						sanitize_key( (string) $latest_failed['error_code'] )
					);
				}
			}
			if ( '' !== $queue_error && 'error' !== $state ) {
				$state = 'warning';
			}
		}

		return array(
			'state'       => $state,
			'title'       => $title,
			'message'     => $message,
			'checked_at'  => $checked_at,
			'queue_error' => $queue_error,
		);
	}

	/**
	 * Whether a URL is likely publicly reachable by the external provider.
	 *
	 * @param string $url URL to evaluate.
	 * @return bool
	 */
	private function is_provider_reachable_url( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === trim( $host ) ) {
			return false;
		}

		$host = strtolower( trim( $host ) );

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var(
				$host,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
		}

		if ( 'localhost' === $host || false === strpos( $host, '.' ) ) {
			return false;
		}

		if ( preg_match( '/\.(local|localhost|test|invalid)$/', $host ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handle queue actions.
	 *
	 * @return void
	 */
	public function handle_queue_action() {
		if ( ! $this->current_user_can_view_queue() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dynamic-alt-tags' ) );
		}

		check_admin_referer( 'ai_alt_queue_action', 'ai_alt_queue_nonce' );

		$allowed_actions = array( 'approve', 'reject', 'skip', 'process', 'requeue' );
		$updated_count   = 0;
		$return_view     = isset( $_POST['return_view'] ) ? sanitize_key( wp_unslash( $_POST['return_view'] ) ) : '';
		$return_view     = in_array( $return_view, array( 'dashboard', 'active', 'history', 'no_alt', 'search', 'browse' ), true ) ? $return_view : '';
		if ( 'browse' === $return_view ) {
			$return_view = 'search';
		}

		$single_action = isset( $_POST['single_action'] ) ? sanitize_text_field( wp_unslash( $_POST['single_action'] ) ) : '';
		if ( '' !== $single_action ) {
			$parts = explode( '|', $single_action );
			if ( 2 !== count( $parts ) ) {
				wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
			}

			$action = sanitize_key( $parts[0] );
			$row_id = absint( $parts[1] );
			if ( ! $row_id || ! in_array( $action, $allowed_actions, true ) ) {
				wp_die( esc_html__( 'Invalid request.', 'dynamic-alt-tags' ) );
			}

			$bulk_final_alt_raw = isset( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$alts               = is_array( $bulk_final_alt_raw ) ? $bulk_final_alt_raw : array();
			$alt  = isset( $alts[ $row_id ] ) ? sanitize_text_field( (string) $alts[ $row_id ] ) : '';
			$applied = $this->apply_queue_action( $row_id, $action, $alt );
			$updated_count = $applied ? 1 : 0;

			$notice = 'queue_updated';
			if ( 'process' === $action ) {
				$notice = 'queue_process_done';
			}

			$redirect_args = array(
				'page'    => 'ai-alt-text-queue',
				'notice'  => $notice,
				'updated' => $updated_count,
			);
			if ( '' !== $return_view ) {
				$redirect_args['view'] = $return_view;
			}
			$redirect = add_query_arg( $redirect_args, admin_url( 'upload.php' ) );

			wp_safe_redirect( $redirect );
			exit;
		} else {
			$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
			if ( '' === $bulk_action || '-1' === $bulk_action ) {
				$bulk_action = isset( $_POST['bulk_action2'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action2'] ) ) : '';
			}

			if ( ! in_array( $bulk_action, $allowed_actions, true ) ) {
				$redirect_args = array(
					'page'      => 'ai-alt-text-queue',
					'notice'    => 'queue_error',
					'queue_msg' => rawurlencode( __( 'Please select a bulk action before clicking Apply.', 'dynamic-alt-tags' ) ),
				);
				if ( '' !== $return_view ) {
					$redirect_args['view'] = $return_view;
				}
				$redirect = add_query_arg( $redirect_args, admin_url( 'upload.php' ) );

				wp_safe_redirect( $redirect );
				exit;
			}

			$selected_ids_raw = isset( $_POST['selected_row_ids'] ) ? wp_unslash( $_POST['selected_row_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$selected_ids     = is_array( $selected_ids_raw ) ? $selected_ids_raw : array();
			$selected_ids     = array_values( array_filter( array_map( 'absint', $selected_ids ) ) );
			if ( empty( $selected_ids ) ) {
				wp_die( esc_html__( 'No queue items selected.', 'dynamic-alt-tags' ) );
			}

			$bulk_final_alt_raw = isset( $_POST['bulk_final_alt'] ) ? wp_unslash( $_POST['bulk_final_alt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$alts               = is_array( $bulk_final_alt_raw ) ? $bulk_final_alt_raw : array();
			foreach ( $selected_ids as $row_id ) {
				$alt = isset( $alts[ $row_id ] ) ? sanitize_text_field( (string) $alts[ $row_id ] ) : '';
				if ( $this->apply_queue_action( $row_id, $bulk_action, $alt ) ) {
					++$updated_count;
				}
			}
		}

		$redirect_args = array(
			'page'    => 'ai-alt-text-queue',
			'notice'  => 'queue_updated',
			'updated' => $updated_count,
		);
		if ( '' !== $return_view ) {
			$redirect_args['view'] = $return_view;
		}
		$redirect = add_query_arg( $redirect_args, admin_url( 'upload.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Apply one queue action.
	 *
	 * @param int    $row_id Queue row ID.
	 * @param string $action Action key.
	 * @param string $alt Alt text.
	 * @return bool
	 */
	private function apply_queue_action( $row_id, $action, $alt ) {
		$row_id = absint( $row_id );
		if ( ! $row_id ) {
			return false;
		}

		$row = $this->queue_repo->get_row( $row_id );
		if ( ! is_array( $row ) || empty( $row['attachment_id'] ) ) {
			return false;
		}

		$attachment_id = absint( $row['attachment_id'] );
		if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return false;
		}

		if ( 'approve' === $action ) {
			if ( '' === trim( (string) $alt ) ) {
				$alt = is_array( $row ) && isset( $row['suggested_alt'] ) ? (string) $row['suggested_alt'] : '';
			}
			return $this->processor->approve_row( $row_id, $alt );
		} elseif ( 'reject' === $action ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
			update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
			return $this->queue_repo->mark_final( $row_id, 'rejected', '' );
		} elseif ( 'skip' === $action ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
			update_post_meta( $attachment_id, '_ai_alt_review_required', 0 );
			return $this->queue_repo->mark_final( $row_id, 'skipped', '' );
		} elseif ( 'process' === $action ) {
			$message = '';
			return $this->process_queue_row( $row_id, $message );
		} elseif ( 'requeue' === $action ) {
			$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
			return $this->queue_repo->enqueue_or_requeue( $attachment_id, $post_id );
		}

		return false;
	}

	/**
	 * Process one queue row by attachment id.
	 *
	 * @param int         $row_id Queue row ID.
	 * @param string|null $message Error message output.
	 * @return bool
	 */
	private function process_queue_row( $row_id, &$message = null ) {
		$message = '';
		$row     = $this->queue_repo->get_row( $row_id );
		if ( ! is_array( $row ) || empty( $row['attachment_id'] ) ) {
			$message = __( 'Queue row was not found.', 'dynamic-alt-tags' );
			return false;
		}

		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
		if ( 'generated' === $status ) {
			$this->queue_repo->mark_failed( $row_id, 'manual_reprocess', 'Manual reprocess requested.' );
		}

		$attachment_id = absint( $row['attachment_id'] );
		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			$message = __( 'You do not have permission to edit this attachment.', 'dynamic-alt-tags' );
			return false;
		}
		$processed     = $this->processor->process_attachment_for_review( $attachment_id );
		if ( ! $processed ) {
			$latest_row = $this->queue_repo->get_row( $row_id );
			if ( is_array( $latest_row ) && ! empty( $latest_row['error_message'] ) ) {
				$message = sanitize_text_field( (string) $latest_row['error_message'] );
			} else {
				$message = __( 'Image processing failed. Please try again.', 'dynamic-alt-tags' );
			}
			return false;
		}

		return true;
	}

	/**
	 * Get the latest provider-wide failure details, if any.
	 *
	 * @return array<string,mixed>
	 */
	private function get_latest_provider_wide_failure( $since = '' ) {
		$latest_failed = $this->queue_repo->get_latest_failed_row();
		if ( ! is_array( $latest_failed ) || ! $this->is_provider_wide_queue_row( $latest_failed ) ) {
			return array(
				'provider_wide' => false,
				'code'          => '',
				'message'       => '',
			);
		}

		if ( '' !== $since ) {
			$since_ts = strtotime( (string) $since );
			$row_ts   = isset( $latest_failed['updated_at'] ) ? strtotime( (string) $latest_failed['updated_at'] ) : false;
			if ( false !== $since_ts && false !== $row_ts && $row_ts < $since_ts ) {
				return array(
					'provider_wide' => false,
					'code'          => '',
					'message'       => '',
				);
			}
		}

		return array(
			'provider_wide' => true,
			'code'          => isset( $latest_failed['error_code'] ) ? sanitize_key( (string) $latest_failed['error_code'] ) : '',
			'message'       => isset( $latest_failed['error_message'] ) ? sanitize_text_field( (string) $latest_failed['error_message'] ) : '',
		);
	}

	/**
	 * Determine whether a queue row represents a provider-wide failure.
	 *
	 * @param array<string,mixed>|null $row Queue row.
	 * @return bool
	 */
	private function is_provider_wide_queue_row( $row ) {
		if ( ! is_array( $row ) || empty( $row['error_code'] ) ) {
			return false;
		}

		return WPAI_Alt_Text_Provider_Cloudflare::is_provider_wide_error_code(
			sanitize_key( (string) $row['error_code'] )
		);
	}

	/**
	 * Build settings metrics display values keyed by DOM id.
	 *
	 * @return array<string,string>
	 */
	private function get_settings_metrics_fields() {
		$metrics  = $this->settings->get_metrics();
		$coverage = $this->queue_repo->get_image_alt_coverage_counts();

		$total_images          = isset( $coverage['total_images'] ) ? absint( $coverage['total_images'] ) : 0;
		$images_with_alt       = isset( $coverage['with_alt'] ) ? absint( $coverage['with_alt'] ) : 0;
		$images_without_alt    = isset( $coverage['without_alt'] ) ? absint( $coverage['without_alt'] ) : 0;
		$total_processed       = isset( $metrics['total_images_processed'] ) ? absint( $metrics['total_images_processed'] ) : 0;
		$success_count         = isset( $metrics['success_count'] ) ? absint( $metrics['success_count'] ) : 0;
		$failure_count         = isset( $metrics['failure_count'] ) ? absint( $metrics['failure_count'] ) : 0;
		$provider_call_count   = isset( $metrics['provider_call_count'] ) ? absint( $metrics['provider_call_count'] ) : 0;
		$total_processing_ms   = isset( $metrics['total_processing_time_ms'] ) ? (float) $metrics['total_processing_time_ms'] : 0.0;
		$total_provider_ms     = isset( $metrics['total_provider_latency_ms'] ) ? (float) $metrics['total_provider_latency_ms'] : 0.0;
		$last_processing_ms    = isset( $metrics['last_processing_time_ms'] ) ? (float) $metrics['last_processing_time_ms'] : 0.0;
		$last_provider_latency = isset( $metrics['last_provider_latency_ms'] ) ? (float) $metrics['last_provider_latency_ms'] : 0.0;
		$average_processing_ms = $total_processed > 0 ? $total_processing_ms / $total_processed : 0.0;
		$average_provider_ms   = $provider_call_count > 0 ? $total_provider_ms / $provider_call_count : 0.0;
		$last_processed_at     = isset( $metrics['last_processed_at'] ) ? sanitize_text_field( (string) $metrics['last_processed_at'] ) : '';
		$last_processed_text   = '';

		if ( '' !== $last_processed_at ) {
			$last_processed_text = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_processed_at );
			if ( ! is_string( $last_processed_text ) || '' === $last_processed_text ) {
				$last_processed_text = $last_processed_at;
			}
		}

		return array(
			'ai-alt-metric-total-images'             => number_format_i18n( $total_images ),
			'ai-alt-metric-images-with-alt'          => number_format_i18n( $images_with_alt ),
			'ai-alt-metric-images-without-alt'       => number_format_i18n( $images_without_alt ),
			'ai-alt-metric-total-processed'          => number_format_i18n( $total_processed ),
			'ai-alt-metric-success-count'            => number_format_i18n( $success_count ),
			'ai-alt-metric-failure-count'            => number_format_i18n( $failure_count ),
			'ai-alt-metric-average-processing'       => number_format_i18n( $average_processing_ms, 2 ) . ' ms',
			'ai-alt-metric-average-provider-latency' => number_format_i18n( $average_provider_ms, 2 ) . ' ms',
			'ai-alt-metric-last-processing'          => number_format_i18n( $last_processing_ms, 2 ) . ' ms',
			'ai-alt-metric-last-provider-latency'    => number_format_i18n( $last_provider_latency, 2 ) . ' ms',
			'ai-alt-metric-last-processed-at'        => '' !== $last_processed_text ? $last_processed_text : __( 'Not yet recorded', 'dynamic-alt-tags' ),
		);
	}

	/**
	 * Check whether current user can access plugin settings/queue pages.
	 *
	 * @return bool
	 */
	private function current_user_can_view_settings() {
		return $this->settings->current_user_can_access_settings();
	}

	/**
	 * Check queue/media access for current user.
	 *
	 * @return bool
	 */
	private function current_user_can_view_queue() {
		return $this->settings->current_user_can_access_queue();
	}

	/**
	 * Determine whether an attachment is SVG.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_svg_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		return 'image/svg+xml' === (string) get_post_mime_type( $attachment_id );
	}
}
