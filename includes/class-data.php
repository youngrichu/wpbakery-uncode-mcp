<?php
/**
 * Data access and mutations for WPBakery/Uncode content.
 *
 * @package WPBakery_Uncode_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPBakery_Uncode_MCP_Data {

	/** Uncode's theme option arrays; the only options the theme tools may touch. */
	const UNCODE_OPTION_NAMES = array( 'uncode_theme_options', 'ot_options', 'uncode_options' );

	/**
	 * WPBakery's raw HTML/JS elements store base64 payloads that kses can't see,
	 * so only users who may post unfiltered HTML can save them.
	 *
	 * @param string $content Incoming content.
	 * @return true|WP_Error
	 */
	public static function check_raw_elements( string $content ) {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return true;
		}
		if ( preg_match( '/\[\s*vc_raw_(html|js)\b/i', $content ) ) {
			return new WP_Error( 'forbidden_raw_element', __( 'vc_raw_html and vc_raw_js require the unfiltered_html capability.', 'wpbakery-uncode-mcp' ) );
		}
		return true;
	}

	/**
	 * Custom CSS is printed inside a <style> block, so a closing tag would let it
	 * break out into HTML.
	 */
	private static function css_has_markup( string $css ): bool {
		return false !== stripos( $css, '</style' ) || false !== stripos( $css, '<script' );
	}
	/**
	 * @var WPBakery_Uncode_MCP_Content_Tree
	 */
	private $content_tree;

	public function __construct() {
		$this->content_tree = new WPBakery_Uncode_MCP_Content_Tree();
	}

	/**
	 * Fetch page payload with content and optional rendered HTML.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $include_rendered Whether to include rendered content.
	 * @return array|WP_Error
	 */
	public function get_page_payload( int $post_id, bool $include_rendered = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}

		$payload = array(
			'post_id'       => $post->ID,
			'post_type'     => $post->post_type,
			'status'        => $post->post_status,
			'title'         => get_the_title( $post ),
			'slug'          => $post->post_name,
			'edit_url'      => get_edit_post_link( $post->ID, 'raw' ) ?: '',
			'preview_url'   => get_permalink( $post->ID ) ?: '',
			'content_raw'   => (string) $post->post_content,
			'has_wpbakery'  => $this->is_wpbakery_content( $post->post_content ),
			'uncode_theme'  => wpbakery_uncode_mcp_is_uncode_active(),
		);

		if ( $include_rendered ) {
			$payload['content_rendered'] = apply_filters( 'the_content', $post->post_content );
		}

		return $payload;
	}

	/**
	 * Read a page as a nested shortcode tree.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $path Optional element path. Empty returns the whole tree.
	 * @param int    $depth Summary depth.
	 * @param bool   $full Whether to include full inner text.
	 * @return array|WP_Error
	 */
	public function get_content_tree( int $post_id, string $path = '', int $depth = 3, bool $full = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}
		$parsed = $this->content_tree->parse( (string) $post->post_content );
		$nodes  = $this->content_tree->assign_paths( $parsed['nodes'] );
		if ( '' !== $path ) {
			$found = $this->content_tree->node_at( $nodes, $path );
			if ( null === $found || (string) ( $found['path'] ?? '' ) !== $path ) {
				return new WP_Error( 'unknown_path', __( 'Unknown path.', 'wpbakery-uncode-mcp' ) );
			}
			$nodes = array( $found );
		}
		return array(
			'post_id'  => $post_id,
			'tree'     => $this->content_tree->summarize( $nodes, $depth, $full ),
			'warnings' => $parsed['warnings'],
		);
	}

	/**
	 * Apply ordered content operations and save unless this is a dry run.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $ops Operations.
	 * @param bool  $dry_run When true, skip saving.
	 * @return array|WP_Error
	 */
	public function apply_content_ops( int $post_id, array $ops, bool $dry_run = false ) {
		$post = get_post( $post_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}
		$result = $this->content_tree->apply( (string) $post->post_content, $ops );
		if ( ! $result['ok'] ) {
			return new WP_Error( $result['code'], $result['message'] );
		}
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			// Existing raw blocks (placed by a trusted user) may stay; new ones may not.
			$raw_pattern = '/\[\s*vc_raw_(html|js)\b.*?\[\/\s*vc_raw_\1\s*\]/is';
			preg_match_all( $raw_pattern, (string) $post->post_content, $before );
			preg_match_all( $raw_pattern, (string) $result['content'], $after );
			$open_pattern = '/\[\s*vc_raw_(html|js)\b/i';
			if ( array_diff( $after[0], $before[0] )
				|| preg_match_all( $open_pattern, (string) $result['content'] ) > preg_match_all( $open_pattern, (string) $post->post_content ) ) {
				return new WP_Error( 'forbidden_raw_element', __( 'vc_raw_html and vc_raw_js require the unfiltered_html capability.', 'wpbakery-uncode-mcp' ) );
			}
		}
		if ( ! $dry_run ) {
			$stored = wpbakery_uncode_mcp_content_for_storage(
				$result['content'],
				current_user_can( 'unfiltered_html' )
			);
			$saved  = wp_update_post(
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

	/**
	 * Create a page/post with optional content and meta.
	 *
	 * @param array $args Input arguments.
	 * @return array|WP_Error
	 */
	public function create_page( array $args ) {
		$title     = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$status    = sanitize_key( (string) ( $args['status'] ?? 'draft' ) );
		$post_type = sanitize_key( (string) ( $args['post_type'] ?? 'page' ) );
		$template  = sanitize_text_field( (string) ( $args['template'] ?? '' ) );
		$content   = (string) ( $args['content'] ?? '' );
		$meta      = is_array( $args['uncode_meta'] ?? null ) ? $args['uncode_meta'] : array();

		if ( '' === $title ) {
			return new WP_Error( 'missing_title', __( 'The title parameter is required.', 'wpbakery-uncode-mcp' ) );
		}

		if ( ! in_array( $status, array( 'draft', 'publish', 'private', 'pending' ), true ) ) {
			$status = 'draft';
		}

		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			$post_type = 'page';
		}

		$type_object = get_post_type_object( $post_type );
		if ( 'publish' === $status && $type_object && ! current_user_can( $type_object->cap->publish_posts ) ) {
			$status = 'pending';
		}
		if ( 'private' === $status && $type_object && ! current_user_can( $type_object->cap->publish_posts ) ) {
			$status = 'draft';
		}

		$raw_check = self::check_raw_elements( $content );
		if ( is_wp_error( $raw_check ) ) {
			return $raw_check;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_status'  => $status,
				'post_type'    => $post_type,
				'post_content' => wp_kses_post( $content ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( '' !== $template ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		if ( ! empty( $meta ) ) {
			$this->update_uncode_meta( $post_id, $meta );
		}

		return $this->get_page_payload( (int) $post_id, false );
	}

	/**
	 * Update post content.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Content fragment.
	 * @param string $mode replace|append|prepend.
	 * @return array|WP_Error
	 */
	public function update_page_content( int $post_id, string $content, string $mode = 'replace' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}

		$raw_check = self::check_raw_elements( $content );
		if ( is_wp_error( $raw_check ) ) {
			return $raw_check;
		}

		$incoming = wp_kses_post( $content );
		$current  = (string) $post->post_content;

		if ( 'append' === $mode ) {
			$next = $current . "\n" . $incoming;
		} elseif ( 'prepend' === $mode ) {
			$next = $incoming . "\n" . $current;
		} else {
			$next = $incoming;
		}

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $next,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'content_size' => strlen( $next ),
		);
	}

	/**
	 * Read Uncode meta. When keys are omitted, all _uncode_* keys are returned.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $keys Meta keys.
	 * @return array|WP_Error
	 */
	public function get_uncode_meta( int $post_id, array $keys = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}

		if ( ! empty( $keys ) ) {
			$values = array();
			foreach ( $keys as $key ) {
				$key = (string) $key;
				if ( ! $this->is_allowed_uncode_meta_key( $key ) ) {
					continue;
				}
				$values[ $key ] = get_post_meta( $post_id, $key, true );
			}

			return array(
				'post_id' => $post_id,
				'meta'    => $values,
			);
		}

		$all = get_post_meta( $post_id );
		$out = array();
		foreach ( $all as $key => $rows ) {
			if ( ! $this->is_allowed_uncode_meta_key( $key ) ) {
				continue;
			}
			$out[ $key ] = maybe_unserialize( $rows[0] ?? '' );
		}

		return array(
			'post_id' => $post_id,
			'meta'    => $out,
		);
	}

	/**
	 * Update Uncode meta keys.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta Key-value pairs.
	 * @return array|WP_Error
	 */
	public function update_uncode_meta( int $post_id, array $meta ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'invalid_post', __( 'Post not found.', 'wpbakery-uncode-mcp' ) );
		}

		$updated = array();
		foreach ( $meta as $raw_key => $raw_value ) {
			$key = sanitize_key( (string) $raw_key );
			if ( ! $this->is_allowed_uncode_meta_key( $key ) ) {
				continue;
			}

			$value = $this->sanitize_meta_value( $raw_value );
			update_post_meta( $post_id, $key, $value );
			$updated[] = $key;
		}

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'updated_keys' => array_values( array_unique( $updated ) ),
		);
	}

	/**
	 * Build one WPBakery shortcode string.
	 *
	 * @param string     $tag Tag name.
	 * @param array      $attrs Attributes.
	 * @param string|nil $content Inner content.
	 * @return string
	 */
	public function build_shortcode( string $tag, array $attrs = array(), $content = null ): string {
		$tag = sanitize_key( $tag );

		$parts = array();
		foreach ( $attrs as $key => $value ) {
			$clean_key = sanitize_key( (string) $key );
			if ( '' === $clean_key ) {
				continue;
			}
			$parts[] = sprintf( '%s="%s"', $clean_key, esc_attr( (string) $value ) );
		}
		$attr_string = empty( $parts ) ? '' : ' ' . implode( ' ', $parts );

		if ( null === $content ) {
			return sprintf( '[%1$s%2$s]', $tag, $attr_string );
		}

		return sprintf( '[%1$s%2$s]%3$s[/%1$s]', $tag, $attr_string, wp_kses_post( (string) $content ) );
	}

	/**
	 * Heuristic check for WPBakery shortcodes.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	public function is_wpbakery_content( string $content ): bool {
		return (bool) preg_match( '/\[(vc_row|vc_column|vc_column_text|vc_single_image|vc_custom_heading|vc_btn)\b/i', $content );
	}

	/**
	 * Resolve Uncode theme options name from common candidates.
	 *
	 * @param string $option_name Optional explicit option name.
	 * @return string|WP_Error
	 */
	public function resolve_uncode_option_name( string $option_name = '' ) {
		$candidates  = self::UNCODE_OPTION_NAMES;
		$option_name = sanitize_key( $option_name );
		if ( '' !== $option_name ) {
			// Only Uncode's own option arrays. Anything else would let a caller read
			// other plugins' secrets or write roles, cron or active_plugins.
			if ( ! in_array( $option_name, $candidates, true ) ) {
				return new WP_Error( 'invalid_option_name', __( 'option_name must be one of the Uncode theme option names.', 'wpbakery-uncode-mcp' ) );
			}
			return $option_name;
		}

		foreach ( $candidates as $candidate ) {
			$value = get_option( $candidate, null );
			if ( is_array( $value ) ) {
				return $candidate;
			}
		}

		return new WP_Error( 'missing_theme_options', __( 'Could not auto-detect Uncode theme options. Pass option_name explicitly.', 'wpbakery-uncode-mcp' ) );
	}

	/**
	 * Summarize a theme option value for compact tool output.
	 *
	 * @param mixed $value Raw option value.
	 * @param bool  $full When true, return the value unchanged.
	 * @return mixed
	 */
	public static function summarize_theme_value( $value, bool $full ) {
		if ( $full ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			if ( mb_strlen( $value, 'UTF-8' ) <= 160 ) {
				return $value;
			}
			return mb_substr( $value, 0, 160, 'UTF-8' ) . '…';
		}
		if ( is_array( $value ) ) {
			return array(
				'type'  => 'array',
				'count' => count( $value ),
			);
		}
		return $value;
	}

	/**
	 * Read Uncode theme options, optionally by specific keys.
	 *
	 * @param string $option_name Option name.
	 * @param array  $keys Optional top-level keys to return.
	 * @param bool   $full When true, return raw option values.
	 * @return array|WP_Error
	 */
	public function get_theme_options( string $option_name = '', array $keys = array(), bool $full = false ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$all = get_option( $resolved, array() );
		if ( ! is_array( $all ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		if ( empty( $keys ) ) {
			$out = array();
			foreach ( $all as $key => $value ) {
				$out[ $key ] = self::summarize_theme_value( $value, $full );
			}
			return array(
				'option_name' => $resolved,
				'options'     => $out,
			);
		}

		$out = array();
		foreach ( $keys as $key ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			if ( array_key_exists( $key, $all ) ) {
				$out[ $key ] = self::summarize_theme_value( $all[ $key ], $full );
			}
		}

		return array(
			'option_name' => $resolved,
			'options'     => $out,
		);
	}

	/**
	 * Partial update for Uncode theme options.
	 *
	 * @param array  $changes Key/value changes to merge.
	 * @param string $option_name Option name.
	 * @return array|WP_Error
	 */
	public function update_theme_options( array $changes, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$safe_changes = array();
		foreach ( $changes as $key => $value ) {
			$k = sanitize_key( (string) $key );
			if ( '' === $k || $this->is_sensitive_option_key( $k ) ) {
				continue;
			}
			$guard = $this->guard_raw_theme_write( $k, $value );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
			$safe_changes[ $k ] = $this->sanitize_meta_value( $value );
		}

		if ( empty( $safe_changes ) ) {
			return new WP_Error( 'no_safe_changes', __( 'No safe theme option keys to update.', 'wpbakery-uncode-mcp' ) );
		}

		$next = array_merge( $current, $safe_changes );
		update_option( $resolved, $next );

		return array(
			'success'      => true,
			'option_name'  => $resolved,
			'updated_keys' => array_values( array_keys( $safe_changes ) ),
		);
	}

	/**
	 * Convenience updater for CSS/JS values inside theme options.
	 *
	 * @param string $css CSS text.
	 * @param string $js JS text.
	 * @param bool   $append Whether to append instead of replace.
	 * @param string $option_name Option name.
	 * @return array|WP_Error
	 */
	public function update_theme_css_js( string $css = '', string $js = '', bool $append = false, string $option_name = '' ) {
		if ( '' !== $js && ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'forbidden_js', __( 'Saving custom JS requires the unfiltered_html capability.', 'wpbakery-uncode-mcp' ) );
		}
		if ( self::css_has_markup( $css ) ) {
			return new WP_Error( 'invalid_css', __( 'CSS must not contain HTML tags.', 'wpbakery-uncode-mcp' ) );
		}
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updates = array();
		if ( '' !== $css ) {
			$existing         = isset( $current['uncode_custom_css'] ) ? (string) $current['uncode_custom_css'] : '';
			$updates['uncode_custom_css'] = $append ? trim( $existing . "\n\n" . $css ) : $css;
		}
		if ( '' !== $js ) {
			$existing        = isset( $current['uncode_custom_js'] ) ? (string) $current['uncode_custom_js'] : '';
			$updates['uncode_custom_js'] = $append ? trim( $existing . "\n\n" . $js ) : $js;
		}

		if ( empty( $updates ) ) {
			return new WP_Error( 'missing_css_js', __( 'Provide css and/or js.', 'wpbakery-uncode-mcp' ) );
		}

		$next = array_merge( $current, $updates );
		update_option( $resolved, $next );

		return array(
			'success'      => true,
			'option_name'  => $resolved,
			'updated_keys' => array_values( array_keys( $updates ) ),
		);
	}

	/**
	 * Discover likely Uncode theme option keys by category.
	 *
	 * @param string $option_name Option name.
	 * @param bool   $full When true, include raw values in previews.
	 * @return array|WP_Error
	 */
	public function discover_theme_option_keys( string $option_name = '', bool $full = false ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		$categories = array(
			'css'        => '/(css|custom_css|style)/i',
			'js'         => '/(js|javascript|custom_js)/i',
			'navigation' => '/(menu|nav|navbar|header_menu|logo|sticky)/i',
			'layout'     => '/(layout|grid|container|sidebar|boxed|width|spacing|padding|margin)/i',
			'typography' => '/(font|typography|text|h1|h2|h3|body_font)/i',
			'colors'     => '/(color|palette|accent|primary|secondary)/i',
			'footer'     => '/(footer|copyright)/i',
		);

		$result = array(
			'option_name' => $resolved,
			'all_keys'    => array_values( array_keys( $current ) ),
			'previews'    => array(),
			'categories'  => array(),
		);

		foreach ( array_keys( $current ) as $key ) {
			$result['previews'][ $key ] = self::summarize_theme_value( $current[ $key ], $full );
		}

		foreach ( $categories as $category => $regex ) {
			$result['categories'][ $category ] = array();
		}

		foreach ( array_keys( $current ) as $key ) {
			foreach ( $categories as $category => $regex ) {
				if ( preg_match( $regex, (string) $key ) ) {
					$result['categories'][ $category ][] = $key;
				}
			}
		}

		foreach ( array_keys( $result['categories'] ) as $category ) {
			$result['categories'][ $category ] = array_values( array_unique( $result['categories'][ $category ] ) );
		}

		return $result;
	}

	/**
	 * Read nested theme option value by dot path.
	 *
	 * @param string $path Dot path, e.g. "header.navigation.style".
	 * @param string $option_name Option name.
	 * @return array|WP_Error
	 */
	public function get_theme_option_path( string $path, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( '' === trim( $path ) ) {
			return new WP_Error( 'missing_path', __( 'path is required.', 'wpbakery-uncode-mcp' ) );
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		$segments = $this->path_to_segments( $path );
		$value    = $this->array_get_by_path( $current, $segments );
		if ( null === $value ) {
			return new WP_Error( 'path_not_found', __( 'Theme option path not found.', 'wpbakery-uncode-mcp' ) );
		}

		return array(
			'option_name' => $resolved,
			'path'        => implode( '.', $segments ),
			'value'       => $value,
		);
	}

	/**
	 * Update nested theme option value by dot path.
	 *
	 * @param string $path Dot path.
	 * @param mixed  $value New value.
	 * @param bool   $create_missing Whether to create missing path segments.
	 * @param string $option_name Option name.
	 * @return array|WP_Error
	 */
	public function update_theme_option_path( string $path, $value, bool $create_missing = false, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		if ( '' === trim( $path ) ) {
			return new WP_Error( 'missing_path', __( 'path is required.', 'wpbakery-uncode-mcp' ) );
		}
		$guard = $this->guard_raw_theme_write( $path, $value );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$segments = $this->path_to_segments( $path );
		if ( empty( $segments ) ) {
			return new WP_Error( 'invalid_path', __( 'Invalid path.', 'wpbakery-uncode-mcp' ) );
		}

		if ( $this->is_sensitive_option_key( (string) $segments[0] ) ) {
			return new WP_Error( 'restricted_path', __( 'Path starts with a restricted key.', 'wpbakery-uncode-mcp' ) );
		}

		$sanitized = $this->sanitize_meta_value( $value );
		$ok        = $this->array_set_by_path( $current, $segments, $sanitized, $create_missing );
		if ( ! $ok ) {
			return new WP_Error( 'path_not_found', __( 'Path not found. Set create_missing=true to create it.', 'wpbakery-uncode-mcp' ) );
		}

		update_option( $resolved, $current );

		return array(
			'success'     => true,
			'option_name' => $resolved,
			'path'        => implode( '.', $segments ),
		);
	}

	/**
	 * Upsert a labeled CSS block inside the custom CSS option.
	 *
	 * @param string $label Block label.
	 * @param string $css CSS body.
	 * @param string $option_name Option name.
	 * @return array|WP_Error
	 */
	public function upsert_theme_css_block( string $label, string $css, string $option_name = '' ) {
		$label = sanitize_key( $label );
		if ( '' === $label ) {
			return new WP_Error( 'missing_label', __( 'label is required.', 'wpbakery-uncode-mcp' ) );
		}
		if ( '' === trim( $css ) ) {
			return new WP_Error( 'missing_css', __( 'css is required.', 'wpbakery-uncode-mcp' ) );
		}
		if ( self::css_has_markup( $css ) || self::css_has_markup( $label ) ) {
			return new WP_Error( 'invalid_css', __( 'CSS must not contain HTML tags.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		$existing = isset( $current['uncode_custom_css'] ) ? (string) $current['uncode_custom_css'] : '';

		$start = "/* MCP-BLOCK:" . $label . ":START */";
		$end   = "/* MCP-BLOCK:" . $label . ":END */";
		$block = $start . "\n" . trim( $css ) . "\n" . $end;

		$pattern = '/' . preg_quote( $start, '/' ) . '.*?' . preg_quote( $end, '/' ) . '/s';
		if ( preg_match( $pattern, $existing ) ) {
			$next = (string) preg_replace( $pattern, $block, $existing, 1 );
		} else {
			$next = trim( $existing . "\n\n" . $block );
		}

		$current['uncode_custom_css'] = $next;
		update_option( $resolved, $current );

		return array(
			'success'     => true,
			'option_name' => $resolved,
			'label'       => $label,
		);
	}

	/**
	 * High-level navigation style update.
	 *
	 * @param string $style Style slug.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_navigation_style( string $style, string $option_name = '' ) {
		$style = sanitize_key( $style );
		if ( '' === $style ) {
			return new WP_Error( 'missing_style', __( 'style is required.', 'wpbakery-uncode-mcp' ) );
		}

		$map = array(
			'minimal'   => 'minimal',
			'classic'   => 'classic',
			'centered'  => 'centered',
			'offcanvas' => 'offcanvas',
			'overlay'   => 'overlay',
		);
		$value = $map[ $style ] ?? $style;

		return $this->update_theme_options_by_candidates(
			array(
				'menu_type',
				'menu_style',
				'header_menu_style',
				'uncode_menu_style',
				'header.navigation.style',
			),
			$value,
			$option_name
		);
	}

	/**
	 * High-level menu layout update.
	 *
	 * @param string $layout Layout slug.
	 * @param string $mobile_behavior Mobile behavior slug.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_menu_layout( string $layout, string $mobile_behavior, string $option_name = '' ) {
		$layout          = sanitize_key( $layout );
		$mobile_behavior = sanitize_key( $mobile_behavior );
		if ( '' === $layout ) {
			return new WP_Error( 'missing_layout', __( 'layout is required.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated_paths = array();
		$p             = $this->set_first_matching_candidate_path(
			$current,
			array( 'menu_layout', 'header_menu_layout', 'uncode_menu_layout', 'header.navigation.layout' ),
			$layout
		);
		if ( '' !== $p ) {
			$updated_paths[] = $p;
		}

		if ( '' !== $mobile_behavior ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'menu_mobile_behavior', 'mobile_menu_style', 'uncode_mobile_menu_style', 'header.navigation.mobile_behavior' ),
				$mobile_behavior
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * High-level megamenu visual settings update.
	 *
	 * @param string $dropdown_style Dropdown style.
	 * @param bool   $full_width Full width dropdown toggle.
	 * @param bool   $animations Dropdown animation toggle.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_megamenu_visuals( string $dropdown_style, bool $full_width, bool $animations, string $option_name = '' ) {
		$dropdown_style = sanitize_key( $dropdown_style );
		if ( '' === $dropdown_style ) {
			return new WP_Error( 'missing_dropdown_style', __( 'dropdown_style is required.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated_paths = array();
		$updates       = array(
			array(
				'candidates' => array( 'menu_dropdown_style', 'dropdown_style', 'uncode_dropdown_style', 'header.menu.dropdown.style' ),
				'value'      => $dropdown_style,
			),
			array(
				'candidates' => array( 'menu_dropdown_full_width', 'dropdown_full_width', 'uncode_dropdown_full_width', 'header.menu.dropdown.full_width' ),
				'value'      => $full_width ? 'on' : 'off',
			),
			array(
				'candidates' => array( 'menu_dropdown_animation', 'dropdown_animation', 'uncode_dropdown_animation', 'header.menu.dropdown.animation' ),
				'value'      => $animations ? 'on' : 'off',
			),
		);

		foreach ( $updates as $update ) {
			$p = $this->set_first_matching_candidate_path( $current, $update['candidates'], $update['value'] );
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * High-level menu CTA button settings update.
	 *
	 * @param bool   $enabled Enable CTA.
	 * @param string $label CTA label.
	 * @param string $url CTA URL.
	 * @param string $style CTA visual style.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_menu_cta_settings( bool $enabled, string $label, string $url, string $style, string $option_name = '' ) {
		$label = sanitize_text_field( $label );
		$url   = esc_url_raw( $url );
		$style = sanitize_key( $style );

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated_paths = array();
		$updates       = array(
			array(
				'candidates' => array( 'menu_cta_enabled', 'cta_menu_enabled', 'uncode_menu_cta_enabled', 'header.menu.cta.enabled' ),
				'value'      => $enabled ? 'on' : 'off',
			),
		);
		if ( '' !== $label ) {
			$updates[] = array(
				'candidates' => array( 'menu_cta_label', 'cta_menu_label', 'uncode_menu_cta_label', 'header.menu.cta.label' ),
				'value'      => $label,
			);
		}
		if ( '' !== $url ) {
			$updates[] = array(
				'candidates' => array( 'menu_cta_url', 'cta_menu_url', 'uncode_menu_cta_url', 'header.menu.cta.url' ),
				'value'      => $url,
			);
		}
		if ( '' !== $style ) {
			$updates[] = array(
				'candidates' => array( 'menu_cta_style', 'cta_menu_style', 'uncode_menu_cta_style', 'header.menu.cta.style' ),
				'value'      => $style,
			);
		}

		foreach ( $updates as $update ) {
			$p = $this->set_first_matching_candidate_path( $current, $update['candidates'], $update['value'] );
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * High-level logo settings update (main/sticky/mobile + retina variants).
	 *
	 * @param array  $settings Logo settings payload.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_logo_settings( array $settings, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$map = array(
			'logo_id'                => array( 'logo_main', 'logo_default', 'uncode_logo', 'header.logo.main_id' ),
			'sticky_logo_id'         => array( 'logo_sticky', 'uncode_logo_sticky', 'header.logo.sticky_id' ),
			'mobile_logo_id'         => array( 'logo_mobile', 'uncode_logo_mobile', 'header.logo.mobile_id' ),
			'logo_retina_id'         => array( 'logo_main_retina', 'logo_default_retina', 'uncode_logo_retina', 'header.logo.main_retina_id' ),
			'sticky_logo_retina_id'  => array( 'logo_sticky_retina', 'uncode_logo_sticky_retina', 'header.logo.sticky_retina_id' ),
			'mobile_logo_retina_id'  => array( 'logo_mobile_retina', 'uncode_logo_mobile_retina', 'header.logo.mobile_retina_id' ),
			'logo_max_width'         => array( 'logo_max_width', 'header_logo_max_width', 'uncode_logo_max_width', 'header.logo.max_width' ),
		);

		$updated_paths = array();
		foreach ( $map as $input_key => $candidates ) {
			if ( ! array_key_exists( $input_key, $settings ) ) {
				continue;
			}

			$value = $settings[ $input_key ];
			if ( false !== strpos( $input_key, '_id' ) ) {
				$value = absint( $value );
				if ( $value <= 0 ) {
					continue;
				}
			} elseif ( 'logo_max_width' === $input_key ) {
				$value = max( 40, min( 1200, absint( $value ) ) );
			}

			$p = $this->set_first_matching_candidate_path( $current, $candidates, (string) $value );
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		if ( empty( $updated_paths ) ) {
			return new WP_Error( 'no_logo_updates', __( 'No valid logo settings provided.', 'wpbakery-uncode-mcp' ) );
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * Update logo link/meta settings.
	 *
	 * @param string $link_url Logo URL.
	 * @param string $alt_text Logo ALT text.
	 * @param string $link_target self|blank.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_logo_links( string $link_url, string $alt_text, string $link_target, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$link_url    = esc_url_raw( $link_url );
		$alt_text    = sanitize_text_field( $alt_text );
		$link_target = sanitize_key( $link_target );
		if ( ! in_array( $link_target, array( 'self', 'blank' ), true ) ) {
			$link_target = 'self';
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated_paths = array();
		if ( '' !== $link_url ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'logo_link', 'logo_url', 'uncode_logo_link', 'header.logo.link_url' ),
				$link_url
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}
		if ( '' !== $alt_text ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'logo_alt', 'logo_alt_text', 'uncode_logo_alt', 'header.logo.alt_text' ),
				$alt_text
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		$p = $this->set_first_matching_candidate_path(
			$current,
			array( 'logo_link_target', 'logo_target', 'uncode_logo_target', 'header.logo.link_target' ),
			$link_target
		);
		if ( '' !== $p ) {
			$updated_paths[] = $p;
		}

		if ( empty( $updated_paths ) ) {
			return new WP_Error( 'no_logo_link_updates', __( 'No valid logo link settings provided.', 'wpbakery-uncode-mcp' ) );
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * Sync logo variants from one source logo ID.
	 *
	 * @param int    $source_logo_id Source media ID (optional if main logo exists).
	 * @param bool   $set_sticky Apply to sticky logo.
	 * @param bool   $set_mobile Apply to mobile logo.
	 * @param bool   $set_retina Apply as retina variants too.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function sync_uncode_logo_variants( int $source_logo_id, bool $set_sticky, bool $set_mobile, bool $set_retina, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$logo_id = absint( $source_logo_id );
		if ( $logo_id <= 0 ) {
			$logo_id = absint(
				$this->get_first_matching_candidate_value(
					$current,
					array( 'logo_main', 'logo_default', 'uncode_logo', 'header.logo.main_id' )
				)
			);
		}
		if ( $logo_id <= 0 ) {
			return new WP_Error( 'missing_source_logo', __( 'source_logo_id is required when no main logo is configured.', 'wpbakery-uncode-mcp' ) );
		}

		$updated_paths = array();
		$p             = $this->set_first_matching_candidate_path(
			$current,
			array( 'logo_main', 'logo_default', 'uncode_logo', 'header.logo.main_id' ),
			(string) $logo_id
		);
		if ( '' !== $p ) {
			$updated_paths[] = $p;
		}

		if ( $set_sticky ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'logo_sticky', 'uncode_logo_sticky', 'header.logo.sticky_id' ),
				(string) $logo_id
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}
		if ( $set_mobile ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'logo_mobile', 'uncode_logo_mobile', 'header.logo.mobile_id' ),
				(string) $logo_id
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}
		if ( $set_retina ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'logo_main_retina', 'logo_default_retina', 'uncode_logo_retina', 'header.logo.main_retina_id' ),
				(string) $logo_id
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
			if ( $set_sticky ) {
				$p = $this->set_first_matching_candidate_path(
					$current,
					array( 'logo_sticky_retina', 'uncode_logo_sticky_retina', 'header.logo.sticky_retina_id' ),
					(string) $logo_id
				);
				if ( '' !== $p ) {
					$updated_paths[] = $p;
				}
			}
			if ( $set_mobile ) {
				$p = $this->set_first_matching_candidate_path(
					$current,
					array( 'logo_mobile_retina', 'uncode_logo_mobile_retina', 'header.logo.mobile_retina_id' ),
					(string) $logo_id
				);
				if ( '' !== $p ) {
					$updated_paths[] = $p;
				}
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'source_logo_id'=> $logo_id,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * Validate candidate key/path mapping against the current Uncode options payload.
	 *
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function validate_uncode_theme_mapping( string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		$groups = array(
			'logo_main'          => array( 'logo_main', 'logo_default', 'uncode_logo', 'header.logo.main_id' ),
			'logo_sticky'        => array( 'logo_sticky', 'uncode_logo_sticky', 'header.logo.sticky_id' ),
			'logo_mobile'        => array( 'logo_mobile', 'uncode_logo_mobile', 'header.logo.mobile_id' ),
			'navigation_style'   => array( 'menu_type', 'menu_style', 'header_menu_style', 'uncode_menu_style', 'header.navigation.style' ),
			'menu_layout'        => array( 'menu_layout', 'header_menu_layout', 'uncode_menu_layout', 'header.navigation.layout' ),
			'mobile_menu'        => array( 'menu_mobile_behavior', 'mobile_menu_style', 'uncode_mobile_menu_style', 'header.navigation.mobile_behavior' ),
			'header_layout'      => array( 'header_type', 'header_layout', 'uncode_header_layout', 'header.layout.type' ),
			'footer_layout'      => array( 'footer_layout', 'uncode_footer_layout', 'footer.layout.type' ),
			'typography_body'    => array( 'body_font_family', 'font_body', 'typography_body_font', 'typography.body.font_family' ),
			'typography_heading' => array( 'heading_font_family', 'font_headings', 'typography_headings_font', 'typography.headings.font_family' ),
			'custom_css'         => array( 'uncode_custom_css', 'custom_css', 'theme_custom_css' ),
			'custom_js'          => array( 'uncode_custom_js', 'custom_js', 'theme_custom_js' ),
		);

		$report = array();
		foreach ( $groups as $group => $candidates ) {
			$path   = $this->find_first_existing_candidate_path( $current, $candidates );
			$value  = null;
			$exists = '' !== $path;
			if ( $exists ) {
				$value = $this->array_get_by_path( $current, $this->path_to_segments( $path ) );
			}

			$report[ $group ] = array(
				'matched'       => $exists,
				'matched_path'  => $path,
				'matched_value' => $value,
				'candidates'    => $candidates,
			);
		}

		return array(
			'option_name' => $resolved,
			'report'      => $report,
		);
	}

	/**
	 * Apply common brand settings in a single operation.
	 *
	 * @param array  $settings Brand payload.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function apply_uncode_brand_setup( array $settings, string $option_name = '' ) {
		$results = array();

		if ( ! empty( $settings['create_snapshot'] ) ) {
			$snapshot = $this->create_theme_options_snapshot( 'brand_setup_auto', $option_name );
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			$results['snapshot'] = $snapshot;
		}

		if ( ! empty( $settings['logo'] ) && is_array( $settings['logo'] ) ) {
			$logo_result = $this->update_uncode_logo_settings( $settings['logo'], $option_name );
			if ( is_wp_error( $logo_result ) ) {
				return $logo_result;
			}
			$results['logo'] = $logo_result;
		}

		if ( ! empty( $settings['logo_links'] ) && is_array( $settings['logo_links'] ) ) {
			$links = $settings['logo_links'];
			$links_result = $this->update_uncode_logo_links(
				(string) ( $links['link_url'] ?? '' ),
				(string) ( $links['alt_text'] ?? '' ),
				(string) ( $links['link_target'] ?? 'self' ),
				$option_name
			);
			if ( is_wp_error( $links_result ) ) {
				return $links_result;
			}
			$results['logo_links'] = $links_result;
		}

		if ( isset( $settings['navigation_style'] ) ) {
			$nav_result = $this->update_uncode_navigation_style( (string) $settings['navigation_style'], $option_name );
			if ( is_wp_error( $nav_result ) ) {
				return $nav_result;
			}
			$results['navigation_style'] = $nav_result;
		}

		if ( ! empty( $settings['menu_layout'] ) ) {
			$layout = is_array( $settings['menu_layout'] ) ? $settings['menu_layout'] : array();
			$menu_result = $this->update_uncode_menu_layout(
				(string) ( $layout['layout'] ?? '' ),
				(string) ( $layout['mobile_behavior'] ?? '' ),
				$option_name
			);
			if ( is_wp_error( $menu_result ) ) {
				return $menu_result;
			}
			$results['menu_layout'] = $menu_result;
		}

		if ( ! empty( $settings['typography'] ) && is_array( $settings['typography'] ) ) {
			$type = $settings['typography'];
			$type_result = $this->update_uncode_global_typography(
				(string) ( $type['body_font'] ?? '' ),
				(string) ( $type['heading_font'] ?? '' ),
				absint( $type['base_size'] ?? 16 ),
				$option_name
			);
			if ( is_wp_error( $type_result ) ) {
				return $type_result;
			}
			$results['typography'] = $type_result;
		}

		if ( isset( $settings['primary_color'] ) && '' !== trim( (string) $settings['primary_color'] ) ) {
			$color_result = $this->update_theme_options_by_candidates(
				array( 'accent_color', 'primary_color', 'uncode_primary_color', 'colors.primary' ),
				sanitize_text_field( (string) $settings['primary_color'] ),
				$option_name
			);
			if ( is_wp_error( $color_result ) ) {
				return $color_result;
			}
			$results['primary_color'] = $color_result;
		}

		if ( isset( $settings['custom_css'] ) && '' !== trim( (string) $settings['custom_css'] ) ) {
			$css_result = $this->upsert_theme_css_block( 'brand_setup', (string) $settings['custom_css'], $option_name );
			if ( is_wp_error( $css_result ) ) {
				return $css_result;
			}
			$results['custom_css'] = $css_result;
		}

		if ( empty( $results ) ) {
			return new WP_Error( 'empty_brand_setup', __( 'No brand settings were provided.', 'wpbakery-uncode-mcp' ) );
		}

		return array(
			'success' => true,
			'results' => $results,
		);
	}

	/**
	 * Apply one of the built-in style presets.
	 *
	 * @param string $preset Preset slug.
	 * @param bool   $create_snapshot Whether to create snapshot first.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function apply_uncode_style_preset( string $preset, bool $create_snapshot = true, string $option_name = '' ) {
		$preset = sanitize_key( $preset );
		$presets = $this->get_style_presets();

		if ( ! isset( $presets[ $preset ] ) ) {
			return new WP_Error( 'invalid_preset', __( 'Unsupported preset. Use: minimal, bold, corporate, editorial.', 'wpbakery-uncode-mcp' ) );
		}

		$settings                   = $presets[ $preset ];
		$settings['create_snapshot'] = $create_snapshot;

		$result = $this->apply_uncode_brand_setup( $settings, $option_name );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'preset'  => $preset,
			'result'  => $result,
		);
	}

	/**
	 * Preview impact of a style preset without writing options.
	 *
	 * @param string $preset Preset slug.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function preview_uncode_style_preset_impact( string $preset, string $option_name = '' ) {
		$preset = sanitize_key( $preset );
		$presets = $this->get_style_presets();
		if ( ! isset( $presets[ $preset ] ) ) {
			return new WP_Error( 'invalid_preset', __( 'Unsupported preset. Use: minimal, bold, corporate, editorial.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$next = $current;
		$settings = $presets[ $preset ];

		if ( isset( $settings['navigation_style'] ) ) {
			$this->set_first_matching_candidate_path(
				$next,
				array( 'menu_type', 'menu_style', 'header_menu_style', 'uncode_menu_style', 'header.navigation.style' ),
				(string) $settings['navigation_style']
			);
		}

		if ( ! empty( $settings['menu_layout'] ) && is_array( $settings['menu_layout'] ) ) {
			$this->set_first_matching_candidate_path(
				$next,
				array( 'menu_layout', 'header_menu_layout', 'uncode_menu_layout', 'header.navigation.layout' ),
				(string) ( $settings['menu_layout']['layout'] ?? '' )
			);
			$this->set_first_matching_candidate_path(
				$next,
				array( 'menu_mobile_behavior', 'mobile_menu_style', 'uncode_mobile_menu_style', 'header.navigation.mobile_behavior' ),
				(string) ( $settings['menu_layout']['mobile_behavior'] ?? '' )
			);
		}

		if ( ! empty( $settings['typography'] ) && is_array( $settings['typography'] ) ) {
			$this->set_first_matching_candidate_path(
				$next,
				array( 'body_font_family', 'font_body', 'typography_body_font', 'typography.body.font_family' ),
				(string) ( $settings['typography']['body_font'] ?? '' )
			);
			$this->set_first_matching_candidate_path(
				$next,
				array( 'heading_font_family', 'font_headings', 'typography_headings_font', 'typography.headings.font_family' ),
				(string) ( $settings['typography']['heading_font'] ?? '' )
			);
			$this->set_first_matching_candidate_path(
				$next,
				array( 'body_font_size', 'typography_base_size', 'font_size_base', 'typography.body.font_size' ),
				(string) absint( $settings['typography']['base_size'] ?? 16 )
			);
		}

		if ( isset( $settings['primary_color'] ) ) {
			$this->set_first_matching_candidate_path(
				$next,
				array( 'accent_color', 'primary_color', 'uncode_primary_color', 'colors.primary' ),
				(string) $settings['primary_color']
			);
		}

		if ( isset( $settings['custom_css'] ) && self::css_has_markup( (string) $settings['custom_css'] ) ) {
			return new WP_Error( 'invalid_css', __( 'CSS must not contain HTML tags.', 'wpbakery-uncode-mcp' ) );
		}
		if ( isset( $settings['custom_css'] ) ) {
			$existing = isset( $next['uncode_custom_css'] ) ? (string) $next['uncode_custom_css'] : '';
			$label    = 'brand_setup';
			$start    = "/* MCP-BLOCK:" . $label . ":START */";
			$end      = "/* MCP-BLOCK:" . $label . ":END */";
			$block    = $start . "\n" . trim( (string) $settings['custom_css'] ) . "\n" . $end;
			$pattern  = '/' . preg_quote( $start, '/' ) . '.*?' . preg_quote( $end, '/' ) . '/s';
			if ( preg_match( $pattern, $existing ) ) {
				$next['uncode_custom_css'] = (string) preg_replace( $pattern, $block, $existing, 1 );
			} else {
				$next['uncode_custom_css'] = trim( $existing . "\n\n" . $block );
			}
		}

		$diff = $this->compute_top_level_diff( $current, $next );

		return array(
			'option_name'   => $resolved,
			'preset'        => $preset,
			'added_keys'    => $diff['added'],
			'removed_keys'  => $diff['removed'],
			'changed_keys'  => $diff['changed'],
			'changed_count' => count( $diff['changed'] ),
		);
	}

	/**
	 * Export top-level options diff vs a baseline snapshot.
	 *
	 * @param string $baseline_snapshot_id Snapshot ID (optional, latest used if empty).
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function export_theme_options_diff( string $baseline_snapshot_id = '', string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		$snapshots = get_option( 'wpbakery_uncode_mcp_theme_option_snapshots', array() );
		if ( ! is_array( $snapshots ) ) {
			$snapshots = array();
		}

		$baseline = null;
		$baseline_id = sanitize_text_field( $baseline_snapshot_id );

		if ( '' !== $baseline_id ) {
			if ( ! isset( $snapshots[ $baseline_id ] ) ) {
				return new WP_Error( 'snapshot_not_found', __( 'Baseline snapshot not found.', 'wpbakery-uncode-mcp' ) );
			}
			$baseline = $snapshots[ $baseline_id ];
		} else {
			$matching = array();
			foreach ( $snapshots as $snap ) {
				if ( is_array( $snap ) && sanitize_key( (string) ( $snap['option_name'] ?? '' ) ) === $resolved ) {
					$matching[] = $snap;
				}
			}
			usort(
				$matching,
				static function ( $a, $b ) {
					return strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) );
				}
			);
			if ( ! empty( $matching ) ) {
				$baseline = $matching[0];
				$baseline_id = (string) ( $baseline['id'] ?? '' );
			}
		}

		if ( ! is_array( $baseline ) || ! is_array( $baseline['data'] ?? null ) ) {
			return new WP_Error( 'missing_baseline', __( 'No baseline snapshot available. Create a snapshot first.', 'wpbakery-uncode-mcp' ) );
		}

		$before = $baseline['data'];
		$after  = $current;
		$diff   = $this->compute_top_level_diff( $before, $after );

		return array(
			'option_name'      => $resolved,
			'baseline_id'      => $baseline_id,
			'added_keys'       => $diff['added'],
			'removed_keys'     => $diff['removed'],
			'changed_keys'     => $diff['changed'],
			'changed_count'    => count( $diff['changed'] ),
			'added_count'      => count( $diff['added'] ),
			'removed_count'    => count( $diff['removed'] ),
		);
	}

	/**
	 * Restore the latest snapshot for an option store.
	 *
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function restore_latest_theme_options_snapshot( string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$list = $this->list_theme_options_snapshots( $resolved );
		$snapshots = is_array( $list['snapshots'] ?? null ) ? $list['snapshots'] : array();
		if ( empty( $snapshots ) ) {
			return new WP_Error( 'snapshot_not_found', __( 'No snapshots found for this option store.', 'wpbakery-uncode-mcp' ) );
		}

		$latest_id = (string) ( $snapshots[0]['id'] ?? '' );
		if ( '' === $latest_id ) {
			return new WP_Error( 'snapshot_not_found', __( 'Latest snapshot is invalid.', 'wpbakery-uncode-mcp' ) );
		}

		return $this->restore_theme_options_snapshot( $latest_id, $resolved );
	}

	/**
	 * Run a full theme-ops batch in one call.
	 *
	 * Flow:
	 * 1) Optional snapshot
	 * 2) Optional mapping validation
	 * 3) Optional preset preview
	 * 4) Optional apply action (preset or brand setup)
	 * 5) Optional diff export
	 *
	 * @param array  $config Batch config.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function run_theme_ops_batch( array $config, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$out = array(
			'option_name' => $resolved,
			'steps'       => array(),
		);

		$snapshot_id = '';
		$create_snapshot = ! empty( $config['create_snapshot'] );
		if ( $create_snapshot ) {
			$snapshot = $this->create_theme_options_snapshot(
				(string) ( $config['snapshot_label'] ?? 'batch_auto' ),
				$resolved
			);
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			$snapshot_id = (string) ( $snapshot['snapshot_id'] ?? '' );
			$out['steps']['snapshot'] = $snapshot;
		}

		if ( ! empty( $config['validate_mapping'] ) ) {
			$validation = $this->validate_uncode_theme_mapping( $resolved );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			$out['steps']['validate_mapping'] = $validation;
		}

		$preset = sanitize_key( (string) ( $config['preset'] ?? '' ) );
		if ( ! empty( $config['preview_preset'] ) && '' !== $preset ) {
			$preview = $this->preview_uncode_style_preset_impact( $preset, $resolved );
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
			$out['steps']['preview_preset'] = $preview;
		}

		$applied = null;
		$apply_mode = sanitize_key( (string) ( $config['apply_mode'] ?? 'none' ) );
		try {
			if ( 'preset' === $apply_mode ) {
				if ( '' === $preset ) {
					return new WP_Error( 'missing_preset', __( 'preset is required when apply_mode is preset.', 'wpbakery-uncode-mcp' ) );
				}
				$applied = $this->apply_uncode_style_preset(
					$preset,
					false,
					$resolved
				);
				if ( is_wp_error( $applied ) ) {
					return $applied;
				}
				$out['steps']['apply'] = $applied;
			} elseif ( 'brand_setup' === $apply_mode ) {
				$brand_settings = is_array( $config['brand_settings'] ?? null ) ? $config['brand_settings'] : array();
				$brand_settings['create_snapshot'] = false;
				$applied = $this->apply_uncode_brand_setup( $brand_settings, $resolved );
				if ( is_wp_error( $applied ) ) {
					return $applied;
				}
				$out['steps']['apply'] = $applied;
			}
		} catch ( \Throwable $e ) {
			if ( ! empty( $config['rollback_on_failure'] ) && '' !== $snapshot_id ) {
				$rollback = $this->restore_theme_options_snapshot( $snapshot_id, $resolved );
				$out['steps']['rollback'] = is_wp_error( $rollback )
					? array( 'success' => false, 'error' => $rollback->get_error_message() )
					: $rollback;
			}
			return new WP_Error( 'batch_apply_failed', $e->getMessage() );
		}

		if ( ! empty( $config['export_diff'] ) ) {
			$baseline = (string) ( $config['baseline_snapshot_id'] ?? $snapshot_id );
			$diff = $this->export_theme_options_diff( $baseline, $resolved );
			if ( is_wp_error( $diff ) ) {
				return $diff;
			}
			$out['steps']['diff'] = $diff;
		}

		$out['success'] = true;
		return $out;
	}

	/**
	 * Report WPBakery Inner API availability.
	 *
	 * @return array
	 */
	public function get_wpbakery_inner_api_status(): array {
		$functions = array(
			'vc_map',
			'vc_map_update',
			'vc_get_shortcode',
			'vc_add_param',
			'vc_add_params',
			'vc_update_shortcode_param',
			'vc_remove_param',
			'vc_remove_element',
			'vc_lean_map',
			'vc_map_get_attributes',
			'vc_map_get_defaults',
			'vc_add_shortcode_param',
		);

		$status = array();
		foreach ( $functions as $fn ) {
			$status[ $fn ] = function_exists( $fn );
		}

		return array(
			'wpbakery_loaded' => defined( 'WPB_VC_VERSION' ),
			'version'         => defined( 'WPB_VC_VERSION' ) ? WPB_VC_VERSION : '',
			'functions'       => $status,
		);
	}

	/**
	 * List mapped WPBakery elements.
	 *
	 * @param int $limit Max elements.
	 * @return array
	 */
	public function list_wpbakery_elements( int $limit = 300 ): array {
		$limit = max( 1, min( 1000, $limit ) );

		$elements = array();
		if ( ! function_exists( 'vc_get_shortcode' ) ) {
			return array(
				'elements' => $elements,
				'count'    => 0,
			);
		}

		global $shortcode_tags;
		$tags = is_array( $shortcode_tags ) ? array_keys( $shortcode_tags ) : array();

		foreach ( $tags as $tag ) {
			$map = vc_get_shortcode( (string) $tag );
			if ( ! is_array( $map ) || empty( $map ) ) {
				continue;
			}

			$params = is_array( $map['params'] ?? null ) ? $map['params'] : array();
			$elements[] = array(
				'base'        => (string) ( $map['base'] ?? $tag ),
				'name'        => (string) ( $map['name'] ?? $tag ),
				'category'    => (string) ( $map['category'] ?? '' ),
				'description' => (string) ( $map['description'] ?? '' ),
				'param_count' => count( $params ),
			);

			if ( count( $elements ) >= $limit ) {
				break;
			}
		}

		return array(
			'elements' => $elements,
			'count'    => count( $elements ),
		);
	}

	/**
	 * Get one mapped WPBakery element definition.
	 *
	 * @param string $tag Shortcode tag.
	 * @return array|WP_Error
	 */
	public function get_wpbakery_element_definition( string $tag ) {
		$tag = sanitize_key( $tag );
		if ( '' === $tag ) {
			return new WP_Error( 'missing_tag', __( 'tag is required.', 'wpbakery-uncode-mcp' ) );
		}
		if ( ! function_exists( 'vc_get_shortcode' ) ) {
			return new WP_Error( 'inner_api_missing', __( 'vc_get_shortcode is not available.', 'wpbakery-uncode-mcp' ) );
		}

		$map = vc_get_shortcode( $tag );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return new WP_Error( 'element_not_found', __( 'Mapped element not found.', 'wpbakery-uncode-mcp' ) );
		}

		return array(
			'element' => $map,
		);
	}

	/**
	 * Update one param definition in mapped element.
	 *
	 * @param string $tag Shortcode tag.
	 * @param string $param_name Param name.
	 * @param array  $param_patch Key/value patch.
	 * @return array|WP_Error
	 */
	public function update_wpbakery_element_param( string $tag, string $param_name, array $param_patch ) {
		$tag        = sanitize_key( $tag );
		$param_name = sanitize_key( $param_name );
		if ( '' === $tag || '' === $param_name ) {
			return new WP_Error( 'invalid_input', __( 'tag and param_name are required.', 'wpbakery-uncode-mcp' ) );
		}
		if ( ! function_exists( 'vc_get_shortcode' ) || ! function_exists( 'vc_map_update' ) ) {
			return new WP_Error( 'inner_api_missing', __( 'vc_get_shortcode/vc_map_update is not available.', 'wpbakery-uncode-mcp' ) );
		}

		$map = vc_get_shortcode( $tag );
		if ( ! is_array( $map ) || empty( $map ) || ! is_array( $map['params'] ?? null ) ) {
			return new WP_Error( 'element_not_found', __( 'Mapped element not found.', 'wpbakery-uncode-mcp' ) );
		}

		$updated = false;
		foreach ( $map['params'] as $i => $param ) {
			$existing_name = sanitize_key( (string) ( $param['param_name'] ?? '' ) );
			if ( $existing_name !== $param_name ) {
				continue;
			}

			$safe_patch = array();
			foreach ( $param_patch as $k => $v ) {
				$key = sanitize_key( (string) $k );
				if ( '' === $key || in_array( $key, array( 'type', 'param_name' ), true ) ) {
					continue;
				}
				$safe_patch[ $key ] = is_string( $v ) ? sanitize_text_field( $v ) : $v;
			}

			$map['params'][ $i ] = array_merge( $param, $safe_patch );
			$updated = true;
			break;
		}

		if ( ! $updated ) {
			return new WP_Error( 'param_not_found', __( 'Param not found in mapped element.', 'wpbakery-uncode-mcp' ) );
		}

		vc_map_update( $tag, $map );

		return array(
			'success'    => true,
			'tag'        => $tag,
			'param_name' => $param_name,
		);
	}

	/**
	 * Persist element visibility settings and apply at runtime via hook.
	 *
	 * @param string $tag Element tag.
	 * @param bool   $enabled Whether element should be enabled.
	 * @return array
	 */
	public function set_wpbakery_element_visibility( string $tag, bool $enabled ): array {
		$tag = sanitize_key( $tag );
		$disabled = get_option( 'wpbakery_uncode_mcp_disabled_elements', array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}

		if ( $enabled ) {
			$disabled = array_values( array_filter( $disabled, static function ( $v ) use ( $tag ) {
				return sanitize_key( (string) $v ) !== $tag;
			} ) );
		} else {
			$disabled[] = $tag;
			$disabled = array_values( array_unique( array_map( 'sanitize_key', $disabled ) ) );
		}

		update_option( 'wpbakery_uncode_mcp_disabled_elements', $disabled );

		return array(
			'success'  => true,
			'tag'      => $tag,
			'enabled'  => $enabled,
			'disabled' => $disabled,
		);
	}

	/**
	 * Configure shortcode output debug filter behavior.
	 *
	 * @param bool  $enabled Enable/disable filter.
	 * @param array $tags Optional whitelist of shortcode tags.
	 * @return array
	 */
	public function set_wpbakery_output_debug( bool $enabled, array $tags = array() ): array {
		$clean_tags = array_values(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_key( (string) $v );
					},
					$tags
				)
			)
		);

		$config = array(
			'enabled' => $enabled,
			'tags'    => $clean_tags,
		);
		update_option( 'wpbakery_uncode_mcp_output_debug', $config );

		return array(
			'success' => true,
			'config'  => $config,
		);
	}

	/**
	 * High-level header layout update.
	 *
	 * @param string $layout Layout slug.
	 * @param bool   $sticky Sticky on scroll.
	 * @param bool   $transparent Transparent header.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_header_layout( string $layout, bool $sticky, bool $transparent, string $option_name = '' ) {
		$layout = sanitize_key( $layout );
		if ( '' === $layout ) {
			return new WP_Error( 'missing_layout', __( 'layout is required.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updates = array(
			array( 'candidates' => array( 'header_type', 'header_layout', 'uncode_header_layout', 'header.layout.type' ), 'value' => $layout ),
			array( 'candidates' => array( 'header_sticky', 'sticky_header', 'uncode_header_sticky', 'header.behavior.sticky' ), 'value' => $sticky ? 'on' : 'off' ),
			array( 'candidates' => array( 'header_transparent', 'menu_transparent', 'uncode_header_transparent', 'header.appearance.transparent' ), 'value' => $transparent ? 'on' : 'off' ),
		);

		$updated_paths = array();
		foreach ( $updates as $update ) {
			$path = $this->set_first_matching_candidate_path( $current, $update['candidates'], $update['value'] );
			if ( '' !== $path ) {
				$updated_paths[] = $path;
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * High-level footer layout update.
	 *
	 * @param string $layout Layout slug.
	 * @param int    $columns Footer columns (1-6).
	 * @param string $copyright Copyright text.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_footer_layout( string $layout, int $columns, string $copyright, string $option_name = '' ) {
		$layout  = sanitize_key( $layout );
		$columns = max( 1, min( 6, $columns ) );

		if ( '' === $layout ) {
			return new WP_Error( 'missing_layout', __( 'layout is required.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updates = array(
			array( 'candidates' => array( 'footer_layout', 'uncode_footer_layout', 'footer.layout.type' ), 'value' => $layout ),
			array( 'candidates' => array( 'footer_columns', 'uncode_footer_columns', 'footer.widgets.columns' ), 'value' => (string) $columns ),
		);

		if ( '' !== trim( $copyright ) ) {
			$updates[] = array(
				'candidates' => array( 'footer_copyright_text', 'uncode_copyright_text', 'footer.copyright.text' ),
				'value'      => sanitize_text_field( $copyright ),
			);
		}

		$updated_paths = array();
		foreach ( $updates as $update ) {
			$path = $this->set_first_matching_candidate_path( $current, $update['candidates'], $update['value'] );
			if ( '' !== $path ) {
				$updated_paths[] = $path;
			}
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * High-level global typography update.
	 *
	 * @param string $body_font Body font family.
	 * @param string $heading_font Heading font family.
	 * @param int    $base_size Body font size in px.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_uncode_global_typography( string $body_font, string $heading_font, int $base_size, string $option_name = '' ) {
		$body_font    = sanitize_text_field( $body_font );
		$heading_font = sanitize_text_field( $heading_font );
		$base_size    = max( 10, min( 30, $base_size ) );

		if ( '' === $body_font && '' === $heading_font ) {
			return new WP_Error( 'missing_typography', __( 'Provide body_font and/or heading_font.', 'wpbakery-uncode-mcp' ) );
		}

		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$updated_paths = array();
		if ( '' !== $body_font ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'body_font_family', 'font_body', 'typography_body_font', 'typography.body.font_family' ),
				$body_font
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}
		if ( '' !== $heading_font ) {
			$p = $this->set_first_matching_candidate_path(
				$current,
				array( 'heading_font_family', 'font_headings', 'typography_headings_font', 'typography.headings.font_family' ),
				$heading_font
			);
			if ( '' !== $p ) {
				$updated_paths[] = $p;
			}
		}

		$p = $this->set_first_matching_candidate_path(
			$current,
			array( 'body_font_size', 'typography_base_size', 'font_size_base', 'typography.body.font_size' ),
			(string) $base_size
		);
		if ( '' !== $p ) {
			$updated_paths[] = $p;
		}

		update_option( $resolved, $current );

		return array(
			'success'       => true,
			'option_name'   => $resolved,
			'updated_paths' => array_values( array_unique( $updated_paths ) ),
		);
	}

	/**
	 * Update a value across candidate keys/paths.
	 *
	 * @param array  $candidates Candidate top-level keys or dot paths.
	 * @param mixed  $value Value to set.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function update_theme_options_by_candidates( array $candidates, $value, string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		foreach ( $candidates as $candidate ) {
			$guard = $this->guard_raw_theme_write( (string) $candidate, $value );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
		}

		$updated_path = $this->set_first_matching_candidate_path( $current, $candidates, $value );
		update_option( $resolved, $current );

		return array(
			'success'      => true,
			'option_name'  => $resolved,
			'updated_keys' => '' === $updated_path ? array() : array( $updated_path ),
		);
	}

	/**
	 * Create snapshot for current theme options payload.
	 *
	 * @param string $label Optional label.
	 * @param string $option_name Option store name.
	 * @return array|WP_Error
	 */
	public function create_theme_options_snapshot( string $label = '', string $option_name = '' ) {
		$resolved = $this->resolve_uncode_option_name( $option_name );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		$current = get_option( $resolved, array() );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'invalid_theme_options', __( 'Theme options are not an array.', 'wpbakery-uncode-mcp' ) );
		}

		$snapshots = get_option( 'wpbakery_uncode_mcp_theme_option_snapshots', array() );
		if ( ! is_array( $snapshots ) ) {
			$snapshots = array();
		}

		$snapshot_id = 'snap_' . gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 6, false, false );
		$created_at  = gmdate( 'c' );

		$snapshots[ $snapshot_id ] = array(
			'id'          => $snapshot_id,
			'label'       => sanitize_text_field( $label ),
			'option_name' => $resolved,
			'created_at'  => $created_at,
			'data'        => $current,
		);

		// Keep last 20 snapshots to avoid unbounded growth.
		if ( count( $snapshots ) > 20 ) {
			uasort(
				$snapshots,
				static function ( $a, $b ) {
					return strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
				}
			);
			while ( count( $snapshots ) > 20 ) {
				array_shift( $snapshots );
			}
		}

		update_option( 'wpbakery_uncode_mcp_theme_option_snapshots', $snapshots, false );

		return array(
			'success'     => true,
			'snapshot_id' => $snapshot_id,
			'option_name' => $resolved,
			'created_at'  => $created_at,
			'label'       => sanitize_text_field( $label ),
		);
	}

	/**
	 * List available theme option snapshots.
	 *
	 * @param string $option_name Optional filter by option name.
	 * @return array
	 */
	public function list_theme_options_snapshots( string $option_name = '' ): array {
		$filter    = sanitize_key( $option_name );
		$snapshots = get_option( 'wpbakery_uncode_mcp_theme_option_snapshots', array() );
		if ( ! is_array( $snapshots ) ) {
			$snapshots = array();
		}

		$list = array();
		foreach ( $snapshots as $snapshot ) {
			if ( ! is_array( $snapshot ) ) {
				continue;
			}
			$name = sanitize_key( (string) ( $snapshot['option_name'] ?? '' ) );
			if ( '' !== $filter && $filter !== $name ) {
				continue;
			}
			$data = is_array( $snapshot['data'] ?? null ) ? $snapshot['data'] : array();
			$list[] = array(
				'id'            => (string) ( $snapshot['id'] ?? '' ),
				'label'         => (string) ( $snapshot['label'] ?? '' ),
				'option_name'   => $name,
				'created_at'    => (string) ( $snapshot['created_at'] ?? '' ),
				'keys_captured' => count( array_keys( $data ) ),
			);
		}

		usort(
			$list,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) );
			}
		);

		return array(
			'snapshots' => $list,
		);
	}

	/**
	 * Restore theme options from a saved snapshot.
	 *
	 * @param string $snapshot_id Snapshot ID.
	 * @param string $option_name Optional forced option name validation.
	 * @return array|WP_Error
	 */
	public function restore_theme_options_snapshot( string $snapshot_id, string $option_name = '' ) {
		$snapshot_id = sanitize_text_field( $snapshot_id );
		if ( '' === $snapshot_id ) {
			return new WP_Error( 'missing_snapshot_id', __( 'snapshot_id is required.', 'wpbakery-uncode-mcp' ) );
		}

		$snapshots = get_option( 'wpbakery_uncode_mcp_theme_option_snapshots', array() );
		if ( ! is_array( $snapshots ) || ! isset( $snapshots[ $snapshot_id ] ) ) {
			return new WP_Error( 'snapshot_not_found', __( 'Snapshot not found.', 'wpbakery-uncode-mcp' ) );
		}

		$snapshot = $snapshots[ $snapshot_id ];
		if ( ! is_array( $snapshot ) || ! is_array( $snapshot['data'] ?? null ) ) {
			return new WP_Error( 'invalid_snapshot', __( 'Snapshot payload is invalid.', 'wpbakery-uncode-mcp' ) );
		}

		$target_option = sanitize_key( (string) ( $snapshot['option_name'] ?? '' ) );
		$forced_option = sanitize_key( $option_name );
		if ( '' !== $forced_option && $forced_option !== $target_option ) {
			return new WP_Error( 'option_name_mismatch', __( 'Provided option_name does not match snapshot.', 'wpbakery-uncode-mcp' ) );
		}

		if ( '' === $target_option || ! in_array( $target_option, self::UNCODE_OPTION_NAMES, true ) ) {
			return new WP_Error( 'invalid_snapshot', __( 'Snapshot option name is missing or not an Uncode option.', 'wpbakery-uncode-mcp' ) );
		}

		update_option( $target_option, $snapshot['data'] );

		return array(
			'success'      => true,
			'snapshot_id'  => $snapshot_id,
			'option_name'  => $target_option,
			'restored_at'  => gmdate( 'c' ),
			'source_label' => (string) ( $snapshot['label'] ?? '' ),
		);
	}

	/**
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	private function sanitize_meta_value( $value ) {
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key          = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = $this->sanitize_meta_value( $item );
			}
			return $clean;
		}

		if ( null === $value ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Restrict writable/readable meta keys to Uncode namespace.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	private function is_allowed_uncode_meta_key( string $key ): bool {
		return 0 === strpos( $key, '_uncode_' ) || 0 === strpos( $key, 'uncode_' );
	}

	/**
	 * Avoid changing sensitive-looking keys via MCP.
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	/**
	 * Theme options that hold script or CSS are printed raw on every page, so
	 * writing them must follow the same rules as update_theme_css_js().
	 *
	 * @param string $key   Option key or dot path.
	 * @param mixed  $value Value to write.
	 * @return true|WP_Error
	 */
	private function guard_raw_theme_write( string $key, $value ) {
		if ( preg_match( '/(^|[._])(custom_)?(js|javascript|script|scripts)($|[._])|custom_js|_js$/i', $key ) && ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'forbidden_js', __( 'Saving custom JS requires the unfiltered_html capability.', 'wpbakery-uncode-mcp' ) );
		}
		if ( is_string( $value ) && self::css_has_markup( $value ) && ! current_user_can( 'unfiltered_html' ) ) {
			return new WP_Error( 'invalid_css', __( 'Theme option values must not contain style or script tags.', 'wpbakery-uncode-mcp' ) );
		}
		return true;
	}

	private function is_sensitive_option_key( string $key ): bool {
		return (bool) preg_match( '/(token|secret|password|apikey|api_key|license|purchase)/i', $key );
	}

	/**
	 * @param string $path Dot path.
	 * @return string[]
	 */
	private function path_to_segments( string $path ): array {
		$raw = array_filter( array_map( 'trim', explode( '.', $path ) ) );
		$out = array();
		foreach ( $raw as $segment ) {
			if ( is_numeric( $segment ) ) {
				$out[] = (int) $segment;
				continue;
			}
			$out[] = sanitize_key( $segment );
		}
		return array_values( array_filter( $out, static function ( $v ) {
			return '' !== $v;
		} ) );
	}

	/**
	 * @param array $data Data array.
	 * @param array $segments Path segments.
	 * @return mixed|null
	 */
	private function array_get_by_path( array $data, array $segments ) {
		$cursor = $data;
		foreach ( $segments as $segment ) {
			if ( ! is_array( $cursor ) || ! array_key_exists( $segment, $cursor ) ) {
				return null;
			}
			$cursor = $cursor[ $segment ];
		}
		return $cursor;
	}

	/**
	 * @param array $data Data array by reference.
	 * @param array $segments Path segments.
	 * @param mixed $value Value to set.
	 * @param bool  $create_missing Create missing segments.
	 * @return bool
	 */
	private function array_set_by_path( array &$data, array $segments, $value, bool $create_missing ): bool {
		$cursor = &$data;
		$last   = count( $segments ) - 1;
		foreach ( $segments as $index => $segment ) {
			if ( $index === $last ) {
				$cursor[ $segment ] = $value;
				return true;
			}

			if ( ! isset( $cursor[ $segment ] ) ) {
				if ( ! $create_missing ) {
					return false;
				}
				$cursor[ $segment ] = array();
			}

			if ( ! is_array( $cursor[ $segment ] ) ) {
				if ( ! $create_missing ) {
					return false;
				}
				$cursor[ $segment ] = array();
			}

			$cursor = &$cursor[ $segment ];
		}

		return false;
	}

	/**
	 * Set value using first existing candidate key/path, else fallback to first candidate.
	 *
	 * @param array $options Options array by ref.
	 * @param array $candidates Candidate keys/paths.
	 * @param mixed $value Value to set.
	 * @return string Updated key/path or empty string.
	 */
	private function set_first_matching_candidate_path( array &$options, array $candidates, $value ): string {
		$sanitized_value = $this->sanitize_meta_value( $value );

		foreach ( $candidates as $candidate ) {
			$path = (string) $candidate;
			if ( '' === $path ) {
				continue;
			}
			$segments = $this->path_to_segments( $path );
			if ( empty( $segments ) ) {
				continue;
			}
			$exists = null !== $this->array_get_by_path( $options, $segments );
			if ( $exists ) {
				$this->array_set_by_path( $options, $segments, $sanitized_value, false );
				return implode( '.', $segments );
			}
		}

		if ( empty( $candidates ) ) {
			return '';
		}

		$fallback = $this->path_to_segments( (string) $candidates[0] );
		if ( empty( $fallback ) ) {
			return '';
		}

		$this->array_set_by_path( $options, $fallback, $sanitized_value, true );
		return implode( '.', $fallback );
	}

	/**
	 * Get first existing value by candidate key/path.
	 *
	 * @param array $options Options array.
	 * @param array $candidates Candidate keys/paths.
	 * @return mixed|null
	 */
	private function get_first_matching_candidate_value( array $options, array $candidates ) {
		foreach ( $candidates as $candidate ) {
			$segments = $this->path_to_segments( (string) $candidate );
			if ( empty( $segments ) ) {
				continue;
			}
			$value = $this->array_get_by_path( $options, $segments );
			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * Get the first existing candidate path.
	 *
	 * @param array $options Options array.
	 * @param array $candidates Candidate keys/paths.
	 * @return string
	 */
	private function find_first_existing_candidate_path( array $options, array $candidates ): string {
		foreach ( $candidates as $candidate ) {
			$segments = $this->path_to_segments( (string) $candidate );
			if ( empty( $segments ) ) {
				continue;
			}
			$value = $this->array_get_by_path( $options, $segments );
			if ( null !== $value ) {
				return implode( '.', $segments );
			}
		}
		return '';
	}

	/**
	 * Return bundled style presets.
	 *
	 * @return array
	 */
	private function get_style_presets(): array {
		return array(
			'minimal' => array(
				'navigation_style' => 'minimal',
				'menu_layout'      => array( 'layout' => 'horizontal', 'mobile_behavior' => 'offcanvas' ),
				'typography'       => array( 'body_font' => 'Inter', 'heading_font' => 'Inter', 'base_size' => 16 ),
				'primary_color'    => '#111111',
				'custom_css'       => 'body { letter-spacing: 0; }',
			),
			'bold' => array(
				'navigation_style' => 'classic',
				'menu_layout'      => array( 'layout' => 'horizontal', 'mobile_behavior' => 'overlay' ),
				'typography'       => array( 'body_font' => 'Poppins', 'heading_font' => 'Montserrat', 'base_size' => 17 ),
				'primary_color'    => '#e53935',
				'custom_css'       => '.menu-item { text-transform: uppercase; letter-spacing: .04em; }',
			),
			'corporate' => array(
				'navigation_style' => 'classic',
				'menu_layout'      => array( 'layout' => 'horizontal', 'mobile_behavior' => 'offcanvas' ),
				'typography'       => array( 'body_font' => 'Roboto', 'heading_font' => 'Roboto', 'base_size' => 16 ),
				'primary_color'    => '#0d47a1',
				'custom_css'       => '.btn, .menu-cta { border-radius: 4px; }',
			),
			'editorial' => array(
				'navigation_style' => 'centered',
				'menu_layout'      => array( 'layout' => 'centered', 'mobile_behavior' => 'offcanvas' ),
				'typography'       => array( 'body_font' => 'Lora', 'heading_font' => 'Playfair Display', 'base_size' => 18 ),
				'primary_color'    => '#6d4c41',
				'custom_css'       => 'p { line-height: 1.8; } h1,h2,h3 { letter-spacing: .01em; }',
			),
		);
	}

	/**
	 * Compute top-level array diff.
	 *
	 * @param array $before Baseline array.
	 * @param array $after Updated array.
	 * @return array{added: array, removed: array, changed: array}
	 */
	private function compute_top_level_diff( array $before, array $after ): array {
		$added   = array();
		$removed = array();
		$changed = array();

		foreach ( $after as $key => $value ) {
			if ( ! array_key_exists( $key, $before ) ) {
				$added[] = $key;
				continue;
			}
			if ( maybe_serialize( $before[ $key ] ) !== maybe_serialize( $value ) ) {
				$changed[] = $key;
			}
		}

		foreach ( $before as $key => $value ) {
			if ( ! array_key_exists( $key, $after ) ) {
				$removed[] = $key;
			}
		}

		sort( $added );
		sort( $removed );
		sort( $changed );

		return array(
			'added'   => $added,
			'removed' => $removed,
			'changed' => $changed,
		);
	}
}
