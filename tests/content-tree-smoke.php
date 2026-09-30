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

$stray_close = $tree->parse( '[vc_row][vc_column_text]Hi[/div][/vc_column_text][/vc_row]' );
assert( 'vc_row' === $stray_close['nodes'][0]['tag'] );
assert( 'vc_column_text' === $stray_close['nodes'][0]['children'][0]['tag'] );
$inner_text = '';
foreach ( $stray_close['nodes'][0]['children'][0]['children'] as $child ) {
	if ( '#text' === ( $child['tag'] ?? '' ) ) {
		$inner_text .= (string) ( $child['text'] ?? '' );
	}
}
assert( str_contains( $inner_text, 'Hi' ) );
assert( str_contains( $inner_text, '[/div]' ) );

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

$base = '[vc_row el_id="row"][vc_column][vc_column_text el_id="copy"]One[/vc_column_text][vc_column_text]Two[/vc_column_text][/vc_column][/vc_row]';

$attrs = $tree->apply(
	$base,
	array(
		array(
			'op'    => 'set_attrs',
			'el_id' => 'copy',
			'attrs' => array(
				'css'   => 'color',
				'el_id' => null,
			),
		),
	)
);
assert( true === $attrs['ok'] );
assert( false === str_contains( $attrs['content'], 'el_id="copy"' ) );
assert( str_contains( $attrs['content'], 'css="color"' ) );

$by_path = $tree->apply(
	$base,
	array(
		array(
			'op'      => 'set_content',
			'path'    => '0/0/1',
			'content' => 'Changed',
		),
	)
);
assert( true === $by_path['ok'] );
assert( str_contains( $by_path['content'], 'Changed' ) );

$inserted = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'insert',
			'parent_path' => '0/0',
			'index'       => 1,
			'shortcode'   => '[vc_column_text]Mid[/vc_column_text]',
		),
	)
);
assert( true === $inserted['ok'] );
assert( str_contains( $inserted['content'], 'One[/vc_column_text][vc_column_text]Mid' ) );

$moved = $tree->apply(
	'[vc_row][vc_column_text]A[/vc_column_text][vc_column_text]B[/vc_column_text][vc_column_text]C[/vc_column_text][/vc_row]',
	array(
		array(
			'op'          => 'move',
			'path'        => '0/0',
			'parent_path' => '0',
			'index'       => 2,
		),
	)
);
assert( true === $moved['ok'] );
$moved_nodes = $tree->parse( $moved['content'] )['nodes'];
assert( 'B' === $moved_nodes[0]['children'][0]['children'][0]['text'] );
assert( 'C' === $moved_nodes[0]['children'][1]['children'][0]['text'] );
assert( 'A' === $moved_nodes[0]['children'][2]['children'][0]['text'] );

$bad_move = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'move',
			'path'        => '0',
			'parent_path' => '0/0',
			'index'       => 0,
		),
	)
);
assert( false === $bad_move['ok'] );
assert( 'invalid_move' === $bad_move['code'] );
assert( false === array_key_exists( 'content', $bad_move ) );

$failed_later = $tree->apply(
	$base,
	array(
		array(
			'op'      => 'set_content',
			'path'    => '0/0/0',
			'content' => 'Nope',
		),
		array(
			'op'   => 'delete',
			'path' => '9/9',
		),
	)
);
assert( false === $failed_later['ok'] );
assert( 'unknown_path' === $failed_later['code'] );
assert( false === array_key_exists( 'content', $failed_later ) );

$dup = $tree->apply( '[vc_row el_id="x"][/vc_row][vc_row el_id="x"][/vc_row]', array() );
assert( false === $dup['ok'] );
assert( 'duplicate_el_id' === $dup['code'] );

$ids = $tree->apply(
	'[vc_row el_id="keep-me"][vc_column][/vc_column][/vc_row]',
	array(
		array( 'op' => 'ensure_ids' ),
	)
);
assert( true === $ids['ok'] );
assert( str_contains( $ids['content'], 'el_id="keep-me"' ) );
assert( 1 === preg_match_all( '/el_id="mcp-[a-f0-9]{8}"/', $ids['content'] ) );

$wipe = $tree->apply( $base, array( array( 'op' => 'replace_content', 'content' => '' ) ) );
assert( false === $wipe['ok'] );
assert( 'empty_replace' === $wipe['code'] );

$replaced = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'replace_content',
			'content'     => '[vc_empty_space /]',
			'allow_empty' => false,
		),
	)
);
assert( true === $replaced['ok'] );
assert( str_contains( $replaced['content'], '[vc_empty_space /]' ) );

$void_set = $tree->apply(
	'[vc_empty_space /]',
	array(
		array(
			'op'      => 'set_content',
			'path'    => '0',
			'content' => 'Hi',
		),
	)
);
assert( true === $void_set['ok'] );
assert( str_contains( $void_set['content'], 'Hi' ) );
assert( false === str_contains( $void_set['content'], '[vc_empty_space /]' ) );

$dup_attr = $tree->apply(
	'[vc_row el_id="a"][/vc_row][vc_row el_id="b"][/vc_row]',
	array(
		array(
			'op'    => 'set_attrs',
			'el_id' => 'b',
			'attrs' => array(
				'el_id' => 'a',
			),
		),
	)
);
assert( false === $dup_attr['ok'] );
assert( 'duplicate_el_id' === $dup_attr['code'] );
assert( false === array_key_exists( 'content', $dup_attr ) );

$dup_repair = $tree->apply(
	'[vc_row el_id="x"][/vc_row][vc_row el_id="x"][/vc_row]',
	array(
		array(
			'op'      => 'replace_content',
			'content' => '[vc_empty_space /]',
		),
	)
);
assert( true === $dup_repair['ok'] );

$warn_clean = $tree->apply(
	'[vc_row]',
	array(
		array(
			'op'      => 'replace_content',
			'content' => '[vc_empty_space /]',
		),
	)
);
assert( true === $warn_clean['ok'] );
assert( [] === $warn_clean['warnings'] );

$warn_bad = $tree->apply(
	'[vc_row el_id="ok"][/vc_row]',
	array(
		array(
			'op'      => 'replace_content',
			'content' => '[vc_row]',
		),
	)
);
assert( true === $warn_bad['ok'] );
assert( [] !== $warn_bad['warnings'] );

echo "content-tree-smoke: ok\n";
