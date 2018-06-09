<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_Plugin_Updater' ) ) {

	class WP_Plugin_Updater {

		private $license_server_secret_key;
		private $license_server_url;
		private $plugin_id;
		private $update_server_url;
		private $plugin_path;
		private $plugin_url;
		private $update_checker;
		private $type;
		private $transient_slug;
		private $use_license;

		public function __construct(
			$update_server_url,
			$plugin_file_path,
			$plugin_path,
			$license_server_secret_key = null,
			$license_server_url = null
		) {
			$plugin_file_path_parts = explode( '/', $plugin_file_path );
			$plugin_id_parts        = array_slice( $plugin_file_path_parts, -2, 2 );
			$plugin_id              = implode( '/', $plugin_id_parts );

			$this->license_server_secret_key = $license_server_secret_key;
			$this->license_server_url        = $license_server_url;
			$this->update_server_url         = trailingslashit( $update_server_url ) . 'wp-update-server/';
			$this->plugin_path               = trailingslashit( $plugin_path );
			$this->plugin_id                 = $plugin_id;
			$this->use_license               = isset( $license_server_secret_key, $license_server_url );

			if ( ! class_exists( 'Puc_v4_Factory' ) ) {
				require $this->plugin_path . 'lib/plugin-update-checker/plugin-update-checker.php';
			}

			$metadata_url  = trailingslashit( $this->update_server_url ) . '?update_action=get_metadata&plugin_id=';
			$metadata_url .= rawurlencode( $this->plugin_id );

			$this->update_checker = Puc_v4_Factory::buildUpdateChecker( $metadata_url, $plugin_file_path );

			$this->set_type();

			if ( 'Plugin' === $this->type ) {
				$this->plugin_url = plugin_dir_url( $plugin_file_path );
			} elseif ( 'Theme' === $this->type ) {
				$this->plugin_url = trailingslashit( get_theme_root_uri() ) . reset( $plugin_id_parts );
			}

			$this->transient_slug = ( strlen( $this->plugin_id ) > 30 ) ? substr( $this->plugin_id, 0, 30 ) : $this->plugin_id;

			$this->update_checker->addQueryArgFilter( array( $this, 'filter_update_checks' ) );

			if ( $this->use_license ) {
				$this->update_checker->addResultFilter( array( $this, 'maybe_show_license_notice' ) );

				if ( 'Plugin' === $this->type ) {
					add_action( 'after_plugin_row_' . $this->plugin_id, array( $this, 'print_license_under_plugin' ), 10, 3 );
				} elseif ( 'Theme' === $this->type ) {
					add_action( 'admin_menu', array( $this, 'setup_theme_admin_menus' ), 10, 0 );
				}

				add_action( 'wp_ajax_wppu_' . $this->plugin_id . '_activate_license', array( $this, 'activate_license' ), 10, 0 );
				add_action( 'wp_ajax_wppu_' . $this->plugin_id . '_deactivate_license', array( $this, 'deactivate_license' ), 10, 0 );
				add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 99, 1 );
				add_action( 'admin_notices', array( $this, 'show_license_error_notice' ), 10, 0 );
			}
		}

		public function setup_theme_admin_menus() {
			add_submenu_page(
				'themes.php',
				'Theme License',
				'Theme License',
				'manage_options',
				'theme-license',
				array( $this, 'theme_license_settings' )
			);
		}

		public function theme_license_settings() {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // @codingStandardsIgnoreLine
			}

			$this->print_license_form_theme_page();

		}

		public function add_admin_scripts( $hook ) {
			$debug = (bool) ( constant( 'WP_DEBUG' ) );

			$condition = 'plugins.php' === $hook;
			$condition = $condition || 'appearance_page_theme-license' === $hook;
			$condition = $condition || 'appearance_page_parent-theme-license' === $hook;
			$condition = $condition && ! wp_script_is( 'wp-plugin-updater-script' );

			if ( $condition ) {
				$js_ext = ( $debug ) ? '.js' : '.min.js';
				$ver_js = filemtime( $this->plugin_path . 'lib/wp-plugin-updater/js/main' . $js_ext );
				$params = array(
					'action_prefix' => 'wppu_' . $this->plugin_id,
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
				);

				wp_enqueue_script( 'wp-plugin-updater-script', $this->plugin_url . '/lib/wp-plugin-updater/js/main' . $js_ext, array( 'jquery' ), $ver_js, true );
				wp_localize_script( 'wp-plugin-updater-script', 'WP_PluginUpdater', $params );
			}
		}

		public function filter_update_checks( $query_args ) {

			if ( $this->use_license ) {
				$license           = get_option( 'license_key_' . $this->plugin_id );
				$license_signature = get_option( 'license_signature_' . $this->plugin_id );

				if ( $license ) {
					$query_args['update_license_key']       = rawurlencode( $license );
					$query_args['update_license_signature'] = rawurlencode( $license_signature );
					$query_args['update_secret_key']        = rawurlencode( $this->license_server_secret_key );
				}
			}

			$query_args['update_type'] = $this->type;

			return $query_args;
		}

		public function print_license_form_theme_page() {
			$title = 'Theme License';
			$form  = $this->get_license_form();

			ob_start();

			require_once $this->plugin_path . 'lib/wp-plugin-updater/templates/theme-page-license.php';

			echo ob_get_clean(); // @codingStandardsIgnoreLine			
		}

		public function print_license_under_plugin( $plugin_file = null, $plugin_data = null, $status = null ) {
			$form = $this->get_license_form();

			ob_start();

			require_once $this->plugin_path . 'lib/wp-plugin-updater/templates/plugin-page-license-row.php';

			echo ob_get_clean(); // @codingStandardsIgnoreLine
		}

		public function activate_license() {
			$license_data = $this->do_query_license( 'activate' );

			if ( isset( $license_data->reference, $license_data->license_key ) ) {
				update_option( 'license_key_' . $license_data->reference, $license_data->license_key, false );

				if ( isset( $license_data->license_signature ) ) {
					update_option( 'license_signature_' . $license_data->reference, $license_data->license_signature, false );
				} else {
					delete_option( 'license_signature_' . $license_data->reference );
				}
			} else {
				$error = new WP_Error( 'License', $license_data->message );

				delete_option( 'license_signature_' . $license_data->reference );
				delete_option( 'license_key_' . $license_data->reference, '', false );
				wp_send_json_error( $error );
			}

			wp_send_json_success( $license_data );
		}

		public function deactivate_license() {
			$license_data = $this->do_query_license( 'deactivate' );

			if ( isset( $license_data->reference, $license_data->license_key ) ) {
				update_option( 'license_key_' . $license_data->reference, '', false );

				if ( isset( $license_data->license_signature ) ) {
					update_option( 'license_signature_' . $license_data->reference, '', false );
				} else {
					delete_option( 'license_signature_' . $license_data->reference );
				}
			} else {
				$error = new WP_Error( 'License', $license_data->message );

				delete_option( 'license_signature_' . $license_data->reference );
				delete_option( 'license_key_' . $license_data->reference, '', false );
				wp_send_json_error( $error );
			}

			wp_send_json_success( $license_data );
		}

		public function maybe_show_license_notice( $plugin_info, $result ) {

			if ( isset( $plugin_info->license_error ) ) {
				set_transient( 'wppu_' . $this->transient_slug . 'license_error', $plugin_info->name . ': ' . $plugin_info->license_error );
			}

			return $plugin_info;
		}

		public function show_license_error_notice() {
			$error = get_transient( 'wppu_' . $this->transient_slug . 'license_error' );

			if ( $error ) {
				$class = 'notice notice-error is-dismissible';

				printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $error ); // @codingStandardsIgnoreLine
			}

			delete_transient( 'wppu_' . $this->transient_slug . 'license_error' );
		}

		protected function do_query_license( $query_type ) {

			if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'license_nonce' ) ) {
				$error = new WP_Error( 'License', 'Unauthorised access.' );

				wp_send_json_error( $error );
			}

			$license_key = $_REQUEST['license_key'];

			if ( empty( $license_key ) ) {
				$error = new WP_Error( 'License', 'Invalid license key' );

				wp_send_json_error( $error );
			}

			$api_params = array(
				'slm_action'        => 'slm_' . $query_type,
				'secret_key'        => $this->license_server_secret_key,
				'license_key'       => $license_key,
				'registered_domain' => $_SERVER['SERVER_NAME'],
				'item_reference'    => rawurlencode( $this->plugin_id ),
			);

			$query    = esc_url_raw( add_query_arg( $api_params, $this->license_server_url ) );
			$response = wp_remote_get( $query, array(
				'timeout'   => 20,
				'sslverify' => false,
			) );

			if ( is_wp_error( $response ) ) {
				$error = new WP_Error( 'License', 'Unexpected Error! The query returned with an error.' );

				wp_send_json_error( $error );
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 'success' === $license_data->result ) {
				$license_data->license_key = $license_key;
				$license_data->reference   = $this->plugin_id;
			} else {
				$license_data->reference = $this->plugin_id;
			}

			return $license_data;
		}

		protected function get_license_form() {
			$license           = get_option( 'license_key_' . $this->plugin_id );
			$license_reference = $this->plugin_id;
			$show_license      = ( ! empty( $license ) );

			ob_start();

			require_once $this->plugin_path . 'lib/wp-plugin-updater/templates/license-form.php';

			return ob_get_clean();
		}

		protected static function is_plugin_file( $absolute_path ) {
			$plugin_dir    = wp_normalize_path( WP_PLUGIN_DIR );
			$mu_plugin_dir = wp_normalize_path( WPMU_PLUGIN_DIR );

			if ( ( 0 === strpos( $absolute_path, $plugin_dir ) ) || ( 0 === strpos( $absolute_path, $mu_plugin_dir ) ) ) {

				return true;
			}

			if ( ! is_file( $absolute_path ) ) {
				return false;
			}

			if ( function_exists( 'get_file_data' ) ) {
				$headers = get_file_data( $absolute_path, array( 'Name' => 'Plugin Name' ), 'plugin' );

				return ! empty( $headers['Name'] );
			}

			return false;
		}

		protected static function get_theme_directory_name( $absolute_path ) {
			if ( is_file( $absolute_path ) ) {
				$absolute_path = dirname( $absolute_path );
			}

			if ( file_exists( $absolute_path . '/style.css' ) ) {
				return basename( $absolute_path );
			}
			return null;
		}

		protected function set_type() {
			$theme_directory = self::get_theme_directory_name( $this->plugin_path );

			if ( self::is_plugin_file( $this->plugin_path ) ) {
				$this->type = 'Plugin';
			} elseif ( null !== $theme_directory ) {
				$this->type = 'Theme';
			} else {
				throw new RuntimeException(sprintf(
					'The plugin updater cannot determine if "%s" is a plugin or a theme. ' .
					'This is a bug. Please contact the developer.',
					htmlentities( $this->plugin_path )
				));
			}
		}

	}
}
