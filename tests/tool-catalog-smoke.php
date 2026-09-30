<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION' ) ) {
	define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );
}

$GLOBALS['wpbakery_uncode_mcp_test_option'] = '__missing__';

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $GLOBALS['wpbakery_uncode_mcp_test_option'];
	}
}

require_once __DIR__ . '/../includes/class-tool-catalog.php';

$defaults = wpbakery_uncode_mcp_get_disabled_tools();
foreach (
	array(
		'wpbakery-uncode-mcp/build-shortcode',
		'wpbakery-uncode-mcp/insert-shortcode',
		'wpbakery-uncode-mcp/remove-shortcode',
		'wpbakery-uncode-mcp/list-shortcodes',
		'wpbakery-uncode-mcp/update-page-content',
		'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
		'wpbakery-uncode-mcp/update-wpbakery-element-param',
		'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
		'wpbakery-uncode-mcp/set-wpbakery-output-debug',
	) as $slug
) {
	assert( in_array( $slug, $defaults, true ) );
}
assert( false === in_array( 'wpbakery-uncode-mcp/get-content-tree', $defaults, true ) );
assert( false === in_array( 'wpbakery-uncode-mcp/get-theme-options', $defaults, true ) );

$GLOBALS['wpbakery_uncode_mcp_test_option'] = array();
assert( array() === wpbakery_uncode_mcp_get_disabled_tools() );

$GLOBALS['wpbakery_uncode_mcp_test_option'] = array( 'wpbakery-uncode-mcp/get-page' );
assert( array( 'wpbakery-uncode-mcp/get-page' ) === wpbakery_uncode_mcp_get_disabled_tools() );

echo "tool-catalog-smoke: ok\n";
