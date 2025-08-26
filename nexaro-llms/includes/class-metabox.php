<?php
namespace NexaroLLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Metabox {
	const META_KEY_SUMMARY = '_nexaro_llms_summary';
	const META_KEY_HISTORY = '_nexaro_llms_history';

	public static function init() : void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_metabox' ] );
		add_action( 'save_post', [ __CLASS__, 'save_post' ], 10, 2 );
	}

	public static function add_metabox() : void {
		$post_types = [ 'page' ];
		foreach ( $post_types as $pt ) {
			add_meta_box( 'nexaro-llms-meta', esc_html__( 'LLM Summary (llms.txt / llms.json)', 'nexaro-llms' ), [ __CLASS__, 'render' ], $pt, 'normal', 'high' );
		}
	}

	private static function generate_draft( \WP_Post $post ) : string {
		$content = (string) $post->post_content;
		$content = wp_strip_all_tags( $content, true );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}
		$words = preg_split( '/\s+/', $content );
		$excerpt = array_slice( $words, 0, 90 );
		$base = trim( implode( ' ', $excerpt ) );
		$keywords = self::extract_keywords( $content );
		$related = self::related_links( $post );
		$lines = [];
		$lines[] = $base;
		if ( ! empty( $keywords ) ) {
			$lines[] = '';
			$lines[] = 'Keywords: ' . implode( ', ', array_slice( $keywords, 0, 12 ) );
		}
		if ( ! empty( $related ) ) {
			$lines[] = '';
			$lines[] = 'Related: ' . implode( ', ', $related );
		}
		return implode( "\n", $lines );
	}

	public static function extract_keywords( string $text ) : array {
		$text = strtolower( $text );
		$text = preg_replace( '/[\p{P}\p{S}]+/u', ' ', $text );
		$parts = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$stop = Admin::stopwords();
		$freq = [];
		foreach ( $parts as $w ) {
			if ( mb_strlen( $w ) < 3 ) { continue; }
			if ( in_array( $w, $stop, true ) ) { continue; }
			$freq[ $w ] = isset( $freq[ $w ] ) ? $freq[ $w ] + 1 : 1;
		}
		arsort( $freq );
		return array_slice( array_keys( $freq ), 0, 15 );
	}

	public static function related_links( \WP_Post $post ) : array {
		$related = [];
		if ( $post->post_parent ) {
			$related[] = get_permalink( $post->post_parent );
		}
		$children = get_children( [ 'post_parent' => $post->ID, 'post_type' => 'page', 'post_status' => 'publish' ] );
		foreach ( $children as $child ) {
			$related[] = get_permalink( $child );
		}
		$siblings = get_children( [ 'post_parent' => $post->post_parent, 'post_type' => 'page', 'post_status' => 'publish' ] );
		foreach ( $siblings as $sib ) {
			if ( (int) $sib->ID !== (int) $post->ID ) {
				$related[] = get_permalink( $sib );
			}
		}
		return array_values( array_unique( array_filter( $related ) ) );
	}

	public static function render( \WP_Post $post ) : void {
		if ( ! current_user_can( 'edit_page', $post->ID ) ) {
			return;
		}
		$options  = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts     = wp_parse_args( $options, $defaults );

		$summary = (string) get_post_meta( $post->ID, self::META_KEY_SUMMARY, true );

		$prefill = '';
		if ( isset( $_GET['nx_llms_generate'] ) && isset( $_GET['_nxnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( (string) $_GET['_nxnonce'], 'nx_llms_generate_' . $post->ID ) && current_user_can( 'edit_page', $post->ID ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$prefill = self::generate_draft( $post );
			}
		}

		$val = $prefill !== '' ? $prefill : $summary;

		wp_nonce_field( 'nexaro_llms_meta_' . $post->ID, 'nexaro_llms_meta_nonce' );
		echo '<div id="nexaro-llms-meta" class="nx-card">';
		echo '<div class="nx-actions">';
		$gen_url = add_query_arg( [ 'nx_llms_generate' => 1, '_nxnonce' => wp_create_nonce( 'nx_llms_generate_' . $post->ID ) ] );
		echo '<a id="nx-generate-draft" href="' . esc_url( $gen_url ) . '" class="button nx-ghost">' . esc_html__( 'Generate draft from page content', 'nexaro-llms' ) . '</a>';
		$permalink = get_permalink( $post );
		$txt = trailingslashit( $permalink ) . 'llms.txt';
		$json = trailingslashit( $permalink ) . 'llms.json';
		echo '<span class="nx-muted">' . esc_html__( 'Endpoints:', 'nexaro-llms' ) . ' <code class="code">' . esc_html( $txt ) . '</code>';
		if ( ! empty( $opts['enable_json'] ) ) {
			echo ' · <code class="code">' . esc_html( $json ) . '</code>';
		}
		echo '</span>';
		echo '</div>';
		echo '<p><label for="nexaro_llms_summary" class="screen-reader-text">' . esc_html__( 'LLM Summary', 'nexaro-llms' ) . '</label>';
		echo '<textarea id="nexaro_llms_summary" name="nexaro_llms_summary" placeholder="' . esc_attr__( 'Write a concise, descriptive summary for LLMs. Plain text or Markdown allowed; no HTML.', 'nexaro-llms' ) . '">' . esc_textarea( $val ) . '</textarea></p>';
		echo '<div id="nexaro-llms-validate" class="nx-validate"><strong>' . esc_html__( 'Validation', 'nexaro-llms' ) . ':</strong> <span class="nx-metrics"></span><div class="nx-hints"></div></div>';

		$history = get_post_meta( $post->ID, self::META_KEY_HISTORY, true );
		$history = is_array( $history ) ? $history : [];
		if ( ! empty( $history ) ) {
			echo '<details><summary>' . esc_html__( 'Version history', 'nexaro-llms' ) . '</summary>';
			echo '<ul class="nx-list">';
			foreach ( $history as $snap ) {
				$ts = isset( $snap['time'] ) ? (int) $snap['time'] : time();
				$dt = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
				echo '<li><span class="nx-muted">' . esc_html( $dt ) . '</span> — <code class="code">' . esc_html( mb_substr( (string) ( $snap['content'] ?? '' ), 0, 80 ) ) . ( mb_strlen( (string) ( $snap['content'] ?? '' ) ) > 80 ? '…' : '' ) . '</code></li>';
			}
			echo '</ul></details>';
		}
		echo '</div>';
	}

	public static function save_post( int $post_id, \WP_Post $post ) : void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) ) { return; }
		if ( 'page' !== $post->post_type ) { return; }
		if ( ! isset( $_POST['nexaro_llms_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nexaro_llms_meta_nonce'] ) ), 'nexaro_llms_meta_' . $post_id ) ) { return; }
		if ( ! current_user_can( 'edit_page', $post_id ) ) { return; }
		$raw = isset( $_POST['nexaro_llms_summary'] ) ? (string) wp_unslash( $_POST['nexaro_llms_summary'] ) : '';
		$clean = wp_kses( $raw, [] );
		$clean = preg_replace( "/\r\n|\r|\n/", "\n", (string) $clean );
		$clean = trim( (string) $clean );
		$prev = (string) get_post_meta( $post_id, self::META_KEY_SUMMARY, true );
		if ( $clean !== $prev ) {
			update_post_meta( $post_id, self::META_KEY_SUMMARY, $clean );
			$history = get_post_meta( $post_id, self::META_KEY_HISTORY, true );
			$history = is_array( $history ) ? $history : [];
			array_unshift( $history, [ 'time' => time(), 'content' => $clean ] );
			$options  = get_option( 'nexaro_llms_options', [] );
			$defaults = \nexaro_llms_default_options();
			$opts     = wp_parse_args( $options, $defaults );
			$limit    = max( 1, (int) $opts['history_limit'] );
			$history  = array_slice( $history, 0, $limit );
			update_post_meta( $post_id, self::META_KEY_HISTORY, $history );
		}
	}
}