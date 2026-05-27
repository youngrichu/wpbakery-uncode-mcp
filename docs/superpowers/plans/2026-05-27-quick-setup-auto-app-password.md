# Quick Setup – Auto Application Password Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a one-click Quick Setup button to the plugin's Connection tab that auto-creates a WordPress Application Password when WP Engine (or any server-level) Basic Auth is detected, saves the full MCP config to `wp_options`, and displays it persistently on the settings page.

**Architecture:** Three new private methods (`build_config_html`, `build_panel_html`, `render_quick_setup_panel`) and two new public AJAX handlers (`handle_quick_setup`, `handle_revoke`) are added to `WPBakery_Uncode_MCP_Admin`. A new option constant is defined in the main plugin file. The inline JS block in `render_page()` gets two delegated event handlers. The existing manual Generate section is untouched.

**Tech Stack:** PHP 8.0+, WordPress AJAX (`wp_ajax_*`), `wp_create_application_password()`, `wp_delete_application_password()`, `wp_options`, vanilla JS (`fetch`, event delegation), existing WP Admin CSS classes.

---

## File Map

| Action | File | Responsibility |
|--------|------|----------------|
| Modify | `wpbakery-uncode-mcp.php` | Add `WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION` constant |
| Modify | `includes/admin/class-admin.php` | All Quick Setup PHP logic + JS |
| Create | `tests/admin-quick-setup-smoke.php` | Smoke tests for new methods |

---

## Task 1: Add the stored-config option constant

**Files:**
- Modify: `wpbakery-uncode-mcp.php` (line 23, after the existing `WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION` define)

- [ ] **Step 1: Add the constant**

Open `wpbakery-uncode-mcp.php` and add one line after line 23:

```php
define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );
define( 'WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION',  'wpbakery_uncode_mcp_stored_config' );
```

- [ ] **Step 2: Verify PHP syntax**

```bash
cd /Users/richu/programming/Plugins/wpbakery-uncode-mcp
php -l wpbakery-uncode-mcp.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add wpbakery-uncode-mcp.php
git commit -m "feat: add WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION constant"
```

---

## Task 2: Add `build_config_html()` private helper + smoke test

**Files:**
- Modify: `includes/admin/class-admin.php`
- Create: `tests/admin-quick-setup-smoke.php`

- [ ] **Step 1: Create the failing smoke test**

Create `tests/admin-quick-setup-smoke.php`:

```php
<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION' ) ) {
    define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );
}
if ( ! defined( 'WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION' ) ) {
    define( 'WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION', 'wpbakery_uncode_mcp_stored_config' );
}
if ( ! defined( 'WPB_VC_VERSION' ) ) {
    define( 'WPB_VC_VERSION', 'test' );
}

// WordPress stubs
if ( ! function_exists( '__' ) ) {
    function __( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( string $text ): void { echo $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( string $text ): string { return $text; }
}
if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( string $text ): string { return $text; }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $value ): string { return (string) json_encode( $value ); }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( string $cap ): bool { return 'manage_options' === $cap; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( string $option, $default = false ) { return $GLOBALS['_test_options'][ $option ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( string $option, $value ): bool {
        $GLOBALS['_test_options'][ $option ] = $value;
        return true;
    }
}
if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( string $option ): bool {
        unset( $GLOBALS['_test_options'][ $option ] );
        return true;
    }
}
if ( ! function_exists( 'rest_url' ) ) {
    function rest_url( string $path = '' ): string { return 'https://example.com/wp-json/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( string $path = '' ): string { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'site_url' ) ) {
    function site_url(): string { return 'https://example.com'; }
}
if ( ! function_exists( 'wp_get_current_user' ) ) {
    function wp_get_current_user(): object { return (object) [ 'user_login' => 'admin' ]; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ): string {
        $key = strtolower( (string) $key );
        return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
    }
}
if ( ! function_exists( 'checked' ) ) {
    function checked( $checked, $current = true, $display = true ) {
        $result = ( (string) $checked === (string) $current ) ? " checked='checked'" : '';
        if ( $display ) { echo $result; }
        return $result;
    }
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
    function wp_create_nonce( string $action ): string { return 'nonce_' . $action; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( string $action ): string { return ''; }
}
if ( ! function_exists( 'wp_add_inline_style' ) ) {
    function wp_add_inline_style( string $handle, string $css ): void {}
}
if ( ! function_exists( 'settings_fields' ) ) {
    function settings_fields( string $group ): void {}
}
if ( ! function_exists( 'submit_button' ) ) {
    function submit_button( string $text ): void { echo "<button>$text</button>"; }
}

$GLOBALS['_test_options'] = [];

require_once __DIR__ . '/../includes/admin/class-admin.php';

// ── Test 1: build_config_html produces claude command + both auth headers ──
$admin = new WPBakery_Uncode_MCP_Admin();
$ref   = new ReflectionMethod( $admin, 'build_config_html' );
$ref->setAccessible( true );

$stored = [
    'app_password_uuid' => 'test-uuid-123',
    'user_id'           => 1,
    'wp_username'       => 'cci3',
    'wp_auth_b64'       => base64_encode( 'cci3:testpassword' ),
    'server_auth_b64'   => base64_encode( 'demo:serverpassword' ),
    'endpoint'          => 'https://example.com/wp-json/mcp/wpbakery-uncode-mcp-server',
];

$html = $ref->invoke( $admin, $stored );

assert( str_contains( $html, 'claude mcp add --transport http' ), 'should contain claude mcp add command' );
assert( str_contains( $html, base64_encode( 'demo:serverpassword' ) ), 'should contain server auth header value' );
assert( str_contains( $html, base64_encode( 'cci3:testpassword' ) ), 'should contain WP auth header value' );
assert( str_contains( $html, 'X-WP-Authorization' ), 'should contain X-WP-Authorization header name' );
assert( str_contains( $html, '"wpbakery-uncode-mcp"' ), 'should contain server name in JSON' );
assert( str_contains( $html, 'Treat the generated config like a secret' ), 'should contain security warning' );

echo "Test 1 (build_config_html): ok\n";
```

- [ ] **Step 2: Run the test and confirm it fails**

```bash
cd /Users/richu/programming/Plugins/wpbakery-uncode-mcp
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: Fatal error or assertion failure because `build_config_html` does not exist yet.

- [ ] **Step 3: Add `build_config_html()` to `class-admin.php`**

Add this private method inside `WPBakery_Uncode_MCP_Admin`, before the `get_all_tool_slugs()` method (around line 330):

```php
/**
 * Build config card HTML from a stored config record.
 *
 * @param array $stored Stored config from wp_options.
 * @return string
 */
private function build_config_html( array $stored ): string {
    $server_auth_b64 = (string) ( $stored['server_auth_b64'] ?? '' );
    $wp_auth_b64     = (string) ( $stored['wp_auth_b64'] ?? '' );
    $endpoint        = (string) ( $stored['endpoint'] ?? '' );

    $claude_cmd = sprintf(
        "claude mcp add --transport http wpbakery-uncode-mcp %s \\\n  --header \"Authorization: Basic %s\" \\\n  --header \"X-WP-Authorization: Basic %s\"",
        $endpoint,
        $server_auth_b64,
        $wp_auth_b64
    );

    $headers = array(
        'Authorization'      => 'Basic ' . $server_auth_b64,
        'X-WP-Authorization' => 'Basic ' . $wp_auth_b64,
    );

    $mcp_json = (string) wp_json_encode(
        array(
            'mcpServers' => array(
                'wpbakery-uncode-mcp' => array(
                    'type'    => 'http',
                    'url'     => $endpoint,
                    'headers' => $headers,
                ),
            ),
        ),
        JSON_PRETTY_PRINT
    );

    $windsurf_json = (string) wp_json_encode(
        array(
            'mcpServers' => array(
                'wpbakery-uncode-mcp' => array(
                    'serverUrl' => $endpoint,
                    'headers'   => $headers,
                ),
            ),
        ),
        JSON_PRETTY_PRINT
    );

    $configs = array(
        __( 'Claude Code (`claude mcp add`)', 'wpbakery-uncode-mcp' )                          => $claude_cmd,
        __( 'Claude Code / Codex / Cursor (.mcp.json / .cursor/mcp.json)', 'wpbakery-uncode-mcp' ) => $mcp_json,
        __( 'Google Antigravity / Windsurf (mcp_config.json)', 'wpbakery-uncode-mcp' )         => $windsurf_json,
    );

    ob_start();
    ?>
    <div class="wpbakery-uncode-mcp-grid" style="margin-top:12px;">
        <?php foreach ( $configs as $title => $content ) : ?>
            <div class="card" style="padding:16px;">
                <h3><?php echo esc_html( $title ); ?></h3>
                <pre class="wpbakery-uncode-mcp-code"><?php echo esc_html( $content ); ?></pre>
                <button type="button" class="button wpbakery-mcp-copy-btn"
                        data-clipboard="<?php echo esc_attr( $content ); ?>">
                    <?php esc_html_e( 'Copy', 'wpbakery-uncode-mcp' ); ?>
                </button>
            </div>
        <?php endforeach; ?>
        <div class="card" style="padding:16px;">
            <h3><?php esc_html_e( 'Claude Desktop Note', 'wpbakery-uncode-mcp' ); ?></h3>
            <p><?php esc_html_e( 'Remote Claude Desktop connectors may require authless or OAuth-compatible servers. This Basic Auth endpoint is better used with Claude Code, Codex, Cursor, or other clients that support custom headers.', 'wpbakery-uncode-mcp' ); ?></p>
        </div>
    </div>
    <div class="wpbakery-uncode-mcp-warning" style="margin-top:12px;">
        <strong><?php esc_html_e( 'Treat the generated config like a secret.', 'wpbakery-uncode-mcp' ); ?></strong>
        <?php esc_html_e( 'Application Passwords grant direct access as your WordPress user. Do not share screenshots, paste configs into chat, or leave the credential visible after generation.', 'wpbakery-uncode-mcp' ); ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
```

- [ ] **Step 4: Run the test — confirm it passes**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: `Test 1 (build_config_html): ok`

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-admin.php tests/admin-quick-setup-smoke.php
git commit -m "feat: add build_config_html helper + smoke test"
```

---

## Task 3: Add `build_panel_html()` private method + tests

**Files:**
- Modify: `includes/admin/class-admin.php`
- Modify: `tests/admin-quick-setup-smoke.php`

- [ ] **Step 1: Add three failing tests to `tests/admin-quick-setup-smoke.php`**

Append to `tests/admin-quick-setup-smoke.php` (after the existing Test 1 block):

```php
// ── Test 2: build_panel_html returns empty string when no server auth and no stored config ──
$GLOBALS['_test_options'] = [];
unset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );

$ref2 = new ReflectionMethod( $admin, 'build_panel_html' );
$ref2->setAccessible( true );
$panel_html = $ref2->invoke( $admin );

assert( '' === $panel_html, 'panel should be empty when no server auth and no stored config' );
echo "Test 2 (build_panel_html hidden state): ok\n";

// ── Test 3: build_panel_html shows Quick Setup button when server auth detected ──
$GLOBALS['_test_options'] = [];
$_SERVER['PHP_AUTH_USER'] = 'demo';
$_SERVER['PHP_AUTH_PW']   = 'serverpassword';

$panel_html = $ref2->invoke( $admin );

assert( str_contains( $panel_html, 'wpbakery-mcp-quick-setup-panel' ), 'panel wrapper should be present' );
assert( str_contains( $panel_html, 'wpbakery-mcp-quick-setup' ), 'Quick Setup button should be present' );
assert( str_contains( $panel_html, 'Quick Setup Available' ), 'State A heading should be present' );
assert( ! str_contains( $panel_html, 'wpbakery-mcp-revoke' ), 'Revoke button should NOT be present in State A' );
assert( str_contains( $panel_html, 'data-setup-nonce' ), 'setup nonce attribute should be present' );
assert( str_contains( $panel_html, 'data-revoke-nonce' ), 'revoke nonce attribute should be present' );
echo "Test 3 (build_panel_html State A): ok\n";

// ── Test 4: build_panel_html shows stored configs and Revoke button when config exists ──
$GLOBALS['_test_options'][ WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION ] = [
    'app_password_uuid' => 'uuid-abc',
    'user_id'           => 1,
    'wp_username'       => 'cci3',
    'wp_auth_b64'       => base64_encode( 'cci3:storedpass' ),
    'server_auth_b64'   => base64_encode( 'demo:serverpassword' ),
    'endpoint'          => 'https://example.com/wp-json/mcp/wpbakery-uncode-mcp-server',
];

$panel_html = $ref2->invoke( $admin );

assert( str_contains( $panel_html, 'MCP Quick Setup Complete' ), 'State B heading should be present' );
assert( str_contains( $panel_html, 'wpbakery-mcp-revoke' ), 'Revoke button should be present in State B' );
assert( str_contains( $panel_html, 'claude mcp add --transport http' ), 'claude command should be present in State B' );
assert( ! str_contains( $panel_html, 'wpbakery-mcp-quick-setup"' ), 'Quick Setup button should NOT be in State B' );
echo "Test 4 (build_panel_html State B): ok\n";
```

- [ ] **Step 2: Run — confirm the three new tests fail**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: Passes Test 1, then fails on Test 2 with "method not found" or similar.

- [ ] **Step 3: Add `build_panel_html()` to `class-admin.php`**

Add this private method directly after `build_config_html()`:

```php
/**
 * Build the complete Quick Setup panel HTML (outer wrapper + state-specific content).
 * Returns empty string when the panel should not be shown.
 *
 * @return string
 */
private function build_panel_html(): string {
    $has_server_basic_auth = isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['PHP_AUTH_PW'] );
    $stored                = get_option( WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION, null );

    if ( ! $has_server_basic_auth && ! $stored ) {
        return '';
    }

    $setup_nonce  = wp_create_nonce( 'wpbakery_uncode_mcp_quick_setup_nonce' );
    $revoke_nonce = wp_create_nonce( 'wpbakery_uncode_mcp_revoke_nonce' );

    ob_start();
    ?>
    <div id="wpbakery-mcp-quick-setup-panel"
         data-setup-nonce="<?php echo esc_attr( $setup_nonce ); ?>"
         data-revoke-nonce="<?php echo esc_attr( $revoke_nonce ); ?>">
        <?php if ( $stored && is_array( $stored ) ) : ?>
            <div class="card" style="padding:16px;margin-top:12px;border-left:4px solid #16a34a;background:#f0fdf4;">
                <h2><?php esc_html_e( '✓ MCP Quick Setup Complete', 'wpbakery-uncode-mcp' ); ?></h2>
                <p><?php esc_html_e( 'Application Password created and configs saved. Use any config below to connect your AI client.', 'wpbakery-uncode-mcp' ); ?></p>
                <?php echo $this->build_config_html( $stored ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <p style="margin-top:12px;">
                    <button type="button" class="button" id="wpbakery-mcp-revoke">
                        <?php esc_html_e( 'Revoke &amp; Regenerate', 'wpbakery-uncode-mcp' ); ?>
                    </button>
                </p>
            </div>
        <?php else : ?>
            <div class="card" style="padding:16px;margin-top:12px;border-left:4px solid #b45309;background:#fff7ed;">
                <h2><?php esc_html_e( 'Quick Setup Available', 'wpbakery-uncode-mcp' ); ?></h2>
                <p><?php esc_html_e( 'Server-level auth (WP Engine staging) detected. Click below to automatically create a WordPress Application Password and save the full MCP config here — no need to visit your profile page.', 'wpbakery-uncode-mcp' ); ?></p>
                <button type="button" class="button button-primary" id="wpbakery-mcp-quick-setup">
                    <?php esc_html_e( 'Quick Setup – Create Application Password', 'wpbakery-uncode-mcp' ); ?>
                </button>
                <span id="wpbakery-mcp-quick-setup-error" style="color:#b91c1c;margin-left:8px;display:none;"></span>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
```

- [ ] **Step 4: Run — confirm all four tests pass**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected:
```
Test 1 (build_config_html): ok
Test 2 (build_panel_html hidden state): ok
Test 3 (build_panel_html State A): ok
Test 4 (build_panel_html State B): ok
```

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-admin.php tests/admin-quick-setup-smoke.php
git commit -m "feat: add build_panel_html with three-state rendering"
```

---

## Task 4: Add `render_quick_setup_panel()` and wire into `render_page()`

**Files:**
- Modify: `includes/admin/class-admin.php`
- Modify: `tests/admin-quick-setup-smoke.php`

- [ ] **Step 1: Add failing test**

Append to `tests/admin-quick-setup-smoke.php`:

```php
// ── Test 5: render_page() includes quick setup panel when server auth is set ──
$GLOBALS['_test_options'] = [];
$_SERVER['PHP_AUTH_USER'] = 'demo';
$_SERVER['PHP_AUTH_PW']   = 'serverpassword';
$_GET['tab'] = 'connection';

ob_start();
$admin->render_page();
$page_html = (string) ob_get_clean();

assert( str_contains( $page_html, 'wpbakery-mcp-quick-setup-panel' ), 'render_page should include the quick setup panel' );
assert( str_contains( $page_html, 'wpbakery-mcp-quick-setup"' ), 'render_page should include Quick Setup button' );
echo "Test 5 (render_page includes panel): ok\n";
```

- [ ] **Step 2: Run — confirm Test 5 fails**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: Tests 1–4 pass, Test 5 fails because the panel is not yet wired into `render_page()`.

- [ ] **Step 3: Add `render_quick_setup_panel()` public method**

Add this public method in `class-admin.php`, directly after `build_panel_html()`:

```php
/**
 * Output the Quick Setup panel. Called from render_page() on the Connection tab.
 */
public function render_quick_setup_panel(): void {
    echo $this->build_panel_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
```

- [ ] **Step 4: Wire `render_quick_setup_panel()` into `render_page()`**

In `render_page()`, find the "Generate Connection Configs" card opening (around line 168):

```php
<div class="card" style="padding:16px;margin-top:12px;">
    <h2><?php esc_html_e( 'Generate Connection Configs', 'wpbakery-uncode-mcp' ); ?></h2>
```

Insert a call to `render_quick_setup_panel()` on the line immediately before that `<div class="card"...>`:

```php
<?php $this->render_quick_setup_panel(); ?>

<div class="card" style="padding:16px;margin-top:12px;">
    <h2><?php esc_html_e( 'Generate Connection Configs', 'wpbakery-uncode-mcp' ); ?></h2>
```

- [ ] **Step 5: Run — confirm all five tests pass**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: All five tests print `ok`.

- [ ] **Step 6: Verify existing smoke test still passes**

```bash
php tests/admin-connection-security-smoke.php 2>&1
```

Expected: `admin-connection-security-smoke: ok`

- [ ] **Step 7: Commit**

```bash
git add includes/admin/class-admin.php tests/admin-quick-setup-smoke.php
git commit -m "feat: add render_quick_setup_panel and wire into render_page"
```

---

## Task 5: Add `handle_quick_setup()` AJAX handler

**Files:**
- Modify: `includes/admin/class-admin.php`
- Modify: `tests/admin-quick-setup-smoke.php`

- [ ] **Step 1: Add failing test**

Append to `tests/admin-quick-setup-smoke.php`:

```php
// ── Test 6: handle_quick_setup creates app password and stores config ──

// Additional stubs needed for AJAX handler
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    function wp_verify_nonce( $nonce, string $action ): bool { return true; }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id(): int { return 1; }
}
if ( ! function_exists( 'wp_create_application_password' ) ) {
    function wp_create_application_password( int $user_id, array $args ): array {
        return [ 'raw_app_pass_xyz', [ 'uuid' => 'auto-uuid-abc' ] ];
    }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool { return false; }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data ): void {
        $GLOBALS['_test_json_response'] = [ 'success' => true, 'data' => $data ];
    }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data ): void {
        $GLOBALS['_test_json_response'] = [ 'success' => false, 'data' => $data ];
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $text ): string { return trim( $text ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
}

$GLOBALS['_test_options'] = [];
$GLOBALS['_test_json_response'] = null;
$_SERVER['PHP_AUTH_USER'] = 'demo';
$_SERVER['PHP_AUTH_PW']   = 'serverpassword';
$_POST['nonce'] = 'nonce_wpbakery_uncode_mcp_quick_setup_nonce';

$admin->handle_quick_setup();

$response = $GLOBALS['_test_json_response'];
assert( true === ( $response['success'] ?? false ), 'handle_quick_setup should return success' );
assert( isset( $response['data']['html'] ), 'response should contain html key' );
assert( str_contains( $response['data']['html'], 'MCP Quick Setup Complete' ), 'response html should be State B' );

$saved = $GLOBALS['_test_options'][ WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION ] ?? null;
assert( null !== $saved, 'handle_quick_setup should save to wp_options' );
assert( 'auto-uuid-abc' === ( $saved['app_password_uuid'] ?? '' ), 'stored UUID should match' );
assert( isset( $saved['wp_auth_b64'] ), 'stored config should have wp_auth_b64' );
assert( isset( $saved['server_auth_b64'] ), 'stored config should have server_auth_b64' );

echo "Test 6 (handle_quick_setup): ok\n";
```

- [ ] **Step 2: Run — confirm Test 6 fails**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: Tests 1–5 pass, Test 6 fails because `handle_quick_setup` doesn't exist.

- [ ] **Step 3: Add `handle_quick_setup()` to `class-admin.php`**

Add this public method after `render_quick_setup_panel()`:

```php
/**
 * AJAX handler: create Application Password, store config, return new panel HTML.
 * Hooked to wp_ajax_wpbakery_uncode_mcp_quick_setup.
 */
public function handle_quick_setup(): void {
    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
        'wpbakery_uncode_mcp_quick_setup_nonce'
    ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpbakery-uncode-mcp' ) ) );
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpbakery-uncode-mcp' ) ) );
        return;
    }

    $server_user = (string) ( $_SERVER['PHP_AUTH_USER'] ?? '' );
    $server_pass = (string) ( $_SERVER['PHP_AUTH_PW'] ?? '' );

    if ( '' === $server_user || '' === $server_pass ) {
        wp_send_json_error( array( 'message' => __( 'Server credentials unavailable — use the manual Generate section below.', 'wpbakery-uncode-mcp' ) ) );
        return;
    }

    $user_id = get_current_user_id();
    $result  = wp_create_application_password(
        $user_id,
        array( 'name' => __( 'WPBakery MCP – Auto', 'wpbakery-uncode-mcp' ) )
    );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        return;
    }

    /** @var array{0: string, 1: array{uuid: string}} $result */
    [ $raw_password, $item ] = $result;

    $wp_username     = wp_get_current_user()->user_login;
    $wp_auth_b64     = base64_encode( $wp_username . ':' . $raw_password );
    $server_auth_b64 = base64_encode( $server_user . ':' . $server_pass );
    $endpoint        = rest_url( 'mcp/wpbakery-uncode-mcp-server' );

    $stored = array(
        'app_password_uuid' => $item['uuid'],
        'user_id'           => $user_id,
        'wp_username'       => $wp_username,
        'wp_auth_b64'       => $wp_auth_b64,
        'server_auth_b64'   => $server_auth_b64,
        'endpoint'          => $endpoint,
    );

    update_option( WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION, $stored );

    wp_send_json_success( array( 'html' => $this->build_panel_html() ) );
}
```

- [ ] **Step 4: Run — confirm all six tests pass**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: All six tests print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-admin.php tests/admin-quick-setup-smoke.php
git commit -m "feat: add handle_quick_setup AJAX handler"
```

---

## Task 6: Add `handle_revoke()` AJAX handler

**Files:**
- Modify: `includes/admin/class-admin.php`
- Modify: `tests/admin-quick-setup-smoke.php`

- [ ] **Step 1: Add failing test**

Append to `tests/admin-quick-setup-smoke.php`:

```php
// ── Test 7: handle_revoke deletes app password, clears wp_options, returns State A HTML ──

if ( ! function_exists( 'wp_delete_application_password' ) ) {
    function wp_delete_application_password( int $user_id, string $uuid ): bool {
        $GLOBALS['_test_revoked'][] = compact( 'user_id', 'uuid' );
        return true;
    }
}

$GLOBALS['_test_options'][ WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION ] = [
    'app_password_uuid' => 'uuid-to-revoke',
    'user_id'           => 1,
    'wp_username'       => 'cci3',
    'wp_auth_b64'       => base64_encode( 'cci3:somepass' ),
    'server_auth_b64'   => base64_encode( 'demo:serverpassword' ),
    'endpoint'          => 'https://example.com/wp-json/mcp/wpbakery-uncode-mcp-server',
];
$GLOBALS['_test_json_response'] = null;
$GLOBALS['_test_revoked']       = [];
$_SERVER['PHP_AUTH_USER'] = 'demo';
$_SERVER['PHP_AUTH_PW']   = 'serverpassword';
$_POST['nonce'] = 'nonce_wpbakery_uncode_mcp_revoke_nonce';

$admin->handle_revoke();

$response = $GLOBALS['_test_json_response'];
assert( true === ( $response['success'] ?? false ), 'handle_revoke should return success' );
assert( isset( $response['data']['html'] ), 'response should contain html' );
assert( str_contains( $response['data']['html'], 'Quick Setup Available' ), 'response should be State A after revoke' );
assert( ! isset( $GLOBALS['_test_options'][ WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION ] ), 'stored config should be deleted' );
assert( count( $GLOBALS['_test_revoked'] ) === 1, 'wp_delete_application_password should be called once' );
assert( 'uuid-to-revoke' === $GLOBALS['_test_revoked'][0]['uuid'], 'correct UUID should be revoked' );

echo "Test 7 (handle_revoke): ok\n";
```

- [ ] **Step 2: Run — confirm Test 7 fails**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: Tests 1–6 pass, Test 7 fails because `handle_revoke` doesn't exist.

- [ ] **Step 3: Add `handle_revoke()` to `class-admin.php`**

Add this public method directly after `handle_quick_setup()`:

```php
/**
 * AJAX handler: revoke Application Password, clear stored config, return State A panel HTML.
 * Hooked to wp_ajax_wpbakery_uncode_mcp_revoke.
 */
public function handle_revoke(): void {
    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ),
        'wpbakery_uncode_mcp_revoke_nonce'
    ) ) {
        wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wpbakery-uncode-mcp' ) ) );
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wpbakery-uncode-mcp' ) ) );
        return;
    }

    $stored = get_option( WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION, null );
    if ( $stored && is_array( $stored ) && isset( $stored['app_password_uuid'], $stored['user_id'] ) ) {
        wp_delete_application_password( (int) $stored['user_id'], (string) $stored['app_password_uuid'] );
    }

    delete_option( WPBAKERY_UNCODE_MCP_STORED_CONFIG_OPTION );

    wp_send_json_success( array( 'html' => $this->build_panel_html() ) );
}
```

- [ ] **Step 4: Run — confirm all seven tests pass**

```bash
php tests/admin-quick-setup-smoke.php 2>&1
```

Expected: All seven tests print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-admin.php tests/admin-quick-setup-smoke.php
git commit -m "feat: add handle_revoke AJAX handler"
```

---

## Task 7: Register AJAX hooks in `init()`

**Files:**
- Modify: `includes/admin/class-admin.php`

- [ ] **Step 1: Add the two hook registrations**

In `class-admin.php`, find the `init()` method:

```php
public function init(): void {
    add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
    add_action( 'admin_init', array( $this, 'register_settings' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
}
```

Replace it with:

```php
public function init(): void {
    add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
    add_action( 'admin_init', array( $this, 'register_settings' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    add_action( 'wp_ajax_wpbakery_uncode_mcp_quick_setup', array( $this, 'handle_quick_setup' ) );
    add_action( 'wp_ajax_wpbakery_uncode_mcp_revoke',       array( $this, 'handle_revoke' ) );
}
```

- [ ] **Step 2: Verify PHP syntax**

```bash
php -l includes/admin/class-admin.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Run full test suite**

```bash
php tests/admin-quick-setup-smoke.php && php tests/admin-connection-security-smoke.php
```

Expected:
```
Test 1 (build_config_html): ok
Test 2 (build_panel_html hidden state): ok
Test 3 (build_panel_html State A): ok
Test 4 (build_panel_html State B): ok
Test 5 (render_page includes panel): ok
Test 6 (handle_quick_setup): ok
Test 7 (handle_revoke): ok
admin-connection-security-smoke: ok
```

- [ ] **Step 4: Commit**

```bash
git add includes/admin/class-admin.php
git commit -m "feat: register quick_setup and revoke AJAX hooks in init()"
```

---

## Task 8: Add JS handlers to the inline script in `render_page()`

**Files:**
- Modify: `includes/admin/class-admin.php`

- [ ] **Step 1: Locate the existing inline script block**

In `render_page()`, find the closing of the existing `(function(){...})();` block — it ends just before `</script>` around line 320. The last line before `})();` is:

```javascript
document.getElementById('wpbakery-mcp-disable-all')?.addEventListener('click', () => {
    document.querySelectorAll('input[type=checkbox][name="<?php echo esc_js( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]"]').forEach(el => el.checked = false);
});
```

- [ ] **Step 2: Add three new JS blocks inside the IIFE, just before the closing `})();`**

Find this exact line (it is the last statement inside the IIFE):

```javascript
					document.getElementById('wpbakery-mcp-disable-all')?.addEventListener('click', () => {
						document.querySelectorAll('input[type=checkbox][name="<?php echo esc_js( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]"]').forEach(el => el.checked = false);
					});
				})();
```

Replace it with:

```javascript
					document.getElementById('wpbakery-mcp-disable-all')?.addEventListener('click', () => {
						document.querySelectorAll('input[type=checkbox][name="<?php echo esc_js( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION ); ?>[]"]').forEach(el => el.checked = false);
					});

					// ── Quick Setup & Revoke: delegated handlers ──
					document.addEventListener('click', function(e) {
						const panel = () => document.getElementById('wpbakery-mcp-quick-setup-panel');

						// Copy buttons inside the stored config grid
						if (e.target && e.target.classList.contains('wpbakery-mcp-copy-btn')) {
							const content = e.target.getAttribute('data-clipboard') || '';
							navigator.clipboard.writeText(content).catch(() => {});
							return;
						}

						// Quick Setup button
						if (e.target && e.target.id === 'wpbakery-mcp-quick-setup') {
							const btn = e.target;
							const p = panel();
							if (!p) return;
							const nonce = p.getAttribute('data-setup-nonce') || '';
							btn.disabled = true;
							btn.textContent = <?php echo wp_json_encode( __( 'Creating…', 'wpbakery-uncode-mcp' ) ); ?>;
							const errEl = document.getElementById('wpbakery-mcp-quick-setup-error');

							const body = new URLSearchParams({ action: 'wpbakery_uncode_mcp_quick_setup', nonce });
							fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' })
								.then(r => r.json())
								.then(data => {
									if (data.success && data.data && data.data.html) {
										const tmp = document.createElement('div');
										tmp.innerHTML = data.data.html;
										p.replaceWith(tmp.firstElementChild);
									} else {
										const msg = (data.data && data.data.message) || <?php echo wp_json_encode( __( 'Setup failed. Try the manual section below.', 'wpbakery-uncode-mcp' ) ); ?>;
										if (errEl) { errEl.textContent = msg; errEl.style.display = ''; }
										btn.disabled = false;
										btn.textContent = <?php echo wp_json_encode( __( 'Quick Setup – Create Application Password', 'wpbakery-uncode-mcp' ) ); ?>;
									}
								})
								.catch(() => {
									if (errEl) { errEl.textContent = <?php echo wp_json_encode( __( 'Network error. Please try again.', 'wpbakery-uncode-mcp' ) ); ?>; errEl.style.display = ''; }
									btn.disabled = false;
									btn.textContent = <?php echo wp_json_encode( __( 'Quick Setup – Create Application Password', 'wpbakery-uncode-mcp' ) ); ?>;
								});
							return;
						}

						// Revoke & Regenerate button
						if (e.target && e.target.id === 'wpbakery-mcp-revoke') {
							if (!confirm(<?php echo wp_json_encode( __( 'This will revoke the existing Application Password. Continue?', 'wpbakery-uncode-mcp' ) ); ?>)) return;
							const p = panel();
							if (!p) return;
							const nonce = p.getAttribute('data-revoke-nonce') || '';
							e.target.disabled = true;

							const body = new URLSearchParams({ action: 'wpbakery_uncode_mcp_revoke', nonce });
							fetch(ajaxurl, { method: 'POST', body, credentials: 'same-origin' })
								.then(r => r.json())
								.then(data => {
									if (data.success && data.data && data.data.html) {
										const tmp = document.createElement('div');
										tmp.innerHTML = data.data.html;
										p.replaceWith(tmp.firstElementChild);
									}
								})
								.catch(() => { e.target.disabled = false; });
							return;
						}
					});
				})();
```

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l includes/admin/class-admin.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Run full test suite one more time**

```bash
php tests/admin-quick-setup-smoke.php && php tests/admin-connection-security-smoke.php
```

Expected: All 7 quick-setup tests + 1 security smoke test print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/admin/class-admin.php
git commit -m "feat: add Quick Setup and Revoke JS event handlers"
```

---

## Task 9: Final integration smoke + cleanup

**Files:**
- Modify: `tests/admin-quick-setup-smoke.php` (add summary line)

- [ ] **Step 1: Add final summary echo to the test file**

Append to `tests/admin-quick-setup-smoke.php`:

```php
echo "admin-quick-setup-smoke: ok\n";
```

- [ ] **Step 2: Run complete test suite**

```bash
php tests/admin-quick-setup-smoke.php && php tests/admin-connection-security-smoke.php && php tests/shortcode-service-smoke.php
```

Expected:
```
Test 1 (build_config_html): ok
Test 2 (build_panel_html hidden state): ok
Test 3 (build_panel_html State A): ok
Test 4 (build_panel_html State B): ok
Test 5 (render_page includes panel): ok
Test 6 (handle_quick_setup): ok
Test 7 (handle_revoke): ok
admin-quick-setup-smoke: ok
admin-connection-security-smoke: ok
shortcode-service-smoke: ok
```

- [ ] **Step 3: Final commit**

```bash
git add tests/admin-quick-setup-smoke.php
git commit -m "test: finalize admin-quick-setup-smoke summary line"
```

---

## Done

The feature is complete when:
- All 3 smoke tests pass
- The WordPress admin page shows the amber Quick Setup panel when `PHP_AUTH_USER` is set
- Clicking "Quick Setup – Create Application Password" creates a WP Application Password, saves the full config to `wp_options`, and replaces the panel with State B (green, showing claude command + JSON configs)
- Clicking "Revoke & Regenerate" revokes the password, clears `wp_options`, and restores State A
- The existing manual "Generate Connection Configs" section is unchanged and still works
