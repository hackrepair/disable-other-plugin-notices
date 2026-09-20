<?php
/**
 * Behaviour test for DOPN_Notice_Collector.
 *
 * Runs the real collector against a simulated WordPress hook system, so the
 * attribution logic can be checked without a WordPress install or a database.
 *
 * Usage, from anywhere:
 *
 *     php .github/tests/test-notice-collector.php
 *     php .github/tests/test-notice-collector.php /path/to/another/copy-of-the-plugin
 *
 * Exits 0 when every assertion passes, 1 otherwise. The simulated site is built
 * in a temporary directory and removed afterwards; nothing in the plugin is
 * modified.
 *
 * @package DisableOtherPluginNotices
 */

$plugin_root = isset( $argv[1] )
	? rtrim( $argv[1], '/' )
	: dirname( __DIR__, 2 );

$includes = $plugin_root . '/includes';

if ( ! is_dir( $includes ) ) {
	fwrite( STDERR, "Could not find the plugin's includes directory at {$includes}\n" );
	fwrite( STDERR, "Pass the plugin directory as the first argument if it lives elsewhere.\n" );
	exit( 1 );
}

/**
 * Removes a directory tree.
 *
 * @param string $path Directory to remove.
 * @return void
 */
function dopn_test_rmdir( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $path );
}

$sim = sys_get_temp_dir() . '/dopn-sim-' . getmypid();
dopn_test_rmdir( $sim );

$dirs = array(
	'/wp-admin/includes',
	'/wp-includes',
	'/wp-content/plugins/acme',
	'/wp-content/plugins/promo',
	'/wp-content/plugins/disable-other-plugin-notices/includes',
	'/wp-content/themes/twenty',
	'/wp-content/mu-plugins',
);

foreach ( $dirs as $dir ) {
	mkdir( $sim . $dir, 0777, true );
}

register_shutdown_function(
	function () use ( $sim ) {
		dopn_test_rmdir( $sim );
	}
);

// The real plugin source under test.
foreach ( glob( $includes . '/*.php' ) as $file ) {
	copy( $file, $sim . '/wp-content/plugins/disable-other-plugin-notices/includes/' . basename( $file ) );
}

// Notice sources, one per kind of origin the collector has to tell apart.
$fixtures = array(
	'/wp-admin/includes/update.php'                                              => '<?php function sim_core_update_nag() { echo "<div class=\"update-nag\">CORE: WordPress update available.</div>\n"; }',
	'/wp-includes/functions.php'                                                 => '<?php function sim_core_inc_notice() { echo "<div class=\"notice notice-error\">CORE: Your site is experiencing a technical issue.</div>\n"; }',
	'/wp-content/plugins/acme/acme.php'                                          => '<?php
function acme_review_nag() { echo "<div class=\"notice notice-info\">ACME: Enjoying Acme? Leave a review!</div>\n"; }
class Acme_Admin {
	public static function license_nag() { echo "<div class=\"notice notice-warning\">ACME: Your license expires soon.</div>\n"; }
	public function wizard_nag() { echo "<div class=\"notice notice-success is-dismissible\"><p>ACME: Run the setup wizard.</p></div>\n"; }
	public function greedy_nag() { ob_start(); echo "<div class=\"notice\">ACME: callback that leaks an open buffer.</div>\n"; }
}',
	'/wp-content/themes/twenty/functions.php'                                    => '<?php function sim_theme_closure() { return function () { echo "<div class=\"notice notice-info\">THEME: Install our companion plugin.</div>\n"; }; }',
	'/wp-content/mu-plugins/host.php'                                            => '<?php function host_mu_notice() { echo "<div class=\"notice notice-warning\">HOST: Scheduled maintenance Sunday.</div>\n"; }',
	'/wp-content/plugins/disable-other-plugin-notices/includes/self-notice.php'  => '<?php function dopn_self_notice() { echo "<div class=\"notice notice-info\">SELF: notice from this plugin itself.</div>\n"; }',
	'/wp-content/plugins/promo/promo.php'                                        => '<?php function promo_banner() { echo "<div id=\"promo-banner\">PROMO: Go Pro, Go Limitless!</div>\n"; }',
	'/wp-content/plugins/acme/security.php'                                      => '<?php
function acme_security_alert() { $GLOBALS["dopn_events"][] = "alert:" . current_filter(); echo "<div class=\"notice notice-error\">SECURITY: Firewall disabled.</div>\n"; }
function acme_security_promo() { $GLOBALS["dopn_events"][] = "promo:" . current_filter(); echo "<div class=\"notice notice-info\">SECURITY: Upgrade now.</div>\n"; }
function acme_context_notice( $value ) { $GLOBALS["dopn_events"][] = "context:" . current_filter() . ":" . $value; echo "<div class=\"notice\">CONTEXT: " . $value . "</div>\n"; }
function acme_zero_notice() { $GLOBALS["dopn_events"][] = "zero:" . func_num_args(); echo "<div class=\"notice\">ZERO arguments</div>\n"; }
function acme_following_notice() { $GLOBALS["dopn_events"][] = "following:" . current_filter(); echo "<div class=\"notice\">FOLLOWING</div>\n"; }
function acme_throwing_notice() { echo "<div class=\"notice\">PARTIAL OUTPUT</div>\n"; throw new RuntimeException( "simulated failure" ); }
',
	'/wp-content/plugins/acme/invoke-base.php'                                   => '<?php class Acme_Invoke_Base { public function __invoke() { echo "INVOKE"; } }',
	'/wp-content/plugins/acme/invoke-child.php'                                  => '<?php class Acme_Invoke_Child extends Acme_Invoke_Base { public function own_notice() { echo "OWN"; } }',
	'/wp-admin/includes/screen-meta.php'                                         => '<?php function sim_core_header_widget() { echo "<div id=\"core-header-widget\">CORE: screen meta links.</div>\n"; }',
);

foreach ( $fixtures as $path => $body ) {
	file_put_contents( $sim . $path, $body . "\n" );
}

define( 'ABSPATH', $sim . '/' );
define( 'WPINC', 'wp-includes' );
define( 'DOPN_VERSION', '1.0.0' );
define( 'DOPN_PLUGIN_DIR', $sim . '/wp-content/plugins/disable-other-plugin-notices/' );
define( 'DOPN_PLUGIN_URL', 'http://example.test/wp-content/plugins/disable-other-plugin-notices/' );

/**
 * Stand-in for the WordPress hook container.
 */
class WP_Hook {
	public $callbacks = array();
}

$wp_filter = array();
$user_meta = array();
$enqueued  = array();
$localized = array();
$wp_current_filter = array();

function sim_id( $cb ) {
	if ( is_string( $cb ) ) {
		return $cb;
	}
	if ( $cb instanceof Closure ) {
		return spl_object_hash( $cb );
	}
	if ( is_array( $cb ) ) {
		$obj = is_object( $cb[0] ) ? spl_object_hash( $cb[0] ) : $cb[0];
		return $obj . '::' . $cb[1];
	}
	if ( is_object( $cb ) ) {
		return spl_object_hash( $cb ) . '::__invoke';
	}
	return '';
}
function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
	global $wp_filter;
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		$wp_filter[ $hook ] = new WP_Hook();
	}
	$wp_filter[ $hook ]->callbacks[ $priority ][ sim_id( $cb ) ] = array(
		'function'      => $cb,
		'accepted_args' => $args,
	);
	ksort( $wp_filter[ $hook ]->callbacks );
	return true;
}
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	return add_action( $hook, $cb, $priority, $args );
}
function remove_action( $hook, $cb, $priority = 10 ) {
	global $wp_filter;
	$id = sim_id( $cb );
	if ( isset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] ) ) {
		unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
		return true;
	}
	return false;
}
function do_action( $hook, ...$args ) {
	global $wp_filter, $wp_current_filter;
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		return;
	}
	$wp_current_filter[] = $hook;
	/*
	 * Re-checks $wp_filter[ $hook ] right before invoking each callback, rather
	 * than iterating a snapshot taken once up front. Real WP_Hook::do_action()
	 * iterates its own $callbacks property directly, so a remove_action() call
	 * made by an earlier callback on this same hook (this class's own capture()
	 * removing a later-priority in_admin_header callback, for example) keeps
	 * that removed callback from firing later in this same pass. A one-time
	 * snapshot would miss that and call it anyway.
	 */
	$priorities = array_keys( $wp_filter[ $hook ]->callbacks );
	sort( $priorities, SORT_NUMERIC );
	foreach ( $priorities as $priority ) {
		if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
			continue;
		}
		$ids = array_keys( $wp_filter[ $hook ]->callbacks[ $priority ] );
		foreach ( $ids as $id ) {
			if ( ! isset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] ) ) {
				continue;
			}
			$entry = $wp_filter[ $hook ]->callbacks[ $priority ][ $id ];
			call_user_func_array( $entry['function'], array_slice( $args, 0, $entry['accepted_args'] ) );
		}
	}
	array_pop( $wp_current_filter );
}
function current_filter() {
	global $wp_current_filter;
	return empty( $wp_current_filter ) ? '' : end( $wp_current_filter );
}
function apply_filters( $hook, $value, ...$args ) {
	global $wp_filter;
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		return $value;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$passed = array_slice( array_merge( array( $value ), $args ), 0, $entry['accepted_args'] );
			$value = call_user_func_array( $entry['function'], $passed );
		}
	}
	return $value;
}
function is_admin() {
	return true;
}
function is_network_admin() {
	return false;
}
function is_user_admin() {
	return false;
}
function wp_doing_ajax() {
	return false;
}
function get_current_user_id() {
	return 1;
}
function get_user_meta( $id, $key, $single = false ) {
	global $user_meta;
	return isset( $user_meta[ $key ] ) ? $user_meta[ $key ] : '';
}
function update_user_meta( $id, $key, $value ) {
	global $user_meta;
	$user_meta[ $key ] = $value;
	return true;
}
function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	return preg_replace( '|(?<=.)/+|', '/', $path );
}
function trailingslashit( $string ) {
	return rtrim( $string, '/\\' ) . '/';
}
function esc_html( $t ) {
	return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $t ) {
	return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' );
}
function esc_html__( $t, $d = '' ) {
	return esc_html( $t );
}
function esc_html_e( $t, $d = '' ) {
	echo esc_html( $t );
}
function _n( $s, $p, $n, $d = '' ) {
	return 1 === $n ? $s : $p;
}
function number_format_i18n( $n ) {
	return number_format( $n );
}
function checked( $a, $b = true, $display = true ) {
	return $a === $b ? ' checked="checked"' : '';
}
function sanitize_text_field( $s ) {
	return trim( wp_strip_all_tags( $s ) );
}
function wp_strip_all_tags( $s ) {
	return strip_tags( $s );
}
function wp_unslash( $s ) {
	return $s;
}
function wp_verify_nonce( $nonce, $action ) {
	return 1;
}
function current_user_can( $capability ) {
	return true;
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	global $enqueued;
	$enqueued[] = $handle;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	global $enqueued;
	$enqueued[] = $handle;
}
function wp_localize_script( $handle, $object_name, $data ) {
	global $localized;
	$localized[ $handle ] = $data;
}
function __( $text, $domain = '' ) {
	return $text;
}

require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/screen-meta.php';
require_once ABSPATH . 'wp-includes/functions.php';
require_once ABSPATH . 'wp-content/plugins/acme/acme.php';
require_once ABSPATH . 'wp-content/plugins/promo/promo.php';
require_once ABSPATH . 'wp-content/plugins/acme/security.php';
require_once ABSPATH . 'wp-content/plugins/acme/invoke-base.php';
require_once ABSPATH . 'wp-content/plugins/acme/invoke-child.php';
require_once ABSPATH . 'wp-content/themes/twenty/functions.php';
require_once ABSPATH . 'wp-content/mu-plugins/host.php';
require_once DOPN_PLUGIN_DIR . 'includes/self-notice.php';
require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-notice-collector.php';
require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-screen-option.php';

$results = array();

/**
 * Records one assertion.
 *
 * @param string $label  What is being asserted.
 * @param bool   $passed Result.
 * @return void
 */
function dopn_assert( $label, $passed ) {
	global $results;
	$results[ $label ] = (bool) $passed;
}

/* ---------------------------------------------------------------------------
 * Case 1: grouping on, every kind of notice source present.
 * ------------------------------------------------------------------------ */

$acme = new Acme_Admin();

add_action( 'admin_notices', 'sim_core_update_nag', 3 );
add_action( 'admin_notices', 'sim_core_inc_notice', 5 );
add_action( 'admin_notices', 'acme_review_nag', 10 );
add_action( 'admin_notices', array( 'Acme_Admin', 'license_nag' ), 10 );
add_action( 'admin_notices', array( $acme, 'wizard_nag' ), 11 );
add_action( 'admin_notices', array( $acme, 'greedy_nag' ), 11 );
add_action( 'admin_notices', 'host_mu_notice', 12 );
add_action( 'admin_notices', 'dopn_self_notice', 13 );
add_action( 'admin_notices', 'time', 14 ); // Internal PHP function: no source file.
add_action( 'all_admin_notices', sim_theme_closure(), 10 );
// A promo banner printed straight into in_admin_header instead of a notice
// hook -- the pattern real plugins like Elementor use to render above the
// notice area, and the gap this fix closes. Priority 5 puts it after DOPN's
// own priority-0 listener, so it is only skipped if that listener's removal
// actually takes effect on the same, still-running hook.
add_action( 'in_admin_header', 'sim_core_header_widget', -1 ); // Core: must stay untouched even on this hook.
add_action( 'in_admin_header', 'promo_banner', 5 );

$collector = new DOPN_Notice_Collector();
$collector->init();

$depth_before = ob_get_level();

ob_start();
do_action( 'admin_enqueue_scripts' );
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$out = ob_get_clean();

$panel = strpos( $out, 'dopn-notices' );

dopn_assert( 'core update nag still printed', false !== strpos( $out, 'CORE: WordPress update available' ) );
dopn_assert( 'core wp-includes notice still printed', false !== strpos( $out, 'CORE: Your site is experiencing' ) );
dopn_assert( 'plugin function notice grouped', false !== strpos( $out, 'ACME: Enjoying Acme' ) );
dopn_assert( 'plugin static method grouped', false !== strpos( $out, 'ACME: Your license expires' ) );
dopn_assert( 'plugin object method grouped', false !== strpos( $out, 'ACME: Run the setup wizard' ) );
dopn_assert( 'theme closure grouped', false !== strpos( $out, 'THEME: Install our companion' ) );
dopn_assert( 'mu-plugin notice grouped', false !== strpos( $out, 'HOST: Scheduled maintenance' ) );
dopn_assert( 'own notice left alone', false !== strpos( $out, 'SELF: notice from this plugin' ) );
dopn_assert( 'panel rendered', false !== $panel );
dopn_assert( 'panel reports seven notices', false !== strpos( $out, '>7</span>' ) );
dopn_assert( 'panel exposes server count for late banners and accessible count text', false !== strpos( $out, 'data-dopn-count="7"' ) && false !== strpos( $out, 'dopn-notices__count-text' ) );
dopn_assert( 'in_admin_header banner grouped', false !== strpos( $out, 'PROMO: Go Pro, Go Limitless' ) );
dopn_assert( 'in_admin_header banner printed only once', 1 === substr_count( $out, 'PROMO: Go Pro, Go Limitless' ) );
dopn_assert( 'in_admin_header banner sits inside the panel', strpos( $out, 'PROMO: Go Pro, Go Limitless' ) > $panel );
dopn_assert( 'core in_admin_header widget still printed', false !== strpos( $out, 'CORE: screen meta links' ) );
dopn_assert( 'core in_admin_header widget sits above the panel', strpos( $out, 'CORE: screen meta links' ) < $panel );
dopn_assert( 'panel collapsed by default', false === strpos( $out, 'dopn-notices__panel open' ) );
dopn_assert( 'stylesheet enqueued', in_array( 'dopn-admin', $enqueued, true ) );
dopn_assert( 'banner watcher script enqueued', in_array( 'dopn-banner-watcher', $enqueued, true ) );
dopn_assert( 'banner watcher localized data present', isset( $localized['dopn-banner-watcher'] ) );
dopn_assert( 'banner watcher sees grouping enabled', ! empty( $localized['dopn-banner-watcher']['groupingEnabled'] ) );
dopn_assert( 'banner watcher gets the default Elementor selector', in_array( '#e-conversion-banner', $localized['dopn-banner-watcher']['selectors'], true ) );
dopn_assert( 'banner watcher gets localized strings', ! empty( $localized['dopn-banner-watcher']['strings']['label'] ) );
dopn_assert( 'core notice sits above the panel', strpos( $out, 'CORE: WordPress update' ) < $panel );
dopn_assert( 'own notice sits above the panel', strpos( $out, 'SELF: notice' ) < $panel );
dopn_assert( 'grouped notice sits inside the panel', strpos( $out, 'ACME: Enjoying Acme' ) > $panel );
dopn_assert( 'leaked output buffer recovered', false !== strpos( $out, 'ACME: callback that leaks' ) );
dopn_assert( 'buffer depth restored', ob_get_level() === $depth_before );

/*
 * WordPress core's wp-admin/js/common.js runs, on every admin screen with a
 * .wp-header-end marker:
 *
 *   $( 'div.updated, div.error, div.notice' ).not( '.inline, .below-h2' )
 *       .insertAfter( $headerEnd );
 *
 * That selector matches anywhere in the document, panel or no panel, and
 * physically moves the matched element out of wherever it was sitting. This
 * check reproduces the selector in PHP against every class attribute inside
 * the panel and confirms each one that core would otherwise grab has been
 * armoured with `below-h2`, so grouped notices actually stay grouped once a
 * browser's admin JS has had a chance to run.
 */
$panel_region = substr( $out, $panel );
preg_match_all( '/class="([^"]*)"/', $panel_region, $class_matches );

$relocatable_and_unguarded = 0;

foreach ( $class_matches[1] as $classes ) {
	$core_would_match = (bool) preg_match( '/(?:^|\s)(?:notice|updated|error)(?:\s|$)/', $classes );
	$is_guarded        = (bool) preg_match( '/(?:^|\s)below-h2(?:\s|$)/', $classes );

	if ( $core_would_match && ! $is_guarded ) {
		++$relocatable_and_unguarded;
	}
}

dopn_assert( 'panel contained at least one real notice element to check', count( $class_matches[1] ) > 0 );
dopn_assert( "no grouped notice is left exposed to WordPress core's relocation script", 0 === $relocatable_and_unguarded );

/* ---------------------------------------------------------------------------
 * Case 2: the user switched grouping off.
 * ------------------------------------------------------------------------ */

$user_meta['dopn_group_notices'] = '0';
$wp_filter                       = array();

add_action( 'admin_notices', 'sim_core_update_nag', 3 );
add_action( 'admin_notices', 'acme_review_nag', 10 );

$disabled = new DOPN_Notice_Collector();
$disabled->init();

$localized = array();

ob_start();
do_action( 'admin_enqueue_scripts' );
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$off = ob_get_clean();

dopn_assert( 'no panel when disabled', false === strpos( $off, 'dopn-notices' ) );
dopn_assert( 'plugin notice printed in place when disabled', false !== strpos( $off, 'ACME: Enjoying Acme' ) );
dopn_assert( 'core notice printed in place when disabled', false !== strpos( $off, 'CORE: WordPress update available' ) );
dopn_assert( 'screen option still offered when disabled', $disabled->found_third_party_notices() );
dopn_assert( 'banner watcher still loaded when disabled (so it can no-op client-side)', in_array( 'dopn-banner-watcher', $enqueued, true ) );
dopn_assert( 'banner watcher told grouping is off', empty( $localized['dopn-banner-watcher']['groupingEnabled'] ) );

/* ---------------------------------------------------------------------------
 * Case 3: nothing but core notices on the screen.
 * ------------------------------------------------------------------------ */

$user_meta = array();
$wp_filter = array();

add_action( 'admin_notices', 'sim_core_update_nag', 3 );

$core_only = new DOPN_Notice_Collector();
$core_only->init();

ob_start();
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$core = ob_get_clean();

dopn_assert( 'no panel on a core-only screen', false === strpos( $core, 'dopn-notices' ) );
dopn_assert( 'no screen option on a core-only screen', ! $core_only->found_third_party_notices() );
dopn_assert( 'core notice untouched on a core-only screen', false !== strpos( $core, 'CORE: WordPress update available' ) );

/* ---------------------------------------------------------------------------
 * Case 4: callback context, argument count, same-priority order and exact opt-out.
 * ------------------------------------------------------------------------ */
$wp_filter = array();
$dopn_events = array();
add_filter( 'dopn_collapse_notice', static function ( $collapse, $source, $hook, $priority, $callback ) {
	return 'acme_security_alert' === $callback ? false : $collapse;
}, 10, 5 );
add_action( 'admin_notices', 'acme_security_alert', 10, 0 );
add_action( 'admin_notices', 'acme_security_promo', 10, 0 );
add_action( 'admin_notices', 'acme_context_notice', 10, 1 );
add_action( 'admin_notices', 'acme_zero_notice', 10, 0 );
add_action( 'admin_notices', 'acme_following_notice', 10, 0 );
$compat = new DOPN_Notice_Collector();
$compat->init();
ob_start();
do_action( 'in_admin_header' );
do_action( 'admin_notices', 'sample-value' );
do_action( 'all_admin_notices' );
$compat_out = ob_get_clean();
$compat_panel = strpos( $compat_out, 'dopn-notices' );
dopn_assert( 'precise security callback stays outside panel', strpos( $compat_out, 'Firewall disabled' ) < $compat_panel );
dopn_assert( 'same-file promotional callback is grouped', strpos( $compat_out, 'Upgrade now' ) > $compat_panel );
dopn_assert( 'callback receives original hook and argument', in_array( 'context:admin_notices:sample-value', $dopn_events, true ) );
dopn_assert( 'zero accepted arguments honored', in_array( 'zero:0', $dopn_events, true ) );
dopn_assert( 'original same-priority order retained', $dopn_events === array( 'alert:admin_notices', 'promo:admin_notices', 'context:admin_notices:sample-value', 'zero:0', 'following:admin_notices' ) );
dopn_assert( 'each callback executes once', 5 === count( $dopn_events ) );
dopn_assert( 'grouped context callback markup appears inside panel', strpos( $compat_out, 'CONTEXT: sample-value' ) > $compat_panel );

/* Case 5: a pre-existing four-argument opt-out remains compatible. */
$wp_filter = array();
add_filter( 'dopn_collapse_notice', static function ( $collapse, $source, $hook, $priority ) {
	return 'admin_notices' === $hook && 10 === $priority ? false : $collapse;
}, 10, 4 );
add_action( 'admin_notices', 'acme_security_alert', 10, 0 );
$legacy = new DOPN_Notice_Collector();
$legacy->init();
ob_start();
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$legacy_out = ob_get_clean();
dopn_assert( 'four-argument legacy filter still excludes callback', false !== strpos( $legacy_out, 'Firewall disabled' ) && false === strpos( $legacy_out, 'dopn-notices' ) );

/* Case 6: partial markup survives a throwing callback and later notices run. */
$wp_filter = array();
add_action( 'admin_notices', 'acme_throwing_notice', 10 );
add_action( 'admin_notices', 'acme_following_notice', 11 );
$resilient = new DOPN_Notice_Collector();
$resilient->init();
ob_start();
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$resilient_out = ob_get_clean();
$resilient_panel = strpos( $resilient_out, 'dopn-notices' );
dopn_assert( 'partial output retained after callback exception', false !== $resilient_panel && strpos( $resilient_out, 'PARTIAL OUTPUT' ) > $resilient_panel );
dopn_assert( 'later callback still runs after exception', strpos( $resilient_out, 'FOLLOWING' ) > $resilient_panel );

/* Case 7: the same callable and source file reuse request-local lookup entries. */
$wp_filter = array();
add_action( 'admin_notices', 'acme_security_promo', 10 );
add_action( 'all_admin_notices', 'acme_security_promo', 10 );
add_action( 'admin_notices', 'acme_security_alert', 11 );
$cached = new DOPN_Notice_Collector();
$cached->init();
do_action( 'in_admin_header' );
$paths_property = new ReflectionProperty( DOPN_Notice_Collector::class, 'callback_paths' );
$paths_property->setAccessible( true );
$ownership_property = new ReflectionProperty( DOPN_Notice_Collector::class, 'source_ownership' );
$ownership_property->setAccessible( true );
$paths = $paths_property->getValue( $cached );
$ownership = $ownership_property->getValue( $cached );
dopn_assert( 'same callable has one reflection cache entry', 1 === count( array_filter( array_keys( $paths ), static function ( $key ) { return 'function:acme_security_promo' === $key; } ) ) );
dopn_assert( 'same source file has one ownership cache entry', 1 === count( array_filter( array_keys( $ownership ), static function ( $key ) { return false !== strpos( $key, '/acme/security.php' ); } ) ) );
$path_method = new ReflectionMethod( DOPN_Notice_Collector::class, 'callback_path' );
$path_method->setAccessible( true );
$invoke_child = new Acme_Invoke_Child();
$method_path = $path_method->invoke( $cached, array( $invoke_child, 'own_notice' ) );
$invoke_path = $path_method->invoke( $cached, $invoke_child );
dopn_assert( 'invokable object and its method keep distinct origins', false !== strpos( $method_path, '/invoke-child.php' ) && false !== strpos( $invoke_path, '/invoke-base.php' ) );

/* ------------------------------------------------------------------------ */

$failed = 0;

foreach ( $results as $label => $passed ) {
	printf( "%s  %s\n", $passed ? 'PASS' : 'FAIL', $label );

	if ( ! $passed ) {
		++$failed;
	}
}

printf( "\n%d assertions, %d failed\n", count( $results ), $failed );

exit( $failed > 0 ? 1 : 0 );
