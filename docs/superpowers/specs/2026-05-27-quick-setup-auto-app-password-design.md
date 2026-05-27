# Design: Quick Setup — Auto Application Password for Server-Level Auth

**Date:** 2026-05-27  
**Plugin:** wpbakery-uncode-mcp  
**Status:** Approved

---

## Problem

When WordPress is hosted behind server-level Basic Auth (e.g. WP Engine staging password protection), connecting an AI client requires two credential layers:

1. **Server-level auth** — e.g. `demo:fdb7ff3aafe9` (nginx/WP Engine layer)
2. **WordPress Application Password** — e.g. `cci3:jdP28x2iH32LcUGX` (WordPress layer)

Currently the plugin detects the server-level auth and pre-fills those fields, but the user must still manually navigate to **Users > Profile > Application Passwords**, create a password, copy it, return to the settings page, paste it, and click Generate. This is fragile and error-prone.

---

## Goal

Add a **one-click Quick Setup** that auto-creates a WordPress Application Password, combines it with the already-known server credentials, saves the full config to the settings page, and shows it there permanently — so the admin never has to leave the plugin settings page.

---

## Scope

- Only activates when `$_SERVER['PHP_AUTH_USER']` is set (server-level auth detected)
- Single PHP file change: `includes/admin/class-admin.php`
- No new files, no new dependencies
- Existing manual "Generate Connection Configs" section is **unchanged**

---

## Data Storage

A single `wp_options` entry keyed `wpbakery_uncode_mcp_stored_config` stores:

```json
{
  "app_password_uuid": "xxx-xxx-xxx",
  "user_id": 1,
  "wp_auth_b64": "Y2NpMzo...",
  "server_auth_b64": "ZGVtbzo...",
  "endpoint": "https://site.wpenginepowered.com/wp-json/mcp/wpbakery-uncode-mcp-server"
}
```

- `app_password_uuid` — WP's UUID for the created password; used by `wp_delete_application_password()` to revoke
- `user_id` — the user who owns the password (current admin at time of creation)
- `wp_auth_b64` — `base64(wp_username:app_password)` — for `X-WP-Authorization` header
- `server_auth_b64` — `base64(server_user:server_pass)` — for `Authorization` header
- `endpoint` — snapshot of the MCP REST endpoint URL at time of creation

The **raw Application Password is never stored** — only its base64-encoded form, which is all the `claude mcp add` command needs.

---

## UI States

The Quick Setup panel renders in the Connection tab, **above** the existing "Generate Connection Configs" card. It has three states:

| State | Condition | Display |
|-------|-----------|---------|
| **Hidden** | No server auth detected, no stored config | Panel not rendered |
| **State A** | Server auth detected, no stored config | Amber warning + "Quick Setup" button |
| **State B** | Stored config exists | Green success panel + saved config cards + "Revoke & Regenerate" button |

State B shows the same four config outputs as the existing Generate section:
- `claude mcp add` command
- `.mcp.json` / `.cursor/mcp.json` JSON
- `mcp_config.json` (Windsurf/Antigravity)
- Stdio Proxy Env vars

---

## PHP Components

All changes are in `WPBakery_Uncode_MCP_Admin`.

### New methods

**`render_quick_setup_panel(): void`**  
Called inside `render_page()` on the Connection tab, just above the existing Generate card.  
Reads `wpbakery_uncode_mcp_stored_config` from `wp_options`.  
Renders State A, B, or nothing based on conditions above.  
Outputs a `<div id="wpbakery-mcp-quick-setup-panel">` wrapper so JS can swap it in-place.

**`handle_quick_setup(): void`** (AJAX handler — `wp_ajax_wpbakery_uncode_mcp_quick_setup`)  
1. Verifies nonce (`wpbakery_uncode_mcp_quick_setup_nonce`) and `manage_options` capability — `wp_die` on failure  
2. Reads `$_SERVER['PHP_AUTH_USER']` and `$_SERVER['PHP_AUTH_PW']`  
3. Calls `wp_create_application_password( get_current_user_id(), ['name' => 'WPBakery MCP – Auto'] )`  
4. Base64-encodes both credential pairs  
5. Saves the `wp_options` record  
6. Returns `wp_send_json_success(['html' => $state_b_html])`

**`handle_revoke(): void`** (AJAX handler — `wp_ajax_wpbakery_uncode_mcp_revoke`)  
1. Verifies nonce (`wpbakery_uncode_mcp_revoke_nonce`) and `manage_options` capability  
2. Reads stored config, calls `wp_delete_application_password( $user_id, $uuid )`  
3. Deletes `wp_options` entry  
4. Returns `wp_send_json_success(['html' => $state_a_html])`

**`build_config_html( array $stored ): string`** (private helper)  
Reconstructs the four config card outputs from a stored config array.  
Used by both `render_quick_setup_panel()` (State B) and the AJAX response from `handle_quick_setup()` — single source of truth for config output rendering.

### Hook registrations (added to `init()`)
```php
add_action( 'wp_ajax_wpbakery_uncode_mcp_quick_setup', [ $this, 'handle_quick_setup' ] );
add_action( 'wp_ajax_wpbakery_uncode_mcp_revoke',       [ $this, 'handle_revoke' ] );
```

---

## JavaScript

Two new handlers added to the existing inline `<script>` block:

**Quick Setup click:**
1. Disable button, set label to "Creating…"
2. `fetch` POST to `ajaxurl` with `action=wpbakery_uncode_mcp_quick_setup` + nonce
3. On success → replace `#wpbakery-mcp-quick-setup-panel` innerHTML with returned `html`
4. On error → show inline error message, re-enable button

**Revoke & Regenerate click (delegated, since button is injected dynamically):**
1. `confirm()` — *"This will revoke the existing Application Password. Continue?"*
2. `fetch` POST to `ajaxurl` with `action=wpbakery_uncode_mcp_revoke` + nonce
3. On success → replace `#wpbakery-mcp-quick-setup-panel` innerHTML with returned `html`

Nonces are embedded as `data-nonce` attributes on the `<div id="wpbakery-mcp-quick-setup-panel">` wrapper rendered by PHP — e.g. `data-setup-nonce="..."` and `data-revoke-nonce="..."`. JS reads them from the wrapper element.

---

## Error Handling

| Scenario | Behaviour |
|----------|-----------|
| `wp_create_application_password` fails | AJAX returns error; inline message shown; button re-enabled |
| Server auth vars missing at AJAX time | Return error: "Server credentials unavailable — use manual setup below" |
| Revoke called but UUID not found in WP | Delete `wp_options` entry anyway; return success (idempotent) |
| Stored config present but app password already revoked externally | State B still shows the config (credentials are still valid for connecting); Revoke just clears the stored record |

---

## Security

- Both AJAX handlers verify a per-action nonce and `manage_options` capability
- Raw Application Password never persisted — only the base64-encoded form
- `$_SERVER['PHP_AUTH_PW']` used only at AJAX request time (server-level password already known to the server; not introduced by this feature)
- Config cards in State B include the existing "Treat the generated config like a secret" warning

---

## Out of Scope

- Auto-creating app passwords when there is **no** server-level auth (manual flow handles that)
- Multi-user support (stores config for the current admin; other admins use manual flow or run Quick Setup themselves)
- Scheduling / expiry of Application Passwords
- Any changes to the Tools tab or MCP tool registration
