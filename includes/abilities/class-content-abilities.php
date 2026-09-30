<?php
/**
 * Content-tree MCP abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Content_Abilities {
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
			'wpbakery-uncode-mcp/get-content-tree',
			'wpbakery-uncode-mcp/apply-content-ops',
		);
	}

	public function register(): void {
		$this->register_get_content_tree();
		$this->register_apply_content_ops();
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

	private function register_get_content_tree(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-content-tree',
			array(
				'label'               => __( 'Get Content Tree', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when you need the nested shortcode structure of a page before editing one element.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_content_tree' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'path'    => array( 'type' => 'string' ),
						'depth'   => array( 'type' => 'integer' ),
						'full'    => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'tree'     => array( 'type' => 'array' ),
						'warnings' => array( 'type' => 'array' ),
					),
				),
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

	private function register_apply_content_ops(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/apply-content-ops',
			array(
				'label'               => __( 'Apply Content Operations', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when changing, inserting, moving, or deleting elements in one page.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_apply_content_ops' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'ops'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'op'            => array(
										'type' => 'string',
										'enum' => array(
											'set_attrs',
											'set_content',
											'insert',
											'move',
											'delete',
											'ensure_ids',
											'replace_content',
										),
									),
									'path'          => array( 'type' => 'string' ),
									'el_id'         => array( 'type' => 'string' ),
									'parent_path'   => array( 'type' => 'string' ),
									'parent_el_id'  => array( 'type' => 'string' ),
									'index'         => array( 'type' => 'integer' ),
									'attrs'         => array( 'type' => 'object' ),
									'content'       => array( 'type' => 'string' ),
									'shortcode'     => array( 'type' => 'string' ),
									'node'          => array( 'type' => 'object' ),
									'allow_empty'   => array( 'type' => 'boolean' ),
								),
								'required'   => array( 'op' ),
							),
						),
						'dry_run' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'post_id', 'ops' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'dry_run'  => array( 'type' => 'boolean' ),
						'post_id'  => array( 'type' => 'integer' ),
						'content'  => array( 'type' => 'string' ),
						'tree'     => array( 'type' => 'array' ),
						'warnings' => array( 'type' => 'array' ),
					),
				),
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

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_content_tree( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$path    = (string) ( $input['path'] ?? '' );
		$depth   = isset( $input['depth'] ) ? (int) $input['depth'] : 3;
		$full    = ! empty( $input['full'] );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->get_content_tree( $post_id, $path, $depth, $full );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_apply_content_ops( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$ops     = is_array( $input['ops'] ?? null ) ? $input['ops'] : array();
		$dry_run = ! empty( $input['dry_run'] );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->apply_content_ops( $post_id, $ops, $dry_run );
	}
}
