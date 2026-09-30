<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $content ): string {
		return 'KSES:' . $content;
	}
}

require_once __DIR__ . '/../includes/class-content-tree.php';

$raw = '[vc_row][vc_column_text]Hi[/vc_column_text][/vc_row]';
assert( $raw === wpbakery_uncode_mcp_content_for_storage( $raw, true ) );
assert( 'KSES:' . $raw === wpbakery_uncode_mcp_content_for_storage( $raw, false ) );

echo "content-save-smoke: ok\n";
