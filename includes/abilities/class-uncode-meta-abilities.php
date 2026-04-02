<?php
/**
 * Uncode meta abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Uncode_Meta_Abilities {
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
			'wpbakery-uncode-mcp/get-uncode-meta',
			'wpbakery-uncode-mcp/update-uncode-meta',
		);
	}

	public function register(): void {
		$this->register_get_meta();
		$this->register_update_meta();
	}

	/**
	 * @param array|null $input Request input.
	 * @return bool
	 */
	public function check_edit_permission( $input = null ): bool {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	private function register_get_meta(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-uncode-meta',
			array(
				'label'               => __( 'Get Uncode Meta', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Reads Uncode-related post meta keys.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_meta' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'keys'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'required'   => array( 'post_id' ),
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

	private function register_update_meta(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-uncode-meta',
			array(
				'label'               => __( 'Update Uncode Meta', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Updates Uncode-related post meta keys (_uncode_* / uncode_* only).', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_meta' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'meta'    => array( 'type' => 'object' ),
					),
					'required'   => array( 'post_id', 'meta' ),
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

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_meta( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$keys    = is_array( $input['keys'] ?? null ) ? $input['keys'] : array();
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->get_uncode_meta( $post_id, $keys );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_meta( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$meta    = is_array( $input['meta'] ?? null ) ? $input['meta'] : array();
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->update_uncode_meta( $post_id, $meta );
	}
}
