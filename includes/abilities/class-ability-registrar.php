<?php
/**
 * Registers all MCP abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Ability_Registrar {
	/**
	 * @var WPBakery_Uncode_MCP_Data
	 */
	private $data;

	/**
	 * @var WPBakery_Uncode_MCP_Shortcode_Service
	 */
	private $shortcode_service;

	/**
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * @param WPBakery_Uncode_MCP_Data              $data Data service.
	 * @param WPBakery_Uncode_MCP_Shortcode_Service $shortcode_service Shortcode service.
	 */
	public function __construct( WPBakery_Uncode_MCP_Data $data, WPBakery_Uncode_MCP_Shortcode_Service $shortcode_service ) {
		$this->data              = $data;
		$this->shortcode_service = $shortcode_service;
	}

	/**
	 * @return string[]
	 */
	public function register_all(): array {
		$pages = new WPBakery_Uncode_MCP_Page_Abilities( $this->data );
		$pages->register();
		$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

		$content = new WPBakery_Uncode_MCP_Content_Abilities( $this->data );
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );

		$shortcodes = new WPBakery_Uncode_MCP_Shortcode_Abilities( $this->data, $this->shortcode_service );
		$shortcodes->register();
		$this->ability_names = array_merge( $this->ability_names, $shortcodes->get_ability_names() );

		$wpbakery_api = new WPBakery_Uncode_MCP_WPBakery_Api_Abilities( $this->data );
		$wpbakery_api->register();
		$this->ability_names = array_merge( $this->ability_names, $wpbakery_api->get_ability_names() );

		$uncode_meta = new WPBakery_Uncode_MCP_Uncode_Meta_Abilities( $this->data );
		$uncode_meta->register();
		$this->ability_names = array_merge( $this->ability_names, $uncode_meta->get_ability_names() );

		$theme_options = new WPBakery_Uncode_MCP_Theme_Options_Abilities( $this->data );
		$theme_options->register();
		$this->ability_names = array_merge( $this->ability_names, $theme_options->get_ability_names() );

		$composer = new WPBakery_Uncode_MCP_Composer_Abilities( $this->data );
		$composer->register();
		$this->ability_names = array_merge( $this->ability_names, $composer->get_ability_names() );

		$this->ability_names = apply_filters( 'wpbakery_uncode_mcp_ability_names', $this->ability_names );

		return array_values( array_unique( $this->ability_names ) );
	}
}
