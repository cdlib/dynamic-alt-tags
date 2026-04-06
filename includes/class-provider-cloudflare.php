<?php
/**
 * Cloudflare Worker provider.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Provider_Cloudflare implements WPAI_Alt_Text_Provider_Interface {
	/**
	 * Site transient key for provider cooldown state.
	 *
	 * @var string
	 */
	const COOLDOWN_TRANSIENT_KEY = 'wpai_alt_provider_cooldown';

	/**
	 * Error code for exhausted provider quota.
	 *
	 * @var string
	 */
	const ERROR_QUOTA_EXHAUSTED = 'ai_alt_provider_quota_exhausted';

	/**
	 * Error code for provider resource limits.
	 *
	 * @var string
	 */
	const ERROR_RESOURCE_LIMITED = 'ai_alt_provider_resource_limited';

	/**
	 * Error code for temporary provider cooldown.
	 *
	 * @var string
	 */
	const ERROR_TEMPORARILY_UNAVAILABLE = 'ai_alt_provider_temporarily_unavailable';

	/**
	 * Max bytes to inline in direct upload mode.
	 */
	const MAX_INLINE_IMAGE_BYTES = 10485760; // 10MB.

	/**
	 * Settings.
	 *
	 * @var WPAI_Alt_Text_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WPAI_Alt_Text_Settings $settings Settings object.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Generate caption.
	 *
	 * @param string $image_url Image URL.
	 * @param array  $context Context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_caption( $image_url, $context = array() ) {
		$cooldown_error = $this->get_cooldown_error();
		if ( is_wp_error( $cooldown_error ) ) {
			return $cooldown_error;
		}

		$options    = $this->settings->get_options();
		$worker_url = isset( $options['worker_url'] ) ? trim( (string) $options['worker_url'] ) : '';
		$token      = isset( $options['cloudflare_token'] ) ? trim( (string) $options['cloudflare_token'] ) : '';
		$attachment_id = isset( $context['attachment_id'] ) ? absint( $context['attachment_id'] ) : 0;
		$use_url_mode = ! empty( $options['use_url_mode'] );
		if ( ! array_key_exists( 'use_url_mode', $options ) && array_key_exists( 'direct_upload_mode', $options ) ) {
			$use_url_mode = empty( $options['direct_upload_mode'] );
		}
		$use_direct = ! $use_url_mode;

		if ( '' === $worker_url ) {
			return new WP_Error( 'ai_alt_missing_worker_url', __( 'Cloudflare Worker URL is not configured.', 'dynamic-alt-tags' ) );
		}
		if ( $this->is_svg_attachment( $attachment_id ) || $this->is_svg_url( $image_url ) ) {
			return new WP_Error( 'ai_alt_svg_not_supported', __( 'SVG images are not supported by the configured provider.', 'dynamic-alt-tags' ) );
		}

		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$payload      = array(
			'image_url' => esc_url_raw( $image_url ),
			'context'   => array(
				'attachment_title' => isset( $context['attachment_title'] ) ? sanitize_text_field( (string) $context['attachment_title'] ) : '',
				'post_title'       => isset( $context['post_title'] ) ? sanitize_text_field( (string) $context['post_title'] ) : '',
			),
			'rules'     => array(
				'concise'       => true,
				'no_guessing'   => true,
				'max_words'     => 18,
				'no_image_of'   => true,
				'alt_text_mode' => true,
			),
		);
		$request_mode = 'url';

		if ( $use_direct ) {
			$attachment_id = isset( $context['attachment_id'] ) ? absint( $context['attachment_id'] ) : 0;
			if ( $attachment_id > 0 ) {
				$direct_payload = $this->build_direct_image_payload( $attachment_id );
				if ( is_wp_error( $direct_payload ) ) {
					return new WP_Error(
						$direct_payload->get_error_code(),
						sprintf(
							/* translators: 1: error message, 2: request mode */
							__( '%1$s; request mode: %2$s', 'dynamic-alt-tags' ),
							$direct_payload->get_error_message(),
							'bytes'
						)
					);
				}

				$payload      = array_merge( $payload, $direct_payload );
				$request_mode = 'bytes';
			}
		}

		$response = wp_remote_post(
			$worker_url,
			array(
				'timeout' => 90,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->normalize_provider_error(
				new WP_Error(
				$response->get_error_code(),
				sprintf(
					/* translators: 1: error message, 2: request mode */
					__( '%1$s; request mode: %2$s', 'dynamic-alt-tags' ),
					$response->get_error_message(),
					$request_mode
				)
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$detail       = '';
			$fetch_url    = esc_url_raw( $image_url );
			$fetch_status = 0;

			if ( is_array( $data ) ) {
				if ( isset( $data['error'] ) ) {
					$detail = (string) $data['error'];
				} elseif ( isset( $data['message'] ) ) {
					$detail = (string) $data['message'];
				}

				$url_keys = array( 'fetch_url', 'image_url', 'url' );
				foreach ( $url_keys as $url_key ) {
					if ( isset( $data[ $url_key ] ) && is_string( $data[ $url_key ] ) && '' !== trim( $data[ $url_key ] ) ) {
						$fetch_url = esc_url_raw( (string) $data[ $url_key ] );
						break;
					}
				}

				$status_keys = array( 'fetch_status', 'upstream_status', 'status', 'status_code', 'http_status' );
				foreach ( $status_keys as $status_key ) {
					if ( isset( $data[ $status_key ] ) ) {
						$candidate = absint( $data[ $status_key ] );
						if ( $candidate > 0 ) {
							$fetch_status = $candidate;
							break;
						}
					}
				}
			}

			if ( '' === trim( $detail ) && is_string( $body ) && '' !== trim( $body ) ) {
				$detail = wp_strip_all_tags( $body );
			}

			$detail = trim( preg_replace( '/\s+/', ' ', (string) $detail ) );
			$parts  = array();
			if ( '' !== $detail ) {
				$parts[] = sanitize_text_field( substr( $detail, 0, 220 ) );
			}
			if ( $fetch_status > 0 ) {
				$parts[] = sprintf(
					/* translators: %d upstream status code */
					__( 'upstream status %d', 'dynamic-alt-tags' ),
					$fetch_status
				);
			}
			if ( '' !== $fetch_url ) {
				$parts[] = sprintf(
					/* translators: %s image fetch URL */
					__( 'image URL: %s', 'dynamic-alt-tags' ),
					$fetch_url
				);
			}
			$parts[] = sprintf(
				/* translators: %s request mode */
				__( 'request mode: %s', 'dynamic-alt-tags' ),
				$request_mode
			);

			if ( ! empty( $parts ) ) {
				return $this->normalize_provider_error(
					new WP_Error(
					'ai_alt_provider_http_error',
					sprintf(
						/* translators: 1: HTTP status code, 2: provider error detail */
						__( 'Provider returned HTTP %1$d: %2$s', 'dynamic-alt-tags' ),
						(int) $code,
						implode( '; ', $parts )
					)
					)
				);
			}

			return $this->normalize_provider_error(
				new WP_Error(
				'ai_alt_provider_http_error',
				sprintf(
					/* translators: %d HTTP status code */
					__( 'Provider returned HTTP %d', 'dynamic-alt-tags' ),
					(int) $code
				)
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return $this->normalize_provider_error(
				new WP_Error( 'ai_alt_provider_parse_error', __( 'Provider returned invalid JSON.', 'dynamic-alt-tags' ) )
			);
		}

		$caption = '';
		if ( isset( $data['alt_text'] ) ) {
			$caption = (string) $data['alt_text'];
		} elseif ( isset( $data['caption'] ) ) {
			$caption = (string) $data['caption'];
		}

		$confidence = isset( $data['confidence'] ) ? (float) $data['confidence'] : 0.0;

		return array(
			'caption'    => $caption,
			'confidence' => max( 0.0, min( 1.0, $confidence ) ),
			'raw'        => $data,
		);
	}

	/**
	 * Build direct-upload payload fields for one attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string,string>|WP_Error
	 */
	private function build_direct_image_payload( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return new WP_Error( 'ai_alt_invalid_attachment', __( 'Invalid attachment for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! is_string( $file_path ) || '' === trim( $file_path ) ) {
			return new WP_Error( 'ai_alt_missing_attachment_file', __( 'Attachment file path was not found for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'ai_alt_unreadable_attachment_file', __( 'Attachment file is not readable for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$file_size = filesize( $file_path );
		if ( false !== $file_size && $file_size > self::MAX_INLINE_IMAGE_BYTES ) {
			return new WP_Error(
				'ai_alt_attachment_too_large',
				sprintf(
					/* translators: %d max size in MB */
					__( 'Attachment is too large for direct upload mode (max %d MB).', 'dynamic-alt-tags' ),
					10
				)
			);
		}

		$binary = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $binary ) || '' === $binary ) {
			return new WP_Error( 'ai_alt_attachment_read_failed', __( 'Attachment file could not be read for direct upload mode.', 'dynamic-alt-tags' ) );
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( is_string( $mime_type ) && 'image/svg+xml' === strtolower( trim( $mime_type ) ) ) {
			return new WP_Error( 'ai_alt_svg_not_supported', __( 'SVG images are not supported by the configured provider.', 'dynamic-alt-tags' ) );
		}
		if ( ! is_string( $mime_type ) || 0 !== strpos( $mime_type, 'image/' ) ) {
			$mime_type = 'application/octet-stream';
		}

		return array(
			'image_source'      => 'bytes',
			'image_data_base64' => base64_encode( $binary ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'image_mime_type'   => sanitize_text_field( $mime_type ),
			'image_filename'    => sanitize_file_name( basename( $file_path ) ),
		);
	}

	/**
	 * Whether an attachment is SVG.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_svg_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! is_string( $mime_type ) ) {
			return false;
		}

		return 'image/svg+xml' === strtolower( trim( $mime_type ) );
	}

	/**
	 * Whether an image URL likely points to an SVG.
	 *
	 * @param string $image_url Image URL.
	 * @return bool
	 */
	private function is_svg_url( $image_url ) {
		$image_url = trim( (string) $image_url );
		if ( '' === $image_url ) {
			return false;
		}

		$path = (string) wp_parse_url( $image_url, PHP_URL_PATH );
		if ( '' === $path ) {
			return false;
		}

		return (bool) preg_match( '/\.svg$/i', $path );
	}

	/**
	 * Whether a provider error code indicates a provider-wide outage/limit.
	 *
	 * @param string $code Error code.
	 * @return bool
	 */
	public static function is_provider_wide_error_code( $code ) {
		$code = sanitize_key( (string) $code );

		return in_array(
			$code,
			array(
				self::ERROR_QUOTA_EXHAUSTED,
				self::ERROR_RESOURCE_LIMITED,
				self::ERROR_TEMPORARILY_UNAVAILABLE,
			),
			true
		);
	}

	/**
	 * Normalize provider errors and apply cooldown when appropriate.
	 *
	 * @param WP_Error $error Raw error.
	 * @return WP_Error
	 */
	private function normalize_provider_error( $error ) {
		$message = trim( (string) $error->get_error_message() );
		$lower   = strtolower( $message );

		if ( false !== strpos( $lower, 'used up your daily free allocation' ) || false !== strpos( $lower, 'daily free allocation of 10,000 neurons' ) ) {
			$this->set_provider_cooldown( self::ERROR_QUOTA_EXHAUSTED, $message, $this->seconds_until_next_utc_midnight() );

			return new WP_Error(
				self::ERROR_QUOTA_EXHAUSTED,
				sprintf(
					/* translators: %s original provider message */
					__( 'Cloudflare daily free allocation is exhausted. %s', 'dynamic-alt-tags' ),
					$message
				)
			);
		}

		if ( false !== strpos( $lower, 'worker exceeded resource limits' ) || false !== strpos( $lower, 'exceeded cpu time limit' ) || false !== strpos( $lower, 'error 1102' ) ) {
			$this->set_provider_cooldown( self::ERROR_RESOURCE_LIMITED, $message, 15 * MINUTE_IN_SECONDS );

			return new WP_Error(
				self::ERROR_RESOURCE_LIMITED,
				sprintf(
					/* translators: %s original provider message */
					__( 'Cloudflare Worker resource limits were exceeded. %s', 'dynamic-alt-tags' ),
					$message
				)
			);
		}

		return $error;
	}

	/**
	 * Return cooldown error if requests are temporarily paused.
	 *
	 * @return WP_Error|null
	 */
	private function get_cooldown_error() {
		$cooldown = get_site_transient( self::COOLDOWN_TRANSIENT_KEY );
		if ( ! is_array( $cooldown ) || empty( $cooldown['until'] ) ) {
			return null;
		}

		$until = absint( $cooldown['until'] );
		if ( $until <= time() ) {
			delete_site_transient( self::COOLDOWN_TRANSIENT_KEY );
			return null;
		}

		$code = isset( $cooldown['code'] ) ? sanitize_key( (string) $cooldown['code'] ) : self::ERROR_TEMPORARILY_UNAVAILABLE;
		if ( self::ERROR_QUOTA_EXHAUSTED === $code ) {
			return new WP_Error(
				self::ERROR_TEMPORARILY_UNAVAILABLE,
				__( 'Cloudflare daily free allocation is exhausted. Provider requests are paused until the next UTC reset window.', 'dynamic-alt-tags' )
			);
		}

		return new WP_Error(
			self::ERROR_TEMPORARILY_UNAVAILABLE,
			__( 'Cloudflare provider requests are temporarily paused after recent resource-limit failures. Try again later.', 'dynamic-alt-tags' )
		);
	}

	/**
	 * Persist provider cooldown state.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param int    $ttl Cooldown in seconds.
	 * @return void
	 */
	private function set_provider_cooldown( $code, $message, $ttl ) {
		$ttl = max( MINUTE_IN_SECONDS, absint( $ttl ) );

		set_site_transient(
			self::COOLDOWN_TRANSIENT_KEY,
			array(
				'code'    => sanitize_key( (string) $code ),
				'message' => sanitize_text_field( (string) $message ),
				'until'   => time() + $ttl,
			),
			$ttl
		);
	}

	/**
	 * Seconds until next UTC midnight.
	 *
	 * @return int
	 */
	private function seconds_until_next_utc_midnight() {
		$now           = time();
		$next_midnight = strtotime( 'tomorrow 00:00:00 UTC', $now );

		if ( false === $next_midnight ) {
			return HOUR_IN_SECONDS;
		}

		return max( MINUTE_IN_SECONDS, (int) ( $next_midnight - $now ) );
	}
}
