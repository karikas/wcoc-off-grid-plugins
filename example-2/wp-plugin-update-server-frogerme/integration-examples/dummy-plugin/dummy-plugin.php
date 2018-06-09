<?php
/*
Plugin Name: Dummy Plugin
Plugin URI: https://froger.me/
Description: Empty plugin to demonstrate the WP Plugin Updater.
Version: 1.0
Author: Alexandre Froger
Author URI: https://froger.me/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/* ================================================================================================ */
/*                                  WP Plugin Update Server                                         */
/* ================================================================================================ */

require_once plugin_dir_path( __FILE__ ) . 'lib/wp-plugin-updater/class-wp-plugin-updater.php';

/**
* Uncomment and complete on of the section below to enable updates:
* - https://your-update-server.com  => The URL of the server where WP Plugin Update is installed.
* - YOUR_LICENSE_SERVER_SECRET      => The Software License Management's Secret Key for License Verification Requests
* - https://your-license-server-url => The URL of the server where Software License Management is installed
**/

/** Enable updates with license check **/
// $dummy_plugin_updater = new WP_Plugin_Updater(
// 	'https://your-update-server.com',
// 	__FILE__,
// 	plugin_dir_path( __FILE__ ),
// 	'YOUR_LICENSE_SERVER_SECRET',
// 	'https://your-license-server-url'
// );

/** Enable updates without license check **/
// $dummy_plugin_updater = new WP_Plugin_Updater(
// 	'https://your-update-server.com',
// 	__FILE__,
// 	plugin_dir_path( __FILE__ ),
// );

/* ================================================================================================ */

function dummy_plugin_run() {}
add_action( 'plugins_loaded', 'dummy_plugin_run', 10, 0 );
