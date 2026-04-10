<?php
/**
 * Queue page template.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows         = isset( $data['rows'] ) && is_array( $data['rows'] ) ? $data['rows'] : array();
$total        = isset( $data['total'] ) ? absint( $data['total'] ) : 0;
$page_num     = isset( $data['page'] ) ? absint( $data['page'] ) : 1;
$per_page     = isset( $data['per_page'] ) ? absint( $data['per_page'] ) : 20;
$max_pages    = max( 1, (int) ceil( $total / $per_page ) );
$has_more     = $page_num < $max_pages;
$has_more     = isset( $queue_has_more ) && null !== $queue_has_more ? (bool) $queue_has_more : $has_more;
$total_images = isset( $total_images ) ? absint( $total_images ) : 0;
$status       = isset( $status ) ? sanitize_key( (string) $status ) : '';
$view         = isset( $view ) && in_array( $view, array( 'dashboard', 'active', 'history', 'no_alt', 'search', 'browse' ), true ) ? $view : 'dashboard';
$view         = 'browse' === $view ? 'search' : $view;
$is_dashboard = 'dashboard' === $view;
$is_history   = 'history' === $view;
$is_no_alt    = 'no_alt' === $view;
$is_search    = 'search' === $view;
$is_active    = ! $is_dashboard && ! $is_history && ! $is_no_alt && ! $is_search;
$refresh_args = array(
	'page' => 'ai-alt-text-queue',
	'view' => $view,
);
if ( '' !== $status ) {
	$refresh_args['status'] = $status;
}
if ( $page_num > 1 ) {
	$refresh_args['paged'] = $page_num;
}

$browse_date = isset( $browse_filters['date'] ) ? sanitize_text_field( (string) $browse_filters['date'] ) : ( isset( $_GET['browse_date'] ) ? sanitize_text_field( wp_unslash( $_GET['browse_date'] ) ) : '' );
$browse_search = isset( $browse_filters['search'] ) ? sanitize_text_field( (string) $browse_filters['search'] ) : ( isset( $_GET['browse_search'] ) ? sanitize_text_field( wp_unslash( $_GET['browse_search'] ) ) : '' );
$browse_alt_filter = isset( $browse_filters['alt_filter'] ) ? sanitize_key( (string) $browse_filters['alt_filter'] ) : ( isset( $_GET['browse_alt_filter'] ) ? sanitize_key( wp_unslash( $_GET['browse_alt_filter'] ) ) : ( isset( $_GET['browse_no_alt_only'] ) ? 'no_alt' : 'all' ) );
$browse_alt_filter = in_array( $browse_alt_filter, array( 'all', 'no_alt' ), true ) ? $browse_alt_filter : 'all';
$browse_category = isset( $browse_filters['category'] ) ? absint( $browse_filters['category'] ) : ( isset( $_GET['browse_category'] ) ? absint( wp_unslash( $_GET['browse_category'] ) ) : 0 );
$browse_filebird_folder = isset( $browse_filters['filebird_folder'] ) ? absint( $browse_filters['filebird_folder'] ) : ( isset( $_GET['browse_filebird_folder'] ) ? absint( wp_unslash( $_GET['browse_filebird_folder'] ) ) : 0 );
$browse_month_options = isset( $browse_month_options ) && is_array( $browse_month_options ) ? $browse_month_options : array();
$browse_category_taxonomy = isset( $browse_category_taxonomy ) ? sanitize_key( (string) $browse_category_taxonomy ) : '';
$browse_category_options  = isset( $browse_category_options ) && is_array( $browse_category_options ) ? $browse_category_options : array();
$browse_filebird_options  = isset( $browse_filebird_options ) && is_array( $browse_filebird_options ) ? $browse_filebird_options : array();
$browse_has_category_filter = '' !== $browse_category_taxonomy;
$browse_has_filebird_filter = ! empty( $browse_filebird_options );
$browse_filter_classes      = 'ai-alt-browse-filters';
if ( $browse_has_category_filter || $browse_has_filebird_filter ) {
	$browse_filter_classes .= ' ai-alt-browse-filters-has-extra-filter';
}
if ( $browse_has_category_filter && $browse_has_filebird_filter ) {
	$browse_filter_classes .= ' ai-alt-browse-filters-has-two-extra-filters';
}
$focused_queue_ids_csv     = isset( $focused_queue_ids ) && is_array( $focused_queue_ids ) ? implode( ',', array_map( 'absint', $focused_queue_ids ) ) : '';
$queue_load_more_next_page = isset( $queue_load_more_next_page ) ? max( 1, absint( $queue_load_more_next_page ) ) : ( $page_num + 1 );
$queued_row_count          = 0;
if ( $is_active && ! empty( $rows ) ) {
	foreach ( $rows as $queue_row ) {
		$queue_status = isset( $queue_row['status'] ) ? sanitize_key( (string) $queue_row['status'] ) : '';
		if ( 'queued' === $queue_status ) {
			++$queued_row_count;
		}
	}
}
if ( $is_search ) {
	if ( '' !== $browse_date ) {
		$refresh_args['browse_date'] = $browse_date;
	}
	if ( '' !== $browse_search ) {
		$refresh_args['browse_search'] = $browse_search;
	}
	if ( 'all' !== $browse_alt_filter ) {
		$refresh_args['browse_alt_filter'] = $browse_alt_filter;
	}
	if ( $browse_category > 0 ) {
		$refresh_args['browse_category'] = $browse_category;
	}
	if ( $browse_filebird_folder > 0 ) {
		$refresh_args['browse_filebird_folder'] = $browse_filebird_folder;
	}
}

$metrics = isset( $metrics ) && is_array( $metrics ) ? $metrics : array();
$coverage = isset( $coverage ) && is_array( $coverage ) ? $coverage : array();
$daily_metrics = isset( $daily_metrics ) && is_array( $daily_metrics ) ? $daily_metrics : array();
$processed_history_chart = isset( $processed_history_chart ) && is_array( $processed_history_chart ) ? $processed_history_chart : array();

$total_images_dashboard   = isset( $coverage['total_images'] ) ? absint( $coverage['total_images'] ) : 0;
$images_with_alt          = isset( $coverage['with_alt'] ) ? absint( $coverage['with_alt'] ) : 0;
$images_without_alt       = isset( $coverage['without_alt'] ) ? absint( $coverage['without_alt'] ) : 0;
$total_processed          = isset( $metrics['total_images_processed'] ) ? absint( $metrics['total_images_processed'] ) : 0;
$success_count            = isset( $metrics['success_count'] ) ? absint( $metrics['success_count'] ) : 0;
$failure_count            = isset( $metrics['failure_count'] ) ? absint( $metrics['failure_count'] ) : 0;
$provider_call_count      = isset( $metrics['provider_call_count'] ) ? absint( $metrics['provider_call_count'] ) : 0;
$total_processing_ms      = isset( $metrics['total_processing_time_ms'] ) ? (float) $metrics['total_processing_time_ms'] : 0.0;
$total_provider_ms        = isset( $metrics['total_provider_latency_ms'] ) ? (float) $metrics['total_provider_latency_ms'] : 0.0;
$last_processing_ms       = isset( $metrics['last_processing_time_ms'] ) ? (float) $metrics['last_processing_time_ms'] : 0.0;
$last_provider_latency    = isset( $metrics['last_provider_latency_ms'] ) ? (float) $metrics['last_provider_latency_ms'] : 0.0;
$average_processing_ms    = $total_processed > 0 ? $total_processing_ms / $total_processed : 0.0;
$average_provider_ms      = $provider_call_count > 0 ? $total_provider_ms / $provider_call_count : 0.0;
$last_processed_at        = isset( $metrics['last_processed_at'] ) ? sanitize_text_field( (string) $metrics['last_processed_at'] ) : '';
$last_processed_display   = '';
$processed_today          = isset( $daily_metrics['daily_images_processed'] ) ? absint( $daily_metrics['daily_images_processed'] ) : 0;
$processed_this_week      = isset( $metrics['weekly_images_processed'] ) ? absint( $metrics['weekly_images_processed'] ) : 0;
$processed_this_month     = isset( $metrics['monthly_images_processed'] ) ? absint( $metrics['monthly_images_processed'] ) : 0;
$processed_this_year      = isset( $metrics['yearly_images_processed'] ) ? absint( $metrics['yearly_images_processed'] ) : 0;
$chart_bar_style_attribute = isset( $chart_bar_style_attribute ) ? (string) $chart_bar_style_attribute : '';
if ( '' !== $last_processed_at ) {
	$last_processed_display = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_processed_at );
	if ( ! is_string( $last_processed_display ) || '' === $last_processed_display ) {
		$last_processed_display = $last_processed_at;
	}
}
?>
<div class="wrap ai-alt-wrap ai-alt-queue-page ai-alt-queue-view-<?php echo esc_attr( $view ); ?>" data-focused-queue-ids="<?php echo esc_attr( $focused_queue_ids_csv ); ?>"<?php echo '' !== $chart_bar_style_attribute ? ' style="' . esc_attr( $chart_bar_style_attribute ) . '"' : ''; ?>>
	<h1><?php esc_html_e( 'Dynamic Alt Tags', 'dynamic-alt-tags' ); ?></h1>
	<div class="ai-alt-queue-shell">
	<div class="ai-alt-queue-header-bar">
		<h2 class="nav-tab-wrapper ai-alt-queue-tabs">
			<a class="nav-tab <?php echo $is_dashboard ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'dashboard',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Dashboard', 'dynamic-alt-tags' ); ?></a>
			<a class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'active',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Active Queue', 'dynamic-alt-tags' ); ?></a>
			<a class="nav-tab <?php echo $is_search ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'search',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'Search', 'dynamic-alt-tags' ); ?></a>
			<a class="nav-tab <?php echo $is_history ? 'nav-tab-active' : ''; ?>" href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page' => 'ai-alt-text-queue',
						'view' => 'history',
					),
					admin_url( 'upload.php' )
				)
			);
			?>
			"><?php esc_html_e( 'History', 'dynamic-alt-tags' ); ?></a>
		</h2>
		<?php if ( $is_active ) : ?>
			<div class="ai-alt-queue-process-top">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ai_alt_run_backfill_queue" />
					<?php wp_nonce_field( 'ai_alt_tools_action', 'ai_alt_tools_nonce' ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Backfill', 'dynamic-alt-tags' ); ?></button>
				</form>
				<button type="button" class="button button-primary" id="ai-alt-generate-all-visible" <?php echo $queued_row_count > 0 ? '' : 'disabled'; ?>><?php esc_html_e( 'Generate Alt Text For Queued', 'dynamic-alt-tags' ); ?></button>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( $refresh_args, admin_url( 'upload.php' ) ) ); ?>"><?php esc_html_e( 'Refresh', 'dynamic-alt-tags' ); ?></a>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( isset( $_GET['notice'] ) && 'queue_updated' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Queue items updated: %d', 'dynamic-alt-tags' ),
					isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_process_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Image successfully processed', 'dynamic-alt-tags' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_batch_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Manual processing finished. %d items processed.', 'dynamic-alt-tags' ),
					isset( $_GET['processed'] ) ? absint( $_GET['processed'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['notice'] ) && 'queue_backfill_done' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					esc_html__( 'Backfill complete. %d images were queued. Previously processed images were skipped.', 'dynamic-alt-tags' ),
					isset( $_GET['enqueued'] ) ? absint( $_GET['enqueued'] ) : 0
				);
				?>
			</p>
		</div>
	<?php endif; ?>

<?php if ( isset( $_GET['notice'] ) && 'queue_error' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) : ?>
	<?php
		$queue_msg_raw   = isset( $_GET['queue_msg'] ) ? wp_unslash( $_GET['queue_msg'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$queue_error_msg = '' !== $queue_msg_raw ? sanitize_text_field( rawurldecode( (string) $queue_msg_raw ) ) : __( 'Unable to apply queue action.', 'dynamic-alt-tags' );
	?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $queue_error_msg ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $is_dashboard ) : ?>
		<p>
			<?php
			if ( $is_history ) {
				echo esc_html( sprintf( __( 'Total history items: %d', 'dynamic-alt-tags' ), $total ) );
			} elseif ( $is_no_alt ) {
				echo esc_html( sprintf( __( 'Total images with no alt text: %d', 'dynamic-alt-tags' ), $total ) );
			} elseif ( $is_search ) {
				esc_html_e( 'Search images using upload date and keyword filters.', 'dynamic-alt-tags' );
			} elseif ( $is_active && '' !== $focused_queue_ids_csv ) {
				echo esc_html( sprintf( __( 'Showing %d newly added queue item(s).', 'dynamic-alt-tags' ), count( $rows ) ) );
			} else {
				echo esc_html( sprintf( __( 'Total active queue items: %d', 'dynamic-alt-tags' ), $total ) );
			}
			?>
		</p>
	<?php endif; ?>

	<?php if ( $is_dashboard ) : ?>
		<div id="ai-alt-settings-panel-metrics">
			<h2><?php esc_html_e( 'Dashboard', 'dynamic-alt-tags' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Welcome to the Dynamic Alt Tags plugin.', 'dynamic-alt-tags' ); ?></p>

			<div class="ai-alt-metrics-grid">
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images on site', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-total-images"><?php echo esc_html( number_format_i18n( $total_images_dashboard ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images with alt text', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-images-with-alt"><?php echo esc_html( number_format_i18n( $images_with_alt ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images without alt text', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-images-without-alt"><?php echo esc_html( number_format_i18n( $images_without_alt ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Total images processed', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-total-processed"><?php echo esc_html( number_format_i18n( $total_processed ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images processed today', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-processed-today"><?php echo esc_html( number_format_i18n( $processed_today ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images processed this week', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-processed-this-week"><?php echo esc_html( number_format_i18n( $processed_this_week ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images processed this month', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-processed-this-month"><?php echo esc_html( number_format_i18n( $processed_this_month ) ); ?></span>
				</div>
				<div class="ai-alt-metric-card">
					<strong><?php esc_html_e( 'Images processed this year', 'dynamic-alt-tags' ); ?></strong>
					<span id="ai-alt-metric-processed-this-year"><?php echo esc_html( number_format_i18n( $processed_this_year ) ); ?></span>
				</div>
			</div>

			<div class="ai-alt-processed-chart" data-chart-series="<?php echo esc_attr( wp_json_encode( $processed_history_chart ) ); ?>">
				<div class="ai-alt-processed-chart-header">
					<div>
						<h3><?php esc_html_e( 'Images Processed Over Time', 'dynamic-alt-tags' ); ?></h3>
						<p><?php esc_html_e( 'Select day, week, month, and year views to compare completed image processing periods.', 'dynamic-alt-tags' ); ?></p>
					</div>
					<div class="ai-alt-processed-chart-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Processed images chart views', 'dynamic-alt-tags' ); ?>">
						<button type="button" class="button ai-alt-chart-toggle is-active" data-chart-view="day"><?php esc_html_e( 'Day', 'dynamic-alt-tags' ); ?></button>
						<button type="button" class="button ai-alt-chart-toggle" data-chart-view="week"><?php esc_html_e( 'Week', 'dynamic-alt-tags' ); ?></button>
						<button type="button" class="button ai-alt-chart-toggle" data-chart-view="month"><?php esc_html_e( 'Month', 'dynamic-alt-tags' ); ?></button>
						<button type="button" class="button ai-alt-chart-toggle" data-chart-view="year"><?php esc_html_e( 'Year', 'dynamic-alt-tags' ); ?></button>
					</div>
				</div>
				<div class="ai-alt-processed-chart-stage">
					<div class="ai-alt-processed-chart-plot" aria-live="polite"></div>
				</div>
			</div>

			<table class="widefat striped ai-alt-metrics-table">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Success count', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-success-count"><?php echo esc_html( number_format_i18n( $success_count ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Failure count', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-failure-count"><?php echo esc_html( number_format_i18n( $failure_count ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average processing time', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-average-processing"><?php echo esc_html( number_format_i18n( $average_processing_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Average provider latency', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-average-provider-latency"><?php echo esc_html( number_format_i18n( $average_provider_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last processing time', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-processing"><?php echo esc_html( number_format_i18n( $last_processing_ms, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last provider latency', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-provider-latency"><?php echo esc_html( number_format_i18n( $last_provider_latency, 2 ) ); ?> ms</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last processed at', 'dynamic-alt-tags' ); ?></th>
						<td id="ai-alt-metric-last-processed-at"><?php echo '' !== $last_processed_display ? esc_html( $last_processed_display ) : esc_html__( 'Not yet recorded', 'dynamic-alt-tags' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

	<?php elseif ( $is_search ) : ?>
		<form id="ai-alt-browse-filters" class="<?php echo esc_attr( $browse_filter_classes ); ?>" method="get" action="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
			<input type="hidden" name="page" value="ai-alt-text-queue" />
			<input type="hidden" name="view" value="search" />
			<select id="ai-alt-browse-date" name="browse_date" aria-label="<?php esc_attr_e( 'Filter by date', 'dynamic-alt-tags' ); ?>">
				<option value=""><?php esc_html_e( 'All dates', 'dynamic-alt-tags' ); ?></option>
				<?php foreach ( $browse_month_options as $month_option ) : ?>
					<?php $month_value = isset( $month_option['value'] ) ? sanitize_text_field( (string) $month_option['value'] ) : ''; ?>
					<?php $month_label = isset( $month_option['label'] ) ? sanitize_text_field( (string) $month_option['label'] ) : ''; ?>
					<?php if ( '' !== $month_value && '' !== $month_label ) : ?>
						<option value="<?php echo esc_attr( $month_value ); ?>" <?php selected( $browse_date, $month_value ); ?>><?php echo esc_html( $month_label ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<select id="ai-alt-browse-alt-filter" name="browse_alt_filter" aria-label="<?php esc_attr_e( 'Filter by alt text', 'dynamic-alt-tags' ); ?>">
				<option value="all" <?php selected( $browse_alt_filter, 'all' ); ?>><?php esc_html_e( 'All Images', 'dynamic-alt-tags' ); ?></option>
				<option value="no_alt" <?php selected( $browse_alt_filter, 'no_alt' ); ?>><?php esc_html_e( 'No Alt Text Images', 'dynamic-alt-tags' ); ?></option>
			</select>
			<?php if ( $browse_has_category_filter ) : ?>
				<select id="ai-alt-browse-category" name="browse_category" aria-label="<?php esc_attr_e( 'Filter by media category', 'dynamic-alt-tags' ); ?>">
					<option value="0"><?php esc_html_e( 'All Categories', 'dynamic-alt-tags' ); ?></option>
					<?php foreach ( $browse_category_options as $category_option ) : ?>
						<?php $category_value = isset( $category_option['value'] ) ? absint( $category_option['value'] ) : 0; ?>
						<?php $category_label = isset( $category_option['label'] ) ? sanitize_text_field( (string) $category_option['label'] ) : ''; ?>
						<?php if ( $category_value > 0 && '' !== $category_label ) : ?>
							<option value="<?php echo esc_attr( (string) $category_value ); ?>" <?php selected( $browse_category, $category_value ); ?>><?php echo esc_html( $category_label ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<?php if ( $browse_has_filebird_filter ) : ?>
				<select id="ai-alt-browse-filebird-folder" name="browse_filebird_folder" aria-label="<?php esc_attr_e( 'Filter by FileBird folder', 'dynamic-alt-tags' ); ?>">
					<option value="0"><?php esc_html_e( 'All FileBird Folders', 'dynamic-alt-tags' ); ?></option>
					<?php foreach ( $browse_filebird_options as $filebird_option ) : ?>
						<?php $filebird_value = isset( $filebird_option['value'] ) ? absint( $filebird_option['value'] ) : 0; ?>
						<?php $filebird_label = isset( $filebird_option['label'] ) ? sanitize_text_field( (string) $filebird_option['label'] ) : ''; ?>
						<?php if ( $filebird_value > 0 && '' !== $filebird_label ) : ?>
							<option value="<?php echo esc_attr( (string) $filebird_value ); ?>" <?php selected( $browse_filebird_folder, $filebird_value ); ?>><?php echo esc_html( $filebird_label ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<label class="screen-reader-text" for="ai-alt-browse-search"><?php esc_html_e( 'Search media', 'dynamic-alt-tags' ); ?></label>
			<div class="ai-alt-browse-search-wrap">
				<input type="search" id="ai-alt-browse-search" name="browse_search" value="<?php echo esc_attr( $browse_search ); ?>" placeholder="<?php echo esc_attr__( 'Search images', 'dynamic-alt-tags' ); ?>" />
				<button type="button" class="ai-alt-browse-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'dynamic-alt-tags' ); ?>" <?php echo '' === $browse_search ? 'hidden' : ''; ?>>X</button>
			</div>
		</form>
		<div class="ai-alt-browse-bulk-bar" id="ai-alt-browse-bulk-bar">
			<button type="button" class="button" id="ai-alt-browse-bulk-toggle"><?php esc_html_e( 'Bulk Select', 'dynamic-alt-tags' ); ?></button>
			<div class="ai-alt-browse-bulk-actions" id="ai-alt-browse-bulk-actions" hidden>
				<button type="button" class="button button-primary" id="ai-alt-browse-add-selected" disabled><?php esc_html_e( 'Add to Queue', 'dynamic-alt-tags' ); ?></button>
				<button type="button" class="button" id="ai-alt-browse-bulk-cancel"><?php esc_html_e( 'Cancel', 'dynamic-alt-tags' ); ?></button>
			</div>
		</div>
		<p class="description" id="ai-alt-browse-summary" role="status" aria-live="polite" aria-atomic="true">
			<?php
			echo esc_html(
				sprintf(
					__( 'Showing %1$d of %2$d media items', 'dynamic-alt-tags' ),
					absint( count( $rows ) ),
					absint( $total )
				)
			);
			?>
		</p>
		<div id="ai-alt-browse-results" class="ai-alt-browse-grid ai-alt-browse-grid-media"><?php echo $this->render_browse_cards_html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php if ( $has_more ) : ?>
			<div class="tablenav ai-alt-load-more-wrap" id="ai-alt-browse-load-more-wrap">
				<button type="button" class="button button-primary ai-alt-browse-load-more" data-next-page="<?php echo esc_attr( (string) ( $page_num + 1 ) ); ?>" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"><?php esc_html_e( 'Load More Images', 'dynamic-alt-tags' ); ?></button>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<?php if ( ! $is_history && ! $is_no_alt ) : ?>
			<?php if ( $is_active && '' !== $focused_queue_ids_csv ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Showing newly added queue items first. Load more to see older queue items.', 'dynamic-alt-tags' ); ?></p>
				</div>
			<?php endif; ?>
			<div class="ai-alt-progress-wrap" id="ai-alt-queue-progress-wrap" hidden>
				<div class="ai-alt-progress-bar" id="ai-alt-queue-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
			</div>
			<p class="description" id="ai-alt-queue-progress-message" aria-live="polite"></p>
		<?php endif; ?>

		<?php if ( ! $is_no_alt ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ai-alt-queue-form">
				<input type="hidden" name="action" value="ai_alt_queue_action" />
				<input type="hidden" name="return_view" value="<?php echo esc_attr( $is_history ? 'history' : 'active' ); ?>" />
				<?php wp_nonce_field( 'ai_alt_queue_action', 'ai_alt_queue_nonce' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label class="screen-reader-text" for="bulk-action-selector-top"><?php esc_html_e( 'Select bulk action', 'dynamic-alt-tags' ); ?></label>
						<select name="bulk_action" id="bulk-action-selector-top">
							<option value="-1"><?php esc_html_e( 'Bulk actions', 'dynamic-alt-tags' ); ?></option>
							<?php if ( $is_history ) : ?>
								<option value="requeue"><?php esc_html_e( 'Re-queue', 'dynamic-alt-tags' ); ?></option>
							<?php else : ?>
								<option value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></option>
								<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
								<option value="process"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></option>
							<?php endif; ?>
						</select>
						<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'dynamic-alt-tags' ); ?></button>
					</div>
					<br class="clear" />
				</div>
		<?php endif; ?>

		<table class="widefat striped ai-alt-table" data-view="<?php echo esc_attr( $view ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>">
			<thead>
				<tr>
					<?php if ( ! $is_no_alt ) : ?>
						<td class="manage-column check-column">
							<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'dynamic-alt-tags' ); ?></label>
							<input id="cb-select-all-1" type="checkbox" class="ai-alt-select-all" />
						</td>
					<?php endif; ?>
					<th><?php esc_html_e( 'Image', 'dynamic-alt-tags' ); ?></th>
					<?php if ( $is_no_alt ) : ?>
						<th><?php esc_html_e( 'Alt Text', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Queue Status', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
					<?php elseif ( $is_history ) : ?>
						<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Alt Text', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Processed On', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
					<?php else : ?>
						<th><?php esc_html_e( 'Status', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Confidence', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Suggested Alt Text', 'dynamic-alt-tags' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'dynamic-alt-tags' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody id="ai-alt-queue-tbody">
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_no_alt ? '4' : '6'; ?>"><?php esc_html_e( 'No queue items found.', 'dynamic-alt-tags' ); ?></td>
					</tr>
				<?php else : ?>
					<?php
					if ( $is_no_alt ) {
						echo $this->render_no_alt_rows_html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo $this->render_queue_rows_html( $rows, $is_history ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! $is_no_alt ) : ?>
			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<label class="screen-reader-text" for="bulk-action-selector-bottom"><?php esc_html_e( 'Select bulk action', 'dynamic-alt-tags' ); ?></label>
					<select name="bulk_action2" id="bulk-action-selector-bottom">
						<option value="-1"><?php esc_html_e( 'Bulk actions', 'dynamic-alt-tags' ); ?></option>
						<?php if ( $is_history ) : ?>
							<option value="requeue"><?php esc_html_e( 'Re-queue', 'dynamic-alt-tags' ); ?></option>
						<?php else : ?>
							<option value="approve"><?php esc_html_e( 'Approve', 'dynamic-alt-tags' ); ?></option>
							<option value="skip"><?php esc_html_e( 'Skip Image', 'dynamic-alt-tags' ); ?></option>
							<option value="process"><?php esc_html_e( 'Generate Alt Text', 'dynamic-alt-tags' ); ?></option>
						<?php endif; ?>
					</select>
					<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'dynamic-alt-tags' ); ?></button>
				</div>
				<br class="clear" />
			</div>
			</form>
		<?php endif; ?>

		<?php if ( $has_more ) : ?>
			<div class="tablenav ai-alt-load-more-wrap">
				<button type="button" class="button button-primary ai-alt-load-more" data-view="<?php echo esc_attr( $view ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-next-page="<?php echo esc_attr( (string) $queue_load_more_next_page ); ?>" data-exclude-ids="<?php echo esc_attr( $focused_queue_ids_csv ); ?>" data-per-page="<?php echo esc_attr( (string) $per_page ); ?>"><?php esc_html_e( 'View more images', 'dynamic-alt-tags' ); ?></button>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>
