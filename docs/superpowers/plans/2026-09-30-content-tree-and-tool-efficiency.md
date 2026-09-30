# Content Tree and Tool Efficiency Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an agent read and patch nested WPBakery shortcodes as a tree, and stop loading superseded tools on a fresh install.

**Architecture:** A WordPress-free `WPBakery_Uncode_MCP_Content_Tree` parses, summarizes, and applies operations in memory. `WPBakery_Uncode_MCP_Data` loads the post and saves only after every operation succeeds. Two abilities expose that to MCP. A small catalog helper decides the default disabled list only when the option has never been saved.

**Tech Stack:** PHP 8.0+, WordPress 6.9+ abilities API, existing smoke-test style (`php tests/*.php` with `assert`).

**Spec:** `docs/superpowers/specs/2026-09-30-content-tree-and-tool-efficiency-design.md`

## Global Constraints

- PHP 8.0+ and WordPress 6.9+. Ability names use the `wpbakery-uncode-mcp/` prefix and the `wpbakery-uncode-mcp` text domain.
- A read never writes the post. `el_id` is included only when the shortcode already has one.
- `ensure_ids` writes `mcp-` plus 8 hex characters, skips existing ids, and regenerates on collision.
- `dry_run` never calls `wp_update_post`. A failed operation does not save.
- `replace_content` with `''` is rejected unless `allow_empty` is true.
- Users with `unfiltered_html` store the serialized shortcodes. Everyone else goes through `wp_kses_post`.
- Attribute values are escaped so `"` and `]` cannot break the shortcode.
- A saved disabled-tools option is left unchanged, including a saved empty array. Defaults apply only when the option is missing.
- Theme tools stay enabled. Do not invent enums for layout slugs the data layer accepts as any sanitized key.
- Media, menus, templates, and extra section composers are out of scope.

## File Structure

- Create `includes/class-content-tree.php`. Parse, serialize, summarize, and apply operations. No WordPress calls.
- Create `includes/abilities/class-content-abilities.php`. Registers `get-content-tree` and `apply-content-ops`.
- Create `includes/class-tool-catalog.php`. Default disabled list and the "option missing" lookup.
- Modify `includes/class-data.php`. Load and save page content through the tree. Summarize theme option values.
- Modify `includes/class-plugin.php` and `includes/abilities/class-ability-registrar.php`. Construct and register the new abilities.
- Modify `wpbakery-uncode-mcp.php`. Require the new files. `wpbakery_uncode_mcp_is_tool_enabled()` uses the catalog.
- Modify `includes/admin/class-admin.php`. Show the two new tools and the same disabled list the runtime uses.
- Modify `includes/abilities/class-theme-options-abilities.php` and `includes/abilities/class-page-abilities.php`. Payload flag and one-sentence descriptions.
- Modify the other ability classes only to replace description strings listed in Task 5.
- Modify `README.md`. Document the two tools and the default-off list.
- Create `tests/content-tree-smoke.php`, `tests/content-save-smoke.php`, `tests/tool-catalog-smoke.php`, and `tests/theme-summary-smoke.php`.

## Review Focus

These are the inputs most likely to corrupt a page. Each one has a test in the task that owns the code.

1. Nested shortcodes that share a tag must close on the matching opener. Task 1 asserts `[vc_row][vc_row]Inner[/vc_row]Outer[/vc_row]` keeps `Inner` inside the child row and `Outer` on the parent.
2. An attribute value containing `"` or `]` must survive a round trip. Task 1 asserts `data-x="say \"hi\" [now]"` comes back with the same decoded characters.
3. Moving a node under its own descendant must fail and return no replacement content. Task 2 asserts `invalid_move` and that `content` is absent.
4. Text and child shortcodes must keep source order. Task 1 asserts `Hello` stays before `vc_column_text` and `Tail` stays after it.
5. A stored empty disabled-tools array means the user enabled every tool. Task 4 asserts `build-shortcode` stays enabled in that case.

---

### Task 1: Parse and serialize the content tree

**Files:**
- Create: `includes/class-content-tree.php`
- Create: `tests/content-tree-smoke.php`
- Modify: `.gitignore` (the `.worktrees/` line is already in the worktree copy; commit it here)
- Test: `tests/content-tree-smoke.php`

**Interfaces:**
- Consumes: nothing
- Produces: `WPBakery_Uncode_MCP_Content_Tree`
  - `parse(string $content): array` with keys `nodes` and `warnings`
  - `assign_paths(array $nodes, string $prefix = ''): array`
  - `serialize(array $nodes): string`
  - `summarize(array $nodes, int $depth, bool $full): array`
  - Element node keys: `tag`, `attrs`, `children`, `self_closing`, `path` after `assign_paths`, and `el_id` only when `attrs['el_id']` is non-empty
  - Text node keys: `tag` = `#text`, `text`

- [ ] **Step 1: Write the failing test**

Create `tests/content-tree-smoke.php`:

```php
<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../includes/class-content-tree.php';

$tree = new WPBakery_Uncode_MCP_Content_Tree();

$nested = '[vc_row][vc_column][vc_column_text el_id="keep-me"]Hello[/vc_column_text][/vc_column][/vc_row]';
$parsed = $tree->assign_paths( $tree->parse( $nested )['nodes'] );
assert( 'vc_row' === $parsed[0]['tag'] );
assert( '0/0/0' === $parsed[0]['children'][0]['children'][0]['path'] );
assert( 'keep-me' === $parsed[0]['children'][0]['children'][0]['el_id'] );

$again = $tree->parse( $tree->serialize( $parsed ) )['nodes'];
assert( 'Hello' === $again[0]['children'][0]['children'][0]['children'][0]['text'] );

$same = $tree->assign_paths( $tree->parse( '[vc_row][vc_row]Inner[/vc_row]Outer[/vc_row]' )['nodes'] );
assert( 'Inner' === $same[0]['children'][0]['children'][0]['text'] );
assert( 'Outer' === $same[0]['children'][1]['text'] );

$mixed = $tree->parse( '[vc_column]Hello[vc_column_text]Hi[/vc_column_text]Tail[/vc_column]' )['nodes'];
assert( '#text' === $mixed[0]['children'][0]['tag'] );
assert( 'Hello' === $mixed[0]['children'][0]['text'] );
assert( 'vc_column_text' === $mixed[0]['children'][1]['tag'] );
assert( 'Tail' === $mixed[0]['children'][2]['text'] );

$empty = $tree->parse( '[vc_empty_space height="32" /]' )['nodes'];
assert( true === $empty[0]['self_closing'] );
assert( str_contains( $tree->serialize( $empty ), '[vc_empty_space height="32" /]' ) );

$raw = $tree->parse( '[vc_column_text]Hi[/vc_column_text][vc_row]' );
assert( [] !== $raw['warnings'] );
assert( '#text' === $raw['nodes'][1]['tag'] );
assert( str_contains( $raw['nodes'][1]['text'], '[vc_row]' ) );

$quoted = '[vc_column_text data-x="say &quot;hi&quot; &#93;now&#93;"]Body[/vc_column_text]';
$qnodes = $tree->parse( $quoted )['nodes'];
assert( 'say "hi" ]now]' === $qnodes[0]['attrs']['data-x'] );
$qback  = $tree->parse( $tree->serialize( $qnodes ) )['nodes'];
assert( 'say "hi" ]now]' === $qback[0]['attrs']['data-x'] );

$summary = $tree->summarize( $tree->assign_paths( $tree->parse( $nested )['nodes'] ), 1, false );
assert( [] === $summary[0]['children'][0]['children'] );
assert( true === $summary[0]['children'][0]['children_omitted'] );

echo "content-tree-smoke: ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/content-tree-smoke.php`

Expected: FAIL because `includes/class-content-tree.php` does not exist.

- [ ] **Step 3: Write minimal implementation**

Create `includes/class-content-tree.php` with this class. Parsing is recursive. An unclosed opener becomes one raw text node from that `[` through the end of the current range, and a warning is recorded. Element paths count element siblings and skip `#text` nodes.

```php
<?php
/**
 * Parse and patch WPBakery shortcode trees.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Content_Tree {
	public const TEXT_TAG = '#text';
	public const TEXT_LIMIT = 160;

	public function parse( string $content ): array {
		$warnings = array();
		$result   = $this->parse_range( $content, 0, null, $warnings );
		return array(
			'nodes'    => $result['nodes'],
			'warnings' => $warnings,
		);
	}

	public function assign_paths( array $nodes, string $prefix = '' ): array {
		$element_index = 0;
		foreach ( $nodes as $index => $node ) {
			if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
				continue;
			}
			$path = '' === $prefix ? (string) $element_index : $prefix . '/' . $element_index;
			$node['path'] = $path;
			++$element_index;
			$el_id = (string) ( $node['attrs']['el_id'] ?? '' );
			if ( '' !== $el_id ) {
				$node['el_id'] = $el_id;
			} else {
				unset( $node['el_id'] );
			}
			$node['children'] = $this->assign_paths( $node['children'] ?? array(), $path );
			$nodes[ $index ]  = $node;
		}
		return $nodes;
	}

	public function serialize( array $nodes ): string {
		$out = '';
		foreach ( $nodes as $node ) {
			if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
				$out .= (string) ( $node['text'] ?? '' );
				continue;
			}
			$tag  = (string) $node['tag'];
			$open = '[' . $tag . $this->serialize_attrs( $node['attrs'] ?? array() );
			if ( ! empty( $node['self_closing'] ) ) {
				$out .= $open . ' /]';
				continue;
			}
			$out .= $open . ']' . $this->serialize( $node['children'] ?? array() ) . '[/' . $tag . ']';
		}
		return $out;
	}

	public function summarize( array $nodes, int $depth, bool $full ): array {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
				$text = (string) ( $node['text'] ?? '' );
				$item = array(
					'tag'  => self::TEXT_TAG,
					'text' => $full ? $text : $this->truncate_text( $text ),
				);
				if ( ! $full && strlen( $text ) > self::TEXT_LIMIT ) {
					$item['text_truncated'] = true;
				}
				$out[] = $item;
				continue;
			}
			$item = array(
				'path'          => (string) ( $node['path'] ?? '' ),
				'tag'           => (string) $node['tag'],
				'attrs'         => $node['attrs'] ?? array(),
				'self_closing'  => ! empty( $node['self_closing'] ),
			);
			if ( ! empty( $node['el_id'] ) ) {
				$item['el_id'] = (string) $node['el_id'];
			}
			$children = $node['children'] ?? array();
			if ( $depth <= 0 ) {
				$item['children'] = array();
				if ( ! empty( $children ) ) {
					$item['children_omitted'] = true;
				}
			} else {
				$item['children'] = $this->summarize( $children, $depth - 1, $full );
			}
			$out[] = $item;
		}
		return $out;
	}

	private function parse_range( string $content, int $offset, ?string $closer, array &$warnings ): array {
		$nodes = array();
		$len   = strlen( $content );
		$text  = '';
		$i     = $offset;
		$flush = function () use ( &$nodes, &$text ): void {
			if ( '' === $text ) {
				return;
			}
			$nodes[] = array(
				'tag'  => self::TEXT_TAG,
				'text' => $text,
			);
			$text = '';
		};

		while ( $i < $len ) {
			if ( '[' !== $content[ $i ] ) {
				$text .= $content[ $i ];
				++$i;
				continue;
			}
			if ( $i + 1 < $len && '/' === $content[ $i + 1 ] ) {
				$close = $this->read_tag_name( $content, $i + 2 );
				$end   = null === $close ? null : $this->closing_bracket( $content, $close['end'] );
				if ( null === $close || null === $end ) {
					$text .= '[';
					++$i;
					continue;
				}
				if ( null !== $closer && $close['tag'] === $closer ) {
					$flush();
					return array(
						'nodes'  => $nodes,
						'end'    => $end,
						'closed' => true,
					);
				}
				$text .= substr( $content, $i, $end - $i );
				$i     = $end;
				continue;
			}

			$open = $this->read_open( $content, $i );
			if ( null === $open ) {
				$text .= '[';
				++$i;
				continue;
			}
			$flush();
			if ( $open['self_closing'] ) {
				$nodes[] = $this->element( $open['tag'], $open['attrs'], array(), true );
				$i       = $open['end'];
				continue;
			}
			$inner = $this->parse_range( $content, $open['end'], $open['tag'], $warnings );
			if ( empty( $inner['closed'] ) ) {
				$warnings[] = array(
					'tag'     => $open['tag'],
					'offset'  => $i,
					'message' => 'Unclosed shortcode left as raw text.',
				);
				$nodes[] = array(
					'tag'  => self::TEXT_TAG,
					'text' => substr( $content, $i, $inner['end'] - $i ),
				);
				return array(
					'nodes'  => $nodes,
					'end'    => $inner['end'],
					'closed' => false,
				);
			}
			$nodes[] = $this->element( $open['tag'], $open['attrs'], $inner['nodes'], false );
			$i       = $inner['end'];
		}

		$flush();
		return array(
			'nodes'  => $nodes,
			'end'    => $len,
			'closed' => false,
		);
	}

	private function element( string $tag, array $attrs, array $children, bool $self_closing ): array {
		$node = array(
			'tag'          => $tag,
			'attrs'        => $attrs,
			'children'     => $children,
			'self_closing' => $self_closing,
		);
		if ( '' !== (string) ( $attrs['el_id'] ?? '' ) ) {
			$node['el_id'] = (string) $attrs['el_id'];
		}
		return $node;
	}

	private function read_open( string $content, int $offset ): ?array {
		$name = $this->read_tag_name( $content, $offset + 1 );
		if ( null === $name ) {
			return null;
		}
		$attrs = array();
		$j     = $name['end'];
		$len   = strlen( $content );
		while ( $j < $len ) {
			$j = $this->skip_ws( $content, $j );
			if ( $j >= $len ) {
				return null;
			}
			if ( ']' === $content[ $j ] ) {
				return array(
					'tag'          => $name['tag'],
					'attrs'        => $attrs,
					'self_closing' => false,
					'end'          => $j + 1,
				);
			}
			if ( '/' === $content[ $j ] ) {
				$k = $this->skip_ws( $content, $j + 1 );
				if ( $k < $len && ']' === $content[ $k ] ) {
					return array(
						'tag'          => $name['tag'],
						'attrs'        => $attrs,
						'self_closing' => true,
						'end'          => $k + 1,
					);
				}
				return null;
			}
			$attr = $this->read_attr( $content, $j );
			if ( null === $attr ) {
				return null;
			}
			$attrs[ $attr['key'] ] = $attr['value'];
			$j                     = $attr['end'];
		}
		return null;
	}

	private function read_tag_name( string $content, int $offset ): ?array {
		$len = strlen( $content );
		if ( $offset >= $len || ! preg_match( '/[A-Za-z]/', $content[ $offset ] ) ) {
			return null;
		}
		$end = $offset + 1;
		while ( $end < $len && preg_match( '/[A-Za-z0-9_\-]/', $content[ $end ] ) ) {
			++$end;
		}
		return array(
			'tag' => substr( $content, $offset, $end - $offset ),
			'end' => $end,
		);
	}

	private function read_attr( string $content, int $offset ): ?array {
		$len = strlen( $content );
		if ( $offset >= $len || ! preg_match( '/[A-Za-z0-9_\-]/', $content[ $offset ] ) ) {
			return null;
		}
		$end = $offset + 1;
		while ( $end < $len && preg_match( '/[A-Za-z0-9_\-]/', $content[ $end ] ) ) {
			++$end;
		}
		$key = substr( $content, $offset, $end - $offset );
		if ( $end >= $len || '=' !== $content[ $end ] ) {
			return array(
				'key'   => $key,
				'value' => '',
				'end'   => $end,
			);
		}
		++$end;
		if ( $end >= $len ) {
			return null;
		}
		$quote = $content[ $end ];
		if ( '"' === $quote || "'" === $quote ) {
			++$end;
			$start = $end;
			while ( $end < $len && $content[ $end ] !== $quote ) {
				++$end;
			}
			if ( $end >= $len ) {
				return null;
			}
			return array(
				'key'   => $key,
				'value' => $this->decode_attr( substr( $content, $start, $end - $start ) ),
				'end'   => $end + 1,
			);
		}
		$start = $end;
		while ( $end < $len && ! preg_match( '/[\s\]]/', $content[ $end ] ) && '/' !== $content[ $end ] ) {
			++$end;
		}
		return array(
			'key'   => $key,
			'value' => $this->decode_attr( substr( $content, $start, $end - $start ) ),
			'end'   => $end,
		);
	}

	private function closing_bracket( string $content, int $offset ): ?int {
		$offset = $this->skip_ws( $content, $offset );
		if ( $offset < strlen( $content ) && ']' === $content[ $offset ] ) {
			return $offset + 1;
		}
		return null;
	}

	private function skip_ws( string $content, int $offset ): int {
		$len = strlen( $content );
		while ( $offset < $len && preg_match( '/\s/', $content[ $offset ] ) ) {
			++$offset;
		}
		return $offset;
	}

	private function serialize_attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $key => $value ) {
			if ( null === $value ) {
				continue;
			}
			$clean = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );
			if ( '' === $clean ) {
				continue;
			}
			$out .= ' ' . $clean . '="' . $this->escape_attr( (string) $value ) . '"';
		}
		return $out;
	}

	private function escape_attr( string $value ): string {
		return str_replace(
			array( '&', '"', ']' ),
			array( '&amp;', '&quot;', '&#93;' ),
			$value
		);
	}

	private function decode_attr( string $value ): string {
		return str_replace(
			array( '&#93;', '&quot;', '&amp;' ),
			array( ']', '"', '&' ),
			$value
		);
	}

	private function truncate_text( string $text ): string {
		if ( strlen( $text ) <= self::TEXT_LIMIT ) {
			return $text;
		}
		return substr( $text, 0, self::TEXT_LIMIT ) . '…';
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/content-tree-smoke.php`

Expected: `content-tree-smoke: ok`

- [ ] **Step 5: Commit**

```bash
git add .gitignore includes/class-content-tree.php tests/content-tree-smoke.php docs/superpowers/specs/2026-09-30-content-tree-and-tool-efficiency-design.md
git commit -m "$(cat <<'EOF'
Add a nested shortcode tree parser.

Agents need a stable structure for WPBakery content before one element can be edited safely.

EOF
)"
```

---

### Task 2: Apply content operations in memory

**Files:**
- Modify: `includes/class-content-tree.php`
- Modify: `tests/content-tree-smoke.php`
- Test: `tests/content-tree-smoke.php`

**Interfaces:**
- Consumes: `parse`, `assign_paths`, `serialize` from Task 1
- Produces: `apply(string $content, array $ops): array`
  - Success: `ok` true, `content` string, `nodes` array, `warnings` array
  - Failure: `ok` false, `code` string, `message` string, and no `content` key
  - Codes: `duplicate_el_id`, `unknown_path`, `unknown_el_id`, `missing_target`, `target_mismatch`, `invalid_op`, `invalid_index`, `invalid_move`, `empty_replace`, `invalid_shortcode`
  - Op keys: `op`, `path`, `el_id`, `parent_path`, `parent_el_id`, `index`, `attrs`, `content`, `shortcode`, `node`, `allow_empty`
  - Ops: `set_attrs`, `set_content`, `insert`, `move`, `delete`, `ensure_ids`, `replace_content`

- [ ] **Step 1: Write the failing test**

Append this before the final `echo` in `tests/content-tree-smoke.php`. Keep the existing asserts.

```php
$base = '[vc_row el_id="row"][vc_column][vc_column_text el_id="copy"]One[/vc_column_text][vc_column_text]Two[/vc_column_text][/vc_column][/vc_row]';

$attrs = $tree->apply(
	$base,
	array(
		array(
			'op'    => 'set_attrs',
			'el_id' => 'copy',
			'attrs' => array(
				'css'   => 'color',
				'el_id' => null,
			),
		),
	)
);
assert( true === $attrs['ok'] );
assert( false === str_contains( $attrs['content'], 'el_id="copy"' ) );
assert( str_contains( $attrs['content'], 'css="color"' ) );

$by_path = $tree->apply(
	$base,
	array(
		array(
			'op'      => 'set_content',
			'path'    => '0/0/1',
			'content' => 'Changed',
		),
	)
);
assert( true === $by_path['ok'] );
assert( str_contains( $by_path['content'], 'Changed' ) );

$inserted = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'insert',
			'parent_path' => '0/0',
			'index'       => 1,
			'shortcode'   => '[vc_column_text]Mid[/vc_column_text]',
		),
	)
);
assert( true === $inserted['ok'] );
assert( str_contains( $inserted['content'], 'One[/vc_column_text][vc_column_text]Mid' ) );

$moved = $tree->apply(
	'[vc_row][vc_column_text]A[/vc_column_text][vc_column_text]B[/vc_column_text][vc_column_text]C[/vc_column_text][/vc_row]',
	array(
		array(
			'op'          => 'move',
			'path'        => '0/0',
			'parent_path' => '0',
			'index'       => 2,
		),
	)
);
assert( true === $moved['ok'] );
$moved_nodes = $tree->parse( $moved['content'] )['nodes'];
assert( 'B' === $moved_nodes[0]['children'][0]['children'][0]['text'] );
assert( 'C' === $moved_nodes[0]['children'][1]['children'][0]['text'] );
assert( 'A' === $moved_nodes[0]['children'][2]['children'][0]['text'] );

$bad_move = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'move',
			'path'        => '0',
			'parent_path' => '0/0',
			'index'       => 0,
		),
	)
);
assert( false === $bad_move['ok'] );
assert( 'invalid_move' === $bad_move['code'] );
assert( false === array_key_exists( 'content', $bad_move ) );

$failed_later = $tree->apply(
	$base,
	array(
		array(
			'op'      => 'set_content',
			'path'    => '0/0/0',
			'content' => 'Nope',
		),
		array(
			'op'   => 'delete',
			'path' => '9/9',
		),
	)
);
assert( false === $failed_later['ok'] );
assert( 'unknown_path' === $failed_later['code'] );
assert( false === array_key_exists( 'content', $failed_later ) );

$dup = $tree->apply( '[vc_row el_id="x"][/vc_row][vc_row el_id="x"][/vc_row]', array() );
assert( false === $dup['ok'] );
assert( 'duplicate_el_id' === $dup['code'] );

$ids = $tree->apply(
	'[vc_row el_id="keep-me"][vc_column][/vc_column][/vc_row]',
	array(
		array( 'op' => 'ensure_ids' ),
	)
);
assert( true === $ids['ok'] );
assert( str_contains( $ids['content'], 'el_id="keep-me"' ) );
assert( 1 === preg_match_all( '/el_id="mcp-[a-f0-9]{8}"/', $ids['content'] ) );

$wipe = $tree->apply( $base, array( array( 'op' => 'replace_content', 'content' => '' ) ) );
assert( false === $wipe['ok'] );
assert( 'empty_replace' === $wipe['code'] );

$replaced = $tree->apply(
	$base,
	array(
		array(
			'op'          => 'replace_content',
			'content'     => '[vc_empty_space /]',
			'allow_empty' => false,
		),
	)
);
assert( true === $replaced['ok'] );
assert( str_contains( $replaced['content'], '[vc_empty_space /]' ) );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/content-tree-smoke.php`

Expected: FAIL because `apply` is undefined.

- [ ] **Step 3: Write minimal implementation**

Add these public and private methods to `WPBakery_Uncode_MCP_Content_Tree`. `apply` checks duplicate ids first, then runs every op against a copy of the tree. On the first failure it returns the error and does not include `content`. `move` rejects a destination inside the source. After a delete, destination trails that pointed past the removed sibling are shifted by one so the requested `index` is the final element index.

```php
public function apply( string $content, array $ops ): array {
	$parsed = $this->parse( $content );
	$nodes  = $this->assign_paths( $parsed['nodes'] );
	$dup    = $this->first_duplicate_el_id( $nodes );
	if ( null !== $dup ) {
		return $this->fail( 'duplicate_el_id', 'Duplicate el_id: ' . $dup );
	}
	foreach ( $ops as $op ) {
		if ( ! is_array( $op ) || ! isset( $op['op'] ) ) {
			return $this->fail( 'invalid_op', 'Each op needs an op name.' );
		}
		$next = $this->apply_one( $nodes, $op );
		if ( ! $next['ok'] ) {
			return $next;
		}
		$nodes = $this->assign_paths( $next['nodes'] );
	}
	return array(
		'ok'       => true,
		'content'  => $this->serialize( $nodes ),
		'nodes'    => $nodes,
		'warnings' => $parsed['warnings'],
	);
}

private function apply_one( array $nodes, array $op ): array {
	switch ( (string) $op['op'] ) {
		case 'set_attrs':
			return $this->op_set_attrs( $nodes, $op );
		case 'set_content':
			return $this->op_set_content( $nodes, $op );
		case 'insert':
			return $this->op_insert( $nodes, $op );
		case 'move':
			return $this->op_move( $nodes, $op );
		case 'delete':
			return $this->op_delete( $nodes, $op );
		case 'ensure_ids':
			return $this->op_ensure_ids( $nodes, $op );
		case 'replace_content':
			return $this->op_replace_content( $op );
		default:
			return $this->fail( 'invalid_op', 'Unknown op.' );
	}
}

private function op_set_attrs( array $nodes, array $op ): array {
	$target = $this->resolve_target( $nodes, $op, true );
	if ( ! $target['ok'] ) {
		return $target;
	}
	if ( ! isset( $op['attrs'] ) || ! is_array( $op['attrs'] ) ) {
		return $this->fail( 'invalid_op', 'set_attrs requires attrs.' );
	}
	$slot = $this->parent_and_index( $target['path'] );
	$next = $this->update_children(
		$nodes,
		$slot['parent'],
		function ( array $children ) use ( $slot, $op ) {
			$pos = $this->element_positions( $children );
			if ( ! isset( $pos[ $slot['index'] ] ) ) {
				return null;
			}
			$node = $children[ $pos[ $slot['index'] ] ];
			foreach ( $op['attrs'] as $key => $value ) {
				if ( null === $value ) {
					unset( $node['attrs'][ $key ] );
				} else {
					$node['attrs'][ $key ] = (string) $value;
				}
			}
			$el_id = (string) ( $node['attrs']['el_id'] ?? '' );
			if ( '' === $el_id ) {
				unset( $node['el_id'] );
			} else {
				$node['el_id'] = $el_id;
			}
			$children[ $pos[ $slot['index'] ] ] = $node;
			return $children;
		}
	);
	if ( null === $next ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_set_content( array $nodes, array $op ): array {
	$target = $this->resolve_target( $nodes, $op, true );
	if ( ! $target['ok'] ) {
		return $target;
	}
	if ( ! isset( $op['content'] ) || ! is_string( $op['content'] ) ) {
		return $this->fail( 'invalid_op', 'set_content requires content.' );
	}
	$inner = $this->parse( $op['content'] )['nodes'];
	$slot  = $this->parent_and_index( $target['path'] );
	$next  = $this->update_children(
		$nodes,
		$slot['parent'],
		function ( array $children ) use ( $slot, $inner ) {
			$pos = $this->element_positions( $children );
			if ( ! isset( $pos[ $slot['index'] ] ) ) {
				return null;
			}
			$children[ $pos[ $slot['index'] ] ]['children'] = $inner;
			return $children;
		}
	);
	if ( null === $next ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_insert( array $nodes, array $op ): array {
	$parent = $this->resolve_parent( $nodes, $op );
	if ( ! $parent['ok'] ) {
		return $parent;
	}
	$index = $this->read_index( $op );
	if ( ! $index['ok'] ) {
		return $index;
	}
	$incoming = $this->nodes_from_insert( $op );
	if ( ! $incoming['ok'] ) {
		return $incoming;
	}
	$next = $this->insert_nodes( $nodes, $this->path_to_ints( $parent['path'] ), $index['index'], $incoming['nodes'] );
	if ( null === $next ) {
		return $this->fail( 'invalid_index', 'insert index is outside the parent.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_delete( array $nodes, array $op ): array {
	$target = $this->resolve_target( $nodes, $op, true );
	if ( ! $target['ok'] ) {
		return $target;
	}
	$slot = $this->parent_and_index( $target['path'] );
	$next = $this->update_children(
		$nodes,
		$slot['parent'],
		function ( array $children ) use ( $slot ) {
			$pos = $this->element_positions( $children );
			if ( ! isset( $pos[ $slot['index'] ] ) ) {
				return null;
			}
			array_splice( $children, $pos[ $slot['index'] ], 1 );
			return $children;
		}
	);
	if ( null === $next ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_move( array $nodes, array $op ): array {
	$target = $this->resolve_target( $nodes, $op, true );
	if ( ! $target['ok'] ) {
		return $target;
	}
	$parent = $this->resolve_parent( $nodes, $op );
	if ( ! $parent['ok'] ) {
		return $parent;
	}
	$index = $this->read_index( $op );
	if ( ! $index['ok'] ) {
		return $index;
	}
	if ( $this->path_is_within( $parent['path'], $target['path'] ) ) {
		return $this->fail( 'invalid_move', 'Cannot move a node into itself.' );
	}
	$slot = $this->parent_and_index( $target['path'] );
	$node = $this->node_at( $nodes, $target['path'] );
	if ( null === $node ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	$removed = $this->update_children(
		$nodes,
		$slot['parent'],
		function ( array $children ) use ( $slot ) {
			$pos = $this->element_positions( $children );
			if ( ! isset( $pos[ $slot['index'] ] ) ) {
				return null;
			}
			array_splice( $children, $pos[ $slot['index'] ], 1 );
			return $children;
		}
	);
	if ( null === $removed ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	$dest = $this->shift_trail( $this->path_to_ints( $parent['path'] ), $slot['parent'], $slot['index'] );
	$next = $this->insert_nodes( $removed, $dest, $index['index'], array( $node ) );
	if ( null === $next ) {
		return $this->fail( 'invalid_index', 'move index is outside the parent.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_ensure_ids( array $nodes, array $op ): array {
	$target = $this->resolve_target( $nodes, $op, false );
	if ( ! $target['ok'] ) {
		return $target;
	}
	$used = array();
	$this->collect_el_ids( $nodes, $used );
	if ( '' === $target['path'] ) {
		return array(
			'ok'    => true,
			'nodes' => $this->stamp_ids( $nodes, $used ),
		);
	}
	$slot = $this->parent_and_index( $target['path'] );
	$next = $this->update_children(
		$nodes,
		$slot['parent'],
		function ( array $children ) use ( $slot, &$used ) {
			$pos = $this->element_positions( $children );
			if ( ! isset( $pos[ $slot['index'] ] ) ) {
				return null;
			}
			$stamped = $this->stamp_ids( array( $children[ $pos[ $slot['index'] ] ] ), $used );
			$children[ $pos[ $slot['index'] ] ] = $stamped[0];
			return $children;
		}
	);
	if ( null === $next ) {
		return $this->fail( 'unknown_path', 'Unknown path.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $next,
	);
}

private function op_replace_content( array $op ): array {
	if ( ! isset( $op['content'] ) || ! is_string( $op['content'] ) ) {
		return $this->fail( 'invalid_op', 'replace_content requires content.' );
	}
	if ( '' === $op['content'] && empty( $op['allow_empty'] ) ) {
		return $this->fail( 'empty_replace', 'Refusing to replace the page with an empty string.' );
	}
	return array(
		'ok'    => true,
		'nodes' => $this->parse( $op['content'] )['nodes'],
	);
}

private function nodes_from_insert( array $op ): array {
	$has_shortcode = isset( $op['shortcode'] ) && is_string( $op['shortcode'] );
	$has_node      = isset( $op['node'] ) && is_array( $op['node'] );
	if ( $has_shortcode === $has_node ) {
		return $this->fail( 'invalid_op', 'insert requires shortcode or node, not both.' );
	}
	if ( $has_shortcode ) {
		$parsed = $this->parse( $op['shortcode'] );
		$nodes  = array();
		foreach ( $parsed['nodes'] as $node ) {
			if ( self::TEXT_TAG !== ( $node['tag'] ?? '' ) ) {
				$nodes[] = $node;
			}
		}
		if ( array() === $nodes ) {
			return $this->fail( 'invalid_shortcode', 'shortcode did not contain an element.' );
		}
		return array(
			'ok'    => true,
			'nodes' => $nodes,
		);
	}
	if ( '' === (string) ( $op['node']['tag'] ?? '' ) || self::TEXT_TAG === $op['node']['tag'] ) {
		return $this->fail( 'invalid_op', 'node.tag is required.' );
	}
	return array(
		'ok'    => true,
		'nodes' => array(
			$this->element(
				(string) $op['node']['tag'],
				is_array( $op['node']['attrs'] ?? null ) ? $op['node']['attrs'] : array(),
				is_array( $op['node']['children'] ?? null ) ? $op['node']['children'] : array(),
				! empty( $op['node']['self_closing'] )
			),
		),
	);
}

private function resolve_target( array $nodes, array $op, bool $required ): array {
	$has_path = isset( $op['path'] ) && '' !== (string) $op['path'];
	$has_id   = isset( $op['el_id'] ) && '' !== (string) $op['el_id'];
	if ( ! $has_path && ! $has_id ) {
		if ( $required ) {
			return $this->fail( 'missing_target', 'path or el_id is required.' );
		}
		return array(
			'ok'   => true,
			'path' => '',
		);
	}
	$path    = null;
	$id_path = null;
	if ( $has_path ) {
		$path = $this->normalize_path( (string) $op['path'] );
		if ( null === $path || null === $this->node_at( $nodes, $path ) ) {
			return $this->fail( 'unknown_path', 'Unknown path.' );
		}
	}
	if ( $has_id ) {
		$found = $this->paths_for_el_id( $nodes, (string) $op['el_id'], '' );
		if ( array() === $found ) {
			return $this->fail( 'unknown_el_id', 'Unknown el_id.' );
		}
		if ( count( $found ) > 1 ) {
			return $this->fail( 'duplicate_el_id', 'Duplicate el_id.' );
		}
		$id_path = $found[0];
	}
	if ( null !== $path && null !== $id_path && $path !== $id_path ) {
		return $this->fail( 'target_mismatch', 'path and el_id point at different nodes.' );
	}
	return array(
		'ok'   => true,
		'path' => $path ?? $id_path,
	);
}

private function resolve_parent( array $nodes, array $op ): array {
	$synthetic = array();
	if ( array_key_exists( 'parent_path', $op ) ) {
		$synthetic['path'] = (string) $op['parent_path'];
	}
	if ( isset( $op['parent_el_id'] ) ) {
		$synthetic['el_id'] = (string) $op['parent_el_id'];
	}
	if ( array() === $synthetic ) {
		return array(
			'ok'   => true,
			'path' => '',
		);
	}
	if ( isset( $synthetic['path'] ) && '' === $synthetic['path'] && empty( $synthetic['el_id'] ) ) {
		return array(
			'ok'   => true,
			'path' => '',
		);
	}
	return $this->resolve_target( $nodes, $synthetic, true );
}

private function insert_nodes( array $nodes, array $parent_trail, int $index, array $incoming ): ?array {
	return $this->update_children(
		$nodes,
		$parent_trail,
		function ( array $children ) use ( $index, $incoming ) {
			$pos = $this->element_positions( $children );
			if ( $index < 0 || $index > count( $pos ) ) {
				return null;
			}
			$at = $index === count( $pos ) ? count( $children ) : $pos[ $index ];
			array_splice( $children, $at, 0, $incoming );
			return $children;
		}
	);
}

private function update_children( array $nodes, array $parent_trail, callable $fn ): ?array {
	if ( array() === $parent_trail ) {
		$next = $fn( $nodes );
		return is_array( $next ) ? $next : null;
	}
	$want = array_shift( $parent_trail );
	$seen = 0;
	foreach ( $nodes as $i => $node ) {
		if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		if ( $seen === $want ) {
			$updated = $this->update_children( $node['children'] ?? array(), $parent_trail, $fn );
			if ( null === $updated ) {
				return null;
			}
			$node['children'] = $updated;
			$nodes[ $i ]      = $node;
			return $nodes;
		}
		++$seen;
	}
	return null;
}

private function node_at( array $nodes, string $path ): ?array {
	$trail = $this->path_to_ints( $path );
	$list  = $nodes;
	$last  = array_pop( $trail );
	if ( null === $last ) {
		return null;
	}
	foreach ( $trail as $element_index ) {
		$pos = $this->element_positions( $list );
		if ( ! isset( $pos[ $element_index ] ) ) {
			return null;
		}
		$list = $list[ $pos[ $element_index ] ]['children'] ?? array();
	}
	$pos = $this->element_positions( $list );
	if ( ! isset( $pos[ $last ] ) ) {
		return null;
	}
	return $list[ $pos[ $last ] ];
}

private function parent_and_index( string $path ): array {
	$parts = $this->path_to_ints( $path );
	$index = array_pop( $parts );
	return array(
		'parent' => $parts,
		'index'  => (int) $index,
	);
}

private function path_to_ints( string $path ): array {
	if ( '' === $path ) {
		return array();
	}
	return array_map( 'intval', explode( '/', $path ) );
}

private function normalize_path( string $path ): ?string {
	if ( ! preg_match( '/^\d+(?:\/\d+)*$/', $path ) ) {
		return null;
	}
	return $path;
}

private function element_positions( array $nodes ): array {
	$pos = array();
	foreach ( $nodes as $index => $node ) {
		if ( self::TEXT_TAG !== ( $node['tag'] ?? '' ) ) {
			$pos[] = $index;
		}
	}
	return $pos;
}

private function path_is_within( string $path, string $ancestor ): bool {
	if ( '' === $ancestor ) {
		return true;
	}
	return $path === $ancestor || str_starts_with( $path, $ancestor . '/' );
}

private function shift_trail( array $dest, array $source_parent, int $source_index ): array {
	$prefix = count( $source_parent );
	if ( count( $dest ) <= $prefix ) {
		return $dest;
	}
	for ( $i = 0; $i < $prefix; $i++ ) {
		if ( $dest[ $i ] !== $source_parent[ $i ] ) {
			return $dest;
		}
	}
	if ( $dest[ $prefix ] > $source_index ) {
		--$dest[ $prefix ];
	}
	return $dest;
}

private function read_index( array $op ): array {
	if ( ! isset( $op['index'] ) || ! is_int( $op['index'] ) ) {
		return $this->fail( 'invalid_index', 'index must be an integer.' );
	}
	if ( $op['index'] < 0 ) {
		return $this->fail( 'invalid_index', 'index must be zero or greater.' );
	}
	return array(
		'ok'    => true,
		'index' => $op['index'],
	);
}

private function paths_for_el_id( array $nodes, string $el_id, string $prefix ): array {
	$found         = array();
	$element_index = 0;
	foreach ( $nodes as $node ) {
		if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		$path = '' === $prefix ? (string) $element_index : $prefix . '/' . $element_index;
		++$element_index;
		if ( (string) ( $node['el_id'] ?? $node['attrs']['el_id'] ?? '' ) === $el_id ) {
			$found[] = $path;
		}
		$found = array_merge( $found, $this->paths_for_el_id( $node['children'] ?? array(), $el_id, $path ) );
	}
	return $found;
}

private function first_duplicate_el_id( array $nodes ): ?string {
	$seen = array();
	$dups = array();
	$this->find_duplicate_ids( $nodes, $seen, $dups );
	return $dups[0] ?? null;
}

private function find_duplicate_ids( array $nodes, array &$seen, array &$dups ): void {
	foreach ( $nodes as $node ) {
		if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		$id = (string) ( $node['el_id'] ?? $node['attrs']['el_id'] ?? '' );
		if ( '' !== $id ) {
			if ( isset( $seen[ $id ] ) ) {
				$dups[] = $id;
			}
			$seen[ $id ] = true;
		}
		$this->find_duplicate_ids( $node['children'] ?? array(), $seen, $dups );
	}
}

private function collect_el_ids( array $nodes, array &$used ): void {
	foreach ( $nodes as $node ) {
		if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		$id = (string) ( $node['attrs']['el_id'] ?? '' );
		if ( '' !== $id ) {
			$used[ $id ] = true;
		}
		$this->collect_el_ids( $node['children'] ?? array(), $used );
	}
}

private function stamp_ids( array $nodes, array &$used ): array {
	foreach ( $nodes as $i => $node ) {
		if ( self::TEXT_TAG === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		$existing = (string) ( $node['attrs']['el_id'] ?? '' );
		if ( '' === $existing ) {
			do {
				$id = 'mcp-' . bin2hex( random_bytes( 4 ) );
			} while ( isset( $used[ $id ] ) );
			$used[ $id ]               = true;
			$node['attrs']['el_id']    = $id;
			$node['el_id']             = $id;
		}
		$node['children'] = $this->stamp_ids( $node['children'] ?? array(), $used );
		$nodes[ $i ]      = $node;
	}
	return $nodes;
}

private function fail( string $code, string $message ): array {
	return array(
		'ok'      => false,
		'code'    => $code,
		'message' => $message,
	);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/content-tree-smoke.php && php tests/shortcode-service-smoke.php`

Expected: both print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-content-tree.php tests/content-tree-smoke.php
git commit -m "$(cat <<'EOF'
Apply shortcode tree edits in memory.

A failed operation returns before any replacement string is produced, so a later bad path cannot save a partial page.

EOF
)"
```

---

### Task 3: Register the two content tools

**Files:**
- Create: `includes/abilities/class-content-abilities.php`
- Create: `tests/content-save-smoke.php`
- Modify: `includes/class-data.php` (add the constructor and two methods; the class currently has no constructor)
- Modify: `includes/class-plugin.php` (require is in the bootstrap, not this file)
- Modify: `includes/abilities/class-ability-registrar.php`
- Modify: `includes/admin/class-admin.php` (`get_tool_groups()` around line 341)
- Modify: `wpbakery-uncode-mcp.php` (require list around line 201)
- Modify: `README.md` (MCP Tools list)
- Test: `tests/content-save-smoke.php`

**Interfaces:**
- Consumes: `WPBakery_Uncode_MCP_Content_Tree::apply()` and `summarize()`
- Produces:
  - `WPBakery_Uncode_MCP_Data::__construct()`
  - `WPBakery_Uncode_MCP_Data::get_content_tree( int $post_id, string $path = '', int $depth = 3, bool $full = false )`
  - `WPBakery_Uncode_MCP_Data::apply_content_ops( int $post_id, array $ops, bool $dry_run = false )`
  - `wpbakery_uncode_mcp_content_for_storage( string $content, bool $unfiltered ): string`
  - Ability names `wpbakery-uncode-mcp/get-content-tree` and `wpbakery-uncode-mcp/apply-content-ops`

- [ ] **Step 1: Write the failing test**

Create `tests/content-save-smoke.php`:

```php
<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $content ): string {
		return 'KSES:' . $content;
	}
}

require_once __DIR__ . '/../includes/class-content-tree.php';

$raw = '[vc_row][vc_column_text]Hi[/vc_column_text][/vc_row]';
assert( $raw === wpbakery_uncode_mcp_content_for_storage( $raw, true ) );
assert( 'KSES:' . $raw === wpbakery_uncode_mcp_content_for_storage( $raw, false ) );

$registrar = file_get_contents( __DIR__ . '/../includes/abilities/class-ability-registrar.php' );
assert( is_string( $registrar ) );
assert( str_contains( $registrar, 'WPBakery_Uncode_MCP_Content_Abilities' ) );

$admin = file_get_contents( __DIR__ . '/../includes/admin/class-admin.php' );
assert( is_string( $admin ) );
assert( str_contains( $admin, 'wpbakery-uncode-mcp/get-content-tree' ) );
assert( str_contains( $admin, 'wpbakery-uncode-mcp/apply-content-ops' ) );

echo "content-save-smoke: ok\n";
```

`wpbakery_uncode_mcp_content_for_storage()` lives in `includes/class-content-tree.php` after the class. The test reads registrar and admin source with `file_get_contents` and does not boot WordPress.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/content-save-smoke.php`

Expected: FAIL because `wpbakery_uncode_mcp_content_for_storage` is undefined, or the admin source does not mention the new slugs.

- [ ] **Step 3: Write minimal implementation**

Append to `includes/class-content-tree.php`:

```php
function wpbakery_uncode_mcp_content_for_storage( string $content, bool $unfiltered ): string {
	if ( $unfiltered ) {
		return $content;
	}
	return wp_kses_post( $content );
}
```

In `WPBakery_Uncode_MCP_Data`, add a constructor and these methods. `get_post` returning a revision, or no post, returns `new WP_Error( 'invalid_post', ... )` the same way `get_page_payload()` does.

```php
/** @var WPBakery_Uncode_MCP_Content_Tree */
private $content_tree;

public function __construct() {
	$this->content_tree = new WPBakery_Uncode_MCP_Content_Tree();
}

public function get_content_tree( int $post_id, string $path = '', int $depth = 3, bool $full = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'revision' === $post->post_type ) {
		return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
	}
	$parsed = $this->content_tree->parse( (string) $post->post_content );
	$nodes  = $this->content_tree->assign_paths( $parsed['nodes'] );
	if ( '' !== $path ) {
		$found = null;
		$walk  = function ( array $list ) use ( &$walk, $path, &$found ): void {
			foreach ( $list as $node ) {
				if ( '#text' === ( $node['tag'] ?? '' ) ) {
					continue;
				}
				if ( ( $node['path'] ?? '' ) === $path ) {
					$found = $node;
					return;
				}
				$walk( $node['children'] ?? array() );
			}
		};
		$walk( $nodes );
		if ( null === $found ) {
			return new WP_Error( 'unknown_path', __( 'Unknown path.', 'wpbakery-uncode-mcp' ) );
		}
		$nodes = array( $found );
	}
	return array(
		'post_id'    => $post_id,
		'tree'       => $this->content_tree->summarize( $nodes, $depth, $full ),
		'warnings'   => $parsed['warnings'],
		'node_count' => $this->count_elements( $nodes ),
	);
}

public function apply_content_ops( int $post_id, array $ops, bool $dry_run = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'revision' === $post->post_type ) {
		return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
	}
	$result = $this->content_tree->apply( (string) $post->post_content, $ops );
	if ( ! $result['ok'] ) {
		return new WP_Error( $result['code'], $result['message'] );
	}
	if ( ! $dry_run ) {
		$stored = wpbakery_uncode_mcp_content_for_storage(
			$result['content'],
			current_user_can( 'unfiltered_html' )
		);
		$saved = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $stored,
			),
			true
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
	}
	return array(
		'success'  => true,
		'dry_run'  => $dry_run,
		'post_id'  => $post_id,
		'content'  => $result['content'],
		'tree'     => $this->content_tree->summarize( $result['nodes'], 3, false ),
		'warnings' => $result['warnings'],
	);
}

private function count_elements( array $nodes ): int {
	$count = 0;
	foreach ( $nodes as $node ) {
		if ( '#text' === ( $node['tag'] ?? '' ) ) {
			continue;
		}
		++$count;
		$count += $this->count_elements( $node['children'] ?? array() );
	}
	return $count;
}
```

`get_content_tree()` only parses and summarizes. It does not call `apply()`.

Create `includes/abilities/class-content-abilities.php` with the same permission and schema shape as `WPBakery_Uncode_MCP_Page_Abilities`:

- `get_ability_names()` returns the two slugs.
- `check_edit_permission()` is the same body as the page ability: `edit_posts`, then `edit_post` for `post_id`.
- `get-content-tree` input: required `post_id` integer, optional `path` string, `depth` integer, `full` boolean. Description: `Use when you need the nested shortcode structure of a page before editing one element.` Readonly annotation true.
- `apply-content-ops` input: required `post_id` and `ops`. `ops` is an array of objects. Each object requires `op` with enum `set_attrs`, `set_content`, `insert`, `move`, `delete`, `ensure_ids`, `replace_content`. Optional properties: `path`, `el_id`, `parent_path`, `parent_el_id`, `index`, `attrs`, `content`, `shortcode`, `node`, `allow_empty`. Optional top-level `dry_run` boolean. Description: `Use when changing, inserting, moving, or deleting elements in one page.` Readonly false. Destructive false.
- Execute methods cast inputs and call `$this->data->get_content_tree()` and `$this->data->apply_content_ops()`. `depth` defaults to 3. `full` and `dry_run` default to false.

In `WPBakery_Uncode_MCP_Ability_Registrar::register_all()`, after the page abilities, construct `WPBakery_Uncode_MCP_Content_Abilities` with `$this->data`, call `register()`, and merge `get_ability_names()`.

In `wpbakery_uncode_mcp_init()`, require `includes/class-content-tree.php` immediately after `class-data.php`'s neighbors, before the ability classes, and require `includes/abilities/class-content-abilities.php` before `class-ability-registrar.php`. `class-data.php` constructs the tree, so require `class-content-tree.php` before `class-data.php`.

In `get_tool_groups()`, put these two slugs first inside `Pages & Shortcodes`:

```php
'wpbakery-uncode-mcp/get-content-tree',
'wpbakery-uncode-mcp/apply-content-ops',
```

In `README.md`, add both slugs under `## MCP Tools`, directly after the heading, and add one sentence: a fresh install leaves `build-shortcode`, `insert-shortcode`, `remove-shortcode`, `list-shortcodes`, `update-page-content`, `get-wpbakery-inner-api-status`, `update-wpbakery-element-param`, `set-wpbakery-element-visibility`, and `set-wpbakery-output-debug` off until they are enabled on the Tools tab.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/content-save-smoke.php && php tests/content-tree-smoke.php && php tests/admin-connection-security-smoke.php`

Expected: all three print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-content-tree.php includes/class-data.php includes/abilities/class-content-abilities.php includes/abilities/class-ability-registrar.php includes/admin/class-admin.php wpbakery-uncode-mcp.php README.md tests/content-save-smoke.php
git commit -m "$(cat <<'EOF'
Expose page content as tree read and patch tools.

Agents can edit one nested element, and a dry run returns the next tree without saving.

EOF
)"
```

---

### Task 4: Default-off superseded tools

**Files:**
- Create: `includes/class-tool-catalog.php`
- Create: `tests/tool-catalog-smoke.php`
- Modify: `wpbakery-uncode-mcp.php` (`wpbakery_uncode_mcp_is_tool_enabled()`)
- Modify: `includes/admin/class-admin.php` (the `get_option` call in `render_page()`)
- Test: `tests/tool-catalog-smoke.php`

**Interfaces:**
- Consumes: option name `WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION`
- Produces:
  - `wpbakery_uncode_mcp_default_disabled_tools(): array`
  - `wpbakery_uncode_mcp_get_disabled_tools(): array`
  - `wpbakery_uncode_mcp_is_tool_enabled( string $ability_name ): bool` delegates to `get_disabled_tools()`

- [ ] **Step 1: Write the failing test**

Create `tests/tool-catalog-smoke.php`:

```php
<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION' ) ) {
	define( 'WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION', 'wpbakery_uncode_mcp_disabled_tools' );
}

$GLOBALS['wpbakery_uncode_mcp_test_option'] = '__missing__';

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $GLOBALS['wpbakery_uncode_mcp_test_option'];
	}
}

require_once __DIR__ . '/../includes/class-tool-catalog.php';

$defaults = wpbakery_uncode_mcp_get_disabled_tools();
foreach (
	array(
		'wpbakery-uncode-mcp/build-shortcode',
		'wpbakery-uncode-mcp/insert-shortcode',
		'wpbakery-uncode-mcp/remove-shortcode',
		'wpbakery-uncode-mcp/list-shortcodes',
		'wpbakery-uncode-mcp/update-page-content',
		'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
		'wpbakery-uncode-mcp/update-wpbakery-element-param',
		'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
		'wpbakery-uncode-mcp/set-wpbakery-output-debug',
	) as $slug
) {
	assert( in_array( $slug, $defaults, true ) );
}
assert( false === in_array( 'wpbakery-uncode-mcp/get-content-tree', $defaults, true ) );
assert( false === in_array( 'wpbakery-uncode-mcp/get-theme-options', $defaults, true ) );

$GLOBALS['wpbakery_uncode_mcp_test_option'] = array();
assert( array() === wpbakery_uncode_mcp_get_disabled_tools() );

$GLOBALS['wpbakery_uncode_mcp_test_option'] = array( 'wpbakery-uncode-mcp/get-page' );
assert( array( 'wpbakery-uncode-mcp/get-page' ) === wpbakery_uncode_mcp_get_disabled_tools() );

echo "tool-catalog-smoke: ok\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/tool-catalog-smoke.php`

Expected: FAIL because `includes/class-tool-catalog.php` does not exist.

- [ ] **Step 3: Write minimal implementation**

Create `includes/class-tool-catalog.php`:

```php
<?php
/**
 * Default MCP tool catalog.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wpbakery_uncode_mcp_default_disabled_tools(): array {
	return array(
		'wpbakery-uncode-mcp/build-shortcode',
		'wpbakery-uncode-mcp/insert-shortcode',
		'wpbakery-uncode-mcp/remove-shortcode',
		'wpbakery-uncode-mcp/list-shortcodes',
		'wpbakery-uncode-mcp/update-page-content',
		'wpbakery-uncode-mcp/get-wpbakery-inner-api-status',
		'wpbakery-uncode-mcp/update-wpbakery-element-param',
		'wpbakery-uncode-mcp/set-wpbakery-element-visibility',
		'wpbakery-uncode-mcp/set-wpbakery-output-debug',
	);
}

function wpbakery_uncode_mcp_get_disabled_tools(): array {
	$stored = get_option( WPBAKERY_UNCODE_MCP_DISABLED_TOOLS_OPTION, '__missing__' );
	if ( '__missing__' === $stored || false === $stored || ! is_array( $stored ) ) {
		return wpbakery_uncode_mcp_default_disabled_tools();
	}
	return $stored;
}
```

Require this file from `wpbakery-uncode-mcp.php` before `wpbakery_uncode_mcp_is_tool_enabled()`. Change that function to:

```php
function wpbakery_uncode_mcp_is_tool_enabled( string $ability_name ): bool {
	return ! in_array( $ability_name, wpbakery_uncode_mcp_get_disabled_tools(), true );
}
```

At the top of `includes/admin/class-admin.php`, after the `ABSPATH` guard, add:

```php
require_once dirname( __DIR__ ) . '/class-tool-catalog.php';
```

`dirname( __DIR__ )` from `includes/admin` is `includes/`. That require keeps `tests/admin-connection-security-smoke.php` working, because that test loads the admin class and then renders the connection tab.

In `render_page()`, replace the `get_option` plus `is_array` block with:

```php
$disabled = wpbakery_uncode_mcp_get_disabled_tools();
```

Call `get_option( OPTION, '__missing__' )` so a registered settings default of `array()` does not hide a missing option. WordPress only substitutes the registered default when the caller does not pass a default.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/tool-catalog-smoke.php && php tests/admin-connection-security-smoke.php`

Expected: both print `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-tool-catalog.php wpbakery-uncode-mcp.php includes/admin/class-admin.php tests/tool-catalog-smoke.php
git commit -m "$(cat <<'EOF'
Turn off superseded MCP tools until an admin saves the catalog.

A saved empty list still means every tool stays available.

EOF
)"
```

---

### Task 5: Shrink theme payloads and state when to call each tool

**Files:**
- Modify: `includes/class-data.php` (`get_theme_options()` and `discover_theme_option_keys()`)
- Modify: `includes/abilities/class-theme-options-abilities.php`
- Modify: description strings in `includes/abilities/class-page-abilities.php`, `class-uncode-meta-abilities.php`, `class-wpbakery-api-abilities.php`, `class-composer-abilities.php`
- Create: `tests/theme-summary-smoke.php`
- Test: `tests/theme-summary-smoke.php`

**Interfaces:**
- Consumes: `get_theme_options( string $option_name = '', array $keys = array() )` and `discover_theme_option_keys( string $option_name = '' )`
- Produces:
  - Third argument `bool $full = false` on `get_theme_options`
  - Second argument `bool $full = false` on `discover_theme_option_keys`
  - `WPBakery_Uncode_MCP_Data::summarize_theme_value( $value, bool $full )`
  - Input property `full` boolean on both abilities

- [ ] **Step 1: Write the failing test**

Create `tests/theme-summary-smoke.php`:

```php
<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-data.php';

$long = str_repeat( 'a', 180 );
$short = WPBakery_Uncode_MCP_Data::summarize_theme_value( $long, false );
assert( str_starts_with( $short, str_repeat( 'a', 160 ) ) );
assert( str_ends_with( $short, '…' ) );
assert( strlen( $short ) === 160 + strlen( '…' ) );
assert( $long === WPBakery_Uncode_MCP_Data::summarize_theme_value( $long, true ) );

$list = WPBakery_Uncode_MCP_Data::summarize_theme_value( array( 'a', 'b' ), false );
assert( 'array' === $list['type'] );
assert( 2 === $list['count'] );
assert( array( 'a', 'b' ) === WPBakery_Uncode_MCP_Data::summarize_theme_value( array( 'a', 'b' ), true ) );

$theme = file_get_contents( __DIR__ . '/../includes/abilities/class-theme-options-abilities.php' );
assert( is_string( $theme ) );
assert( str_contains( $theme, "'full'" ) );
assert( str_contains( $theme, 'Use when you need Uncode theme option keys or short values before editing one key.' ) );

$pages = file_get_contents( __DIR__ . '/../includes/abilities/class-page-abilities.php' );
assert( is_string( $pages ) );
assert( str_contains( $pages, 'Use when you need the page title, status, URLs, or rendered HTML.' ) );

echo "theme-summary-smoke: ok\n";
```

`class-data.php` is safe to require after `ABSPATH` is defined. It does not register hooks.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/theme-summary-smoke.php`

Expected: FAIL because `summarize_theme_value` does not exist.

- [ ] **Step 3: Write minimal implementation**

Add this public static method to `WPBakery_Uncode_MCP_Data`:

```php
public static function summarize_theme_value( $value, bool $full ) {
	if ( $full ) {
		return $value;
	}
	if ( is_string( $value ) ) {
		if ( strlen( $value ) <= 160 ) {
			return $value;
		}
		return substr( $value, 0, 160 ) . '…';
	}
	if ( is_array( $value ) ) {
		return array(
			'type'  => 'array',
			'count' => count( $value ),
		);
	}
	return $value;
}
```

Change `get_theme_options()` to accept `bool $full = false`. When building `$out`, store `self::summarize_theme_value( $all[ $key ], $full )`. When `$keys` is empty and `$full` is false, return every top-level key through `summarize_theme_value` instead of the raw `$all` array. When `$full` is true, keep today's raw values.

Change `discover_theme_option_keys()` to accept `bool $full = false`. Add a `previews` map. For each top-level key, set `previews[ $key ]` to `self::summarize_theme_value( $current[ $key ], $full )`. Keep `all_keys` and `categories`.

In `execute_get_theme_options()` and `execute_discover_theme_option_keys()`, pass `(bool) ( $input['full'] ?? false )`.

Add `'full' => array( 'type' => 'boolean' )` to both input schemas.

Replace these description strings exactly:

| File | Old | New |
| --- | --- | --- |
| `class-page-abilities.php` | `Creates a page/post for WPBakery and optional Uncode meta.` | `Use when creating a page or post before adding WPBakery content.` |
| `class-page-abilities.php` | `Reads raw WPBakery shortcode content for a page/post.` | `Use when you need the page title, status, URLs, or rendered HTML.` |
| `class-composer-abilities.php` | `Generates and inserts a WPBakery/Uncode-friendly hero section into a page.` | `Use when inserting a ready-made hero with a headline, subheadline, and button.` |
| `class-wpbakery-api-abilities.php` | `Lists mapped WPBakery elements.` | `Use when you need the shortcode tags available in this WPBakery install.` |
| `class-wpbakery-api-abilities.php` | `Returns full mapped definition for one WPBakery shortcode element.` | `Use when you need the accepted parameters for one element before setting attributes.` |
| `class-uncode-meta-abilities.php` | `Reads Uncode-related post meta keys.` | `Use when reading Uncode post meta before changing page settings.` |
| `class-uncode-meta-abilities.php` | `Updates Uncode-related post meta keys (_uncode_* / uncode_* only).` | `Use when changing Uncode post meta keys that start with uncode_ or _uncode_.` |
| `class-theme-options-abilities.php` | `Reads Uncode theme options from the options table.` | `Use when you need Uncode theme option keys or short values before editing one key.` |
| `class-theme-options-abilities.php` | `Scans Uncode theme options and groups likely keys for CSS/JS/navigation/layout.` | `Use when you need to find which theme option keys exist for CSS, navigation, layout, type, or color.` |

Leave the existing enums on navigation style, link target, and presets. Do not add enums for menu layout, mobile behavior, header layout, or footer layout.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/theme-summary-smoke.php && php tests/content-tree-smoke.php && php tests/content-save-smoke.php && php tests/tool-catalog-smoke.php && php tests/shortcode-service-smoke.php && php tests/admin-connection-security-smoke.php`

Expected: every file prints `ok`.

- [ ] **Step 5: Commit**

```bash
git add includes/class-data.php includes/abilities/class-theme-options-abilities.php includes/abilities/class-page-abilities.php includes/abilities/class-uncode-meta-abilities.php includes/abilities/class-wpbakery-api-abilities.php includes/abilities/class-composer-abilities.php tests/theme-summary-smoke.php
git commit -m "$(cat <<'EOF'
Return short theme option values unless a caller asks for the full store.

Default tool descriptions now say when an agent should call them.

EOF
)"
```
