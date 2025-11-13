<?php
/**
 * Loader for Easy IO plugin.
 *
 * This file bootstraps the rest of the Easy IO plugin after some basic checks.
 *
 * @link https://ewww.io/easy/
 * @package Easy_Image_Optimizer
 */

/*
Plugin Name: Easy Image Optimizer
Plugin URI: https://wordpress.org/plugins/easy-image-optimizer/
Description: Easily speed up your website to better connect with your visitors. Properly compress and size/scale images. Includes lazy load and WebP auto-convert.
Author: Exactly WWW
Version: 4.2.1.6
Requires at least: 6.6
Requires PHP: 8.1
Author URI: https://ewww.io/
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EasyIO\Plugin' ) && ! str_contains( add_query_arg( '', '' ), 'easyio_disable=1' ) ) {
	define( 'EASYIO_VERSION', 421.613 );

	/**
	 * The full path of the plugin file (this file).
	 *
	 * @var string EASYIO_PLUGIN_FILE
	 */
	define( 'EASYIO_PLUGIN_FILE', __FILE__ );
	/**
	 * The path of the plugin file relative to the plugins/ folder.
	 *
	 * @var string EASYIO_PLUGIN_FILE_REL
	 */
	define( 'EASYIO_PLUGIN_FILE_REL', plugin_basename( __FILE__ ) );
	/**
	 * This is the full system path to the plugin folder.
	 *
	 * @var string EASYIO_PLUGIN_PATH
	 */
	define( 'EASYIO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

	if ( ! defined( 'EASYIO_CONTENT_DIR' ) ) {
		if ( defined( 'EWWWIO_CONTENT_DIR' ) ) {
			define( 'EASYIO_CONTENT_DIR', EWWWIO_CONTENT_DIR );
		} else {
			$easyio_content_dir = trailingslashit( WP_CONTENT_DIR ) . trailingslashit( 'easyio' );
			if ( ! is_writable( WP_CONTENT_DIR ) || ! empty( $_ENV['PANTHEON_ENVIRONMENT'] ) ) {
				$upload_dir = wp_get_upload_dir();
				if ( ! str_contains( $upload_dir['basedir'], '://' ) && is_writable( $upload_dir['basedir'] ) ) {
					$easyio_content_dir = trailingslashit( $upload_dir['basedir'] ) . trailingslashit( 'easyio' );
				}
			}
			define( 'EASYIO_CONTENT_DIR', $easyio_content_dir );
		}
	}

	/**
	 * All the base functions for our plugins.
	 */
	require_once EASYIO_PLUGIN_PATH . 'classes/class-base.php';
	/**
	 * The setup functions for Easy IO.
	 */
	require_once EASYIO_PLUGIN_PATH . 'classes/class-plugin.php';
	/**
	 * The main function to return a single EasyIO\Plugin object to functions elsewhere.
	 *
	 * @return object object|EasyIO\Plugin The one true EasyIO\Plugin instance.
	 */
	function easyio() {
		return EasyIO\Plugin::instance();
	}
	easyio();
} // End if().
