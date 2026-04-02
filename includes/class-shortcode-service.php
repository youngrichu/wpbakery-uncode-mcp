<?php
/**
 * Helpers for manipulating WPBakery shortcode strings.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Shortcode_Service {
	/**
	 * Insert shortcode string into content.
	 *
	 * @param string $content Existing content.
	 * @param string $shortcode Shortcode string.
	 * @param string $position append|prepend|before_anchor|after_anchor.
	 * @param string $anchor Optional anchor shortcode fragment.
	 * @return string
	 */
	public function insert_shortcode( string $content, string $shortcode, string $position = 'append', string $anchor = '' ): string {
		$shortcode = trim( $shortcode );
		if ( '' === $shortcode ) {
			return $content;
		}

		switch ( $position ) {
			case 'prepend':
				return $shortcode . "\n" . $content;
			case 'before_anchor':
				if ( '' !== $anchor && false !== strpos( $content, $anchor ) ) {
					return preg_replace( '/' . preg_quote( $anchor, '/' ) . '/', $shortcode . "\n" . $anchor, $content, 1 ) ?: $content;
				}
				return $shortcode . "\n" . $content;
			case 'after_anchor':
				if ( '' !== $anchor && false !== strpos( $content, $anchor ) ) {
					return preg_replace( '/' . preg_quote( $anchor, '/' ) . '/', $anchor . "\n" . $shortcode, $content, 1 ) ?: $content;
				}
				return $content . "\n" . $shortcode;
			case 'append':
			default:
				return $content . "\n" . $shortcode;
		}
	}

	/**
	 * Remove first shortcode by tag from content.
	 *
	 * @param string $content Existing content.
	 * @param string $tag Shortcode tag.
	 * @return array {content, removed}
	 */
	public function remove_first_shortcode_by_tag( string $content, string $tag ): array {
		$tag = sanitize_key( $tag );
		if ( '' === $tag ) {
			return array(
				'content' => $content,
				'removed' => false,
			);
		}

		$pattern = $this->build_shortcode_pattern( $tag );
		$next    = preg_replace( $pattern, '', $content, 1, $count );

		if ( null === $next ) {
			$next = $content;
		}

		return array(
			'content' => trim( (string) $next ),
			'removed' => $count > 0,
		);
	}

	/**
	 * List shortcode tags used in content.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	public function list_shortcode_tags( string $content ): array {
		// Use a lightweight opener scan so nested WPBakery tags are all discovered.
		if ( ! preg_match_all( '/\[(?!\/)([a-zA-Z0-9_\-]+)\b/', $content, $matches ) ) {
			return array();
		}

		$tags = array_map( 'sanitize_key', $matches[1] );
		$tags = array_filter( $tags );

		return array_values( array_unique( $tags ) );
	}

	/**
	 * Build regex that removes both enclosing and self-closing variants.
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	private function build_shortcode_pattern( string $tag ): string {
		$safe_tag = preg_quote( $tag, '/' );

		return '/\[' . $safe_tag . '(?:\s[^\]]*)?\](?:.*?\[\/' . $safe_tag . '\])?/s';
	}
}
