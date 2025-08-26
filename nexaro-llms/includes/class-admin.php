<?php
namespace NexaroLLMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {
	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( string $hook ) : void {
		if ( false === strpos( $hook, 'nexaro-llms' ) && false === strpos( $hook, 'post.php' ) && false === strpos( $hook, 'post-new.php' ) ) {
			return;
		}
		wp_enqueue_style( 'nexaro-llms-admin', NEXARO_LLMS_URL . 'assets/admin.css', [], NEXARO_LLMS_VERSION );
		wp_enqueue_script( 'nexaro-llms-admin', NEXARO_LLMS_URL . 'assets/admin.js', [], NEXARO_LLMS_VERSION, true );

		$options = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts = wp_parse_args( $options, $defaults );
		wp_localize_script( 'nexaro-llms-admin', 'NexaroLLMS', [
			'minChars'    => (int) $opts['validate_min_chars'],
			'minKeywords' => (int) $opts['validate_keywords'],
			'stopwords'   => self::stopwords(),
			'i18nMinChars'=> esc_html__( 'Consider adding more detail to reach the minimum characters.', 'nexaro-llms' ),
			'i18nKeywords'=> esc_html__( 'Include more specific terms or entities.', 'nexaro-llms' ),
			'i18nMetrics' => esc_html__( 'Metrics:', 'nexaro-llms' ),
		] );
	}

	public static function register_menus() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_menu_page(
			esc_html__( 'Nexaro LLM', 'nexaro-llms' ),
			esc_html__( 'Nexaro LLM', 'nexaro-llms' ),
			'manage_options',
			'nexaro-llms',
			[ __CLASS__, 'render_settings_page' ],
			'dashicons-lightbulb',
			2
		);
		add_submenu_page( 'nexaro-llms', esc_html__( 'Settings', 'nexaro-llms' ), esc_html__( 'Settings', 'nexaro-llms' ), 'manage_options', 'nexaro-llms', [ __CLASS__, 'render_settings_page' ] );
		add_submenu_page( 'nexaro-llms', esc_html__( 'Bulk', 'nexaro-llms' ), esc_html__( 'Bulk', 'nexaro-llms' ), 'edit_pages', 'nexaro-llms-bulk', [ __CLASS__, 'render_bulk_page' ] );
		add_submenu_page( 'nexaro-llms', esc_html__( 'Docs', 'nexaro-llms' ), esc_html__( 'Docs', 'nexaro-llms' ), 'read', 'nexaro-llms-docs', [ __CLASS__, 'render_docs_page' ] );
	}

	public static function register_settings() : void {
		register_setting( 'nexaro-llms', 'nexaro_llms_options', [ __CLASS__, 'sanitize_options' ] );

		add_settings_section( 'nx_general', esc_html__( 'General', 'nexaro-llms' ), function(){
			echo '<p class="description">' . esc_html__( 'Core behavior and feature toggles.', 'nexaro-llms' ) . '</p>';
		}, 'nexaro-llms' );

		add_settings_field( 'enable_head_link', esc_html__( 'Inject head link', 'nexaro-llms' ), [ __CLASS__, 'field_checkbox' ], 'nexaro-llms', 'nx_general', [ 'key' => 'enable_head_link', 'label' => esc_html__( 'Add rel=alternate link for llms.txt on pages with summaries.', 'nexaro-llms' ) ] );
		add_settings_field( 'enable_json', esc_html__( 'Enable JSON endpoints', 'nexaro-llms' ), [ __CLASS__, 'field_checkbox' ], 'nexaro-llms', 'nx_general', [ 'key' => 'enable_json', 'label' => esc_html__( 'Serve /llms.json and include in sitemap.', 'nexaro-llms' ) ] );
		add_settings_field( 'enable_sitemap', esc_html__( 'Enable LLM sitemaps', 'nexaro-llms' ), [ __CLASS__, 'field_checkbox' ], 'nexaro-llms', 'nx_general', [ 'key' => 'enable_sitemap', 'label' => esc_html__( 'Expose /llms-sitemap.xml and /llms-sitemap.json', 'nexaro-llms' ) ] );

		add_settings_section( 'nx_headers', esc_html__( 'HTTP Headers', 'nexaro-llms' ), function(){
			echo '<p class="description">' . esc_html__( 'Safety headers for endpoints and sitemaps.', 'nexaro-llms' ) . '</p>';
		}, 'nexaro-llms' );
		add_settings_field( 'xrobots', esc_html__( 'X-Robots-Tag', 'nexaro-llms' ), [ __CLASS__, 'field_text' ], 'nexaro-llms', 'nx_headers', [ 'key' => 'xrobots', 'placeholder' => 'noindex, noarchive, nosnippet' ] );
		add_settings_field( 'cache_control', esc_html__( 'Cache-Control', 'nexaro-llms' ), [ __CLASS__, 'field_text' ], 'nexaro-llms', 'nx_headers', [ 'key' => 'cache_control', 'placeholder' => 'public, max-age=86400' ] );
		add_settings_field( 'cors', esc_html__( 'CORS (Access-Control-Allow-Origin)', 'nexaro-llms' ), [ __CLASS__, 'field_text' ], 'nexaro-llms', 'nx_headers', [ 'key' => 'cors', 'placeholder' => '*' ] );

		add_settings_section( 'nx_sitemap', esc_html__( 'LLM Sitemaps', 'nexaro-llms' ), function(){
			echo '<p class="description">' . esc_html__( 'Control slugs for XML and JSON sitemaps. Changing slugs flushes rewrite rules.', 'nexaro-llms' ) . '</p>';
		}, 'nexaro-llms' );
		add_settings_field( 'sitemap_slug', esc_html__( 'XML Sitemap slug', 'nexaro-llms' ), [ __CLASS__, 'field_text' ], 'nexaro-llms', 'nx_sitemap', [ 'key' => 'sitemap_slug', 'placeholder' => 'llms-sitemap.xml' ] );
		add_settings_field( 'sitemap_json_slug', esc_html__( 'JSON Sitemap slug', 'nexaro-llms' ), [ __CLASS__, 'field_text' ], 'nexaro-llms', 'nx_sitemap', [ 'key' => 'sitemap_json_slug', 'placeholder' => 'llms-sitemap.json' ] );

		add_settings_section( 'nx_validation', esc_html__( 'Validation & Limits', 'nexaro-llms' ), function(){
			echo '<p class="description">' . esc_html__( 'Configure validation thresholds and history retention.', 'nexaro-llms' ) . '</p>';
		}, 'nexaro-llms' );
		add_settings_field( 'validate_min_chars', esc_html__( 'Minimum characters', 'nexaro-llms' ), [ __CLASS__, 'field_number' ], 'nexaro-llms', 'nx_validation', [ 'key' => 'validate_min_chars', 'min' => 50, 'step' => 10 ] );
		add_settings_field( 'validate_keywords', esc_html__( 'Minimum keywords', 'nexaro-llms' ), [ __CLASS__, 'field_number' ], 'nexaro-llms', 'nx_validation', [ 'key' => 'validate_keywords', 'min' => 1, 'step' => 1 ] );
		add_settings_field( 'history_limit', esc_html__( 'History snapshots', 'nexaro-llms' ), [ __CLASS__, 'field_number' ], 'nexaro-llms', 'nx_validation', [ 'key' => 'history_limit', 'min' => 1, 'max' => 20, 'step' => 1 ] );
		add_settings_field( 'bulk_per_page', esc_html__( 'Bulk list size', 'nexaro-llms' ), [ __CLASS__, 'field_number' ], 'nexaro-llms', 'nx_validation', [ 'key' => 'bulk_per_page', 'min' => 5, 'max' => 200, 'step' => 5 ] );
	}

	public static function sanitize_options( $raw ) : array {
		$defaults = \nexaro_llms_default_options();
		$raw = is_array( $raw ) ? $raw : [];
		$san = [];
		$san['enable_json']        = empty( $raw['enable_json'] ) ? 0 : 1;
		$san['enable_head_link']   = empty( $raw['enable_head_link'] ) ? 0 : 1;
		$san['enable_sitemap']     = empty( $raw['enable_sitemap'] ) ? 0 : 1;
		$san['sitemap_slug']       = sanitize_text_field( $raw['sitemap_slug'] ?? $defaults['sitemap_slug'] );
		$san['sitemap_json_slug']  = sanitize_text_field( $raw['sitemap_json_slug'] ?? $defaults['sitemap_json_slug'] );
		$san['xrobots']            = sanitize_text_field( $raw['xrobots'] ?? $defaults['xrobots'] );
		$san['cache_control']      = sanitize_text_field( $raw['cache_control'] ?? $defaults['cache_control'] );
		$san['cors']               = sanitize_text_field( $raw['cors'] ?? $defaults['cors'] );
		$san['history_limit']      = max( 1, (int) ( $raw['history_limit'] ?? $defaults['history_limit'] ) );
		$san['validate_min_chars'] = max( 0, (int) ( $raw['validate_min_chars'] ?? $defaults['validate_min_chars'] ) );
		$san['validate_keywords']  = max( 0, (int) ( $raw['validate_keywords'] ?? $defaults['validate_keywords'] ) );
		$san['bulk_per_page']      = max( 1, (int) ( $raw['bulk_per_page'] ?? $defaults['bulk_per_page'] ) );
		return $san;
	}

	public static function field_checkbox( array $args ) : void {
		$options = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts = wp_parse_args( $options, $defaults );
		$key = $args['key'];
		$checked = ! empty( $opts[ $key ] );
		echo '<label><input type="checkbox" name="nexaro_llms_options[' . esc_attr( $key ) . ']" value="1" ' . checked( $checked, true, false ) . '> ' . esc_html( $args['label'] ) . '</label>';
	}

	public static function field_text( array $args ) : void {
		$options = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts = wp_parse_args( $options, $defaults );
		$key = $args['key'];
		$val = isset( $opts[ $key ] ) ? (string) $opts[ $key ] : '';
		echo '<input type="text" class="regular-text" name="nexaro_llms_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $args['placeholder'] ?? '' ) . '">';
		if ( ! empty( $args['help'] ) ) {
			echo '<p class="description">' . esc_html( $args['help'] ) . '</p>';
		}
	}

	public static function field_number( array $args ) : void {
		$options = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts = wp_parse_args( $options, $defaults );
		$key = $args['key'];
		$val = isset( $opts[ $key ] ) ? (int) $opts[ $key ] : 0;
		$min = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$max = isset( $args['max'] ) ? (int) $args['max'] : 9999;
		$step = isset( $args['step'] ) ? (int) $args['step'] : 1;
		echo '<input type="number" class="small-text" name="nexaro_llms_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '">';
	}

	public static function render_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap" id="nx-admin">';
		echo '<h1>' . esc_html__( 'Nexaro LLM', 'nexaro-llms' ) . '</h1>';
		echo '<div class="nx-card">';
		echo '<form method="post" action="options.php">';
		settings_fields( 'nexaro-llms' );
		do_settings_sections( 'nexaro-llms' );
		submit_button();
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	public static function render_bulk_page() : void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$options = get_option( 'nexaro_llms_options', [] );
		$defaults = \nexaro_llms_default_options();
		$opts = wp_parse_args( $options, $defaults );
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per   = (int) $opts['bulk_per_page'];
		$q = new \WP_Query( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => $per,
			'paged'          => $paged,
		] );
		echo '<div class="wrap" id="nx-admin">';
		echo '<h1>' . esc_html__( 'Bulk manager', 'nexaro-llms' ) . '</h1>';
		echo '<div class="nx-card">';
		echo '<table class="nx-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Title', 'nexaro-llms' ) . '</th>';
		echo '<th>' . esc_html__( 'Summary', 'nexaro-llms' ) . '</th>';
		echo '<th>' . esc_html__( 'Endpoints', 'nexaro-llms' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) { $q->the_post();
				$post_id = get_the_ID();
				$summary = (string) get_post_meta( $post_id, '_nexaro_llms_summary', true );
				$has = trim( $summary ) !== '';
				$permalink = get_permalink( $post_id );
				$txt = trailingslashit( $permalink ) . 'llms.txt';
				$json = trailingslashit( $permalink ) . 'llms.json';
				echo '<tr>';
				echo '<td><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( get_the_title() ) . '</a></td>';
				echo '<td>' . ( $has ? '<span class="nx-badge ok">' . esc_html__( 'Present', 'nexaro-llms' ) . '</span> (' . esc_html( strlen( $summary ) ) . ')</td>' : '<span class="nx-badge bad">' . esc_html__( 'Missing', 'nexaro-llms' ) . '</span>' ) . '</td>';
				echo '<td><code class="code">' . esc_html( $txt ) . '</code>';
				if ( ! empty( $opts['enable_json'] ) ) {
					echo '<br><code class="code">' . esc_html( $json ) . '</code>';
				}
				echo '</td>';
				echo '</tr>';
			}
			wp_reset_postdata();
		} else {
			echo '<tr><td colspan="3">' . esc_html__( 'No pages found.', 'nexaro-llms' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$total = (int) $q->found_posts;
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<p>';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg( [ 'paged' => $i ], menu_page_url( 'nexaro-llms-bulk', false ) );
				if ( $i === $paged ) {
					echo '<span class="nx-badge">' . esc_html( (string) $i ) . '</span> ';
				} else {
					echo '<a class="nx-badge" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
				}
			}
			echo '</p>';
		}
		echo '</div>';
		echo '</div>';
	}

	public static function render_docs_page() : void {
		echo '<div class="wrap" id="nx-admin">';
		echo '<h1>' . esc_html__( 'Documentation', 'nexaro-llms' ) . '</h1>';
		$default = '<div class="nx-card"><h2>' . esc_html__( 'Getting Started', 'nexaro-llms' ) . '</h2><p class="description">' . esc_html__( 'Edit a page and add an LLM summary. Then access /llms.txt on that page URL. Configure settings under the Nexaro LLM menu.', 'nexaro-llms' ) . '</p></div>';
		$custom = apply_filters( 'nexaro_llms_docs_html', $default );
		echo wp_kses_post( $custom );
		echo '</div>';
	}

	public static function stopwords() : array {
		$en = [ 'the','a','an','and','or','but','if','then','else','when','at','by','for','from','in','into','of','on','to','with','is','are','was','were','be','being','been','it','its','as','that','this','these','those','i','you','he','she','they','we','them','him','her','us','our','your','my','mine','ours','yours','their','theirs','about','across','after','again','against','all','almost','alone','along','already','also','although','always','among','and','another','any','anybody','anyone','anything','anywhere','both','each','either','enough','etc','even','ever','every','everybody','everyone','everything','everywhere','few','fewer','fewest','following','former','forward','found','further','had','has','have','having','here','how','however','just','less','many','may','me','more','most','mostly','much','must','near','need','needs','neither','never','no','nobody','none','noone','nor','not','nothing','now','nowhere','once','one','only','other','others','otherwise','over','per','perhaps','quite','rather','really','same','seem','seems','seemed','seeming','seems','several','she','should','since','so','some','somebody','someone','something','somewhere','still','such','take','takes','than','that','the','their','theirs','them','themselves','then','there','therefore','these','they','thing','things','think','thinks','this','those','through','thus','together','too','toward','towards','under','until','up','upon','us','use','used','uses','using','very','via','well','what','whatever','when','whenever','where','wherever','whether','which','while','who','whoever','whom','whose','why','will','would','yet','you','your','yours','yourself','yourselves' ];
		$fa = [ 'و','در','به','از','که','این','آن','ها','را','با','برای','تا','یا','اما','اگر','نه','هر','بر','می','شود','شود','بود','بودن','هست','نیست','هیچ','چون','چه','چگونه','چرا','یکی','دو','سه','چهار','پنج','شش','هفت','هشت','نه','ده','من','تو','او','ما','شما','ایشان','وی','خود','خویش','ای','های','باشد','داشت','داشتن','هم','همه','همین','اکنون','کنون','زیرا','ضمن','روی','زیر','بین','بدون','بسیار','کم','زیاد','همچنین','چند','حتی','قبل','بعد','هنوز','دیگر','نیز' ];
		return array_values( array_unique( array_merge( $en, $fa ) ) );
	}
}