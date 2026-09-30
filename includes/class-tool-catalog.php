<?php
/**
 * Default MCP tool catalog.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wpbakery_uncode_mcp_default_disabled_tools(): array {
	return array(
		'wpbakery-uncode-mcp/build-shortcode',
		'wpbakery-uncode-mcp/insert-shortcode',
		'wpbakery-uncode-mcp/remove-shortcode',
		'wpbakery-uncode-mcp/list-shortcodes',
		'wpbakery-uncode-mcp/update-page-content',
		'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
		'wpbakery-uncode-mcp/update-wpbakery-element-param',
		'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
		'wpbakery-uncode-mcp/set-wpbakery-output-debug',
	);
}

function wpbakery_uncode_mcp_get_disabled_tools(): array {
	$stored = get_option( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION, '__missing__' );
	if ( '__missing__' === $stored || false === $stored || ! is_array( $stored ) ) {
		return wpbakery_uncode_mcp_default_disabled_tools();
	}
	return $stored;
}
