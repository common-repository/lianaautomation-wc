<?php
/**
 * LianaAutomation for WooCommerce Order Status handler
 *
 * PHP Version 8.1
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Define the LianaAutomation_WooCommerce_orderstatus callback
 *
 * @param mixed $order_id   WooCommerce order id (of the new order).
 * @param mixed $old_status WooCommerce order id (of the new order).
 * @param mixed $new_status WooCommerce order id (of the new order).
 *
 * @return null
 */
function lianaautomation_wc_orderstatus( $order_id, $old_status, $new_status ) {
	if ( $old_status === $new_status ) {
		return null;
	}

	// Fetch the WooCommerce Order for further processing.
	$order = wc_get_order( $order_id );

	/*
	 * Construct Automation "order/orderrows" events array from WooCommerce items
	 * See also:
	 * https://www.businessbloomer.com/woocommerce-easily-get-order-info-total-items-etc-from-order-object/
	 */
	$line_items          = $order->get_items();
	$automation_events   = array();
	$automation_events[] = array(
		'verb'  => 'order',
		'items' => array(
			'status'                       => $new_status,
			'id'                           => $order_id,
			'total'                        => $order->get_total(),
			'taxes'                        => $order->get_total_tax(),
			'currency'                     => $order->get_currency(),
			'customer_id'                  => $order->get_customer_id(),
			'user_id'                      => $order->get_user_id(),
			'customer_ip_address'          => $order->get_customer_ip_address(),
			'customer_user_agent'          => $order->get_customer_user_agent(),
			'created_via'                  => $order->get_created_via(),
			'customer_note'                => $order->get_customer_note(),
			'billing_first_name'           => $order->get_billing_first_name(),
			'billing_last_name'            => $order->get_billing_last_name(),
			'billing_company'              => $order->get_billing_company(),
			'billing_address_1'            => $order->get_billing_address_1(),
			'billing_address_2'            => $order->get_billing_address_2(),
			'billing_city'                 => $order->get_billing_city(),
			'billing_state'                => $order->get_billing_state(),
			'billing_postcode'             => $order->get_billing_postcode(),
			'billing_country'              => $order->get_billing_country(),
			'billing_email'                => $order->get_billing_email(),
			'billing_phone'                => $order->get_billing_phone(),
			'shipping_first_name'          => $order->get_shipping_first_name(),
			'shipping_last_name'           => $order->get_shipping_last_name(),
			'shipping_company'             => $order->get_shipping_company(),
			'shipping_address_1'           => $order->get_shipping_address_1(),
			'shipping_address_2'           => $order->get_shipping_address_2(),
			'shipping_city'                => $order->get_shipping_city(),
			'shipping_state'               => $order->get_shipping_state(),
			'shipping_postcode'            => $order->get_shipping_postcode(),
			'shipping_country'             => $order->get_shipping_country(),
			'address'                      => $order->get_address(),
			'shipping_address_map_url'     => $order->get_shipping_address_map_url(),
			'formatted_billing_full_name'  => $order->get_formatted_billing_full_name(),
			'formatted_shipping_full_name' => $order->get_formatted_shipping_full_name(),
			'formatted_billing_address'    => $order->get_formatted_billing_address(),
			'formatted_shipping_address'   => $order->get_formatted_shipping_address(),
		),
	);

	foreach ( $line_items as $item_id => $item ) {

		// Get product categories.
		$category_terms = get_the_terms( $item->get_product_id(), 'product_cat' );
		$category_names = array();
		foreach ( $category_terms as $category_term ) {
			$category_names[] = $category_term->name;
		}
		$categories_string = implode( ',', $category_names );

		// Get first product parent categories (string).
		$category_parents_names
			= get_term_parents_list(
				$category_terms[0]->term_id,
				'product_cat',
				array(
					'separator' => ',',
					'link'      => false,
					'inclusive' => true,
				)
			);

		$automation_events[] = array(
			'verb'  => 'orderrow',
			'items' => array(
				'id'                       => $order_id,
				'product'                  => $item->get_name(),
				'product_id'               => $item->get_product_id(),
				'variation_id'             => $item->get_variation_id(),
				'product_name'             => $item->get_name(),
				'quantity'                 => $item->get_quantity(),
				'subtotal'                 => $item->get_subtotal(),
				'total'                    => $item->get_total(),
				'tax'                      => $item->get_subtotal_tax(),
				'taxclass'                 => $item->get_tax_class(),
				'taxstat'                  => $item->get_tax_status(),
				'product_categories'       => $categories_string,
				'product_category_parents' => $category_parents_names,
				'status'                   => $new_status,
			),
		);
	}

	// Use the WooCommerce order billing or shipping email.
	$email = $order->get_billing_email();
	if ( empty( $email ) ) {
		$email = $order->get_shipping_email();
	}

	if ( empty( $email ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'ERROR: No email found on order data. Bailing out.' );
			// phpcs:enable
		}
		return false;
	}

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
	$method       = 'POST';             // Method is always POST.

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

// This hook for order status changes in processing with line_item data intact!
add_action( 'woocommerce_order_status_changed', 'lianaautomation_wc_orderstatus', 10, 3 );
