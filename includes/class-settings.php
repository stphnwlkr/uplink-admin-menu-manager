<?php
/**
 * Settings storage and validation.
 *
 * @package UplinkAdminMenuManager
 */

namespace Uplink\AdminMenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	public const OPTION = 'uamm_settings';
	public const SCHEMA_VERSION = 1;
	public const ACCESS_CAPABILITY = 'uamm_customize_own_menu';

	/**
	 * Return the complete normalized settings array.
	 */
	public static function get_all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		unset( $stored['toolbar'], $stored['plugin_rules'] );

		$settings = wp_parse_args(
			$stored,
			array(
				'schema_version'   => self::SCHEMA_VERSION,
				'profiles'         => array(),
				'protected_users'  => array(),
				'menu_access_roles' => array(),
				'menu_role_modes'  => array(),
				'appearance'       => self::appearance_defaults(),
				'group_style'      => self::group_style_defaults(),
			)
		);
		$settings['group_style'] = wp_parse_args( is_array( $settings['group_style'] ) ? $settings['group_style'] : array(), self::group_style_defaults() );
		$settings['appearance']  = wp_parse_args( is_array( $settings['appearance'] ) ? $settings['appearance'] : array(), self::appearance_defaults() );
		$settings['menu_access_roles'] = self::sanitize_menu_access_roles( $settings['menu_access_roles'] );
		$settings['menu_role_modes'] = self::sanitize_menu_role_modes( $settings['menu_role_modes'] );

		return $settings;
	}

	/**
	 * Whether a user has full administrative access to Menu Manager.
	 */
	public static function user_has_full_access( ?\WP_User $user = null ): bool {
		$user = $user ?: wp_get_current_user();

		return $user instanceof \WP_User && $user->exists() && $user->has_cap( 'manage_options' );
	}

	/**
	 * Whether a user may edit only their own menu profile.
	 */
	public static function user_can_customize_own_menu( ?\WP_User $user = null ): bool {
		$user = $user ?: wp_get_current_user();
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return false;
		}

		$allowed_roles = (array) self::get_all()['menu_access_roles'];

		return (bool) array_intersect( array_map( 'sanitize_key', (array) $user->roles ), $allowed_roles );
	}

	/**
	 * Whether a user can open Menu Manager by either access path.
	 */
	public static function user_can_access_manager( ?\WP_User $user = null ): bool {
		return self::user_has_full_access( $user ) || self::user_can_customize_own_menu( $user );
	}

	/**
	 * Whether Menu Manager should alter the admin menu for a user.
	 */
	public static function user_uses_managed_menu( ?\WP_User $user = null ): bool {
		$user = $user ?: wp_get_current_user();
		if ( ! $user instanceof \WP_User || ! $user->exists() ) {
			return false;
		}
		if ( self::user_can_access_manager( $user ) ) {
			return true;
		}

		$roles = array_map( 'sanitize_key', (array) $user->roles );
		if ( ! $roles ) {
			return true;
		}
		$modes = (array) self::get_all()['menu_role_modes'];
		foreach ( $roles as $role ) {
			if ( 'wordpress' !== ( $modes[ $role ] ?? 'managed' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a set of roles supplies either full or personal-profile access.
	 */
	public static function roles_can_access_manager( array $roles, ?array $access_roles = null ): bool {
		$registered   = wp_roles()->roles;
		$access_roles = null === $access_roles ? (array) self::get_all()['menu_access_roles'] : self::sanitize_menu_access_roles( $access_roles );

		foreach ( array_map( 'sanitize_key', $roles ) as $role ) {
			if ( ! empty( $registered[ $role ]['capabilities']['manage_options'] ) || in_array( $role, $access_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a personal profile when a role change removes Menu Manager access.
	 */
	public static function clear_profile_after_role_change( int $user_id, array $previous_roles ): bool {
		$user_id = absint( $user_id );
		$user    = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User || ! self::roles_can_access_manager( $previous_roles ) || self::user_can_access_manager( $user ) ) {
			return false;
		}

		$settings = self::get_all();
		if ( in_array( $user_id, (array) $settings['protected_users'], true ) ) {
			return false;
		}

		$key = 'user:' . $user_id;
		if ( ! isset( $settings['profiles'][ $key ] ) ) {
			return false;
		}

		unset( $settings['profiles'][ $key ] );
		update_option( self::OPTION, $settings, false );

		return true;
	}

	/**
	 * Return the default appearance settings.
	 */
	private static function appearance_defaults(): array {
		return array(
			'background'               => '#1e1e1e',
			'text'                     => '#ffffff',
			'icon'                     => '#f3f1f1',
			'highlight'                => '#0056d6',
			'divider_color'            => '#ffffff',
			'divider_opacity'          => 13,
			'submenu_indicators'       => true,
			'submenu_background'       => '#333333',
			'submenu_text'             => '#f0f0f1',
			'submenu_hover_background' => '#2c3338',
			'submenu_hover_text'       => '#ffffff',
			'menu_width'               => 275,
			'submenu_edge_distance'     => 0,
			'touch_optimized_menu'      => true,
			'group_margin_inline'       => 5,
			'group_margin_block'        => 4,
			'group_border_radius'       => 5,
			'group_heading_font_size'      => 11,
			'group_heading_font_weight'    => 700,
			'group_heading_letter_spacing' => 0.4,
			'group_heading_text_transform' => 'uppercase',
		);
	}

	private static function group_style_defaults(): array {
		return array(
			'background'             => '#053a8a',
			'text_color'             => '#ffffff',
			'border_color'           => '#ffffff',
			'hover_background'       => '#014cbc',
			'hover_text_color'       => '#ffffff',
			'collapsed_background'   => '#0056d6',
			'collapsed_text_color'   => '#f0f0f1',
			'collapsed_hover_background' => '#2c3338',
			'collapsed_hover_text_color' => '#ffffff',
			'panel_background'       => '#474747',
			'panel_text_color'       => '#f0f0f1',
			'panel_border_color'     => '#72aee6',
			'panel_hover_background' => '#2c3338',
			'panel_hover_text_color' => '#ffffff',
		);
	}

	/**
	 * Return the profile that applies to the current user.
	 */
	public static function get_active_profile( ?\WP_User $user = null ): array {
		$user     = $user ?: wp_get_current_user();
		$settings = self::get_all();
		$profiles = is_array( $settings['profiles'] ) ? $settings['profiles'] : array();
		$keys     = array();

		if ( $user && $user->exists() ) {
			$keys[] = 'user:' . $user->ID;
			foreach ( (array) $user->roles as $role ) {
				$keys[] = 'role:' . sanitize_key( $role );
			}
		}
		$keys[] = 'default';

		foreach ( $keys as $key ) {
			if ( isset( $profiles[ $key ] ) && is_array( $profiles[ $key ] ) ) {
				return self::normalize_profile( $profiles[ $key ] );
			}
		}

		return self::normalize_profile( array() );
	}

	/**
	 * Return one profile for editing without falling back.
	 */
	public static function get_profile( string $key ): array {
		$settings = self::get_all();
		$profiles = is_array( $settings['profiles'] ) ? $settings['profiles'] : array();

		return self::normalize_profile( $profiles[ $key ] ?? array() );
	}

	/**
	 * Whether another administrator has protected a user's saved menu profile.
	 */
	public static function is_profile_write_protected( string $key, ?int $actor_id = null ): bool {
		if ( ! str_starts_with( $key, 'user:' ) ) {
			return false;
		}

		$target_id = absint( substr( $key, 5 ) );
		$actor_id  = null === $actor_id ? get_current_user_id() : absint( $actor_id );
		if ( 0 === $target_id || $target_id === $actor_id ) {
			return false;
		}

		return in_array( $target_id, (array) self::get_all()['protected_users'], true );
	}

	/**
	 * Save one profile.
	 */
	public static function save_profile( string $key, array $profile ): bool {
		$own_profile = 'user:' . get_current_user_id();
		if ( ! self::is_valid_profile_key( $key ) || self::is_profile_write_protected( $key ) || ( ! self::user_has_full_access() && ( ! self::user_can_customize_own_menu() || $own_profile !== $key ) ) ) {
			return false;
		}

		$settings                    = self::get_all();
		$settings['profiles'][ $key ] = self::sanitize_profile( $profile );
		update_option( self::OPTION, $settings, false );

		return true;
	}

	/**
	 * Delete one profile so it inherits the default profile.
	 */
	public static function delete_profile( string $key ): bool {
		$own_profile = 'user:' . get_current_user_id();
		if ( ! self::is_valid_profile_key( $key ) || self::is_profile_write_protected( $key ) || ( ! self::user_has_full_access() && ( ! self::user_can_customize_own_menu() || $own_profile !== $key ) ) ) {
			return false;
		}

		$settings = self::get_all();
		unset( $settings['profiles'][ $key ] );
		update_option( self::OPTION, $settings, false );

		return true;
	}

	/**
	 * Save non-profile settings.
	 */
	public static function save_site_settings( array $input ): void {
		$settings = self::get_all();
		$old_access_roles = (array) $settings['menu_access_roles'];

		$settings['appearance']      = self::sanitize_appearance( $input['appearance'] ?? array() );
		$settings['group_style']     = self::sanitize_group_style( $input['group_style'] ?? array() );
		$settings['menu_access_roles'] = self::sanitize_menu_access_roles( $input['menu_access_roles'] ?? array() );
		$settings['menu_role_modes'] = self::sanitize_menu_role_modes( $input['menu_role_modes'] ?? array() );
		$settings['protected_users'] = self::sanitize_user_ids( $input['protected_users'] ?? array() );

		if ( $old_access_roles !== $settings['menu_access_roles'] ) {
			foreach ( array_keys( (array) $settings['profiles'] ) as $profile_key ) {
				if ( ! str_starts_with( (string) $profile_key, 'user:' ) ) {
					continue;
				}
				$user_id = absint( substr( (string) $profile_key, 5 ) );
				$user    = get_user_by( 'id', $user_id );
				if ( ! $user instanceof \WP_User || in_array( $user_id, $settings['protected_users'], true ) ) {
					continue;
				}
				$had_access = self::user_has_full_access( $user ) || self::roles_can_access_manager( (array) $user->roles, $old_access_roles );
				$has_access = self::user_has_full_access( $user ) || self::roles_can_access_manager( (array) $user->roles, $settings['menu_access_roles'] );
				if ( $had_access && ! $has_access ) {
					unset( $settings['profiles'][ $profile_key ] );
				}
			}
		}

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Restore presentation settings without changing menus or protected users.
	 */
	public static function reset_appearance(): void {
		$settings                = self::get_all();
		$settings['appearance']  = self::appearance_defaults();
		$settings['group_style'] = self::group_style_defaults();
		$item_color_keys         = array( 'background', 'text_color', 'submenu_background', 'submenu_text_color', 'submenu_hover_background', 'submenu_hover_text_color' );
		$group_color_keys        = array( 'background', 'text_color', 'border_color', 'hover_background', 'hover_text_color', 'collapsed_background', 'collapsed_text_color', 'collapsed_hover_background', 'collapsed_hover_text_color', 'panel_background', 'panel_text_color', 'panel_border_color', 'panel_hover_background', 'panel_hover_text_color' );

		foreach ( $settings['profiles'] as &$profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}
			foreach ( array( 'items', 'custom_items' ) as $collection ) {
				if ( empty( $profile[ $collection ] ) || ! is_array( $profile[ $collection ] ) ) {
					continue;
				}
				foreach ( $profile[ $collection ] as &$item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					foreach ( $item_color_keys as $key ) {
						$item[ $key ] = '';
					}
				}
				unset( $item );
			}
			if ( empty( $profile['groups'] ) || ! is_array( $profile['groups'] ) ) {
				continue;
			}
			foreach ( $profile['groups'] as &$group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				foreach ( $group_color_keys as $key ) {
					$group[ $key ] = '';
				}
				$group['show_heading_border'] = false;
				$group['show_panel_border']   = false;
			}
			unset( $group );
		}
		unset( $profile );

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Replace all settings after validating imported data.
	 */
	public static function import( array $input ): void {
		$clean = array(
			'schema_version'  => self::SCHEMA_VERSION,
			'profiles'        => array(),
			'menu_access_roles' => self::sanitize_menu_access_roles( $input['menu_access_roles'] ?? array() ),
			'menu_role_modes' => self::sanitize_menu_role_modes( $input['menu_role_modes'] ?? array() ),
			'protected_users' => self::sanitize_user_ids( $input['protected_users'] ?? array() ),
			'appearance'      => self::sanitize_appearance( $input['appearance'] ?? array() ),
			'group_style'     => self::sanitize_group_style( $input['group_style'] ?? array() ),
		);

		foreach ( (array) ( $input['profiles'] ?? array() ) as $key => $profile ) {
			$key = sanitize_text_field( (string) $key );
			if ( self::is_valid_profile_key( $key ) && is_array( $profile ) ) {
				$clean['profiles'][ $key ] = self::sanitize_profile( $profile );
			}
		}
		update_option( self::OPTION, $clean, false );
	}

	/**
	 * Return only presentation settings, without menu structure or protected users.
	 */
	public static function get_look_and_feel_export(): array {
		$settings = self::get_all();
		$profiles = array();

		foreach ( (array) $settings['profiles'] as $key => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$profile_styles = array(
				'items'        => array(),
				'groups'       => array(),
				'custom_items' => array(),
			);

			foreach ( (array) ( $profile['items'] ?? array() ) as $slug => $item ) {
				if ( is_array( $item ) ) {
					$profile_styles['items'][ $slug ] = self::item_style( $item );
				}
			}
			foreach ( (array) ( $profile['groups'] ?? array() ) as $group ) {
				if ( is_array( $group ) && ! empty( $group['id'] ) ) {
					$profile_styles['groups'][ (string) $group['id'] ] = self::group_presentation( $group );
				}
			}
			foreach ( (array) ( $profile['custom_items'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$profile_styles['custom_items'][ (string) $item['id'] ] = self::item_style( $item );
				}
			}

			$profiles[ $key ] = $profile_styles;
		}

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'export_type'    => 'look_and_feel',
			'appearance'     => $settings['appearance'],
			'group_style'    => $settings['group_style'],
			'profile_styles' => $profiles,
		);
	}

	/**
	 * Merge presentation settings while preserving menu structure and protected users.
	 */
	public static function import_look_and_feel( array $input ): void {
		$settings                  = self::get_all();
		$settings['appearance']    = self::sanitize_appearance( $input['appearance'] ?? array() );
		$settings['group_style']   = self::sanitize_group_style( $input['group_style'] ?? array() );
		$imported_profile_styles   = isset( $input['profile_styles'] ) && is_array( $input['profile_styles'] ) ? $input['profile_styles'] : self::styles_from_profiles( $input['profiles'] ?? array() );

		foreach ( $imported_profile_styles as $key => $profile_styles ) {
			if ( ! isset( $settings['profiles'][ $key ] ) || ! is_array( $settings['profiles'][ $key ] ) || ! is_array( $profile_styles ) ) {
				continue;
			}

			$profile = $settings['profiles'][ $key ];
			foreach ( (array) ( $profile['items'] ?? array() ) as $slug => $item ) {
				if ( is_array( $item ) && isset( $profile_styles['items'][ $slug ] ) && is_array( $profile_styles['items'][ $slug ] ) ) {
					$profile['items'][ $slug ] = array_merge( $item, self::item_style( $profile_styles['items'][ $slug ] ) );
				}
			}

			foreach ( (array) ( $profile['groups'] ?? array() ) as $index => $group ) {
				$id = is_array( $group ) ? (string) ( $group['id'] ?? '' ) : '';
				if ( '' !== $id && isset( $profile_styles['groups'][ $id ] ) && is_array( $profile_styles['groups'][ $id ] ) ) {
					$profile['groups'][ $index ] = array_merge( $group, self::group_presentation( $profile_styles['groups'][ $id ] ) );
				}
			}

			foreach ( (array) ( $profile['custom_items'] ?? array() ) as $index => $item ) {
				$id = is_array( $item ) ? (string) ( $item['id'] ?? '' ) : '';
				if ( '' !== $id && isset( $profile_styles['custom_items'][ $id ] ) && is_array( $profile_styles['custom_items'][ $id ] ) ) {
					$profile['custom_items'][ $index ] = array_merge( $item, self::item_style( $profile_styles['custom_items'][ $id ] ) );
				}
			}

			$settings['profiles'][ $key ] = self::sanitize_profile( $profile );
		}

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Validate profile identifiers.
	 */
	public static function is_valid_profile_key( string $key ): bool {
		if ( 'default' === $key ) {
			return true;
		}

		if ( str_starts_with( $key, 'role:' ) ) {
			$role = substr( $key, 5 );
			return '' !== $role && isset( wp_roles()->roles[ $role ] );
		}

		if ( str_starts_with( $key, 'user:' ) ) {
			return get_user_by( 'id', absint( substr( $key, 5 ) ) ) instanceof \WP_User;
		}

		return false;
	}

	/**
	 * Normalize a stored profile.
	 */
	private static function normalize_profile( array $profile ): array {
		return wp_parse_args(
			$profile,
			array(
				'items'        => array(),
				'order'        => array(),
				'submenus'     => array(),
				'groups'       => array(),
				'custom_items' => array(),
				'single_open'  => false,
			)
		);
	}

	/**
	 * Sanitize a menu profile.
	 */
	private static function sanitize_profile( array $profile ): array {
		$clean = self::normalize_profile( array() );
		$clean['single_open'] = ! empty( $profile['single_open'] );

		foreach ( (array) ( $profile['items'] ?? array() ) as $slug => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$slug = self::sanitize_slug( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$clean['items'][ $slug ] = self::sanitize_item( $item );
		}

		$clean['order'] = self::sanitize_slug_list( $profile['order'] ?? array() );

		foreach ( (array) ( $profile['submenus'] ?? array() ) as $parent => $children ) {
			$parent = self::sanitize_slug( (string) $parent );
			if ( '' !== $parent ) {
				$clean['submenus'][ $parent ] = self::sanitize_slug_list( $children );
			}
		}

		foreach ( (array) ( $profile['groups'] ?? array() ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$id    = sanitize_key( (string) ( $group['id'] ?? '' ) );
			$label = sanitize_text_field( (string) ( $group['label'] ?? '' ) );
			if ( '' === $id || '' === $label ) {
				continue;
			}
			$pinned = ! empty( $group['pinned'] );
			$clean['groups'][] = array(
				'id'               => $id,
				'label'            => $label,
				'icon'             => sanitize_html_class( (string) ( $group['icon'] ?? 'dashicons-category' ) ),
				'items'            => self::sanitize_slug_list( $group['items'] ?? array() ),
				'collapsed'        => ! $pinned && ! empty( $group['collapsed'] ),
				'pinned'           => $pinned,
				'background'       => sanitize_hex_color( (string) ( $group['background'] ?? '' ) ) ?: '',
				'text_color'       => sanitize_hex_color( (string) ( $group['text_color'] ?? '' ) ) ?: '',
				'border_color'     => sanitize_hex_color( (string) ( $group['border_color'] ?? '' ) ) ?: '',
				'show_heading_border' => ! empty( $group['show_heading_border'] ),
				'hover_background' => sanitize_hex_color( (string) ( $group['hover_background'] ?? '' ) ) ?: '',
				'hover_text_color' => sanitize_hex_color( (string) ( $group['hover_text_color'] ?? '' ) ) ?: '',
				'collapsed_background' => sanitize_hex_color( (string) ( $group['collapsed_background'] ?? '' ) ) ?: '',
				'collapsed_text_color' => sanitize_hex_color( (string) ( $group['collapsed_text_color'] ?? '' ) ) ?: '',
				'collapsed_hover_background' => sanitize_hex_color( (string) ( $group['collapsed_hover_background'] ?? '' ) ) ?: '',
				'collapsed_hover_text_color' => sanitize_hex_color( (string) ( $group['collapsed_hover_text_color'] ?? '' ) ) ?: '',
				'panel_background' => sanitize_hex_color( (string) ( $group['panel_background'] ?? '' ) ) ?: '',
				'panel_text_color'  => sanitize_hex_color( (string) ( $group['panel_text_color'] ?? '' ) ) ?: '',
				'panel_border_color' => sanitize_hex_color( (string) ( $group['panel_border_color'] ?? '' ) ) ?: '',
				'show_panel_border' => ! empty( $group['show_panel_border'] ),
				'panel_hover_background' => sanitize_hex_color( (string) ( $group['panel_hover_background'] ?? '' ) ) ?: '',
				'panel_hover_text_color' => sanitize_hex_color( (string) ( $group['panel_hover_text_color'] ?? '' ) ) ?: '',
			);
		}

		foreach ( (array) ( $profile['custom_items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id    = sanitize_key( (string) ( $item['id'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( '' === $id || '' === $title ) {
				continue;
			}
			$clean['custom_items'][] = array_merge(
				array( 'id' => $id, 'title' => $title ),
				self::sanitize_item( $item )
			);
		}

		return $clean;
	}

	/**
	 * Sanitize one menu item configuration.
	 */
	private static function sanitize_item( array $item ): array {
		$mode = sanitize_key( (string) ( $item['open_mode'] ?? 'same' ) );
		if ( ! in_array( $mode, array( 'same', 'new', 'iframe', 'none' ), true ) ) {
			$mode = 'same';
		}

		return array(
			'title'       => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'url'         => esc_url_raw( (string) ( $item['url'] ?? '' ), array( 'http', 'https' ) ),
			'roles'       => self::sanitize_role_list( $item['roles'] ?? array( 'administrator' ) ),
			'icon'        => self::sanitize_icon( (string) ( $item['icon'] ?? '' ) ),
			'css_class'   => implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) ( $item['css_class'] ?? '' ) ) ?: array() ) ) ),
			'hidden'      => ! empty( $item['hidden'] ),
			'open_mode'   => $mode,
			'parent'      => self::sanitize_slug( (string) ( $item['parent'] ?? '' ) ),
			'background'  => sanitize_hex_color( (string) ( $item['background'] ?? '' ) ) ?: '',
			'text_color'  => sanitize_hex_color( (string) ( $item['text_color'] ?? '' ) ) ?: '',
			'submenu_background' => sanitize_hex_color( (string) ( $item['submenu_background'] ?? '' ) ) ?: '',
			'submenu_text_color' => sanitize_hex_color( (string) ( $item['submenu_text_color'] ?? '' ) ) ?: '',
			'submenu_hover_background' => sanitize_hex_color( (string) ( $item['submenu_hover_background'] ?? '' ) ) ?: '',
			'submenu_hover_text_color' => sanitize_hex_color( (string) ( $item['submenu_hover_text_color'] ?? '' ) ) ?: '',
		);
	}

	/**
	 * Keep Dashicons classes, data image URLs, and HTTP(S) image URLs.
	 */
	private static function sanitize_icon( string $icon ): string {
		if ( str_starts_with( $icon, 'dashicons-' ) ) {
			return sanitize_html_class( $icon );
		}
		if ( str_starts_with( $icon, 'data:image/svg+xml;base64,' ) ) {
			return preg_match( '/^data:image\/svg\+xml;base64,[A-Za-z0-9+\/=]+$/', $icon ) ? $icon : '';
		}

		return esc_url_raw( $icon, array( 'http', 'https' ) );
	}

	/**
	 * Sanitize the visual properties of one menu item.
	 */
	private static function item_style( array $item ): array {
		return array(
			'icon'                     => self::sanitize_icon( (string) ( $item['icon'] ?? '' ) ),
			'background'               => sanitize_hex_color( (string) ( $item['background'] ?? '' ) ) ?: '',
			'text_color'               => sanitize_hex_color( (string) ( $item['text_color'] ?? '' ) ) ?: '',
			'submenu_background'       => sanitize_hex_color( (string) ( $item['submenu_background'] ?? '' ) ) ?: '',
			'submenu_text_color'       => sanitize_hex_color( (string) ( $item['submenu_text_color'] ?? '' ) ) ?: '',
			'submenu_hover_background' => sanitize_hex_color( (string) ( $item['submenu_hover_background'] ?? '' ) ) ?: '',
			'submenu_hover_text_color' => sanitize_hex_color( (string) ( $item['submenu_hover_text_color'] ?? '' ) ) ?: '',
		);
	}

	/**
	 * Sanitize visual group properties without changing group membership or behavior.
	 */
	private static function group_presentation( array $group ): array {
		$clean = array(
			'icon'                => sanitize_html_class( (string) ( $group['icon'] ?? 'dashicons-category' ) ),
			'show_heading_border' => ! empty( $group['show_heading_border'] ),
			'show_panel_border'   => ! empty( $group['show_panel_border'] ),
		);
		foreach ( array( 'background', 'text_color', 'border_color', 'hover_background', 'hover_text_color', 'collapsed_background', 'collapsed_text_color', 'collapsed_hover_background', 'collapsed_hover_text_color', 'panel_background', 'panel_text_color', 'panel_border_color', 'panel_hover_background', 'panel_hover_text_color' ) as $key ) {
			$clean[ $key ] = sanitize_hex_color( (string) ( $group[ $key ] ?? '' ) ) ?: '';
		}

		return $clean;
	}

	/**
	 * Extract presentation data from a complete, older export.
	 */
	private static function styles_from_profiles( $profiles ): array {
		$styles = array();
		foreach ( (array) $profiles as $key => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}
			$styles[ $key ] = array(
				'items'        => array(),
				'groups'       => array(),
				'custom_items' => array(),
			);
			foreach ( (array) ( $profile['items'] ?? array() ) as $slug => $item ) {
				if ( is_array( $item ) ) {
					$styles[ $key ]['items'][ $slug ] = self::item_style( $item );
				}
			}
			foreach ( (array) ( $profile['groups'] ?? array() ) as $group ) {
				if ( is_array( $group ) && ! empty( $group['id'] ) ) {
					$styles[ $key ]['groups'][ (string) $group['id'] ] = self::group_presentation( $group );
				}
			}
			foreach ( (array) ( $profile['custom_items'] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$styles[ $key ]['custom_items'][ (string) $item['id'] ] = self::item_style( $item );
				}
			}
		}

		return $styles;
	}

	/**
	 * Sanitize slugs without destroying query strings used by WordPress menus.
	 */
	private static function sanitize_slug( string $slug ): string {
		return preg_replace( '/[^A-Za-z0-9_.?=&:\/\-]/', '', wp_strip_all_tags( $slug ) ) ?: '';
	}

	private static function sanitize_slug_list( $items ): array {
		$clean = array();
		foreach ( (array) $items as $item ) {
			$item = self::sanitize_slug( (string) $item );
			if ( '' !== $item && ! in_array( $item, $clean, true ) ) {
				$clean[] = $item;
			}
		}

		return $clean;
	}

	/**
	 * Sanitize the roles allowed to see a custom menu item.
	 *
	 * An asterisk intentionally makes the item visible to every role. Items
	 * without a valid saved role default to administrators.
	 */
	private static function sanitize_role_list( $roles ): array {
		$roles = (array) $roles;
		if ( in_array( '*', $roles, true ) ) {
			return array( '*' );
		}

		$registered = array_keys( wp_roles()->roles );
		$clean      = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
		$clean      = array_values( array_intersect( $clean, $registered ) );

		return $clean ?: array( 'administrator' );
	}

	private static function sanitize_appearance( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = self::appearance_defaults();
		foreach ( array( 'background', 'text', 'icon', 'highlight', 'divider_color', 'submenu_background', 'submenu_text', 'submenu_hover_background', 'submenu_hover_text' ) as $key ) {
			$clean[ $key ] = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) ) ?: '';
		}
		$clean['divider_opacity'] = min( 100, absint( $input['divider_opacity'] ?? 13 ) );
		$clean['submenu_indicators'] = ! empty( $input['submenu_indicators'] );
		$clean['menu_width'] = max( 140, min( 320, absint( $input['menu_width'] ?? 275 ) ) );
		$clean['submenu_edge_distance'] = min( 24, absint( $input['submenu_edge_distance'] ?? 0 ) );
		$clean['touch_optimized_menu'] = ! empty( $input['touch_optimized_menu'] );
		$clean['group_margin_inline'] = min( 32, absint( $input['group_margin_inline'] ?? 5 ) );
		$clean['group_margin_block']  = min( 32, absint( $input['group_margin_block'] ?? 4 ) );
		$clean['group_border_radius'] = min( 32, absint( $input['group_border_radius'] ?? 5 ) );
		$clean['group_heading_font_size'] = max( 9, min( 24, absint( $input['group_heading_font_size'] ?? 11 ) ) );
		$font_weight = absint( $input['group_heading_font_weight'] ?? 700 );
		$clean['group_heading_font_weight'] = in_array( $font_weight, array( 400, 500, 600, 700, 800, 900 ), true ) ? $font_weight : 700;
		$clean['group_heading_letter_spacing'] = max( 0, min( 4, (float) ( $input['group_heading_letter_spacing'] ?? 0.4 ) ) );
		$text_transform = sanitize_key( (string) ( $input['group_heading_text_transform'] ?? 'uppercase' ) );
		$clean['group_heading_text_transform'] = in_array( $text_transform, array( 'none', 'uppercase', 'capitalize' ), true ) ? $text_transform : 'uppercase';

		return $clean;
	}

	/**
	 * Keep only registered roles that do not already have full administrative access.
	 */
	private static function sanitize_menu_access_roles( $roles ): array {
		$registered = wp_roles()->roles;
		$clean      = array();

		foreach ( (array) $roles as $role ) {
			$role = sanitize_key( (string) $role );
			if ( isset( $registered[ $role ] ) && empty( $registered[ $role ]['capabilities']['manage_options'] ) ) {
				$clean[] = $role;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Sanitize the menu shown to roles that cannot open Menu Manager.
	 */
	private static function sanitize_menu_role_modes( $modes ): array {
		$registered = wp_roles()->roles;
		$clean      = array();

		foreach ( (array) $modes as $role => $mode ) {
			$role = sanitize_key( (string) $role );
			$mode = sanitize_key( (string) $mode );
			if ( isset( $registered[ $role ] ) && in_array( $mode, array( 'managed', 'wordpress' ), true ) ) {
				$clean[ $role ] = $mode;
			}
		}

		return $clean;
	}

	/**
	 * Keep valid user IDs without changing profile protection based on current access.
	 */
	private static function sanitize_user_ids( $user_ids ): array {
		return array_values(
			array_filter(
				array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ),
				static fn( int $user_id ): bool => get_user_by( 'id', $user_id ) instanceof \WP_User
			)
		);
	}

	private static function sanitize_group_style( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = self::group_style_defaults();
		foreach ( $clean as $key => $default ) {
			$clean[ $key ] = sanitize_hex_color( (string) ( $input[ $key ] ?? '' ) ) ?: $default;
		}

		return $clean;
	}

}
