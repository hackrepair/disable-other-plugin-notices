<?php
/**
 * Update checks against this plugin's GitHub releases.
 *
 * @package DisableOtherPluginNotices
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Points WordPress at GitHub releases for this plugin's updates.
 *
 * This plugin is not distributed on the WordPress.org repository, so it
 * would otherwise never show an "update available" notice. This class
 * wires in the Plugin Update Checker library (vendored in
 * `plugin-update-checker/`) so WordPress checks this plugin's GitHub
 * releases the same way it checks wordpress.org for everything else.
 *
 * A release's own uploaded zip asset is used instead of GitHub's
 * auto-generated source archive, because the auto-generated archive
 * extracts into a version-stamped folder name (e.g.
 * `disable-other-plugin-notices-2.0.0/`) which would change the
 * installed plugin's directory name and deactivate it on update.
 *
 * @since 2.0.0
 */
final class DOPN_Updater {

	const REPOSITORY_URL = 'https://github.com/hackrepair/disable-other-plugin-notices/';
	const BRANCH         = 'main';
	const ASSET_PATTERN  = '/disable-other-plugin-notices\.[0-9.]+\.zip($|[?&#])/i';

	/**
	 * @var \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	private $checker = null;

	/**
	 * Build and configure the update checker.
	 *
	 * Safe to call even if the vendored library failed to load for some
	 * reason (e.g. a stripped-down copy of the plugin folder) — it just
	 * no-ops rather than fataling.
	 */
	public function init() {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$this->checker = PucFactory::buildUpdateChecker(
			self::REPOSITORY_URL,
			DOPN_PLUGIN_FILE,
			'disable-other-plugin-notices'
		);

		$this->checker->setBranch( self::BRANCH );

		$api = $this->checker->getVcsApi();

		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( self::ASSET_PATTERN );
		}
	}

	/**
	 * Expose the underlying checker instance, mostly for debugging.
	 *
	 * @return \YahnisElsts\PluginUpdateChecker\v5\Vcs\Api|null
	 */
	public function checker() {
		return $this->checker;
	}
}
