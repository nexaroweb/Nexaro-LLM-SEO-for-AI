<?php
namespace NexaroLLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sitemap {
	public static function init() : void {
		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_serve' ], 2 );
	}

	public static function register_query_vars( array $vars ) : array {
		$vars[] = 'llms_sitemap';
		return $vars;
	}

	public static function register_rewrite_rules() : void {
		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );
		$xml  = trim( (string) $opts['sitemap_slug'] );
		$json = trim( (string) $opts['sitemap_json_slug'] );
		$xml  = '' !== $xml ? $xml : 'llms-sitemap.xml';
		$json = '' !== $json ? $json : 'llms-sitemap.json';

		add_rewrite_rule( '^' . preg_quote( $xml, '/' ) . '$', 'index.php?llms_sitemap=xml', 'top' );
		add_rewrite_rule( '^' . preg_quote( $json, '/' ) . '$', 'index.php?llms_sitemap=json', 'top' );

		// Always support defaults as well to avoid breakage.
		if ( $xml !== 'llms-sitemap.xml' ) {
			add_rewrite_rule( '^llms-sitemap\.xml$', 'index.php?llms_sitemap=xml', 'top' );
		}
		if ( $json !== 'llms-sitemap.json' ) {
			add_rewrite_rule( '^llms-sitemap\.json$', 'index.php?llms_sitemap=json', 'top' );
		}
	}

	public static function maybe_serve() : void {
		$which = get_query_var( 'llms_sitemap' );
		if ( ! in_array( $which, [ 'xml', 'json' ], true ) ) {
			return;
		}
		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );
		if ( empty( $opts['enable_sitemap'] ) ) {
			status_header( 404 );
			header( 'X-Nexaro-LLMS: 404' );
			exit;
		}
		self::send_common_headers( $opts );

		$items = self::collect_items( ! empty( $opts['enable_json'] ) );
		if ( 'xml' === $which ) {
			header( 'Content-Type: application/xml; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
			echo self::render_xml( $items, ! empty( $opts['enable_json'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
		// JSON sitemap
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		echo wp_json_encode( [
			'type'    => 'llms-sitemap',
			'count'   => count( $items ),
			'items'   => array_values( $items ),
			'version' => NEXARO_LLMS_VERSION,
			'site'    => [ 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ) ],
		] );
		exit;
	}

	private static function collect_items( bool $include_json ) : array {
		$q = new \WP_Query( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => Metabox::META_KEY_SUMMARY,
					'compare' => '!=',
					'value'   => '',
				],
			],
		] );
		$items = [];
		foreach ( $q->posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			$entry = [
				'loc' => trailingslashit( $permalink ) . 'llms.txt',
				'id'  => (int) $post_id,
				'title' => get_the_title( $post_id ),
			];
			if ( $include_json ) {
				$entry['json'] = trailingslashit( $permalink ) . 'llms.json';
			}
			$entry = apply_filters( 'nexaro_llms_sitemap_item', $entry, $post_id );
			$items[] = $entry;
		}
		return $items;
	}

	private static function render_xml( array $items, bool $include_json ) : string {
		$xmlns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
		$xml  = '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset', 'UTF-8' ) ) . '"?>';
		$xml .= '<urlset xmlns="' . esc_attr( $xmlns ) . '">';
		foreach ( $items as $item ) {
			$xml .= '<url>';
			$xml .= '<loc>' . esc_url( $item['loc'] ) . '</loc>';
			if ( $include_json && ! empty( $item['json'] ) ) {
				$xml .= '<x-json>' . esc_url( $item['json'] ) . '</x-json>';
			}
			$xml .= '</url>';
		}
		$xml .= '</urlset>';
		return $xml;
	}

	private static function send_common_headers( array $opts ) : void {
		if ( ! headers_sent() ) {
			if ( ! empty( $opts['xrobots'] ) ) {
				header( 'X-Robots-Tag: ' . $opts['xrobots'] );
			}
			if ( ! empty( $opts['cache_control'] ) ) {
				header( 'Cache-Control: ' . $opts['cache_control'] );
			}
			if ( ! empty( $opts['cors'] ) ) {
				header( 'Access-Control-Allow-Origin: ' . $opts['cors'] );
			}
			header( 'X-Nexaro-LLMS: hit' );
		}
	}
}