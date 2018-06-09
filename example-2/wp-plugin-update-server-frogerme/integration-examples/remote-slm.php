<?php // @codingStandardsIgnoreLine
/**
* Integrating Software License Manager and WP Plugin Update Server
* Use this file as a template to create a plugin if you are using a remote installation of Software License Manager plugin.
* This is necessary to add the license signature parameter required by WP Plugin Update when checking for license validity.
* Ignore this file if running WP Plugin Update Server and Software License Manager on the same instalation of WordPress.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'SLM_SIG_DATA_SEPARATOR', '|' );
define( 'SLM_CRYPT_HMAC_SEPARATOR', '-' );
define( 'SLM_SIG_ENCRYPT_KEY', 'encrypt_key_in_wp_plugin_update_server_config' ); // CHANGE THIS VALUE
define( 'SLM_SIG_HMAC_KEY', 'hmac_key_in_wp_plugin_update_server_config' ); // CHANGE THIS VALUE

if ( ! class_exists( 'CryptoUrl' ) ) {

	class CryptoUrl {

		const METHOD        = 'aes-256-cbc';
		const SLASH_REPLACE = '_';

		public static function encrypt( $message, $key ) {
			$key = hex2bin( hash( 'sha256', $key ) );

			if ( mb_strlen( $key, '8bit' ) !== 32 ) {
				throw new Exception( 'Needs a 256-bit key!' );
			}

			$ivsize = openssl_cipher_iv_length( self::METHOD );
			$iv     = openssl_random_pseudo_bytes( $ivsize );

			$ciphertext = openssl_encrypt(
				$message,
				self::METHOD,
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);

			$finalcipher = self::base64url_encode( $iv . $ciphertext );

			return $finalcipher;
		}

		public static function decrypt( $message, $key ) {
			$key = hex2bin( hash( 'sha256', $key ) );

			if ( mb_strlen( $key, '8bit' ) !== 32 ) {
				throw new Exception( 'Needs a 256-bit key!' );
			}

			$message    = self::base64url_decode( $message );
			$ivsize     = openssl_cipher_iv_length( self::METHOD );
			$iv         = mb_substr( $message, 0, $ivsize, '8bit' );
			$ciphertext = mb_substr( $message, $ivsize, null, '8bit' );

			return openssl_decrypt(
				$ciphertext,
				self::METHOD,
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);
		}

		public static function hmac_sign( $message, $key ) {
			$msg_mac = hash_hmac( 'sha256', $message, $key );
			$message = $message;

			$mac = self::base64url_encode( $msg_mac . $message );

			return $mac;
		}

		public static function hmac_verify( $bundle, $key ) {
			$bundle  = self::base64url_decode( $bundle );
			$msg_mac = mb_substr( $bundle, 0, 64, '8bit' );
			$message = mb_substr( $bundle, 64, null, '8bit' );

			return hash_equals(
				hash_hmac( 'sha256', $message, $key ),
				$msg_mac
			);
		}

		public static function hmac_get_message( $bundle ) {
			$bundle  = self::base64url_decode( $bundle );
			$message = mb_substr( $bundle, 64, null, '8bit' );

			return $message;
		}

		public static function base64url_encode( $s ) {

			return str_replace( '/', self::SLASH_REPLACE, base64_encode( $s ) ); // @codingStandardsIgnoreLine
		}

		public static function base64url_decode( $s ) {

			return base64_decode( str_replace( self::SLASH_REPLACE, '/', $s ) ); // @codingStandardsIgnoreLine
		}
	}
}

function my_slm_add_signature( $args ) {
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

		$crypt     = CryptoUrl::encrypt( implode( SLM_SIG_DATA_SEPARATOR, $crypt_payload ), SLM_SIG_ENCRYPT_KEY );
		$hmac      = CryptoUrl::hmac_sign( implode( SLM_SIG_DATA_SEPARATOR, $hmac_payload ), SLM_SIG_HMAC_KEY );
		$signature = $crypt . SLM_CRYPT_HMAC_SEPARATOR . $hmac;

		$args['license_signature'] = $signature;
	}

	return $args;
}
add_filter( 'slm_ap_response_args', 'my_slm_add_signature', 10, 1 );
