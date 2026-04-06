<?php
/**
 * Queue repository.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Queue_Repo {

	/**
	 * Maximum number of automatic retries for failed rows.
	 *
	 * @var int
	 */
	const MAX_AUTO_RETRY_ATTEMPTS = 3;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'ai_alt_queue';
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function table() {
		return $this->table;
	}

	/**
	 * Enqueue one attachment if needed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id Parent post ID.
	 * @return bool
	 */
	public function enqueue( $attachment_id, $post_id = 0 ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post_id       = absint( $post_id );

		if ( ! $attachment_id ) {
			return false;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			)
		);

		if ( $existing_id ) {
			return false;
		}

		$now = current_time( 'mysql' );

		$result = $wpdb->insert(
			$this->table,
			array(
				'attachment_id' => $attachment_id,
				'post_id'       => $post_id,
				'status'        => 'queued',
				'provider'      => 'cloudflare',
				'created_at'    => $now,
				'updated_at'    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Enqueue attachment or reset existing queue row to queued.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id Parent post ID.
	 * @return bool
	 */
	public function enqueue_or_requeue( $attachment_id, $post_id = 0 ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$post_id       = absint( $post_id );
		if ( ! $attachment_id ) {
			return false;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			)
		);

		$now = current_time( 'mysql' );
		if ( $existing_id ) {
			$result = $wpdb->update(
				$this->table,
				array(
					'post_id'       => $post_id,
					'status'        => 'queued',
					'raw_caption'   => null,
					'suggested_alt' => '',
					'final_alt'     => '',
					'confidence'    => 0,
					'attempts'      => 0,
					'error_code'    => null,
					'error_message' => null,
					'locked_at'     => null,
					'updated_at'    => $now,
				),
				array( 'id' => absint( $existing_id ) ),
				array( '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		return $this->enqueue( $attachment_id, $post_id );
	}

	/**
	 * Backfill queue.
	 *
	 * @param int $limit Max to enqueue.
	 * @return int
	 */
	public function enqueue_missing_alts( $limit = 200 ) {
		global $wpdb;

		$limit = max( 1, min( 1000, absint( $limit ) ) );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
					"SELECT p.ID
					 FROM {$wpdb->posts} p
					 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
					 WHERE p.post_type = 'attachment'
					 AND p.post_mime_type LIKE 'image/%'
					 AND p.post_mime_type <> 'image/svg+xml'
					 AND (pm.meta_value IS NULL OR pm.meta_value = '')
					 ORDER BY p.ID DESC
					 LIMIT %d",
				$limit
			)
		);

		$count = 0;
		foreach ( $ids as $id ) {
			if ( $this->enqueue( absint( $id ), 0 ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Claim jobs atomically enough for WP cron.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function claim_jobs( $limit ) {
		global $wpdb;

		$limit       = max( 1, min( 50, absint( $limit ) ) );
		$now         = current_time( 'mysql' );
		$lock_expiry = gmdate( 'Y-m-d H:i:s', time() - ( 15 * MINUTE_IN_SECONDS ) );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
				 FROM {$this->table}
				 WHERE status IN ('queued')
				 AND (locked_at IS NULL OR locked_at < %s)
				 ORDER BY updated_at ASC
				 LIMIT %d",
				$lock_expiry,
				$limit
			)
		);

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		if ( count( $ids ) < $limit ) {
			$ids = array_merge( $ids, $this->get_retryable_failed_job_ids( $limit - count( $ids ), $lock_expiry ) );
		}

		if ( empty( $ids ) ) {
			return array();
		}

		$ids          = array_values( array_unique( array_map( 'absint', $ids ) ) );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$update_sql   = $wpdb->prepare(
			"UPDATE {$this->table} SET status = 'processing', locked_at = %s, updated_at = %s WHERE id IN ({$placeholders})",
			array_merge( array( $now, $now ), $ids )
		);
		$wpdb->query( $update_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id IN ({$placeholders})",
				$ids
			),
			ARRAY_A
		);
	}

	/**
	 * Release claimed jobs back to queued state.
	 *
	 * @param array<int,int> $ids Queue row IDs.
	 * @return void
	 */
	public function release_jobs( $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( current_time( 'mysql' ) ), $ids );
		$sql          = $wpdb->prepare(
			"UPDATE {$this->table}
			 SET status = 'queued', locked_at = NULL, updated_at = %s
			 WHERE id IN ({$placeholders})",
			$params
		);

		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update generated row.
	 *
	 * @param int    $id Row ID.
	 * @param string $raw_caption Raw caption.
	 * @param string $suggested_alt Suggested alt.
	 * @param float  $confidence Confidence.
	 * @return void
	 */
	public function mark_generated( $id, $raw_caption, $suggested_alt, $confidence ) {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'        => 'generated',
				'raw_caption'   => (string) $raw_caption,
				'suggested_alt' => sanitize_text_field( $suggested_alt ),
				'confidence'    => max( 0.0, min( 1.0, (float) $confidence ) ),
				'attempts'      => 0,
				'error_code'    => null,
				'error_message' => null,
				'updated_at'    => current_time( 'mysql' ),
				'locked_at'     => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark failure.
	 *
	 * @param int    $id Row ID.
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return void
	 */
	public function mark_failed( $id, $code, $message ) {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = 'failed',
				 attempts = attempts + 1,
				 error_code = %s,
				 error_message = %s,
				 updated_at = %s,
				 locked_at = NULL
				 WHERE id = %d",
				sanitize_key( (string) $code ),
				sanitize_text_field( (string) $message ),
				current_time( 'mysql' ),
				absint( $id )
			)
		);
	}

	/**
	 * Get failed row IDs that are eligible for automatic retry.
	 *
	 * @param int    $limit Number of rows to return.
	 * @param string $lock_expiry Lock expiry datetime.
	 * @return array<int,int>
	 */
	private function get_retryable_failed_job_ids( $limit, $lock_expiry ) {
		global $wpdb;

		$limit = max( 0, absint( $limit ) );
		if ( 0 === $limit ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attempts, error_code, updated_at
				 FROM {$this->table}
				 WHERE status = 'failed'
				 AND attempts < %d
				 AND (locked_at IS NULL OR locked_at < %s)
				 ORDER BY updated_at ASC
				 LIMIT %d",
				self::MAX_AUTO_RETRY_ATTEMPTS,
				$lock_expiry,
				max( $limit * 3, $limit )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$eligible_ids = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id         = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$attempts   = isset( $row['attempts'] ) ? absint( $row['attempts'] ) : 0;
			$error_code = isset( $row['error_code'] ) ? sanitize_key( (string) $row['error_code'] ) : '';
			$updated_at = isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '';

			if ( ! $id || $attempts < 1 ) {
				continue;
			}

			if ( WPAI_Alt_Text_Provider_Cloudflare::is_provider_wide_error_code( $error_code ) ) {
				continue;
			}

			if ( ! $this->has_retry_backoff_elapsed( $attempts, $updated_at ) ) {
				continue;
			}

			$eligible_ids[] = $id;
			if ( count( $eligible_ids ) >= $limit ) {
				break;
			}
		}

		return $eligible_ids;
	}

	/**
	 * Whether the retry backoff window has elapsed for a failed row.
	 *
	 * @param int    $attempts Failed-attempt count.
	 * @param string $updated_at Datetime of latest failure.
	 * @return bool
	 */
	private function has_retry_backoff_elapsed( $attempts, $updated_at ) {
		$attempts   = max( 1, absint( $attempts ) );
		$updated_at = trim( (string) $updated_at );
		if ( '' === $updated_at ) {
			return true;
		}

		$failed_at = strtotime( $updated_at );
		if ( false === $failed_at ) {
			return true;
		}

		return time() >= ( $failed_at + $this->get_retry_backoff_seconds( $attempts ) );
	}

	/**
	 * Get the retry backoff window for a failed row.
	 *
	 * @param int $attempts Failed-attempt count.
	 * @return int
	 */
	private function get_retry_backoff_seconds( $attempts ) {
		$attempts = max( 1, absint( $attempts ) );

		if ( 1 === $attempts ) {
			return 5 * MINUTE_IN_SECONDS;
		}

		if ( 2 === $attempts ) {
			return 15 * MINUTE_IN_SECONDS;
		}

		return HOUR_IN_SECONDS;
	}

	/**
	 * Mark final status.
	 *
	 * @param int    $id Row ID.
	 * @param string $status Status.
	 * @param string $final_alt Final alt value.
	 * @return bool
	 */
	public function mark_final( $id, $status, $final_alt = '' ) {
		global $wpdb;

		$allowed = array( 'approved', 'rejected', 'skipped' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'final_alt'  => sanitize_text_field( $final_alt ),
				'updated_at' => current_time( 'mysql' ),
				'locked_at'  => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get row.
	 *
	 * @param int $id Row ID.
	 * @return array<string,mixed>|null
	 */
	public function get_row( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", absint( $id ) ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get row by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,mixed>|null
	 */
	public function get_row_by_attachment( $attachment_id ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE attachment_id = %d AND provider = %s LIMIT 1",
				$attachment_id,
				'cloudflare'
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete queue rows by attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int Number of deleted rows.
	 */
	public function delete_by_attachment_id( $attachment_id ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return 0;
		}

		return (int) $wpdb->delete(
			$this->table,
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete all history rows (approved/rejected/skipped).
	 *
	 * @return int Number of deleted rows.
	 */
	public function delete_history_rows() {
		global $wpdb;

		$result = $wpdb->query(
			"DELETE FROM {$this->table}
			 WHERE status IN ('approved', 'rejected', 'skipped')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Paginate queue rows.
	 *
	 * @param int          $page Current page.
	 * @param int          $per_page Per page.
	 * @param string       $status Status filter.
	 * @param string       $view View mode: active|history.
	 * @param array<int,int> $exclude_attachment_ids Attachment IDs to exclude.
	 * @return array<string,mixed>
	 */
	public function get_paginated( $page = 1, $per_page = 20, $status = '', $view = 'active', $exclude_attachment_ids = array() ) {
		global $wpdb;

		$page              = max( 1, absint( $page ) );
		$per_page          = max( 1, min( 100, absint( $per_page ) ) );
		$offset            = ( $page - 1 ) * $per_page;
		$params            = array();
		$view              = sanitize_key( $view );
		$active_statuses   = array( 'queued', 'processing', 'generated', 'failed' );
		$history_statuses  = array( 'approved', 'rejected', 'skipped' );
		$allowed_statuses  = 'history' === $view ? $history_statuses : $active_statuses;
		$status            = sanitize_key( $status );
		$exclude_attachment_ids = array_values( array_filter( array_map( 'absint', (array) $exclude_attachment_ids ) ) );

		$in_sql = "'" . implode( "', '", array_map( 'esc_sql', $allowed_statuses ) ) . "'";
		$where  = "q.status IN ({$in_sql})";

		if ( '' !== $status && in_array( $status, $allowed_statuses, true ) ) {
			$where            .= ' AND q.status = %s';
			$params[]          = $status;
		}
		if ( ! empty( $exclude_attachment_ids ) ) {
			$exclude_sql = implode( ', ', array_fill( 0, count( $exclude_attachment_ids ), '%d' ) );
			$where      .= " AND q.attachment_id NOT IN ({$exclude_sql})";
			$params      = array_merge( $params, $exclude_attachment_ids );
		}

		$total_sql = "SELECT COUNT(*)
			FROM {$this->table} q
			LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			WHERE {$where}
			AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')";
		$rows_sql  = "SELECT q.*
			FROM {$this->table} q
			LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			WHERE {$where}
			AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')
			ORDER BY q.updated_at DESC
			LIMIT %d OFFSET %d";

			if ( ! empty( $params ) ) {
				$total = (int) $wpdb->get_var(
					$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$total_sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$params
					)
				);
		} else {
			$total = (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

			if ( ! empty( $params ) ) {
				$rows = $wpdb->get_results(
						$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$rows_sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							array_merge( $params, array( $per_page, $offset ) )
						),
						ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
						$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$rows_sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$per_page,
							$offset
				),
				ARRAY_A
			);
		}

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'rows'     => is_array( $rows ) ? $rows : array(),
		);
	}

	/**
	 * Get active queue rows for a set of attachment IDs, newest first.
	 *
	 * @param array<int,int> $attachment_ids Attachment IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_rows_by_attachment_ids( $attachment_ids ) {
		global $wpdb;

		$attachment_ids = array_values( array_filter( array_map( 'absint', (array) $attachment_ids ) ) );
		if ( empty( $attachment_ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $attachment_ids ), '%d' ) );
		$sql          = "SELECT q.*
			FROM {$this->table} q
			LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			WHERE q.status IN ('queued', 'processing', 'generated', 'failed')
			AND q.attachment_id IN ({$placeholders})
			AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')
			ORDER BY q.updated_at DESC, q.id DESC";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $attachment_ids ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get latest failed queue row.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_failed_row() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT q.id, q.attachment_id, q.error_code, q.error_message, q.updated_at
			 FROM {$this->table} q
			 LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			 WHERE q.status = 'failed'
			 AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')
			 ORDER BY q.updated_at DESC, q.id DESC
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get latest row from active queue statuses.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_active_row() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT q.id, q.attachment_id, q.status, q.updated_at
			 FROM {$this->table} q
			 LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			 WHERE q.status IN ('queued', 'processing', 'generated', 'failed')
			 AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')
			 ORDER BY q.updated_at DESC, q.id DESC
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get latest non-SVG row from active queue statuses.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_latest_active_non_svg_row() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT q.id, q.attachment_id, q.status, q.updated_at
			 FROM {$this->table} q
			 INNER JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			 WHERE q.status IN ('queued', 'processing', 'generated', 'failed')
			 AND p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND p.post_mime_type <> 'image/svg+xml'
			 ORDER BY q.updated_at DESC, q.id DESC
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get counts for active queue statuses.
	 *
	 * @return array<string,int>
	 */
	public function get_active_status_counts() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT q.status, COUNT(*) AS c
			 FROM {$this->table} q
			 LEFT JOIN {$wpdb->posts} p ON p.ID = q.attachment_id
			 WHERE q.status IN ('queued', 'processing', 'generated', 'failed')
			 AND (p.ID IS NULL OR p.post_mime_type <> 'image/svg+xml')
			 GROUP BY q.status", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$counts = array(
			'queued'     => 0,
			'processing' => 0,
			'generated'  => 0,
			'failed'     => 0,
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = isset( $row['c'] ) ? absint( $row['c'] ) : 0;
				}
			}
		}

		return $counts;
	}

	/**
	 * Count image attachments that currently have no alt text.
	 *
	 * @return int
	 */
	public function get_total_no_alt_images() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND p.post_mime_type <> 'image/svg+xml'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get image alt coverage counts.
	 *
	 * @return array<string,int>
	 */
	public function get_image_alt_coverage_counts() {
		global $wpdb;

		$row = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total_images,
				SUM(CASE WHEN pm.meta_value IS NULL OR pm.meta_value = '' THEN 1 ELSE 0 END) AS without_alt
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND p.post_mime_type <> 'image/svg+xml'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$total_images = is_array( $row ) && isset( $row['total_images'] ) ? absint( $row['total_images'] ) : 0;
		$without_alt  = is_array( $row ) && isset( $row['without_alt'] ) ? absint( $row['without_alt'] ) : 0;
		$without_alt  = min( $without_alt, $total_images );
		$with_alt     = max( 0, $total_images - $without_alt );

		return array(
			'total_images' => $total_images,
			'with_alt'     => $with_alt,
			'without_alt'  => $without_alt,
		);
	}

	/**
	 * Paginate image attachments with empty alt text.
	 *
	 * @param int $page Current page.
	 * @param int $per_page Per page.
	 * @return array<string,mixed>
	 */
	public function get_no_alt_paginated( $page = 1, $per_page = 20 ) {
		global $wpdb;

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 100, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
			 WHERE p.post_type = 'attachment'
			 AND p.post_mime_type LIKE 'image/%'
			 AND p.post_mime_type <> 'image/svg+xml'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS attachment_id, q.id AS queue_row_id, q.status AS queue_status
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt')
				 LEFT JOIN {$this->table} q ON (q.attachment_id = p.ID AND q.provider = %s)
				 WHERE p.post_type = 'attachment'
				 AND p.post_mime_type LIKE 'image/%%'
				 AND p.post_mime_type <> 'image/svg+xml'
				 AND (pm.meta_value IS NULL OR pm.meta_value = '')
				 ORDER BY p.ID DESC
				 LIMIT %d OFFSET %d",
				'cloudflare',
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array(
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'rows'     => is_array( $rows ) ? $rows : array(),
		);
	}
}
