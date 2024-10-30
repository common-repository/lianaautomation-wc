<?php
/**
 * Plugin Name:       LianaAutomation for WooCommerce
 * Description:       LianaAutomation for WooCommerce integrates the LianaAutomation marketing automation platform with a WordPress site with the WooCommerce plugin.
 * Version:           1.0.41
 * Requires at least: 5.2
 * Requires PHP:      8.1
 * Author:            Liana Technologies Oy
 * Author URI:        https://www.lianatech.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * Text Domain:       lianaautomation-wc
 * Domain Path:       /languages
 *
 * PHP Version 8.1
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Include cookie handler code
 */
require_once __DIR__ . '/includes/lianaautomation-cookie.php';

/**
 * Include WooCommerce Order Status handler code
 */
require_once __DIR__
	. '/includes/lianaautomation-wc-orderstatus.php';

/**
 * Include WooCommerce Login handler code
 */
require_once __DIR__
	. '/includes/lianaautomation-wc-login.php';

/**
 * Include WooCommerce Customer handler code
 */
require_once __DIR__
	. '/includes/lianaautomation-wc-customer.php';

/**
 * Conditionally include admin panel code
 */
if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-lianaautomation-wc.php';
}
