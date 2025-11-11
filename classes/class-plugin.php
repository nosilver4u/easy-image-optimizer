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
	 * Buffer object.
	 *
	 * @var object|\EasyIO\Buffer $buffer
	 */
	public $buffer;

	/**
	 * Lazy Load object.
	 *
	 * @var object|\EasyIO\Lazy_Load $lazy_load
	 */
	public $lazy_load;

	/**
	 * Helpscout Beacon object.
	 *
	 * @var object|\EasyIO\HS_Beacon $hs_beacon
	 */
	public $hs_beacon;

	/**
	 * Settings object.
	 *
	 * @var object|\EasyIO\Settings $settings
	 */
	public $settings;

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
			// Setup page parsing, if parsers are enabled.
			\add_action( 'init', array( self::$instance, 'parser_init' ), 99 );
			// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
			\add_action( 'admin_init', array( self::$instance, 'admin_init' ) );

			// Filters to set default permissions, admins can override these if they wish.
			\add_filter( 'easyio_admin_permissions', array( self::$instance, 'admin_permissions' ), 8 );
			\add_filter( 'easyio_superadmin_permissions', array( self::$instance, 'superadmin_permissions' ), 8 );

			// Add Easy IO version to useragent for API requests.
			\add_filter( 'exactdn_api_request_useragent', array( self::$instance, 'api_useragent' ) );
			// Check the current screen ID to see if temp debugging should still be enabled.
			\add_action( 'current_screen', array( self::$instance, 'current_screen' ), 10, 1 );
			// Disable core WebP generation since we already do that.
			\add_filter( 'wp_upload_image_mime_transforms', '__return_empty_array' );
			// Makes sure we flush the debug info to the log on shutdown.
			\add_action( 'shutdown', array( self::$instance, 'debug_log' ) );
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
		// Starts the HTML buffer for all other functions to parse.
		require_once EASYIO_PLUGIN_PATH . 'classes/class-buffer.php';
		// Page Parsing class for working with HTML content.
		require_once EASYIO_PLUGIN_PATH . 'classes/class-page-parser.php';
		// Lazy Load class for parsing image urls and deferring off-screen images.
		require_once EASYIO_PLUGIN_PATH . 'classes/class-lazy-load.php';
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
	 * Setup page parsing classes after theme functions.php is loaded and plugins have run init routines.
	 */
	public function parser_init() {
		$buffer_start = false;
		// If ExactDN is enabled.
		if ( $this->get_option( 'easyio_exactdn' ) && ! \str_contains( \add_query_arg( '', '' ), 'exactdn_disable=1' ) ) {
			$buffer_start = true;

			// ExactDN class for parsing image urls and rewriting them.
			require_once EASYIO_PLUGIN_PATH . 'classes/class-exactdn.php';
		}
		// If Lazy Load is enabled.
		if ( $this->get_option( 'easyio_lazy_load' ) ) {
			$buffer_start = true;

			$this->lazy_load = new Lazy_Load();
		}
		if ( $buffer_start ) {
			// Start an output buffer before any output starts.
			$this->buffer = new Buffer();
		}
	}

	/**
	 * Setup plugin for wp-admin.
	 */
	public function admin_init() {
		$this->hs_beacon = new HS_Beacon();

		if ( ! \class_exists( __NAMESPACE__ . '\ExactDN' ) || ! $this->get_option( 'easyio_exactdn' ) ) {
			\add_action( 'network_admin_notices', array( $this, 'service_inactive_notice' ) );
			\add_action( 'admin_notices', array( $this, 'service_inactive_notice' ) );
		}

		\add_action( 'exactdn_as3cf_cname_active', array( $this, 'exactdn_as3cf_cname_active_notice' ) );
		\add_action( 'exactdn_domain_mismatch', array( $this, 'exactdn_domain_mismatch_notice' ) );

		\add_action( 'easyio_beacon_notice', array( $this, 'hs_beacon_notice' ) );

		// Prevent ShortPixel AIO messiness.
		\remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
		if ( \class_exists( '\autoptimizeExtra' ) ) {
			$ao_extra = \get_option( 'autoptimize_imgopt_settings' );
			if ( $this->get_option( 'easyio_exactdn' ) && ! empty( $ao_extra['autoptimize_imgopt_checkbox_field_1'] ) ) {
				$this->debug_message( 'detected ExactDN + SP conflict' );
				$ao_extra['autoptimize_imgopt_checkbox_field_1'] = 0;
				\update_option( 'autoptimize_imgopt_settings', $ao_extra );
				\add_action( 'admin_notices', array( $this, 'sp_conflict_notice' ) );
			}
		}

		if ( \method_exists( '\HMWP_Classes_Tools', 'getOption' ) ) {
			if ( $this->get_option( 'easyio_exactdn' ) && \HMWP_Classes_Tools::getOption( 'hmwp_hide_version' ) && ! \HMWP_Classes_Tools::getOption( 'hmwp_hide_version_random' ) ) {
				$this->debug_message( 'detected HMWP Hide Version' );
				\add_action( 'admin_notices', array( $this, 'hmwp_hide_version_notice' ) );
			}
		}

		if ( ! \defined( 'WP_CLI' ) || ! WP_CLI ) {
			$this->privacy_policy_content();
		}
	}

	/**
	 * Adds the Easy IO version to the useragent for http requests.
	 *
	 * @param string $useragent The current useragent used in http requests.
	 * @return string The useragent with the Easy IO version appended.
	 */
	public function api_useragent( $useragent ) {
		if ( ! \str_contains( $useragent, 'EIO' ) ) {
			$useragent .= ' EIO/' . EASYIO_VERSION . ' ';
		}
		return $useragent;
	}

	/**
	 * Adds suggested privacy policy content for site admins.
	 *
	 * Note that this is just a suggestion, it should be customized for your site.
	 */
	private function privacy_policy_content() {
		if ( ! \function_exists( 'wp_add_privacy_policy_content' ) || ! \function_exists( 'wp_kses_post' ) ) {
			return;
		}
		$content  = '<p class="privacy-policy-tutorial">';
		$content .= \wp_kses_post( \__( 'Normally, this plugin does not process any information about your visitors. However, if you accept user-submitted images and display them on your site, you can use this language to keep your visitors informed.', 'easy-image-optimizer' ) ) . '</p>';
		$content .= '<p>' . \wp_kses_post( \__( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).', 'easy-image-optimizer' ) ) . '</p>';
		\wp_add_privacy_policy_content( 'Easy Image Optimizer', $content );
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
	 * Check the current screen, used to temporarily enable debugging on settings page.
	 *
	 * @param object $screen Information about the page/screen currently being loaded.
	 */
	public function current_screen( $screen ) {
		if ( $this->get_option( 'easyio_debug' ) ) {
			return;
		}
		if ( \str_contains( $screen->id, 'settings_page_easy-image-optimizer' ) ) {
			return;
		}
		// Otherwise, we are somewhere else and should disable temp debugging.
		Base::$debug_data = '';
		Base::$temp_debug = false;
	}

	/**
	 * Let the user know they need to take action!
	 */
	public function service_inactive_notice() {
		?>
		<div id='easyio-inactive' class='notice notice-warning'>
			<p>
				<a href="<?php echo \esc_url( \admin_url( 'options-general.php?page=easy-image-optimizer-options' ) ); ?>">
					<?php \esc_html_e( 'Please visit the settings page to complete activation of the Easy Image Optimizer.', 'easy-image-optimizer' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Let the user know they need to disable the WP Offload Media CNAME.
	 */
	public function exactdn_as3cf_cname_active_notice() {
		?>
		<div id="easyio-notice-exactdn-as3cf-cname-active" class="notice notice-error">
			<p>
				<?php \esc_html_e( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'easy-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Let the user know the local domain appears to have changed from what Easy IO has recorded in the db.
	 */
	public function exactdn_domain_mismatch_notice() {
		global $exactdn;
		if ( ! isset( $exactdn->upload_domain ) ) {
			return;
		}
		$stored_local_domain = $this->get_option( 'easyio_exactdn_local_domain' );
		if ( empty( $stored_local_domain ) ) {
			return;
		}
		if ( ! \str_contains( $stored_local_domain, '.' ) ) {
			$stored_local_domain = \base64_decode( $stored_local_domain );
		}
		?>
		<div id="easyio-notice-exactdn-domain-mismatch" class="notice notice-warning">
			<p>
				<?php
				\printf(
					/* translators: 1: old domain name, 2: current domain name */
					\esc_html__( 'Easy IO detected that the Site URL has changed since the initial activation (previously %1$s, currently %2$s).', 'easy-image-optimizer' ),
					'<strong>' . \esc_html( $stored_local_domain ) . '</strong>',
					'<strong>' . \esc_html( $exactdn->upload_domain ) . '</strong>'
				);
				?>
				<br>
				<?php
				\printf(
					/* translators: %s: settings page */
					\esc_html__( 'Please visit the %s to refresh the Easy IO settings and verify activation status.', 'easy-image-optimizer' ),
					'<a href="' . \esc_url( \admin_url( 'options-general.php?page=easy-image-optimizer-options' ) ) . '">' . \esc_html__( 'settings page', 'easy-image-optimizer' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Inform the user of our beacon function so that they can opt-in.
	 */
	public function hs_beacon_notice() {
		$optin_url  = \wp_nonce_url( 'admin.php?action=eio_opt_into_hs_beacon', 'eio_beacon' );
		$optout_url = \wp_nonce_url( 'admin.php?action=eio_opt_out_of_hs_beacon', 'eio_beacon' );
		?>
		<div id="easyio-hs-beacon" class="notice notice-info">
			<p>
				<?php \esc_html_e( 'Enable the Easy IO support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we collect the current url, IP address, browser/device information, and debugging information.', 'easy-image-optimizer' ); ?><br>
				<a href="<?php echo \esc_url( $optin_url ); ?>" class="button-secondary"><?php esc_html_e( 'Allow', 'easy-image-optimizer' ); ?></a>&nbsp;
				<a href="<?php echo \esc_url( $optout_url ); ?>" class="button-secondary"><?php esc_html_e( 'Do not allow', 'easy-image-optimizer' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
	 */
	public function sp_conflict_notice() {
		?>
		<div id='easyio-sp-conflict' class='notice notice-warning'>
			<p>
				<?php \esc_html_e( 'ShortPixel/Autoptimize image optimization has been disabled to prevent conflicts with Easy Image Optimizer).', 'easy-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Tell the user to disable Hide my WP function that removes query strings.
	 */
	public function hmwp_hide_version_notice() {
		?>
		<div id='easy-image-optimizer-warning-hmwp-hide-version' class='notice notice-warning'>
			<p>
				<?php \esc_html_e( 'Please enable the Random Static Number option in Hide My WP to ensure compatibility with Easy IO or disable the Hide Version option for best performance.', 'easy-image-optimizer' ); ?>
				<?php $this->settings->help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' ); ?>
			</p>
		</div>
		<?php
	}
}
