<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly}
}

global $wpdb;

WP_Filesystem();

global $wp_filesystem;

$server_directory  = realpath( __DIR__ . '/..' );
$package_directory = $server_directory . '/packages';

if ( $wp_filesystem->is_dir( $package_directory ) ) {
	$package_paths = glob( trailingslashit( $package_directory ) . '*.zip' );

	if ( ! empty( $package_paths ) ) {

		foreach ( $package_paths as $package_path ) {
			$package_path_parts = explode( '/', $package_path );
			$safe_slug          = str_replace( '.zip', '', end( $package_path_parts ) );
			wp_clear_scheduled_hook( 'wppus_check_remote_' . $safe_slug, array( $safe_slug ) );
		}
	}
}

wp_clear_scheduled_hook( 'wppus_cleanup', array( 'cache' ) );
wp_clear_scheduled_hook( 'wppus_cleanup', array( 'logs' ) );
wp_clear_scheduled_hook( 'wppus_cleanup', array( 'tmp' ) );

$transient_prefix = $wpdb->esc_like( '_transient_wppus_' ) . '%';
$option_prefix    = $wpdb->esc_like( 'wppus_' ) . '%';
$sql              = "DELETE FROM $wpdb->options WHERE `option_name` LIKE '%s' OR `option_name` LIKE '%s'";

$wpdb->query( $wpdb->prepare( $sql, $option_prefix . '%', $transient_prefix . '%' ) ); // @codingStandardsIgnoreLine
