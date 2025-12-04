<?php
/**
 * Plugin Name: Ashwab WP Access and Hide
 * Description: Restricts access to specific WordPress dashboard content for all users except a designated administrator.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 7.2
 * Author: Essam Barghsh, Ashwab
 * Author URI: https://www.ashwab.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://www.ashwab.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the allowed email address.
define( 'ASHWAB_ALLOWED_EMAIL', 'dev@ashwab.com' );

define( 'ASHWAB_ACCESS_HIDE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ASHWAB_ACCESS_HIDE_URL', plugin_dir_url( __FILE__ ) );

require_once ASHWAB_ACCESS_HIDE_PATH . 'includes/class-ashwab-access-hide.php';

function ashwab_access_hide_init() {
	$plugin = new Ashwab_Access_Hide();
	$plugin->init();
}
add_action( 'plugins_loaded', 'ashwab_access_hide_init' );

register_activation_hook( __FILE__, array( 'Ashwab_Access_Hide', 'install' ) );
