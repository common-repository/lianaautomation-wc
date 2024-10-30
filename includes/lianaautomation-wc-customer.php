<?php
/**
 * LianaAutomation for WooCommerce Customer handler
 *
 * PHP Version 8.1
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Define the lianaautomation_wc_orderstatus callback
 *
 * @param int    $customer_id WooCommerce customer id (of the new customer).
 * @param object $customer    WooCommerce customer object (of the new customer).
 *
 * @return bool
 */
function lianaautomation_wc_customer( $customer_id, $customer ) {

	$email = $customer['user_email'];

	if ( empty( $email ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'ERROR: No email found on customer data. Bailing out.' );
			// phpcs:enable
		}
		return false;
	}

	$automation_events   = array();
	$automation_events[] = array(
		'verb'  => 'customer',
		'items' => array(
			'id'    => $customer_id,
			'login' => $customer['user_login'],
			'role'  => $customer['role'],
			'email' => $customer['user_email'],
		),
	);

	/**
	* Retrieve Liana Options values (Array of All Options)
	*/
	$lianaautomation_wc_options = get_option( 'lianaautomation_wc_options' );

	if ( empty( $lianaautomation_wc_options ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options was empty' );
			// phpcs:enable
		}
		return false;
	}

	// The user id, integer.
	if ( empty( $lianaautomation_wc_options['lianaautomation_user'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options lianaautomation_user empty' );
			// phpcs:enable
		}
		return false;
	}
	$user = $lianaautomation_wc_options['lianaautomation_user'];

	// Hexadecimal secret string.
	if ( empty( $lianaautomation_wc_options['lianaautomation_key'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options lianaautomation_key empty' );
			// phpcs:enable
		}
		return false;
	}
	$secret = $lianaautomation_wc_options['lianaautomation_key'];

	// The base url for our API installation.
	if ( empty( $lianaautomation_wc_options['lianaautomation_url'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options lianaautomation_url empty' );
			// phpcs:enable
		}
		return false;
	}
	$url = $lianaautomation_wc_options['lianaautomation_url'];

	// The realm of our API installation, all caps alphanumeric string.
	if ( empty( $lianaautomation_wc_options['lianaautomation_realm'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options lianaautomation_realm empty' );
			// phpcs:enable
		}
		return false;
	}
	$realm = $lianaautomation_wc_options['lianaautomation_realm'];

	// The channel ID of our automation.
	if ( empty( $lianaautomation_wc_options['lianaautomation_channel'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_wc_options lianaautomation_channel empty' );
			// phpcs:enable
		}
		return false;
	}
	$channel = $lianaautomation_wc_options['lianaautomation_channel'];

	/**
	* General variables
	*/
	$base_path    = 'rest';             // Base path of the api end points.
	$content_type = 'application/json'; // Content will be send as json.
	$method       = 'POST';              // Method is always POST.

	// Import Data.
	$path = 'v1/import';

	$data = array(
		'channel'       => $channel,
		'no_duplicates' => false,
		'data'          => array(
			array(
				'identity' => array(
					'email' => $email,
				),
				'events'   => $automation_events,
			),
		),
	);

	// Encode our body content data.
	$data = wp_json_encode( $data );
	// Get the current datetime in ISO 8601.
	$date = gmdate( 'c' );
	// MD5 hash our body content.
	$content_md5 = md5( $data );
	// Create our signature.
	$signature_content = implode(
		"\n",
		array(
			$method,
			$content_md5,
			$content_type,
			$date,
			$data,
			"/{$base_path}/{$path}",
		),
	);

	$signature = hash_hmac( 'sha256', $signature_content, $secret );
	// Create the authorization header value.
	$auth = "{$realm} {$user}:" . $signature;

	// Create our full stream context with all required headers.
	$ctx = stream_context_create(
		array(
			'http' => array(
				'method'  => $method,
				'header'  => implode(
					"\r\n",
					array(
						"Authorization: {$auth}",
						"Date: {$date}",
						"Content-md5: {$content_md5}",
						"Content-Type: {$content_type}",
					)
				),
				'content' => $data,
			),
		)
	);

	// Build full path, open a data stream, and decode the json response.
	$full_path = "{$url}/{$base_path}/{$path}";

	$fp = fopen( $full_path, 'rb', false, $ctx );

	// If LianaAutomation API settings is invalid or endpoint is not working properly, bail out.
	if ( ! $fp ) {
		return false;
	}
	$response = stream_get_contents( $fp );
	$response = json_decode( $response, true );
}

add_action(
	'woocommerce_created_customer',
	'lianaautomation_wc_customer',
	10,
	3
);
