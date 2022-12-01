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
Version: 3.2.3
Requires at least: 5.8
Requires PHP: 7.2
Author URI: https://ewww.io/
License: GPLv3
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check the PHP version.
if ( ! defined( 'PHP_VERSION_ID' ) || PHP_VERSION_ID < 70200 ) {
	add_action( 'network_admin_notices', 'easyio_unsupported_php' );
	add_action( 'admin_notices', 'easyio_unsupported_php' );
} elseif ( empty( $_GET['easyio_disable'] ) ) {
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
				if ( false === strpos( $upload_dir['basedir'], '://' ) && is_writable( $upload_dir['basedir'] ) ) {
					$easyio_content_dir = trailingslashit( $upload_dir['basedir'] ) . trailingslashit( 'easyio' );
				}
			}
			define( 'EASYIO_CONTENT_DIR', $easyio_content_dir );
		}
	}

	/**
	 * All the 'unique' functions for the core Easy I.O. plugin.
	 */
	require_once( EASYIO_PLUGIN_PATH . 'unique.php' );
	/**
	 * All the base functions for our plugins.
	 */
	require_once( EASYIO_PLUGIN_PATH . 'classes/class-eio-base.php' );
} // End if().

if ( ! function_exists( 'easyio_unsupported_php' ) ) {
	/**
	 * Display a notice that the PHP version is too old.
	 */
	function easyio_unsupported_php() {
		echo '<div id="easyio-warning-php" class="error"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'Easy Image Optimizer requires PHP 7.2 or greater. Newer versions of PHP are faster and more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'easy-image-optimizer' ) . '</a></p></div>';
	}
}
