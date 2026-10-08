<?php
/**
 * Apply menu profiles and accordion groups.
 *
 * @package UplinkAdminMenuManager
 */

namespace Uplink\AdminMenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu_Manager {
	private static array $active_profile = array();
	private static array $custom_links = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'apply_profile' ), PHP_INT_MAX - 20 );
		add_action( 'admin_menu', array( __CLASS__, 'finalize_submenu_order' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', array( __CLASS__, 'enable_custom_order' ) );
		add_filter( 'menu_order', array( __CLASS__, 'apply_order' ), PHP_INT_MAX );
		add_filter( 'admin_body_class', array( __CLASS__, 'add_admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_runtime_assets' ) );
	}

	/**
	 * Mark admin screens that use the touch-optimized menu layout.
	 *
	 * The class lets runtime styles correct third-party submenu spacing without
	 * changing WordPress's compact menu layout.
	 */
	public static function add_admin_body_class( string $classes ): string {
		if ( ! Settings::user_uses_managed_menu() ) {
			return $classes;
		}

		$settings   = Settings::get_all();
		$appearance = is_array( $settings['appearance'] ?? null ) ? $settings['appearance'] : array();
		$profile    = Settings::get_active_profile();

		if ( ! empty( $appearance['touch_optimized_menu'] ) ) {
			$classes .= ' uamm-touch-optimized';
		}
		if ( ! empty( $appearance['submenu_indicators'] ) ) {
			$classes .= ' uamm-submenu-indicators';
		}

		if ( ! empty( $profile['groups'] ) ) {
			$classes .= ' uamm-menu-pending';
		}

		return trim( $classes );
	}

	/**
	 * Apply all PHP-side profile changes after plugins have registered menus.
	 */
	public static function apply_profile(): void {
		global $menu, $submenu;

		if ( ! is_array( $menu ) || ! Settings::user_uses_managed_menu() ) {
			self::$active_profile = array();
			return;
		}

		self::$active_profile = Settings::get_active_profile();
		$profile              = self::$active_profile;

		self::add_custom_items( $profile, $menu, $submenu );
		self::move_submenu_items( $profile, $submenu );

		$visible_count = 0;
		foreach ( $menu as $index => &$item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( '' === $slug ) {
				continue;
			}

			$config = is_array( $profile['items'][ $slug ] ?? null ) ? $profile['items'][ $slug ] : array();
			if ( self::is_safety_item( $slug ) ) {
				$config['hidden']    = false;
				$config['url']       = '';
				$config['open_mode'] = 'same';
			}
			if ( ! empty( $config['hidden'] ) && ! self::is_safety_item( $slug ) ) {
				unset( $menu[ $index ] );
				continue;
			}

			self::apply_item_config( $item, $slug, $config );
			if ( ! empty( $config['url'] ) || in_array( (string) ( $config['open_mode'] ?? 'same' ), array( 'new', 'none' ), true ) ) {
				self::$custom_links[ $slug ] = array(
					'url'  => (string) ( $config['url'] ?? '' ),
					'mode' => (string) ( $config['open_mode'] ?? 'same' ),
				);
			}
			if ( ! str_starts_with( $slug, 'separator' ) ) {
				++$visible_count;
			}
		}
		unset( $item );

		if ( 0 === $visible_count ) {
			$menu[2] = array( __( 'Dashboard', 'uplink-admin-menu-manager' ), 'read', 'index.php', '', 'menu-top menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard' );
		}

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => &$children ) {
				if ( ! is_array( $children ) ) {
					continue;
				}
				foreach ( $children as $index => &$child ) {
					$slug   = isset( $child[2] ) ? (string) $child[2] : '';
					$key    = $parent . '::' . $slug;
					$config = is_array( $profile['items'][ $key ] ?? null ) ? $profile['items'][ $key ] : array();
					if ( ! empty( $config['hidden'] ) ) {
						unset( $children[ $index ] );
						continue;
					}
					if ( ! empty( $config['title'] ) ) {
						$child[0] = esc_html( $config['title'] );
					}
				}
				unset( $child );
				$children = array_values( $children );
			}
			unset( $children );
		}
	}

	private static function apply_item_config( array &$item, string $slug, array $config ): void {
		if ( ! empty( $config['title'] ) ) {
			$item[0] = esc_html( $config['title'] );
		}

		if ( ! empty( $config['icon'] ) ) {
			$item[6] = $config['icon'];
		}

		$classes = isset( $item[4] ) ? (string) $item[4] : '';
		$classes .= ' uamm-item ' . self::item_class( $slug );
		if ( ! empty( $config['css_class'] ) ) {
			$classes .= ' ' . $config['css_class'];
		}
		$item[4] = trim( $classes );
	}

	private static function item_class( string $slug ): string {
		return 'uamm-item-' . substr( md5( $slug ), 0, 12 );
	}

	private static function add_custom_items( array $profile, array &$menu, &$submenu ): void {
		foreach ( (array) $profile['custom_items'] as $custom ) {
			$id     = sanitize_key( (string) ( $custom['id'] ?? '' ) );
			$title  = (string) ( $custom['title'] ?? '' );
			$slug   = 'uamm-custom-' . $id;
			$parent = (string) ( $custom['parent'] ?? '' );
			$mode   = (string) ( $custom['open_mode'] ?? 'same' );
			$url    = (string) ( $custom['url'] ?? '' );

			if ( '' === $id || '' === $title || ! self::current_user_matches_custom_roles( $custom ) ) {
				continue;
			}

			if ( 'iframe' === $mode ) {
				add_menu_page( $title, $title, 'read', $slug, static function () use ( $title, $url ): void {
					self::render_iframe_page( $title, $url );
				}, (string) ( $custom['icon'] ?? 'dashicons-admin-generic' ) );
			} elseif ( '' !== $parent ) {
				$submenu[ $parent ]   = is_array( $submenu[ $parent ] ?? null ) ? $submenu[ $parent ] : array();
				$submenu[ $parent ][] = array( esc_html( $title ), 'read', $slug );
				self::$custom_links[ $slug ] = array( 'url' => $url, 'mode' => $mode );
			} else {
				$menu[] = array( esc_html( $title ), 'read', $slug, '', 'menu-top uamm-custom-item', 'uamm-custom-' . $id, (string) ( $custom['icon'] ?? 'dashicons-admin-generic' ) );
				self::$custom_links[ $slug ] = array( 'url' => $url, 'mode' => $mode );
			}
		}
	}

	/**
	 * Determine whether the current user's roles may see a custom item.
	 */
	private static function current_user_matches_custom_roles( array $custom ): bool {
		$roles = (array) ( $custom['roles'] ?? array( 'administrator' ) );
		if ( in_array( '*', $roles, true ) ) {
			return true;
		}

		$allowed = array_values( array_filter( array_map( 'sanitize_key', $roles ) ) );
		if ( ! $allowed ) {
			$allowed = array( 'administrator' );
		}

		return (bool) array_intersect( wp_get_current_user()->roles, $allowed );
	}

	private static function move_submenu_items( array $profile, &$submenu ): void {
		if ( ! is_array( $submenu ) ) {
			return;
		}

		$pool = array();
		foreach ( $submenu as $parent => $children ) {
			foreach ( (array) $children as $child ) {
				if ( isset( $child[2] ) ) {
					$pool[ $parent . '::' . $child[2] ] = $child;
				}
			}
		}

		foreach ( (array) $profile['items'] as $key => $config ) {
			if ( ! str_contains( (string) $key, '::' ) || ! is_array( $config ) || empty( $config['parent'] ) || ! isset( $pool[ $key ] ) ) {
				continue;
			}
			$source = explode( '::', (string) $key, 2 )[0];
			if ( isset( $submenu[ $source ] ) ) {
				$submenu[ $source ] = array_values( array_filter( (array) $submenu[ $source ], static fn( $child ) => (string) ( $child[2] ?? '' ) !== (string) $pool[ $key ][2] ) );
			}
			$target               = (string) $config['parent'];
			$submenu[ $target ]   = is_array( $submenu[ $target ] ?? null ) ? $submenu[ $target ] : array();
			$submenu[ $target ][] = $pool[ $key ];
		}


		self::order_submenu_items( $profile, $submenu );
	}

	/**
	 * Reapply submenu ordering after every plugin has finished changing menus.
	 */
	public static function finalize_submenu_order(): void {
		global $submenu;

		if ( ! is_array( $submenu ) || ! Settings::user_uses_managed_menu() ) {
			return;
		}

		$profile = self::$active_profile ?: Settings::get_active_profile();
		self::order_submenu_items( $profile, $submenu );
	}

	/**
	 * Apply explicit submenu orders while preserving new, unsaved child links.
	 */
	private static function order_submenu_items( array $profile, array &$submenu ): void {
		foreach ( (array) $profile['submenus'] as $parent => $ordered_keys ) {
			$ordered_slugs = array();
			foreach ( (array) $ordered_keys as $key ) {
				$lookup = str_contains( (string) $key, '::' ) ? (string) $key : $parent . '::' . (string) $key;
				if ( ! empty( $profile['items'][ $lookup ]['parent'] ) && (string) $profile['items'][ $lookup ]['parent'] !== (string) $parent ) {
					continue;
				}
				$parts = explode( '::', $lookup, 2 );
				if ( ! empty( $parts[1] ) && ! in_array( $parts[1], $ordered_slugs, true ) ) {
					$ordered_slugs[] = $parts[1];
				}
			}
			if ( ! $ordered_slugs ) {
				continue;
			}

			$actual_parent = self::resolve_submenu_parent( (string) $parent, $ordered_slugs, $submenu );
			if ( '' === $actual_parent ) {
				continue;
			}

			$current   = (array) $submenu[ $actual_parent ];
			$by_slug   = array();
			foreach ( $current as $child ) {
				$slug = (string) ( $child[2] ?? '' );
				if ( '' !== $slug ) {
					$by_slug[ $slug ] = $child;
				}
			}
			$available = array();
			foreach ( $ordered_slugs as $slug ) {
				if ( isset( $by_slug[ $slug ] ) ) {
					$available[] = $by_slug[ $slug ];
				}
			}
			foreach ( $current as $index => $child ) {
				if ( in_array( (string) ( $child[2] ?? '' ), $ordered_slugs, true ) && $available ) {
					$current[ $index ] = array_shift( $available );
				}
			}
			$submenu[ $actual_parent ] = $current;
		}
	}

	/**
	 * Find a submenu whose parent slug was replaced by WordPress or a plugin.
	 */
	private static function resolve_submenu_parent( string $parent, array $ordered_slugs, array $submenu ): string {
		if ( isset( $submenu[ $parent ] ) ) {
			$parent_slugs = array_map( static fn( $child ) => (string) ( $child[2] ?? '' ), (array) $submenu[ $parent ] );
			if ( array_intersect( $ordered_slugs, $parent_slugs ) ) {
				return $parent;
			}
		}

		$best_parent = '';
		$best_score  = 0;
		foreach ( $submenu as $candidate => $children ) {
			$child_slugs = array_map( static fn( $child ) => (string) ( $child[2] ?? '' ), (array) $children );
			$score       = count( array_intersect( $ordered_slugs, $child_slugs ) );
			if ( $score > $best_score ) {
				$best_parent = (string) $candidate;
				$best_score  = $score;
			}
		}

		return $best_score > 0 ? $best_parent : '';
	}

	public static function enable_custom_order( bool $enabled ): bool {
		if ( ! Settings::user_uses_managed_menu() ) {
			return $enabled;
		}

		$profile = self::$active_profile ?: Settings::get_active_profile();

		return ! empty( $profile['order'] ) || $enabled;
	}

	public static function apply_order( array $order ): array {
		if ( ! Settings::user_uses_managed_menu() ) {
			return $order;
		}

		$profile = self::$active_profile ?: Settings::get_active_profile();
		$saved   = array_values( array_intersect( (array) $profile['order'], $order ) );

		return array_merge( $saved, array_values( array_diff( $order, $saved ) ) );
	}

	public static function enqueue_runtime_assets(): void {
		if ( ! Settings::user_uses_managed_menu() ) {
			return;
		}

		$profile      = self::$active_profile ?: Settings::get_active_profile();
		$settings     = Settings::get_all();
		$appearance   = is_array( $settings['appearance'] ) ? $settings['appearance'] : array();
		$menu_width          = max( 140, min( 320, absint( $appearance['menu_width'] ?? 275 ) ) );
		$submenu_edge_distance = min( 24, absint( $appearance['submenu_edge_distance'] ?? 0 ) );
		$touch_optimized_menu = ! empty( $appearance['touch_optimized_menu'] );
		$divider_opacity = min( 100, absint( $appearance['divider_opacity'] ?? 13 ) );
		$group_margin_inline = min( 32, absint( $appearance['group_margin_inline'] ?? 5 ) );
		$group_margin_block  = min( 32, absint( $appearance['group_margin_block'] ?? 4 ) );
		$group_border_radius = min( 32, absint( $appearance['group_border_radius'] ?? 5 ) );
		$group_heading_font_size = max( 9, min( 24, absint( $appearance['group_heading_font_size'] ?? 11 ) ) );
		$group_heading_font_weight = absint( $appearance['group_heading_font_weight'] ?? 700 );
		if ( ! in_array( $group_heading_font_weight, array( 400, 500, 600, 700, 800, 900 ), true ) ) {
			$group_heading_font_weight = 700;
		}
		$group_heading_letter_spacing = max( 0, min( 4, (float) ( $appearance['group_heading_letter_spacing'] ?? 0.4 ) ) );
		$group_heading_text_transform = sanitize_key( (string) ( $appearance['group_heading_text_transform'] ?? 'uppercase' ) );
		if ( ! in_array( $group_heading_text_transform, array( 'none', 'uppercase', 'capitalize' ), true ) ) {
			$group_heading_text_transform = 'uppercase';
		}
		$item_classes = array();
		$known_slugs  = array_merge( (array) $profile['order'], array_keys( (array) $profile['items'] ) );
		foreach ( (array) $profile['groups'] as $group ) {
			$known_slugs = array_merge( $known_slugs, (array) ( $group['items'] ?? array() ) );
		}
		foreach ( array_unique( array_map( 'strval', $known_slugs ) ) as $slug ) {
			if ( '' !== $slug && ! str_contains( $slug, '::' ) ) {
				$item_classes[ $slug ] = self::item_class( $slug );
			}
		}

		wp_enqueue_style( 'uamm-runtime', UAMM_URL . 'assets/runtime.css', array( 'dashicons' ), Plugin::asset_version( 'assets/runtime.css' ) );
		wp_enqueue_script( 'uamm-runtime', UAMM_URL . 'assets/runtime.js', array(), Plugin::asset_version( 'assets/runtime.js' ), false );
		wp_script_add_data( 'uamm-runtime', 'strategy', 'defer' );
		wp_add_inline_script(
			'uamm-runtime',
			'window.uammRuntime = ' . wp_json_encode(
				array(
					'groups'        => array_values( (array) $profile['groups'] ),
					'singleOpen'    => ! empty( $profile['single_open'] ),
					'userId'        => get_current_user_id(),
					'groupDefaults' => (array) $settings['group_style'],
					'customLinks'   => self::$custom_links,
					'itemClasses'   => $item_classes,
					'submenus'      => (array) $profile['submenus'],
					'i18n'          => array(
						/* translators: %s: accordion group label. */
						'expand'   => __( 'Expand %s', 'uplink-admin-menu-manager' ),
						/* translators: %s: accordion group label. */
						'collapse' => __( 'Collapse %s', 'uplink-admin-menu-manager' ),
						/* translators: %s: accordion group label. */
						'pinned'   => __( '%s is pinned open', 'uplink-admin-menu-manager' ),
						/* translators: %s: accordion group label. */
						'pin'      => __( 'Pin %s open', 'uplink-admin-menu-manager' ),
						/* translators: %s: accordion group label. */
						'unpin'    => __( 'Unpin %s', 'uplink-admin-menu-manager' ),
					),
				)
			) . ';',
			'before'
		);

		$rules = array();
		foreach ( array( 'background', 'text', 'icon', 'highlight', 'divider_color', 'submenu_background', 'submenu_text', 'submenu_hover_background', 'submenu_hover_text' ) as $key ) {
			if ( ! empty( $appearance[ $key ] ) ) {
				$rules[ $key ] = $appearance[ $key ];
			}
		}
		$css = ':root{--uamm-admin-menu-width:' . $menu_width . 'px;--uamm-submenu-edge-distance:' . $submenu_edge_distance . 'px;--uamm-menu-divider-opacity:' . $divider_opacity . '%;--uamm-menu-item-min-height:' . ( $touch_optimized_menu ? '44px' : '34px' ) . ';--uamm-menu-item-padding-block:' . ( $touch_optimized_menu ? '13px' : '8px' ) . ';--uamm-menu-icon-padding-top:' . ( $touch_optimized_menu ? '12px' : '7px' ) . ';--uamm-submenu-item-padding-block:' . ( $touch_optimized_menu ? '12px' : '5px' ) . ';--uamm-submenu-item-line-height:' . ( $touch_optimized_menu ? '20px' : '24px' ) . ';--uamm-group-pin-size:' . ( $touch_optimized_menu ? '34px' : '26px' ) . ';--uamm-group-pin-top:' . ( $touch_optimized_menu ? '5px' : '4px' ) . ';--uamm-group-label-pin-space:' . ( $touch_optimized_menu ? '36px' : '28px' ) . ';--uamm-group-margin-inline:' . $group_margin_inline . 'px;--uamm-group-margin-block:' . $group_margin_block . 'px;--uamm-group-border-radius:' . $group_border_radius . 'px;--uamm-group-heading-font-size:' . $group_heading_font_size . 'px;--uamm-group-heading-font-weight:' . $group_heading_font_weight . ';--uamm-group-heading-letter-spacing:' . $group_heading_letter_spacing . 'px;--uamm-group-heading-text-transform:' . $group_heading_text_transform . ';' . ( isset( $rules['background'] ) ? '--uamm-bg:' . $rules['background'] . ';' : '' ) . ( isset( $rules['text'] ) ? '--uamm-text:' . $rules['text'] . ';' : '' ) . ( isset( $rules['icon'] ) ? '--uamm-icon:' . $rules['icon'] . ';' : '' ) . ( isset( $rules['highlight'] ) ? '--uamm-highlight:' . $rules['highlight'] . ';' : '' ) . ( isset( $rules['divider_color'] ) ? '--uamm-menu-divider:' . $rules['divider_color'] . ';' : '' ) . ( isset( $rules['submenu_background'] ) ? '--uamm-submenu-bg:' . $rules['submenu_background'] . ';' : '' ) . ( isset( $rules['submenu_text'] ) ? '--uamm-submenu-text:' . $rules['submenu_text'] . ';' : '' ) . ( isset( $rules['submenu_hover_background'] ) ? '--uamm-submenu-hover-bg:' . $rules['submenu_hover_background'] . ';' : '' ) . ( isset( $rules['submenu_hover_text'] ) ? '--uamm-submenu-hover-text:' . $rules['submenu_hover_text'] . ';' : '' ) . '}';
		if ( class_exists( '\\Wicked_Folders' ) ) {
			$css .= ':root{--sidebar-width-min:' . $menu_width . 'px;}@media screen and (min-width:961px){body:not(.folded):not(.rtl) #wicked-folder-pane .wicked-navigating-mask{left:var(--uamm-admin-menu-width,160px);}body:not(.folded).rtl #wicked-folder-pane .wicked-navigating-mask{right:var(--uamm-admin-menu-width,160px);left:0;}}';
		}
		wp_add_inline_style( 'uamm-runtime', $css );

		$item_css = '';
		foreach ( (array) $profile['items'] as $slug => $item ) {
			if ( ! is_array( $item ) || str_contains( (string) $slug, '::' ) ) {
				continue;
			}
			$background = sanitize_hex_color( (string) ( $item['background'] ?? '' ) );
			$text       = sanitize_hex_color( (string) ( $item['text_color'] ?? '' ) );
			$selector   = '#adminmenu li.' . self::item_class( (string) $slug ) . '>a.menu-top';
			$submenu_background = sanitize_hex_color( (string) ( $item['submenu_background'] ?? '' ) );
			$submenu_text = sanitize_hex_color( (string) ( $item['submenu_text_color'] ?? '' ) );
			$submenu_hover_background = sanitize_hex_color( (string) ( $item['submenu_hover_background'] ?? '' ) );
			$submenu_hover_text = sanitize_hex_color( (string) ( $item['submenu_hover_text_color'] ?? '' ) );
			$submenu_selector = '#adminmenu li.' . self::item_class( (string) $slug ) . '>.wp-submenu';
			if ( $background ) {
				$item_css .= $selector . '{background:' . $background . ';}';
			}
			if ( $text ) {
				$item_css .= $selector . ',' . $selector . ' .wp-menu-image:before{color:' . $text . ';}';
			}
			if ( $submenu_background ) {
				$item_css .= $submenu_selector . '{background:' . $submenu_background . '!important;}';
			}
			if ( $submenu_text ) {
				$item_css .= $submenu_selector . ' a{color:' . $submenu_text . '!important;}';
			}
			if ( $submenu_hover_background ) {
				$item_css .= $submenu_selector . ' li.current a,' . $submenu_selector . ' a:hover,' . $submenu_selector . ' a:focus{background:' . $submenu_hover_background . '!important;}';
			}
			if ( $submenu_hover_text ) {
				$item_css .= $submenu_selector . ' li.current a,' . $submenu_selector . ' a:hover,' . $submenu_selector . ' a:focus{color:' . $submenu_hover_text . '!important;}';
			}
		}
		if ( '' !== $item_css ) {
			wp_add_inline_style( 'uamm-runtime', $item_css );
		}
	}

	private static function is_safety_item( string $slug ): bool {
		return in_array( $slug, array( 'index.php', 'uplink-admin-menu-manager' ), true );
	}

	private static function render_iframe_page( string $title, string $url ): void {
		if ( ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'uplink-admin-menu-manager' ), '', array( 'response' => 403 ) );
		}
		?>
		<div class="wrap uamm-frame-wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<iframe class="uamm-frame" src="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $title ); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
		</div>
		<?php
	}
}
