<?php
/**
 * Companion plugins for the native View Details dialog.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append the companion guide only to this plugin's information.
 *
 * @param mixed  $result Plugin information or the original API result.
 * @param string $action Requested API action.
 * @param object $args API arguments.
 * @return mixed
 */
function dopn_companion_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || 'disable-other-plugin-notices' !== $args->slug || ! is_object( $result ) || ! isset( $result->sections ) || ! is_array( $result->sections ) ) {
		return $result;
	}
	wp_enqueue_style( 'dopn-plugin-details', plugins_url( 'assets/css/dopn-plugin-details.css', DOPN_PLUGIN_FILE ), array(), DOPN_VERSION );
	$result->version                        = DOPN_VERSION;
	$result->sections['other_THRG_plugins'] = '<div class="dopn-toolkit"><p class="dopn-toolkit-intro">More tools from The Hack Repair Guy</p><p>Three focused ways to look after your WordPress site. Use only what you need.</p><div class="dopn-toolkit-card"><h3>Group Password Reset</h3><p>Reset passwords by role, choose who to exclude, and send secure reset links when needed.</p><p><a href="https://github.com/hackrepair/group-password-reset" target="_blank" rel="noopener noreferrer">Explore Group Password Reset &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Plugin Detective</h3><p>Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.</p><p><a href="https://github.com/hackrepair/hackrepair-plugin-detective" target="_blank" rel="noopener noreferrer">Explore Plugin Detective &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Site Inspector</h3><p>Review security-related observations, keep inspection reports, and see what changed.</p><p><a href="https://github.com/hackrepair/thrg-site-inspector" target="_blank" rel="noopener noreferrer">Explore Site Inspector &rarr;</a></p></div><p class="dopn-toolkit-note">Each plugin works independently. Links open the project pages in a new tab.</p></div>';
	return $result;
}
add_filter( 'plugins_api', 'dopn_companion_plugin_info', 100, 3 );
