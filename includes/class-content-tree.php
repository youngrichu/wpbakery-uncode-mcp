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
				if ( null !== $closer ) {
					$flush();
					return array(
						'nodes'  => $nodes,
						'end'    => $i,
						'closed' => false,
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
