<?php
/**
 * Removes the per-user preference this plugin stores.
 *
 * The plugin creates no options, tables, post types, or scheduled events, so a
 * single user meta key is the whole footprint.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_metadata( 'user', 0, 'dopn_group_notices', '', true );
