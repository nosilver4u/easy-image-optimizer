<?php
/**
 * Uninstaller for plugin.
 *
 * @link https://ewww.io
 * @package EWWW_Image_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'easyio_exactdn_domain' );
delete_option( 'easyio_exactdn_verified' );
delete_option( 'easyio_exactdn_validation' );
delete_option( 'easyio_exactdn_verify_method' );
delete_option( 'easyio_exactdn' );
delete_option( 'easyio_lazy_load' );
delete_option( 'easyio_debug' );
delete_option( 'easyio_version' );
delete_option( 'easyio_metadata_remove' );
delete_option( 'easyio_enable_help' );
delete_option( 'easyio_enable_help_notice' );
if ( ! get_option( 'ewww_image_optimizer_exactdn' ) ) {
	delete_option( 'exactdn_all_the_things' );
	delete_option( 'exactdn_lossy' );
	delete_option( 'exactdn_hidpi' );
}
