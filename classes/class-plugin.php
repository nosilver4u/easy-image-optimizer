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
	 * Settings object.
	 *
	 * @var object|\EasyIO\Settings $settings
	 */
	public $settings;

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

			if ( ! self::$instance->php_supported() ) {
				return self::$instance;
			}
			if ( ! self::$instance->wp_supported() ) {
				return self::$instance;
			}

			// Load plugin components that need to be available early.
			\add_action( 'plugins_loaded', array( self::$instance, 'plugins_loaded' ) );
			// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
			\add_action( 'admin_init', array( self::$instance, 'admin_init' ) );

			// TODO: setup anything that needs to run on init/plugins_loaded.
			// TODO: add any custom option/setting hooks here (actions that need to be taken when certain settings are saved/updated).

			// Filters to set default permissions, admins can override these if they wish.
			add_filter( 'easyio_admin_permissions', array( self::$instance, 'admin_permissions' ), 8 );
			add_filter( 'easyio_superadmin_permissions', array( self::$instance, 'superadmin_permissions' ), 8 );
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
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot clone core object.', 'easy-image-optimizer' ), \esc_html( EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Disable unserializing of the class.
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		\_doing_it_wrong( __METHOD__, \esc_html__( 'Cannot unserialize (wakeup) the core object.', 'easy-image-optimizer' ), \esc_html( EWWW_IMAGE_OPTIMIZER_VERSION ) );
	}

	/**
	 * Include required files.
	 *
	 * @access private
	 */
	private function requires() {
		// Sets up the settings page and various option-related hooks/functions.
		require_once EASYIO_PLUGIN_PATH . 'classes/class-settings.php';
		// EasyIO\HS_Beacon class for integrated help/docs.
		require_once EASYIO_PLUGIN_PATH . 'classes/class-hs-beacon.php';
	}

	/**
	 * Setup mandatory child classes.
	 */
	public function load_children() {
		self::$instance->settings = new Settings();
	}

	/**
	 * Make sure we are on a supported version of PHP.
	 *
	 * @access private
	 */
	private function php_supported() {
		if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 80100 ) {
			return true;
		}
		\add_action( 'network_admin_notices', array( self::$instance, 'unsupported_php_notice' ) );
		\add_action( 'admin_notices', array( self::$instance, 'unsupported_php_notice' ) );
		return false;
	}

	/**
	 * Make sure we are on a supported version of WordPress.
	 *
	 * @access private
	 */
	private function wp_supported() {
		global $wp_version;
		if ( \version_compare( $wp_version, '6.6' ) >= 0 ) {
			return true;
		}
		\add_action( 'network_admin_notices', array( self::$instance, 'unsupported_wp_notice' ) );
		\add_action( 'admin_notices', array( self::$instance, 'unsupported_wp_notice' ) );
		return false;
	}

	/**
	 * Display a notice that the PHP version is too old.
	 */
	public function unsupported_php_notice() {
		echo '<div id="easyio-warning-php" class="error"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'Easy Image Optimizer requires PHP 8.1 or greater. Newer versions of PHP are faster and more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'easy-image-optimizer' ) . '</a></p></div>';
	}

	/**
	 * Display a notice that the WP version is too old.
	 */
	public function unsupported_wp_notice() {
		echo '<div id="swis-warning-wp" class="notice notice-error"><p>' . esc_html__( 'Easy Image Optimizer requires WordPress 6.6 or greater, please update your website.', 'easy-image-optimizer' ) . '</p></div>';
	}

	/**
	 * Run things that need to go early, on plugins_loaded.
	 */
	public function plugins_loaded() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );

		if ( $this->get_option( 'easyio_lazy_load' ) && $this->get_option( 'easyio_ll_external_bg' ) ) {
			$this->debug_message( 'requesting external parsing of CSS for background images via SWIS' );
			add_filter( 'eio_lazify_external_css', '__return_true' );
		}
	}

	/**
	 * Setup plugin for wp-admin.
	 */
	public function admin_init() {
		$this->hs_beacon = new HS_Beacon();

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

		if ( \method_exists( '\HMWP_Classes_Tools', 'getOption' ) ) {
			if ( $this->get_option( 'easyio_exactdn' ) && \HMWP_Classes_Tools::getOption( 'hmwp_hide_version' ) && ! \HMWP_Classes_Tools::getOption( 'hmwp_hide_version_random' ) ) {
				$this->debug_message( 'detected HMWP Hide Version' );
				\add_action( 'admin_notices', array( $this, 'notice_hmwp_hide_version' ) );
			}
		}

		if ( ! \defined( '\WP_CLI' ) || ! WP_CLI ) {
			$this->privacy_policy_content();
		}
	}

	/**
	 * Adds suggested privacy policy content for site admins.
	 *
	 * Note that this is just a suggestion, it should be customized for your site.
	 */
	private function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! function_exists( 'wp_kses_post' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">';
		$content .= wp_kses_post( __( 'Normally, this plugin does not process any information about your visitors. However, if you accept user-submitted images and display them on your site, you can use this language to keep your visitors informed.', 'easy-image-optimizer' ) ) . '</p>';
		$content .= '<p>' . wp_kses_post( __( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).', 'easy-image-optimizer' ) ) . '</p>';
		wp_add_privacy_policy_content( 'Easy Image Optimizer', $content );
	}

	/**
	 * Set default permissions for admin (configuration) and bulk operations.
	 *
	 * @param string $permissions A valid WP capability level.
	 * @return string Either the original value, unchanged, or the default capability level.
	 */
	public function admin_permissions( $permissions ) {
		if ( empty( $permissions ) ) {
			return 'activate_plugins';
		}
		return $permissions;
	}

	/**
	 * Set default permissions for multisite/network admin (configuration) operations.
	 *
	 * @param string $permissions A valid WP capability level.
	 * @return string Either the original value, unchanged, or the default capability level.
	 */
	public function superadmin_permissions( $permissions ) {
		if ( empty( $permissions ) ) {
			return 'manage_network_options';
		}
		return $permissions;
	}

	/**
	 * Tell the user to disable Hide my WP function that removes query strings.
	 */
	public function notice_hmwp_hide_version() {
		?>
		<div id='easy-image-optimizer-warning-hmwp-hide-version' class='notice notice-warning'>
			<p>
				<?php \esc_html_e( 'Please enable the Random Static Number option in Hide My WP to ensure compatibility with Easy IO or disable the Hide Version option for best performance.', 'easy-image-optimizer' ); ?>
				<?php \easyio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' ); ?>
			</p>
		</div>
		<?php
	}
}
