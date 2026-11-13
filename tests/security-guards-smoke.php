<?php
define('ABSPATH','/');
$GLOBALS['caps'] = array();
class WP_Error { public $code; function __construct($c='',$m=''){ $this->code=$c; } }
function is_wp_error($x){ return $x instanceof WP_Error; }
function __($s){ return $s; }
function current_user_can($c){ return in_array($c,$GLOBALS['caps'],true); }
function sanitize_key($k){ return preg_replace('/[^a-z0-9_\-]/','',strtolower($k)); }
function get_option($n,$d=null){ return $n==='uncode_theme_options'?array():$d; }
require __DIR__ . '/../includes/class-data.php';
$d = (new ReflectionClass('WPBakery_Uncode_MCP_Data'))->newInstanceWithoutConstructor();
$g = new ReflectionMethod($d,'guard_raw_theme_write'); $g->setAccessible(true);
$fail=0; $t=function($name,$ok) use (&$fail){ $fail+=!$ok; echo ($ok?'PASS ':'FAIL ').$name."\n"; };
$GLOBALS['caps']=array('manage_options');
$t('raw html blocked for admin w/o unfiltered_html', is_wp_error(WPBakery_Uncode_MCP_Data::check_raw_elements('[vc_raw_html]PHNjcmlwdD4=[/vc_raw_html]')));
$t('raw js blocked (spacing/case)', is_wp_error(WPBakery_Uncode_MCP_Data::check_raw_elements('[ VC_RAW_JS ]x[/vc_raw_js]')));
$t('normal content allowed', WPBakery_Uncode_MCP_Data::check_raw_elements('[vc_row][vc_column_text]hi[/vc_column_text][/vc_row]')===true);
$t('option users_roles rejected', is_wp_error($d->resolve_uncode_option_name('wp_user_roles')));
$t('option active_plugins rejected', is_wp_error($d->resolve_uncode_option_name('active_plugins')));
$t('uncode option allowed', $d->resolve_uncode_option_name('uncode_theme_options')==='uncode_theme_options');
$t('custom_js key blocked', is_wp_error($g->invoke($d,'uncode_custom_js','alert(1)')));
$t('path a.custom_js blocked', is_wp_error($g->invoke($d,'footer.custom_js','x')));
$t('css with </style blocked', is_wp_error($g->invoke($d,'uncode_custom_css','a{}</style><script>alert(1)</script>')));
$t('normal key allowed', $g->invoke($d,'accent_color','#fff')===true);
$t('json key not mistaken for js', $g->invoke($d,'body_font_family','Jost')===true);
$GLOBALS['caps']=array('manage_options','unfiltered_html');
$t('raw html allowed with unfiltered_html', WPBakery_Uncode_MCP_Data::check_raw_elements('[vc_raw_html]x[/vc_raw_html]')===true);
$t('custom_js allowed with unfiltered_html', $g->invoke($d,'uncode_custom_js','console.log(1)')===true);
if ($fail) { exit(1); }
echo "security-guards-smoke: ok\n";
