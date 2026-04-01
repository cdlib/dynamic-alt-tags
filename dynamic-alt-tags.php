<?php
/**
 * Plugin Name:       Dynamic Alt Tags
 * Plugin URI:        https://github.com/ericsatzman/dynamic-alt-tags
 * Description:       Generate and manage AI-suggested alt text for WordPress images.
 * Version:           1.0.5
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Eric Satzman
 * Author URI:        mailto:esatzman@ucop.edu
 * Text Domain:       dynamic-alt-tags
 * Domain Path:       /languages
 * Update URI:        https://cdlib.org/services-groups/webprod/plugins/dynamic-alt-tags/
 *
 * @package WPAIAltText
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAI_ALT_TEXT_VERSION', '1.0.5' );
define( 'WPAI_ALT_TEXT_FILE', __FILE__ );
define( 'WPAI_ALT_TEXT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAI_ALT_TEXT_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAI_ALT_TEXT_CRON_HOOK', 'ai_alt_text_process_queue' );
define( 'WPAI_ALT_TEXT_QUEUE_CAP', 'ai_alt_manage_queue' );
define( 'WPAI_ALT_TEXT_UPDATE_INFO_URL', 'https://cdlib.org/services-groups/webprod/plugins/dynamic-alt-tags/info.json' );
define( 'WPAI_ALT_TEXT_UPDATE_PACKAGE_URL', 'https://cdlib.org/services-groups/webprod/plugins/dynamic-alt-tags/files/dynamic-alt-tags-1.0.5.zip' );

require_once WPAI_ALT_TEXT_DIR . 'includes/class-activator.php';
require_once WPAI_ALT_TEXT_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WPAI_Alt_Text_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPAI_Alt_Text_Activator', 'deactivate' ) );

WPAI_Alt_Text_Plugin::instance();
