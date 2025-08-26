<?php
namespace NexaroLLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Endpoints {
	public static function init() : void {
		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_serve' ], 1 );
		add_action( 'wp_head', [ __CLASS__, 'inject_head_link' ] );
	}

	public static function register_query_vars( array $vars ) : array {
		$vars[] = 'llms';
		$vars[] = 'llms_format';
		$vars[] = 'llms_path';
		return $vars;
	}

	public static function register_rewrite_rules() : void {
		add_rewrite_rule( '(.+?)/llms\.txt/?$', 'index.php?llms=1&llms_format=txt&llms_path=$matches[1]', 'top' );
		add_rewrite_rule( '(.+?)/llms\.json/?$', 'index.php?llms=1&llms_format=json&llms_path=$matches[1]', 'top' );
	}

	public static function inject_head_link() : void {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );
		if ( empty( $opts['enable_head_link'] ) ) {
			return;
		}
		global $post;
		if ( ! $post instanceof \WP_Post ) { return; }
		$summary = (string) get_post_meta( $post->ID, Metabox::META_KEY_SUMMARY, true );
		if ( '' === trim( $summary ) ) { return; }
		$href = trailingslashit( get_permalink( $post ) ) . 'llms.txt';
		$href = apply_filters( 'nexaro_llms_head_href', $href, $post );
		echo '<link rel="alternate" type="text/plain" href="' . esc_url( $href ) . '" />' . "\n";
	}

	public static function maybe_serve() : void {
		if ( (int) get_query_var( 'llms' ) !== 1 ) {
			return;
		}
		$format = get_query_var( 'llms_format' );
		$path   = get_query_var( 'llms_path' );

		if ( ! in_array( $format, [ 'txt', 'json' ], true ) ) {
			self::send_status( 404, 'invalid-format' );
			return;
		}

		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );
		if ( 'json' === $format && empty( $opts['enable_json'] ) ) {
			self::send_status( 404, 'json-disabled' );
			return;
		}

		$post = self::resolve_post_from_path( $path );
		if ( ! $post ) {
			self::send_status( 404, 'no-post' );
			return;
		}

		$summary = (string) get_post_meta( $post->ID, Metabox::META_KEY_SUMMARY, true );
		$summary = trim( $summary );
		if ( '' === $summary ) {
			self::send_status( 404, 'no-summary' );
			return;
		}

		// Headers
		self::send_common_headers( $opts );

		if ( 'txt' === $format ) {
			header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
			$content = preg_replace( "/\r\n|\r|\n/", "\n", (string) $summary );
			$content = apply_filters( 'nexaro_llms_output_text', $content, $post );
			header( 'X-Nexaro-LLMS: hit' );
			do_action( 'nexaro_llms_served', $post, 'txt' );
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// JSON
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		$payload = self::build_json_payload( $post, $summary );
		$payload = apply_filters( 'nexaro_llms_output_json', $payload, $post, $summary );
		header( 'X-Nexaro-LLMS: hit' );
		do_action( 'nexaro_llms_served', $post, 'json' );
		echo wp_json_encode( $payload );
		exit;
	}

	private static function build_json_payload( \WP_Post $post, string $summary ) : array {
		$history = get_post_meta( $post->ID, Metabox::META_KEY_HISTORY, true );
		$history = is_array( $history ) ? $history : [];
		$version = count( $history ) > 0 ? count( $history ) : 1;
		$keywords = Metabox::extract_keywords( $summary );
		$related = Metabox::related_links( $post );
		$chars = strlen( $summary );
		$words = str_word_count( wp_strip_all_tags( $summary ) );
		return [
			'page' => [
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
				'url'   => get_permalink( $post ),
				'type'  => (string) get_post_type( $post ),
			],
			'format'   => 'llms',
			'lang'     => get_bloginfo( 'language' ),
			'content'  => $summary,
			'metrics'  => [ 'chars' => $chars, 'words' => $words ],
			'keywords' => array_values( $keywords ),
			'related'  => array_values( $related ),
			'timestamp'=> current_time( 'mysql', true ),
			'version'  => (int) $version,
		];
	}

	private static function resolve_post_from_path( string $path ) : ?\WP_Post {
		$path = ltrim( (string) $path, '/' );
		$full = home_url( '/' . $path . '/' );
		$post_id = url_to_postid( $full );
		if ( ! $post_id ) {
			// Fallback by path
			$trimmed = trim( (string) $path, '/' );
			$post = get_page_by_path( $trimmed, OBJECT, get_post_types( [ 'public' => true ] ) );
			if ( $post instanceof \WP_Post ) {
				$post_id = $post->ID;
			}
		}
		if ( $post_id ) {
			$p = get_post( $post_id );
			if ( $p && 'attachment' !== $p->post_type ) {
				return $p;
			}
		}
		return null;
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
		}
	}

	private static function send_status( int $code, string $reason = '' ) : void {
		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );
		self::send_common_headers( $opts );
		status_header( $code );
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset', 'UTF-8' ) );
		header( 'X-Nexaro-LLMS: 404' );
		echo esc_html( (string) $reason );
		exit;
	}
}