# MCP Tools for WPBakery + Uncode

A WordPress plugin that extends the WordPress MCP Adapter to expose WPBakery and Uncode actions as MCP tools. This lets Codex, Claude Code, Claude Desktop, and other MCP clients read and modify page builder content programmatically.

## What This Gives You (v1)

- Create pages/posts with WPBakery shortcode content
- Read raw and rendered page content
- Replace/append/prepend page content
- Build shortcode strings from structured inputs
- Insert or remove shortcodes in page content
- List all shortcode tags used in a page
- Read and update Uncode post meta (`_uncode_*`, `uncode_*`)
- Read and update Uncode Theme Options (including custom CSS/JS)
- Compose a ready-made hero section in one call

## Requirements

- WordPress `6.9+`
- PHP `8.0+`
- [WPBakery Page Builder](https://wpbakery.com/) active
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) active
- WordPress Abilities API (`wp_register_ability`) available (WP 6.9+)

## How Authentication Works

- This plugin does **not** create a custom auth layer.
- AI clients authenticate to WordPress MCP endpoint using **Basic Auth + WordPress Application Password**.
- Requests execute with the permissions of that WordPress user (for example `manage_options`, `edit_posts`).
- WordPress MCP Adapter handles MCP transport/session behavior.

## Install

1. Copy this plugin folder into `wp-content/plugins/wpbakery-uncode-mcp`.
2. Activate **MCP Tools for WPBakery + Uncode**.
3. Ensure WPBakery and MCP Adapter are active.
4. Create an Application Password in **Users > Profile > Application Passwords**.

Endpoint:

`https://your-site.com/wp-json/mcp/wpbakery-uncode-mcp-server`

Admin panel:
- Go to `Settings > WPBakery MCP`
- `Connection` tab: generate connection details for Codex, Claude Code, Cursor, and similar clients
- `Tools` tab: enable/disable individual MCP tools

## Setup Tour

1. Activate WPBakery Page Builder, WordPress MCP Adapter, and this plugin.
2. Create a WordPress Application Password for the user account you want AI to act as.
3. Open `Settings > WPBakery MCP > Connection`.
4. Enter username + application password and click **Generate**.
5. Use the generated instructions that match your client:
   - Codex/Cursor: paste the generated JSON into the MCP config those apps expect.
   - Claude Code: run the generated `claude mcp add --transport http ... --header ...` command.
6. Reconnect/restart the AI client and confirm MCP tools are visible.

## Codex / Cursor (HTTP JSON)

```json
{
  "mcpServers": {
    "wpbakery-uncode-mcp": {
      "type": "http",
      "url": "https://your-site.com/wp-json/mcp/wpbakery-uncode-mcp-server",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

## Claude Code (recommended)

Claude Code works best with its official CLI registration flow:

```bash
claude mcp add --transport http wpbakery-uncode-mcp \
  https://your-site.com/wp-json/mcp/wpbakery-uncode-mcp-server \
  --header "Authorization: Basic BASE64_ENCODED_CREDENTIALS"
```

Then restart Claude Code and confirm the MCP tools appear.

## Stdio Proxy (optional)

```json
{
  "mcpServers": {
    "wpbakery-uncode-mcp": {
      "type": "stdio",
      "command": "node",
      "args": ["/path/to/wp-content/plugins/wpbakery-uncode-mcp/bin/mcp-proxy.mjs"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USERNAME": "admin",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

## Claude Desktop

Claude Desktop support for remote MCP servers may differ from Claude Code. This plugin uses Basic Auth with a custom `Authorization` header, so the most reliable setup today is Claude Code, Codex, Cursor, or another MCP client that supports custom HTTP headers for remote servers.

## MCP Tools

- `wpbakery-uncode-mcp/create-page`
- `wpbakery-uncode-mcp/get-page`
- `wpbakery-uncode-mcp/update-page-content`
- `wpbakery-uncode-mcp/build-shortcode`
- `wpbakery-uncode-mcp/insert-shortcode`
- `wpbakery-uncode-mcp/remove-shortcode`
- `wpbakery-uncode-mcp/list-shortcodes`
- `wpbakery-uncode-mcp/get-wpbakery-inner-api-status`
- `wpbakery-uncode-mcp/list-wpbakery-elements`
- `wpbakery-uncode-mcp/get-wpbakery-element-definition`
- `wpbakery-uncode-mcp/update-wpbakery-element-param`
- `wpbakery-uncode-mcp/set-wpbakery-element-visibility`
- `wpbakery-uncode-mcp/set-wpbakery-output-debug`
- `wpbakery-uncode-mcp/get-uncode-meta`
- `wpbakery-uncode-mcp/update-uncode-meta`
- `wpbakery-uncode-mcp/get-theme-options`
- `wpbakery-uncode-mcp/update-theme-options`
- `wpbakery-uncode-mcp/update-theme-css-js`
- `wpbakery-uncode-mcp/discover-theme-option-keys`
- `wpbakery-uncode-mcp/get-theme-option-path`
- `wpbakery-uncode-mcp/update-theme-option-path`
- `wpbakery-uncode-mcp/upsert-theme-css-block`
- `wpbakery-uncode-mcp/update-navigation-style`
- `wpbakery-uncode-mcp/update-menu-layout`
- `wpbakery-uncode-mcp/update-megamenu-visuals`
- `wpbakery-uncode-mcp/update-menu-cta-settings`
- `wpbakery-uncode-mcp/update-logo-settings`
- `wpbakery-uncode-mcp/update-logo-links`
- `wpbakery-uncode-mcp/sync-logo-variants`
- `wpbakery-uncode-mcp/validate-theme-mapping`
- `wpbakery-uncode-mcp/apply-brand-setup`
- `wpbakery-uncode-mcp/apply-style-presets`
- `wpbakery-uncode-mcp/preview-preset-impact`
- `wpbakery-uncode-mcp/export-theme-options-diff`
- `wpbakery-uncode-mcp/restore-last-snapshot`
- `wpbakery-uncode-mcp/run-theme-ops-batch`
- `wpbakery-uncode-mcp/update-header-layout`
- `wpbakery-uncode-mcp/update-footer-layout`
- `wpbakery-uncode-mcp/update-global-typography`
- `wpbakery-uncode-mcp/create-theme-options-snapshot`
- `wpbakery-uncode-mcp/list-theme-options-snapshots`
- `wpbakery-uncode-mcp/restore-theme-options-snapshot`
- `wpbakery-uncode-mcp/compose-hero-section`

## Notes for Uncode

- Uncode-specific tools intentionally restrict writable meta keys to `uncode_` and `_uncode_` prefixes.
- This keeps remote MCP mutations scoped to theme-related options and reduces accidental global/meta corruption.
- Theme Options tools require `manage_options` capability and can auto-detect common option stores (`uncode_theme_options`, `ot_options`, `uncode_options`).

## Suggested Theme Options Workflow

1. Call `discover-theme-option-keys` to identify real keys on your site (CSS/JS/navigation/layout buckets).
2. Use `get-theme-option-path` to inspect the exact nested value before changing it.
3. Use `update-theme-option-path` for targeted nested changes (set `create_missing=true` only when needed).
4. Use `upsert-theme-css-block` for CSS sections you want to maintain over time without overwriting everything.
