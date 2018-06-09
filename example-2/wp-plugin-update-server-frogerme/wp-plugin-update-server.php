<?php
/*
Plugin Name: WP Plugin Update Server
Plugin URI: https://github.com/froger-me/wp-plugin-update-server/
Description: Update server for custom plugins.
Version: 1.0
Author: Alexandre Froger
Author URI: https://froger.me/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'WP_PUS_PLUGIN_PATH' ) ) {
	define( 'WP_PUS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WP_PUS_PLUGIN_URL' ) ) {
	define( 'WP_PUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once WP_PUS_PLUGIN_PATH . 'inc/class-wp-plugin-update-server.php';

register_activation_hook( __FILE__, array( 'WP_Plugin_Update_Server', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Plugin_Update_Server', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'WP_Plugin_Update_Server', 'uninstall' ) );

function wp_pus_init() {
	add_filter( 'slm_ap_response_args', array( 'WP_Plugin_Update_Server', 'slm_add_signature' ), 10, 1 );
}
add_action( 'init', 'wp_pus_init', 5, 0 );

function wp_pus_run() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once WP_PUS_PLUGIN_PATH . 'lib/wp-update-server/loader.php';
	require_once WP_PUS_PLUGIN_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	require_once WP_PUS_PLUGIN_PATH . 'lib/proxy-update-checker/proxy-update-checker.php';
	require_once WP_PUS_PLUGIN_PATH . 'lib/crypto-url/crypto-url.class.php';
	require_once WP_PUS_PLUGIN_PATH . 'inc/class-wppus-update-server.php';
	require_once WP_PUS_PLUGIN_PATH . 'inc/class-wppus-secure-license-update-server.php';

	if ( ! WP_Plugin_Update_Server::is_doing_update_api_request() ) {

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		require_once WP_PUS_PLUGIN_PATH . 'inc/class-wppus-packages-table.php';
		require_once WP_PUS_PLUGIN_PATH . 'inc/class-wp-plugin-update-server-settings.php';

		$wp_plugin_update_server_settings = new WP_Plugin_Update_Server_Settings();
	}

	$wp_plugin_update_server = new WP_Plugin_Update_Server();
}
add_action( 'plugins_loaded', 'wp_pus_run', 5, 0 );
