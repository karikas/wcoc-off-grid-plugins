<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/* ================================================================================================ */
/*                                  WP Plugin Update Server                                         */
/* ================================================================================================ */

require_once get_stylesheet_directory() . '/lib/wp-plugin-updater/class-wp-plugin-updater.php';

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
// 	get_stylesheet_directory(),
// 	'YOUR_LICENSE_SERVER_SECRET',
// 	'https://your-license-server-url'
// );

/** Enable updates without license check **/
// $dummy_plugin_updater = new WP_Plugin_Updater(
// 	'https://your-update-server.com',
// 	__FILE__,
// 	get_stylesheet_directory(),
// );

/* ================================================================================================ */

function my_theme_enqueue_styles() {
	$parent_style = 'twentyseventeen-style';

	wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( $parent_style ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );
