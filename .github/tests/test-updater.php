<?php
/**
 * Check that GitHub updates require a correctly named release asset.
 * Run with: php .github/tests/test-updater.php
 */

namespace YahnisElsts\PluginUpdateChecker\v5 {
	class PucFactory {
		public static function addVersion() {
		}

		public static function buildUpdateChecker() {
			return new \DopnTestChecker();
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DOPN_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/disable-other-plugin-notices.php' );

	require_once dirname( __DIR__, 2 ) . '/plugin-update-checker/plugin-update-checker.php';

	function is_wp_error( $value ) {
		return false;
	}

	class DopnTestGitHubApi extends \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi {
		public $fixture;

		public function __construct() {
		}

		protected function api( $url, $queryParams = array() ) {
			return $this->fixture;
		}
	}

	class DopnTestChecker {
		public $api;
		public $branch;

		public function __construct() {
			$this->api = new DopnTestGitHubApi();
		}

		public function setBranch( $branch ) {
			$this->branch = $branch;
		}

		public function getVcsApi() {
			return $this->api;
		}
	}

	require_once dirname( __DIR__, 2 ) . '/includes/class-dopn-updater.php';

	$updater = new \DOPN_Updater();
	$updater->init();
	$checker = $updater->checker();

	$checker->api->fixture = (object) array(
		'tag_name'    => 'v2.3.0',
		'zipball_url' => 'https://example.test/source.zip',
		'created_at'  => '2026-09-19T00:00:00Z',
		'assets'      => array(),
	);

	if ( 'main' !== $checker->branch || null !== $checker->api->getLatestRelease() ) {
		fwrite( STDERR, "Updater accepted a release without an uploaded ZIP.\n" );
		exit( 1 );
	}

	$checker->api->fixture->assets = array( (object) array(
		'name'                 => 'wrong-package.zip',
		'browser_download_url' => 'https://example.test/wrong-package.zip',
		'download_count'       => 0,
	) );

	if ( null !== $checker->api->getLatestRelease() ) {
		fwrite( STDERR, "Updater accepted a release with the wrong ZIP name.\n" );
		exit( 1 );
	}

	$checker->api->fixture->assets[0]->name = 'disable-other-plugin-notices.2.3.0.zip';
	$checker->api->fixture->assets[0]->browser_download_url = 'https://example.test/disable-other-plugin-notices.2.3.0.zip';
	$release = $checker->api->getLatestRelease();

	if ( null === $release || $release->downloadUrl !== $checker->api->fixture->assets[0]->browser_download_url ) {
		fwrite( STDERR, "Updater did not select the matching release ZIP.\n" );
		exit( 1 );
	}

	echo "Updater rejects absent or mismatched ZIPs and accepts the matching release asset.\n";
}
