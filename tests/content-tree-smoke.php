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

$nested_unclosed = $tree->parse( '[vc_row][vc_column_text el_id="keep-me"]Hello[/vc_column_text][vc_column_text]Hi[/vc_row]' );
assert( [] !== $nested_unclosed['warnings'] );
assert( 'vc_row' === $nested_unclosed['nodes'][0]['tag'] );
assert( 'keep-me' === $nested_unclosed['nodes'][0]['children'][0]['el_id'] );
assert( 'Hello' === $nested_unclosed['nodes'][0]['children'][0]['children'][0]['text'] );
assert( '#text' === $nested_unclosed['nodes'][0]['children'][1]['tag'] );
assert( str_contains( $nested_unclosed['nodes'][0]['children'][1]['text'], '[vc_column_text]' ) );

$void_raw = $tree->parse( '[vc_row][vc_column][vc_empty_space height="32"][/vc_column][/vc_row]' );
assert( 'vc_row' === $void_raw['nodes'][0]['tag'] );
assert( 'vc_column' === $void_raw['nodes'][0]['children'][0]['tag'] );
assert( '#text' === $void_raw['nodes'][0]['children'][0]['children'][0]['tag'] );
assert( str_contains( $void_raw['nodes'][0]['children'][0]['children'][0]['text'], '[vc_empty_space' ) );

$utf8_long = str_repeat( 'あ', 80 );
$utf8_summary = $tree->summarize(
	array(
		array(
			'tag'  => '#text',
			'text' => $utf8_long,
		),
	),
	0,
	false
);
assert( 240 === strlen( $utf8_long ) );
assert( true !== ( $utf8_summary[0]['text_truncated'] ?? false ) );
assert( mb_check_encoding( $utf8_summary[0]['text'], 'UTF-8' ) );
assert( false !== json_encode( $utf8_summary[0]['text'] ) );
assert( 80 === mb_strlen( $utf8_summary[0]['text'], 'UTF-8' ) );

$utf8_over = str_repeat( 'あ', 161 );
$utf8_over_summary = $tree->summarize(
	array(
		array(
			'tag'  => '#text',
			'text' => $utf8_over,
		),
	),
	0,
	false
);
assert( true === ( $utf8_over_summary[0]['text_truncated'] ?? false ) );
assert( mb_check_encoding( $utf8_over_summary[0]['text'], 'UTF-8' ) );
assert( 161 === mb_strlen( $utf8_over_summary[0]['text'], 'UTF-8' ) );

echo "content-tree-smoke: ok\n";
