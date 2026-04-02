<?php
/**
 * Shortcode manipulation abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Shortcode_Abilities {
	/**
	 * @var WPBakery_Uncode_MCP_Data
	 */
	private $data;

	/**
	 * @var WPBakery_Uncode_MCP_Shortcode_Service
	 */
	private $shortcode_service;

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
	public function get_ability_names(): array {
		return array(
			'wpbakery-uncode-mcp/build-shortcode',
			'wpbakery-uncode-mcp/insert-shortcode',
			'wpbakery-uncode-mcp/remove-shortcode',
			'wpbakery-uncode-mcp/list-shortcodes',
		);
	}

	public function register(): void {
		$this->register_build_shortcode();
		$this->register_insert_shortcode();
		$this->register_remove_shortcode();
		$this->register_list_shortcodes();
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

	private function register_build_shortcode(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/build-shortcode',
			array(
				'label'               => __( 'Build WPBakery Shortcode', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Builds one shortcode string from tag, attributes, and content.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_build_shortcode' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tag'     => array( 'type' => 'string' ),
						'attrs'   => array( 'type' => 'object' ),
						'content' => array( 'type' => 'string' ),
					),
					'required'   => array( 'tag' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'shortcode' => array( 'type' => 'string' ),
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

	private function register_insert_shortcode(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/insert-shortcode',
			array(
				'label'               => __( 'Insert Shortcode Into Page', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Inserts a shortcode into page content using append/prepend/anchor modes.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_insert_shortcode' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array( 'type' => 'integer' ),
						'shortcode'  => array( 'type' => 'string' ),
						'position'   => array( 'type' => 'string', 'enum' => array( 'append', 'prepend', 'before_anchor', 'after_anchor' ) ),
						'anchor'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'post_id', 'shortcode' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
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

	private function register_remove_shortcode(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/remove-shortcode',
			array(
				'label'               => __( 'Remove First Shortcode By Tag', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Removes the first shortcode occurrence by tag from a page/post.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_remove_shortcode' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'tag'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'post_id', 'tag' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'removed' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
					),
				),
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

	private function register_list_shortcodes(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/list-shortcodes',
			array(
				'label'               => __( 'List Shortcodes Used In Page', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Lists shortcode tags detected in page content.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_list_shortcodes' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'tags'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
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

	/**
	 * @param array $input Input payload.
	 * @return array
	 */
	public function execute_build_shortcode( $input ): array {
		$tag     = sanitize_key( (string) ( $input['tag'] ?? '' ) );
		$attrs   = is_array( $input['attrs'] ?? null ) ? $input['attrs'] : array();
		$content = array_key_exists( 'content', (array) $input ) ? (string) $input['content'] : null;

		return array(
			'shortcode' => $this->data->build_shortcode( $tag, $attrs, $content ),
		);
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_insert_shortcode( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$shortcode  = (string) ( $input['shortcode'] ?? '' );
		$position   = sanitize_key( (string) ( $input['position'] ?? 'append' ) );
		$anchor     = (string) ( $input['anchor'] ?? '' );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		$page = $this->data->get_page_payload( $post_id, false );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$next_content = $this->shortcode_service->insert_shortcode(
			(string) $page['content_raw'],
			$shortcode,
			$position,
			$anchor
		);

		$result = $this->data->update_page_content( $post_id, $next_content, 'replace' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_remove_shortcode( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		$tag     = sanitize_key( (string) ( $input['tag'] ?? '' ) );

		if ( $post_id <= 0 || '' === $tag ) {
			return new WP_Error( 'invalid_input', __( 'post_id and tag are required.', 'wpbakery-uncode-mcp' ) );
		}

		$page = $this->data->get_page_payload( $post_id, false );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$operation = $this->shortcode_service->remove_first_shortcode_by_tag( (string) $page['content_raw'], $tag );
		$result    = $this->data->update_page_content( $post_id, (string) $operation['content'], 'replace' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'removed' => ! empty( $operation['removed'] ),
			'post_id' => $post_id,
		);
	}

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_list_shortcodes( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		$page = $this->data->get_page_payload( $post_id, false );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		return array(
			'post_id' => $post_id,
			'tags'    => $this->shortcode_service->list_shortcode_tags( (string) $page['content_raw'] ),
		);
	}
}
