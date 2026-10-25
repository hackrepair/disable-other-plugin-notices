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
	wp_enqueue_style( 'disable-other-plugin-notices-brand', plugins_url( 'assets/brand/thrg-brand.css', DOPN_PLUGIN_FILE ), array(), DOPN_VERSION );
	wp_enqueue_style( 'disable-other-plugin-notices-brand-details', plugins_url( 'assets/brand/thrg-details.css', DOPN_PLUGIN_FILE ), array( 'disable-other-plugin-notices-brand' ), DOPN_VERSION );
	wp_enqueue_style( 'dopn-plugin-details', plugins_url( 'assets/css/dopn-plugin-details.css', DOPN_PLUGIN_FILE ), array(), DOPN_VERSION );
	$result->version                        = DOPN_VERSION;
	$result->sections['other_THRG_plugins'] = '<div class="dopn-toolkit"><p class="dopn-toolkit-intro">More tools from The Hack Repair Guy</p><p>Five focused ways to look after your WordPress site. Use only what you need.</p><div class="dopn-toolkit-card"><h3>Group Password Reset</h3><p>Reset passwords by role, choose who to exclude, and optionally send secure reset links.</p><p><a href="https://github.com/hackrepair/group-password-reset" target="_blank" rel="noopener noreferrer">Explore Group Password Reset &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Plugin Detective</h3><p>Find the plugin behind a conflict, with recovery tools for when your dashboard is unavailable.</p><p><a href="https://github.com/hackrepair/hackrepair-plugin-detective" target="_blank" rel="noopener noreferrer">Explore Plugin Detective &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Site Inspector</h3><p>Review security-related observations, keep inspection reports, and see what changed.</p><p><a href="https://github.com/hackrepair/thrg-site-inspector" target="_blank" rel="noopener noreferrer">Explore Site Inspector &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Post Update Email Notifier</h3><p>Notify selected team members when published content changes, with configurable update emails.</p><p><a href="https://github.com/tvcnet/hackrepair-post-update-email-notifier" target="_blank" rel="noopener noreferrer">Explore Post Update Email Notifier &rarr;</a></p></div><div class="dopn-toolkit-card"><h3>Plugin Archiver</h3><p>Move unused plugins out of the Plugins list without deleting them, and restore their files when needed.</p><p><a href="https://wordpress.org/plugins/hackrepair-plugin-archiver/" target="_blank" rel="noopener noreferrer">Explore Plugin Archiver &rarr;</a></p></div><p class="dopn-toolkit-note">Each plugin works independently. Links open in a new tab.</p></div>';
	return $result;
}
add_filter( 'plugins_api', 'dopn_companion_plugin_info', 100, 3 );

/**
 * Provide bundled details when remote information is unavailable.
 *
 * @param mixed  $result Existing result.
 * @param string $action API action.
 * @param object $args Request arguments.
 * @return mixed
 */
function dopn_local_plugin_info( $result, $action = null, $args = null ) {
	if ( false !== $result || 'plugin_information' !== $action || ! is_object( $args ) ||
		! isset( $args->slug ) || 'disable-other-plugin-notices' !== $args->slug ) {
		return $result;
	}

	$readme_file = DOPN_PLUGIN_DIR . 'readme.txt';
	if ( ! is_readable( $readme_file ) || ! class_exists( 'PucReadmeParser' ) ) {
		return $result;
	}

	$parser = new \PucReadmeParser();
	$readme = $parser->parse_readme( $readme_file );
	if ( empty( $readme['sections'] ) ) {
		return $result;
	}

	// The readme parser files the FAQ under its long heading; WordPress's
	// details window calls that tab "faq".
	$wanted = array(
		'description'                => 'description',
		'installation'               => 'installation',
		'frequently_asked_questions' => 'faq',
		'changelog'                  => 'changelog',
	);

	$sections = array();
	foreach ( $wanted as $from => $to ) {
		if ( ! empty( $readme['sections'][ $from ] ) ) {
			$sections[ $to ] = $readme['sections'][ $from ];
		}
	}

	$info = new \stdClass();

	$info->external     = true;
	$info->name         = "The Hack Repair Guy's Disable Other Plugin Notices";
	$info->slug         = 'disable-other-plugin-notices';
	$info->version      = DOPN_VERSION;
	$info->author       = '<a href="https://hackrepair.com/">Jim Walker, The Hack Repair Guy</a>';
	$info->homepage     = 'https://github.com/hackrepair/disable-other-plugin-notices';
	$info->requires     = isset( $readme['requires_at_least'] ) ? $readme['requires_at_least'] : '';
	$info->tested       = isset( $readme['tested_up_to'] ) ? $readme['tested_up_to'] : '';
	$info->requires_php = isset( $readme['requires_php'] ) ? $readme['requires_php'] : '';
	$info->last_updated = gmdate( 'Y-m-d H:i:s', (int) filemtime( DOPN_PLUGIN_FILE ) );
	$info->sections     = $sections;
	$info->banners      = array(
		'low'  => DOPN_PLUGIN_URL . 'assets/banner-772x250.png',
		'high' => DOPN_PLUGIN_URL . 'assets/banner-1544x500.png',
	);
	$info->icons        = array(
		'1x' => DOPN_PLUGIN_URL . 'assets/icon-128x128.png',
		'2x' => DOPN_PLUGIN_URL . 'assets/icon-256x256.png',
	);

	return $info;
}
add_filter( 'plugins_api', 'dopn_local_plugin_info', 20, 3 );
