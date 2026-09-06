<?php
/**
 * Plugin bootstrap.
 *
 * @package UplinkAdminMenuManager
 */

namespace Uplink\AdminMenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	public static function init(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_menu_manager_capability' ), 10, 4 );
		add_action( 'set_user_role', array( __CLASS__, 'handle_set_user_role' ), 10, 3 );
		add_action( 'remove_user_role', array( __CLASS__, 'handle_remove_user_role' ), 10, 2 );
		Menu_Manager::init();
		Admin_Page::init();
	}

	/**
	 * Map Menu Manager access without granting any broader WordPress capability.
	 */
	public static function map_menu_manager_capability( array $caps, string $cap, int $user_id, array $args ): array {
		unset( $args );
		if ( Settings::ACCESS_CAPABILITY !== $cap ) {
			return $caps;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array( 'do_not_allow' );
		}

		if ( Settings::user_has_full_access( $user ) ) {
			return array( 'manage_options' );
		}

		return Settings::user_can_customize_own_menu( $user ) ? array( 'read' ) : array( 'do_not_allow' );
	}

	/**
	 * Clear an unprotected personal profile when replacing a user's roles removes access.
	 */
	public static function handle_set_user_role( int $user_id, string $role, array $old_roles ): void {
		unset( $role );
		Settings::clear_profile_after_role_change( $user_id, $old_roles );
	}

	/**
	 * Clear an unprotected personal profile when removing one role removes access.
	 */
	public static function handle_remove_user_role( int $user_id, string $role ): void {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$previous_roles   = (array) $user->roles;
		$previous_roles[] = $role;
		Settings::clear_profile_after_role_change( $user_id, $previous_roles );
	}

	public static function asset_version( string $relative_path ): string {
		$path     = UAMM_PATH . ltrim( $relative_path, '/' );
		$modified = is_file( $path ) ? filemtime( $path ) : false;

		return false !== $modified ? UAMM_VERSION . '.' . $modified : UAMM_VERSION;
	}
}
