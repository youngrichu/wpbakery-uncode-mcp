<?php
/**
 * Higher-level composition abilities.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Composer_Abilities {
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
			'wpbakery-uncode-mcp/compose-hero-section',
		);
	}

	public function register(): void {
		$this->register_compose_hero_section();
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

	private function register_compose_hero_section(): void {
		wpbakery_uncode_mcp_register_ability(
			'wpbakery-uncode-mcp/compose-hero-section',
			array(
				'label'               => __( 'Compose Hero Section', 'wpbakery-uncode-mcp' ),
				'description'         => __( 'Generates and inserts a WPBakery/Uncode-friendly hero section into a page.', 'wpbakery-uncode-mcp' ),
				'category'            => 'wpbakery-uncode-mcp',
				'execute_callback'    => array( $this, 'execute_compose_hero_section' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array( 'type' => 'integer' ),
						'headline'     => array( 'type' => 'string' ),
						'subheadline'  => array( 'type' => 'string' ),
						'button_text'  => array( 'type' => 'string' ),
						'button_url'   => array( 'type' => 'string' ),
						'background_image_id' => array( 'type' => 'integer' ),
						'position'     => array( 'type' => 'string', 'enum' => array( 'append', 'prepend' ) ),
					),
					'required'   => array( 'post_id', 'headline' ),
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

	/**
	 * @param array $input Input payload.
	 * @return array|WP_Error
	 */
	public function execute_compose_hero_section( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'post_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		$headline    = sanitize_text_field( (string) ( $input['headline'] ?? '' ) );
		$subheadline = sanitize_text_field( (string) ( $input['subheadline'] ?? '' ) );
		$button_text = sanitize_text_field( (string) ( $input['button_text'] ?? 'Get Started' ) );
		$button_url  = esc_url_raw( (string) ( $input['button_url'] ?? '#' ) );
		$image_id    = absint( $input['background_image_id'] ?? 0 );
		$position    = sanitize_key( (string) ( $input['position'] ?? 'append' ) );

		if ( '' === $headline ) {
			return new WP_Error( 'missing_headline', __( 'headline is required.', 'wpbakery-uncode-mcp' ) );
		}

		$page = $this->data->get_page_payload( $post_id, false );
		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$bg_attr = $image_id > 0 ? sprintf( ' bg_image="%d"', $image_id ) : '';
		$hero    = sprintf(
			'[vc_row full_width="stretch_row" uncode_shortcode_id="mcp_hero"%1$s][vc_column width="1/1"][vc_custom_heading text="%2$s" font_container="tag:h1|text_align:center"][vc_column_text]<p style="text-align:center;">%3$s</p>[/vc_column_text][vc_btn title="%4$s" style="flat" color="primary" align="center" link="url:%5$s"][/vc_column][/vc_row]',
			$bg_attr,
			esc_attr( $headline ),
			esc_html( $subheadline ),
			esc_attr( $button_text ),
			esc_attr( $button_url )
		);

		$existing = (string) $page['content_raw'];
		$next     = 'prepend' === $position ? $hero . "\n" . $existing : $existing . "\n" . $hero;

		$result = $this->data->update_page_content( $post_id, $next, 'replace' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}
}
