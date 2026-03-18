<?php
/**
 * Self-hosted plugin updater.
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAI_Alt_Text_Updater {

	/**
	 * Cached remote payload transient key.
	 *
	 * @var string
	 */
	const REMOTE_INFO_TRANSIENT_KEY = 'wpai_alt_text_update_info';

	/**
	 * Get icon URLs for update UI.
	 *
	 * @return array<string,string>
	 */
	private function get_icon_urls() {
		$svg_icon_url = esc_url_raw( plugins_url( 'assets/plugin-icon.svg', WPAI_ALT_TEXT_FILE ) );
		$icon_1x_url  = esc_url_raw( plugins_url( 'assets/plugin-icon-128.png', WPAI_ALT_TEXT_FILE ) );
		$icon_2x_url  = esc_url_raw( plugins_url( 'assets/plugin-icon-256.png', WPAI_ALT_TEXT_FILE ) );

		return array(
			'svg'     => $svg_icon_url,
			'2x'      => $icon_2x_url,
			'1x'      => $icon_1x_url,
			'default' => $svg_icon_url,
		);
	}

	/**
	 * External update hostname.
	 *
	 * @var string
	 */
	const UPDATE_HOSTNAME = 'satzman.com';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'update_plugins_' . self::UPDATE_HOSTNAME, array( $this, 'filter_update' ), 10, 4 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_legacy_update' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
	}

	/**
	 * Provide update details for the plugin Update URI hostname.
	 *
	 * @param false|array<string,mixed>|object $update Existing update payload.
	 * @param array<string,string>             $plugin_data Plugin header data.
	 * @param string                           $plugin_file Plugin basename.
	 * @param string[]                         $locales Installed locales.
	 * @return false|array<string,mixed>|object
	 */
	public function filter_update( $update, $plugin_data, $plugin_file, $locales ) {
		unset( $locales );

		if ( plugin_basename( WPAI_ALT_TEXT_FILE ) !== $plugin_file ) {
			return $update;
		}

		$remote_info = $this->get_remote_info();
		if ( empty( $remote_info['version'] ) || empty( $remote_info['download_url'] ) ) {
			return $update;
		}

		return (object) array(
			'id'           => 'https://satzman.com/plugin-updates/dynamic-alt-tags/',
			'slug'         => 'dynamic-alt-tags',
			'version'      => (string) $remote_info['version'],
			'new_version'  => (string) $remote_info['version'],
			'url'          => isset( $remote_info['homepage'] ) ? (string) $remote_info['homepage'] : 'https://satzman.com/',
			'package'      => (string) $remote_info['download_url'],
			'requires'     => isset( $remote_info['requires'] ) ? (string) $remote_info['requires'] : '',
			'tested'       => isset( $remote_info['tested'] ) ? (string) $remote_info['tested'] : '',
			'requires_php' => isset( $remote_info['requires_php'] ) ? (string) $remote_info['requires_php'] : '',
			'icons'        => $this->get_icon_urls(),
		);
	}

	/**
	 * Inject update details into the WordPress update transient for legacy sites.
	 *
	 * @param stdClass|mixed $transient Update transient.
	 * @return stdClass|mixed
	 */
	public function inject_legacy_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( WPAI_ALT_TEXT_FILE );
		if ( ! isset( $transient->checked[ $plugin_file ] ) ) {
			return $transient;
		}

		$remote_info = $this->get_remote_info();
		if ( empty( $remote_info['version'] ) || empty( $remote_info['download_url'] ) ) {
			return $transient;
		}

		$current_version = (string) $transient->checked[ $plugin_file ];
		$remote_version  = (string) $remote_info['version'];

		if ( ! version_compare( $remote_version, $current_version, '>' ) ) {
			return $transient;
		}

		$transient->response[ $plugin_file ] = (object) array(
			'slug'         => 'dynamic-alt-tags',
			'plugin'       => $plugin_file,
			'new_version'  => $remote_version,
			'package'      => (string) $remote_info['download_url'],
			'url'          => isset( $remote_info['homepage'] ) ? (string) $remote_info['homepage'] : 'https://satzman.com/',
			'requires'     => isset( $remote_info['requires'] ) ? (string) $remote_info['requires'] : '',
			'tested'       => isset( $remote_info['tested'] ) ? (string) $remote_info['tested'] : '',
			'requires_php' => isset( $remote_info['requires_php'] ) ? (string) $remote_info['requires_php'] : '',
			'icons'        => $this->get_icon_urls(),
		);

		return $transient;
	}

	/**
	 * Provide plugin details for the update modal.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action Requested action.
	 * @param object $args Plugin API arguments.
	 * @return mixed
	 */
	public function filter_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
			return $result;
		}

		$slug = isset( $args->slug ) ? sanitize_key( (string) $args->slug ) : '';
		if ( 'dynamic-alt-tags' !== $slug ) {
			return $result;
		}

		$remote_info = $this->get_remote_info( true );
		if ( empty( $remote_info['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => isset( $remote_info['name'] ) ? (string) $remote_info['name'] : 'Dynamic Alt Tags',
			'slug'          => 'dynamic-alt-tags',
			'version'       => (string) $remote_info['version'],
			'author'        => '<a href="https://satzman.com/">Eric Satzman</a>',
			'homepage'      => isset( $remote_info['homepage'] ) ? (string) $remote_info['homepage'] : 'https://satzman.com/',
			'requires'      => isset( $remote_info['requires'] ) ? (string) $remote_info['requires'] : '',
			'tested'        => isset( $remote_info['tested'] ) ? (string) $remote_info['tested'] : '',
			'requires_php'  => isset( $remote_info['requires_php'] ) ? (string) $remote_info['requires_php'] : '',
			'last_updated'  => isset( $remote_info['last_updated'] ) ? (string) $remote_info['last_updated'] : '',
			'download_link' => isset( $remote_info['download_url'] ) ? (string) $remote_info['download_url'] : WPAI_ALT_TEXT_UPDATE_PACKAGE_URL,
			'icons'         => $this->get_icon_urls(),
			'sections'      => isset( $remote_info['sections'] ) && is_array( $remote_info['sections'] ) ? $remote_info['sections'] : array(),
		);
	}

	/**
	 * Fetch and normalize remote update info.
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return array<string,mixed>
	 */
	private function get_remote_info( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_site_transient( self::REMOTE_INFO_TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			WPAI_ALT_TEXT_UPDATE_INFO_URL,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || '' === trim( $body ) ) {
			return array();
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$remote_info = array(
			'name'          => isset( $decoded['name'] ) ? sanitize_text_field( (string) $decoded['name'] ) : 'Dynamic Alt Tags',
			'version'       => isset( $decoded['version'] ) ? sanitize_text_field( (string) $decoded['version'] ) : '',
			'download_url'  => isset( $decoded['download_url'] ) ? esc_url_raw( (string) $decoded['download_url'] ) : WPAI_ALT_TEXT_UPDATE_PACKAGE_URL,
			'homepage'      => isset( $decoded['homepage'] ) ? esc_url_raw( (string) $decoded['homepage'] ) : 'https://satzman.com/',
			'requires'      => isset( $decoded['requires'] ) ? sanitize_text_field( (string) $decoded['requires'] ) : '',
			'tested'        => isset( $decoded['tested'] ) ? sanitize_text_field( (string) $decoded['tested'] ) : '',
			'requires_php'  => isset( $decoded['requires_php'] ) ? sanitize_text_field( (string) $decoded['requires_php'] ) : '',
			'last_updated'  => isset( $decoded['last_updated'] ) ? sanitize_text_field( (string) $decoded['last_updated'] ) : '',
			'sections'      => $this->normalize_sections( isset( $decoded['sections'] ) ? $decoded['sections'] : array() ),
		);

		set_site_transient( self::REMOTE_INFO_TRANSIENT_KEY, $remote_info, 6 * HOUR_IN_SECONDS );

		return $remote_info;
	}

	/**
	 * Normalize plugin info modal sections.
	 *
	 * @param mixed $sections Raw sections.
	 * @return array<string,string>
	 */
	private function normalize_sections( $sections ) {
		if ( ! is_array( $sections ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $sections as $key => $value ) {
			$section_key = sanitize_key( (string) $key );
			if ( '' === $section_key ) {
				continue;
			}

			$normalized[ $section_key ] = wp_kses_post( (string) $value );
		}

		return $normalized;
	}
}
