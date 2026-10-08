<?php
/**
 * Branded settings screen and form handlers.
 *
 * @package UplinkAdminMenuManager
 */

namespace Uplink\AdminMenuManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Page {
	private static string $hook = '';
	private static array $menu_snapshot = array();

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ), PHP_INT_MAX - 40 );
		add_action( 'admin_menu', array( __CLASS__, 'capture_menu' ), PHP_INT_MAX - 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UAMM_FILE ), array( __CLASS__, 'settings_link' ) );
		add_action( 'admin_post_uamm_save_profile', array( __CLASS__, 'save_profile' ) );
		add_action( 'admin_post_uamm_delete_profile', array( __CLASS__, 'delete_profile' ) );
		add_action( 'admin_post_uamm_save_site_settings', array( __CLASS__, 'save_site_settings' ) );
		add_action( 'admin_post_uamm_reset_appearance', array( __CLASS__, 'reset_appearance' ) );
		add_action( 'admin_post_uamm_export', array( __CLASS__, 'export_settings' ) );
		add_action( 'admin_post_uamm_import', array( __CLASS__, 'import_settings' ) );
		add_action( 'admin_post_uamm_clear_settings', array( __CLASS__, 'clear_settings' ) );
	}

	public static function register_page(): void {
		self::$hook = (string) add_menu_page(
			__( 'Uplink Admin Menu Manager', 'uplink-admin-menu-manager' ),
			__( 'Menu Manager', 'uplink-admin-menu-manager' ),
			Settings::ACCESS_CAPABILITY,
			'uplink-admin-menu-manager',
			array( __CLASS__, 'render' ),
			UAMM_URL . 'assets/layout-list-stroke-standard.svg',
			null
		);
	}

	public static function capture_menu(): void {
		global $menu, $submenu;

		$captured_menu    = is_array( $menu ) ? $menu : array();
		$captured_submenu = is_array( $submenu ) ? $submenu : array();

		// WordPress renders menu entries by their numeric position keys. Preserve
		// that same initial order in the editor before a profile is customized.
		ksort( $captured_menu, SORT_NUMERIC );
		foreach ( $captured_submenu as &$submenu_items ) {
			if ( is_array( $submenu_items ) ) {
				ksort( $submenu_items, SORT_NUMERIC );
			}
		}
		unset( $submenu_items );

		self::$menu_snapshot = array(
			'menu'    => $captured_menu,
			'submenu' => $captured_submenu,
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( self::$hook !== $hook ) {
			return;
		}

		wp_enqueue_style( 'uamm-admin', UAMM_URL . 'assets/admin.css', array( 'dashicons' ), Plugin::asset_version( 'assets/admin.css' ) );
		wp_enqueue_script( 'uamm-sortable', UAMM_URL . 'assets/vendor/sortable.min.js', array(), Plugin::asset_version( 'assets/vendor/sortable.min.js' ), true );
		wp_enqueue_script( 'uamm-admin', UAMM_URL . 'assets/admin.js', array( 'uamm-sortable' ), Plugin::asset_version( 'assets/admin.js' ), true );
		wp_add_inline_script(
			'uamm-admin',
			'window.uammAdmin = ' . wp_json_encode(
				array(
					'groupStyle' => (array) Settings::get_all()['group_style'],
					'profileKey' => self::selected_profile_key(),
					'userId'     => get_current_user_id(),
					'i18n' => array(
						'remove'       => __( 'Remove', 'uplink-admin-menu-manager' ),
						'newGroup'     => __( 'New group', 'uplink-admin-menu-manager' ),
						'newItem'      => __( 'New menu item', 'uplink-admin-menu-manager' ),
						/* translators: %s: menu group label. */
						'collapseGroup' => __( 'Collapse %s group', 'uplink-admin-menu-manager' ),
						/* translators: %s: menu group label. */
						'expandGroup'   => __( 'Expand %s group', 'uplink-admin-menu-manager' ),
						'confirmReset' => __( 'Delete this profile and return to inherited settings?', 'uplink-admin-menu-manager' ),
						'confirmAppearanceReset' => __( 'Reset all Appearance settings and clear individual item and group color overrides? Menu structure, labels, icons, plugin rules, and users will not be changed.', 'uplink-admin-menu-manager' ),
						'confirmClear' => __( 'Clear every saved Menu Manager profile and setting on this site? This cannot be undone.', 'uplink-admin-menu-manager' ),
						'contrastBlocked' => __( 'Color changes were not saved. Fix the failing contrast checks below.', 'uplink-admin-menu-manager' ),
						'contrastReady' => __( 'Contrast checks pass.', 'uplink-admin-menu-manager' ),
						'colorContrast' => __( 'Color contrast', 'uplink-admin-menu-manager' ),
						'appearanceContrastBlocked' => __( 'Cannot save: fix the admin menu or group contrast checks.', 'uplink-admin-menu-manager' ),
						'menuColorsContrastBlocked' => __( 'Cannot save: fix contrast in Menu colors.', 'uplink-admin-menu-manager' ),
						'groupColorsContrastBlocked' => __( 'Cannot save: fix contrast in Group colors.', 'uplink-admin-menu-manager' ),
						'allColorsContrastBlocked' => __( 'Cannot save: fix contrast in Menu colors and Group colors.', 'uplink-admin-menu-manager' ),
						'unsavedAppearance' => __( 'You have unsaved Appearance changes. Leave this section and discard the preview?', 'uplink-admin-menu-manager' ),
						/* translators: %d: number of groups with failing text contrast. */
						'contrastCount' => __( 'Cannot save: fix text contrast in %d group(s).', 'uplink-admin-menu-manager' ),
						/* translators: %d: number of menu items or groups with failing contrast. */
						'menuContrastCount' => __( 'Cannot save: fix %d contrast check(s).', 'uplink-admin-menu-manager' ),
						'chooseIcon' => __( 'Choose Dashicon', 'uplink-admin-menu-manager' ),
						'saveGroupForDrop' => __( 'Save the menu profile to activate this group\'s drop area.', 'uplink-admin-menu-manager' ),
						'dragItemsHere' => __( 'Drag menu items here', 'uplink-admin-menu-manager' ),
						'dropAtEnd' => __( 'Drop after the last group or menu item', 'uplink-admin-menu-manager' ),
						/* translators: %s: menu group label. */
						'dropIntoGroup' => __( 'Drop into %s', 'uplink-admin-menu-manager' ),
						/* translators: %s: Dashicon name. */
						'useIcon' => __( 'Use %s', 'uplink-admin-menu-manager' ),
						/* translators: %d: number of available Dashicons. */
						'iconCount' => __( '%d icons', 'uplink-admin-menu-manager' ),
						'iconPickerTitle' => __( 'Choose a Dashicon', 'uplink-admin-menu-manager' ),
						'iconPickerHelp' => __( 'Choose an icon supplied by this WordPress installation.', 'uplink-admin-menu-manager' ),
						'searchIcons' => __( 'Search icons', 'uplink-admin-menu-manager' ),
						'searchIconsPlaceholder' => __( 'Search by name', 'uplink-admin-menu-manager' ),
						'closeIconPicker' => __( 'Close icon picker', 'uplink-admin-menu-manager' ),
						'dashiconsReference' => __( 'Dashicons reference', 'uplink-admin-menu-manager' ),
						'close' => __( 'Close', 'uplink-admin-menu-manager' ),
					),
				)
			) . ';',
			'before'
		);
	}

	public static function settings_link( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=uplink-admin-menu-manager' ) ) . '">' . esc_html__( 'Settings', 'uplink-admin-menu-manager' ) . '</a>' );
		return $links;
	}

	public static function render(): void {
		self::guard();
		$full_access = Settings::user_has_full_access();
		$tab = sanitize_key( (string) ( $_GET['tab'] ?? 'menus' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $full_access || ! in_array( $tab, array( 'menus', 'access', 'appearance', 'tools' ), true ) ) {
			$tab = 'menus';
		}
		?>
		<div class="wrap uamm-admin">
			<h1 class="screen-reader-text"><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php self::render_hero(); ?>
			<?php self::render_notices(); ?>
			<?php if ( $full_access ) : ?><nav class="uamm-tabs" role="tablist" aria-orientation="horizontal" aria-label="<?php esc_attr_e( 'Menu Manager sections', 'uplink-admin-menu-manager' ); ?>">
				<?php
				$tabs = array(
					'menus'      => __( 'Menus and groups', 'uplink-admin-menu-manager' ),
					'appearance' => __( 'Appearance', 'uplink-admin-menu-manager' ),
					'access'     => __( 'Users', 'uplink-admin-menu-manager' ),
					'tools'      => __( 'Import and export', 'uplink-admin-menu-manager' ),
				);
				foreach ( $tabs as $key => $label ) {
					$url = add_query_arg( array( 'page' => 'uplink-admin-menu-manager', 'tab' => $key ), admin_url( 'admin.php' ) );
					printf( '<a id="uamm-tab-%1$s" class="%2$s" href="%3$s" role="tab" aria-controls="uamm-panel-%1$s" aria-selected="%4$s" tabindex="%5$s" data-uamm-tab="%1$s">%6$s</a>', esc_attr( $key ), esc_attr( $key === $tab ? 'uamm-tabs__tab is-active' : 'uamm-tabs__tab' ), esc_url( $url ), $key === $tab ? 'true' : 'false', $key === $tab ? '0' : '-1', esc_html( $label ) );
				}
				?>
			</nav><?php endif; ?>
			<div class="uamm-tab-panels">
				<section id="uamm-panel-menus" class="uamm-tab-panel" role="tabpanel" aria-labelledby="uamm-tab-menus" data-uamm-panel="menus"<?php echo 'menus' === $tab ? '' : ' hidden'; ?>><?php self::render_menus(); ?></section>
				<?php if ( $full_access ) : ?>
					<section id="uamm-panel-appearance" class="uamm-tab-panel" role="tabpanel" aria-labelledby="uamm-tab-appearance" data-uamm-panel="appearance"<?php echo 'appearance' === $tab ? '' : ' hidden'; ?>><?php self::render_appearance(); ?></section>
					<section id="uamm-panel-access" class="uamm-tab-panel" role="tabpanel" aria-labelledby="uamm-tab-access" data-uamm-panel="access"<?php echo 'access' === $tab ? '' : ' hidden'; ?>><?php self::render_access(); ?></section>
					<section id="uamm-panel-tools" class="uamm-tab-panel" role="tabpanel" aria-labelledby="uamm-tab-tools" data-uamm-panel="tools"<?php echo 'tools' === $tab ? '' : ' hidden'; ?>><?php self::render_tools(); ?></section>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_hero(): void {
		?>
		<header class="uamm-hero">
			<div class="uamm-hero__brand">
				<span class="uamm-hero__mark" aria-hidden="true"><img src="<?php echo esc_url( UAMM_URL . 'assets/uplink-admin-menu-manager-logo.svg' ); ?>" alt="" width="78" height="78"></span>
				<div>
					<div class="uamm-hero__meta">
						<span class="uamm-hero__eyebrow"><?php esc_html_e( 'Uplink.Press administration', 'uplink-admin-menu-manager' ); ?></span>
						<?php
						/* translators: %s: current plugin version. */
						$version_label = sprintf( __( 'Version %s', 'uplink-admin-menu-manager' ), UAMM_VERSION );
						?>
						<span class="uamm-hero__version"><?php echo esc_html( $version_label ); ?></span>
					</div>
					<div class="uamm-hero__title" aria-hidden="true"><?php echo esc_html( get_admin_page_title() ); ?></div>
					<p><?php esc_html_e( 'Organize WordPress admin navigation with flexible menu profiles and collapsible groups.', 'uplink-admin-menu-manager' ); ?></p>
				</div>
			</div>
		</header>
		<?php
	}

	private static function render_notices(): void {
		if ( isset( $_GET['uamm-cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All stored Menu Manager configuration was cleared.', 'uplink-admin-menu-manager' ) . '</p></div>';
		} elseif ( isset( $_GET['uamm-appearance-reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible" role="status" data-uamm-auto-dismiss="10000"><p>' . esc_html__( 'Appearance settings were reset to the plugin defaults.', 'uplink-admin-menu-manager' ) . '</p></div>';
		} elseif ( isset( $_GET['uamm-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible" role="status" data-uamm-auto-dismiss="10000"><p>' . esc_html__( 'Settings saved.', 'uplink-admin-menu-manager' ) . '</p></div>';
		}
		if ( isset( $_GET['uamm-error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The settings could not be saved. Check the submitted data and try again.', 'uplink-admin-menu-manager' ) . '</p></div>';
		}
	}

	private static function render_menus(): void {
		$full_access = Settings::user_has_full_access();
		$profile_key = self::selected_profile_key();
		$profile     = Settings::get_profile( $profile_key );
		$profile_locked = Settings::is_profile_write_protected( $profile_key );
		$menu        = self::$menu_snapshot['menu'] ?? array();
		$submenu     = self::$menu_snapshot['submenu'] ?? array();
		self::add_custom_items_to_editor_menu( $profile, $menu, $submenu );
		?>
		<div class="uamm-layout">
			<main class="uamm-main">
				<section class="uamm-card" aria-labelledby="uamm-menu-title">
					<div class="uamm-card__header uamm-card__header--split">
						<div><h2 id="uamm-menu-title"><?php esc_html_e( 'Admin menu', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Drag items by the left handle or use the arrow buttons. Drop an item on a collapsed group heading to place it inside.', 'uplink-admin-menu-manager' ); ?></p></div>
						<?php if ( $full_access ) : ?><form method="get"><input type="hidden" name="page" value="uplink-admin-menu-manager"><label for="uamm-profile"><span><?php esc_html_e( 'Editing profile', 'uplink-admin-menu-manager' ); ?></span><?php self::profile_select( $profile_key ); ?></label><button class="button" type="submit"><?php esc_html_e( 'Load', 'uplink-admin-menu-manager' ); ?></button></form><?php else : ?><span class="uamm-profile-context"><?php esc_html_e( 'Your personal menu profile', 'uplink-admin-menu-manager' ); ?></span><?php endif; ?>
					</div>
					<?php if ( $profile_locked ) : ?><div class="uamm-form-message" role="status"><?php esc_html_e( 'This user’s saved menu profile is protected from changes by other administrators. Remove protection on the Users tab before editing or deleting it.', 'uplink-admin-menu-manager' ); ?></div><?php endif; ?>
					<form id="uamm-menu-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="uamm_save_profile">
						<input type="hidden" name="profile_key" value="<?php echo esc_attr( $profile_key ); ?>">
						<input id="uamm-profile-json" type="hidden" name="profile_json" value="">
						<?php wp_nonce_field( 'uamm_save_profile' ); ?>
						<fieldset class="uamm-profile-editor" <?php disabled( $profile_locked ); ?>>
						<div class="uamm-group-behavior">
							<label class="uamm-check"><input data-profile-field="single_open" type="checkbox" <?php checked( ! empty( $profile['single_open'] ) ); ?>><span><?php esc_html_e( 'Only one unpinned group open at a time', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Pinned groups stay open. Each user can change pins from the admin menu.', 'uplink-admin-menu-manager' ); ?></small></span></label>
						</div>
						<div class="uamm-group-view-controls" role="group" aria-label="<?php esc_attr_e( 'Group display controls', 'uplink-admin-menu-manager' ); ?>"><span><?php esc_html_e( 'Group view', 'uplink-admin-menu-manager' ); ?></span><button id="uamm-collapse-all-groups" class="button button-small" type="button"><?php esc_html_e( 'Collapse all', 'uplink-admin-menu-manager' ); ?></button><button id="uamm-expand-all-groups" class="button button-small" type="button"><?php esc_html_e( 'Expand all', 'uplink-admin-menu-manager' ); ?></button><label class="uamm-check uamm-group-view-default"><input id="uamm-always-collapse-groups" type="checkbox"><span><?php esc_html_e( 'Always load groups collapsed', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Saved for this user, profile, and browser.', 'uplink-admin-menu-manager' ); ?></small></span></label></div>
						<ul id="uamm-menu-list" class="uamm-menu-list">
							<?php self::render_menu_hierarchy( $menu, $submenu, $profile ); ?>
						</ul>
						<div id="uamm-contrast-summary" class="uamm-form-message" role="alert" tabindex="-1" hidden></div>
						<div class="uamm-card__footer"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save menu profile', 'uplink-admin-menu-manager' ); ?></button><span id="uamm-save-contrast-state" class="uamm-save-state" aria-live="polite"></span><span class="uamm-save-note"><?php esc_html_e( 'Menu visibility changes navigation only. WordPress capabilities still control access to each screen.', 'uplink-admin-menu-manager' ); ?></span></div>
						</fieldset>
					</form>
				</section>
			</main>
			<aside class="uamm-sidebar" aria-label="<?php esc_attr_e( 'Menu tools', 'uplink-admin-menu-manager' ); ?>">
				<section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Collapsible groups', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Groups now live in the menu order. Drag menu items into a group, drag them back out, or use each item\'s Group selector.', 'uplink-admin-menu-manager' ); ?></p><button id="uamm-add-group" class="button button-primary" type="button"><?php esc_html_e( 'Add group to menu', 'uplink-admin-menu-manager' ); ?></button></section>
				<section class="uamm-card uamm-card--compact" aria-labelledby="uamm-icon-key-title"><h2 id="uamm-icon-key-title"><?php esc_html_e( 'Control icons', 'uplink-admin-menu-manager' ); ?></h2><dl class="uamm-icon-key"><div><dt><span class="uamm-control-icon uamm-icon-drag" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Drag handle', 'uplink-admin-menu-manager' ); ?></span></dt><dd><?php esc_html_e( 'Drag to reposition an item or group.', 'uplink-admin-menu-manager' ); ?></dd></div><div><dt><span class="uamm-control-icon uamm-icon-move-up" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Move up', 'uplink-admin-menu-manager' ); ?></span></dt><dd><?php esc_html_e( 'Move up one position in the current list.', 'uplink-admin-menu-manager' ); ?></dd></div><div><dt><span class="uamm-control-icon uamm-icon-move-down" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Move down', 'uplink-admin-menu-manager' ); ?></span></dt><dd><?php esc_html_e( 'Move down one position in the current list.', 'uplink-admin-menu-manager' ); ?></dd></div><div><dt><span class="uamm-control-icon uamm-icon-chevron" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Open or close', 'uplink-admin-menu-manager' ); ?></span></dt><dd><?php esc_html_e( 'Open or close a group or its options.', 'uplink-admin-menu-manager' ); ?></dd></div><div><dt><span class="uamm-control-icon uamm-icon-settings" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Settings', 'uplink-admin-menu-manager' ); ?></span></dt><dd><?php esc_html_e( 'Edit group settings.', 'uplink-admin-menu-manager' ); ?></dd></div></dl></section>
				<section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Custom menu items', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Add internal resources, external links, non-clickable headings, or pages displayed in a contained admin frame.', 'uplink-admin-menu-manager' ); ?></p><div id="uamm-custom-list" class="uamm-repeaters"><?php foreach ( $profile['custom_items'] as $custom ) { self::render_custom_row( $custom ); } ?></div><button id="uamm-add-custom" class="button" type="button"><?php esc_html_e( 'Add menu item', 'uplink-admin-menu-manager' ); ?></button></section>
				<?php if ( 'default' !== $profile_key ) : ?><section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Inheritance', 'uplink-admin-menu-manager' ); ?></h2><p><?php echo esc_html( $profile_locked ? __( 'This protected user profile cannot be deleted by another administrator.', 'uplink-admin-menu-manager' ) : __( 'Delete this override to inherit the default profile again.', 'uplink-admin-menu-manager' ) ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-uamm-reset><input type="hidden" name="action" value="uamm_delete_profile"><input type="hidden" name="profile_key" value="<?php echo esc_attr( $profile_key ); ?>"><?php wp_nonce_field( 'uamm_delete_profile' ); ?><button class="button button-link-delete" type="submit" <?php disabled( $profile_locked ); ?>><?php esc_html_e( 'Delete profile override', 'uplink-admin-menu-manager' ); ?></button></form></section><?php endif; ?>
			</aside>
		</div>
		<?php
	}

	/**
	 * Add saved custom items to the clean menu snapshot used by the editor.
	 *
	 * The snapshot is captured before profile changes are applied so hidden and
	 * reordered native items remain editable. Custom items do not exist at that
	 * point, so they must be added separately for ordering and group assignment.
	 */
	private static function add_custom_items_to_editor_menu( array $profile, array &$menu, array &$submenu ): void {
		foreach ( (array) ( $profile['custom_items'] ?? array() ) as $custom ) {
			if ( ! is_array( $custom ) ) {
				continue;
			}

			$id         = sanitize_key( (string) ( $custom['id'] ?? '' ) );
			$title      = (string) ( $custom['title'] ?? '' );
			$capability = (string) ( $custom['capability'] ?? 'read' );
			$capability = '' !== $capability ? $capability : 'read';
			$slug       = 'uamm-custom-' . $id;
			$parent     = (string) ( $custom['parent'] ?? '' );
			$icon       = (string) ( $custom['icon'] ?? 'dashicons-admin-generic' );
			$mode       = (string) ( $custom['open_mode'] ?? 'same' );

			if ( '' === $id || '' === $title || ! current_user_can( $capability ) ) {
				continue;
			}

			if ( 'iframe' !== $mode && '' !== $parent ) {
				$submenu[ $parent ]   = is_array( $submenu[ $parent ] ?? null ) ? $submenu[ $parent ] : array();
				$submenu[ $parent ][] = array( $title, $capability, $slug );
				continue;
			}

			$menu[] = array( $title, $capability, $slug, '', 'menu-top uamm-custom-item', $slug, $icon );
		}
	}

	/**
	 * Render the menu as a hierarchy of ungrouped items and group containers.
	 */
	private static function render_menu_hierarchy( array $menu, array $submenu, array $profile ): void {
		$items = array();
		foreach ( $menu as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( '' !== $slug && ! str_starts_with( $slug, 'separator' ) && self::is_manageable_menu_item( $item, $slug ) ) {
				$items[ $slug ] = $item;
			}
		}

		$ordered_slugs = array_values( array_intersect( (array) $profile['order'], array_keys( $items ) ) );
		$ordered_slugs = array_merge( $ordered_slugs, array_values( array_diff( array_keys( $items ), $ordered_slugs ) ) );
		$groups        = array();
		$item_groups   = array();

		foreach ( (array) $profile['groups'] as $group ) {
			$id = (string) ( $group['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$groups[ $id ] = $group;
			foreach ( (array) ( $group['items'] ?? array() ) as $slug ) {
				if ( isset( $items[ $slug ] ) && ! isset( $item_groups[ $slug ] ) ) {
					$item_groups[ $slug ] = $id;
				}
			}
		}

		$rendered_groups = array();
		foreach ( $ordered_slugs as $slug ) {
			$group_id = $item_groups[ $slug ] ?? '';
			if ( '' === $group_id ) {
				self::render_menu_item( $items[ $slug ], $submenu, $profile );
				continue;
			}
			if ( isset( $rendered_groups[ $group_id ] ) ) {
				continue;
			}

			$members = array();
			foreach ( $ordered_slugs as $member_slug ) {
				if ( ( $item_groups[ $member_slug ] ?? '' ) === $group_id ) {
					$members[] = $items[ $member_slug ];
				}
			}
			self::render_group_row( $groups[ $group_id ], $members, $submenu, $profile );
			$rendered_groups[ $group_id ] = true;
		}

		foreach ( $groups as $group_id => $group ) {
			if ( ! isset( $rendered_groups[ $group_id ] ) ) {
				self::render_group_row( $group, array(), $submenu, $profile );
			}
		}
	}

	private static function is_manageable_menu_item( array $item, string $slug ): bool {
		$capability = (string) ( $item[1] ?? 'read' );
		if ( '' !== $capability && ! current_user_can( $capability ) ) {
			return false;
		}

		$path  = (string) wp_parse_url( $slug, PHP_URL_PATH );
		$query = wp_parse_args( (string) wp_parse_url( $slug, PHP_URL_QUERY ) );
		if ( 'edit-tags.php' !== basename( $path ) || empty( $query['taxonomy'] ) ) {
			return true;
		}

		$taxonomy_name = sanitize_key( (string) $query['taxonomy'] );
		if ( 'link_category' === $taxonomy_name && ! get_option( 'link_manager_enabled' ) ) {
			return false;
		}

		$taxonomy = get_taxonomy( $taxonomy_name );
		return $taxonomy instanceof \WP_Taxonomy && current_user_can( $taxonomy->cap->manage_terms );
	}

	private static function render_menu_item( array $item, array $submenu, array $profile ): void {
		static $appearance_defaults = null;
		static $group_defaults      = null;
		if ( null === $appearance_defaults || null === $group_defaults ) {
			$settings            = Settings::get_all();
			$appearance_defaults = (array) $settings['appearance'];
			$group_defaults      = (array) $settings['group_style'];
		}
		$slug = isset( $item[2] ) ? (string) $item[2] : '';
		if ( '' === $slug || str_starts_with( $slug, 'separator' ) ) {
			return;
		}
		$is_safety_item = in_array( $slug, array( 'index.php', 'uplink-admin-menu-manager' ), true );
		$config = is_array( $profile['items'][ $slug ] ?? null ) ? $profile['items'][ $slug ] : array();
		$configured_icon = (string) ( $config['icon'] ?? '' );
		$original_icon   = (string) ( $item[6] ?? '' );
		$preview_icon    = '' !== $configured_icon ? $configured_icon : $original_icon;
		$raw_title = preg_replace( '/<span[^>]*>.*?<\/span>/s', '', (string) ( $item[0] ?? $slug ) );
		$title     = wp_strip_all_tags( (string) $raw_title );
		$group  = '';
		foreach ( $profile['groups'] as $candidate ) {
			if ( in_array( $slug, (array) ( $candidate['items'] ?? array() ), true ) ) {
				$group = (string) $candidate['id'];
				break;
			}
		}
		$item_background_fallback       = '' !== $group ? (string) $group_defaults['panel_background'] : (string) $appearance_defaults['background'];
		$item_text_fallback             = '' !== $group ? (string) $group_defaults['panel_text_color'] : (string) $appearance_defaults['text'];
		// Expanded plugin submenus always inherit the global submenu palette.
		// Group panel colors only style the top-level row while it is inside a group.
		$submenu_background_fallback       = (string) $appearance_defaults['submenu_background'];
		$submenu_text_fallback             = (string) $appearance_defaults['submenu_text'];
		$submenu_hover_background_fallback = (string) $appearance_defaults['submenu_hover_background'];
		$submenu_hover_text_fallback       = (string) $appearance_defaults['submenu_hover_text'];
		$item_settings_id                  = 'uamm-item-settings-' . md5( $slug );
		?>
		<li class="uamm-menu-item" data-slug="<?php echo esc_attr( $slug ); ?>" data-safety-item="<?php echo esc_attr( $is_safety_item ? '1' : '0' ); ?>">
			<div class="uamm-menu-item__summary"><button class="uamm-drag" type="button" aria-label="<?php esc_attr_e( 'Drag to reorder', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-drag" aria-hidden="true"></span></button><?php self::render_menu_icon_preview( $preview_icon, 'uamm-menu-item__icon' ); ?><strong><?php echo esc_html( $title ); ?></strong><code><?php echo esc_html( $slug ); ?></code><span class="uamm-row-actions"><button class="uamm-move-up" type="button" aria-label="<?php esc_attr_e( 'Move up', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-move-up" aria-hidden="true"></span></button><button class="uamm-move-down" type="button" aria-label="<?php esc_attr_e( 'Move down', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-move-down" aria-hidden="true"></span></button><button class="uamm-disclosure" type="button" aria-expanded="false" aria-label="<?php esc_attr_e( 'Edit menu item', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-chevron" aria-hidden="true"></span></button></span></div>
			<div class="uamm-menu-item__details" hidden>
				<div class="uamm-group-settings-tabs uamm-item-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Menu item settings', 'uplink-admin-menu-manager' ); ?>">
					<button id="<?php echo esc_attr( $item_settings_id ); ?>-general-tab" class="uamm-group-settings-tab uamm-item-settings-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="<?php echo esc_attr( $item_settings_id ); ?>-general"><?php esc_html_e( 'General', 'uplink-admin-menu-manager' ); ?></button>
					<button id="<?php echo esc_attr( $item_settings_id ); ?>-appearance-tab" class="uamm-group-settings-tab uamm-item-settings-tab" type="button" role="tab" aria-selected="false" aria-controls="<?php echo esc_attr( $item_settings_id ); ?>-appearance" tabindex="-1"><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></button>
				</div>
				<div id="<?php echo esc_attr( $item_settings_id ); ?>-general" class="uamm-group-settings-panel uamm-item-settings-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $item_settings_id ); ?>-general-tab">
					<fieldset class="uamm-group-settings-section uamm-item-settings-section"><legend><?php esc_html_e( 'Item details', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Change how this menu item is labeled, grouped, and linked.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
						<label><?php esc_html_e( 'Menu label', 'uplink-admin-menu-manager' ); ?><input data-field="title" type="text" value="<?php echo esc_attr( (string) ( $config['title'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $title ); ?>"></label>
						<label><?php esc_html_e( 'Group', 'uplink-admin-menu-manager' ); ?><select data-field="group"><option value=""><?php esc_html_e( 'No group', 'uplink-admin-menu-manager' ); ?></option><?php foreach ( $profile['groups'] as $candidate ) : ?><option value="<?php echo esc_attr( $candidate['id'] ); ?>" <?php selected( $group, $candidate['id'] ); ?>><?php echo esc_html( $candidate['label'] ); ?></option><?php endforeach; ?></select></label>
						<div class="uamm-icon-field"><label><span><?php esc_html_e( 'Icon or URL', 'uplink-admin-menu-manager' ); ?></span><span class="uamm-icon-control"><?php self::render_menu_icon_preview( $preview_icon, 'uamm-icon-field__preview' ); ?><input data-field="icon" type="text" value="<?php echo esc_attr( $configured_icon ); ?>" placeholder="dashicons-admin-generic"><button class="button uamm-open-dashicon-picker" type="button" aria-label="<?php esc_attr_e( 'Choose Dashicon', 'uplink-admin-menu-manager' ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></span></label></div>
						<label><?php esc_html_e( 'CSS classes', 'uplink-admin-menu-manager' ); ?><input data-field="css_class" type="text" value="<?php echo esc_attr( (string) ( $config['css_class'] ?? '' ) ); ?>"></label>
						<label><?php esc_html_e( 'Override link URL', 'uplink-admin-menu-manager' ); ?><input data-field="url" type="url" value="<?php echo esc_attr( (string) ( $config['url'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Keep the original URL', 'uplink-admin-menu-manager' ); ?>" <?php disabled( $is_safety_item ); ?>></label>
						<label><?php esc_html_e( 'Open in', 'uplink-admin-menu-manager' ); ?><select data-field="open_mode" <?php disabled( $is_safety_item ); ?>><option value="same" <?php selected( $config['open_mode'] ?? 'same', 'same' ); ?>><?php esc_html_e( 'Same window', 'uplink-admin-menu-manager' ); ?></option><option value="new" <?php selected( $config['open_mode'] ?? '', 'new' ); ?>><?php esc_html_e( 'New tab', 'uplink-admin-menu-manager' ); ?></option><option value="none" <?php selected( $config['open_mode'] ?? '', 'none' ); ?>><?php esc_html_e( 'Non-clickable', 'uplink-admin-menu-manager' ); ?></option></select></label>
					</div></fieldset>
					<fieldset class="uamm-group-settings-section uamm-item-settings-section"><legend><?php esc_html_e( 'Visibility', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Control whether this item appears in the selected profile.', 'uplink-admin-menu-manager' ); ?></p>
						<label class="uamm-check uamm-item-visibility-control"><input data-field="hidden" type="checkbox" <?php checked( ! empty( $config['hidden'] ) ); ?> <?php disabled( $is_safety_item ); ?>><span><?php esc_html_e( 'Hide this item for the selected profile', 'uplink-admin-menu-manager' ); ?><?php if ( $is_safety_item ) : ?><small><?php esc_html_e( 'This essential navigation item can be moved into a group, but it cannot be hidden or redirected.', 'uplink-admin-menu-manager' ); ?></small><?php endif; ?></span></label>
					</fieldset>
					<?php if ( ! empty( $submenu[ $slug ] ) ) : ?>
						<?php
						$original_submenu_order = array_values(
							array_map(
								static fn( $child ) => $slug . '::' . (string) ( $child[2] ?? '' ),
								(array) $submenu[ $slug ]
							)
						);
						$submenu_items = self::order_submenu_items_for_editor( $slug, (array) $submenu[ $slug ], $profile );
						?>
						<fieldset class="uamm-group-settings-section uamm-item-settings-section"><legend><?php esc_html_e( 'Submenu items', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Reorder, rename, move, or hide individual links in this submenu.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-submenu" data-submenu-parent="<?php echo esc_attr( $slug ); ?>" data-submenu-original-order="<?php echo esc_attr( wp_json_encode( $original_submenu_order ) ); ?>"><?php foreach ( $submenu_items as $child ) { self::render_submenu_item( $slug, $child, $profile ); } ?></div></fieldset>
					<?php endif; ?>
				</div>
				<div id="<?php echo esc_attr( $item_settings_id ); ?>-appearance" class="uamm-group-settings-panel uamm-item-settings-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $item_settings_id ); ?>-appearance-tab" hidden data-item-appearance>
					<div class="uamm-group-settings-sections">
						<fieldset class="uamm-group-settings-section uamm-item-settings-section"><legend><?php esc_html_e( 'Top-level item', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors for this item in the main admin menu.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
							<?php self::render_item_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'background', (string) ( $config['background'] ?? '' ), $item_background_fallback ); ?>
							<?php self::render_item_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'text_color', (string) ( $config['text_color'] ?? '' ), $item_text_fallback ); ?>
						</div></fieldset>
						<?php if ( ! empty( $submenu[ $slug ] ) ) : ?>
							<fieldset class="uamm-group-settings-section uamm-item-settings-section"><legend><?php esc_html_e( 'Expanded submenu', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Optional colors for the submenu panel shown when this item is active or expanded.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
								<?php self::render_item_color_field( __( 'Submenu background', 'uplink-admin-menu-manager' ), 'submenu_background', (string) ( $config['submenu_background'] ?? '' ), $submenu_background_fallback ); ?>
								<?php self::render_item_color_field( __( 'Submenu text', 'uplink-admin-menu-manager' ), 'submenu_text_color', (string) ( $config['submenu_text_color'] ?? '' ), $submenu_text_fallback ); ?>
								<?php self::render_item_color_field( __( 'Submenu hover and active background', 'uplink-admin-menu-manager' ), 'submenu_hover_background', (string) ( $config['submenu_hover_background'] ?? '' ), $submenu_hover_background_fallback ); ?>
								<?php self::render_item_color_field( __( 'Submenu hover and active text', 'uplink-admin-menu-manager' ), 'submenu_hover_text_color', (string) ( $config['submenu_hover_text_color'] ?? '' ), $submenu_hover_text_fallback ); ?>
							</div></fieldset>
						<?php endif; ?>
					</div>
					<div class="uamm-contrast-status" data-item-contrast aria-live="polite"></div>
				</div>
			</div>
		</li>
		<?php
	}

	private static function render_menu_icon_preview( string $icon, string $class_name ): void {
		if ( str_starts_with( $icon, 'dashicons-' ) ) {
			printf( '<span class="%1$s dashicons %2$s" aria-hidden="true"></span>', esc_attr( $class_name ), esc_attr( sanitize_html_class( $icon ) ) );
			return;
		}

		$icon_url = self::safe_menu_icon_url( $icon );
		if ( '' !== $icon_url ) {
			printf( '<span class="%1$s uamm-menu-icon-image" aria-hidden="true"><img src="%2$s" alt=""></span>', esc_attr( $class_name ), esc_url( $icon_url, array( 'http', 'https', 'data' ) ) );
			return;
		}

		printf( '<span class="%1$s dashicons dashicons-admin-generic" aria-hidden="true"></span>', esc_attr( $class_name ) );
	}

	private static function safe_menu_icon_url( string $icon ): string {
		if ( preg_match( '/^data:image\/svg\+xml;base64,[A-Za-z0-9+\/=]+$/', $icon ) ) {
			return $icon;
		}

		return esc_url_raw( $icon, array( 'http', 'https' ) );
	}

	private static function render_item_color_field( string $label, string $field, string $value, string $fallback ): void {
		$picker_value = sanitize_hex_color( $value ) ?: $fallback;
		?>
		<label class="uamm-item-color-field"><span><?php echo esc_html( $label ); ?></span><span class="uamm-inline-color"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $picker_value ); ?>;"><input data-item-color-picker="<?php echo esc_attr( $field ); ?>" type="color" value="<?php echo esc_attr( $picker_value ); ?>"></span><input data-field="<?php echo esc_attr( $field ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $fallback ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false"></span></label>
		<?php
	}

	private static function render_submenu_item( string $parent, array $child, array $profile ): void {
		$slug   = (string) ( $child[2] ?? '' );
		$key    = $parent . '::' . $slug;
		$config = is_array( $profile['items'][ $key ] ?? null ) ? $profile['items'][ $key ] : array();
		$title  = wp_strip_all_tags( (string) ( $child[0] ?? $slug ) );
		/* translators: %s: submenu item label. */
		$drag_label = sprintf( __( 'Drag %s to reorder', 'uplink-admin-menu-manager' ), $title );
		/* translators: %s: submenu item label. */
		$move_up_label = sprintf( __( 'Move %s up', 'uplink-admin-menu-manager' ), $title );
		/* translators: %s: submenu item label. */
		$move_down_label = sprintf( __( 'Move %s down', 'uplink-admin-menu-manager' ), $title );
		?><div class="uamm-submenu-row" data-submenu-key="<?php echo esc_attr( $key ); ?>"><button class="uamm-submenu-drag" type="button" aria-label="<?php echo esc_attr( $drag_label ); ?>"><span class="uamm-control-icon uamm-icon-drag" aria-hidden="true"></span></button><span class="uamm-submenu-row__title"><?php echo esc_html( $title ); ?></span><input data-subfield="title" type="text" value="<?php echo esc_attr( (string) ( $config['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Custom label', 'uplink-admin-menu-manager' ); ?>"><input data-subfield="parent" type="text" value="<?php echo esc_attr( (string) ( $config['parent'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'New parent slug', 'uplink-admin-menu-manager' ); ?>" aria-label="<?php esc_attr_e( 'Move to parent menu slug', 'uplink-admin-menu-manager' ); ?>"><label class="uamm-check"><input data-subfield="hidden" type="checkbox" <?php checked( ! empty( $config['hidden'] ) ); ?>><?php esc_html_e( 'Hidden', 'uplink-admin-menu-manager' ); ?></label><span class="uamm-submenu-row__actions"><button class="uamm-submenu-move-up" type="button" aria-label="<?php echo esc_attr( $move_up_label ); ?>"><span class="uamm-control-icon uamm-icon-move-up" aria-hidden="true"></span></button><button class="uamm-submenu-move-down" type="button" aria-label="<?php echo esc_attr( $move_down_label ); ?>"><span class="uamm-control-icon uamm-icon-move-down" aria-hidden="true"></span></button></span></div><?php
	}

	/**
	 * Show a saved submenu order in the editor without losing new plugin entries.
	 */
	private static function order_submenu_items_for_editor( string $parent, array $children, array $profile ): array {
		$saved_order = (array) ( $profile['submenus'][ $parent ] ?? array() );
		if ( ! $saved_order ) {
			return $children;
		}

		$by_key = array();
		foreach ( $children as $child ) {
			$key = $parent . '::' . (string) ( $child[2] ?? '' );
			$by_key[ $key ] = $child;
		}

		$saved_existing = array();
		foreach ( $saved_order as $key ) {
			$key = str_contains( (string) $key, '::' ) ? (string) $key : $parent . '::' . (string) $key;
			if ( isset( $by_key[ $key ] ) ) {
				$saved_existing[] = $key;
			}
		}

		$native_existing = array_values(
			array_filter(
				array_keys( $by_key ),
				static fn( $key ) => in_array( $key, $saved_existing, true )
			)
		);
		if ( $saved_existing === $native_existing ) {
			return $children;
		}

		$available = array_map( static fn( $key ) => $by_key[ $key ], $saved_existing );
		foreach ( $children as $index => $child ) {
			$key = $parent . '::' . (string) ( $child[2] ?? '' );
			if ( in_array( $key, $saved_existing, true ) && $available ) {
				$children[ $index ] = array_shift( $available );
			}
		}

		return $children;
	}

	private static function render_group_row( array $group, array $members, array $submenu, array $profile ): void {
		$id               = (string) ( $group['id'] ?? '' );
		$label            = (string) ( $group['label'] ?? '' );
		$icon             = (string) ( $group['icon'] ?? 'dashicons-category' );
		$background       = (string) ( $group['background'] ?? '' );
		$text_color       = (string) ( $group['text_color'] ?? '' );
		$border_color     = (string) ( $group['border_color'] ?? '' );
		$show_heading_border = ! empty( $group['show_heading_border'] );
		$hover_background = (string) ( $group['hover_background'] ?? '' );
		$hover_text_color = (string) ( $group['hover_text_color'] ?? '' );
		$collapsed_background = (string) ( $group['collapsed_background'] ?? '' );
		$collapsed_text_color = (string) ( $group['collapsed_text_color'] ?? '' );
		$collapsed_hover_background = (string) ( $group['collapsed_hover_background'] ?? '' );
		$collapsed_hover_text_color = (string) ( $group['collapsed_hover_text_color'] ?? '' );
		$panel_background = (string) ( $group['panel_background'] ?? '' );
		$panel_text_color  = (string) ( $group['panel_text_color'] ?? '' );
		$panel_border_color      = (string) ( $group['panel_border_color'] ?? '' );
		$show_panel_border       = ! empty( $group['show_panel_border'] );
		$pinned                  = ! empty( $group['pinned'] );
		$panel_hover_background = (string) ( $group['panel_hover_background'] ?? '' );
		$panel_hover_text_color  = (string) ( $group['panel_hover_text_color'] ?? '' );
		$global_style            = (array) Settings::get_all()['group_style'];
		/* translators: %s: number of menu items in the group. */
		$count_label = sprintf( _n( '%s item', '%s items', count( $members ), 'uplink-admin-menu-manager' ), number_format_i18n( count( $members ) ) );
		/* translators: %s: menu group label. */
		$items_label = sprintf( __( 'Menu items in %s', 'uplink-admin-menu-manager' ), $label );
		/* translators: %s: menu group label. */
		$collapse_label = sprintf( __( 'Collapse %s group', 'uplink-admin-menu-manager' ), $label );
		$items_id       = 'uamm-group-items-' . sanitize_html_class( $id );
		?>
		<li class="uamm-menu-group" data-group-id="<?php echo esc_attr( $id ); ?>">
			<div class="uamm-menu-group__summary"><button class="uamm-drag" type="button" aria-label="<?php esc_attr_e( 'Drag group to reorder', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-drag" aria-hidden="true"></span></button><span class="uamm-group-row-icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><strong data-group-summary-label><?php echo esc_html( $label ); ?></strong><span class="uamm-group-count"><?php echo esc_html( $count_label ); ?></span><span class="uamm-row-actions"><button class="uamm-move-up" type="button" aria-label="<?php esc_attr_e( 'Move group up', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-move-up" aria-hidden="true"></span></button><button class="uamm-move-down" type="button" aria-label="<?php esc_attr_e( 'Move group down', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-move-down" aria-hidden="true"></span></button><button class="uamm-group-collapse" type="button" aria-expanded="true" aria-controls="<?php echo esc_attr( $items_id ); ?>" aria-label="<?php echo esc_attr( $collapse_label ); ?>"><span class="uamm-control-icon uamm-icon-chevron" aria-hidden="true"></span></button><button class="uamm-group-disclosure" type="button" aria-expanded="false" aria-label="<?php esc_attr_e( 'Edit group settings', 'uplink-admin-menu-manager' ); ?>"><span class="uamm-control-icon uamm-icon-settings" aria-hidden="true"></span></button></span></div>
			<div class="uamm-menu-group__details" hidden>
				<div class="uamm-group-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Group settings', 'uplink-admin-menu-manager' ); ?>">
					<button id="<?php echo esc_attr( $items_id ); ?>-general-tab" class="uamm-group-settings-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="<?php echo esc_attr( $items_id ); ?>-general"><?php esc_html_e( 'General', 'uplink-admin-menu-manager' ); ?></button>
					<button id="<?php echo esc_attr( $items_id ); ?>-appearance-tab" class="uamm-group-settings-tab" type="button" role="tab" aria-selected="false" aria-controls="<?php echo esc_attr( $items_id ); ?>-appearance" tabindex="-1"><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></button>
				</div>
				<div id="<?php echo esc_attr( $items_id ); ?>-general" class="uamm-group-settings-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $items_id ); ?>-general-tab">
					<fieldset class="uamm-group-settings-section"><legend><?php esc_html_e( 'Group details', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Set the label and icon shown in the admin menu.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
						<label><?php esc_html_e( 'Group label', 'uplink-admin-menu-manager' ); ?><input data-group-field="label" type="text" value="<?php echo esc_attr( $label ); ?>"></label>
						<div class="uamm-icon-field"><label><span><?php esc_html_e( 'Dashicon', 'uplink-admin-menu-manager' ); ?></span><span class="uamm-icon-control"><span class="uamm-icon-field__preview dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><input data-group-field="icon" type="text" value="<?php echo esc_attr( $icon ); ?>"><button class="button uamm-open-dashicon-picker" type="button" aria-label="<?php esc_attr_e( 'Choose Dashicon', 'uplink-admin-menu-manager' ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></span></label></div>
					</div></fieldset>
					<fieldset class="uamm-group-settings-section"><legend><?php esc_html_e( 'Group behavior', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Choose the initial state for users who have not set their own preference.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-group-state-options">
						<label class="uamm-check"><input data-group-field="collapsed" type="checkbox" <?php checked( ! $pinned && ! empty( $group['collapsed'] ) ); ?> <?php disabled( $pinned ); ?>><span><?php esc_html_e( 'Start collapsed', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Users can still open the group from the admin menu.', 'uplink-admin-menu-manager' ); ?></small></span></label>
						<label class="uamm-check"><input data-group-field="pinned" type="checkbox" <?php checked( $pinned ); ?>><span><?php esc_html_e( 'Pin this group open by default', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Users can change the pin from the admin menu.', 'uplink-admin-menu-manager' ); ?></small></span></label>
					</div></fieldset>
				</div>
				<div id="<?php echo esc_attr( $items_id ); ?>-appearance" class="uamm-group-settings-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( $items_id ); ?>-appearance-tab" hidden>
					<div class="uamm-group-settings-sections">
						<fieldset class="uamm-group-settings-section"><legend><?php esc_html_e( 'Open heading', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors used while the group is expanded.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
					<?php
					self::render_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'background', $background, (string) $global_style['background'] );
					self::render_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'text_color', $text_color, (string) $global_style['text_color'] );
					self::render_group_color_field( __( 'Hover background', 'uplink-admin-menu-manager' ), 'hover_background', $hover_background, (string) $global_style['hover_background'] );
					self::render_group_color_field( __( 'Hover text and icon', 'uplink-admin-menu-manager' ), 'hover_text_color', $hover_text_color, (string) $global_style['hover_text_color'] );
					self::render_group_color_field( __( 'Border', 'uplink-admin-menu-manager' ), 'border_color', $border_color, (string) $global_style['border_color'] );
					?>
						<label class="uamm-check uamm-group-border-toggle"><input data-group-field="show_heading_border" type="checkbox" <?php checked( $show_heading_border ); ?>><span><?php esc_html_e( 'Show heading border', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Uses the border color shown beside this option.', 'uplink-admin-menu-manager' ); ?></small></span></label>
						</div></fieldset>
						<fieldset class="uamm-group-settings-section"><legend><?php esc_html_e( 'Collapsed heading', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors used while the group is closed.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
						<?php
						self::render_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'collapsed_background', $collapsed_background, (string) $global_style['collapsed_background'] );
						self::render_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'collapsed_text_color', $collapsed_text_color, (string) $global_style['collapsed_text_color'] );
						self::render_group_color_field( __( 'Hover background', 'uplink-admin-menu-manager' ), 'collapsed_hover_background', $collapsed_hover_background, (string) $global_style['collapsed_hover_background'] );
						self::render_group_color_field( __( 'Hover text and icon', 'uplink-admin-menu-manager' ), 'collapsed_hover_text_color', $collapsed_hover_text_color, (string) $global_style['collapsed_hover_text_color'] );
						?>
						</div></fieldset>
						<fieldset class="uamm-group-settings-section"><legend><?php esc_html_e( 'Item panel', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors used by top-level menu items inside this group.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
						<?php
						self::render_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'panel_background', $panel_background, (string) $global_style['panel_background'] );
						self::render_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'panel_text_color', $panel_text_color, (string) $global_style['panel_text_color'] );
						self::render_group_color_field( __( 'Hover and active background', 'uplink-admin-menu-manager' ), 'panel_hover_background', $panel_hover_background, (string) $global_style['panel_hover_background'] );
						self::render_group_color_field( __( 'Hover and active text and icon', 'uplink-admin-menu-manager' ), 'panel_hover_text_color', $panel_hover_text_color, (string) $global_style['panel_hover_text_color'] );
						self::render_group_color_field( __( 'Border', 'uplink-admin-menu-manager' ), 'panel_border_color', $panel_border_color, (string) $global_style['panel_border_color'] );
						?>
						<label class="uamm-check uamm-group-border-toggle"><input data-group-field="show_panel_border" type="checkbox" <?php checked( $show_panel_border ); ?>><span><?php esc_html_e( 'Show item panel border', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Uses the border color shown beside this option.', 'uplink-admin-menu-manager' ); ?></small></span></label>
						</div></fieldset>
					</div>
					<div class="uamm-contrast-status" data-group-contrast aria-live="polite"></div>
				</div>
				<button class="button-link-delete uamm-remove-group" type="button"><?php esc_html_e( 'Remove group and keep its menu items', 'uplink-admin-menu-manager' ); ?></button>
			</div>
			<ul id="<?php echo esc_attr( $items_id ); ?>" class="uamm-group-items" aria-label="<?php echo esc_attr( $items_label ); ?>">
				<?php foreach ( $members as $item ) { self::render_menu_item( $item, $submenu, $profile ); } ?>
			</ul>
		</li>
		<?php
	}

	private static function render_group_color_field( string $label, string $field, string $value, string $fallback ): void {
		$picker_value = sanitize_hex_color( $value ) ?: $fallback;
		?>
		<label class="uamm-group-color-field"><span><?php echo esc_html( $label ); ?></span><span class="uamm-inline-color"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $picker_value ); ?>;"><input data-group-color-picker="<?php echo esc_attr( $field ); ?>" type="color" value="<?php echo esc_attr( $picker_value ); ?>"></span><input data-group-field="<?php echo esc_attr( $field ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $fallback ); ?>"></span></label>
		<?php
	}

	private static function render_custom_row( array $custom ): void {
		$custom_icon    = (string) ( $custom['icon'] ?? 'dashicons-admin-links' );
		$custom_preview = str_starts_with( $custom_icon, 'dashicons-' ) ? $custom_icon : 'dashicons-admin-generic';
		?><div class="uamm-repeater" data-custom-id="<?php echo esc_attr( (string) ( $custom['id'] ?? '' ) ); ?>"><label><?php esc_html_e( 'Label', 'uplink-admin-menu-manager' ); ?><input data-custom-field="title" type="text" value="<?php echo esc_attr( (string) ( $custom['title'] ?? '' ) ); ?>"></label><label><?php esc_html_e( 'URL', 'uplink-admin-menu-manager' ); ?><input data-custom-field="url" type="url" value="<?php echo esc_attr( (string) ( $custom['url'] ?? '' ) ); ?>"></label><label><?php esc_html_e( 'Parent menu slug', 'uplink-admin-menu-manager' ); ?><input data-custom-field="parent" type="text" value="<?php echo esc_attr( (string) ( $custom['parent'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Blank for top level', 'uplink-admin-menu-manager' ); ?>"></label><label><?php esc_html_e( 'Open in', 'uplink-admin-menu-manager' ); ?><select data-custom-field="open_mode"><option value="same" <?php selected( $custom['open_mode'] ?? '', 'same' ); ?>><?php esc_html_e( 'Same window', 'uplink-admin-menu-manager' ); ?></option><option value="new" <?php selected( $custom['open_mode'] ?? '', 'new' ); ?>><?php esc_html_e( 'New tab', 'uplink-admin-menu-manager' ); ?></option><option value="iframe" <?php selected( $custom['open_mode'] ?? '', 'iframe' ); ?>><?php esc_html_e( 'Admin frame', 'uplink-admin-menu-manager' ); ?></option><option value="none" <?php selected( $custom['open_mode'] ?? '', 'none' ); ?>><?php esc_html_e( 'Non-clickable', 'uplink-admin-menu-manager' ); ?></option></select></label><label><?php esc_html_e( 'Capability', 'uplink-admin-menu-manager' ); ?><input data-custom-field="capability" type="text" value="<?php echo esc_attr( (string) ( $custom['capability'] ?? 'read' ) ); ?>"></label><div class="uamm-icon-field"><label><span><?php esc_html_e( 'Dashicon', 'uplink-admin-menu-manager' ); ?></span><span class="uamm-icon-control"><span class="uamm-icon-field__preview dashicons <?php echo esc_attr( $custom_preview ); ?>" aria-hidden="true"></span><input data-custom-field="icon" type="text" value="<?php echo esc_attr( $custom_icon ); ?>"><button class="button uamm-open-dashicon-picker" type="button" aria-label="<?php esc_attr_e( 'Choose Dashicon', 'uplink-admin-menu-manager' ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></span></label></div><button class="button-link-delete uamm-remove" type="button"><?php esc_html_e( 'Remove', 'uplink-admin-menu-manager' ); ?></button></div><?php
	}

	private static function render_access(): void {
		$settings = Settings::get_all();
		$roles    = wp_roles()->roles;
		$users    = array_filter(
			get_users( array( 'number' => 100, 'orderby' => 'display_name' ) ),
			static fn( $user ): bool => $user instanceof \WP_User && Settings::user_can_access_manager( $user )
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="uamm_save_site_settings">
			<input type="hidden" name="return_tab" value="access">
			<?php wp_nonce_field( 'uamm_save_site_settings' ); ?>
			<div class="uamm-layout">
				<main class="uamm-main"><section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Menu customization access', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Choose who may edit a personal menu profile and which menu other roles receive.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-user-list uamm-role-access-list">
					<?php foreach ( $roles as $role => $details ) : ?>
						<?php if ( ! empty( $details['capabilities']['manage_options'] ) ) { continue; } ?>
						<?php $has_access = in_array( $role, (array) $settings['menu_access_roles'], true ); ?>
						<?php $menu_mode = (string) ( $settings['menu_role_modes'][ $role ] ?? 'managed' ); ?>
						<div class="uamm-role-access-row" data-uamm-role-access-row>
							<label class="uamm-check"><input data-uamm-role-access type="checkbox" name="menu_access_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( $has_access ); ?>><span><?php echo esc_html( translate_user_role( $details['name'] ) ); ?><small><?php esc_html_e( 'Allow users in this role to edit their personal menu profile.', 'uplink-admin-menu-manager' ); ?></small></span></label>
							<label class="uamm-role-menu-mode"><span><?php esc_html_e( 'Menu shown without access', 'uplink-admin-menu-manager' ); ?></span><select data-uamm-role-menu-mode name="menu_role_modes[<?php echo esc_attr( $role ); ?>]" <?php disabled( $has_access ); ?>><option value="managed" <?php selected( $menu_mode, 'managed' ); ?>><?php esc_html_e( 'Admin-set menu', 'uplink-admin-menu-manager' ); ?></option><option value="wordpress" <?php selected( $menu_mode, 'wordpress' ); ?>><?php esc_html_e( 'WordPress default', 'uplink-admin-menu-manager' ); ?></option></select></label>
						</div>
					<?php endforeach; ?>
				</div><p class="uamm-compatibility-note"><?php esc_html_e( 'Roles with editing access always use the managed menu. Removing access deletes affected personal profiles unless they are protected.', 'uplink-admin-menu-manager' ); ?></p></section></main>
				<aside class="uamm-sidebar"><section class="uamm-card uamm-card--compact uamm-protected-users-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Protected user profiles', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Prevent another administrator from overwriting or deleting a selected user’s saved Menu Manager profile. Protected profiles are retained if a user later loses access.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-user-list"><?php foreach ( $users as $user ) : ?><label class="uamm-check"><input type="checkbox" name="protected_users[]" value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php checked( in_array( $user->ID, (array) $settings['protected_users'], true ) ); ?>><span><?php echo esc_html( $user->display_name ); ?><small><?php echo esc_html( $user->user_login ); ?></small></span></label><?php endforeach; ?></div></section></aside>
			</div>
			<p class="submit"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save user access', 'uplink-admin-menu-manager' ); ?></button></p>
		</form>
		<?php
	}

	private static function render_appearance(): void {
		$appearance = (array) Settings::get_all()['appearance'];
		$group_style = (array) Settings::get_all()['group_style'];
		$main_menu_colors = array(
			'background' => __( 'Background', 'uplink-admin-menu-manager' ),
			'text'       => __( 'Text', 'uplink-admin-menu-manager' ),
			'icon'       => __( 'Icons', 'uplink-admin-menu-manager' ),
			'highlight'  => __( 'Current and hover', 'uplink-admin-menu-manager' ),
		);
		$plugin_submenu_colors = array(
			'submenu_background'       => __( 'Background', 'uplink-admin-menu-manager' ),
			'submenu_text'             => __( 'Text', 'uplink-admin-menu-manager' ),
			'submenu_hover_background' => __( 'Hover and focus background', 'uplink-admin-menu-manager' ),
			'submenu_hover_text'       => __( 'Hover and focus text', 'uplink-admin-menu-manager' ),
		);
		/* translators: %s: color setting label. */
		$hex_value_label = __( '%s hex value', 'uplink-admin-menu-manager' );

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-global-group-style-form>
			<input type="hidden" name="action" value="uamm_save_site_settings"><input type="hidden" name="return_tab" value="appearance"><?php wp_nonce_field( 'uamm_save_site_settings' ); ?>
			<div class="uamm-inspector-layout">
				<nav class="uamm-inspector-nav" role="tablist" aria-label="<?php esc_attr_e( 'Appearance settings', 'uplink-admin-menu-manager' ); ?>">
					<button id="uamm-appearance-tab-layout" class="uamm-appearance-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="uamm-appearance-layout" tabindex="0"><img src="<?php echo esc_url( UAMM_URL . 'assets/lucide/layout-dashboard.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true"><?php esc_html_e( 'Layout', 'uplink-admin-menu-manager' ); ?></button>
					<button id="uamm-appearance-tab-headings" class="uamm-appearance-tab" type="button" role="tab" aria-selected="false" aria-controls="uamm-appearance-headings" tabindex="-1"><img src="<?php echo esc_url( UAMM_URL . 'assets/lucide/type.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true"><?php esc_html_e( 'Group headings', 'uplink-admin-menu-manager' ); ?></button>
					<button id="uamm-appearance-tab-menu-colors" class="uamm-appearance-tab" type="button" role="tab" aria-selected="false" aria-controls="uamm-appearance-menu-colors" tabindex="-1"><img src="<?php echo esc_url( UAMM_URL . 'assets/lucide/palette.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true"><?php esc_html_e( 'Menu colors', 'uplink-admin-menu-manager' ); ?></button>
					<button id="uamm-appearance-tab-group-colors" class="uamm-appearance-tab" type="button" role="tab" aria-selected="false" aria-controls="uamm-appearance-group-colors" tabindex="-1"><img src="<?php echo esc_url( UAMM_URL . 'assets/lucide/folders.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true"><?php esc_html_e( 'Group colors', 'uplink-admin-menu-manager' ); ?></button>
				</nav>
				<div class="uamm-inspector-content">
				<section id="uamm-appearance-layout" class="uamm-inspector-section" role="tabpanel" aria-labelledby="uamm-appearance-tab-layout"><header class="uamm-inspector-header"><span><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></span><h2><?php esc_html_e( 'Layout', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Set the menu width, flyout spacing, and touch target size. Changes preview in the admin menu immediately.', 'uplink-admin-menu-manager' ); ?></p></header><div class="uamm-inspector-body">
			<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Menu dimensions', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Control the desktop sidebar width and the gap before flyout submenus.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-menu-width-control"><input data-menu-width-range type="range" min="140" max="320" step="1" value="<?php echo esc_attr( (string) $appearance['menu_width'] ); ?>" aria-label="<?php esc_attr_e( 'Admin menu width slider', 'uplink-admin-menu-manager' ); ?>"><label for="uamm-menu-width"><?php esc_html_e( 'Menu width', 'uplink-admin-menu-manager' ); ?><span><input id="uamm-menu-width" data-menu-width-number type="number" name="menu_width" min="140" max="320" step="1" value="<?php echo esc_attr( (string) $appearance['menu_width'] ); ?>"> px</span></label></div><p class="uamm-compatibility-note"><?php esc_html_e( 'Some plugins hard-code their admin layouts, so this width may not apply everywhere.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-control-section"><h3><?php esc_html_e( 'Flyout spacing', 'uplink-admin-menu-manager' ); ?></h3><div class="uamm-group-layout-controls">
				<?php self::render_inspector_range( __( 'Distance from menu', 'uplink-admin-menu-manager' ), __( 'Zero places the flyout against the menu edge. Increase the value to add a gap.', 'uplink-admin-menu-manager' ), 'submenu_edge_distance', 0, 24, 1, (string) $appearance['submenu_edge_distance'] ); ?>
			</div></div></section>
			<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Touch targets', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Make menu links easier to tap on touch devices.', 'uplink-admin-menu-manager' ); ?></p></div><input type="hidden" name="touch_optimized_menu" value="0"><label class="uamm-check"><input type="checkbox" name="touch_optimized_menu" value="1" <?php checked( ! empty( $appearance['touch_optimized_menu'] ) ); ?>><span><?php esc_html_e( 'Use 44 px touch targets', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Applies a 44-pixel minimum height to top-level items, group headings, and submenu links.', 'uplink-admin-menu-manager' ); ?></small></span></label></section>
				</div></section>
				<section id="uamm-appearance-headings" class="uamm-inspector-section" role="tabpanel" aria-labelledby="uamm-appearance-tab-headings" hidden><header class="uamm-inspector-header"><span><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></span><h2><?php esc_html_e( 'Group headings', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Adjust the spacing, corner shape, and typography of every collapsible group heading.', 'uplink-admin-menu-manager' ); ?></p></header><div class="uamm-inspector-body">
			<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Spacing and shape', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Position group headings and control how rounded they appear.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-group-layout-controls">
				<?php self::render_inspector_range( __( 'Side margin', 'uplink-admin-menu-manager' ), __( 'Space at the left and right of each group.', 'uplink-admin-menu-manager' ), 'group_margin_inline', 0, 32, 1, (string) $appearance['group_margin_inline'] ); ?>
				<?php self::render_inspector_range( __( 'Vertical margin', 'uplink-admin-menu-manager' ), __( 'Space above and below each group heading.', 'uplink-admin-menu-manager' ), 'group_margin_block', 0, 32, 1, (string) $appearance['group_margin_block'] ); ?>
				<?php self::render_inspector_range( __( 'Corner radius', 'uplink-admin-menu-manager' ), __( 'Use zero for square, flush-looking corners.', 'uplink-admin-menu-manager' ), 'group_border_radius', 0, 32, 1, (string) $appearance['group_border_radius'] ); ?>
			</div></section>
			<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Typography', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Set the size, weight, spacing, and letter case of group labels.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-group-layout-controls">
				<?php self::render_inspector_range( __( 'Font size', 'uplink-admin-menu-manager' ), __( 'Size of the group heading label.', 'uplink-admin-menu-manager' ), 'group_heading_font_size', 9, 24, 1, (string) $appearance['group_heading_font_size'] ); ?>
				<?php self::render_inspector_range( __( 'Letter spacing', 'uplink-admin-menu-manager' ), __( 'Space between letters in the label.', 'uplink-admin-menu-manager' ), 'group_heading_letter_spacing', 0, 4, 0.1, (string) $appearance['group_heading_letter_spacing'] ); ?>
			</div><div class="uamm-choice-control"><span class="uamm-control-label"><span class="uamm-type-symbol" aria-hidden="true">Aa</span><span><?php esc_html_e( 'Font weight', 'uplink-admin-menu-manager' ); ?></span></span><small><?php esc_html_e( 'Weight of the group heading label.', 'uplink-admin-menu-manager' ); ?></small><div class="uamm-segmented-control"><?php foreach ( array( 400 => __( 'Regular', 'uplink-admin-menu-manager' ), 500 => __( 'Medium', 'uplink-admin-menu-manager' ), 600 => __( 'Semibold', 'uplink-admin-menu-manager' ), 700 => __( 'Bold', 'uplink-admin-menu-manager' ), 800 => __( 'Extra bold', 'uplink-admin-menu-manager' ), 900 => __( 'Black', 'uplink-admin-menu-manager' ) ) as $weight => $weight_label ) : ?><label><input type="radio" name="group_heading_font_weight" value="<?php echo esc_attr( (string) $weight ); ?>" <?php checked( (int) $appearance['group_heading_font_weight'], $weight ); ?>><span><?php echo esc_html( $weight_label ); ?></span></label><?php endforeach; ?></div></div>
				<div class="uamm-choice-control"><span class="uamm-control-label"><span class="uamm-type-symbol" aria-hidden="true">Aa</span><span><?php esc_html_e( 'Letter case', 'uplink-admin-menu-manager' ); ?></span></span><small><?php esc_html_e( 'Choose how the group label is capitalized.', 'uplink-admin-menu-manager' ); ?></small><div class="uamm-segmented-control uamm-segmented-control--caps"><label><input type="radio" name="group_heading_text_transform" value="none" <?php checked( (string) $appearance['group_heading_text_transform'], 'none' ); ?>><span><b>Aa</b><?php esc_html_e( 'As typed', 'uplink-admin-menu-manager' ); ?></span></label><label><input type="radio" name="group_heading_text_transform" value="capitalize" <?php checked( (string) $appearance['group_heading_text_transform'], 'capitalize' ); ?>><span><b>Aa</b><?php esc_html_e( 'Words', 'uplink-admin-menu-manager' ); ?></span></label><label><input type="radio" name="group_heading_text_transform" value="uppercase" <?php checked( (string) $appearance['group_heading_text_transform'], 'uppercase' ); ?>><span><b>AA</b><?php esc_html_e( 'Uppercase', 'uplink-admin-menu-manager' ); ?></span></label></div></div></section>
				</div></section>
				<section id="uamm-appearance-menu-colors" class="uamm-inspector-section" role="tabpanel" aria-labelledby="uamm-appearance-tab-menu-colors" hidden><header class="uamm-inspector-header"><span><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></span><h2><?php esc_html_e( 'Menu colors', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Set defaults for the main menu and plugin flyouts. Every saved color pair must pass its contrast check.', 'uplink-admin-menu-manager' ); ?></p></header><div class="uamm-inspector-body">
			<section class="uamm-card" data-admin-menu-colors><div class="uamm-color-setting-groups">
				<fieldset class="uamm-color-setting-group"><legend><?php esc_html_e( 'Main menu', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors for top-level menu rows and their current or hover state.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-color-grid"><?php foreach ( $main_menu_colors as $key => $label ) : ?><?php $picker_value = sanitize_hex_color( (string) $appearance[ $key ] ) ?: '#000000'; ?><label><span class="uamm-color-label"><?php echo esc_html( $label ); ?></span><span class="uamm-modern-color-control"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $picker_value ); ?>;"><input data-appearance-color-picker="<?php echo esc_attr( $key ); ?>" type="color" name="appearance[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $picker_value ); ?>"></span><input data-appearance-field="<?php echo esc_attr( $key ); ?>" type="text" name="appearance_text[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $appearance[ $key ] ); ?>" placeholder="#000000" aria-label="<?php echo esc_attr( sprintf( $hex_value_label, $label ) ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false"></span></label><?php endforeach; ?></div></fieldset>
				<fieldset class="uamm-color-setting-group"><legend><?php esc_html_e( 'Plugin flyouts', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Default colors for submenus opened by ordinary plugin menu items. Item-level colors still take priority.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-color-grid"><?php foreach ( $plugin_submenu_colors as $key => $label ) : ?><?php $picker_value = sanitize_hex_color( (string) $appearance[ $key ] ) ?: '#000000'; ?><label><span class="uamm-color-label"><?php echo esc_html( $label ); ?></span><span class="uamm-modern-color-control"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $picker_value ); ?>;"><input data-appearance-color-picker="<?php echo esc_attr( $key ); ?>" type="color" name="appearance[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $picker_value ); ?>"></span><input data-appearance-field="<?php echo esc_attr( $key ); ?>" type="text" name="appearance_text[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $appearance[ $key ] ); ?>" placeholder="#000000" aria-label="<?php echo esc_attr( sprintf( $hex_value_label, $label ) ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false"></span></label><?php endforeach; ?></div></fieldset>
			</div><div class="uamm-contrast-status" data-admin-menu-contrast aria-live="polite"></div></section>
			<section class="uamm-card" data-menu-row-details><div class="uamm-card__header"><h2><?php esc_html_e( 'Menu details', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Add subtle dividers between menu links and identify items that open a submenu.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-color-grid">
				<?php $divider_color = sanitize_hex_color( (string) $appearance['divider_color'] ) ?: '#ffffff'; ?>
				<label><span class="uamm-color-label"><?php esc_html_e( 'Divider color', 'uplink-admin-menu-manager' ); ?></span><span class="uamm-modern-color-control"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $divider_color ); ?>;"><input data-appearance-color-picker="divider_color" type="color" name="appearance[divider_color]" value="<?php echo esc_attr( $divider_color ); ?>"></span><input data-appearance-field="divider_color" type="text" name="appearance_text[divider_color]" value="<?php echo esc_attr( (string) $appearance['divider_color'] ); ?>" placeholder="#ffffff" aria-label="<?php esc_attr_e( 'Divider color hexadecimal value', 'uplink-admin-menu-manager' ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false"></span></label>
			</div><div class="uamm-group-layout-controls">
				<?php self::render_inspector_range( __( 'Divider opacity', 'uplink-admin-menu-manager' ), __( 'Zero hides the divider. The default is a subtle 13 percent.', 'uplink-admin-menu-manager' ), 'divider_opacity', 0, 100, 1, (string) $appearance['divider_opacity'], '%' ); ?>
			</div><input type="hidden" name="submenu_indicators" value="0"><label class="uamm-check"><input type="checkbox" name="submenu_indicators" value="1" <?php checked( ! empty( $appearance['submenu_indicators'] ) ); ?>><img class="uamm-check__icon" src="<?php echo esc_url( UAMM_URL . 'assets/menu-collapse-solid-sharp.svg' ); ?>" alt="" width="20" height="20" aria-hidden="true"><span><?php esc_html_e( 'Show submenu indicators', 'uplink-admin-menu-manager' ); ?><small><?php esc_html_e( 'Shows a quiet marker on top-level items that contain submenu links. It fades when the submenu opens.', 'uplink-admin-menu-manager' ); ?></small></span></label></section>
				</div></section>
				<section id="uamm-appearance-group-colors" class="uamm-inspector-section" role="tabpanel" aria-labelledby="uamm-appearance-tab-group-colors" hidden><header class="uamm-inspector-header"><span><?php esc_html_e( 'Appearance', 'uplink-admin-menu-manager' ); ?></span><h2><?php esc_html_e( 'Group colors', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Set default colors for group headings and the menu items inside them. Individual group colors still take priority.', 'uplink-admin-menu-manager' ); ?></p></header><div class="uamm-inspector-body">
			<section class="uamm-card" data-global-group-style><div class="uamm-group-color-sections">
				<fieldset class="uamm-group-color-section"><legend><?php esc_html_e( 'Group heading', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Separate colors for open, collapsed, and hover states.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
					<?php
					echo '<h4>' . esc_html__( 'Open', 'uplink-admin-menu-manager' ) . '</h4>';
					self::render_global_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'background', (string) $group_style['background'] );
					self::render_global_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'text_color', (string) $group_style['text_color'] );
					self::render_global_group_color_field( __( 'Border', 'uplink-admin-menu-manager' ), 'border_color', (string) $group_style['border_color'] );
					self::render_global_group_color_field( __( 'Hover background', 'uplink-admin-menu-manager' ), 'hover_background', (string) $group_style['hover_background'] );
					self::render_global_group_color_field( __( 'Hover text and icon', 'uplink-admin-menu-manager' ), 'hover_text_color', (string) $group_style['hover_text_color'] );
					echo '<h4>' . esc_html__( 'Collapsed', 'uplink-admin-menu-manager' ) . '</h4>';
					self::render_global_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'collapsed_background', (string) $group_style['collapsed_background'] );
					self::render_global_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'collapsed_text_color', (string) $group_style['collapsed_text_color'] );
					self::render_global_group_color_field( __( 'Hover background', 'uplink-admin-menu-manager' ), 'collapsed_hover_background', (string) $group_style['collapsed_hover_background'] );
					self::render_global_group_color_field( __( 'Hover text and icon', 'uplink-admin-menu-manager' ), 'collapsed_hover_text_color', (string) $group_style['collapsed_hover_text_color'] );
					?>
				</div></fieldset>
				<fieldset class="uamm-group-color-section"><legend><?php esc_html_e( 'Item panel', 'uplink-admin-menu-manager' ); ?></legend><p><?php esc_html_e( 'Colors for top-level menu items inside a group. Nested submenu rows use the plugin submenu colors.', 'uplink-admin-menu-manager' ); ?></p><div class="uamm-field-grid">
					<?php
					self::render_global_group_color_field( __( 'Background', 'uplink-admin-menu-manager' ), 'panel_background', (string) $group_style['panel_background'] );
					self::render_global_group_color_field( __( 'Text and icon', 'uplink-admin-menu-manager' ), 'panel_text_color', (string) $group_style['panel_text_color'] );
					self::render_global_group_color_field( __( 'Border', 'uplink-admin-menu-manager' ), 'panel_border_color', (string) $group_style['panel_border_color'] );
					self::render_global_group_color_field( __( 'Hover and active background', 'uplink-admin-menu-manager' ), 'panel_hover_background', (string) $group_style['panel_hover_background'] );
					self::render_global_group_color_field( __( 'Hover and active text and icon', 'uplink-admin-menu-manager' ), 'panel_hover_text_color', (string) $group_style['panel_hover_text_color'] );
					?>
				</div></fieldset>
			</div><div class="uamm-contrast-status" data-group-contrast aria-live="polite"></div></section>
				</div></section>
					<div id="uamm-global-contrast-summary" class="uamm-form-message" role="alert" tabindex="-1" hidden></div>
					<div class="uamm-card uamm-appearance-savebar"><button class="button button-primary" type="submit"><?php esc_html_e( 'Save appearance', 'uplink-admin-menu-manager' ); ?></button><button class="button" type="submit" form="uamm-reset-appearance-form"><?php esc_html_e( 'Reset appearance to defaults', 'uplink-admin-menu-manager' ); ?></button><span id="uamm-global-save-contrast-state" class="uamm-save-state" aria-live="polite"></span></div>
				</div>
			</div>
		</form>
		<form id="uamm-reset-appearance-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-uamm-reset data-uamm-appearance-reset>
			<input type="hidden" name="action" value="uamm_reset_appearance">
			<?php wp_nonce_field( 'uamm_reset_appearance' ); ?>
		</form>
		<?php
	}

	private static function render_global_group_color_field( string $label, string $field, string $value ): void {
		$picker_value = sanitize_hex_color( $value ) ?: '#000000';
		?>
		<label class="uamm-group-color-field"><span><?php echo esc_html( $label ); ?></span><span class="uamm-inline-color"><span class="uamm-color-swatch" style="--uamm-swatch-color: <?php echo esc_attr( $picker_value ); ?>;"><input data-group-color-picker="<?php echo esc_attr( $field ); ?>" type="color" name="group_style[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $picker_value ); ?>"></span><input data-group-field="<?php echo esc_attr( $field ); ?>" type="text" name="group_style_text[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $picker_value ); ?>" pattern="#[0-9A-Fa-f]{6}" maxlength="7" spellcheck="false"></span></label>
		<?php
	}

	private static function render_inspector_range( string $label, string $description, string $field, float $min, float $max, float $step, string $value, string $unit = 'px' ): void {
		$icons = array(
			'submenu_edge_distance'         => 'assets/lucide/move-horizontal.svg',
			'group_margin_inline'          => 'assets/lucide/fold-horizontal.svg',
			'group_margin_block'           => 'assets/lucide/fold-vertical.svg',
			'group_border_radius'          => 'assets/lucide/radius.svg',
			'group_heading_font_size'      => 'assets/lucide/type.svg',
			'group_heading_letter_spacing' => 'assets/letter-spacing-stroke-sharp.svg',
			'divider_opacity'             => 'assets/lucide/separator-horizontal.svg',
		);
		$icon = $icons[ $field ] ?? 'assets/lucide/type.svg';
		?>
		<label class="uamm-inspector-control" for="uamm-<?php echo esc_attr( $field ); ?>"><span class="uamm-control-label"><img src="<?php echo esc_url( UAMM_URL . $icon ); ?>" alt="" width="20" height="20" aria-hidden="true"><span><?php echo esc_html( $label ); ?></span></span><small><?php echo esc_html( $description ); ?></small><span class="uamm-inspector-control__value"><input id="uamm-<?php echo esc_attr( $field ); ?>" data-uamm-number="<?php echo esc_attr( $field ); ?>" type="number" name="<?php echo esc_attr( $field ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="<?php echo esc_attr( (string) $step ); ?>" value="<?php echo esc_attr( $value ); ?>"><span aria-hidden="true"><?php echo esc_html( $unit ); ?></span></span><input data-uamm-range="<?php echo esc_attr( $field ); ?>" type="range" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="<?php echo esc_attr( (string) $step ); ?>" value="<?php echo esc_attr( $value ); ?>" aria-label="<?php echo esc_attr( $label ); ?>">		</label>
		<?php
	}

	private static function render_tools(): void {
		?>
		<div class="uamm-layout">
			<main class="uamm-main">
				<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Export settings', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Choose whether to move the complete configuration or only its visual presentation.', 'uplink-admin-menu-manager' ); ?></p></div><div class="uamm-transfer-options">
					<div><h3><?php esc_html_e( 'All settings', 'uplink-admin-menu-manager' ); ?></h3><p><?php esc_html_e( 'Includes profiles, menu order, groups, appearance settings, and protected users.', 'uplink-admin-menu-manager' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="uamm_export"><input type="hidden" name="export_scope" value="all"><?php wp_nonce_field( 'uamm_export' ); ?><button class="button button-primary" type="submit"><?php esc_html_e( 'Download all settings', 'uplink-admin-menu-manager' ); ?></button></form></div>
					<div><h3><?php esc_html_e( 'Look and feel only', 'uplink-admin-menu-manager' ); ?></h3><p><?php esc_html_e( 'Includes menu width, global colors, and matching menu item and group presentation. It excludes menu structure and protected users.', 'uplink-admin-menu-manager' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="uamm_export"><input type="hidden" name="export_scope" value="look_and_feel"><?php wp_nonce_field( 'uamm_export' ); ?><button class="button" type="submit"><?php esc_html_e( 'Download look and feel', 'uplink-admin-menu-manager' ); ?></button></form></div>
				</div></section>
				<section class="uamm-card"><div class="uamm-card__header"><h2><?php esc_html_e( 'Configuration retention', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Deactivating or deleting the plugin preserves its configuration. WordPress returns to its normal admin menu while the plugin is inactive.', 'uplink-admin-menu-manager' ); ?></p></div></section>
			</main>
			<aside class="uamm-sidebar">
				<section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Import all settings', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Replaces every current Menu Manager setting. A look-and-feel-only export cannot be imported here.', 'uplink-admin-menu-manager' ); ?></p><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="uamm_import"><input type="hidden" name="import_scope" value="all"><?php wp_nonce_field( 'uamm_import' ); ?><label for="uamm-import-all-file"><?php esc_html_e( 'Complete JSON export file', 'uplink-admin-menu-manager' ); ?></label><input id="uamm-import-all-file" type="file" name="import_file" accept="application/json,.json" required><button class="button" type="submit"><?php esc_html_e( 'Import and replace all settings', 'uplink-admin-menu-manager' ); ?></button></form></section>
				<section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Import look and feel', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Updates presentation only. Existing menu order, groups, permissions, and protected users remain in place.', 'uplink-admin-menu-manager' ); ?></p><form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="uamm_import"><input type="hidden" name="import_scope" value="look_and_feel"><?php wp_nonce_field( 'uamm_import' ); ?><label for="uamm-import-look-file"><?php esc_html_e( 'Look and feel JSON export file', 'uplink-admin-menu-manager' ); ?></label><input id="uamm-import-look-file" type="file" name="import_file" accept="application/json,.json" required><button class="button" type="submit"><?php esc_html_e( 'Import look and feel', 'uplink-admin-menu-manager' ); ?></button></form></section>
				<section class="uamm-card uamm-card--compact"><h2><?php esc_html_e( 'Clear configuration', 'uplink-admin-menu-manager' ); ?></h2><p><?php esc_html_e( 'Permanently remove every saved profile, group, appearance setting, and access rule from this site.', 'uplink-admin-menu-manager' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-uamm-clear><input type="hidden" name="action" value="uamm_clear_settings"><?php wp_nonce_field( 'uamm_clear_settings' ); ?><button class="button button-link-delete" type="submit"><?php esc_html_e( 'Clear stored configuration', 'uplink-admin-menu-manager' ); ?></button></form></section>
			</aside>
		</div>
		<?php
	}

	private static function profile_select( string $selected ): void {
		?><select id="uamm-profile" name="profile"><?php self::profile_option( 'default', __( 'All users (default)', 'uplink-admin-menu-manager' ), $selected ); ?><optgroup label="<?php esc_attr_e( 'Roles', 'uplink-admin-menu-manager' ); ?>"><?php foreach ( wp_roles()->get_names() as $role => $label ) { self::profile_option( 'role:' . $role, translate_user_role( $label ), $selected ); } ?></optgroup><optgroup label="<?php esc_attr_e( 'Users', 'uplink-admin-menu-manager' ); ?>"><?php foreach ( get_users( array( 'number' => 100, 'orderby' => 'display_name' ) ) as $user ) { self::profile_option( 'user:' . $user->ID, $user->display_name . ' (' . $user->user_login . ')', $selected ); } ?></optgroup></select><?php
	}

	private static function profile_option( string $value, string $label, string $selected ): void {
		if ( Settings::is_profile_write_protected( $value ) ) {
			$label .= ' — ' . __( 'protected', 'uplink-admin-menu-manager' );
		}
		printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
	}

	private static function selected_profile_key(): string {
		if ( ! Settings::user_has_full_access() ) {
			return 'user:' . get_current_user_id();
		}
		$key = sanitize_text_field( wp_unslash( (string) ( $_GET['profile'] ?? 'default' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return Settings::is_valid_profile_key( $key ) ? $key : 'default';
	}

	public static function save_profile(): void {
		self::guard();
		check_admin_referer( 'uamm_save_profile' );
		$key = sanitize_text_field( wp_unslash( (string) ( $_POST['profile_key'] ?? 'default' ) ) );
		if ( ! Settings::user_has_full_access() && 'user:' . get_current_user_id() !== $key ) {
			self::redirect( 'menus', false );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Settings::save_profile() sanitizes every decoded field.
		$decoded = json_decode( wp_unslash( (string) ( $_POST['profile_json'] ?? '' ) ), true );
		if ( ! is_array( $decoded ) || ! Settings::is_valid_profile_key( $key ) ) {
			self::redirect( 'menus', false, array( 'profile' => $key ) );
		}
		$saved = Settings::save_profile( $key, $decoded );
		self::redirect( 'menus', $saved, array( 'profile' => $key ) );
	}

	public static function delete_profile(): void {
		self::guard();
		check_admin_referer( 'uamm_delete_profile' );
		$key = sanitize_text_field( wp_unslash( (string) ( $_POST['profile_key'] ?? '' ) ) );
		if ( ! Settings::user_has_full_access() && 'user:' . get_current_user_id() !== $key ) {
			self::redirect( 'menus', false );
		}
		$deleted = Settings::is_valid_profile_key( $key ) && 'default' !== $key && Settings::delete_profile( $key );
		self::redirect( 'menus', $deleted, array( 'profile' => $key ) );
	}

	public static function save_site_settings(): void {
		self::guard_full_access();
		check_admin_referer( 'uamm_save_site_settings' );
		$current = Settings::get_all();
		$input   = $current;

		if ( isset( $_POST['appearance'] ) || isset( $_POST['appearance_text'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every submitted value passes through sanitize_hex_color() below.
			$raw_colors = isset( $_POST['appearance_text'] ) ? (array) wp_unslash( $_POST['appearance_text'] ) : (array) wp_unslash( $_POST['appearance'] );
			$colors     = array_map( 'sanitize_hex_color', $raw_colors );
			$input['appearance'] = array_merge( (array) $current['appearance'], $colors );
		}
		if ( isset( $_POST['menu_width'] ) ) {
			$input['appearance']['menu_width'] = absint( wp_unslash( $_POST['menu_width'] ) );
		}
		if ( isset( $_POST['submenu_edge_distance'] ) ) {
			$input['appearance']['submenu_edge_distance'] = absint( wp_unslash( $_POST['submenu_edge_distance'] ) );
		}
		if ( isset( $_POST['touch_optimized_menu'] ) ) {
			$input['appearance']['touch_optimized_menu'] = ! empty( absint( wp_unslash( $_POST['touch_optimized_menu'] ) ) );
		}
		if ( isset( $_POST['divider_opacity'] ) ) {
			$input['appearance']['divider_opacity'] = min( 100, absint( wp_unslash( $_POST['divider_opacity'] ) ) );
		}
		if ( isset( $_POST['submenu_indicators'] ) ) {
			$input['appearance']['submenu_indicators'] = ! empty( absint( wp_unslash( $_POST['submenu_indicators'] ) ) );
		}
		foreach ( array( 'group_margin_inline', 'group_margin_block', 'group_border_radius' ) as $layout_key ) {
			if ( isset( $_POST[ $layout_key ] ) ) {
				$input['appearance'][ $layout_key ] = absint( wp_unslash( $_POST[ $layout_key ] ) );
			}
		}
		if ( isset( $_POST['group_heading_font_size'] ) ) {
			$input['appearance']['group_heading_font_size'] = absint( wp_unslash( $_POST['group_heading_font_size'] ) );
		}
		if ( isset( $_POST['group_heading_font_weight'] ) ) {
			$input['appearance']['group_heading_font_weight'] = absint( wp_unslash( $_POST['group_heading_font_weight'] ) );
		}
		if ( isset( $_POST['group_heading_letter_spacing'] ) ) {
			$input['appearance']['group_heading_letter_spacing'] = (float) sanitize_text_field( wp_unslash( $_POST['group_heading_letter_spacing'] ) );
		}
		if ( isset( $_POST['group_heading_text_transform'] ) ) {
			$input['appearance']['group_heading_text_transform'] = sanitize_key( wp_unslash( $_POST['group_heading_text_transform'] ) );
		}
		if ( isset( $_POST['group_style'] ) || isset( $_POST['group_style_text'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Settings::save_site_settings() sanitizes every submitted group color.
			$input['group_style'] = isset( $_POST['group_style_text'] ) ? (array) wp_unslash( $_POST['group_style_text'] ) : (array) wp_unslash( $_POST['group_style'] );
		}
		$return_tab = sanitize_key( wp_unslash( (string) ( $_POST['return_tab'] ?? 'menus' ) ) );
		if ( 'access' === $return_tab ) {
			$input['menu_access_roles'] = array_map( 'sanitize_key', (array) wp_unslash( $_POST['menu_access_roles'] ?? array() ) );
			$input['menu_role_modes'] = array_merge( (array) $current['menu_role_modes'], array_map( 'sanitize_key', (array) wp_unslash( $_POST['menu_role_modes'] ?? array() ) ) );
			$visible_user_ids = array_map(
				static fn( $user ): int => (int) $user->ID,
				array_filter(
					get_users( array( 'number' => 100 ) ),
					static fn( $user ): bool => $user instanceof \WP_User && Settings::user_can_access_manager( $user )
				)
			);
			$hidden_protected = array_diff( (array) $current['protected_users'], $visible_user_ids );
			$submitted_protected = array_map( 'absint', (array) wp_unslash( $_POST['protected_users'] ?? array() ) );
			$input['protected_users'] = array_values( array_unique( array_merge( $hidden_protected, $submitted_protected ) ) );
		}
		Settings::save_site_settings( $input );
		self::redirect( $return_tab, true );
	}

	public static function reset_appearance(): void {
		self::guard_full_access();
		check_admin_referer( 'uamm_reset_appearance' );
		Settings::reset_appearance();
		self::redirect( 'appearance', true, array( 'uamm-appearance-reset' => 1 ) );
	}

	public static function export_settings(): void {
		self::guard_full_access();
		check_admin_referer( 'uamm_export' );
		$scope  = sanitize_key( wp_unslash( (string) ( $_POST['export_scope'] ?? 'all' ) ) );
		$is_look_and_feel = 'look_and_feel' === $scope;
		$export = $is_look_and_feel ? Settings::get_look_and_feel_export() : array_merge( Settings::get_all(), array( 'export_type' => 'all' ) );
		$suffix = $is_look_and_feel ? 'look-and-feel-' : 'all-settings-';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="uplink-admin-menu-manager-' . $suffix . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function import_settings(): void {
		self::guard_full_access();
		check_admin_referer( 'uamm_import' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The upload is validated before its temporary path is read.
		$file = $_FILES['import_file'] ?? null;
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || (int) ( $file['size'] ?? 0 ) > 1048576 || 'json' !== strtolower( pathinfo( sanitize_file_name( (string) ( $file['name'] ?? '' ) ), PATHINFO_EXTENSION ) ) ) {
			self::redirect( 'tools', false );
		}
		if ( ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			self::redirect( 'tools', false );
		}
		$contents = file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded  = json_decode( (string) $contents, true );
		if ( ! is_array( $decoded ) ) {
			self::redirect( 'tools', false );
		}
		$scope = sanitize_key( wp_unslash( (string) ( $_POST['import_scope'] ?? 'all' ) ) );
		if ( 'look_and_feel' === $scope ) {
			Settings::import_look_and_feel( $decoded );
		} else {
			if ( 'look_and_feel' === sanitize_key( (string) ( $decoded['export_type'] ?? '' ) ) || ! isset( $decoded['profiles'] ) ) {
				self::redirect( 'tools', false );
			}
			Settings::import( $decoded );
		}
		self::redirect( 'tools', true );
	}

	public static function clear_settings(): void {
		self::guard_full_access();
		check_admin_referer( 'uamm_clear_settings' );
		delete_option( Settings::OPTION );
		self::redirect( 'tools', true, array( 'uamm-cleared' => 1 ) );
	}

	private static function guard(): void {
		if ( ! current_user_can( Settings::ACCESS_CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'uplink-admin-menu-manager' ), '', array( 'response' => 403 ) );
		}
	}

	private static function guard_full_access(): void {
		if ( ! Settings::user_has_full_access() ) {
			wp_die( esc_html__( 'You are not allowed to manage these site-wide settings.', 'uplink-admin-menu-manager' ), '', array( 'response' => 403 ) );
		}
	}

	private static function redirect( string $tab, bool $success, array $extra = array() ): void {
		$args = array_merge( array( 'page' => 'uplink-admin-menu-manager', 'tab' => $tab, $success ? 'uamm-updated' : 'uamm-error' => 1 ), $extra );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
