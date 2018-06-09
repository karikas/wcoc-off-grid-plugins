<?php
/**
* This MU Plugin file allows to run only the core and WP Plugin Update Server actions and filters.
* It is useful to prevent WordPress from running plugin actions and filters when doing update checks.
* The array $wppus_always_active_plugins below can be edited.
* Add values to this array if you wish to continue to keep corresponding plugins active even during update checks.
*
* !!! IMPORTANT - THEMES:
* !!! This MU Plugin file does not prevent theme hooks registered before parse_request from being fired.
* !!! It is therefore recommended to use a theme that registers very few hooks before parse_request.
* !!! However, it provides a global variable $wppus_doing_update_api_request that can be tested when adding hooks and filters
* !!! Use it in a main theme's functions.php to test if hooks should be added.
* !!! Use it in a child theme's function.php to remove actions and filters from the parent theme.
*
* Place this file in a wp-content/mu-plugin folder and it will be loaded automatically
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wppus_doing_update_api_request, $wppus_always_active_plugins;

if ( ! $wppus_always_active_plugins ) {
	$wppus_always_active_plugins = array(
	// Your plugins here to keep active during update checks.
	// 'my-plugin-slug/my-plugin-file.php',
	// 'my-other-plugin-slug/my-other-plugin-file.php',
	);
}


$url_parts                      = explode( '/', ltrim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) );
$wppus_doing_update_api_request = ( 'wp-update-server' === reset( $url_parts ) );


if ( true === $wppus_doing_update_api_request ) {
	add_filter( 'option_active_plugins', 'wppus_unset_plugins' );
}

function wppus_unset_plugins( $plugins ) {
	global $wppus_always_active_plugins;

	foreach ( $plugins as $key => $plugin ) {

		if ( ! in_array( $plugin, $wppus_always_active_plugins, true ) ) {
			unset( $plugins[ $key ] );
		}
	}

	return $plugins;
}
