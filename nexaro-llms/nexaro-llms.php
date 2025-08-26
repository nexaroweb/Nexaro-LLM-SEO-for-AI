<?php
/**
 * Plugin Name: Nexaro LLM Summaries — SEO for AI
 * Plugin URI: https://nexaro.ir
 * Description: Per-page machine-readable summaries for LLMs with llms.txt/json endpoints and LLM sitemaps. Helps AI understand your site content.
 * Version: 1.0.1
 * Author: نکسارو | سئو سایت، طراحی سایت
 * Author URI: https://nexaro.ir
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexaro-llms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
if ( ! defined( 'NEXARO_LLMS_VERSION' ) ) {
	define( 'NEXARO_LLMS_VERSION', '1.0.1' );
}
if ( ! defined( 'NEXARO_LLMS_FILE' ) ) {
	define( 'NEXARO_LLMS_FILE', __FILE__ );
}
if ( ! defined( 'NEXARO_LLMS_PATH' ) ) {
	define( 'NEXARO_LLMS_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'NEXARO_LLMS_URL' ) ) {
	define( 'NEXARO_LLMS_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Default options for the plugin.
 *
 * @return array
 */
function nexaro_llms_default_options() {
	return array(
		'enable_json'         => 1,
		'enable_head_link'    => 1,
		'enable_sitemap'      => 1,
		'sitemap_slug'        => 'llms-sitemap.xml',
		'sitemap_json_slug'   => 'llms-sitemap.json',
		'xrobots'             => 'noindex, noarchive, nosnippet',
		'cache_control'       => 'public, max-age=86400',
		'cors'                => '',
		'history_limit'       => 5,
		'validate_min_chars'  => 180,
		'validate_keywords'   => 5,
		'bulk_per_page'       => 20,
	);
}

/**
 * Activation: seed defaults and flush rules.
 */
function nexaro_llms_activate() {
	$defaults = nexaro_llms_default_options();
	$options  = get_option( 'nexaro_llms_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	$merged = wp_parse_args( $options, $defaults );
	update_option( 'nexaro_llms_options', $merged, false );

	// Register rules before flushing.
	nexaro_llms_include_files();
	\NexaroLLMS\Endpoints::register_rewrite_rules();
	\NexaroLLMS\Sitemap::register_rewrite_rules();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nexaro_llms_activate' );

/**
 * Deactivation: flush rules only.
 */
function nexaro_llms_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nexaro_llms_deactivate' );

/**
 * Requirements check. If unmet, deactivate plugin.
 */
function nexaro_llms_maybe_deactivate_for_requirements() {
	global $wp_version;
	$min_wp  = '5.8';
	$min_php = '7.4';
	if ( version_compare( PHP_VERSION, $min_php, '<' ) || version_compare( $wp_version, $min_wp, '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', static function () use ( $min_wp, $min_php ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				$message = sprintf(
					esc_html__( 'Nexaro LLM Summaries requires WordPress %1$s+ and PHP %2$s+. The plugin has been deactivated.', 'nexaro-llms' ),
					esc_html( $min_wp ),
					esc_html( $min_php )
				);
				echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
			}
		} );
		return true;
	}
	return false;
}

/**
 * Include core files.
 */
function nexaro_llms_include_files() {
	require_once NEXARO_LLMS_PATH . 'includes/class-admin.php';
	require_once NEXARO_LLMS_PATH . 'includes/class-metabox.php';
	require_once NEXARO_LLMS_PATH . 'includes/class-endpoints.php';
	require_once NEXARO_LLMS_PATH . 'includes/class-sitemap.php';
}

/**
 * Bootstrap plugin.
 */
function nexaro_llms_bootstrap() {
	if ( nexaro_llms_maybe_deactivate_for_requirements() ) {
		return;
	}

	// Load textdomain.
	load_plugin_textdomain( 'nexaro-llms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	nexaro_llms_include_files();

	// Initialize components.
	\NexaroLLMS\Admin::init();
	\NexaroLLMS\Metabox::init();
	\NexaroLLMS\Endpoints::init();
	\NexaroLLMS\Sitemap::init();

	/**
	 * Fires when Nexaro LLMS is fully loaded.
	 */
	do_action( 'nexaro_llms_loaded' );
}
add_action( 'plugins_loaded', 'nexaro_llms_bootstrap' );

/**
 * Flush rewrite rules on sitemap slug change.
 */
add_action( 'update_option_nexaro_llms_options', static function ( $old_value, $value ) {
	$old_slug_xml  = isset( $old_value['sitemap_slug'] ) ? (string) $old_value['sitemap_slug'] : 'llms-sitemap.xml';
	$old_slug_json = isset( $old_value['sitemap_json_slug'] ) ? (string) $old_value['sitemap_json_slug'] : 'llms-sitemap.json';
	$new_slug_xml  = isset( $value['sitemap_slug'] ) ? (string) $value['sitemap_slug'] : 'llms-sitemap.xml';
	$new_slug_json = isset( $value['sitemap_json_slug'] ) ? (string) $value['sitemap_json_slug'] : 'llms-sitemap.json';
	if ( $old_slug_xml !== $new_slug_xml || $old_slug_json !== $new_slug_json ) {
		flush_rewrite_rules();
	}
}, 10, 2 );