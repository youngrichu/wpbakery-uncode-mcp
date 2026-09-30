# Design: Content Tree Editing and Default Tool Efficiency

**Date:** 2026-09-30  
**Plugin:** wpbakery-uncode-mcp  
**Status:** Approved

---

## Problem

Page edits are string operations. An agent can append a shortcode, replace the whole post, or remove the first tag of a given name. It cannot reliably change one nested row, column, or element.

The default MCP catalog registers every tool. The string tools overlap with the work a content tree can do, and a few WPBakery builder mutations are rarely needed. That extra surface is loaded into agent context on every connection.

## Goal

Add two tools that read and patch WPBakery content as a tree, and shrink the default tool list without removing any tool from the admin catalog.

## Addressing

A read never writes the post. Each node includes:

- `path`, such as `0/2/1`
- `tag`
- `attrs`
- `children`
- `el_id` only when the shortcode already has one

Inner text and child shortcodes stay in source order. Serializing a well-formed tree returns equivalent markup. A span the parser cannot close stays raw text and is listed in `warnings`.

A patch targets either `path` or `el_id`. `ensure_ids` writes `mcp-` plus 8 hex characters onto elements that lack an `el_id`. Existing ids are left alone. A generated id that collides is regenerated.

## Tools

### `get-content-tree`

Read-only. Inputs: `post_id`, optional `path` for one subtree, optional `depth`. The default response is a summary: tags, attributes, paths, and ids, with long inner text truncated. The caller can request full inner text for the subtree it is about to edit.

Permission: `edit_posts`, and `edit_post` when `post_id` is set. Revision posts are rejected, same as `get-page`.

### `apply-content-ops`

Inputs: `post_id`, `ops`, `dry_run`. Operations run in order:

| Op | Effect |
| --- | --- |
| `set_attrs` | Merge attributes. A null value removes a key. |
| `set_content` | Replace that node's inner markup and re-parse it. |
| `insert` | Insert a node, or a shortcode string parsed into a node. |
| `move` | Move a node under a parent at an index. |
| `delete` | Remove a node. |
| `ensure_ids` | Add missing `el_id` values. |
| `replace_content` | Replace the whole post body. An empty string is rejected unless `allow_empty` is true. |

`dry_run` returns the next tree and does not save. If any operation fails, nothing is saved. A successful save uses `wp_update_post`, so WordPress stores a revision when the post type supports revisions.

Attribute values are escaped so a `]` cannot break a shortcode. When the acting user has `unfiltered_html`, the serialized shortcodes are stored directly. Otherwise the result passes through `wp_kses_post`.

Permission matches `get-content-tree`.

`create-page`, `get-page`, and `compose-hero-section` stay enabled. `get-page` remains the metadata and rendered-HTML read.

## Default catalog

Tools are not deleted. The Tools tab still lists them. A saved disabled-tools option is left unchanged. The new defaults apply only when that option has never been saved.

Default off, because the tree tools cover the same jobs:

- `build-shortcode`
- `insert-shortcode`
- `remove-shortcode`
- `list-shortcodes`
- `update-page-content`

Uncode theme tools stay on. They encode fields such as logo variants, header layout, and typography. `get-theme-options` and `discover-theme-option-keys` return keys and short values unless the caller sets `full` to true. Descriptions become one sentence that says when to call the tool, and known values become enums.

WPBakery element discovery stays on: list elements, and get one definition. Default off:

- `update-wpbakery-element-param`
- `set-wpbakery-element-visibility`
- `set-wpbakery-output-debug`
- `get-wpbakery-inner-api-status`

## Errors

The write path parses the page, validates every operation, and applies the list in memory. The post is saved only after the full list succeeds.

These failures return `WP_Error` and leave stored content unchanged:

- Unknown `path`
- Unknown `el_id`
- Duplicate `el_id`
- Operation missing its target
- Empty `replace_content` without `allow_empty`

A read still returns the tree when some spans are raw text. Those spans appear in `warnings`.

## Tests

Add a PHP smoke file next to the existing shortcode tests. It does not need a running WordPress site for the parser cases.

Cover:

- Nested row and column round-trip
- Text between shortcodes
- A self-closing tag
- An unclosed tag left as raw text
- Patch by `path` and by `el_id`
- A duplicate `el_id`
- A failed later operation leaving the original string unchanged
- `dry_run`
- `ensure_ids` preserving existing ids
- The five string tools defaulting off only when the disabled-tools option has never been saved

## Out of scope

Media, menus, templates, and extra section composers are not part of this change. Theme tools are not folded into generic path updates.
