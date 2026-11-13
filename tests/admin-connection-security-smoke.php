<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION' ) ) {
	define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );
}

if ( ! defined( 'WPB_VC_VERSION' ) ) {
	define( 'WPB_VC_VERSION', 'test' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text ): void {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url(): string {
		return 'https://example.com';
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user(): object {
		return (object) array(
			'user_login' => 'admin',
		);
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ): string {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		$result = '';
		if ( (string) $checked === (string) $current ) {
			$result = " checked='checked'";
		}
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

require_once __DIR__ . '/../includes/admin/class-admin.php';

$_GET['tab'] = 'connection';

$admin = new WPBakery_Uncode_MCP_Admin();

ob_start();
$admin->render_page();
$output = (string) ob_get_clean();

assert( str_contains( $output, 'id="wpbakery-mcp-password" type="password"' ) );
assert( str_contains( $output, 'Treat the generated config like a secret' ) );
assert( str_contains( $output, 'Claude Code works best with its official `claude mcp add` command.' ) );
assert( str_contains( $output, "passwordInput.value = '';" ) );
assert( str_contains( $output, 'claude mcp add --transport http wpbakery-uncode-mcp' ) );
assert( str_contains( $output, 'passwordInput.type = passwordInput.type === \'password\' ? \'text\' : \'password\'' ) );

echo "admin-connection-security-smoke: ok\n";
