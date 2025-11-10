<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package EIO
 */

namespace EasyIO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML element and attribute parsing, replacing, etc.
 */
final class Settings extends Base {

	/**
	 * Handle the plugin settings and options page.
	 */
	public function __construct() {
		parent::__construct();

		\add_action( 'admin_init', array( $this, 'admin_init' ), 9 );

		// Activation routine for Easy IO/ExactDN.
		\add_action( 'admin_action_easyio_activate', array( $this, 'activate_service' ) );
		// De-activation routine for Easy IO/ExactDN.
		\add_action( 'admin_action_easyio_deactivate', array( $this, 'deactivate_service' ) );
	}

	/**
	 * Setup options for wp-admin.
	 */
	public function admin_init() {
		$this->upgrade();
		$this->register_settings();
	}

	/**
	 * Plugin upgrade function
	 *
	 * @global object $wpdb
	 */
	private function upgrade() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		if ( \get_option( 'easyio_version' ) < EASYIO_VERSION ) {
			if ( \wp_doing_ajax() ) {
				return;
			}
			$this->set_defaults();
			// This will get re-enabled if things are too slow.
			\update_option( 'exactdn_prevent_db_queries', true );
			if ( $this->get_option( 'easyio_exactdn_verify_method' ) > 0 ) {
				\delete_option( 'easyio_exactdn_verify_method' );
				\delete_site_option( 'easyio_exactdn_verify_method' );
			}
			if ( \function_exists( 'swis' ) && $this->get_option( 'easyio_ll_all_things' ) ) {
				\update_option( 'easyio_ll_external_bg', true );
				\update_option( 'easyio_ll_all_things', '' );
			}
			if ( ! \get_option( 'easyio_version' ) && ! $this->get_option( 'easyio_exactdn' ) ) {
				\add_option( 'exactdn_never_been_active', true, '', false );
			}
			\update_option( 'easyio_version', EASYIO_VERSION );
		}
	}

	/**
	 * Register all our options and sanitation functions.
	 */
	public function register_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Register all the common Easy IO settings.
		\register_setting( 'easyio_options', 'easyio_debug', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_enable_help', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_all_the_things', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_lossy', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_hidpi', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_exclude', array( $this, 'exclude_paths_sanitize' ) );
		\register_setting( 'easyio_options', 'easyio_add_missing_dims', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_lazy_load', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_ll_abovethefold', 'intval' );
		\register_setting( 'easyio_options', 'easyio_use_lqip', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_use_dcip', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_ll_external_bg', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_ll_all_things', 'sanitize_textarea_field' );
		\register_setting( 'easyio_options', 'easyio_ll_exclude', array( $this, 'exclude_paths_sanitize' ) );
	}

	/**
	 * Set some default option values.
	 */
	public function set_defaults() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Set defaults for all options that need to be autoloaded.
		\add_option( 'easyio_debug', false );
		\add_option( 'easyio_metadata_remove', true );
		\add_option( 'easyio_exactdn', false );
		\add_option( 'easyio_plan_id', 0 );
		\add_option( 'exactdn_all_the_things', false );
		\add_option( 'exactdn_lossy', false );
		\add_option( 'exactdn_hidpi', false );
		\add_option( 'exactdn_exclude', '' );
		\add_option( 'exactdn_sub_folder', false );
		\add_option( 'exactdn_prevent_db_queries', true );
		\add_option( 'exactdn_asset_domains', '' );
		\add_option( 'easyio_add_missing_dims', false );
		\add_option( 'easyio_lazy_load', false );
		\add_option( 'easyio_use_lqip', false );
		\add_option( 'easyio_use_dcip', false );
		\add_option( 'easyio_use_siip', false );
		\add_option( 'easyio_ll_autoscale', true );
		\add_option( 'easyio_ll_abovethefold', 0 );
		\add_option( 'easyio_ll_external_bg', false );
		\add_option( 'easyio_ll_all_things', '' );
		\add_option( 'easyio_ll_exclude', '' );

		// Set network defaults.
		\add_site_option( 'easyio_metadata_remove', true );
		\add_site_option( 'easyio_add_missing_dims', true );
		\add_site_option( 'easyio_ll_autoscale', true );
		\add_site_option( 'exactdn_sub_folder', false );
		\add_site_option( 'exactdn_prevent_db_queries', true );
	}

	/**
	 * Attempt to activate the site with ExactDN/Easy IO.
	 */
	public function activate_service() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		\check_admin_referer( 'easy-image-optimizer-settings' );
		$permissions = \apply_filters( 'easyio_admin_permissions', '' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'You do not have permission to activate the Easy Image Optimizer service.', 'easy-image-optimizer' ) );
		}
		\update_option( 'easyio_exactdn', true );
		\update_option( 'exactdn_all_the_things', true );
		\update_option( 'exactdn_lossy', true );
		\update_option( 'easyio_lazy_load', true );
		if ( $this->get_option( 'ewww_image_optimizer_exactdn' ) ) {
			\update_option( 'ewww_image_optimizer_exactdn', false );
			\update_site_option( 'ewww_image_optimizer_exactdn', false );
		}
		if ( $this->get_option( 'ewww_image_optimizer_lazy_load' ) ) {
			\update_option( 'ewww_image_optimizer_lazy_load', false );
			\update_site_option( 'ewww_image_optimizer_lazy_load', false );
		}
		if ( $this->get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
			\update_option( 'ewww_image_optimizer_webp_for_cdn', false );
			\update_site_option( 'ewww_image_optimizer_webp_for_cdn', false );
		}
		$sendback = \wp_get_referer();
		\wp_safe_redirect( \esc_url_raw( $sendback ) );
		exit;
	}

	/**
	 * De-activate the site with ExactDN/Easy IO.
	 */
	public function deactivate_service() {
		$this->debug_message( '<b>' . __FUNCTION__ . '()</b>' );
		\check_admin_referer( 'easy-image-optimizer-settings' );
		$permissions = \apply_filters( 'easyio_admin_permissions', '' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'You do not have permission to activate the Easy Image Optimizer service.', 'easy-image-optimizer' ) );
		}
		\update_option( 'easyio_exactdn', false );
		\update_option( 'easyio_lazy_load', false );
		global $exactdn;
		if ( isset( $exactdn ) && \is_object( $exactdn ) ) {
			$exactdn->cron_setup( false );
		}
		\wp_safe_redirect( \wp_get_referer() );
		exit;
	}

	/**
	 * Save the multi-site settings, if this is the WP admin, and they've been POSTed.
	 */
	public function save_network_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// NOTE: we don't actually have a network settings screen, so...
		if ( ! \function_exists( 'is_plugin_active_for_network' ) && \is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
			// Set the common network settings if they have been POSTed.
		if (
			\is_multisite() &&
			\is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) &&
			! empty( $_REQUEST['_wpnonce'] ) &&
			isset( $_POST['option_page'] ) &&
			false !== \strpos( sanitize_text_field( wp_unslash( $_POST['option_page'] ) ), 'easyio_options' ) &&
			\wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'easyio_options-options' ) &&
			\current_user_can( 'manage_network_options' ) &&
			! \get_site_option( 'easyio_allow_multisite_override' ) &&
			false === \strpos( wp_get_referer(), 'options-general' )
		) {
			$this->debug_message( 'network-wide settings, no override' );
			$easyio_debug = ( empty( $_POST['easyio_debug'] ) ? false : true );
			\update_site_option( 'easyio_debug', $easyio_debug );
			$easyio_metadata_remove = ( empty( $_POST['easyio_metadata_remove'] ) ? false : true );
			\update_site_option( 'easyio_metadata_remove', $easyio_metadata_remove );
			$exactdn_all_the_things = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			\update_site_option( 'exactdn_all_the_things', $exactdn_all_the_things );
			$exactdn_lossy = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			\update_site_option( 'exactdn_lossy', $exactdn_lossy );
			$exactdn_hidpi = ( empty( $_POST['exactdn_hidpi'] ) ? false : true );
			\update_site_option( 'exactdn_hidpi', $exactdn_hidpi );
			$exactdn_exclude = empty( $_POST['exactdn_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['exactdn_exclude'] ) );
			\update_site_option( 'exactdn_exclude', $this->exclude_paths_sanitize( $exactdn_exclude ) );
			$easyio_add_missing_dims = ( empty( $_POST['easyio_add_missing_dims'] ) ? false : true );
			\update_site_option( 'easyio_add_missing_dims', $easyio_add_missing_dims );
			$easyio_lazy_load = ( empty( $_POST['easyio_lazy_load'] ) ? false : true );
			\update_site_option( 'easyio_lazy_load', $easyio_lazy_load );
			$easyio_ll_autoscale = ( empty( $_POST['easyio_ll_autoscale'] ) ? false : true );
			\update_site_option( 'easyio_ll_autoscale', $easyio_ll_autoscale );
			$easyio_ll_abovethefold = ! empty( $_POST['easyio_ll_abovethefold'] ) ? (int) $_POST['easyio_ll_abovethefold'] : 0;
			\update_site_option( 'easyio_ll_abovethefold', $easyio_ll_abovethefold );
			$easyio_use_lqip = ( empty( $_POST['easyio_use_lqip'] ) ? false : true );
			\update_site_option( 'easyio_use_lqip', $easyio_use_lqip );
			$easyio_use_dcip = ( empty( $_POST['easyio_use_dcip'] ) ? false : true );
			\update_site_option( 'easyio_use_dcip', $easyio_use_dcip );
			$easyio_ll_exclude = empty( $_POST['easyio_ll_exclude'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['easyio_ll_exclude'] ) );
			\update_site_option( 'easyio_ll_exclude', $this->exclude_paths_sanitize( $easyio_ll_exclude ) );
			$easyio_ll_external_bg = empty( $_POST['easyio_ll_external_bg'] ) ? false : true;
			\update_site_option( 'easyio_ll_external_bg', $easyio_ll_external_bg );
			$easyio_ll_all_things = empty( $_POST['easyio_ll_all_things'] ) ? '' : sanitize_textarea_field( wp_unslash( $_POST['easyio_ll_all_things'] ) );
			\update_site_option( 'easyio_ll_all_things', $easyio_ll_all_things );
			$easyio_allow_multisite_override = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			\update_site_option( 'easyio_allow_multisite_override', $easyio_allow_multisite_override );
			$easyio_enable_help = empty( $_POST['easyio_enable_help'] ) ? false : true;
			\update_site_option( 'easyio_enable_help', $easyio_enable_help );
			\add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} elseif ( isset( $_POST['easyio_allow_multisite_override_active'] ) && \current_user_can( 'manage_network_options' ) && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'easyio_options-options' ) ) {
			$this->debug_message( 'network-wide settings, single-site overriding' );
			$easyio_allow_multisite_override = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			\update_site_option( 'easyio_allow_multisite_override', $easyio_allow_multisite_override );
			\add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} // End if().
	}
}
