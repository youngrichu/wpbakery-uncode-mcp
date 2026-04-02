<?php
/**
 * Main plugin orchestrator.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var WPBakery_Uncode_MCP_Data
	 */
	private $data;

	/**
	 * @var WPBakery_Uncode_MCP_Shortcode_Service
	 */
	private $shortcode_service;

	/**
	 * @var WPBakery_Uncode_MCP_Ability_Registrar
	 */
	private $registrar;

	/**
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	private function init(): void {
		$this->data              = new WPBakery_Uncode_MCP_Data();
		$this->shortcode_service = new WPBakery_Uncode_MCP_Shortcode_Service();
		$this->registrar         = new WPBakery_Uncode_MCP_Ability_Registrar( $this->data, $this->shortcode_service );

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ), 20 );
	}

	public function register_category(): void {
		wp_register_ability_category(
			'wpbakery-uncode-mcp',
			array(
				'label'       => __( 'MCP Tools for WPBakery + Uncode', 'wpbakery-uncode-mcp' ),
				'description' => __( 'Tools for reading and manipulating WPBakery/Uncode content via MCP.', 'wpbakery-uncode-mcp' ),
			)
		);
	}

	public function register_abilities(): void {
		$this->ability_names = $this->registrar->register_all();
	}

	/**
	 * @param \WP\MCP\Core\McpAdapter $mcp_adapter MCP adapter instance.
	 */
	public function register_mcp_server( $mcp_adapter ): void {
		if ( empty( $this->ability_names ) ) {
			return;
		}

		$mcp_adapter->create_server(
			'wpbakery-uncode-mcp-server',
			'mcp',
			'wpbakery-uncode-mcp-server',
			__( 'MCP Tools for WPBakery + Uncode Server', 'wpbakery-uncode-mcp' ),
			__( 'Exposes WPBakery and Uncode actions as MCP tools for AI agents.', 'wpbakery-uncode-mcp' ),
			'v' . WPBAKERY_UNCODE_MCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			null,
			null,
			$this->ability_names,
			array(),
			array(),
			null
		);
	}
}
