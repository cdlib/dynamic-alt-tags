<?php
/**
 * Settings manager.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'ai_alt_text_options';

	/**
	 * Metrics option key.
	 *
	 * @var string
	 */
	const METRICS_OPTION_KEY = 'ai_alt_text_metrics';

	/**
	 * Get options with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_options() {
		$defaults = array(
			'provider'            => 'cloudflare',
			'cloudflare_account'  => '',
			'cloudflare_token'    => '',
			'worker_url'          => '',
			'use_url_mode'        => 0,
			'direct_upload_image_size' => 'large',
			'chart_bar_style'     => 'blue',
			'show_dashboard_processed_chart' => 0,
			'show_dashboard_processing_metrics' => 0,
			'enable_background_processing' => 0,
			'background_process_interval'  => 5,
			'background_batch_size'        => 5,
			'batch_size'          => 5,
			'min_confidence'      => 0.70,
			'auto_apply_new_uploads' => 0,
			'sync_title_from_alt' => 0,
			'sync_caption_from_alt' => 0,
			'sync_description_from_alt' => 0,
			'search_media_taxonomy' => '',
			'allowed_roles'       => array( 'administrator' ),
			'overwrite_existing'  => 0,
			'require_review'      => 1,
			'keep_data_on_delete' => 0,
		);

		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$options = wp_parse_args( $raw, $defaults );

		// Backward compatibility: older versions used direct_upload_mode.
		if ( ! array_key_exists( 'use_url_mode', $raw ) && array_key_exists( 'direct_upload_mode', $raw ) ) {
			$options['use_url_mode'] = ! empty( $raw['direct_upload_mode'] ) ? 0 : 1;
		}

		if ( ! array_key_exists( 'background_batch_size', $raw ) && array_key_exists( 'batch_size', $raw ) ) {
			$options['background_batch_size'] = max( 1, min( 20, absint( $raw['batch_size'] ) ) );
		}
		if ( ! array_key_exists( 'show_dashboard_processed_chart', $raw ) && array_key_exists( 'show_dashboard_processing_panels', $raw ) ) {
			$options['show_dashboard_processed_chart'] = ! empty( $raw['show_dashboard_processing_panels'] ) ? 1 : 0;
		}
		if ( ! array_key_exists( 'show_dashboard_processing_metrics', $raw ) && array_key_exists( 'show_dashboard_processing_panels', $raw ) ) {
			$options['show_dashboard_processing_metrics'] = ! empty( $raw['show_dashboard_processing_panels'] ) ? 1 : 0;
		}

		$options['enable_background_processing'] = ! empty( $options['enable_background_processing'] ) ? 1 : 0;
		$options['background_process_interval']  = max( 5, min( 60, absint( $options['background_process_interval'] ) ) );
		if ( 0 !== $options['background_process_interval'] % 5 ) {
			$options['background_process_interval'] = 5;
		}
		$options['background_batch_size'] = max( 1, min( 20, absint( $options['background_batch_size'] ) ) );
		$size_options                   = $this->get_direct_upload_image_size_options();
		$palette_options                = $this->get_chart_bar_style_options();
		$options['direct_upload_image_size'] = isset( $size_options[ $options['direct_upload_image_size'] ] ) ? $options['direct_upload_image_size'] : 'large';
		$options['chart_bar_style']     = isset( $palette_options[ $options['chart_bar_style'] ] ) ? $options['chart_bar_style'] : 'blue';
		$options['show_dashboard_processed_chart'] = ! empty( $options['show_dashboard_processed_chart'] ) ? 1 : 0;
		$options['show_dashboard_processing_metrics'] = ! empty( $options['show_dashboard_processing_metrics'] ) ? 1 : 0;

		return $options;
	}

	/**
	 * Get processing metrics with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_metrics() {
		$current_day_key = $this->get_los_angeles_day_key();
		$current_week_key = $this->get_los_angeles_week_key();
		$current_month_key = $this->get_los_angeles_month_key();
		$current_year_key = $this->get_los_angeles_year_key();
		$defaults = array(
			'total_images_processed'    => 0,
			'processed_attachment_ids'  => array(),
			'success_count'             => 0,
			'failure_count'             => 0,
			'provider_call_count'       => 0,
			'total_processing_time_ms'  => 0.0,
			'total_provider_latency_ms' => 0.0,
			'last_processing_time_ms'   => 0.0,
			'last_provider_latency_ms'  => 0.0,
			'last_processed_at'         => '',
			'daily_metrics_date'        => $current_day_key,
			'daily_images_processed'    => 0,
			'daily_provider_call_count' => 0,
			'daily_processed_attachment_ids' => array(),
			'daily_history_attachment_ids' => array(),
			'weekly_metrics_date'       => $current_week_key,
			'weekly_images_processed'   => 0,
			'weekly_processed_attachment_ids' => array(),
			'weekly_history_attachment_ids' => array(),
			'monthly_metrics_date'      => $current_month_key,
			'monthly_images_processed'  => 0,
			'monthly_processed_attachment_ids' => array(),
			'monthly_history_attachment_ids' => array(),
			'yearly_metrics_date'       => $current_year_key,
			'yearly_images_processed'   => 0,
			'yearly_processed_attachment_ids' => array(),
			'yearly_history_attachment_ids' => array(),
		);

		$raw = get_option( self::METRICS_OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$metrics = wp_parse_args( $raw, $defaults );

		$processed_attachment_ids = isset( $metrics['processed_attachment_ids'] ) && is_array( $metrics['processed_attachment_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $metrics['processed_attachment_ids'] ) ) ) )
			: array();
		$metrics['processed_attachment_ids']  = $processed_attachment_ids;
		$metrics['total_images_processed']    = ! empty( $processed_attachment_ids )
			? count( $processed_attachment_ids )
			: max( 0, absint( $metrics['total_images_processed'] ) );
		$metrics['success_count']             = max( 0, absint( $metrics['success_count'] ) );
		$metrics['failure_count']             = max( 0, absint( $metrics['failure_count'] ) );
		$metrics['provider_call_count']       = max( 0, absint( $metrics['provider_call_count'] ) );
		$metrics['total_processing_time_ms']  = max( 0.0, (float) $metrics['total_processing_time_ms'] );
		$metrics['total_provider_latency_ms'] = max( 0.0, (float) $metrics['total_provider_latency_ms'] );
		$metrics['last_processing_time_ms']   = max( 0.0, (float) $metrics['last_processing_time_ms'] );
		$metrics['last_provider_latency_ms']  = max( 0.0, (float) $metrics['last_provider_latency_ms'] );
		$metrics['last_processed_at']         = is_string( $metrics['last_processed_at'] ) ? sanitize_text_field( $metrics['last_processed_at'] ) : '';

		$daily_processed_attachment_ids = isset( $metrics['daily_processed_attachment_ids'] ) && is_array( $metrics['daily_processed_attachment_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $metrics['daily_processed_attachment_ids'] ) ) ) )
			: array();
		$metrics['daily_metrics_date']        = is_string( $metrics['daily_metrics_date'] ) ? sanitize_text_field( $metrics['daily_metrics_date'] ) : $current_day_key;
		$metrics['daily_images_processed']    = max( 0, absint( $metrics['daily_images_processed'] ) );
		$metrics['daily_provider_call_count'] = max( 0, absint( $metrics['daily_provider_call_count'] ) );
		$metrics['daily_processed_attachment_ids'] = $daily_processed_attachment_ids;

		if ( $metrics['daily_metrics_date'] !== $current_day_key ) {
			$metrics['daily_metrics_date']             = $current_day_key;
			$metrics['daily_images_processed']         = 0;
			$metrics['daily_provider_call_count']      = 0;
			$metrics['daily_processed_attachment_ids'] = array();
		} elseif ( ! empty( $daily_processed_attachment_ids ) ) {
			$metrics['daily_images_processed'] = count( $daily_processed_attachment_ids );
		}
		$metrics['daily_history_attachment_ids'] = $this->normalize_history_attachment_buckets(
			isset( $metrics['daily_history_attachment_ids'] ) ? $metrics['daily_history_attachment_ids'] : array(),
			$this->get_recent_day_keys( 14 )
		);

		$weekly_processed_attachment_ids = isset( $metrics['weekly_processed_attachment_ids'] ) && is_array( $metrics['weekly_processed_attachment_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $metrics['weekly_processed_attachment_ids'] ) ) ) )
			: array();
		$metrics['weekly_metrics_date']        = is_string( $metrics['weekly_metrics_date'] ) ? sanitize_text_field( $metrics['weekly_metrics_date'] ) : $current_week_key;
		$metrics['weekly_images_processed']    = max( 0, absint( $metrics['weekly_images_processed'] ) );
		$metrics['weekly_processed_attachment_ids'] = $weekly_processed_attachment_ids;

		if ( $metrics['weekly_metrics_date'] !== $current_week_key ) {
			$metrics['weekly_metrics_date']             = $current_week_key;
			$metrics['weekly_images_processed']         = 0;
			$metrics['weekly_processed_attachment_ids'] = array();
		} elseif ( ! empty( $weekly_processed_attachment_ids ) ) {
			$metrics['weekly_images_processed'] = count( $weekly_processed_attachment_ids );
		}
		$metrics['weekly_history_attachment_ids'] = $this->normalize_history_attachment_buckets(
			isset( $metrics['weekly_history_attachment_ids'] ) ? $metrics['weekly_history_attachment_ids'] : array(),
			$this->get_recent_week_keys( 12 )
		);

		$monthly_processed_attachment_ids = isset( $metrics['monthly_processed_attachment_ids'] ) && is_array( $metrics['monthly_processed_attachment_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $metrics['monthly_processed_attachment_ids'] ) ) ) )
			: array();
		$metrics['monthly_metrics_date']        = is_string( $metrics['monthly_metrics_date'] ) ? sanitize_text_field( $metrics['monthly_metrics_date'] ) : $current_month_key;
		$metrics['monthly_images_processed']    = max( 0, absint( $metrics['monthly_images_processed'] ) );
		$metrics['monthly_processed_attachment_ids'] = $monthly_processed_attachment_ids;

		if ( $metrics['monthly_metrics_date'] !== $current_month_key ) {
			$metrics['monthly_metrics_date']             = $current_month_key;
			$metrics['monthly_images_processed']         = 0;
			$metrics['monthly_processed_attachment_ids'] = array();
		} elseif ( ! empty( $monthly_processed_attachment_ids ) ) {
			$metrics['monthly_images_processed'] = count( $monthly_processed_attachment_ids );
		}
		$metrics['monthly_history_attachment_ids'] = $this->normalize_history_attachment_buckets(
			isset( $metrics['monthly_history_attachment_ids'] ) ? $metrics['monthly_history_attachment_ids'] : array(),
			$this->get_recent_month_keys( 12 )
		);

		$yearly_processed_attachment_ids = isset( $metrics['yearly_processed_attachment_ids'] ) && is_array( $metrics['yearly_processed_attachment_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $metrics['yearly_processed_attachment_ids'] ) ) ) )
			: array();
		$metrics['yearly_metrics_date']        = is_string( $metrics['yearly_metrics_date'] ) ? sanitize_text_field( $metrics['yearly_metrics_date'] ) : $current_year_key;
		$metrics['yearly_images_processed']    = max( 0, absint( $metrics['yearly_images_processed'] ) );
		$metrics['yearly_processed_attachment_ids'] = $yearly_processed_attachment_ids;

		if ( $metrics['yearly_metrics_date'] !== $current_year_key ) {
			$metrics['yearly_metrics_date']             = $current_year_key;
			$metrics['yearly_images_processed']         = 0;
			$metrics['yearly_processed_attachment_ids'] = array();
		} elseif ( ! empty( $yearly_processed_attachment_ids ) ) {
			$metrics['yearly_images_processed'] = count( $yearly_processed_attachment_ids );
		}
		$metrics['yearly_history_attachment_ids'] = $this->normalize_history_attachment_buckets(
			isset( $metrics['yearly_history_attachment_ids'] ) ? $metrics['yearly_history_attachment_ids'] : array(),
			$this->get_recent_year_keys( 5 )
		);

		return $metrics;
	}

	/**
	 * Persist processing metrics.
	 *
	 * @param array<string,mixed> $event Metric event values.
	 * @return void
	 */
	public function record_processing_metrics( $event ) {
		$metrics = $this->get_metrics();

		$attachment_id        = isset( $event['attachment_id'] ) ? absint( $event['attachment_id'] ) : 0;
		$is_success          = ! empty( $event['success'] );
		$provider_call_count = ! empty( $event['provider_called'] ) ? 1 : 0;
		$processing_time_ms  = isset( $event['processing_time_ms'] ) ? max( 0.0, (float) $event['processing_time_ms'] ) : 0.0;
		$provider_latency_ms = isset( $event['provider_latency_ms'] ) ? max( 0.0, (float) $event['provider_latency_ms'] ) : 0.0;

		if ( $attachment_id > 0 && ! in_array( $attachment_id, $metrics['processed_attachment_ids'], true ) ) {
			$metrics['processed_attachment_ids'][] = $attachment_id;
		}
		$metrics['total_images_processed'] = count( $metrics['processed_attachment_ids'] );

		if ( $is_success && $attachment_id > 0 && ! in_array( $attachment_id, $metrics['daily_processed_attachment_ids'], true ) ) {
			$metrics['daily_processed_attachment_ids'][] = $attachment_id;
		}
		$metrics['daily_images_processed'] = count( $metrics['daily_processed_attachment_ids'] );
		if ( $is_success && $attachment_id > 0 ) {
			$metrics['daily_history_attachment_ids'] = $this->add_attachment_to_history_bucket(
				isset( $metrics['daily_history_attachment_ids'] ) ? $metrics['daily_history_attachment_ids'] : array(),
				$this->get_los_angeles_day_key(),
				$attachment_id
			);
		}
		if ( $is_success && $attachment_id > 0 && ! in_array( $attachment_id, $metrics['weekly_processed_attachment_ids'], true ) ) {
			$metrics['weekly_processed_attachment_ids'][] = $attachment_id;
		}
		$metrics['weekly_images_processed'] = count( $metrics['weekly_processed_attachment_ids'] );
		if ( $is_success && $attachment_id > 0 ) {
			$metrics['weekly_history_attachment_ids'] = $this->add_attachment_to_history_bucket(
				isset( $metrics['weekly_history_attachment_ids'] ) ? $metrics['weekly_history_attachment_ids'] : array(),
				$this->get_los_angeles_week_key(),
				$attachment_id
			);
		}
		if ( $is_success && $attachment_id > 0 && ! in_array( $attachment_id, $metrics['monthly_processed_attachment_ids'], true ) ) {
			$metrics['monthly_processed_attachment_ids'][] = $attachment_id;
		}
		$metrics['monthly_images_processed'] = count( $metrics['monthly_processed_attachment_ids'] );
		if ( $is_success && $attachment_id > 0 ) {
			$metrics['monthly_history_attachment_ids'] = $this->add_attachment_to_history_bucket(
				isset( $metrics['monthly_history_attachment_ids'] ) ? $metrics['monthly_history_attachment_ids'] : array(),
				$this->get_los_angeles_month_key(),
				$attachment_id
			);
		}
		if ( $is_success && $attachment_id > 0 && ! in_array( $attachment_id, $metrics['yearly_processed_attachment_ids'], true ) ) {
			$metrics['yearly_processed_attachment_ids'][] = $attachment_id;
		}
		$metrics['yearly_images_processed'] = count( $metrics['yearly_processed_attachment_ids'] );
		if ( $is_success && $attachment_id > 0 ) {
			$metrics['yearly_history_attachment_ids'] = $this->add_attachment_to_history_bucket(
				isset( $metrics['yearly_history_attachment_ids'] ) ? $metrics['yearly_history_attachment_ids'] : array(),
				$this->get_los_angeles_year_key(),
				$attachment_id
			);
		}
		if ( $is_success ) {
			$metrics['success_count'] += 1;
		} else {
			$metrics['failure_count'] += 1;
		}
		$metrics['provider_call_count']       += $provider_call_count;
		$metrics['daily_provider_call_count'] += $provider_call_count;
		$metrics['total_processing_time_ms']  += $processing_time_ms;
		$metrics['total_provider_latency_ms'] += $provider_latency_ms;
		$metrics['last_processing_time_ms']    = $processing_time_ms;
		$metrics['last_provider_latency_ms']   = $provider_latency_ms;
		$metrics['last_processed_at']          = current_time( 'mysql' );

		update_option( self::METRICS_OPTION_KEY, $metrics, false );
	}

	/**
	 * Reset processing metrics to defaults.
	 *
	 * @return void
	 */
	public function reset_metrics() {
		delete_option( self::METRICS_OPTION_KEY );
	}

	/**
	 * Get processed-history chart data grouped by time period.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function get_processed_history_chart_data() {
		$metrics = $this->get_metrics();

		return array(
			'day'   => $this->build_chart_points(
				$this->get_recent_day_keys( 14 ),
				isset( $metrics['daily_history_attachment_ids'] ) && is_array( $metrics['daily_history_attachment_ids'] ) ? $metrics['daily_history_attachment_ids'] : array(),
				static function ( $key ) {
					$date = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $key, new DateTimeZone( 'America/Los_Angeles' ) );
					if ( false === $date ) {
						return array(
							'label'      => (string) $key,
							'full_label' => (string) $key,
						);
					}

					return array(
						'label'      => $date->format( 'M j' ),
						'full_label' => $date->format( 'F j, Y' ),
					);
				}
			),
			'week'  => $this->build_chart_points(
				$this->get_recent_week_keys( 12 ),
				isset( $metrics['weekly_history_attachment_ids'] ) && is_array( $metrics['weekly_history_attachment_ids'] ) ? $metrics['weekly_history_attachment_ids'] : array(),
				static function ( $key ) {
					$date = DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $key, new DateTimeZone( 'America/Los_Angeles' ) );
					if ( false === $date ) {
						return array(
							'label'      => (string) $key,
							'full_label' => (string) $key,
						);
					}

					$week_end = $date->modify( '+6 days' );

					return array(
						'label'      => $date->format( 'M j' ),
						'full_label' => sprintf(
							/* translators: 1: week start date, 2: week end date */
							__( 'Week of %1$s to %2$s', 'dynamic-alt-tags' ),
							$date->format( 'F j, Y' ),
							$week_end->format( 'F j, Y' )
						),
					);
				}
			),
			'month' => $this->build_chart_points(
				$this->get_recent_month_keys( 12 ),
				isset( $metrics['monthly_history_attachment_ids'] ) && is_array( $metrics['monthly_history_attachment_ids'] ) ? $metrics['monthly_history_attachment_ids'] : array(),
				static function ( $key ) {
					$date = DateTimeImmutable::createFromFormat( 'Y-m', (string) $key, new DateTimeZone( 'America/Los_Angeles' ) );
					if ( false === $date ) {
						return array(
							'label'      => (string) $key,
							'full_label' => (string) $key,
						);
					}

					return array(
						'label'      => $date->format( 'M Y' ),
						'full_label' => $date->format( 'F Y' ),
					);
				}
			),
			'year'  => $this->build_chart_points(
				$this->get_recent_year_keys( 5 ),
				isset( $metrics['yearly_history_attachment_ids'] ) && is_array( $metrics['yearly_history_attachment_ids'] ) ? $metrics['yearly_history_attachment_ids'] : array(),
				static function ( $key ) {
					return array(
						'label'      => (string) $key,
						'full_label' => (string) $key,
					);
				}
			),
		);
	}

	/**
	 * Get the current Los Angeles date key for daily metrics.
	 *
	 * @return string
	 */
	private function get_los_angeles_day_key() {
		$tz = new DateTimeZone( 'America/Los_Angeles' );
		$now = new DateTimeImmutable( 'now', $tz );

		return $now->format( 'Y-m-d' );
	}

	/**
	 * Get the current Los Angeles week key for weekly metrics.
	 *
	 * @return string
	 */
	private function get_los_angeles_week_key() {
		$tz  = new DateTimeZone( 'America/Los_Angeles' );
		$now = new DateTimeImmutable( 'now', $tz );
		$week_start = $now->modify( '-' . (int) $now->format( 'w' ) . ' days' );

		return $week_start->format( 'Y-m-d' );
	}

	/**
	 * Get the current Los Angeles month key for monthly metrics.
	 *
	 * @return string
	 */
	private function get_los_angeles_month_key() {
		$tz  = new DateTimeZone( 'America/Los_Angeles' );
		$now = new DateTimeImmutable( 'now', $tz );

		return $now->format( 'Y-m' );
	}

	/**
	 * Get the current Los Angeles year key for yearly metrics.
	 *
	 * @return string
	 */
	private function get_los_angeles_year_key() {
		$tz  = new DateTimeZone( 'America/Los_Angeles' );
		$now = new DateTimeImmutable( 'now', $tz );

		return $now->format( 'Y' );
	}

	/**
	 * Normalize historical attachment ID buckets and prune to allowed keys.
	 *
	 * @param mixed    $history History bucket map.
	 * @param string[] $allowed_keys Allowed bucket keys.
	 * @return array<string,array<int,int>>
	 */
	private function normalize_history_attachment_buckets( $history, $allowed_keys ) {
		if ( ! is_array( $history ) ) {
			return array();
		}

		$allowed_lookup = array_fill_keys( $allowed_keys, true );
		$normalized     = array();

		foreach ( $history as $bucket_key => $attachment_ids ) {
			$bucket_key = sanitize_text_field( (string) $bucket_key );
			if ( '' === $bucket_key || ! isset( $allowed_lookup[ $bucket_key ] ) || ! is_array( $attachment_ids ) ) {
				continue;
			}

			$normalized[ $bucket_key ] = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );
		}

		return $normalized;
	}

	/**
	 * Add an attachment ID to a historical metrics bucket.
	 *
	 * @param mixed  $history History bucket map.
	 * @param string $bucket_key Bucket key.
	 * @param int    $attachment_id Attachment ID.
	 * @return array<string,array<int,int>>
	 */
	private function add_attachment_to_history_bucket( $history, $bucket_key, $attachment_id ) {
		$bucket_key    = sanitize_text_field( (string) $bucket_key );
		$attachment_id = absint( $attachment_id );
		$history       = is_array( $history ) ? $history : array();

		if ( '' === $bucket_key || $attachment_id <= 0 ) {
			return $history;
		}

		$current_ids = isset( $history[ $bucket_key ] ) && is_array( $history[ $bucket_key ] ) ? $history[ $bucket_key ] : array();
		$current_ids = array_values( array_unique( array_filter( array_map( 'absint', $current_ids ) ) ) );

		if ( ! in_array( $attachment_id, $current_ids, true ) ) {
			$current_ids[] = $attachment_id;
		}

		$history[ $bucket_key ] = $current_ids;

		return $history;
	}

	/**
	 * Build chart points from bucket keys and historical IDs.
	 *
	 * @param string[]            $keys Ordered bucket keys.
	 * @param array<string,mixed> $history History bucket map.
	 * @param callable            $label_builder Label builder callback.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_chart_points( $keys, $history, $label_builder ) {
		$points = array();

		foreach ( $keys as $key ) {
			$key = (string) $key;
			$ids = isset( $history[ $key ] ) && is_array( $history[ $key ] ) ? array_values( array_unique( array_filter( array_map( 'absint', $history[ $key ] ) ) ) ) : array();
			$labels = call_user_func( $label_builder, $key );
			$label = is_array( $labels ) && isset( $labels['label'] ) ? (string) $labels['label'] : $key;
			$full_label = is_array( $labels ) && isset( $labels['full_label'] ) ? (string) $labels['full_label'] : $label;

			$points[] = array(
				'key'        => $key,
				'label'      => $label,
				'full_label' => $full_label,
				'value'      => count( $ids ),
			);
		}

		return $points;
	}

	/**
	 * Get recent Los Angeles day keys, oldest first.
	 *
	 * @param int $count Number of buckets.
	 * @return string[]
	 */
	private function get_recent_day_keys( $count ) {
		$count = max( 1, absint( $count ) );
		$tz    = new DateTimeZone( 'America/Los_Angeles' );
		$now   = new DateTimeImmutable( 'now', $tz );
		$keys  = array();

		for ( $offset = $count - 1; $offset >= 0; --$offset ) {
			$keys[] = $now->modify( '-' . $offset . ' days' )->format( 'Y-m-d' );
		}

		return $keys;
	}

	/**
	 * Get recent Los Angeles week keys, oldest first.
	 *
	 * @param int $count Number of buckets.
	 * @return string[]
	 */
	private function get_recent_week_keys( $count ) {
		$count      = max( 1, absint( $count ) );
		$tz         = new DateTimeZone( 'America/Los_Angeles' );
		$now        = new DateTimeImmutable( 'now', $tz );
		$current    = $now->modify( '-' . (int) $now->format( 'w' ) . ' days' );
		$keys       = array();

		for ( $offset = $count - 1; $offset >= 0; --$offset ) {
			$keys[] = $current->modify( '-' . $offset . ' weeks' )->format( 'Y-m-d' );
		}

		return $keys;
	}

	/**
	 * Get recent Los Angeles month keys, oldest first.
	 *
	 * @param int $count Number of buckets.
	 * @return string[]
	 */
	private function get_recent_month_keys( $count ) {
		$count = max( 1, absint( $count ) );
		$tz    = new DateTimeZone( 'America/Los_Angeles' );
		$now   = new DateTimeImmutable( 'first day of this month', $tz );
		$keys  = array();

		for ( $offset = $count - 1; $offset >= 0; --$offset ) {
			$keys[] = $now->modify( '-' . $offset . ' months' )->format( 'Y-m' );
		}

		return $keys;
	}

	/**
	 * Get recent Los Angeles year keys, oldest first.
	 *
	 * @param int $count Number of buckets.
	 * @return string[]
	 */
	private function get_recent_year_keys( $count ) {
		$count = max( 1, absint( $count ) );
		$tz    = new DateTimeZone( 'America/Los_Angeles' );
		$now   = new DateTimeImmutable( 'now', $tz );
		$current_year = (int) $now->format( 'Y' );
		$start_year   = $current_year - $count + 1;
		$keys  = array();

		for ( $year = $start_year; $year <= $current_year; ++$year ) {
			$keys[] = (string) $year;
		}

		return $keys;
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! function_exists( 'register_setting' ) || ! function_exists( 'add_settings_section' ) || ! function_exists( 'add_settings_field' ) ) {
			return;
		}

		register_setting(
			'ai_alt_text_options_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);

		add_settings_section(
			'ai_alt_text_provider_section',
			__( 'Provider Settings', 'dynamic-alt-tags' ),
			'__return_false',
			'ai-alt-text-settings'
		);

		add_settings_section(
			'ai_alt_text_access_section',
			__( 'Access', 'dynamic-alt-tags' ),
			'__return_false',
			'ai-alt-text-settings'
		);

		$fields = array(
			array(
				'id'    => 'worker_url',
				'label' => __( 'Cloudflare Worker URL', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'cloudflare_token',
				'label' => __( 'Cloudflare API Token', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'direct_upload_image_size',
				'label' => __( 'Direct Upload Image Size', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'min_confidence',
				'label' => __( 'Min Confidence (0-1)', 'dynamic-alt-tags' ),
				'class' => 'ai-alt-settings-divider-after',
			),
			array(
				'id'    => 'enable_background_processing',
				'label' => __( 'Enable Background Processing', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'background_process_interval',
				'label' => __( 'Background Processing Frequency', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'background_batch_size',
				'label' => __( 'Images Processed Per Background Run', 'dynamic-alt-tags' ),
				'class' => 'ai-alt-settings-divider-after',
			),
			array(
				'id'    => 'use_url_mode',
				'label' => __( 'Use URL Mode - Send Image URL', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'auto_apply_new_uploads',
				'label' => __( 'Auto-Approve New Uploads', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'sync_title_from_alt',
				'label' => __( 'Sync Alt Text to Attachment Title', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'sync_caption_from_alt',
				'label' => __( 'Sync Alt Text to Attachment Caption', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'sync_description_from_alt',
				'label' => __( 'Sync Alt Text to Attachment Description', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'search_media_taxonomy',
				'label' => __( 'Search Media Taxonomy', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'chart_bar_style',
				'label' => __( 'Chart Bar Color Style', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'show_dashboard_processed_chart',
				'label' => __( 'Show Dashboard Processed Images Chart', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'show_dashboard_processing_metrics',
				'label' => __( 'Show Dashboard Processing Metrics', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'overwrite_existing',
				'label' => __( 'Overwrite Existing Alt Text', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'require_review',
				'label' => __( 'Require Manual Review for Queue Items', 'dynamic-alt-tags' ),
			),
			array(
				'id'    => 'keep_data_on_delete',
				'label' => __( 'Keep Data On Delete', 'dynamic-alt-tags' ),
			),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				$field['id'],
				$field['label'],
				array( $this, 'render_field' ),
				'ai-alt-text-settings',
				'ai_alt_text_provider_section',
				$field
			);
		}

		add_settings_field(
			'allowed_roles',
			__( 'Roles Allowed To Access Dynamic Alt Tags', 'dynamic-alt-tags' ),
			array( $this, 'render_field' ),
			'ai-alt-text-settings',
			'ai_alt_text_access_section',
			array( 'id' => 'allowed_roles' )
		);

	}

	/**
	 * Sanitize options.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_options( $input ) {
		$current = $this->get_options();
		$input   = is_array( $input ) ? $input : array();

		$current['provider'] = 'cloudflare';

		if ( isset( $input['worker_url'] ) ) {
			$worker_url_raw = trim( (string) $input['worker_url'] );

			if ( '' === $worker_url_raw ) {
				$current['worker_url'] = '';
			} else {
				$worker_url = esc_url_raw( $worker_url_raw );

				// If user omitted scheme, try https:// before treating it as invalid.
				if ( '' === $worker_url && false === strpos( $worker_url_raw, '://' ) ) {
					$worker_url = esc_url_raw( 'https://' . $worker_url_raw );
				}

				// Keep last valid value instead of clearing the field on invalid input.
				if ( '' !== $worker_url ) {
					$current['worker_url'] = $worker_url;
				}
			}
		}

		$current['cloudflare_account'] = isset( $input['cloudflare_account'] ) ? sanitize_text_field( (string) $input['cloudflare_account'] ) : '';

		if ( isset( $input['cloudflare_token'] ) ) {
			$token = trim( (string) $input['cloudflare_token'] );
			$current['cloudflare_token'] = '' === $token ? '' : sanitize_text_field( $token );
		}
		$direct_upload_size_options           = $this->get_direct_upload_image_size_options();
		$current['direct_upload_image_size']  = isset( $input['direct_upload_image_size'] ) ? sanitize_key( (string) $input['direct_upload_image_size'] ) : 'large';
		if ( ! isset( $direct_upload_size_options[ $current['direct_upload_image_size'] ] ) ) {
			$current['direct_upload_image_size'] = 'large';
		}
		$chart_bar_style_options       = $this->get_chart_bar_style_options();
		$current['chart_bar_style']    = isset( $input['chart_bar_style'] ) ? sanitize_key( (string) $input['chart_bar_style'] ) : 'blue';
		if ( ! isset( $chart_bar_style_options[ $current['chart_bar_style'] ] ) ) {
			$current['chart_bar_style'] = 'blue';
		}
		$current['show_dashboard_processed_chart'] = ! empty( $input['show_dashboard_processed_chart'] ) ? 1 : 0;
		$current['show_dashboard_processing_metrics'] = ! empty( $input['show_dashboard_processing_metrics'] ) ? 1 : 0;

		$current['enable_background_processing'] = ! empty( $input['enable_background_processing'] ) ? 1 : 0;
		$current['background_process_interval']  = isset( $input['background_process_interval'] ) ? max( 5, min( 60, absint( $input['background_process_interval'] ) ) ) : 5;
		if ( 0 !== $current['background_process_interval'] % 5 ) {
			$current['background_process_interval'] = 5;
		}
		$current['background_batch_size'] = isset( $input['background_batch_size'] ) ? max( 1, min( 20, absint( $input['background_batch_size'] ) ) ) : 5;
		$current['batch_size'] = $current['background_batch_size'];

		$current['min_confidence'] = isset( $input['min_confidence'] ) ? (float) $input['min_confidence'] : 0.70;
		$current['min_confidence'] = max( 0.00, min( 1.00, $current['min_confidence'] ) );

		$current['use_url_mode']        = ! empty( $input['use_url_mode'] ) ? 1 : 0;
		$current['auto_apply_new_uploads'] = ! empty( $input['auto_apply_new_uploads'] ) ? 1 : 0;
		$current['sync_title_from_alt'] = ! empty( $input['sync_title_from_alt'] ) ? 1 : 0;
		$current['sync_caption_from_alt'] = ! empty( $input['sync_caption_from_alt'] ) ? 1 : 0;
		$current['sync_description_from_alt'] = ! empty( $input['sync_description_from_alt'] ) ? 1 : 0;
		$current['search_media_taxonomy'] = '';
		if ( isset( $input['search_media_taxonomy'] ) ) {
			$taxonomy = sanitize_key( (string) $input['search_media_taxonomy'] );
			if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( 'attachment', $taxonomy ) ) {
				$current['search_media_taxonomy'] = $taxonomy;
			}
		}
		$current['overwrite_existing']  = ! empty( $input['overwrite_existing'] ) ? 1 : 0;
		$current['require_review']      = ! empty( $input['require_review'] ) ? 1 : 0;
		$current['keep_data_on_delete'] = ! empty( $input['keep_data_on_delete'] ) ? 1 : 0;
		$current['allowed_roles']       = array( 'administrator' );
		if ( isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ) {
			$roles = array_filter(
				array_map(
					'sanitize_key',
					array_map( 'strval', wp_unslash( $input['allowed_roles'] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				)
			);
			if ( ! empty( $roles ) ) {
				$current['allowed_roles'] = array_values( array_unique( $roles ) );
			}
		}
		if ( ! in_array( 'administrator', $current['allowed_roles'], true ) ) {
			array_unshift( $current['allowed_roles'], 'administrator' );
			$current['allowed_roles'] = array_values( array_unique( $current['allowed_roles'] ) );
		}

		return $current;
	}

	/**
	 * Render settings field.
	 *
	 * @param array<string,string> $args Field args.
	 * @return void
	 */
	public function render_field( $args ) {
		$options = $this->get_options();
		$id      = isset( $args['id'] ) ? $args['id'] : '';

		if ( '' === $id ) {
			return;
		}

		$name = self::OPTION_KEY . '[' . $id . ']';

		if ( 'allowed_roles' === $id ) {
			$selected_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] ) ? array_map( 'strval', $options['allowed_roles'] ) : array( 'administrator' );
			$wp_roles       = wp_roles();
			$roles          = $wp_roles instanceof WP_Roles ? $wp_roles->roles : array();
			$sortable_roles = array();
			foreach ( $roles as $role_key => $role_data ) {
				$role_label = isset( $role_data['name'] ) ? (string) $role_data['name'] : (string) $role_key;
				$sortable_roles[] = array(
					'key'   => (string) $role_key,
					'label' => $role_label,
				);
			}
			usort(
				$sortable_roles,
				static function ( $a, $b ) {
					return strcasecmp( (string) $a['label'], (string) $b['label'] );
				}
			);
			foreach ( $sortable_roles as $role_item ) {
				$is_administrator_role = 'administrator' === (string) $role_item['key'];
				printf(
					'<label style="display:block; margin-bottom:6px;"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s %5$s /> %4$s</label>',
					esc_attr( $name ),
					esc_attr( (string) $role_item['key'] ),
					checked( true, $is_administrator_role ? true : in_array( (string) $role_item['key'], $selected_roles, true ), false ),
					esc_html( (string) $role_item['label'] ),
					$is_administrator_role ? 'class="ai-alt-admin-role-lock"' : ''
				);
			}
			echo '<p class="description">' . esc_html__( 'Administrator always has full access. Selected roles can access only the Dynamic Alt Tags Queue page under Media.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( 'search_media_taxonomy' === $id ) {
			$selected_taxonomy = isset( $options['search_media_taxonomy'] ) ? sanitize_key( (string) $options['search_media_taxonomy'] ) : '';
			$taxonomies        = $this->get_attachment_taxonomy_options();
			$field_id          = 'ai-alt-field-' . sanitize_html_class( $id );

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $field_id ),
				esc_attr( $name )
			);
			printf(
				'<option value="">%s</option>',
				esc_html__( 'Auto-detect (Media Categories if available)', 'dynamic-alt-tags' )
			);

			foreach ( $taxonomies as $taxonomy ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( (string) $taxonomy['value'] ),
					selected( $selected_taxonomy, (string) $taxonomy['value'], false ),
					esc_html( (string) $taxonomy['label'] )
				);
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Choose which attachment taxonomy should appear as the category filter on the Search tab. Leave on auto-detect to use Media Categories when that taxonomy exists.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( in_array( $id, array( 'enable_background_processing', 'use_url_mode', 'auto_apply_new_uploads', 'sync_title_from_alt', 'sync_caption_from_alt', 'sync_description_from_alt', 'show_dashboard_processed_chart', 'show_dashboard_processing_metrics', 'overwrite_existing', 'require_review', 'keep_data_on_delete' ), true ) ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1" %2$s /></label>',
				esc_attr( $name ),
				checked( 1, (int) $options[ $id ], false )
			);

			if ( 'enable_background_processing' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, queued items can be processed automatically in the background using WP-Cron. It is recommended to keep this feature turned off if you are running into usage limits.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'show_dashboard_processed_chart' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, the main Dashboard page shows the Images Processed Over Time panel. This is off by default.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'show_dashboard_processing_metrics' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, the main Dashboard page shows the detailed processing metrics table below the chart area. This is off by default.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'use_url_mode' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, the plugin sends image URLs and the Worker fetches images remotely. Leave unchecked to use Direct Upload Mode (default, recommended).', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'auto_apply_new_uploads' === $id ) {
				echo '<p class="description">' . esc_html__( 'Reserved for future upload workflow behavior. Newly uploaded images currently stay in the queue after generation until they are approved.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'sync_title_from_alt' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, applying alt text will also set the attachment title to the same value.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'sync_caption_from_alt' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, applying alt text will also set the WordPress attachment caption to the same value.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'sync_description_from_alt' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, applying alt text will also set the WordPress attachment description to the same value.', 'dynamic-alt-tags' ) . '</p>';
			} elseif ( 'require_review' === $id ) {
				echo '<p class="description">' . esc_html__( 'When enabled, queue, backfill, and newly uploaded items stay in Generated status until someone approves them.', 'dynamic-alt-tags' ) . '</p>';
			}
			return;
		}

		$type = 'text';
		$step = '';

		if ( 'background_process_interval' === $id ) {
			$field_id = 'ai-alt-field-' . sanitize_html_class( $id );

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $field_id ),
				esc_attr( $name )
			);

			foreach ( range( 5, 60, 5 ) as $minutes ) {
				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					(int) $minutes,
					selected( absint( $options[ $id ] ), (int) $minutes, false ),
					esc_html(
						sprintf(
							/* translators: %d minute interval */
							_n( 'Every %d minute', 'Every %d minutes', (int) $minutes, 'dynamic-alt-tags' ),
							(int) $minutes
						)
					)
				);
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Choose how often queued items may be processed automatically in the background.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( 'direct_upload_image_size' === $id ) {
			$field_id = 'ai-alt-field-' . sanitize_html_class( $id );
			$options_map = $this->get_direct_upload_image_size_options();

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $field_id ),
				esc_attr( $name )
			);

			foreach ( $options_map as $value => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( (string) $value ),
					selected( (string) $options[ $id ], (string) $value, false ),
					esc_html( (string) $label )
				);
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Choose which generated image size is sent in Direct Upload Mode. Large is the current default. Medium can be useful for testing smaller payloads.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( 'chart_bar_style' === $id ) {
			$field_id = 'ai-alt-field-' . sanitize_html_class( $id );
			$options_map = $this->get_chart_bar_style_options();

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $field_id ),
				esc_attr( $name )
			);

			foreach ( $options_map as $value => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( (string) $value ),
					selected( (string) $options[ $id ], (string) $value, false ),
					esc_html( (string) $label )
				);
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Choose the color style used for the processed images chart bars and chart view tabs.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( 'background_batch_size' === $id ) {
			$field_id = 'ai-alt-field-' . sanitize_html_class( $id );

			printf(
				'<select id="%1$s" name="%2$s">',
				esc_attr( $field_id ),
				esc_attr( $name )
			);

			foreach ( range( 1, 20 ) as $count ) {
				printf(
					'<option value="%1$d" %2$s>%3$d</option>',
					(int) $count,
					selected( absint( $options[ $id ] ), (int) $count, false ),
					(int) $count
				);
			}

			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Choose how many images can be processed during each background run.', 'dynamic-alt-tags' ) . '</p>';
			return;
		}

		if ( 'min_confidence' === $id ) {
			$type = 'number';
			$step = ' step="0.01" min="0" max="1"';
		}
		if ( 'cloudflare_token' === $id ) {
			$type = 'password';
		}

		$field_id = 'ai-alt-field-' . sanitize_html_class( $id );

		if ( 'cloudflare_token' === $id ) {
			printf(
				'<input id="%1$s" class="regular-text" type="%2$s" name="%3$s" value="%4$s" autocomplete="off" />',
				esc_attr( $field_id ),
				esc_attr( $type ),
				esc_attr( $name ),
				esc_attr( (string) $options[ $id ] )
			);
			printf(
				' <button type="button" class="button ai-alt-toggle-token" data-target="%1$s" data-show-label="%2$s" data-hide-label="%3$s" aria-pressed="false">%2$s</button>',
				esc_attr( $field_id ),
				esc_attr__( 'Show', 'dynamic-alt-tags' ),
				esc_attr__( 'Hide', 'dynamic-alt-tags' )
			);
			return;
		}

		printf(
			'<input id="%1$s" class="regular-text" type="%2$s" name="%3$s" value="%4$s"%5$s />',
			esc_attr( $field_id ),
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( (string) $options[ $id ] ),
			$step // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

	}

	/**
	 * Get attachment taxonomy options for the settings dropdown.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_attachment_taxonomy_options() {
		$taxonomy_objects = get_object_taxonomies( 'attachment', 'objects' );
		if ( ! is_array( $taxonomy_objects ) ) {
			return array();
		}

		$options = array();
		foreach ( $taxonomy_objects as $taxonomy_object ) {
			if ( ! ( $taxonomy_object instanceof WP_Taxonomy ) ) {
				continue;
			}

			$label = isset( $taxonomy_object->labels->singular_name ) && '' !== (string) $taxonomy_object->labels->singular_name
				? (string) $taxonomy_object->labels->singular_name
				: (string) $taxonomy_object->label;

			$options[] = array(
				'value' => (string) $taxonomy_object->name,
				'label' => sprintf(
					/* translators: 1: taxonomy label, 2: taxonomy slug */
					__( '%1$s (%2$s)', 'dynamic-alt-tags' ),
					$label,
					(string) $taxonomy_object->name
				),
			);
		}

		usort(
			$options,
			static function ( $a, $b ) {
				return strcasecmp( (string) $a['label'], (string) $b['label'] );
			}
		);

		return $options;
	}

	/**
	 * Get available direct-upload image size settings.
	 *
	 * @return array<string,string>
	 */
	public function get_direct_upload_image_size_options() {
		return array(
			'large'  => __( 'Large (default)', 'dynamic-alt-tags' ),
			'medium' => __( 'Medium', 'dynamic-alt-tags' ),
		);
	}

	/**
	 * Get available chart bar style settings.
	 *
	 * @return array<string,string>
	 */
	public function get_chart_bar_style_options() {
		return array(
			'blue'    => __( '🟦 Blue', 'dynamic-alt-tags' ),
			'teal'    => __( '🟩 Teal', 'dynamic-alt-tags' ),
			'orange'  => __( '🟧 Orange', 'dynamic-alt-tags' ),
			'emerald' => __( '🟩 Emerald', 'dynamic-alt-tags' ),
			'plum'    => __( '🟪 Plum', 'dynamic-alt-tags' ),
			'terracotta' => __( '🟫 Terracotta', 'dynamic-alt-tags' ),
		);
	}

	/**
	 * Get CSS variables for the selected chart bar style.
	 *
	 * @return array<string,string>
	 */
	public function get_chart_bar_style_palette() {
		$options = $this->get_options();
		$style   = isset( $options['chart_bar_style'] ) ? sanitize_key( (string) $options['chart_bar_style'] ) : 'blue';
		$palettes = array(
			'blue' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(16, 53, 95, 0.09)',
				'--ai-alt-chart-toggle-hover-text'   => '#0f3154',
				'--ai-alt-chart-toggle-active-start' => '#2271b1',
				'--ai-alt-chart-toggle-active-end'   => '#13558f',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(19, 85, 143, 0.22)',
				'--ai-alt-chart-fill-start'          => 'rgba(77, 161, 226, 0.95)',
				'--ai-alt-chart-fill-mid'            => 'rgba(34, 113, 177, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(18, 77, 128, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(34, 113, 177, 0.18)',
			),
			'teal' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(30, 166, 145, 0.12)',
				'--ai-alt-chart-toggle-hover-text'   => '#145f58',
				'--ai-alt-chart-toggle-active-start' => '#2ab7a2',
				'--ai-alt-chart-toggle-active-end'   => '#178a7d',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(23, 138, 125, 0.22)',
				'--ai-alt-chart-fill-start'          => 'rgba(108, 224, 210, 0.96)',
				'--ai-alt-chart-fill-mid'            => 'rgba(30, 166, 145, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(18, 111, 104, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(30, 138, 122, 0.2)',
			),
			'orange' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(244, 136, 37, 0.13)',
				'--ai-alt-chart-toggle-hover-text'   => '#8f480d',
				'--ai-alt-chart-toggle-active-start' => '#f49f3a',
				'--ai-alt-chart-toggle-active-end'   => '#cb6712',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(203, 103, 18, 0.24)',
				'--ai-alt-chart-fill-start'          => 'rgba(255, 194, 107, 0.96)',
				'--ai-alt-chart-fill-mid'            => 'rgba(244, 136, 37, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(196, 91, 9, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(214, 116, 24, 0.2)',
			),
			'emerald' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(47, 158, 98, 0.12)',
				'--ai-alt-chart-toggle-hover-text'   => '#1b6a43',
				'--ai-alt-chart-toggle-active-start' => '#4cbf7a',
				'--ai-alt-chart-toggle-active-end'   => '#268f57',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(38, 143, 87, 0.22)',
				'--ai-alt-chart-fill-start'          => 'rgba(137, 230, 173, 0.96)',
				'--ai-alt-chart-fill-mid'            => 'rgba(60, 179, 113, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(32, 120, 73, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(38, 143, 87, 0.2)',
			),
			'plum' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(138, 92, 184, 0.12)',
				'--ai-alt-chart-toggle-hover-text'   => '#5e3f87',
				'--ai-alt-chart-toggle-active-start' => '#9b6bd6',
				'--ai-alt-chart-toggle-active-end'   => '#6f45ad',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(111, 69, 173, 0.22)',
				'--ai-alt-chart-fill-start'          => 'rgba(202, 164, 244, 0.96)',
				'--ai-alt-chart-fill-mid'            => 'rgba(143, 92, 196, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(90, 54, 138, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(111, 69, 173, 0.2)',
			),
			'terracotta' => array(
				'--ai-alt-chart-toggle-hover'        => 'rgba(176, 105, 78, 0.12)',
				'--ai-alt-chart-toggle-hover-text'   => '#7b4530',
				'--ai-alt-chart-toggle-active-start' => '#cb8667',
				'--ai-alt-chart-toggle-active-end'   => '#9f5b3f',
				'--ai-alt-chart-toggle-shadow'       => 'rgba(159, 91, 63, 0.22)',
				'--ai-alt-chart-fill-start'          => 'rgba(230, 177, 154, 0.96)',
				'--ai-alt-chart-fill-mid'            => 'rgba(198, 122, 91, 0.98)',
				'--ai-alt-chart-fill-end'            => 'rgba(132, 73, 49, 1)',
				'--ai-alt-chart-fill-shadow'         => 'rgba(159, 91, 63, 0.2)',
			),
		);

		return isset( $palettes[ $style ] ) ? $palettes[ $style ] : $palettes['blue'];
	}

	/**
	 * Build inline style string for chart palette CSS variables.
	 *
	 * @return string
	 */
	public function get_chart_bar_style_attribute() {
		$palette = $this->get_chart_bar_style_palette();
		$parts   = array();

		foreach ( $palette as $property => $value ) {
			$parts[] = sanitize_text_field( (string) $property ) . ': ' . sanitize_text_field( (string) $value );
		}

		return implode( '; ', $parts );
	}

	/**
	 * Check whether a user is allowed to access plugin settings/queue pages.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_has_access( $user_id = 0 ) {
		return $this->current_user_can_access_queue( $user_id );
	}

	/**
	 * Check whether a user can access plugin queue/media controls.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_can_access_queue( $user_id = 0 ) {
		$user = $user_id > 0 ? get_user_by( 'id', absint( $user_id ) ) : wp_get_current_user();
		if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
			return false;
		}

		if ( $this->current_user_is_administrator( $user_id ) ) {
			return true;
		}

		$options       = $this->get_options();
		$allowed_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] )
			? array_filter( array_map( 'sanitize_key', array_map( 'strval', $options['allowed_roles'] ) ) )
			: array();

		if ( empty( $allowed_roles ) ) {
			$allowed_roles = array( 'administrator' );
		}

		return ! empty( array_intersect( $allowed_roles, array_map( 'strval', $user->roles ) ) );
	}

	/**
	 * Check whether a user can access plugin settings page.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_can_access_settings( $user_id = 0 ) {
		return $this->current_user_is_administrator( $user_id );
	}

	/**
	 * Check whether user has administrator role.
	 *
	 * @param int $user_id Optional user ID. Defaults to current user.
	 * @return bool
	 */
	public function current_user_is_administrator( $user_id = 0 ) {
		$user = $user_id > 0 ? get_user_by( 'id', absint( $user_id ) ) : wp_get_current_user();
		if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
			return false;
		}

		return in_array( 'administrator', array_map( 'strval', $user->roles ), true );
	}
}
