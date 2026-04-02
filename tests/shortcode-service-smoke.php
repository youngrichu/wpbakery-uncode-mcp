<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
	}
}

if ( ! function_exists( 'get_shortcode_regex' ) ) {
	function get_shortcode_regex(): string {
		return '\\[(\\[?)([a-zA-Z0-9_\\-]+)(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+)\\[\\/\\2\\])?)(\\]?)';
	}
}

require_once __DIR__ . '/../includes/class-shortcode-service.php';

$service = new WPBakery_Uncode_MCP_Shortcode_Service();

$original = '[vc_row][vc_column][vc_column_text]Hello[/vc_column_text][/vc_column][/vc_row]';
$inserted = $service->insert_shortcode( $original, '[vc_btn title="Book"]', 'append' );
assert( str_contains( $inserted, '[vc_btn title="Book"]' ) );

$removed = $service->remove_first_shortcode_by_tag( $inserted, 'vc_btn' );
assert( true === $removed['removed'] );
assert( false === str_contains( $removed['content'], '[vc_btn' ) );

$tags = $service->list_shortcode_tags( $original );
assert( in_array( 'vc_row', $tags, true ) );
assert( in_array( 'vc_column', $tags, true ) );
assert( in_array( 'vc_column_text', $tags, true ) );

echo "shortcode-service-smoke: ok\n";
