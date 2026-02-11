<?php
/**
 * Plugin Name: Paid Memberships Pro - Member RSS Add On
 * Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-member-rss/
 * Description: Create Member-Specific RSS Feeds for Paid Memberships Pro
 * Version: 0.3
 * Author: Stranger Studios
 * Author URI: http://www.strangerstudios.com
 * Text Domain: pmpro-member-rss
 * Domain Path: /languages
 * License: GPLv2 or later
*/

/*
	1. Generate a random key for each user.
	2. Check for &memberkey param in RSS URL.
	3. If memberkey is present and valid, don't restrict content in RSS.
	
	To add more RSS URLs, use the pmpromrss_feeds filter.
*/
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PMPROMRSS_VERSION', '0.3' );
define( 'PMPROMRSS_DIR', dirname( __FILE__ ) );

require_once( PMPROMRSS_DIR . '/includes/functions.php' );
require_once( PMPROMRSS_DIR . '/includes/admin.php' );

/**
 * Load the plugin text domain.
 * 
 * @since TBD
 *
 * @return void
 */
function pmpromrss_load_plugin_textdomain() {
	load_plugin_textdomain( 'pmpro-member-rss', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmpromrss_load_plugin_textdomain' );


/**
 * Function to add links to the plugin row meta
 * 
 * @since 0.1 
 */
function pmpromrss_plugin_row_meta( $links, $file ) {
	if( strpos( $file, 'pmpro-member-rss.php' ) !== false ) {
		$new_links = array(
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/member-rss/' ) . '" title="' . esc_attr__( 'View Documentation', 'pmpro-member-rss' ) . '">' . esc_html__( 'Docs', 'pmpro-member-rss' ) . '</a>',
			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'pmpro-member-rss' ) . '">' . esc_html__( 'Support', 'pmpro-member-rss' ) . '</a>',
		);
		$links = array_merge( $links, $new_links );
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpromrss_plugin_row_meta', 10, 2 );