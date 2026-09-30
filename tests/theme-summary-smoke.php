<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-data.php';

$long = str_repeat( 'a', 180 );
$short = WPBakery_Uncode_MCP_Data::summarize_theme_value( $long, false );
assert( str_starts_with( $short, str_repeat( 'a', 160 ) ) );
assert( str_ends_with( $short, '…' ) );
assert( strlen( $short ) === 160 + strlen( '…' ) );
assert( $long === WPBakery_Uncode_MCP_Data::summarize_theme_value( $long, true ) );

$list = WPBakery_Uncode_MCP_Data::summarize_theme_value( array( 'a', 'b' ), false );
assert( 'array' === $list['type'] );
assert( 2 === $list['count'] );
assert( array( 'a', 'b' ) === WPBakery_Uncode_MCP_Data::summarize_theme_value( array( 'a', 'b' ), true ) );

$theme = file_get_contents( __DIR__ . '/../includes/abilities/class-theme-options-abilities.php' );
assert( is_string( $theme ) );
assert( str_contains( $theme, "'full'" ) );
assert( str_contains( $theme, 'Use when you need Uncode theme option keys or short values before editing one key.' ) );

$pages = file_get_contents( __DIR__ . '/../includes/abilities/class-page-abilities.php' );
assert( is_string( $pages ) );
assert( str_contains( $pages, 'Use when you need the page title, status, URLs, or rendered HTML.' ) );

echo "theme-summary-smoke: ok\n";
