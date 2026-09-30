<?php
/**
 * WPBakery Inner API and debug abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_WPBakery_Api_Abilities {
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
			'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
			'wpbakery-uncode-mcp/list-wpbakery-elements',
			'wpbakery-uncode-mcp/get-wpbakery-element-definition',
			'wpbakery-uncode-mcp/update-wpbakery-element-param',
			'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
			'wpbakery-uncode-mcp/set-wpbakery-output-debug',
		);
	}

	public function register(): void {
		$this->register_get_inner_api_status();
		$this->register_list_elements();
		$this->register_get_element_definition();
		$this->register_update_element_param();
		$this->register_set_element_visibility();
		$this->register_set_output_debug();
	}

	public function check_manage_options_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function register_get_inner_api_status(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
			array(
				'label'               => __( 'Get WPBakery Inner API Status', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Reports availability of WPBakery Inner API functions.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_inner_api_status' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array( 'type' => 'object' ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_list_elements(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/list-wpbakery-elements',
			array(
				'label'               => __( 'List WPBakery Elements', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when you need the shortcode tags available in this WPBakery install.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_list_elements' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array( 'type' => 'integer' ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_get_element_definition(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-wpbakery-element-definition',
			array(
				'label'               => __( 'Get WPBakery Element Definition', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when you need the accepted parameters for one element before setting attributes.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_element_definition' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tag' => array( 'type' => 'string' ),
					),
					'required'   => array( 'tag' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_update_element_param(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-wpbakery-element-param',
			array(
				'label'               => __( 'Update WPBakery Element Param', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Patches a mapped WPBakery element param definition.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_element_param' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tag'        => array( 'type' => 'string' ),
						'param_name' => array( 'type' => 'string' ),
						'patch'      => array( 'type' => 'object' ),
					),
					'required'   => array( 'tag', 'param_name', 'patch' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_set_element_visibility(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
			array(
				'label'               => __( 'Set WPBakery Element Visibility', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Enable or disable one WPBakery element in builder UI.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_set_element_visibility' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tag'     => array( 'type' => 'string' ),
						'enabled' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'tag', 'enabled' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_set_output_debug(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/set-wpbakery-output-debug',
			array(
				'label'               => __( 'Set WPBakery Output Debug', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Enable shortcode output debug comments for troubleshooting.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_set_output_debug' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'enabled' => array( 'type' => 'boolean' ),
						'tags'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'enabled' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_get_inner_api_status( $input ): array {
		return $this->data->get_wpbakery_inner_api_status();
	}

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_list_elements( $input ): array {
		$limit = absint( $input['limit'] ?? 300 );
		if ( $limit <= 0 ) {
			$limit = 300;
		}
		return $this->data->list_wpbakery_elements( $limit );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_element_definition( $input ) {
		$tag = (string) ( $input['tag'] ?? '' );
		return $this->data->get_wpbakery_element_definition( $tag );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_element_param( $input ) {
		$tag        = (string) ( $input['tag'] ?? '' );
		$param_name = (string) ( $input['param_name'] ?? '' );
		$patch      = is_array( $input['patch'] ?? null ) ? $input['patch'] : array();
		return $this->data->update_wpbakery_element_param( $tag, $param_name, $patch );
	}

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_set_element_visibility( $input ): array {
		$tag     = (string) ( $input['tag'] ?? '' );
		$enabled = ! empty( $input['enabled'] );
		return $this->data->set_wpbakery_element_visibility( $tag, $enabled );
	}

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_set_output_debug( $input ): array {
		$enabled = ! empty( $input['enabled'] );
		$tags    = is_array( $input['tags'] ?? null ) ? $input['tags'] : array();
		return $this->data->set_wpbakery_output_debug( $enabled, $tags );
	}
}
