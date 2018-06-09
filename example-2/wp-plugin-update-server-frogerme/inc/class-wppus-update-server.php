<?php

class Wppus_Update_Server extends Wpup_UpdateServer {

	protected $server_directory;
	protected $use_remote_repository;
	protected $repository_service_url;
	protected $repository_branch;
	protected $repository_credentials;
	protected $repository_service_self_hosted;
	protected $repository_check_frequency;
	protected $update_checker;
	protected $plugin_file_name;
	protected $type;
	protected $debug;

	public function __construct(
		$use_remote_repository,
		$server_url,
		$server_directory = null,
		$repository_service_url = null,
		$repository_branch = 'master',
		$repository_credentials = null,
		$repository_service_self_hosted = false,
		$repository_check_frequency = 'daily'
	) {
		parent::__construct( $server_url, $server_directory );

		$this->use_remote_repository          = $use_remote_repository;
		$this->server_directory               = $server_directory;
		$this->repository_service_self_hosted = $repository_service_self_hosted;
		$this->repository_service_url         = $repository_service_url;
		$this->repository_branch              = $repository_branch;
		$this->repository_credentials         = $repository_credentials;
		$this->repository_check_frequency     = $repository_check_frequency;
		$this->debug                          = (bool) ( constant( 'WP_DEBUG' ) );
	}

	protected function initRequest( $query = null, $headers = null ) {
		$request = parent::initRequest( $query, $headers );
		$license = null;

		if ( $request->param( 'type' ) ) {
			$request->type = $request->param( 'type' );
			$this->type    = ucfirst( $request->type );
		}

		if ( $request->param( 'plugin_file_name' ) ) {
			$request->plugin_file_name = $request->param( 'plugin_file_name' );
			$this->plugin_file_name    = $request->param( 'plugin_file_name' );
		}

		return $request;
	}

	protected function generateDownloadUrl( Wpup_Package $package ) {
		$query = array(
			'update_action' => 'download',
			'plugin_id'     => $package->slug,
		);

		return self::addQueryArg( $query, $this->serverUrl ); // @codingStandardsIgnoreLine
	}

	protected function findPackage( $slug, $check_remote = true ) {
		$safe_slug = preg_replace( '@[^a-z0-9\-_\.,+!]@i', '', $slug );
		$filename  = $this->packageDirectory . '/' . $safe_slug . '.zip'; // @codingStandardsIgnoreLine

		if ( ! is_file( $filename ) || ! is_readable( $filename ) ) {
			$re_check_local = false;

			if ( $this->use_remote_repository && $this->repository_service_url ) {

				if ( $check_remote ) {
					$re_check_local = $this->save_remote_package_to_local( $safe_slug );
				}
			} else {
				wp_clear_scheduled_hook( 'wppus_check_remote_' . $safe_slug, array( $safe_slug ) );
			}

			if ( $re_check_local ) {

				return $this->findPackage( $slug, false );
			} else {

				return null;
			}
		}

		if ( $this->use_remote_repository && $this->repository_service_url ) {

			if ( ! wp_next_scheduled( 'wppus_check_remote_' . $safe_slug, array( $safe_slug ) ) ) {
				wp_schedule_event(
					current_time( 'timestamp' ),
					$this->repository_check_frequency,
					'wppus_check_remote_' . $safe_slug,
					array( $safe_slug )
				);
			}
		}

		return call_user_func( $this->packageFileLoader, $filename, $slug, $this->cache ); // @codingStandardsIgnoreLine
	}

	public function save_remote_package_to_local( $safe_slug ) {
		$transient_slug     = ( strlen( $safe_slug ) > 29 ) ? substr( $safe_slug, 0, 29 ) : $safe_slug;
		$doing_proxy_update = get_transient( 'wppus_maybe_' . $transient_slug . '_proxy_update' );
		$local_ready        = false;

		if ( ! $doing_proxy_update ) {
			set_transient( 'wppus_maybe_' . $transient_slug . '_proxy_update', 1 );
			$this->init_update_checker( $safe_slug );

			if ( $this->update_checker ) {

				try {
					$info = $this->update_checker->requestInfo();

					if ( $info && ! is_wp_error( $info ) ) {
						$this->remove_package( $safe_slug );

						$package    = $this->download_remote_package( $info['download_url'] );
						$downloaded = $this->clean_package_from_remote( $package, $safe_slug );

						if ( $downloaded ) {
							$local_ready = true;
						}
					}
				} catch ( Exception $e ) {
					delete_transient( 'wppus_maybe_' . $transient_slug . '_proxy_update' );

					throw $e;
				}
			}

			delete_transient( 'wppus_maybe_' . $transient_slug . '_proxy_update' );
		}

		return $local_ready;
	}

	public function remove_package( $slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		$filepath = $this->packageDirectory . '/' . $slug . '.zip'; // @codingStandardsIgnoreLine

		if ( $wp_filesystem->is_file( $filepath ) ) {
			$wp_filesystem->delete( $filepath );
		}
	}

	protected function clean_package_from_remote( $package, $slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		$return        = true;
		$error_message = __METHOD__ . ': ';

		if ( is_wp_error( $package ) ) {
			$return         = false;
			$error_message .= $package->get_error_message();
		}

		if ( $return && ! $package ) {
			$return         = false;
			$error_message .= __( '$package variable cannot be empty.' );
		}

		if ( $return && ! $wp_filesystem ) {
			$return         = false;
			$error_message .= __( ' unavailable file system.' );
		}

		if ( $return ) {
			$source      = $package;
			$destination = $this->packageDirectory . '/' . $slug . '.zip'; // @codingStandardsIgnoreLine
			$result      = $wp_filesystem->move( $source, $destination, true );
			$temp_path   = $this->packageDirectory . '/' . $slug . '-tmp/'; // @codingStandardsIgnoreLine

			if ( ! is_dir( $temp_path ) ) {
				$wp_filesystem->mkdir( $temp_path );
				$wp_filesystem->chmod( $temp_path, 0755, true );
			}

			if ( $result ) {
				$package       = $destination;
				$repack_result = $this->repack_package_from_remote( $package, $temp_path, $slug );

				if ( ! $repack_result ) {
					$return         = false;
					$error_message .= sprintf( // @codingStandardsIgnoreLine
						'Could not repack %s.',
						esc_html( dirname( $package ) . '/' . $slug . '.zip' )
					);
				}

				$return = $repack_result;

			} else {
				$return         = false;
				$error_message .= sprintf( // @codingStandardsIgnoreLine
					'Could not rename %s to %s - could not move the file.',
					esc_html( $package ),
					esc_html( dirname( $package ) . '/' . $slug . '.zip' )
				);
			}
		}

		if ( ! $return ) {

			if ( $this->debug ) {
				trigger_error( $error_message, E_USER_WARNING ); // @codingStandardsIgnoreLine
			}

			$wp_filesystem->delete( $package, true );
		}

		return $return;
	}

	protected function repack_package_from_remote( $package, $temp_path, $slug ) {
		WP_Filesystem();

		global $wp_filesystem;

		$unzipped      = $this->unzip_package( $package, $temp_path );
		$return        = true;
		$error_message = __METHOD__ . ': ';

		$wp_filesystem->delete( $package, true );

		$package = $temp_path . $slug;

		if ( ! $unzipped ) {
			$return         = false;
			$error_message .= sprintf( // @codingStandardsIgnoreLine
				'Could not unzip %s to %s.',
				esc_html( $package ),
				esc_html( $temp_path )
			);
		}

		if ( $return ) {
			$content         = array_diff( scandir( $temp_path ), array( '..', '.' ) );
			$maybe_directory = $temp_path . reset( $content );

			if ( ( 1 === count( $content ) && is_dir( $maybe_directory ) ) ) {
				$directory = $maybe_directory;
				$zip       = $this->packageDirectory . '/' . $slug . '.zip'; // @codingStandardsIgnoreLine

				$wp_filesystem->move( $directory, $temp_path . $slug, true );
				$wp_filesystem->chmod( $temp_path, false, true );

				$zipped = $this->zip_package( $temp_path, $zip );

				if ( $zipped ) {
					$wp_filesystem->chmod( $zip, 0755 );
				} else {
					$return         = false;
					$error_message .= sprintf( // @codingStandardsIgnoreLine
						'Could not create archive from %s to %s - zipping failed',
						esc_html( $temp_path ),
						esc_html( $zip )
					);
				}
			} else {
				$return         = false;
				$error_message .= sprintf( // @codingStandardsIgnoreLine
					'Could not create archive for %s - invalid remote package (must contain only one directory)',
					esc_html( $package ),
					esc_html( dirname( $package ) . '/' . $slug . '.zip' )
				);
			}
		}

		$wp_filesystem->delete( $temp_path, true );

		if ( ! $return ) {

			if ( $this->debug ) {
				trigger_error( $error_message, E_USER_WARNING ); // @codingStandardsIgnoreLine
			}
		}

		return $return;
	}

	protected function unzip_package( $source, $destination ) {

		return unzip_file( $source, $destination );
	}

	protected function zip_package( $source, $destination, $container_dir = '' ) {
		global $wp_filesystem;

		$zip = new ZipArchive();

		if ( ! $zip->open( $destination, ZIPARCHIVE::CREATE ) ) {

			return false;
		}

		if ( ! empty( $container_dir ) ) {
			$container_dir = trailingslashit( $container_dir );
		}

		$source = str_replace( '\\', '/', realpath( $source ) );

		if ( true === $wp_filesystem->is_dir( $source ) ) {

			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$source
				)
			);

			$it->rewind();

			while ( $it->valid() ) {

				if ( ! $it->isDot() ) {
					$file      = str_replace( '\\', '/', $it->key() );
					$file_name = $it->getSubPathName();

					if ( true === $wp_filesystem->is_dir( $file ) ) {
						$dir_name = $container_dir . trailingslashit( $file_name );

						$zip->addEmptyDir( $dir_name );
					} elseif ( true === $wp_filesystem->is_file( $file ) ) {
						$zip->addFromString( $container_dir . $file_name, $wp_filesystem->get_contents( $file ) );
					}
				}

				$it->next();
			}
		} elseif ( true === $wp_filesystem->is_file( $source ) && '.' !== $file && '..' !== $file ) {
			$file_name = str_replace( ' ', '', basename( $source ) );

			if ( ! empty( $file_name ) ) {
				$zip->addFromString( $file_name, $wp_filesystem->get_contents( $source ) );
			}
		}

		return $zip->close();
	}

	public function set_type( $type ) {

		if ( 'Plugin' === ucfirst( $type ) || 'Theme' === ucfirst( $type ) ) {
			$this->type = $type;
		}
	}

	protected function init_update_checker( $slug ) {

		if ( $this->update_checker ) {

			return;
		}

		if ( $this->repository_service_self_hosted ) {

			if ( 'Plugin' === $this->type ) {
				$this->update_checker = new Proxuc_Vcs_PluginUpdateChecker(
					new Puc_v4p4_Vcs_GitLabApi( trailingslashit( $this->repository_service_url ) . $slug ),
					$slug,
					$this->plugin_file_name,
					$this->packageDirectory // @codingStandardsIgnoreLine
				);
			} elseif ( 'Theme' === $this->type ) {
				$this->update_checker = new Proxuc_Vcs_ThemeUpdateChecker(
					new Puc_v4p4_Vcs_GitLabApi( trailingslashit( $this->repository_service_url ) . $slug ),
					$slug,
					$this->plugin_file_name,
					$this->packageDirectory // @codingStandardsIgnoreLine
				);
			}
		} else {
			$this->update_checker = Proxuc_Factory::buildUpdateChecker(
				trailingslashit( $this->repository_service_url ) . $slug,
				$slug,
				$this->plugin_file_name,
				$this->type,
				$this->packageDirectory // @codingStandardsIgnoreLine
			);
		}

		if ( $this->repository_credentials ) {
			$this->update_checker->setAuthentication( $this->repository_credentials );
		}

		if ( $this->repository_branch ) {
			$this->update_checker->setBranch( $this->repository_branch );
		}
	}

	public function check_remote_update( $safe_slug ) {
		$has_update = false;

		$local_package = $this->findPackage( $safe_slug );

		if ( null !== $local_package ) {
			$package_path = $local_package->getFileName();
			$local_meta   = WshWordPressPackageParser::parsePackage( $package_path, true );
			$local_info   = array(
				'type'         => $local_meta['type'],
				'version'      => $local_meta['header']['Version'],
				'main_file'    => $local_meta['pluginFile'],
				'download_url' => '',
			);

			$this->type = ucfirst( $local_info['type'] );

			if ( 'Plugin' === $this->type ) {
				$this->plugin_file_name = str_replace( trailingslashit( $safe_slug ), '', str_replace( '.php', '', $local_info['main_file'] ) );
			} elseif ( 'Theme' === $this->type ) {
				$this->plugin_file_name = 'style.css';
			}

			if ( $this->plugin_file_name ) {
				$remote_info = null;

				$this->init_update_checker( $safe_slug );

				$remote_info = $this->update_checker->requestInfo();

				if ( $remote_info && ! is_wp_error( $remote_info ) ) {
					$has_update = version_compare( $remote_info['version'], $local_info['version'], '>' );
				}
			}
		} else {
			$has_update = true;
		}
		$has_update = true;

		return $has_update;
	}

	protected function download_remote_package( $url, $timeout = 300 ) {

		if ( ! $url ) {
			return new WP_Error( 'http_no_url', __( ' Invalid URL Provided.' ) );
		}

		$local_filename = wp_tempnam( $url );

		if ( ! $local_filename ) {

			return new WP_Error( 'http_no_file', __( 'Could not create Temporary file.' ) );
		}

		$response = wp_safe_remote_get( $url, array(
			'timeout'  => $timeout,
			'stream'   => true,
			'filename' => $local_filename,
		) );

		if ( is_wp_error( $response ) ) {
			unlink( $local_filename );

			return $response;
		}

		if ( 200 !== absint( wp_remote_retrieve_response_code( $response ) ) ) {
			unlink( $local_filename );

			return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ) );
		}

		$content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );

		if ( $content_md5 ) {
			$md5_check = verify_file_md5( $local_filename, $content_md5 );

			if ( is_wp_error( $md5_check ) ) {
				unlink( $local_filename );

				return $md5_check;
			}
		}

		return $local_filename;
	}

}
