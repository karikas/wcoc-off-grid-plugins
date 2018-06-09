<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// @TODO:
// - readme.md
// - readme.txt
// - cleanup

// - support for plugins and theme icons ; instructions in the help tab (redacted content of readme files):
//		use "package-icons" and "package-banners" directories in the repository/uploaded package
//		when repacking package, move content to icons and banners directories on the update server
//		don't include "package-icons" and "package-banners" directories when repacking package
//      handle icons and banners delete when package is deleted

// - inject updater code, optionally with license
// 		when repacking, fire an action 'wwpus_before_repack'
//      check if code is already present
//      check if license is needed
// 		parse the main file (theme or plugin)
// 		add updater code
// 		add updater library
// 		if using license, get the license server secret from an option field (or local secret option)
//      when changing license status, reinject the appropriate code (unpack/repack)
//      update documentation
//
// - cleanup

class WP_Plugin_Update_Server {
	protected $update_server;
	protected $hmac_key;
	protected $crypto_key;
	protected $license_check_signature;
	protected $use_remote_repository;

	protected static $doing_update_api_request = null;
	protected static $did_activation           = false;

	public static $allowed_directories = array(
		'packages',
		'cache',
		'logs',
		'tmp',
	);

	public static $persistent_content_directories = array(
		'packages',
	);

	public function __construct() {
		$this->license_server_url    = get_option( 'wppus_license_server_url', home_url( '/' ) );
		$this->use_remote_repository = get_option( 'wppus_use_remote_repository', false );

		if ( ! self::is_doing_update_api_request() ) {

			if ( ! ( trailingslashit( home_url( '/' ) ) === trailingslashit( $this->license_server_url ) &&
				is_plugin_active( 'software-license-manager/slm_bootstrap.php' ) ) ) {
				add_action( 'admin_notices', array( $this, 'license_server_requirements_notice' ), 10, 0 );
			}

			if ( $this->use_remote_repository ) {
				add_action( 'init', array( 'WP_Plugin_Update_Server', 'register_remote_check_schedules' ), 10, 0 );
			} else {
				add_action( 'init', array( 'WP_Plugin_Update_Server', 'clear_remote_check_schedules' ), 10, 0 );
			}

			add_action( 'init', array( 'WP_Plugin_Update_Server', 'register_cleanup_events' ), 10, 0 );
			add_action( 'init', array( 'WP_Plugin_Update_Server', 'register_cache_cleaner_schedule' ), 10, 0 );
			add_action( 'init', array( 'WP_Plugin_Update_Server', 'register_log_cleaner_schedule' ), 10, 0 );
			add_action( 'init', array( $this, 'add_endpoints' ), 10, 0 );
			add_action( 'init', array( $this, 'maybe_flush' ), 99, 0 );
		}

		add_action( 'init', array( $this, 'load_textdomain' ), 10, 0 );
		add_action( 'parse_request', array( $this, 'parse_request' ), -99, 0 );

		add_filter( 'query_vars', array( $this, 'addquery_variables' ) );
	}

	public static function uninstall() {
		include_once WP_PUS_PLUGIN_PATH . 'uninstall.php';
	}

	public static function deactivate() {
		flush_rewrite_rules();
		self::remote_check_schedules_alter( 'clear' );
		self::cleaning_schedule_alter( 'cache', 'clear' );
		self::cleaning_schedule_alter( 'logs', 'clear' );
		self::cleaning_schedule_alter( 'tmp', 'clear' );
	}

	public static function activate() {
		self::$did_activation = true;

		self::register_cleanup_events();
	}

	public static function is_doing_update_api_request() {

		if ( null === self::$doing_update_api_request ) {
			self::$doing_update_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], 'wp-update-server' ) );
		}

		return self::$doing_update_api_request;
	}

	public static function register_cleanup_events() {
		$types = array_diff( self::$allowed_directories, self::$persistent_content_directories );

		foreach ( $types as $type ) {
			$params = array( $type );

			if ( 'tmp' === $type ) {
				$params[] = true;
			}

			if ( ! wp_next_scheduled( 'wppus_cleanup', $params ) ) {
				wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'wppus_cleanup', $params );
			}
		}
	}

	public static function reschedule_remote_check_events( $frequency ) {

		if ( self::is_doing_update_api_request() ) {

			return false;
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return;
		}

		$server_directory  = realpath( __DIR__ . '/..' );
		$package_directory = $server_directory . '/packages';

		if ( $wp_filesystem->is_dir( $package_directory ) ) {
			$package_paths = glob( trailingslashit( $package_directory ) . '*.zip' );

			if ( ! empty( $package_paths ) ) {

				foreach ( $package_paths as $package_path ) {
					$package_path_parts = explode( '/', $package_path );
					$safe_slug          = str_replace( '.zip', '', end( $package_path_parts ) );

					wp_clear_scheduled_hook( 'wppus_check_remote_' . $safe_slug, array( $safe_slug ) );
					wp_schedule_event(
						current_time( 'timestamp' ),
						$frequency,
						'wppus_check_remote_' . $safe_slug,
						array( $safe_slug )
					);
				}
			}
		}
	}

	public static function clear_remote_check_schedules() {
		self::remote_check_schedules_alter( 'clear' );
	}

	public static function register_remote_check_schedules() {
		self::remote_check_schedules_alter( 'register' );
	}

	public static function register_cache_cleaner_schedule() {
		self::cleaning_schedule_alter( 'cache', 'register' );
	}

	public static function register_log_cleaner_schedule() {
		self::cleaning_schedule_alter( 'logs', 'register' );
	}

	public static function register_tmp_cleaner_schedule() {
		self::cleaning_schedule_alter( 'tmp', 'register' );
	}

	public static function maybe_download_remote_update( $slug, $type = null ) {
		$config        = self::get_config();
		$update_server = new Wppus_Update_Server(
			$config['use_remote_repository'],
			home_url( '/wp-update-server/' ),
			$config['server_directory'],
			$config['repository_service_url'],
			$config['repository_branch'],
			$config['repository_credentials'],
			$config['repository_service_self_hosted'],
			$config['repository_check_frequency']
		);

		if ( $type ) {
			$update_server->set_type( $type );
		}

		$has_update = $update_server->check_remote_update( $slug );

		if ( $has_update ) {
			return $update_server->save_remote_package_to_local( $slug );
		}

		return false;
	}

	protected static function cleaning_schedule_alter( $type, $action ) {

		if ( self::is_doing_update_api_request() ) {

			return false;
		}

		$params = array( $type );

		if ( 'tmp' === $type ) {
			$params[] = true;
		}

		switch ( $action ) {
			case 'register':
				$hook = array( 'WP_Plugin_Update_Server', 'maybe_cleanup' );
				add_action( 'wppus_cleanup', $hook, 10, 2 );
				break;
			case 'clear':
				wp_clear_scheduled_hook( 'wppus_cleanup', $params );
				break;
		}
	}

	public static function maybe_cleanup( $type, $force = false ) {

		if ( ! in_array( $type, self::$allowed_directories, true ) ) {
			return false;
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return false;
		}

		$server_directory       = realpath( __DIR__ . '/..' );
		$directory              = $server_directory . '/' . $type;
		$max_size_constant_name = 'WP_PUS_DEFAULT_' . strtoupper( $type ) . '_MAX_SIZE';
		$default_max_size       = defined( $max_size_constant_name ) ? constant( $max_size_constant_name ) : 0;
		$cleanup                = false;
		$is_dir                 = $wp_filesystem->is_dir( $directory );

		if ( $default_max_size && $is_dir && false === $force ) {
			$total_size = 0;
			$max_size   = get_option( 'wppus_' . $type . '_max_size', $default_max_size );

			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
				$total_size += $file->getSize();
			}

			if ( $total_size >= ( $max_size * WP_PUS_MB_TO_B ) ) {
				$cleanup = true;
			}
		}

		if ( $is_dir && ( $cleanup || $force ) ) {
			$wp_filesystem->rmdir( $directory, true );
			$wp_filesystem->mkdir( $directory );

			return self::generate_restricted_htaccess( $directory );
		}
	}

	public function force_clean() {
		$result = false;

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {

			$type = $_REQUEST['type'];

			$result = self::maybe_cleanup( $type, true );
		}

		if ( $result ) {
			wp_send_json_success();
		} else {
			$error = new WP_Error(
				'WP_Plugin_Update_Server::force_clean',
				__( 'Error - check the directory is writable', 'wppus' )
			);

			wp_send_json_error( $error );
		}
	}

	protected static function generate_restricted_htaccess( $directory ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return;
		}
		$contents = "Order deny,allow\nDeny from all";
		$htaccess = trailingslashit( $directory ) . '.htaccess';

		$wp_filesystem->touch( $htaccess );

		return $wp_filesystem->put_contents( $htaccess, $contents, 0644 );
	}

	protected static function remote_check_schedules_alter( $action ) {

		if ( self::is_doing_update_api_request() ) {

			return false;
		}

		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return;
		}

		$server_directory  = realpath( __DIR__ . '/..' );
		$package_directory = $server_directory . '/packages';

		if ( $wp_filesystem->is_dir( $package_directory ) ) {
			$package_paths = glob( trailingslashit( $package_directory ) . '*.zip' );

			if ( ! empty( $package_paths ) ) {

				foreach ( $package_paths as $package_path ) {
					$package_path_parts = explode( '/', $package_path );
					$safe_slug          = str_replace( '.zip', '', end( $package_path_parts ) );

					switch ( $action ) {
						case 'register':
							$hook = array( 'WP_Plugin_Update_Server', 'maybe_download_remote_update' );

							add_action( 'wppus_check_remote_' . $safe_slug, $hook, 10, 1 );
							break;
						case 'clear':
							wp_clear_scheduled_hook( 'wppus_check_remote_' . $safe_slug, array( $safe_slug ) );
							break;
					}
				}
			}
		}
	}

	public function maybe_flush() {

		if ( self::$did_activation ) {
			flush_rewrite_rules();
		}
	}

	public function add_endpoints() {
		add_rewrite_rule( '^wp-update-server/*$', 'index.php?$matches[1]&__wp_plugin_update_server_api=1&', 'top' );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wppus', false, 'wp-plugin-update-server/languages' );
	}

	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__wp_plugin_update_server_api'] ) ) {
			$this->handle_update_api_request();
		}
	}

	public function addquery_variables( $query_variables ) {
		$query_variables = array_merge( $query_variables, array(
			'__wp_plugin_update_server_api',
			'update_action',
			'plugin_id',
			'update_secret_key',
			'update_license_key',
			'update_license_signature',
			'update_type',
		) );

		return $query_variables;
	}

	public static function slm_add_signature( $args ) {
		$tbl_name          = SLM_TBL_LICENSE_KEYS;
		$lic_key           = isset( $_REQUEST['license_key'] ) ? trim( strip_tags( $_REQUEST['license_key'] ) ) : null; // @codingStandardsIgnoreLine
		$registered_domain = isset( $_REQUEST['registered_domain'] ) ? trim( wp_unslash( strip_tags( $_REQUEST['registered_domain'] ) ) ) : null; // @codingStandardsIgnoreLine
		$item_reference    = isset( $_REQUEST['item_reference'] ) ? trim( strip_tags( $_REQUEST['item_reference'] ) ) : null; // @codingStandardsIgnoreLine

		if ( 'success' === $args['result'] && $lic_key && $registered_domain && $item_reference ) {
			global $wpdb;

			$sql_prep   = $wpdb->prepare( "SELECT * FROM $tbl_name WHERE license_key = %s", $lic_key ); // @codingStandardsIgnoreLine
			$lic        = $wpdb->get_row( $sql_prep, OBJECT ); // @codingStandardsIgnoreLine
			$lic_key_id = $lic->id;

			$crypt_payload = array(
				$registered_domain,
				$item_reference,
			);
			$hmac_payload  = array(
				$lic->license_key,
				$lic_key_id,
			);

			$crypt     = CryptoUrl::encrypt(
				implode( Wppus_Secure_License_Update_Server::DATA_SEPARATOR, $crypt_payload ),
				get_option( 'wppus_crypto_key', 'crypto' )
			);
			$hmac      = CryptoUrl::hmac_sign(
				implode( Wppus_Secure_License_Update_Server::DATA_SEPARATOR, $hmac_payload ),
				get_option( 'wppus_hmac_key', 'hmac' )
			);
			$signature = $crypt . Wppus_Secure_License_Update_Server::CRYPT_HMAC_SEPARATOR . $hmac;

			$args['license_signature'] = $signature;
		}

		return $args;
	}

	public function handle_update_api_request() {
		global $wp;

		if ( isset( $wp->query_vars['update_action'] ) ) {
			$plugin_id = isset( $wp->query_vars['plugin_id'] ) ? rawurldecode( $wp->query_vars['plugin_id'] ) : null;

			if ( $plugin_id ) {
				$plugin_id_parts  = explode( '/', $plugin_id );
				$slug             = reset( $plugin_id_parts );
				$plugin_file_name = end( $plugin_id_parts );

				$this->init_update_server( $slug );
				$this->update_server->handleRequest( array_merge( $_GET, array( // @codingStandardsIgnoreLine
					'action'            => $wp->query_vars['update_action'],
					'slug'              => $slug,
					'plugin_file_name'  => $plugin_file_name,
					'secret_key'        => isset( $wp->query_vars['update_secret_key'] ) ? $wp->query_vars['update_secret_key'] : null,
					'license_key'       => isset( $wp->query_vars['update_license_key'] ) ? $wp->query_vars['update_license_key'] : null,
					'license_signature' => isset( $wp->query_vars['update_license_signature'] ) ? $wp->query_vars['update_license_signature'] : null,
					'type'              => isset( $wp->query_vars['update_type'] ) ? $wp->query_vars['update_type'] : null,
				) ) );
			}
		}
	}

	public function no_license_server_notice() {
		$class   = 'notice notice-error';
		$message = __( '<h3>WP Plugin Update Server</h3>License server does not exist of is unavailable (no response after 20 seconds). Check the plugin configuration.', 'wppus' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );// @codingStandardsIgnoreLine
	}

	public function license_server_requirements_notice() {
		$class   = 'notice notice-info is-dismissible';
		$message = __( '<h3>WP Plugin Update Server</h3>You are using a remote License server. Make sure to implement a filter for <code>slm_ap_response_args</code> on the remote server as described in <code>wp-plugin-update-server/integration-examples/slm.php</code>', 'wppus' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );// @codingStandardsIgnoreLine
	}

	protected static function get_config() {
		$config = array();

		$config['license_server_url']             = get_option( 'wppus_license_server_url', home_url( '/' ) );
		$config['use_remote_repository']          = get_option( 'wppus_use_remote_repository', false );
		$config['server_directory']               = realpath( __DIR__ . '/..' );
		$config['use_license_server']             = get_option( 'wppus_use_license_server', false );
		$config['repository_service_url']         = get_option( 'wppus_remote_repository_url' );
		$config['repository_branch']              = get_option( 'wppus_remote_repository_branch', 'master' );
		$config['repository_credentials']         = explode( '|', get_option( 'wppus_remote_repository_credentials' ) );
		$config['repository_service_self_hosted'] = get_option( 'wppus_remote_repository_self_hosted', false );
		$config['repository_check_frequency']     = get_option( 'wppus_remote_repository_check_frequency', 'daily' );

		if ( 1 < count( $config['repository_credentials'] ) ) {
			$config['repository_credentials'] = array(
				'consumer_key'    => reset( $config['repository_credentials'] ),
				'consumer_secret' => end( $config['repository_credentials'] ),
			);
		} else {
			$config['repository_credentials'] = reset( $config['repository_credentials'] );
		}

		$config['hmac_key']                = get_option( 'wppus_hmac_key', 'hmac' );
		$config['crypto_key']              = get_option( 'wppus_crypto_key', 'crypto' );
		$config['license_check_signature'] = get_option( 'wppus_license_check_signature', 1 );

		return $config;
	}

	protected function init_update_server( $slug ) {
		$config                     = self::get_config();
		$licensed_package_slugs     = get_option( 'wppus_licensed_package_slugs', array() );
		$package_use_license_server = false;

		if ( in_array( $slug, $licensed_package_slugs, true ) ) {
			$package_use_license_server = true;
		}

		if ( $package_use_license_server && $config['use_license_server'] ) {
			$this->validate_license_server();

			$this->update_server = new Wppus_Secure_License_Update_Server(
				$this->use_remote_repository,
				home_url( '/wp-update-server/' ),
				$config['server_directory'],
				$config['repository_service_url'],
				$config['repository_branch'],
				$config['repository_credentials'],
				$config['repository_service_self_hosted'],
				$config['repository_check_frequency'],
				$this->license_server_url,
				$config['crypto_key'],
				$config['hmac_key'],
				$config['license_check_signature']
			);
		} else {
			$this->update_server = new Wppus_Update_Server(
				$this->use_remote_repository,
				home_url( '/wp-update-server/' ),
				$config['server_directory'],
				$config['repository_service_url'],
				$config['repository_branch'],
				$config['repository_credentials'],
				$config['repository_service_self_hosted'],
				$config['repository_check_frequency']
			);
		}
	}

	protected function validate_license_server() {

		if ( is_admin() ) {

			if ( home_url( '/' ) === $this->license_server_url && ! is_plugin_active( 'software-license-manager/slm_bootstrap.php' ) ) {
				add_action( 'admin_notices', array( $this, 'no_license_server_notice' ), 10, 0 );
			} elseif ( home_url( '/' ) !== $this->license_server_url && ! get_transient( 'wppus_valid_license_server' ) ) {
				$api_params = array(
					'slm_action'        => 'slm_check',
					'secret_key'        => 'bogus',
					'license_key'       => 'bogus',
					'registered_domain' => 'bogus',
					'item_reference'    => 'bogus',
				);

				$query    = esc_url_raw( add_query_arg( $api_params, $this->license_server_url ) );
				$response = wp_remote_get( $query, array(
					'timeout'   => 20,
					'sslverify' => false,
				) );
				$data     = json_decode( wp_remote_retrieve_body( $response ) );

				if ( ! isset( $data->result ) ) {
					add_action( 'admin_notices', array( $this, 'no_license_server_notice' ), 10, 0 );
				} else {
					set_transient( 'wppus_valid_license_server', true );
				}
			}
		}
	}

}
