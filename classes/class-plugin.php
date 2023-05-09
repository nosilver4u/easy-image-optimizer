<?php
/**
 * Low-level plugin class.
 *
 * @link https://ewww.io
 * @package Easy_Image_Optimizer
 */

namespace EasyIO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kitchen sink, for everything that doesn't fit somewhere else.
 * Ideally, these are things like plugin initialization, setting defaults, and checking compatibility. We'll see how that plays out!
 */
final class Plugin extends Base {
	/* Singleton */

	/**
	 * The one and only true EasyIO\Plugin
	 *
	 * @var object|\EasyIO\Plugin $instance
	 */
	private static $instance;

	/**
	 * Helpscout Beacon object.
	 *
	 * @var object|\EasyIO\HS_Beacon $hs_beacon
	 */
	public $hs_beacon;

	/**
	 * Main EWWW_Plugin instance.
	 *
	 * Ensures that only one instance of EWWW_Plugin exists in memory at any given time.
	 *
	 * @static
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Plugin ) ) {

			self::$instance = new Plugin( true );
			self::$instance->debug_message( '<b>' . __METHOD__ . '()</b>' );

			// For classes we need everywhere, front-end and back-end. Others are only included on admin_init (below).
			self::$instance->requires();
			self::$instance->load_children();
			// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
			\add_action( 'admin_init', array( self::$instance, 'admin_init' ) );

			// TODO: check PHP and WP compat here.
			// TODO: setup anything that needs to run on init/plugins_loaded.
			// TODO: add any custom option/setting hooks here (actions that need to be taken when certain settings are saved/updated).
		}

		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object. Therefore, we don't want the object to be cloned.
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot clone core object.', 'ewww-image-optimizer' ), \esc_html( \EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot unserialize (wakeup) the core object.', 'ewww-image-optimizer' ), \esc_html( \EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		// EasyIO\HS_Beacon class for integrated help/docs.
		require_once( \EASYIO_PLUGIN_PATH . 'classes/class-hs-beacon.php' );
	}

	/**
	 * Setup mandatory child classes.
	 */
	function load_children() {
		/* self::$instance->class = new Class(); */
	}

	/**
	 * Setup plugin for wp-admin.
	 */
	function admin_init() {
		$this->hs_beacon = new HS_Beacon();
		\easyio_upgrade();
		$this->register_settings();

		if ( ! \class_exists( __NAMESPACE__ . '\ExactDN' ) || ! $this->get_option( 'easyio_exactdn' ) ) {
			add_action( 'network_admin_notices', 'easyio_notice_inactive' );
			add_action( 'admin_notices', 'easyio_notice_inactive' );
		}
		// Prevent ShortPixel AIO messiness.
		\remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
		if ( \class_exists( '\autoptimizeExtra' ) ) {
			$ao_extra = \get_option( 'autoptimize_imgopt_settings' );
			if ( $this->get_option( 'easyio_exactdn' ) && ! empty( $ao_extra['autoptimize_imgopt_checkbox_field_1'] ) ) {
				$this->debug_message( 'detected ExactDN + SP conflict' );
				$ao_extra['autoptimize_imgopt_checkbox_field_1'] = 0;
				\update_option( 'autoptimize_imgopt_settings', $ao_extra );
				\add_action( 'admin_notices', 'easyio_notice_sp_conflict' );
			}
		}

		if ( ! \defined( '\WP_CLI' ) || ! \WP_CLI ) {
			\easyio_privacy_policy_content();
		}
		// Increase the version when the next bump is coming.
		if ( \defined( '\PHP_VERSION_ID' ) && \PHP_VERSION_ID < 50600 ) {
			\add_action( 'network_admin_notices', 'easyio_php55_warning' );
			\add_action( 'admin_notices', 'easyio_php55_warning' );
		}
	}

	/**
	 * Save the multi-site settings, if this is the WP admin, and they've been POSTed.
	 */
	function save_network_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// NOTE: we don't actually have a network settings screen, so...
		if ( ! \function_exists( 'is_plugin_active_for_network' ) && \is_multisite() ) {
			// Need to include the plugin library for the is_plugin_active function.
			require_once( \ABSPATH . 'wp-admin/includes/plugin.php' );
		}
			// Set the common network settings if they have been POSTed.
		if (
			\is_multisite() &&
			\is_plugin_active_for_network( \EASYIO_PLUGIN_FILE_REL ) &&
			isset( $_POST['option_page'] ) &&
			false !== \strpos( $_POST['option_page'], 'easyio_options' ) &&
			\wp_verify_nonce( $_REQUEST['_wpnonce'], 'easyio_options-options' ) &&
			\current_user_can( 'manage_network_options' ) &&
			! \get_site_option( 'easyio_allow_multisite_override' ) &&
			false === \strpos( $_POST['_wp_http_referer'], 'options-general' )
		) {
			$this->debug_message( 'network-wide settings, no override' );
			$_POST['easyio_debug'] = ( empty( $_POST['easyio_debug'] ) ? false : true );
			\update_site_option( 'easyio_debug', $_POST['easyio_debug'] );
			$_POST['easyio_metadata_remove'] = ( empty( $_POST['easyio_metadata_remove'] ) ? false : true );
			\update_site_option( 'easyio_metadata_remove', $_POST['easyio_metadata_remove'] );
			$_POST['easyio_exactdn'] = ( empty( $_POST['easyio_exactdn'] ) ? false : true );
			\update_site_option( 'easyio_exactdn', $_POST['easyio_exactdn'] );
			$_POST['exactdn_all_the_things'] = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			\update_site_option( 'exactdn_all_the_things', $_POST['exactdn_all_the_things'] );
			$_POST['exactdn_lossy'] = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			\update_site_option( 'exactdn_lossy', $_POST['exactdn_lossy'] );
			$_POST['exactdn_exclude'] = empty( $_POST['exactdn_exclude'] ) ? '' : $_POST['exactdn_exclude'];
			\update_site_option( 'exactdn_exclude', $this->exclude_paths_sanitize( $_POST['exactdn_exclude'] ) );
			$_POST['easyio_add_missing_dims'] = ( empty( $_POST['easyio_add_missing_dims'] ) ? false : true );
			\update_site_option( 'easyio_add_missing_dims', $_POST['easyio_add_missing_dims'] );
			$_POST['easyio_lazy_load'] = ( empty( $_POST['easyio_lazy_load'] ) ? false : true );
			\update_site_option( 'easyio_lazy_load', $_POST['easyio_lazy_load'] );
			$_POST['easyio_use_lqip'] = ( empty( $_POST['easyio_use_lqip'] ) ? false : true );
			\update_site_option( 'easyio_use_lqip', $_POST['easyio_use_lqip'] );
			$_POST['easyio_ll_exclude'] = empty( $_POST['easyio_ll_exclude'] ) ? '' : $_POST['easyio_ll_exclude'];
			\update_site_option( 'easyio_ll_exclude', $this->exclude_paths_sanitize( $_POST['easyio_ll_exclude'] ) );
			$_POST['easyio_allow_multisite_override'] = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			\update_site_option( 'easyio_allow_multisite_override', $_POST['easyio_allow_multisite_override'] );
			$_POST['easyio_enable_help'] = empty( $_POST['easyio_enable_help'] ) ? false : true;
			\update_site_option( 'easyio_enable_help', $_POST['easyio_enable_help'] );
			\add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} elseif ( isset( $_POST['easyio_allow_multisite_override_active'] ) && \current_user_can( 'manage_network_options' ) && \wp_verify_nonce( $_REQUEST['_wpnonce'], 'easyio_options-options' ) ) {
			$this->debug_message( 'network-wide settings, single-site overriding' );
			$_POST['easyio_allow_multisite_override'] = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			\update_site_option( 'easyio_allow_multisite_override', $_POST['easyio_allow_multisite_override'] );
			\add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} // End if().
	}

	/**
	 * Register all our options and santiation functions.
	 */
	function register_settings() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Register all the common Easy IO settings.
		\register_setting( 'easyio_options', 'easyio_debug', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_enable_help', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_exactdn', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_all_the_things', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_lossy', 'boolval' );
		\register_setting( 'easyio_options', 'exactdn_exclude', array( $this, 'exclude_paths_sanitize' ) );
		\register_setting( 'easyio_options', 'easyio_add_missing_dims', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_lazy_load', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_use_lqip', 'boolval' );
		\register_setting( 'easyio_options', 'easyio_ll_exclude', array( $this, 'exclude_paths_sanitize' ) );
	}

	/**
	 * Set some default option values.
	 */
	function set_defaults() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Set defaults for all options that need to be autoloaded.
		\add_option( 'easyio_debug', false );
		\add_option( 'easyio_metadata_remove', true );
		\add_option( 'easyio_exactdn', false );
		\add_option( 'easyio_plan_id', 0 );
		\add_option( 'exactdn_all_the_things', false );
		\add_option( 'exactdn_lossy', false );
		\add_option( 'exactdn_exclude', '' );
		\add_option( 'exactdn_sub_folder', false );
		\add_option( 'exactdn_prevent_db_queries', true );
		\add_option( 'easyio_add_missing_dims', true );
		\add_option( 'easyio_lazy_load', false );
		\add_option( 'easyio_use_lqip', false );
		\add_option( 'easyio_use_siip', false );
		\add_option( 'easyio_ll_autoscale', true );
		\add_option( 'easyio_ll_exclude', '' );

		// Set network defaults.
		\add_site_option( 'easyio_metadata_remove', true );
		\add_site_option( 'easyio_add_missing_dims', true );
		\add_site_option( 'easyio_ll_autoscale', true );
		\add_site_option( 'exactdn_sub_folder', false );
		\add_site_option( 'exactdn_prevent_db_queries', true );
	}
}
