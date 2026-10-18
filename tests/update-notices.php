<?php
/** Focused updater-message regression checks. */
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS', 86400);
$transients=[];
function add_action(...$args) {}
function add_filter(...$args) {}
function current_user_can($cap) { return true; }
function sanitize_key($s) { return $s; }
function wp_unslash($s) { return $s; }
function wp_parse_url($url,$part) { return parse_url($url,$part); }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_header($r,$key) { return $r['headers'][$key]??''; }
function set_site_transient($key,$value,$ttl) { $GLOBALS['transients'][$key]=$value; }
function get_site_transient($key) { return $GLOBALS['transients'][$key]??false; }
function delete_site_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_date($fmt,$time) { return '12:27 pm PDT'; }
function esc_html($s) { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
class WP_Error {
 function get_error_message() { return '<script>bad</script>'; }
 function get_error_code() { return 'puc-no-update-source'; }
}
function is_wp_error($e) { return $e instanceof WP_Error; }
require dirname(__DIR__).'/includes/class-dopn-update-notices.php';
$c=new class {public $slug='disable-other-plugin-notices'; function getUniqueName($key){return $key.'-'.$this->slug;}};
$n=new DOPN_Update_Notices($c);
function check($label,$ok) { if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}echo "PASS $label\n"; }
check('successful results preserved',$n->message('original','no_update')==='original' && $n->message('available','update_available')==='available');
check('generic failure gives no false up-to-date claim',strpos($n->message('','error'),'could not reach')!==false);
$url='https://api.github.com/repos/hackrepair/disable-other-plugin-notices/releases/latest';
$n->record_limit(null,['code'=>403,'headers'=>[]],$url,$c->slug);
check('ordinary forbidden response is not called rate limiting',get_site_transient('dopn_github_limit')===false);
$n->record_limit(null,['code'=>403,'headers'=>['x-ratelimit-remaining'=>'0','x-ratelimit-reset'=>time()+600]],$url,'other-plugin');
check('unrelated plugin ignored',get_site_transient('dopn_github_limit')===false);
$n->record_limit(null,['code'=>403,'headers'=>['x-ratelimit-remaining'=>'0','x-ratelimit-reset'=>time()+600]],$url,$c->slug);
check('confirmed rate limit gives local retry time',strpos($n->message('','error'),'12:27 pm PDT')!==false);
$_GET=['puc_slug'=>$c->slug,'puc_update_check_result'=>'error'];
set_site_transient($c->getUniqueName('manual_check_errors'),[['error'=>new WP_Error()]],60);
$n->prepare_notice();$html=$n->message('','error');
check('raw library details suppressed',get_site_transient($c->getUniqueName('manual_check_errors'))===false);
check('diagnostics disclosed and escaped',strpos($html,'<details>')!==false && strpos($html,'<script>')===false && strpos($html,'puc-no-update-source')!==false);
