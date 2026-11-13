<?php
/**
 * Admin settings page for WPBakery + Uncode MCP.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Admin {
	const PAGE_SLUG = 'wpbakery-uncode-mcp';
	const SETTINGS_GROUP = 'wpbakery_uncode_mcp_settings';

	/**
	 * @var string
	 */
	private $hook_suffix = '';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_settings_page(): void {
		$this->hook_suffix = add_options_page(
			__( 'MCP Tools for WPBakery + Uncode', 'wpbakery-uncode-mcp' ),
			__( 'WPBakery MCP', 'wpbakery-uncode-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_disabled_tools' ),
			)
		);
	}

	/**
	 * @param mixed $input Raw enabled tools list from form.
	 * @return array
	 */
	public function sanitize_disabled_tools( $input ): array {
		$enabled = array();
		if ( is_array( $input ) ) {
			$enabled = array_map( 'sanitize_text_field', $input );
		}

		$all = $this->get_all_tool_slugs();
		return array_values( array_diff( $all, $enabled ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', '.wpbakery-uncode-mcp-wrap .card{max-width:none}.wpbakery-uncode-mcp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}.wpbakery-uncode-mcp-code{white-space:pre;overflow:auto;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;font-size:12px}.wpbakery-uncode-mcp-kv{display:grid;grid-template-columns:180px 1fr;gap:8px;align-items:center}.wpbakery-uncode-mcp-tool{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #eee}.wpbakery-uncode-mcp-tool:last-child{border-bottom:none}.wpbakery-uncode-mcp-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.wpbakery-uncode-mcp-password-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.wpbakery-uncode-mcp-password-row input{flex:1 1 280px}.wpbakery-uncode-mcp-warning{margin-top:12px;padding:12px 14px;border-left:4px solid #b45309;background:#fff7ed;color:#7c2d12;border-radius:6px}' );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$disabled = get_option( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION, array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}
		$endpoint         = rest_url( 'mcp/wpbakery-uncode-mcp-server' );
		$has_adapter      = class_exists( '\WP\MCP\Core\McpAdapter' );
		$has_abilities    = function_exists( 'wp_register_ability' );
		$has_wpbakery     = defined( 'WPB_VC_VERSION' );
		$profile_password = admin_url( 'profile.php#application-passwords-section' );
		$is_https              = 0 === strpos( site_url(), 'https://' );
		$has_server_basic_auth = isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['PHP_AUTH_PW'] );
		?>
		<div class="wrap wpbakery-uncode-mcp-wrap">
			<h1><?php esc_html_e( 'MCP Tools for WPBakery + Uncode', 'wpbakery-uncode-mcp' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=connection' ) ); ?>" class="nav-tab <?php echo esc_attr( 'connection' === $tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Connection', 'wpbakery-uncode-mcp' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=tools' ) ); ?>" class="nav-tab <?php echo esc_attr( 'tools' === $tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Tools', 'wpbakery-uncode-mcp' ); ?></a>
			</h2>

			<?php if ( 'tools' === $tab ) : ?>
				<form method="post" action="options.php" class="card" style="padding:16px;">
					<?php settings_fields( self::SETTINGS_GROUP ); ?>
					<p><?php esc_html_e( 'Toggle MCP tools on/off. Disabled tools are not registered in the MCP server.', 'wpbakery-uncode-mcp' ); ?></p>
					<div class="wpbakery-uncode-mcp-actions">
						<button type="button" class="button" id="wpbakery-mcp-enable-all"><?php esc_html_e( 'Enable All', 'wpbakery-uncode-mcp' ); ?></button>
						<button type="button" class="button" id="wpbakery-mcp-disable-all"><?php esc_html_e( 'Disable All', 'wpbakery-uncode-mcp' ); ?></button>
					</div>
					<?php foreach ( $this->get_tool_groups() as $group_label => $tools ) : ?>
						<h3><?php echo esc_html( $group_label ); ?></h3>
						<?php foreach ( $tools as $slug ) : ?>
							<?php $enabled = ! in_array( $slug, $disabled, true ); ?>
							<label class="wpbakery-uncode-mcp-tool">
								<input type="checkbox" name="<?php echo esc_attr( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $enabled ); ?> />
								<span><code><?php echo esc_html( $slug ); ?></code></span>
							</label>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<?php submit_button( __( 'Save Changes', 'wpbakery-uncode-mcp' ) ); ?>
				</form>
			<?php else : ?>
				<div class="card" style="padding:16px;margin-top:12px;">
					<h2><?php esc_html_e( 'Setup Checklist', 'wpbakery-uncode-mcp' ); ?></h2>
					<p><?php esc_html_e( 'Complete all required items before connecting an AI client.', 'wpbakery-uncode-mcp' ); ?></p>
					<ul style="list-style:disc;padding-left:18px;">
						<li>
							<?php echo $has_adapter ? '[OK] ' : '[MISSING] '; ?>
							<?php esc_html_e( 'WordPress MCP Adapter is active', 'wpbakery-uncode-mcp' ); ?>
						</li>
						<li>
							<?php echo $has_abilities ? '[OK] ' : '[MISSING] '; ?>
							<?php esc_html_e( 'WordPress Abilities API is available (WordPress 6.9+)', 'wpbakery-uncode-mcp' ); ?>
						</li>
						<li>
							<?php echo $has_wpbakery ? '[OK] ' : '[MISSING] '; ?>
							<?php esc_html_e( 'WPBakery Page Builder is active', 'wpbakery-uncode-mcp' ); ?>
						</li>
						<li>
							<?php echo $is_https ? '[OK] ' : '[WARN] '; ?>
							<?php esc_html_e( 'Site uses HTTPS (recommended for remote MCP clients)', 'wpbakery-uncode-mcp' ); ?>
						</li>
					</ul>
					<p>
						<strong><?php esc_html_e( 'Authentication model:', 'wpbakery-uncode-mcp' ); ?></strong>
						<?php esc_html_e( 'AI clients call the MCP endpoint using Basic auth with a WordPress Application Password. Requests run as that WordPress user and respect user capabilities.', 'wpbakery-uncode-mcp' ); ?>
					</p>
				</div>

				<div class="card" style="padding:16px;margin-top:12px;">
					<h2><?php esc_html_e( 'Setup Tour', 'wpbakery-uncode-mcp' ); ?></h2>
					<ol style="padding-left:18px;">
						<li><?php esc_html_e( 'Activate WPBakery Page Builder, WordPress MCP Adapter, and this plugin.', 'wpbakery-uncode-mcp' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: %s: application passwords link */
								esc_html__( 'Create an Application Password in %s.', 'wpbakery-uncode-mcp' ),
								'<a href="' . esc_url( $profile_password ) . '">' . esc_html__( 'Users > Profile > Application Passwords', 'wpbakery-uncode-mcp' ) . '</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Generate credentials below using your username + app password.', 'wpbakery-uncode-mcp' ); ?></li>
						<li><?php esc_html_e( 'Use the generated Codex/Cursor JSON or the Claude Code command exactly as shown.', 'wpbakery-uncode-mcp' ); ?></li>
						<li><?php esc_html_e( 'Reconnect/restart the AI client and verify tool listing.', 'wpbakery-uncode-mcp' ); ?></li>
					</ol>
				</div>

				<div class="card" style="padding:16px;margin-top:12px;">
					<h2><?php esc_html_e( 'Server Endpoint', 'wpbakery-uncode-mcp' ); ?></h2>
					<p><code id="wpbakery-mcp-endpoint"><?php echo esc_html( $endpoint ); ?></code></p>
				</div>

				<div class="card" style="padding:16px;margin-top:12px;">
					<h2><?php esc_html_e( 'Generate Connection Configs', 'wpbakery-uncode-mcp' ); ?></h2>
					
					<?php if ( $has_server_basic_auth ) : ?>
						<div class="wpbakery-uncode-mcp-warning" style="margin-top: 0; margin-bottom: 16px;">
							<strong><?php esc_html_e( 'Server-Level Basic Auth Detected (e.g. WP Engine Staging Password Protection).', 'wpbakery-uncode-mcp' ); ?></strong><br>
							<?php esc_html_e( 'To connect successfully, we will generate a configuration containing BOTH the server staging credentials and the WordPress Application Password.', 'wpbakery-uncode-mcp' ); ?>
						</div>
					<?php endif; ?>

					<div class="wpbakery-uncode-mcp-kv">
						<label for="wpbakery-mcp-username"><?php esc_html_e( 'WP Username', 'wpbakery-uncode-mcp' ); ?></label>
						<input id="wpbakery-mcp-username" type="text" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" />
						
						<label for="wpbakery-mcp-password"><?php esc_html_e( 'WP Application Password', 'wpbakery-uncode-mcp' ); ?></label>
						<div class="wpbakery-uncode-mcp-password-row">
							<input id="wpbakery-mcp-password" type="password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" autocomplete="off" spellcheck="false" />
							<button type="button" class="button" id="wpbakery-mcp-toggle-password" aria-controls="wpbakery-mcp-password" aria-pressed="false"><?php esc_html_e( 'Show', 'wpbakery-uncode-mcp' ); ?></button>
						</div>
					</div>

					<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #eee;">
						<label>
							<input type="checkbox" id="wpbakery-mcp-use-server-auth" <?php checked( $has_server_basic_auth ); ?> />
							<strong><?php esc_html_e( 'Include Server-Level Basic Auth (e.g. WP Engine Staging Password)', 'wpbakery-uncode-mcp' ); ?></strong>
						</label>
					</div>

					<div id="wpbakery-mcp-server-auth-fields" class="wpbakery-uncode-mcp-kv" style="margin-top: 12px; <?php echo $has_server_basic_auth ? '' : 'display:none;'; ?>">
						<label for="wpbakery-mcp-server-username"><?php esc_html_e( 'Server Username', 'wpbakery-uncode-mcp' ); ?></label>
						<input id="wpbakery-mcp-server-username" type="text" placeholder="Staging Username" />
						
						<label for="wpbakery-mcp-server-password"><?php esc_html_e( 'Server Password', 'wpbakery-uncode-mcp' ); ?></label>
						<div class="wpbakery-uncode-mcp-password-row">
							<input id="wpbakery-mcp-server-password" type="password" placeholder="Staging Password" autocomplete="off" spellcheck="false" />
							<button type="button" class="button" id="wpbakery-mcp-toggle-server-password" aria-controls="wpbakery-mcp-server-password" aria-pressed="false"><?php esc_html_e( 'Show', 'wpbakery-uncode-mcp' ); ?></button>
						</div>
					</div>

					<div class="wpbakery-uncode-mcp-warning">
						<strong><?php esc_html_e( 'Treat the generated config like a secret.', 'wpbakery-uncode-mcp' ); ?></strong>
						<?php esc_html_e( 'Application Passwords grant direct access as your WordPress user. Do not share screenshots, paste configs into chat, or leave the credential visible after generation.', 'wpbakery-uncode-mcp' ); ?>
					</div>
					<p><?php esc_html_e( 'Client support differs: Codex and Cursor accept MCP JSON directly, while Claude Code works best with its official `claude mcp add` command.', 'wpbakery-uncode-mcp' ); ?></p>
					<p><button type="button" class="button button-primary" id="wpbakery-mcp-generate"><?php esc_html_e( 'Generate', 'wpbakery-uncode-mcp' ); ?></button></p>
				</div>

				<div class="wpbakery-uncode-mcp-grid" id="wpbakery-mcp-config-grid" style="margin-top:12px;"></div>

				<script>
				(function(){
					const endpoint = <?php echo wp_json_encode( $endpoint ); ?>;
					const grid = document.getElementById('wpbakery-mcp-config-grid');
					const passwordInput = document.getElementById('wpbakery-mcp-password');
					const togglePasswordButton = document.getElementById('wpbakery-mcp-toggle-password');
					const serverPasswordInput = document.getElementById('wpbakery-mcp-server-password');
					const toggleServerPasswordButton = document.getElementById('wpbakery-mcp-toggle-server-password');
					const useServerAuthCheckbox = document.getElementById('wpbakery-mcp-use-server-auth');
					const serverAuthFields = document.getElementById('wpbakery-mcp-server-auth-fields');

					useServerAuthCheckbox?.addEventListener('change', () => {
						serverAuthFields.style.display = useServerAuthCheckbox.checked ? 'grid' : 'none';
					});

					const makeCard = (title, content) => {
						const card = document.createElement('div');
						card.className = 'card';
						card.style.padding = '16px';
						const h = document.createElement('h3');
						h.textContent = title;
						const pre = document.createElement('pre');
						pre.className = 'wpbakery-uncode-mcp-code';
						pre.textContent = content;
						const btn = document.createElement('button');
						btn.className = 'button';
						btn.textContent = 'Copy';
						btn.addEventListener('click', () => navigator.clipboard.writeText(content));
						card.appendChild(h); card.appendChild(pre); card.appendChild(btn);
						return card;
					};
					const makeNoteCard = (title, content) => {
						const card = document.createElement('div');
						card.className = 'card';
						card.style.padding = '16px';
						const h = document.createElement('h3');
						h.textContent = title;
						const p = document.createElement('p');
						p.textContent = content;
						card.appendChild(h); card.appendChild(p);
						return card;
					};
					togglePasswordButton?.addEventListener('click', () => {
						passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
						togglePasswordButton.textContent = passwordInput.type === 'password' ? 'Show' : 'Hide';
						togglePasswordButton.setAttribute('aria-pressed', passwordInput.type === 'text' ? 'true' : 'false');
					});
					toggleServerPasswordButton?.addEventListener('click', () => {
						serverPasswordInput.type = serverPasswordInput.type === 'password' ? 'text' : 'password';
						toggleServerPasswordButton.textContent = serverPasswordInput.type === 'password' ? 'Show' : 'Hide';
						toggleServerPasswordButton.setAttribute('aria-pressed', serverPasswordInput.type === 'text' ? 'true' : 'false');
					});
					document.getElementById('wpbakery-mcp-generate').addEventListener('click', () => {
						const username = (document.getElementById('wpbakery-mcp-username').value || '').trim();
						const password = (passwordInput.value || '').trim();
						if (!username || !password) { alert('Enter username and application password.'); return; }
						const auth = btoa(`${username}:${password}`);

						const useServerAuth = useServerAuthCheckbox?.checked;
						const serverUser = (document.getElementById('wpbakery-mcp-server-username')?.value || '').trim();
						const serverPass = (serverPasswordInput?.value || '').trim();

						let headers = { Authorization: `Basic ${auth}` };
						let claudeCommand = `claude mcp add --transport http wpbakery-uncode-mcp ${endpoint} --header "Authorization: Basic ${auth}"`;
						let proxyEnv = `WP_URL: "${window.location.origin}"\nWP_USERNAME: "${username}"\nWP_APP_PASSWORD: "${password}"`;

						if (useServerAuth && serverUser && serverPass) {
							const serverAuth = btoa(`${serverUser}:${serverPass}`);
							headers = {
								Authorization: `Basic ${serverAuth}`,
								"X-WP-Authorization": `Basic ${auth}`
							};
							claudeCommand = `claude mcp add --transport http wpbakery-uncode-mcp ${endpoint} \\\n  --header "Authorization: Basic ${serverAuth}" \\\n  --header "X-WP-Authorization: Basic ${auth}"`;
							proxyEnv += `\nWP_SERVER_USERNAME: "${serverUser}"\nWP_SERVER_PASSWORD: "${serverPass}"`;
						}

						const base = {
							mcpServers: {
								"wpbakery-uncode-mcp": {
									type: "http",
									url: endpoint,
									headers: headers
								}
							}
						};
						grid.innerHTML = '';
						grid.appendChild(makeCard('Claude Code (`claude mcp add`)', claudeCommand));
						grid.appendChild(makeCard('Claude Code / Codex / Cursor (.mcp.json / .cursor/mcp.json)', JSON.stringify(base, null, 2)));
						grid.appendChild(makeCard('Google Antigravity / Windsurf (mcp_config.json)', JSON.stringify({ mcpServers: {"wpbakery-uncode-mcp": { serverUrl: endpoint, headers: headers } } }, null, 2)));
						grid.appendChild(makeCard('Stdio Proxy Env Setup', proxyEnv));
						grid.appendChild(makeNoteCard('Claude Desktop Note', 'Remote Claude Desktop connectors may require authless or OAuth-compatible servers. This Basic Auth endpoint is better used with Claude Code, Codex, Cursor, or other clients that support custom headers.'));
						passwordInput.value = '';
						passwordInput.type = 'password';
						togglePasswordButton.textContent = 'Show';
						togglePasswordButton.setAttribute('aria-pressed', 'false');
					});

					document.getElementById('wpbakery-mcp-enable-all')?.addEventListener('click', () => {
						document.querySelectorAll('input[type=checkbox][name="<?php echo esc_js( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]"]').forEach(el => el.checked = true);
					});
					document.getElementById('wpbakery-mcp-disable-all')?.addEventListener('click', () => {
						document.querySelectorAll('input[type=checkbox][name="<?php echo esc_js( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]"]').forEach(el => el.checked = false);
					});
				})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return string[]
	 */
	private function get_all_tool_slugs(): array {
		$all = array();
		foreach ( $this->get_tool_groups() as $tools ) {
			$all = array_merge( $all, $tools );
		}
		return array_values( array_unique( $all ) );
	}

	/**
	 * @return array<string, string[]>
	 */
	private function get_tool_groups(): array {
		return array(
			'Pages & Shortcodes' => array(
				'wpbakery-uncode-mcp/create-page',
				'wpbakery-uncode-mcp/get-page',
				'wpbakery-uncode-mcp/update-page-content',
				'wpbakery-uncode-mcp/build-shortcode',
				'wpbakery-uncode-mcp/insert-shortcode',
				'wpbakery-uncode-mcp/remove-shortcode',
				'wpbakery-uncode-mcp/list-shortcodes',
				'wpbakery-uncode-mcp/compose-hero-section',
			),
			'WPBakery API & Debug' => array(
				'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
				'wpbakery-uncode-mcp/list-wpbakery-elements',
				'wpbakery-uncode-mcp/get-wpbakery-element-definition',
				'wpbakery-uncode-mcp/update-wpbakery-element-param',
				'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
				'wpbakery-uncode-mcp/set-wpbakery-output-debug',
			),
			'Uncode Theme Options' => array(
				'wpbakery-uncode-mcp/get-theme-options',
				'wpbakery-uncode-mcp/update-theme-options',
				'wpbakery-uncode-mcp/update-theme-css-js',
				'wpbakery-uncode-mcp/discover-theme-option-keys',
				'wpbakery-uncode-mcp/get-theme-option-path',
				'wpbakery-uncode-mcp/update-theme-option-path',
				'wpbakery-uncode-mcp/upsert-theme-css-block',
				'wpbakery-uncode-mcp/validate-theme-mapping',
				'wpbakery-uncode-mcp/apply-brand-setup',
				'wpbakery-uncode-mcp/apply-style-presets',
				'wpbakery-uncode-mcp/preview-preset-impact',
				'wpbakery-uncode-mcp/run-theme-ops-batch',
				'wpbakery-uncode-mcp/export-theme-options-diff',
			),
			'Navigation/Header/Footer/Typography/Logo' => array(
				'wpbakery-uncode-mcp/update-navigation-style',
				'wpbakery-uncode-mcp/update-menu-layout',
				'wpbakery-uncode-mcp/update-megamenu-visuals',
				'wpbakery-uncode-mcp/update-menu-cta-settings',
				'wpbakery-uncode-mcp/update-header-layout',
				'wpbakery-uncode-mcp/update-footer-layout',
				'wpbakery-uncode-mcp/update-global-typography',
				'wpbakery-uncode-mcp/update-logo-settings',
				'wpbakery-uncode-mcp/update-logo-links',
				'wpbakery-uncode-mcp/sync-logo-variants',
			),
			'Snapshots & Recovery' => array(
				'wpbakery-uncode-mcp/create-theme-options-snapshot',
				'wpbakery-uncode-mcp/list-theme-options-snapshots',
				'wpbakery-uncode-mcp/restore-theme-options-snapshot',
				'wpbakery-uncode-mcp/restore-last-snapshot',
			),
			'Meta' => array(
				'wpbakery-uncode-mcp/get-uncode-meta',
				'wpbakery-uncode-mcp/update-uncode-meta',
			),
		);
	}
}
