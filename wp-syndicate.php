<?php 
/*
Plugin Name: WP Syndicate
Plugin URI: http://digitalcube.jp
Description: It is a plug-in that WP Syndicate takes in an RSS feed, it is possible to capture the content of other sites on the WordPress site.
Author: horike
Version: 1.1
Author URI: http://digitalcube.jp

Copyright 2014 horike (email : horike37@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'WPSYND_DOMAIN' ) )
	define( 'WPSYND_DOMAIN', 'wp-syndicate' );
	
if ( ! defined( 'WPSYND_PLUGIN_URL' ) )
	define( 'WPSYND_PLUGIN_URL', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ));

if ( ! defined( 'WPSYND_PLUGIN_DIR' ) )
	define( 'WPSYND_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ));

load_plugin_textdomain( WPSYND_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages' );
	
require_once( WPSYND_PLUGIN_DIR . '/lib/class-wp_post_helper.php' );
require_once( WPSYND_PLUGIN_DIR . '/lib/class-logger.php' );
$portal_db_log_operator = new WP_SYND_Log_Operator();
register_activation_hook( __FILE__, array( $portal_db_log_operator, 'set_event' ) );
register_deactivation_hook( __FILE__, array( $portal_db_log_operator, 'delete_event' ) );


$hack_dir = trailingslashit(dirname(__FILE__)) . 'modules/';
opendir($hack_dir);
while(($ent = readdir()) !== false) {
    if(!is_dir($ent) && strtolower(substr($ent,-4)) == ".php") {
        require_once($hack_dir.$ent);
    }
}
closedir();

