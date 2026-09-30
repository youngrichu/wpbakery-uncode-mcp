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
		$result   = $this->parse_range( $content, 0, null, array(), $warnings );
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
				if ( ! $full && mb_strlen( $text, 'UTF-8' ) > self::TEXT_LIMIT ) {
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

	public function apply( string $content, array $ops ): array {
		$parsed = $this->parse( $content );
		$nodes  = $this->assign_paths( $parsed['nodes'] );
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
		$dup = $this->first_duplicate_el_id( $nodes );
		if ( null !== $dup ) {
			return $this->fail( 'duplicate_el_id', 'Duplicate el_id: ' . $dup );
		}
		$serialized = $this->serialize( $nodes );
		return array(
			'ok'       => true,
			'content'  => $serialized,
			'nodes'    => $nodes,
			'warnings' => $this->parse( $serialized )['warnings'],
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
		$attrs = $this->normalize_agent_attrs( $op['attrs'], true );
		if ( ! $attrs['ok'] ) {
			return $attrs;
		}
		$clean = $attrs['attrs'];
		$slot  = $this->parent_and_index( $target['path'] );
		$next  = $this->update_children(
			$nodes,
			$slot['parent'],
			function ( array $children ) use ( $slot, $clean ) {
				$pos = $this->element_positions( $children );
				if ( ! isset( $pos[ $slot['index'] ] ) ) {
					return null;
				}
				$node = $children[ $pos[ $slot['index'] ] ];
				foreach ( $clean as $key => $value ) {
					if ( null === $value ) {
						unset( $node['attrs'][ $key ] );
					} else {
						$node['attrs'][ $key ] = $value;
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
				$node                 = $children[ $pos[ $slot['index'] ] ];
				$node['children']     = $inner;
				$node['self_closing'] = false;
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
		if ( '' === $op['content'] && true !== ( $op['allow_empty'] ?? null ) ) {
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
		$built = $this->agent_element_from_input( $op['node'] );
		if ( ! $built['ok'] ) {
			return $built;
		}
		return array(
			'ok'    => true,
			'nodes' => array( $built['node'] ),
		);
	}

	private function agent_element_from_input( array $input ): array {
		$tag = $input['tag'] ?? null;
		if ( ! is_string( $tag ) || '' === $tag || self::TEXT_TAG === $tag ) {
			return $this->fail( 'invalid_op', 'node.tag is required.' );
		}
		if ( array_key_exists( 'attrs', $input ) && ! is_array( $input['attrs'] ) ) {
			return $this->fail( 'invalid_op', 'node.attrs must be an array.' );
		}
		if ( array_key_exists( 'children', $input ) && ! is_array( $input['children'] ) ) {
			return $this->fail( 'invalid_op', 'node.children must be an array.' );
		}
		$attrs = $this->normalize_agent_attrs( is_array( $input['attrs'] ?? null ) ? $input['attrs'] : array(), false );
		if ( ! $attrs['ok'] ) {
			return $attrs;
		}
		$children = array();
		foreach ( is_array( $input['children'] ?? null ) ? $input['children'] : array() as $child ) {
			$built = $this->agent_child_from_input( $child );
			if ( ! $built['ok'] ) {
				return $built;
			}
			$children[] = $built['node'];
		}
		return array(
			'ok'   => true,
			'node' => $this->element( $tag, $attrs['attrs'], $children, ! empty( $input['self_closing'] ) ),
		);
	}

	private function agent_child_from_input( mixed $child ): array {
		if ( ! is_array( $child ) ) {
			return $this->fail( 'invalid_op', 'Each child must be a node.' );
		}
		if ( self::TEXT_TAG === ( $child['tag'] ?? null ) ) {
			if ( ! isset( $child['text'] ) || ! is_string( $child['text'] ) ) {
				return $this->fail( 'invalid_op', 'Text nodes require a string text.' );
			}
			return array(
				'ok'   => true,
				'node' => array(
					'tag'  => self::TEXT_TAG,
					'text' => $child['text'],
				),
			);
		}
		return $this->agent_element_from_input( $child );
	}

	private function normalize_agent_attrs( array $attrs, bool $allow_null ): array {
		$out = array();
		foreach ( $attrs as $key => $value ) {
			$key_name = (string) $key;
			if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $key_name ) ) {
				return $this->fail( 'invalid_op', 'Attribute keys must match ^[A-Za-z0-9_-]+$.' );
			}
			if ( null === $value ) {
				if ( ! $allow_null ) {
					return $this->fail( 'invalid_op', 'Attribute values must be scalar.' );
				}
				$out[ $key_name ] = null;
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) || ! is_scalar( $value ) ) {
				return $this->fail( 'invalid_op', 'Attribute values must be scalar.' );
			}
			$out[ $key_name ] = $this->attr_scalar_string( $value );
		}
		return array(
			'ok'    => true,
			'attrs' => $out,
		);
	}

	private function attr_scalar_string( $value ): string {
		if ( true === $value ) {
			return 'true';
		}
		if ( false === $value ) {
			return 'false';
		}
		return (string) $value;
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

	public function node_at( array $nodes, string $path ): ?array {
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

	private function parse_range( string $content, int $offset, ?string $closer, array $ancestors, array &$warnings ): array {
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
				if ( null !== $closer && in_array( $close['tag'], $ancestors, true ) ) {
					$flush();
					return array(
						'nodes'  => $nodes,
						'end'    => $i,
						'closed' => false,
					);
				}
				if ( null !== $closer ) {
					$text .= substr( $content, $i, $end - $i );
					$i     = $end;
					continue;
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
			$child_ancestors = null === $closer ? $ancestors : array_merge( $ancestors, array( $closer ) );
			$inner           = $this->parse_range( $content, $open['end'], $open['tag'], $child_ancestors, $warnings );
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
				$stop_tag = $this->peek_closer_tag( $content, $inner['end'] );
				if ( null === $stop_tag ) {
					$i = $inner['end'];
					continue;
				}
				if ( null !== $closer && $stop_tag === $closer ) {
					$i = $inner['end'];
					continue;
				}
				if ( in_array( $stop_tag, $ancestors, true ) ) {
					return array(
						'nodes'  => $nodes,
						'end'    => $inner['end'],
						'closed' => false,
					);
				}
				$i = $inner['end'];
				continue;
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

	private function peek_closer_tag( string $content, int $offset ): ?string {
		if ( $offset >= strlen( $content ) || '[' !== $content[ $offset ] ) {
			return null;
		}
		if ( $offset + 1 >= strlen( $content ) || '/' !== $content[ $offset + 1 ] ) {
			return null;
		}
		$close = $this->read_tag_name( $content, $offset + 2 );
		if ( null === $close ) {
			return null;
		}
		$end = $this->closing_bracket( $content, $close['end'] );
		if ( null === $end ) {
			return null;
		}
		return $close['tag'];
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
		if ( mb_strlen( $text, 'UTF-8' ) <= self::TEXT_LIMIT ) {
			return $text;
		}
		return mb_substr( $text, 0, self::TEXT_LIMIT, 'UTF-8' ) . '…';
	}
}

/**
 * Prepare serialized shortcodes for post storage.
 *
 * @param string $content Serialized shortcodes.
 * @param bool   $unfiltered Whether the user may store unfiltered HTML.
 * @return string
 */
function wpbakery_uncode_mcp_content_for_storage( string $content, bool $unfiltered ): string {
	if ( $unfiltered ) {
		return $content;
	}
	return wp_kses_post( $content );
}
