<?php
/**
 * Plugin Name:       Disable Other Plugin Notices
 * Plugin URI:        https://github.com/hackrepair/disable-other-plugin-notices
 * Description:       Groups admin notices printed by other plugins and themes into one collapsed panel, while WordPress core notices stay exactly where they are.
 * Version:           2.3.0
 * Author:            The Hack Repair Guy
 * Author URI:        https://hackrepair.com/
 * Update URI:        https://github.com/hackrepair/disable-other-plugin-notices
 * Requires at least: 6.7
 * Tested up to:      8.3.0
 * Requires PHP:      7.4
 * Text Domain:       disable-other-plugin-notices
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DOPN_VERSION' ) ) {
    define( 'DOPN_VERSION', '2.3.0' );
}
if ( ! defined( 'DOPN_PLUGIN_FILE' ) ) {
    define( 'DOPN_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'DOPN_PLUGIN_DIR' ) ) {
    define( 'DOPN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DOPN_PLUGIN_URL' ) ) {
    define( 'DOPN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-notice-collector.php';
require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-screen-option.php';
require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-plugin.php';

$dopn_update_checker = DOPN_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

if ( is_readable( $dopn_update_checker ) ) {
    require_once $dopn_update_checker;
}
unset( $dopn_update_checker );

require_once DOPN_PLUGIN_DIR . 'includes/class-dopn-updater.php';

/**
 * Returns the shared plugin instance.
 *
 * @since 1.0.0
 *
 * @return DOPN_Plugin The plugin controller.
 */
function dopn_plugin() {
    return DOPN_Plugin::instance();
}

/**
 * Starts the plugin once all plugins are loaded.
 *
 * Booting on plugins_loaded keeps the notice scan predictable: every other
 * plugin has already been given the chance to register its admin notices by
 * the time the scan runs on in_admin_header. The update checker is started
 * here too so it also runs on cron, where is_admin() is false.
 *
 * @since 1.0.0
 *
 * @return void
 */
function dopn_bootstrap() {
    dopn_plugin()->boot_updates();
    dopn_plugin()->boot();
}
add_action( 'plugins_loaded', 'dopn_bootstrap' );
