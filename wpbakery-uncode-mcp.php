<?php
/**
 * Plugin Name:       MCP Tools for WPBakery + Uncode
 * Description:       Extends the WordPress MCP Adapter to expose WPBakery and Uncode editing actions as MCP tools for AI agents.
 * Version:           0.1.1
 * Requires at least: 6.9
 * Tested up to:      6.9
 * Requires PHP:      8.0
 * Author:            Local
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpbakery-uncode-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPBAKERY_UNCODE_MCP_VERSION', '0.1.1' );
define( 'WPBAKERY_UNCODE_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBAKERY_UNCODE_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBAKERY_UNCODE_MCP_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );

/**
 * Recursively sanitize JSON schemas for MCP client compatibility.
 *
 * @param array $schema Schema array.
 * @return array
 */
function wpbakery_uncode_mcp_sanitize_schema( array $schema ): array {
	if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) ) {
		$schema['enum'] = array_values(
			array_filter(
				$schema['enum'],
				static function ( $value ) {
					return '' !== $value;
				}
			)
		);
		if ( empty( $schema['enum'] ) ) {
			unset( $schema['enum'] );
		}
	}

	if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
		if ( empty( $schema['properties'] ) ) {
			$schema['properties'] = new \stdClass();
		} else {
			foreach ( $schema['properties'] as $key => $prop ) {
				if ( is_array( $prop ) ) {
					$schema['properties'][ $key ] = wpbakery_uncode_mcp_sanitize_schema( $prop );
				}
			}
		}
	}

	if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
		$schema['items'] = wpbakery_uncode_mcp_sanitize_schema( $schema['items'] );
	}

	foreach ( array( 'allOf', 'oneOf', 'anyOf' ) as $keyword ) {
		if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
			foreach ( $schema[ $keyword ] as $index => $sub ) {
				if ( is_array( $sub ) ) {
					$schema[ $keyword ][ $index ] = wpbakery_uncode_mcp_sanitize_schema( $sub );
				}
			}
		}
	}

	return $schema;
}

/**
 * Wrapper around wp_register_ability that sanitizes schemas first.
 *
 * @param string $name Ability name.
 * @param array  $args Ability args.
 * @return mixed
 */
function wpbakery_uncode_mcp_register_ability( string $name, array $args ) {
	if ( ! wpbakery_uncode_mcp_is_tool_enabled( $name ) ) {
		return true;
	}

	if ( isset( $args['input_schema'] ) && is_array( $args['input_schema'] ) ) {
		$args['input_schema'] = wpbakery_uncode_mcp_sanitize_schema( $args['input_schema'] );
	}
	if ( isset( $args['output_schema'] ) && is_array( $args['output_schema'] ) ) {
		$args['output_schema'] = wpbakery_uncode_mcp_sanitize_schema( $args['output_schema'] );
	}

	return wp_register_ability( $name, $args );
}

/**
 * Whether an MCP tool is enabled via admin settings.
 *
 * @param string $ability_name Ability slug.
 * @return bool
 */
function wpbakery_uncode_mcp_is_tool_enabled( string $ability_name ): bool {
	$disabled = get_option( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION, array() );
	if ( ! is_array( $disabled ) ) {
		$disabled = array();
	}
	return ! in_array( $ability_name, $disabled, true );
}

/**
 * Check plugin dependencies.
 *
 * @return bool
 */
function wpbakery_uncode_mcp_check_dependencies(): bool {
	$missing = array();

	if ( ! class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
		$missing[] = 'WordPress MCP Adapter';
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		$missing[] = 'WordPress Abilities API (WordPress 6.9+)';
	}

	if ( ! defined( 'WPB_VC_VERSION' ) ) {
		$missing[] = 'WPBakery Page Builder';
	}

	if ( ! empty( $missing ) ) {
		add_action(
			'admin_notices',
			static function () use ( $missing ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: dependency list */
							__( 'MCP Tools for WPBakery + Uncode requires: %s', 'wpbakery-uncode-mcp' ),
							implode( ', ', $missing )
						)
					)
				);
			}
		);

		return false;
	}

	if ( ! wpbakery_uncode_mcp_is_uncode_active() ) {
		add_action(
			'admin_notices',
			static function () {
				printf(
					'<div class="notice notice-warning"><p>%s</p></div>',
					esc_html__( 'Uncode theme was not detected. WPBakery tools still work, but Uncode-specific meta tools may be limited.', 'wpbakery-uncode-mcp' )
				);
			}
		);
	}

	return true;
}

/**
 * Detect whether the current active theme is Uncode.
 *
 * @return bool
 */
function wpbakery_uncode_mcp_is_uncode_active(): bool {
	$theme = wp_get_theme();
	if ( ! $theme ) {
		return false;
	}

	$name        = strtolower( (string) $theme->get( 'Name' ) );
	$template    = strtolower( (string) $theme->get_template() );
	$stylesheet  = strtolower( (string) $theme->get_stylesheet() );
	$parent_name = strtolower( (string) $theme->parent()->get( 'Name' ) );

	$targets = array( $name, $template, $stylesheet, $parent_name );
	foreach ( $targets as $candidate ) {
		if ( false !== strpos( $candidate, 'uncode' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Bootstrap plugin.
 */
function wpbakery_uncode_mcp_init(): void {
	if ( ! wpbakery_uncode_mcp_check_dependencies() ) {
		return;
	}

	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/class-data.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/class-shortcode-service.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-page-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-shortcode-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-wpbakery-api-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-uncode-meta-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-theme-options-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-composer-abilities.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/abilities/class-ability-registrar.php';
	require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/class-plugin.php';
	if ( is_admin() ) {
		require_once WPBAKERY_UNCODE_MCP_DIR . 'includes/admin/class-admin.php';
	}

	WPBakery_Uncode_MCP_Plugin::instance();

	if ( is_admin() && class_exists( 'WPBakery_Uncode_MCP_Admin' ) ) {
		$GLOBALS['wpbakery_uncode_mcp_admin'] = new WPBakery_Uncode_MCP_Admin();
		$GLOBALS['wpbakery_uncode_mcp_admin']->init();
	}
}
add_action( 'plugins_loaded', 'wpbakery_uncode_mcp_init', 20 );

/**
 * Apply persisted WPBakery element visibility.
 *
 * @return void
 */
function wpbakery_uncode_mcp_apply_element_visibility(): void {
	if ( ! function_exists( 'vc_remove_element' ) ) {
		return;
	}

	$disabled = get_option( 'wpbakery_uncode_mcp_disabled_elements', array() );
	if ( ! is_array( $disabled ) ) {
		return;
	}

	foreach ( $disabled as $tag ) {
		$clean_tag = sanitize_key( (string) $tag );
		if ( '' !== $clean_tag ) {
			vc_remove_element( $clean_tag );
		}
	}
}
add_action( 'vc_after_init', 'wpbakery_uncode_mcp_apply_element_visibility', 30 );

/**
 * Optional shortcode output debug wrapper for troubleshooting.
 *
 * @param string $output Shortcode output.
 * @param object $shortcode Shortcode object.
 * @param array  $atts Shortcode atts.
 * @return string
 */
function wpbakery_uncode_mcp_debug_shortcode_output( $output, $shortcode, $atts ) {
	$config = get_option( 'wpbakery_uncode_mcp_output_debug', array() );
	if ( ! is_array( $config ) || empty( $config['enabled'] ) ) {
		return $output;
	}

	$tag = '';
	if ( is_object( $shortcode ) && isset( $shortcode->shortcode ) ) {
		$tag = sanitize_key( (string) $shortcode->shortcode );
	}
	$allow_tags = is_array( $config['tags'] ?? null ) ? $config['tags'] : array();
	if ( ! empty( $allow_tags ) && ! in_array( $tag, $allow_tags, true ) ) {
		return $output;
	}

	$meta = array(
		'tag'        => $tag,
		'atts'       => is_array( $atts ) ? array_keys( $atts ) : array(),
		'output_len' => strlen( (string) $output ),
	);

	return "<!-- mcp-wpbakery-debug " . esc_html( wp_json_encode( $meta ) ) . " -->\n" . $output;
}
add_filter( 'vc_shortcode_output', 'wpbakery_uncode_mcp_debug_shortcode_output', 99, 3 );
