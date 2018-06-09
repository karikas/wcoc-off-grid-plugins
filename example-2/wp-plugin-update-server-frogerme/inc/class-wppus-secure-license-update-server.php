<?php

class Wppus_Secure_License_Update_Server extends Wppus_Update_Server {

	protected $license_key;
	protected $secret_key;
	protected $license_signature;
	protected $hmac_key;
	protected $crypto_key;
	protected $license_server_url;
	protected $license_check_signature;

	const DATA_SEPARATOR       = '|';
	const CRYPT_HMAC_SEPARATOR = '-';

	public function __construct(
		$use_remote_repository,
		$server_url,
		$server_directory,
		$repository_service_url,
		$repository_branch,
		$repository_credentials,
		$repository_service_self_hosted,
		$repository_check_frequency,
		$license_server_url,
		$crypto_key,
		$hmac_key,
		$license_check_signature
		) {
		parent::__construct(
			$use_remote_repository,
			$server_url,
			$server_directory,
			$repository_service_url,
			$repository_branch,
			$repository_credentials,
			$repository_service_self_hosted,
			$repository_check_frequency
		);

		$this->license_server_url      = $license_server_url;
		$this->hmac_key                = $hmac_key;
		$this->crypto_key              = $crypto_key;
		$this->repository_service_url  = $repository_service_url;
		$this->license_check_signature = $license_check_signature;
	}

	protected function initRequest( $query = null, $headers = null ) {
		$request = parent::initRequest( $query, $headers );
		$license = null;

		if ( $request->param( 'license_key' ) && $request->param( 'secret_key' ) ) {
			$result = $this->verifyLicenseExists(
				$request->slug,
				$request->param( 'license_key' ),
				$request->param( 'secret_key' ),
				$request->param( 'license_signature' )
			);

			$request->license_key       = $request->param( 'license_key' );
			$request->secret_key        = $request->param( 'secret_key' );
			$request->license_signature = $request->param( 'license_signature' );
			$request->license           = $result;

			$this->license_key       = $request->license_key;
			$this->secret_key        = $request->secret_key;
			$this->license_signature = $request->license_signature;
		}

		return $request;
	}

	protected function filterMetadata( $meta, $request ) {
		$meta              = parent::filterMetadata( $meta, $request );
		$license           = $request->license;
		$license_signature = $request->license_signature;

		if ( null !== $license ) {
			$meta['license'] = $this->prepareLicenseForOutput( $license );
		}

		if ( $this->isLicenseValid( $license, $license_signature ) ) {
			$args                 = array(
				'update_license_key'       => $request->license_key,
				'update_secret_key'        => $request->secret_key,
				'update_license_signature' => $request->license_signature,
			);
			$meta['download_url'] = self::addQueryArg( $args, $meta['download_url'] );
		} else {
			unset( $meta['download_url'] );

			$meta['license_error'] = $this->get_license_error_message( $license );
		}

		return $meta;
	}

	protected function checkAuthorization( $request ) {
		parent::checkAuthorization( $request );

		$license           = $request->license;
		$license_signature = $request->license_signature;

		if ( 'download' === $request->action && ! ( $this->isLicenseValid( $license, $license_signature ) ) ) {

			if ( ! isset( $license ) ) {
				$message = 'An active license key is required to download or update this plugin.';
			} else {
				$message = $this->get_license_error_message( $license );
			}

			$this->exitWithError( $message, 403 );
		}
	}

	protected function generateDownloadUrl( Wpup_Package $package ) {
		$query = array(
			'update_action'            => 'download',
			'plugin_id'                => $package->slug,
			'update_secret_key'        => $this->secret_key,
			'update_license_key'       => $this->license_key,
			'update_license_signature' => $this->license_signature,
		);

		return self::addQueryArg( $query, $this->serverUrl ); // @codingStandardsIgnoreLine
	}

	protected function get_license_error_message( $license ) {

		if ( ! $license ) {
			return 'An active license key is required to download or update this plugin.';
		}

		if ( is_wp_error( $license ) ) {
			return $license->get_error_message( 'invalid_license' );
		}

		switch ( $license->status ) {
			case 'blocked':
				$error = 'The associated license has been blocked.';
				return $error;
			case 'expired':
				$error = 'The associated license has expired on ' . $license->date_expiry;
				return $error;
			case 'pending':
				$error = 'The associated license is pending activation.';
				return $error;
			default:
				$error = 'Invalid License key. Please contact the developer for help or buying a license.';
				return $error;
		}
	}

	protected function verifyLicenseExists( $slug, $license_key, $secret_key, $license_signature ) {
		$result     = null;
		$api_params = array(
			'slm_action'  => 'slm_check',
			'secret_key'  => $secret_key,
			'license_key' => $license_key,
		);
		$response   = wp_remote_get( add_query_arg( $api_params, $this->license_server_url ), array(
			'timeout'   => 20,
			'sslverify' => false,
		) );

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $license_data ) ) {
			$result = new WP_Error(
				'Wppus_Secure_License_Update_Server::verifyLicenseExists',
				'Could not find license data'
			);
		} elseif ( 'error' === $license_data->result && isset( $license_data->error_code ) && 60 === absint( $license_data->error_code ) ) {
			$result = new WP_Error(
				'Wppus_Secure_License_Update_Server::verifyLicenseExists',
				$license_data->message,
				$license_data->data
			);
		} else {
			$result = $license_data;
		}

		return $result;
	}

	protected function prepareLicenseForOutput( $license ) {
		$output = null;

		if ( is_plugin_active( 'software-license-manager/slm_bootstrap.php' ) ) {
			// @TODO: format properly
			$output = print_r( $license, true );
		}

		return $output;
	}

	protected function isLicenseValid( $license, $license_signature ) {
		$valid = false;

		if ( ! $this->license_check_signature ) {

			foreach ( $license->registered_domains as $domain_info ) {

				$condition = $this->license_key === $domain_info->lic_key;
				$condition = $condition && $domain_info->item_reference === $license->product_ref;

				if ( $condition ) {
					$valid = true;

					break;
				}
			}
		} elseif ( $license && $license_signature && ! is_wp_error( $license ) && 'active' === $license->status ) {
			$raw_data = explode( self::CRYPT_HMAC_SEPARATOR, $license_signature );
			$hmac     = end( $raw_data );
			$crypt    = reset( $raw_data );
			$payload  = null;

			if ( ! ( empty( $crypt ) || empty( $hmac ) || ! CryptoUrl::hmac_verify( $hmac, $this->hmac_key ) ) ) {

				try {
					$payload = CryptoUrl::decrypt( $crypt, $this->crypto_key );
				} catch ( Exception $e ) {
					$payload = false;
				}

				if ( $payload ) {

					$data              = explode( self::DATA_SEPARATOR, $payload );
					$registered_domain = isset( $data[0] ) ? $data[0] : null;
					$item_reference    = isset( $data[1] ) ? $data[1] : null;

					foreach ( $license->registered_domains as $domain_info ) {

						$condition = $this->license_key === $domain_info->lic_key;
						$condition = $condition && $domain_info->registered_domain === $registered_domain;
						$condition = $condition && $domain_info->item_reference === $item_reference;

						if ( $condition ) {
							$valid = true;

							break;
						}
					}
				}
			}
		}

		return $valid;
	}

}
