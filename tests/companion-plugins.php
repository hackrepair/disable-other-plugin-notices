<?php
/** Standalone companion-tab contract; no network requests or plugin actions. */
define('ABSPATH', __DIR__ . '/');
$root = dirname(__DIR__) . '/';
foreach (array('GPR_PLUGIN_DIR','PUE_PLUGIN_DIR') as $key) { define($key,$root); }
foreach (array('GPR_PLUGIN_URL','PUE_PLUGIN_URL') as $key) { define($key,'https://example.test/plugins/'); }
foreach (array('GPR_PLUGIN_FILE','DOPN_PLUGIN_FILE','HRPD_FILE','PUE_PLUGIN_FILE') as $key) { define($key,$root.'index.php'); }
foreach (array('GPR_VERSION','DOPN_VERSION','HRPD_VERSION','PUE_VERSION','THRG_SI_VERSION') as $key) { define($key,'test'); }
$GLOBALS['hooks'] = array();
function add_filter($hook,$fn,$priority=10,$args=1) { $GLOBALS['hooks'][$hook][]=$fn; }
function wp_enqueue_style(...$args) {}
function plugins_url($path,$file) { return 'https://example.test/plugins/'.$path; }
require $root.'includes/companion-plugins.php';
$fn = 'dopn_companion_plugin_info';
$args = (object) array('slug'=>'disable-other-plugin-notices');
$info = (object) array('version'=>'9.9.9','banners'=>array('high'=>'official-banner'),'sections'=>array('description'=>'Keep description','changelog'=>'Keep changelog'));
$out = $fn($info,'plugin_information',$args);
$html = $out->sections['other_THRG_plugins'] ?? '';
if (substr_count($html,'<h3>')!==5 || strpos($html,'<h3>Disable Other Plugin Notices</h3>')!==false || $out->sections['description']!=='Keep description' || $out->sections['changelog']!=='Keep changelog') { throw new RuntimeException('Five companions, self-exclusion or original sections failed.'); }
preg_match_all('/<a href="([^"]+)"/',$html,$matches);
if (count(array_unique($matches[1]))!==5 || strpos($html,'github.com/hackrepair/hackrepairpluginarchiver')!==false) { throw new RuntimeException('Duplicate or incorrect destination.'); }
if ('disable-other-plugin-notices'!=='hackrepair-plugin-archiver' && strpos($html,'https://wordpress.org/plugins/hackrepair-plugin-archiver/')===false) { throw new RuntimeException('Archiver must link to WordPress.org.'); }
$again = $fn($out,'plugin_information',$args);
if ($again->sections['other_THRG_plugins']!==$html) { throw new RuntimeException('Repeated filters duplicate content.'); }
$foreign = (object) array('sections'=>array('description'=>'Other'));
if ($fn($foreign,'plugin_information',(object)array('slug'=>'another-plugin'))!==$foreign || isset($foreign->sections['other_THRG_plugins'])) { throw new RuntimeException('Foreign plugin modified.'); }
foreach (array(false,null,array(),(object)array('errors'=>array())) as $invalid) {
 if ($fn($invalid,'plugin_information',$args)!==$invalid) { throw new RuntimeException('Invalid/error response changed.'); }
}
$wrong = (object) array('sections'=>array('description'=>'Other action'));
if ($fn($wrong,'query_plugins',$args)!==$wrong || isset($wrong->sections['other_THRG_plugins'])) { throw new RuntimeException('Other action modified.'); }
if ('disable-other-plugin-notices'==='hackrepair-plugin-archiver' && (!in_array($fn,$GLOBALS['hooks']['plugins_api_result']??array(),true) || $out->banners['high']!=='official-banner' || $out->version!=='9.9.9')) { throw new RuntimeException('WordPress.org hook or metadata preservation failed.'); }
echo "PASS disable-other-plugin-notices: five distinct companions, WordPress.org destination, self-exclusion, metadata, idempotence and isolation.\n";
