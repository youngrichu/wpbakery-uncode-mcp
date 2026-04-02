<?php
/**
 * Theme options abilities for Uncode.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Theme_Options_Abilities {
	/**
	 * @var WPBakery_Uncode_MCP_Data
	 */
	private $data;

	/**
	 * @param WPBakery_Uncode_MCP_Data $data Data service.
	 */
	public function __construct( WPBakery_Uncode_MCP_Data $data ) {
		$this->data = $data;
	}

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'wpbakery-uncode-mcp/get-theme-options',
			'wpbakery-uncode-mcp/update-theme-options',
			'wpbakery-uncode-mcp/update-theme-css-js',
			'wpbakery-uncode-mcp/discover-theme-option-keys',
			'wpbakery-uncode-mcp/get-theme-option-path',
			'wpbakery-uncode-mcp/update-theme-option-path',
			'wpbakery-uncode-mcp/upsert-theme-css-block',
			'wpbakery-uncode-mcp/update-navigation-style',
			'wpbakery-uncode-mcp/update-menu-layout',
			'wpbakery-uncode-mcp/update-megamenu-visuals',
			'wpbakery-uncode-mcp/update-menu-cta-settings',
			'wpbakery-uncode-mcp/update-logo-settings',
			'wpbakery-uncode-mcp/update-logo-links',
			'wpbakery-uncode-mcp/sync-logo-variants',
			'wpbakery-uncode-mcp/validate-theme-mapping',
			'wpbakery-uncode-mcp/apply-brand-setup',
			'wpbakery-uncode-mcp/apply-style-presets',
			'wpbakery-uncode-mcp/preview-preset-impact',
			'wpbakery-uncode-mcp/export-theme-options-diff',
			'wpbakery-uncode-mcp/restore-last-snapshot',
			'wpbakery-uncode-mcp/run-theme-ops-batch',
			'wpbakery-uncode-mcp/update-header-layout',
			'wpbakery-uncode-mcp/update-footer-layout',
			'wpbakery-uncode-mcp/update-global-typography',
			'wpbakery-uncode-mcp/create-theme-options-snapshot',
			'wpbakery-uncode-mcp/list-theme-options-snapshots',
			'wpbakery-uncode-mcp/restore-theme-options-snapshot',
		);
	}

	public function register(): void {
		$this->register_get_theme_options();
		$this->register_update_theme_options();
		$this->register_update_theme_css_js();
		$this->register_discover_theme_option_keys();
		$this->register_get_theme_option_path();
		$this->register_update_theme_option_path();
		$this->register_upsert_theme_css_block();
		$this->register_update_navigation_style();
		$this->register_update_menu_layout();
		$this->register_update_megamenu_visuals();
		$this->register_update_menu_cta_settings();
		$this->register_update_logo_settings();
		$this->register_update_logo_links();
		$this->register_sync_logo_variants();
		$this->register_validate_theme_mapping();
		$this->register_apply_brand_setup();
		$this->register_apply_style_presets();
		$this->register_preview_preset_impact();
		$this->register_export_theme_options_diff();
		$this->register_restore_last_snapshot();
		$this->register_run_theme_ops_batch();
		$this->register_update_header_layout();
		$this->register_update_footer_layout();
		$this->register_update_global_typography();
		$this->register_create_theme_options_snapshot();
		$this->register_list_theme_options_snapshots();
		$this->register_restore_theme_options_snapshot();
	}

	public function check_manage_options_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function register_get_theme_options(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-theme-options',
			array(
				'label'               => __( 'Get Uncode Theme Options', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Reads Uncode theme options from the options table.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_theme_options' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'keys'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_theme_options(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-theme-options',
			array(
				'label'               => __( 'Update Uncode Theme Options', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Updates selected top-level keys in Uncode theme options (sensitive keys blocked).', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_theme_options' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'changes'     => array( 'type' => 'object' ),
					),
					'required'   => array( 'changes' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_theme_css_js(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-theme-css-js',
			array(
				'label'               => __( 'Update Theme CSS/JS', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Updates Uncode custom CSS and/or JS in theme options.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_theme_css_js' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'css'         => array( 'type' => 'string' ),
						'js'          => array( 'type' => 'string' ),
						'append'      => array( 'type' => 'boolean' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_discover_theme_option_keys(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/discover-theme-option-keys',
			array(
				'label'               => __( 'Discover Theme Option Keys', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Scans Uncode theme options and groups likely keys for CSS/JS/navigation/layout.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_discover_theme_option_keys' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_get_theme_option_path(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-theme-option-path',
			array(
				'label'               => __( 'Get Theme Option Path', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Reads a nested value from Uncode theme options using dot path.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_theme_option_path' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'path'        => array( 'type' => 'string' ),
					),
					'required'   => array( 'path' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_theme_option_path(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-theme-option-path',
			array(
				'label'               => __( 'Update Theme Option Path', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Updates a nested Uncode theme option using dot path.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_theme_option_path' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'    => array( 'type' => 'string' ),
						'path'           => array( 'type' => 'string' ),
						'value'          => array(),
						'create_missing' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'path', 'value' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_upsert_theme_css_block(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/upsert-theme-css-block',
			array(
				'label'               => __( 'Upsert Theme CSS Block', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Creates or replaces a labeled CSS block in Uncode custom CSS.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_upsert_theme_css_block' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'label'       => array( 'type' => 'string' ),
						'css'         => array( 'type' => 'string' ),
					),
					'required'   => array( 'label', 'css' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_navigation_style(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-navigation-style',
			array(
				'label'               => __( 'Update Navigation Style', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level navigation style update for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_navigation_style' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'style'       => array( 'type' => 'string', 'enum' => array( 'minimal', 'classic', 'centered', 'offcanvas', 'overlay' ) ),
					),
					'required'   => array( 'style' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_menu_layout(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-menu-layout',
			array(
				'label'               => __( 'Update Menu Layout', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level menu layout and mobile behavior update for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_menu_layout' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'     => array( 'type' => 'string' ),
						'layout'          => array( 'type' => 'string' ),
						'mobile_behavior' => array( 'type' => 'string' ),
					),
					'required'   => array( 'layout' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_megamenu_visuals(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-megamenu-visuals',
			array(
				'label'               => __( 'Update Megamenu Visuals', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level dropdown/megamenu visual settings for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_megamenu_visuals' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'    => array( 'type' => 'string' ),
						'dropdown_style' => array( 'type' => 'string' ),
						'full_width'     => array( 'type' => 'boolean' ),
						'animations'     => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'dropdown_style' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_menu_cta_settings(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-menu-cta-settings',
			array(
				'label'               => __( 'Update Menu CTA Settings', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level menu CTA button settings for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_menu_cta_settings' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'enabled'     => array( 'type' => 'boolean' ),
						'label'       => array( 'type' => 'string' ),
						'url'         => array( 'type' => 'string' ),
						'style'       => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_logo_settings(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-logo-settings',
			array(
				'label'               => __( 'Update Logo Settings', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level logo settings update for Uncode (main/sticky/mobile + retina).', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_logo_settings' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'             => array( 'type' => 'string' ),
						'logo_id'                 => array( 'type' => 'integer' ),
						'sticky_logo_id'          => array( 'type' => 'integer' ),
						'mobile_logo_id'          => array( 'type' => 'integer' ),
						'logo_retina_id'          => array( 'type' => 'integer' ),
						'sticky_logo_retina_id'   => array( 'type' => 'integer' ),
						'mobile_logo_retina_id'   => array( 'type' => 'integer' ),
						'logo_max_width'          => array( 'type' => 'integer' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_logo_links(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-logo-links',
			array(
				'label'               => __( 'Update Logo Links', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Updates logo link URL, ALT text, and link target.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_logo_links' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'link_url'    => array( 'type' => 'string' ),
						'alt_text'    => array( 'type' => 'string' ),
						'link_target' => array( 'type' => 'string', 'enum' => array( 'self', 'blank' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_sync_logo_variants(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/sync-logo-variants',
			array(
				'label'               => __( 'Sync Logo Variants', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Copies a source logo to sticky/mobile and retina logo variants.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_sync_logo_variants' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'    => array( 'type' => 'string' ),
						'source_logo_id' => array( 'type' => 'integer' ),
						'set_sticky'     => array( 'type' => 'boolean' ),
						'set_mobile'     => array( 'type' => 'boolean' ),
						'set_retina'     => array( 'type' => 'boolean' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_validate_theme_mapping(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/validate-theme-mapping',
			array(
				'label'               => __( 'Validate Theme Mapping', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Validates which Uncode option key/path candidates exist on this installation.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_validate_theme_mapping' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_apply_brand_setup(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/apply-brand-setup',
			array(
				'label'               => __( 'Apply Brand Setup', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Applies logo, navigation, typography, primary color, and CSS brand settings in one call.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_apply_brand_setup' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'      => array( 'type' => 'string' ),
						'create_snapshot'  => array( 'type' => 'boolean' ),
						'logo'             => array( 'type' => 'object' ),
						'logo_links'       => array( 'type' => 'object' ),
						'navigation_style' => array( 'type' => 'string' ),
						'menu_layout'      => array( 'type' => 'object' ),
						'typography'       => array( 'type' => 'object' ),
						'primary_color'    => array( 'type' => 'string' ),
						'custom_css'       => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_apply_style_presets(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/apply-style-presets',
			array(
				'label'               => __( 'Apply Style Presets', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Applies one built-in Uncode style preset (minimal, bold, corporate, editorial).', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_apply_style_presets' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'     => array( 'type' => 'string' ),
						'preset'          => array( 'type' => 'string', 'enum' => array( 'minimal', 'bold', 'corporate', 'editorial' ) ),
						'create_snapshot' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'preset' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_export_theme_options_diff(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/export-theme-options-diff',
			array(
				'label'               => __( 'Export Theme Options Diff', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Compares current Uncode options against a baseline snapshot and returns top-level key diff.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_export_theme_options_diff' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'          => array( 'type' => 'string' ),
						'baseline_snapshot_id' => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_preview_preset_impact(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/preview-preset-impact',
			array(
				'label'               => __( 'Preview Preset Impact', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Dry-run preview of top-level option keys that would change for a style preset.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_preview_preset_impact' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'preset'      => array( 'type' => 'string', 'enum' => array( 'minimal', 'bold', 'corporate', 'editorial' ) ),
					),
					'required'   => array( 'preset' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_restore_last_snapshot(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/restore-last-snapshot',
			array(
				'label'               => __( 'Restore Last Snapshot', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Restores the most recent snapshot for the option store.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_restore_last_snapshot' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_run_theme_ops_batch(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/run-theme-ops-batch',
			array(
				'label'               => __( 'Run Theme Ops Batch', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Runs snapshot/validate/preview/apply/diff flow in one call.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_run_theme_ops_batch' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'          => array( 'type' => 'string' ),
						'create_snapshot'      => array( 'type' => 'boolean' ),
						'snapshot_label'       => array( 'type' => 'string' ),
						'validate_mapping'     => array( 'type' => 'boolean' ),
						'preview_preset'       => array( 'type' => 'boolean' ),
						'preset'               => array( 'type' => 'string', 'enum' => array( 'minimal', 'bold', 'corporate', 'editorial' ) ),
						'apply_mode'           => array( 'type' => 'string', 'enum' => array( 'none', 'preset', 'brand_setup' ) ),
						'brand_settings'       => array( 'type' => 'object' ),
						'export_diff'          => array( 'type' => 'boolean' ),
						'baseline_snapshot_id' => array( 'type' => 'string' ),
						'rollback_on_failure'  => array( 'type' => 'boolean' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_header_layout(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-header-layout',
			array(
				'label'               => __( 'Update Header Layout', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level header layout update for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_header_layout' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'layout'      => array( 'type' => 'string' ),
						'sticky'      => array( 'type' => 'boolean' ),
						'transparent' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'layout' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_footer_layout(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-footer-layout',
			array(
				'label'               => __( 'Update Footer Layout', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level footer layout update for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_footer_layout' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'    => array( 'type' => 'string' ),
						'layout'         => array( 'type' => 'string' ),
						'columns'        => array( 'type' => 'integer' ),
						'copyright_text' => array( 'type' => 'string' ),
					),
					'required'   => array( 'layout' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_global_typography(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-global-typography',
			array(
				'label'               => __( 'Update Global Typography', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'High-level typography update for Uncode.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_global_typography' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name'  => array( 'type' => 'string' ),
						'body_font'    => array( 'type' => 'string' ),
						'heading_font' => array( 'type' => 'string' ),
						'base_size'    => array( 'type' => 'integer' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_create_theme_options_snapshot(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/create-theme-options-snapshot',
			array(
				'label'               => __( 'Create Theme Options Snapshot', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Creates a restorable snapshot of current Uncode theme options.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_create_theme_options_snapshot' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
						'label'       => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_list_theme_options_snapshots(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/list-theme-options-snapshots',
			array(
				'label'               => __( 'List Theme Option Snapshots', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Lists available theme option snapshots.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_list_theme_options_snapshots' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'option_name' => array( 'type' => 'string' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_restore_theme_options_snapshot(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/restore-theme-options-snapshot',
			array(
				'label'               => __( 'Restore Theme Options Snapshot', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Restores Uncode theme options from a snapshot.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_restore_theme_options_snapshot' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'snapshot_id' => array( 'type' => 'string' ),
						'option_name' => array( 'type' => 'string' ),
					),
					'required'   => array( 'snapshot_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_theme_options( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$keys        = is_array( $input['keys'] ?? null ) ? $input['keys'] : array();

		return $this->data->get_theme_options( $option_name, $keys );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_theme_options( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$changes     = is_array( $input['changes'] ?? null ) ? $input['changes'] : array();

		return $this->data->update_theme_options( $changes, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_theme_css_js( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$css         = (string) ( $input['css'] ?? '' );
		$js          = (string) ( $input['js'] ?? '' );
		$append      = ! empty( $input['append'] );

		return $this->data->update_theme_css_js( $css, $js, $append, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_discover_theme_option_keys( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		return $this->data->discover_theme_option_keys( $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_theme_option_path( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$path        = (string) ( $input['path'] ?? '' );
		return $this->data->get_theme_option_path( $path, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_theme_option_path( $input ) {
		$option_name    = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$path           = (string) ( $input['path'] ?? '' );
		$value          = $input['value'] ?? null;
		$create_missing = ! empty( $input['create_missing'] );
		return $this->data->update_theme_option_path( $path, $value, $create_missing, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_upsert_theme_css_block( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$label       = (string) ( $input['label'] ?? '' );
		$css         = (string) ( $input['css'] ?? '' );
		return $this->data->upsert_theme_css_block( $label, $css, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_navigation_style( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$style       = (string) ( $input['style'] ?? '' );
		return $this->data->update_uncode_navigation_style( $style, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_menu_layout( $input ) {
		$option_name     = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$layout          = (string) ( $input['layout'] ?? '' );
		$mobile_behavior = (string) ( $input['mobile_behavior'] ?? '' );
		return $this->data->update_uncode_menu_layout( $layout, $mobile_behavior, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_megamenu_visuals( $input ) {
		$option_name    = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$dropdown_style = (string) ( $input['dropdown_style'] ?? '' );
		$full_width     = ! empty( $input['full_width'] );
		$animations     = ! empty( $input['animations'] );
		return $this->data->update_uncode_megamenu_visuals( $dropdown_style, $full_width, $animations, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_menu_cta_settings( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$enabled     = ! empty( $input['enabled'] );
		$label       = (string) ( $input['label'] ?? '' );
		$url         = (string) ( $input['url'] ?? '' );
		$style       = (string) ( $input['style'] ?? '' );
		return $this->data->update_uncode_menu_cta_settings( $enabled, $label, $url, $style, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_logo_settings( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$settings    = array(
			'logo_id'               => $input['logo_id'] ?? null,
			'sticky_logo_id'        => $input['sticky_logo_id'] ?? null,
			'mobile_logo_id'        => $input['mobile_logo_id'] ?? null,
			'logo_retina_id'        => $input['logo_retina_id'] ?? null,
			'sticky_logo_retina_id' => $input['sticky_logo_retina_id'] ?? null,
			'mobile_logo_retina_id' => $input['mobile_logo_retina_id'] ?? null,
			'logo_max_width'        => $input['logo_max_width'] ?? null,
		);
		return $this->data->update_uncode_logo_settings( $settings, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_logo_links( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$link_url    = (string) ( $input['link_url'] ?? '' );
		$alt_text    = (string) ( $input['alt_text'] ?? '' );
		$link_target = (string) ( $input['link_target'] ?? 'self' );
		return $this->data->update_uncode_logo_links( $link_url, $alt_text, $link_target, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_sync_logo_variants( $input ) {
		$option_name    = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$source_logo_id = absint( $input['source_logo_id'] ?? 0 );
		$set_sticky     = array_key_exists( 'set_sticky', (array) $input ) ? ! empty( $input['set_sticky'] ) : true;
		$set_mobile     = array_key_exists( 'set_mobile', (array) $input ) ? ! empty( $input['set_mobile'] ) : true;
		$set_retina     = array_key_exists( 'set_retina', (array) $input ) ? ! empty( $input['set_retina'] ) : true;
		return $this->data->sync_uncode_logo_variants( $source_logo_id, $set_sticky, $set_mobile, $set_retina, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_validate_theme_mapping( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		return $this->data->validate_uncode_theme_mapping( $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_apply_brand_setup( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$settings    = is_array( $input ) ? $input : array();
		return $this->data->apply_uncode_brand_setup( $settings, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_apply_style_presets( $input ) {
		$option_name     = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$preset          = (string) ( $input['preset'] ?? '' );
		$create_snapshot = array_key_exists( 'create_snapshot', (array) $input ) ? ! empty( $input['create_snapshot'] ) : true;
		return $this->data->apply_uncode_style_preset( $preset, $create_snapshot, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_export_theme_options_diff( $input ) {
		$option_name          = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$baseline_snapshot_id = (string) ( $input['baseline_snapshot_id'] ?? '' );
		return $this->data->export_theme_options_diff( $baseline_snapshot_id, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_preview_preset_impact( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$preset      = (string) ( $input['preset'] ?? '' );
		return $this->data->preview_uncode_style_preset_impact( $preset, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_restore_last_snapshot( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		return $this->data->restore_latest_theme_options_snapshot( $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_run_theme_ops_batch( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$config = is_array( $input ) ? $input : array();
		return $this->data->run_theme_ops_batch( $config, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_header_layout( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$layout      = (string) ( $input['layout'] ?? '' );
		$sticky      = ! empty( $input['sticky'] );
		$transparent = ! empty( $input['transparent'] );
		return $this->data->update_uncode_header_layout( $layout, $sticky, $transparent, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_footer_layout( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$layout      = (string) ( $input['layout'] ?? '' );
		$columns     = absint( $input['columns'] ?? 4 );
		$copyright   = (string) ( $input['copyright_text'] ?? '' );
		return $this->data->update_uncode_footer_layout( $layout, $columns, $copyright, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_global_typography( $input ) {
		$option_name  = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$body_font    = (string) ( $input['body_font'] ?? '' );
		$heading_font = (string) ( $input['heading_font'] ?? '' );
		$base_size    = absint( $input['base_size'] ?? 16 );
		return $this->data->update_uncode_global_typography( $body_font, $heading_font, $base_size, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_create_theme_options_snapshot( $input ) {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		$label       = (string) ( $input['label'] ?? '' );
		return $this->data->create_theme_options_snapshot( $label, $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_list_theme_options_snapshots( $input ): array {
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		return $this->data->list_theme_options_snapshots( $option_name );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_restore_theme_options_snapshot( $input ) {
		$snapshot_id = (string) ( $input['snapshot_id'] ?? '' );
		$option_name = sanitize_key( (string) ( $input['option_name'] ?? '' ) );
		return $this->data->restore_theme_options_snapshot( $snapshot_id, $option_name );
	}
}
