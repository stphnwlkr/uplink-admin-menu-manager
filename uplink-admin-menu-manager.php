<?php
/**
 * Plugin Name:       Uplink Admin Menu Manager
 * Plugin URI:        https://uplinkplugins.com/articles/introducing-uplink-admin-menu-manager/
 * Description:       Organize WordPress admin menus with role-aware profiles and accessible collapsible groups.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Stephen Walker
 * Author URI:        https://profiles.wordpress.org/stphnwlkr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uplink-admin-menu-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UAMM_VERSION', '1.0.0' );
define( 'UAMM_FILE', __FILE__ );
define( 'UAMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'UAMM_URL', plugin_dir_url( __FILE__ ) );

require_once UAMM_PATH . 'includes/class-settings.php';
require_once UAMM_PATH . 'includes/class-menu-manager.php';
require_once UAMM_PATH . 'includes/class-admin-page.php';
require_once UAMM_PATH . 'includes/class-plugin.php';

Uplink\AdminMenuManager\Plugin::init();
