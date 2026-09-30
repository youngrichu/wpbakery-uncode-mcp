<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/class-content-tree.php';

$tree = new WPBakery_Uncode_MCP_Content_Tree();

$nested = '[vc_row][vc_column][vc_column_text el_id="keep-me"]Hello[/vc_column_text][/vc_column][/vc_row]';
$parsed = $tree->assign_paths( $tree->parse( $nested )['nodes'] );
assert( 'vc_row' === $parsed[0]['tag'] );
assert( '0/0/0' === $parsed[0]['children'][0]['children'][0]['path'] );
assert( 'keep-me' === $parsed[0]['children'][0]['children'][0]['el_id'] );

$again = $tree->parse( $tree->serialize( $parsed ) )['nodes'];
assert( 'Hello' === $again[0]['children'][0]['children'][0]['children'][0]['text'] );

$same = $tree->assign_paths( $tree->parse( '[vc_row][vc_row]Inner[/vc_row]Outer[/vc_row]' )['nodes'] );
assert( 'Inner' === $same[0]['children'][0]['children'][0]['text'] );
assert( 'Outer' === $same[0]['children'][1]['text'] );

$mixed = $tree->parse( '[vc_column]Hello[vc_column_text]Hi[/vc_column_text]Tail[/vc_column]' )['nodes'];
assert( '#text' === $mixed[0]['children'][0]['tag'] );
assert( 'Hello' === $mixed[0]['children'][0]['text'] );
assert( 'vc_column_text' === $mixed[0]['children'][1]['tag'] );
assert( 'Tail' === $mixed[0]['children'][2]['text'] );

$empty = $tree->parse( '[vc_empty_space height="32" /]' )['nodes'];
assert( true === $empty[0]['self_closing'] );
assert( str_contains( $tree->serialize( $empty ), '[vc_empty_space height="32" /]' ) );

$raw = $tree->parse( '[vc_column_text]Hi[/vc_column_text][vc_row]' );
assert( [] !== $raw['warnings'] );
assert( '#text' === $raw['nodes'][1]['tag'] );
assert( str_contains( $raw['nodes'][1]['text'], '[vc_row]' ) );

$quoted = '[vc_column_text data-x="say &quot;hi&quot; &#93;now&#93;"]Body[/vc_column_text]';
$qnodes = $tree->parse( $quoted )['nodes'];
assert( 'say "hi" ]now]' === $qnodes[0]['attrs']['data-x'] );
$qback  = $tree->parse( $tree->serialize( $qnodes ) )['nodes'];
assert( 'say "hi" ]now]' === $qback[0]['attrs']['data-x'] );

$summary = $tree->summarize( $tree->assign_paths( $tree->parse( $nested )['nodes'] ), 1, false );
assert( [] === $summary[0]['children'][0]['children'] );
assert( true === $summary[0]['children'][0]['children_omitted'] );

echo "content-tree-smoke: ok\n";
