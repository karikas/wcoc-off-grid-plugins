<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'WP_PUS_MB_TO_B' ) ) {
	define( 'WP_PUS_MB_TO_B', 1000000 );
}

if ( ! defined( 'WP_PUS_DEFAULT_LOGS_MAX_SIZE' ) ) {
	define( 'WP_PUS_DEFAULT_LOGS_MAX_SIZE', 10 );
}

if ( ! defined( 'WP_PUS_DEFAULT_CACHE_MAX_SIZE' ) ) {
	define( 'WP_PUS_DEFAULT_CACHE_MAX_SIZE', 100 );
}

class WP_Plugin_Update_Server_Settings {

	const WP_PUS_DEFAULT_LOGS_MAX_SIZE    = 10;
	const WP_PUS_DEFAULT_CACHE_MAX_SIZE   = 100;
	const WP_PUS_DEFAULT_ARCHIVE_MAX_SIZE = 20;

	protected $packages_table;

	public function __construct() {
		$parts     = explode( '/', untrailingslashit( WP_PUS_PLUGIN_PATH ) );
		$plugin_id = end( $parts ) . '/wp-plugin-update-server.php';

		add_action( 'admin_init', array( $this, 'init_request' ), 10, 0 );
		add_action( 'admin_menu', array( $this, 'plugin_options_menu' ), 10, 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_scripts' ), 10, 1 );
		add_action( 'wp_ajax_wppus_force_clean', array( $this, 'force_clean' ), 10, 0 );
		add_action( 'wp_ajax_wppus_prime_package_from_remote', array( $this, 'prime_package_from_remote' ), 10, 0 );
		add_action( 'wp_ajax_wppus_manual_package_upload', array( $this, 'manual_package_upload' ), 10, 0 );
		add_action( 'load-toplevel_page_wppus-options', array( $this, 'add_page_options' ), 10, 0 );

		add_filter( 'set-screen-option', array( $this, 'set_page_options' ), 10, 3 );
		add_filter( 'plugin_action_links_' . $plugin_id, array( $this, 'add_action_links' ), 10, 1 );
	}

	public function init_request() {

		if ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			$this->packages_table = new Wppus_Packages_Table( $this );
			$redirect             = false;

			$condition = ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], $this->packages_table->nonce_action ) );
			$condition = $condition || ( isset( $_REQUEST['linknonce'] ) && wp_verify_nonce( $_REQUEST['linknonce'], 'linknonce' ) );

			if ( $condition ) {
				$page                = isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : false;
				$tab                 = isset( $_REQUEST['tab'] ) ? $_REQUEST['tab'] : false;
				$packages            = isset( $_REQUEST['packages'] ) ? $_REQUEST['packages'] : false;
				$delete_all_packages = isset( $_REQUEST['wppus_delete_all_packages'] ) ? true : false;
				$action              = false;

				if ( isset( $_REQUEST['action'] ) && -1 !== $_REQUEST['action'] ) {
					$action = $_REQUEST['action'];
				} elseif ( isset( $_REQUEST['action2'] ) && -1 !== $_REQUEST['action2'] ) {
					$action = $_REQUEST['action2'];
				}

				if ( 'wppus-options' === $page && 'general-options' === $tab ) {
					$redirect = admin_url( 'admin.php?page=wppus-options&tab=general-options' );

					if ( $packages && 'download' === $action ) {
						$error    = $this->download_packages_bulk( $packages );
						$redirect = false;
						if ( $error ) {
							$this->packages_table->bulk_action_error = $error;
						}
					}

					if ( $packages && 'delete' === $action ) {
						$this->delete_packages_bulk( $packages );
					}

					if ( $packages && 'enable_license' === $action ) {
						$this->change_packages_license_bulk( $packages, true );
					}

					if ( $packages && 'disable_license' === $action ) {
						$this->change_packages_license_bulk( $packages, false );
					}

					if ( $delete_all_packages ) {
						$this->delete_packages_bulk();
					}
				}
			}

			$this->packages_table->licensed_package_slugs = get_option( 'wppus_licensed_package_slugs', array() );

			if ( $redirect ) {
				wp_redirect( $redirect );
			}
		}
	}

	public function add_admin_scripts( $hook ) {
		$debug = (bool) ( constant( 'WP_DEBUG' ) );

		if ( 'toplevel_page_wppus-options' === $hook ) {
			$js_ext = ( $debug ) ? '.js' : '.min.js';
			$ver_js = filemtime( WP_PUS_PLUGIN_PATH . 'js/admin/main' . $js_ext );
			$params = array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'invalidFileFormat' => __( 'Error: invalid file format.', 'wppus' ),
				'invalidFileSize'   => __( 'Error: invalid file size.', 'wppus' ),
				'invalidFileName'   => __( 'Error: invalid file name.', 'wppus' ),
				'invalidFile'       => __( 'Error: invalid file' ),
			);

			wp_enqueue_script( 'wp-plugin-update-server-script', WP_PUS_PLUGIN_URL . 'js/admin/main' . $js_ext, array( 'jquery' ), $ver_js, true );
			wp_localize_script( 'wp-plugin-update-server-script', 'Wppus', $params );
		}
	}

	public function add_action_links( $links ) {
		$link = array(
			'<a href="' . admin_url( 'admin.php?page=wppus-options' ) . '">' . __( 'Settings' ) . '</a>',
		);

		return array_merge( $links, $link );
	}

	public function add_admin_menu() {
		$title    = __( 'WP Weixin Settings', 'wp-weixin' );
		$icon_url = WP_WEIXIN_PLUGIN_URL . '/images/wechat.png';

		add_menu_page( $title, 'WP Weixin', 'publish_posts', 'wp-weixin', array( $this, 'wp_weixin_options_page' ), $icon_url );
	}

	public function plugin_options_menu() {
		$page_title = __( 'WP Plugin Update Server', 'wppus' );
		$menu_title = $page_title;
		$capability = 'manage_options';
		$menu_slug  = 'wppus-options';
		$function   = array( $this, 'plugin_options_page' );
		$icon       = 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxNy44NSAxNS4zMSI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiNhNGE0YTQ7fS5jbHMtMntmaWxsOiNhMGE1YWE7fTwvc3R5bGU+PC9kZWZzPjx0aXRsZT5VbnRpdGxlZC0xPC90aXRsZT48cGF0aCBjbGFzcz0iY2xzLTEiIGQ9Ik0xMCwxMy41NGMyLjIzLDAsNC40NiwwLDYuNjksMCwuNjksMCwxLS4xNSwxLS45MSwwLTIuMzUsMC00LjcxLDAtNy4wNiwwLS42NC0uMi0uODctLjg0LS44NS0xLjEzLDAtMi4yNiwwLTMuMzksMC0uNDQsMC0uNjgtLjExLS42OC0uNjJzLjIzLS42My42OC0uNjJjMS40MSwwLDIuODEsMCw0LjIyLDAsLjgyLDAsMS4yMS40MywxLjIsMS4yNywwLDIuOTMsMCw1Ljg3LDAsOC44LDAsMS0uMjksMS4yNC0xLjI4LDEuMjVxLTIuNywwLTUuNDEsMGMtLjU0LDAtLjg1LjA5LS44NS43NXMuMzUuNzMuODcuNzFjLjgyLDAsMS42NSwwLDIuNDgsMCwuNDgsMCwuNzQuMTguNzUuNjlzLS40LjUxLS43NS41MUg1LjJjLS4zNSwwLS43OC4xMS0uNzUtLjVzLjI4LS43MS43Ni0uN2MuODMsMCwxLjY1LDAsMi40OCwwLC41NCwwLC45NSwwLC45NC0uNzRzLS40OC0uNzEtMS0uNzFIMi41MWMtMS4yMiwwLTEuNS0uMjgtMS41LTEuNTFRMSw5LjE1LDEsNWMwLTEuMTQuMzQtMS40NiwxLjQ5LTEuNDdINi40NGMuNCwwLC43LDAsLjcxLjU3cy0uMjEuNjgtLjcuNjdjLTEuMTMsMC0yLjI2LDAtMy4zOSwwLS41NywwLS44My4xNy0uODIuNzhxMCwzLjYyLDAsNy4yNGMwLC42LjIxLjguOC43OUM1LjM2LDEzLjUyLDcuNjgsMTMuNTQsMTAsMTMuNTRaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMSAtMi4xOSkiLz48cGF0aCBjbGFzcz0iY2xzLTIiIGQ9Ik0xMy4xLDkuMzhsLTIuNjIsMi41YS44MS44MSwwLDAsMS0xLjEyLDBMNi43NCw5LjM4YS43NC43NCwwLDAsMSwwLTEuMDguODIuODIsMCwwLDEsMS4xMywwTDkuMTMsOS41VjNhLjguOCwwLDAsMSwxLjU5LDBWOS41TDEyLDguM2EuODIuODIsMCwwLDEsMS4xMywwQS43NC43NCwwLDAsMSwxMy4xLDkuMzhaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMSAtMi4xOSkiLz48L3N2Zz4=';

		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon );
	}

	public function add_page_options() {
		$option = 'per_page';
		$args   = array(
			'label'   => __( 'Packages per page', 'wppus' ),
			'default' => 10,
			'option'  => 'packages_per_page',
		);

		add_screen_option( $option, $args );
	}

	public function set_page_options( $status, $option, $value ) {

		return $value;
	}

	public function plugin_options_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.' ) ); // @codingStandardsIgnoreLine
		}

		$updated              = $this->plugin_options_handler();
		$action_error         = '';
		$schedules            = wp_get_schedules();
		$active_tab           = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$active_tab           = ( $active_tab ) ? $active_tab : 'general-options';
		$cache_size           = 0;
		$logs_size            = 0;
		$package_rows         = array();
		$default_cache_size   = self::WP_PUS_DEFAULT_LOGS_MAX_SIZE;
		$default_logs_size    = self::WP_PUS_DEFAULT_CACHE_MAX_SIZE;
		$default_archive_size = self::WP_PUS_DEFAULT_ARCHIVE_MAX_SIZE;
		$packages_table       = $this->packages_table;

		if ( 'general-options' === $active_tab ) {
			$cache_size   = self::get_dir_size_mb( 'cache' );
			$logs_size    = self::get_dir_size_mb( 'logs' );
			$package_rows = $this->get_package_rows_data();

			$packages_table->set_rows( $package_rows );
			$packages_table->prepare_items();
		}

		ob_start();

		require_once WP_PUS_PLUGIN_PATH . 'inc/templates/admin/plugin-options-page.php';

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function force_clean() {
		$result = false;

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {

			$type   = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_STRING );
			$result = WP_Plugin_Update_Server::maybe_cleanup( $type, true );
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

	public function prime_package_from_remote() {

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {
			$type   = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_STRING );
			$slug   = filter_input( INPUT_POST, 'slug', FILTER_SANITIZE_STRING );
			$result = WP_Plugin_Update_Server::maybe_download_remote_update( $slug, $type );
		}

		if ( $result ) {
			wp_send_json_success();
		} else {
			$type_string = __( $type ); // @codingStandardsIgnoreLine
			$error       = new WP_Error(
				'WP_Plugin_Update_Server::prime_package_from_remote',
				sprintf(
					// translators: %1$s is the package type - "Plugin" or "Theme"
					__( 'Error - could not get remote package. Check if a repository with this slug for a package of type "%1$s" exists and has a valid file structure.', 'wppus' ),
					$type_string
				)
			);

			wp_send_json_error( $error );
		}
	}

	public function manual_package_upload() {
		$result     = false;
		$error_text = __( 'Reload the page and try again.', 'wppus' );

		if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'wppus_plugin_options' ) ) {
			WP_Filesystem();

			global $wp_filesystem;

			if ( ! $wp_filesystem ) {

				return;
			}

			$package_info = isset( $_FILES['package'] ) ? $_FILES['package'] : false;
			$valid        = (bool) ( $package_info );

			if ( ! $valid ) {
				$error_text = __( 'Something very wrong happened.', 'wppus' );
			}

			if ( $valid && 'application/zip' !== $package_info['type'] ) {
				$valid      = false;
				$error_text = __( 'Make sure the uploaded file is a zip archive.', 'wppus' );
			}

			if ( $valid && 0 !== absint( $package_info['error'] ) ) {
				$valid = false;

				switch ( $package_info['error'] ) {
					case UPLOAD_ERR_INI_SIZE:
						$error_text = ( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.' );
						break;

					case UPLOAD_ERR_FORM_SIZE:
						$error_text = ( 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.' );
						break;

					case UPLOAD_ERR_PARTIAL:
						$error_text = ( 'The uploaded file was only partially uploaded.' );
						break;

					case UPLOAD_ERR_NO_FILE:
						$error_text = ( 'No file was uploaded.' );
						break;

					case UPLOAD_ERR_NO_TMP_DIR:
						$error_text = ( 'Missing a temporary folder.' );
						break;

					case UPLOAD_ERR_CANT_WRITE:
						$error_text = ( 'Failed to write file to disk.' );
						break;

					case UPLOAD_ERR_EXTENSION:
						$error_text = ( 'A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.' );
						break;
				}
			}

			if ( $valid && 0 >= $package_info['size'] ) {
				$valid      = false;
				$error_text = __( 'Make sure the uploaded file is not empty.', 'wppus' );
			}

			if ( $valid && ! WshWordPressPackageParser::parsePackage( $package_info['tmp_name'], true ) ) {
				$valid      = false;
				$error_text = __( 'The uploaded package is not a valid WordPress package, or if it is a plugin, the main plugin file could not be found.', 'wppus' );
			}

			if ( $valid ) {
				$source      = $package_info['tmp_name'];
				$destination = WP_PUS_PLUGIN_PATH . 'packages/' . $package_info['name'];
				$result      = $wp_filesystem->move( $source, $destination, true );

			} else {
				$result = false;

				$wp_filesystem->delete( $package_info['tmp_name'] );
			}
		}

		if ( $result ) {
			wp_send_json_success();
		} else {
			$error = new WP_Error(
				'WP_Plugin_Update_Server::manual_package_upload',
				__( 'Error - could not upload the package. ', 'wppus' ) . "\n\n" . $error_text
			);

			wp_send_json_error( $error );
		}
	}

	protected static function get_dir_size_mb( $type ) {
		$result = 'N/A';

		if ( ! in_array( $type, WP_Plugin_Update_Server::$allowed_directories, true ) ) {
			return $result;
		}

		$server_directory = realpath( __DIR__ . '/..' );
		$directory        = $server_directory . '/' . $type;
		$total_size       = 0;

		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) ) as $file ) {
			$total_size += $file->getSize();
		}

		$size = (float) ( $total_size / WP_PUS_MB_TO_B );

		if ( $size < 0.01 ) {
			$result = '< 0.01 MB';
		} else {
			$result = number_format( $size, 2, '.', '' ) . 'MB';
		}

		return $result;
	}

	protected function plugin_options_handler() {
		$errors = array();
		$result = '';

		$original_wppus_remote_repository_check_frequency = get_option( 'wppus_remote_repository_check_frequency' );
		$new_wppus_remote_repository_check_frequency      = null;
		$original_wppus_use_remote_repository             = get_option( 'wppus_use_remote_repository' );
		$new_wppus_use_remote_repository                  = null;

		if ( isset( $_REQUEST['wppus_plugin_options_handler_nonce'] ) && wp_verify_nonce( $_REQUEST['wppus_plugin_options_handler_nonce'], 'wppus_plugin_options' ) ) {
			$result  = __( 'WP Plugin Update Server options successfully updated', 'wppus' );
			$section = filter_input( INPUT_POST, 'wppus_settings_section', FILTER_SANITIZE_STRING );
			$options = $this->get_submitted_options();

			foreach ( $options as $option_name => $option_info ) {
				$condition = $option_info['value'];
				$skip      = false;

				if ( $section !== $option_info['section'] ) {
					$skip = true;
				}

				if ( ! $skip && isset( $option_info['condition'] ) ) {

					if ( 'boolean' === $option_info['condition'] ) {
						$condition            = true;
						$option_info['value'] = ( $option_info['value'] );
					}

					if ( 'number' === $option_info['condition'] ) {
						$condition = is_numeric( $option_info['value'] );
					}

					if ( 'known frequency' === $option_info['condition'] ) {
						$schedules      = wp_get_schedules();
						$schedule_slugs = array_keys( $schedules );

						$condition = $condition && in_array( $option_info['value'], $schedule_slugs ); // @codingStandardsIgnoreLine
					}
				}

				if ( ! $skip && isset( $option_info['dependency'] ) && ! $options[ $option_info['dependency'] ]['value'] ) {
					$skip      = true;
					$condition = false;
				}

				if ( ! $skip && $condition ) {
					update_option( $option_name, $option_info['value'], false );

					if ( 'wppus_remote_repository_check_frequency' === $option_name ) {
						$new_wppus_remote_repository_check_frequency = $option_info['value'];
					}

					if ( 'wppus_use_remote_repository' === $option_name ) {
						$new_wppus_use_remote_repository = $option_info['value'];
					}
				} elseif ( ! $skip ) {
					$errors[ $option_name ] = sprintf(
						// translators: %1$s is the option display name, %2$s is the condition for update
						__( 'Option %1$s was not updated. Reason: %2$s', 'wppus' ),
						$option_info['display_name'],
						$option_info['failure_display_message']
					);
				}
			}
		} elseif ( isset( $_REQUEST['wppus_plugin_options_handler_nonce'] ) && ! wp_verify_nonce( $_REQUEST['wppus_plugin_options_handler_nonce'], 'wppus_plugin_options' ) ) {
			$errors['general'] = __( 'There was an error validating the form. It may be outdated. Please reload the page.', 'wppus' );
		}

		if ( ! empty( $errors ) ) {
			$result = $errors;
		}

		$frequency = get_option( 'wppus_remote_repository_check_frequency' );

		if ( null !== $new_wppus_use_remote_repository &&
			$new_wppus_use_remote_repository !== $original_wppus_use_remote_repository ) {

			if ( ! $original_wppus_use_remote_repository && $new_wppus_use_remote_repository ) {
				WP_Plugin_Update_Server::reschedule_remote_check_events(
					get_option( 'wppus_remote_repository_check_frequency' )
				);
			} elseif ( $original_wppus_use_remote_repository && ! $new_wppus_use_remote_repository ) {
				WP_Plugin_Update_Server::clear_remote_check_schedules();
			}
		}

		if ( null !== $new_wppus_remote_repository_check_frequency &&
			$new_wppus_remote_repository_check_frequency !== $original_wppus_remote_repository_check_frequency ) {
			WP_Plugin_Update_Server::reschedule_remote_check_events( $new_wppus_remote_repository_check_frequency );
		}

		delete_transient( 'wppus_valid_license_server' );

		return $result;
	}

	protected function get_submitted_options() {

		return array(
			'wppus_use_license_server'                => array(
				'value'        => filter_input( INPUT_POST, 'wppus_use_license_server', FILTER_VALIDATE_BOOLEAN ),
				'display_name' => __( 'Software License Manager integration', 'wppus' ),
				'condition'    => 'boolean',
				'section'      => 'package-licensing',
			),
			'wppus_license_server_url'                => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_license_server_url', FILTER_VALIDATE_URL ),
				'display_name'            => __( 'License server URL (Software License Manager plugin)', 'wppus' ),
				'failure_display_message' => __( 'Not a valid URL', 'wppus' ),
				'dependency'              => 'wppus_use_license_server',
				'section'                 => 'package-licensing',
			),
			'wppus_hmac_key'                          => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_hmac_key', FILTER_SANITIZE_STRING ),
				'display_name'            => __( 'HMAC Key', 'wppus' ),
				'failure_display_message' => __( 'Not a valid string', 'wppus' ),
				'section'                 => 'package-licensing',
			),
			'wppus_crypto_key'                        => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_crypto_key', FILTER_SANITIZE_STRING ),
				'display_name'            => __( 'Encryption Key', 'wppus' ),
				'failure_display_message' => __( 'Not a valid string', 'wppus' ),
				'section'                 => 'package-licensing',
			),
			'wppus_license_check_signature'           => array(
				'value'        => filter_input( INPUT_POST, 'wppus_license_check_signature', FILTER_VALIDATE_BOOLEAN ),
				'display_name' => __( 'Check License signature?', 'wppus' ),
				'condition'    => 'boolean',
				'section'      => 'package-licensing',
			),
			'wppus_use_remote_repository'             => array(
				'value'        => filter_input( INPUT_POST, 'wppus_use_remote_repository', FILTER_VALIDATE_BOOLEAN ),
				'display_name' => __( 'Use remote repository service', 'wppus' ),
				'condition'    => 'boolean',
				'section'      => 'package-source',
			),
			'wppus_remote_repository_url'             => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_remote_repository_url', FILTER_VALIDATE_URL ),
				'display_name'            => __( 'Remote repository service URL', 'wppus' ),
				'failure_display_message' => __( 'Not a valid URL', 'wppus' ),
				'dependency'              => 'wppus_use_remote_repository',
				'section'                 => 'package-source',
			),
			'wppus_remote_repository_self_hosted'     => array(
				'value'        => filter_input( INPUT_POST, 'wppus_remote_repository_self_hosted', FILTER_VALIDATE_BOOLEAN ),
				'display_name' => __( 'Self-hosted remote repository service', 'wppus' ),
				'condition'    => 'boolean',
				'section'      => 'package-source',
			),
			'wppus_remote_repository_branch'          => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_remote_repository_branch', FILTER_SANITIZE_STRING ),
				'display_name'            => __( 'Packages branch name', 'wppus' ),
				'failure_display_message' => __( 'Not a valid string', 'wppus' ),
				'section'                 => 'package-source',
			),
			'wppus_remote_repository_credentials'     => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_remote_repository_credentials', FILTER_SANITIZE_STRING ),
				'display_name'            => __( 'Remote repository service credentials', 'wppus' ),
				'failure_display_message' => __( 'Not a valid string', 'wppus' ),
				'section'                 => 'package-source',
			),
			'wppus_remote_repository_check_frequency' => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_remote_repository_check_frequency', FILTER_SANITIZE_STRING ),
				'display_name'            => __( 'Remote update check frequency', 'wppus' ),
				'failure_display_message' => __( 'Not a valid option', 'wppus' ),
				'condition'               => 'known frequency',
				'section'                 => 'package-source',
			),
			'wppus_cache_max_size'                    => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_cache_max_size', FILTER_VALIDATE_INT ),
				'display_name'            => __( 'Cache max size (in MB)', 'wppus' ),
				'failure_display_message' => __( 'Not a valid number', 'wppus' ),
				'condition'               => 'number',
				'section'                 => 'general-options',
			),
			'wppus_logs_max_size'                     => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_logs_max_size', FILTER_VALIDATE_INT ),
				'display_name'            => __( 'Logs max size (in MB)', 'wppus' ),
				'failure_display_message' => __( 'Not a valid number', 'wppus' ),
				'condition'               => 'number',
				'section'                 => 'general-options',
			),
			'wppus_archive_max_size'                  => array(
				'value'                   => filter_input( INPUT_POST, 'wppus_archive_max_size', FILTER_VALIDATE_INT ),
				'display_name'            => __( 'Archive max size (in MB)', 'wppus' ),
				'failure_display_message' => __( 'Not a valid number', 'wppus' ),
				'condition'               => 'number',
				'section'                 => 'general-options',
			),
		);
	}

	public function delete_packages_bulk( $package_slugs = array() ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return null;
		}

		$package_slugs     = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );
		$package_directory = realpath( __DIR__ . '/..' ) . '/packages';
		$package_paths     = glob( trailingslashit( $package_directory ) . '*.zip' );
		$package_names     = array();
		$delete_all        = false;

		if ( ! empty( $package_paths ) ) {

			if ( empty( $package_slugs ) ) {
				$delete_all = true;
			}

			foreach ( $package_paths as $package_path ) {
				$package_path_parts = explode( '/', $package_path );
				$package_name       = end( $package_path_parts );
				$package_names[]    = $package_name;

				if ( $delete_all ) {
					$package_slugs[] = str_replace( '.zip', '', $package_name );
				}
			}
		}

		foreach ( $package_slugs as $package_slug ) {

			if ( in_array( $package_slug . '.zip', $package_names, true ) ) {
				$wp_filesystem->delete( trailingslashit( $package_directory ) . $package_slug . '.zip' );
				wp_clear_scheduled_hook( 'wppus_check_remote_' . $package_slug, array( $package_slug ) );
				unset( $this->rows[ $package_slug ] );
			}
		}

		$this->change_packages_license_bulk( $package_slugs, false );
	}

	public function download_packages_bulk( $package_slugs ) {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return null;
		}

		$package_directory = realpath( __DIR__ . '/..' ) . '/packages';
		$total_size        = 0;
		$max_archive_size  = get_option( 'wppus_archive_max_size', self::WP_PUS_DEFAULT_ARCHIVE_MAX_SIZE );
		$package_slugs     = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );

		foreach ( $package_slugs as $package_slug ) {
			$total_size += filesize( trailingslashit( $package_directory ) . $package_slug . '.zip' );
		}

		if ( $max_archive_size < ( (float) ( $total_size / WP_PUS_MB_TO_B ) ) ) {
			$this->packages_table->bulk_action_error = 'max_file_size_exceeded';

			return;
		}

		if ( 1 === count( $package_slugs ) ) {
			$this->trigger_packages_download(
				reset( $package_slugs ),
				trailingslashit( $package_directory ) . reset( $package_slugs ) . '.zip'
			);

			return;
		}

		$temp_directory = realpath( __DIR__ . '/..' ) . '/tmp';
		$archive_name   = 'archive-' . current_time( 'timestamp' );
		$archive_path   = trailingslashit( $temp_directory ) . $archive_name . '.zip';

		$zip = new ZipArchive();

		if ( ! $zip->open( $archive_path, ZIPARCHIVE::CREATE ) ) {

			return false;
		}

		foreach ( $package_slugs as $package_slug ) {
			$file = trailingslashit( $package_directory ) . $package_slug . '.zip';

			$zip->addFromString( $package_slug . '.zip', $wp_filesystem->get_contents( $file ) );
		}

		$zip->close();

		$this->trigger_packages_download( $archive_name, $archive_path );
	}

	protected function change_packages_license_bulk( $package_slugs, $add ) {

		$package_slugs          = is_array( $package_slugs ) ? $package_slugs : array( $package_slugs );
		$licensed_package_slugs = get_option( 'wppus_licensed_package_slugs', array() );
		$changed                = false;

		foreach ( $package_slugs as $package_slug ) {

			if ( $add && ! in_array( $package_slug, $licensed_package_slugs, true ) ) {
				$licensed_package_slugs[] = $package_slug;
				$changed                  = true;
			} elseif ( ! $add && in_array( $package_slug, $licensed_package_slugs, true ) ) {
				$key = array_search( $package_slug, $licensed_package_slugs, true );

				unset( $licensed_package_slugs[ $key ] );

				$changed = true;
			}
		}

		if ( $changed ) {
			$licensed_package_slugs = array_values( $licensed_package_slugs );

			update_option( 'wppus_licensed_package_slugs', $licensed_package_slugs, true );
		}

	}

	protected function trigger_packages_download( $archive_name, $archive_path ) {

		if ( ! empty( $archive_path ) && ! empty( $archive_name ) ) {

			if ( ini_get( 'zlib.output_compression' ) ) {
				@ini_set( 'zlib.output_compression', 'Off' ); // @codingStandardsIgnoreLine
			}

			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $archive_name . '.zip"' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Length: ' . filesize( $archive_path ) );

			readfile( $archive_path ); // @codingStandardsIgnoreLine

			exit;
		}
	}

	protected function get_package_rows_data() {
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {

			return;
		}

		$server_directory  = realpath( __DIR__ . '/..' );
		$package_directory = $server_directory . '/packages';
		$packages          = array();

		if ( $wp_filesystem->is_dir( $package_directory ) ) {
			$package_paths = glob( trailingslashit( $package_directory ) . '*.zip' );

			if ( ! empty( $package_paths ) ) {

				foreach ( $package_paths as $package_path ) {
					$package = $this->get_package( $package_path );
					$meta    = $package->getMetadata();

					$packages[ $meta['slug'] ] = array(
						'name'               => $meta['name'],
						'version'            => $meta['version'],
						'type'               => isset( $meta['details_url'] ) ? __( 'Theme', 'wppus' ) : __( 'Plugin', 'wppus' ),
						'last_updated'       => $meta['last_updated'],
						'file_name'          => $meta['slug'] . '.zip',
						'file_path'          => $package_path,
						'file_size'          => $package->getFileSize(),
						'file_last_modified' => $package->getLastModified(),
					);
				}
			}
		}

		return $packages;
	}

	protected function get_package( $path ) {

		return Wpup_Package::fromArchive( $path, null, new Wpup_FileCache( trailingslashit( realpath( __DIR__ . '/..' ) ) . 'cache' ) );
	}

}
