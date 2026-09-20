<?php
/**
 * Run against an existing WordPress core checkout:
 * php .github/tests/test-real-wp-hook.php /path/to/wordpress
 */
$core = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : '';
if ( ! is_file( $core . '/wp-includes/plugin.php' ) ) {
    fwrite( STDERR, "Pass the path to a WordPress core checkout.\n" );
    exit( 2 );
}
define( 'ABSPATH', $core . '/' );
define( 'WPINC', 'wp-includes' );
$fixture = tempnam( sys_get_temp_dir(), 'dopn-hook-' );
if ( false === $fixture ) {
    fwrite( STDERR, "Could not create a temporary plugin fixture.\n" );
    exit( 2 );
}
unlink( $fixture );
mkdir( $fixture . '/includes', 0700, true );
copy( dirname( __DIR__, 2 ) . '/includes/class-dopn-notice-collector.php', $fixture . '/includes/class-dopn-notice-collector.php' );
register_shutdown_function( static function () use ( $fixture ) {
    unlink( $fixture . '/includes/class-dopn-notice-collector.php' );
    rmdir( $fixture . '/includes' );
    rmdir( $fixture );
} );
// Keep callbacks in this test file outside the plugin directory, as they are
// on a real site. Otherwise ownership attribution would exclude the fixture.
define( 'DOPN_PLUGIN_DIR', $fixture . '/' );
require ABSPATH . WPINC . '/plugin.php';
require DOPN_PLUGIN_DIR . 'includes/class-dopn-notice-collector.php';

function is_admin() { return true; }
function is_network_admin() { return 'network' === $GLOBALS['context']; }
function is_user_admin() { return 'user' === $GLOBALS['context']; }
function get_current_user_id() { return 1; }
function get_user_meta() { return ''; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s ) { return $s; }
function _n( $singular, $plural, $count ) { return 1 === $count ? $singular : $plural; }
function number_format_i18n( $n ) { return number_format( $n ); }

function dopn_real_note( $value = '' ) {
    $GLOBALS['events'][] = 'first:' . current_filter() . ':' . $value;
    echo '<div class="notice">FIRST</div>';
}
function dopn_real_second( $value = '' ) {
    $GLOBALS['events'][] = 'second:' . current_filter() . ':' . $value;
    echo '<div class="notice">SECOND</div>';
}
function dopn_real_zero() {
    $GLOBALS['events'][] = 'zero:' . func_num_args();
    echo '<div class="notice">ZERO</div>';
}
function dopn_real_exempt() {
    $GLOBALS['events'][] = 'exempt:' . current_filter();
    echo '<div class="notice">EXEMPT</div>';
}
function dopn_real_late() {
    $GLOBALS['events'][] = 'late:' . current_filter();
    echo '<div class="notice">LATE</div>';
}
function dopn_real_register_late() {
    $GLOBALS['events'][] = 'register:' . current_filter();
    add_action( current_filter(), 'dopn_real_late', 30 );
}
function dopn_real_remove_second() {
    $GLOBALS['events'][] = 'remove:' . current_filter();
    $GLOBALS['removed'] = remove_action( current_filter(), 'dopn_real_second', 10 );
}

class DOPN_Real_Methods {
    public static function static_notice() { $GLOBALS['events'][] = 'static:' . current_filter(); echo '<div class="notice">STATIC</div>'; }
    public function instance_notice() { $GLOBALS['events'][] = 'instance:' . current_filter(); echo '<div class="notice">INSTANCE</div>'; }
    public function __invoke() { $GLOBALS['events'][] = 'invoke:' . current_filter(); echo '<div class="notice">INVOKE</div>'; }
}

$failed = 0;
function check_real( $label, $condition ) {
    global $failed;
    echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . "\n";
    $failed += $condition ? 0 : 1;
}

foreach ( array( 'site' => 'admin_notices', 'network' => 'network_admin_notices', 'user' => 'user_admin_notices' ) as $context => $notice_hook ) {
    $GLOBALS['context'] = $context;
    $GLOBALS['events'] = array();
    $GLOBALS['wp_filter'] = array();
    $GLOBALS['removed'] = false;
    $methods = new DOPN_Real_Methods();
    add_filter( 'dopn_collapse_notice', static function ( $collapse, $source, $hook, $priority, $callback ) {
        return 'dopn_real_exempt' === $callback ? false : $collapse;
    }, 10, 5 );
    add_action( 'in_admin_header', 'dopn_real_zero', -1 );
    add_action( 'in_admin_header', 'dopn_real_exempt', 0 );
    add_action( 'in_admin_header', 'dopn_real_note', 5, 1 );
    add_action( $notice_hook, 'dopn_real_exempt', 9, 0 );
    add_action( $notice_hook, 'dopn_real_note', 10, 1 );
    add_action( $notice_hook, 'dopn_real_second', 10, 1 );
    add_action( $notice_hook, 'dopn_real_zero', 11, 0 );
    add_action( $notice_hook, array( 'DOPN_Real_Methods', 'static_notice' ), 12 );
    add_action( $notice_hook, array( $methods, 'instance_notice' ), 13 );
    add_action( $notice_hook, $methods, 14 );
    add_action( $notice_hook, static function () { $GLOBALS['events'][] = 'closure:' . current_filter(); echo '<div class="notice">CLOSURE</div>'; }, 15 );
    add_action( 'all_admin_notices', 'dopn_real_note', 10, 1 );
    $collector = new DOPN_Notice_Collector();
    $collector->init();
    ob_start();
    do_action( 'in_admin_header', 'header-value' );
    $collector->capture(); // Repeated scans must not wrap already wrapped callbacks.
    do_action( $notice_hook, 'notice-value' );
    do_action( 'all_admin_notices', 'all-value' );
    $out = ob_get_clean();
    $panel = strpos( $out, 'dopn-notices__panel' );
    check_real( "$context panel and exempt position", false !== $panel && strpos( $out, 'EXEMPT' ) < $panel && strpos( $out, 'SECOND' ) > $panel );
    check_real( "$context original hook arguments", in_array( 'first:' . $notice_hook . ':notice-value', $GLOBALS['events'], true ) && in_array( 'first:all_admin_notices:all-value', $GLOBALS['events'], true ) && in_array( 'first:in_admin_header:header-value', $GLOBALS['events'], true ) );
    check_real( "$context zero arguments and callback types", in_array( 'zero:0', $GLOBALS['events'], true ) && in_array( 'static:' . $notice_hook, $GLOBALS['events'], true ) && in_array( 'instance:' . $notice_hook, $GLOBALS['events'], true ) && in_array( 'invoke:' . $notice_hook, $GLOBALS['events'], true ) && in_array( 'closure:' . $notice_hook, $GLOBALS['events'], true ) );
    check_real( "$context same priority order and single execution", array_search( 'first:' . $notice_hook . ':notice-value', $GLOBALS['events'], true ) < array_search( 'second:' . $notice_hook . ':notice-value', $GLOBALS['events'], true ) && 1 === substr_count( $out, 'SECOND' ) );
    check_real( "$context current header priority stays visible", strpos( $out, 'EXEMPT' ) < $panel && 2 === substr_count( $out, 'EXEMPT' ) );
}

$GLOBALS['context'] = 'site';
$GLOBALS['events'] = array();
$GLOBALS['wp_filter'] = array();
add_action( 'admin_notices', 'dopn_real_remove_second', 9 );
add_action( 'admin_notices', 'dopn_real_second', 10 );
add_action( 'admin_notices', 'dopn_real_register_late', 11 );
$collector = new DOPN_Notice_Collector();
$collector->init();
ob_start();
do_action( 'in_admin_header' );
do_action( 'admin_notices' );
do_action( 'all_admin_notices' );
$out = ob_get_clean();
check_real( 'remove_action still removes original registration', $GLOBALS['removed'] && false === strpos( $out, 'SECOND' ) );
check_real( 'callback added during hook runs once', 1 === substr_count( $out, 'LATE' ) && in_array( 'late:admin_notices', $GLOBALS['events'], true ) );
echo "$failed failures\n";
exit( $failed ? 1 : 0 );
