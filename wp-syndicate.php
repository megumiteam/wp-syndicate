<?php
/**
 * Plugin Name: WP Syndicate
 * Plugin URI: http://digitalcube.jp
 * Description: It is a plug-in that WP Syndicate takes in an RSS feed, it is possible to capture the content of other sites on the WordPress site.
 * Author: horike
 * Version: 1.2
 * Author URI: http://digitalcube.jp
 * License: GPL2+
 * @package WordPress
 */

if ( ! defined( 'WPSYND_DOMAIN' ) ) {
	define( 'WPSYND_DOMAIN', 'wp-syndicate' ); }

if ( ! defined( 'WPSYND_PLUGIN_URL' ) ) {
	define( 'WPSYND_PLUGIN_URL', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) ); }

if ( ! defined( 'WPSYND_PLUGIN_DIR' ) ) {
	define( 'WPSYND_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) ); }

load_plugin_textdomain( WPSYND_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
require_once( dirname( __FILE__ ) . '/lib/class-wp-post-helper.php' );
require_once( dirname( __FILE__ ) . '/lib/class-logger.php' );
$portal_db_log_operator = new WP_SYND_Log_Operator();
register_activation_hook( __FILE__, array( $portal_db_log_operator, 'set_event' ) );
register_deactivation_hook( __FILE__, array( $portal_db_log_operator, 'delete_event' ) );


$hack_dir = trailingslashit( dirname( __FILE__ ) ) . 'modules/';
opendir( $hack_dir );
while ( ($ent = readdir()) !== false ) {
	if ( ! is_dir( $ent ) && strtolower( substr( $ent,-4 ) ) === '.php' ) {
		require_once( $hack_dir.$ent );
	}
}
closedir();
