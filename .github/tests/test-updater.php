<?php
/**
 * Check that GitHub updates use only a release asset matching its tag.
 * Run with: php .github/tests/test-updater.php
 */

namespace YahnisElsts\PluginUpdateChecker\v5 {
	class PucFactory {
		public static $nextChecker;

		public static function addVersion() {
		}

		public static function buildUpdateChecker() {
			if ( self::$nextChecker ) {
				$checker = self::$nextChecker;
				self::$nextChecker = null;
				return $checker;
			}
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

	function add_filter( $name, $callback ) {
		$GLOBALS['dopn_test_filters'][ $name ][] = $callback;
		return true;
	}

	function apply_filters( $name, $value ) {
		foreach ( $GLOBALS['dopn_test_filters'][ $name ] ?? array() as $callback ) {
			$value = $callback( $value );
		}
		return $value;
	}

	class DopnTestGitHubApi extends \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi {
		public $fixture;
		public $requests = array();

		public function __construct() {
		}

		protected function api( $url, $queryParams = array() ) {
			$this->requests[] = $url;
			if ( false !== strpos( $url, '/releases/' ) ) {
				return $this->fixture;
			}
			if ( false !== strpos( $url, '/tags' ) ) {
				return array( (object) array(
					'name'        => 'v2.5.0',
					'zipball_url' => 'https://example.test/tag-source.zip',
				) );
			}
			return (object) array( 'name' => 'main' );
		}
	}

	class DopnTestChecker {
		public $api;
		public $branch;

		public function __construct() {
			$this->api = new DopnTestGitHubApi();
			$this->api->setStrategyFilterName( 'puc_vcs_update_detection_strategies-disable-other-plugin-notices' );
		}

		public function setBranch( $branch ) {
			$this->branch = $branch;
		}

		public function getVcsApi() {
			return $this->api;
		}

		public function addFilter( $name, $callback ) {
			add_filter( 'puc_' . $name . '-disable-other-plugin-notices', $callback );
		}

		public function removeHooks() {
		}
	}

	class DopnUnsupportedChecker {
		public $hooksRemoved = false;

		public function setBranch( $branch ) {
		}

		public function getVcsApi() {
			return new \stdClass();
		}

		public function removeHooks() {
			$this->hooksRemoved = true;
		}
	}

	function assert_rejected( $checker, $description ) {
		$checker->api->requests = array();
		if ( null !== $checker->api->chooseReference( $checker->branch ) ) {
			fwrite( STDERR, "Updater accepted {$description}.\n" );
			exit( 1 );
		}
		if ( array( '/repos/:user/:repo/releases/latest' ) !== $checker->api->requests ) {
			fwrite( STDERR, "Updater fell back to a tag or branch for {$description}.\n" );
			exit( 1 );
		}
	}

	require_once dirname( __DIR__, 2 ) . '/includes/class-dopn-updater.php';

	$updater = new \DOPN_Updater();
	$updater->init();
	$checker = $updater->checker();

	$checker->api->fixture = (object) array(
		'tag_name'    => 'v2.5.0',
		'zipball_url' => 'https://example.test/source.zip',
		'created_at'  => '2026-09-19T00:00:00Z',
		'assets'      => array(),
	);

	if ( 'main' !== $checker->branch ) {
		fwrite( STDERR, "Updater did not check the main branch.\n" );
		exit( 1 );
	}
	assert_rejected( $checker, 'a release without an uploaded ZIP' );

	$checker->api->fixture->assets = array( (object) array(
		'name'                 => 'wrong-package.zip',
		'browser_download_url' => 'https://example.test/wrong-package.zip',
		'download_count'       => 0,
	) );

	assert_rejected( $checker, 'a release with the wrong ZIP name' );

	$checker->api->fixture->assets[0]->name = 'disable-other-plugin-notices.2.4.0.zip';
	$checker->api->fixture->assets[0]->browser_download_url = 'https://example.test/older-package.zip';
	assert_rejected( $checker, 'a ZIP whose version differs from the release tag' );

	$checker->api->fixture->assets[] = (object) array(
		'name'                 => 'disable-other-plugin-notices.2.5.0.zip',
		'browser_download_url' => 'https://example.test/current-package.zip',
		'download_count'       => 0,
	);
	assert_rejected( $checker, 'an older ZIP before the correct ZIP' );
	array_shift( $checker->api->fixture->assets );

	$checker->api->fixture->tag_name = 'vv2.5.0';
	assert_rejected( $checker, 'a malformed release tag' );
	$checker->api->fixture->tag_name = 'v2.5.0';

	$checker->api->fixture->assets[0]->name = 'prefix-disable-other-plugin-notices.2.5.0.zip';
	assert_rejected( $checker, 'a ZIP with a prefixed filename' );

	$checker->api->fixture->assets[0]->name = 'disable-other-plugin-notices.2.5.0.zip';
	$checker->api->fixture->assets[0]->browser_download_url = 'https://example.test/disable-other-plugin-notices.2.5.0.zip';
	$release = $checker->api->chooseReference( $checker->branch );

	if ( null === $release || $release->downloadUrl !== $checker->api->fixture->assets[0]->browser_download_url ) {
		fwrite( STDERR, "Updater did not select the matching release ZIP.\n" );
		exit( 1 );
	}

	$checker->api->fixture = null;
	assert_rejected( $checker, 'an unavailable release API' );

	$unsupported = new DopnUnsupportedChecker();
	\YahnisElsts\PluginUpdateChecker\v5\PucFactory::$nextChecker = $unsupported;
	$updater = new \DOPN_Updater();
	$updater->init();
	if ( ! $unsupported->hooksRemoved || null !== $updater->checker() ) {
		fwrite( STDERR, "Updater did not disable an unsupported checker.\n" );
		exit( 1 );
	}

	echo "Updater selects only a same-version release ZIP, never falls back to source archives, and disables unsupported checkers.\n";
}
