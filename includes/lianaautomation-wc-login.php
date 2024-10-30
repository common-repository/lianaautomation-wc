<?php
/**
 * LianaAutomation for WooCommerce Login tracker
 *
 * PHP Version 8.1
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Login tracker function
 *
 * Send event when a WordPress user successfully logs in
 *
 * @param string  $user_login      User's login name.
 * @param WP_User $logging_in_user User's WP_User object.
 *
 * @return bool
 */
function lianaautomation_wc_login_send( $user_login, $logging_in_user ) {
	// Gets liana_t tracking cookie if set.
	$liana_t = null;
	if ( isset( $_COOKIE['liana_t'] ) ) {
		$liana_t = sanitize_key( $_COOKIE['liana_t'] );
	} else {
		// liana_t cookie not found, unable to track. Bailing out.
		return false;
	}

	// Get current page URL.
	global $wp;
	$current_url = home_url( add_query_arg( array(), $wp->request ) );

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

	// Alternative user desired marketing_permission key in usermeta.
	$marketing_permission_key = 'marketing_permission';
	if ( ! empty( $lianaautomation_wc_options['lianaautomation_marketing_permission'] ) ) {
		$marketing_permission_key = $lianaautomation_wc_options['lianaautomation_marketing_permission'];
	}

	$user_meta_keys = array();
	if ( ! empty( $lianaautomation_wc_options['lianaautomation_user_meta_keys'] ) ) {
		$user_meta_keys = explode( ',', $lianaautomation_wc_options['lianaautomation_user_meta_keys'] );
	}

	if ( empty( $logging_in_user ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'LianaAutomation-WC ERROR: logging_in_user was empty' );
			// phpcs:enable
		}
		return false;
	}
	if ( ! in_array( 'customer', $logging_in_user->roles, true ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'LianaAutomation-WC ERROR: User was not WooCommerce default customer role, bailing out.' );
			// phpcs:enable
		}
		return false;
	}
	$current_user_email = $logging_in_user->user_email;
	if ( empty( $current_user_email ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'LianaAutomation-WC ERROR: user_email was empty' );
			// phpcs:enable
		}
		return false;
	}

	/**
	* General variables
	*/
	$base_path    = 'rest';             // Base path of the api end points.
	$content_type = 'application/json'; // Content will be send as json.
	$method       = 'POST';             // Method is always POST.

	/**
	 * Send a API request to LianaAutomation
	 *
	 * This function will add the required headers and
	 * calculates the signature for the authorization header
	 *
	 * @param string $path The path of the end point
	 * @param array  $data The content body (data) of the request
	 *
	 * @return mixed
	 */
	// Import Data.
	$path = 'v1/import';

	// Build the identity array.
	$identity = array();
	if ( ! empty( $current_user_email ) ) {
		$identity['email'] = $current_user_email;
	}
	if ( ! empty( $liana_t ) ) {
		$identity['token'] = $liana_t;
	}
	// Bail out if no identities found.
	if ( empty( $identity ) ) {
		return false;
	}

	// Try to fetch the marketing permission from the user meta.
	$marketing_permission = get_user_meta( $logging_in_user->ID, $marketing_permission_key, true );

	$data = null;

	if ( isset( $marketing_permission_key ) && isset( $marketing_permission ) && ! empty( $marketing_permission ) ) {
		$customer_items = array(
			'url'                     => $current_url,
			'username'                => $user_login,
			'email'                   => $logging_in_user->user_email,
			$marketing_permission_key => $marketing_permission,
		);
		foreach ( $user_meta_keys as $user_meta_key ) {
			// If the key is separated by comma and space, trim the spaces.
			$user_meta_key = trim( $user_meta_key );
			if ( 'locale' === $user_meta_key ) {
				$user_meta_value = get_user_locale( $logging_in_user->ID );
			} else {
				$user_meta_value = get_user_meta( $logging_in_user->ID, $user_meta_key, true );
			}
			if ( ! empty( $user_meta_value ) ) {
				// convert arrays and objects to string.
				if ( is_array( $user_meta_value ) || is_object( $user_meta_value ) ) {
					$user_meta_value = wp_json_encode( $user_meta_value );
				}
				// Automation doesnt like if key starts with underscore.
				$ma_user_meta_key = ltrim( $user_meta_key, '_' );
				$customer_items[ $ma_user_meta_key ] = $user_meta_value;
			}
		}

		$data = array(
			'channel'       => $channel,
			'no_duplicates' => false,
			'data'          => array(
				array(
					'identity' => $identity,
					'events'   => array(
						array(
							'verb'  => 'login',
							'items' => array(
								'url'      => $current_url,
								'username' => $user_login,
							),
						),
						array(
							'verb'  => 'customer',
							'items' => $customer_items,
						),
					),
				),
			),
		);
	} else {
		$data = array(
			'channel'       => $channel,
			'no_duplicates' => false,
			'data'          => array(
				array(
					'identity' => $identity,
					'events'   => array(
						array(
							'verb'  => 'login',
							'items' => array(
								'url'      => $current_url,
								'username' => $user_login,
							),
						),
					),
				),
			),
		);
	}

	// Encode our body content data.
	$data = wp_json_encode( $data );
	// Get the current datetime in ISO 8601.
	$date = gmdate( 'c' );
	// md5 hash our body content.
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
	if ( ! $fp ) {
		// API failed to connect.
		return null;
	}
	$response = stream_get_contents( $fp );
	$response = json_decode( $response, true );
}

add_action( 'wp_login', 'lianaautomation_wc_login_send', 10, 2 );
