<?php
/**
 * Page-level MCP abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Page_Abilities {
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
			'wpbakery-uncode-mcp/create-page',
			'wpbakery-uncode-mcp/get-page',
			'wpbakery-uncode-mcp/update-page-content',
		);
	}

	public function register(): void {
		$this->register_create_page();
		$this->register_get_page();
		$this->register_update_page_content();
	}

	public function check_create_permission(): bool {
		return current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' );
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

	private function register_create_page(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/create-page',
			array(
				'label'               => __( 'Create WPBakery Page', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when creating a page or post before adding WPBakery content.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_create_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'       => array( 'type' => 'string' ),
						'status'      => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'private', 'pending' ) ),
						'post_type'   => array( 'type' => 'string', 'enum' => array( 'page', 'post' ) ),
						'template'    => array( 'type' => 'string' ),
						'content'     => array( 'type' => 'string' ),
						'uncode_meta' => array( 'type' => 'object' ),
					),
					'required'   => array( 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array( 'type' => 'integer' ),
						'title'        => array( 'type' => 'string' ),
						'edit_url'     => array( 'type' => 'string' ),
						'preview_url'  => array( 'type' => 'string' ),
						'has_wpbakery' => array( 'type' => 'boolean' ),
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

	private function register_get_page(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/get-page',
			array(
				'label'               => __( 'Get Page Content', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Use when you need the page title, status, URLs, or rendered HTML.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_get_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array( 'type' => 'integer' ),
						'include_rendered' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type' => 'object',
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

	private function register_update_page_content(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/update-page-content',
			array(
				'label'               => __( 'Update Page Content', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Replaces, appends, or prepends WPBakery shortcode content on a page/post.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_update_page_content' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'mode'    => array( 'type' => 'string', 'enum' => array( 'replace', 'append', 'prepend' ) ),
						'content' => array( 'type' => 'string' ),
					),
					'required'   => array( 'post_id', 'content' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'post_id'      => array( 'type' => 'integer' ),
						'content_size' => array( 'type' => 'integer' ),
					),
				),
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
	public function execute_create_page( $input ) {
		return $this->data->create_page( is_array( $input ) ? $input : array() );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_get_page( $input ) {
		$post_id          = absint( $input['post_id'] ?? 0 );
		$include_rendered = ! empty( $input['include_rendered'] );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->get_page_payload( $post_id, $include_rendered );
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_update_page_content( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$content = (string) ( $input['content'] ?? '' );
		$mode    = sanitize_key( (string) ( $input['mode'] ?? 'replace' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->data->update_page_content( $post_id, $content, $mode );
	}
}
