<?php

/**
 * @package Simple_Oauth2_Client
 * @version 1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: Simple Oauth2 Client
 * Plugin URI:  https://github.com/gundamew/simple-oauth2-client
 * Description: A simple OAuth2 client for WordPress.
 * Version:     1.0.0
 * Author:      Bing-Sheng Chen
 * Author URI:  https://bschen.tw/
 * License:     MIT License
 * Text Domain: simple-oauth2-client
 * Domain Path: /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require plugin_dir_path( __FILE__ ) . 'class-simple-oauth2-client.php';

$client = new Simple_Oauth2_Client( 'simple-oauth2-client', '1.0.0' );

register_activation_hook( __FILE__, array( $client, 'activate' ) );
register_deactivation_hook( __FILE__, array( $client, 'deactivate' ) );

add_action( 'admin_init', array( $client, 'register_simple_oauth2_client_settings' ) );
add_action( 'admin_menu', array( $client, 'plugin_add_options_page' ) );
add_action( 'plugins_loaded', array( $client, 'set_locale' ) );
add_action( 'admin_post_simple_oauth2_client_actions', array( $client, 'simple_oauth2_client_actions' ) );

remove_action( 'authenticate', 'wp_authenticate_username_password' );
remove_action( 'authenticate', 'wp_authenticate_email_password' );
add_action( 'authenticate', array( $client, 'authenticate_with_sso' ), 10, 3 );
