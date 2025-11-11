<?php
/**
 * Implements basic page parsing functions.
 *
 * @link https://ewww.io
 * @package Easy_Image_Optimizer
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

		// Activation routine for Easy IO/ExactDN, just a fallback in case JS is busted.
		\add_action( 'admin_action_easyio_activate', array( $this, 'activate_service' ) );
		// De-activation routine for Easy IO/ExactDN.
		\add_action( 'admin_action_easyio_deactivate', array( $this, 'deactivate_service' ) );
		// AJAX action hook to activate Easy IO.
		\add_action( 'wp_ajax_easyio_activate', array( $this, 'ajax_activate_service' ) );

		// Adds the Easy IO pages to the admin menu.
		\add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		// Adds the Easy IO settings to the network admin menu.
		\add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );
		// Adds scripts for the Easy IO settings page.
		\add_action( 'admin_enqueue_scripts', array( $this, 'settings_script' ) );
		// Add a link to the plugins page so the user can go straight to the settings page.
		$easyio_plugin_slug = \plugin_basename( EASYIO_PLUGIN_FILE );
		\add_filter( "plugin_action_links_$easyio_plugin_slug", array( $this, 'settings_link' ) );

		// Non-AJAX handler to view the debug log, and display it.
		\add_action( 'admin_action_easyio_view_debug_log', array( $this, 'view_debug_log' ) );
		// Non-AJAX handler to delete the debug log, and reroute back to the settings page.
		\add_action( 'admin_action_easyio_delete_debug_log', array( $this, 'delete_debug_log' ) );
		// Non-AJAX handler to download the debug log.
		\add_action( 'admin_action_easyio_download_debug_log', array( $this, 'download_debug_log' ) );
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		\check_admin_referer( 'easy-image-optimizer-settings' );
		$permissions = \apply_filters( 'easyio_admin_permissions', '' );
		if ( ! \current_user_can( $permissions ) ) {
			\wp_die( \esc_html__( 'You do not have permission to activate the Easy Image Optimizer service.', 'easy-image-optimizer' ) );
		}
		\update_option( 'easyio_exactdn', true );
		\update_option( 'exactdn_all_the_things', true );
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
	 * Activates Easy IO via AJAX.
	 */
	public function ajax_activate_service() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		if ( ! \current_user_can( \apply_filters( 'easyio_admin_permissions', '' ) ) ) {
			// Display error message if insufficient permissions.
			$this->ob_clean();
			\wp_die( \wp_json_encode( array( 'error' => \esc_html__( 'Access denied.', 'easy-image-optimizer' ) ) ) );
		}
		// Make sure we didn't accidentally get to this page without an attachment to work on.
		if ( empty( $_REQUEST['_wpnonce'] ) || ! \wp_verify_nonce( \sanitize_key( $_REQUEST['_wpnonce'] ), 'easy-image-optimizer-settings' ) ) {
			die( \wp_json_encode( array( 'error' => \esc_html__( 'Access token has expired, please reload the page.', 'easy-image-optimizer' ) ) ) );
		}
		\update_option( 'easyio_exactdn', true );
		global $exactdn;
		unset( $GLOBALS['exactdn'] );
		if ( ! \class_exists( '\EasyIO\ExactDN' ) ) {
			$this->debug_message( 'firing up ExactDN class for AJAX activation' );
			// ExactDN class for parsing image urls and rewriting them.
			require_once EASYIO_PLUGIN_PATH . 'classes/class-exactdn.php';
		}
		if ( $exactdn->get_exactdn_domain() ) {
			\update_option( 'exactdn_all_the_things', true );
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
			die(
				\wp_json_encode(
					array(
						'success' => '<span style="color: #3eadc9; font-weight: bolder;">' . \esc_html__( 'Verified', 'easy-image-optimizer' ) . '</span><br><span style="font-weight:normal;line-height:1.8em;">' . \esc_html( $exactdn->get_exactdn_domain() ) . '</span>',
					)
				)
			);
		}
		global $exactdn_activate_error;
		if ( empty( $exactdn_activate_error ) ) {
			$exactdn_activate_error = 'error unknown';
		}
		$error_message = \sprintf(
			/* translators: 1: A link to the documentation 2: the error message/details */
			\esc_html__( 'Could not activate Easy IO, please try again in a few minutes. If this error continues, please see %1$s for troubleshooting steps: %2$s', 'easy-image-optimizer' ),
			'https://docs.ewww.io/article/66-exactdn-not-verified',
			'<code>' . \esc_html( $exactdn_activate_error ) . '</code>'
		);
		if ( 'as3cf_cname_active' === $exactdn_activate_error ) {
			$error_message = \esc_html__( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'easy-image-optimizer' );
		}
		die(
			\wp_json_encode(
				array(
					'error' => $error_message,
				)
			)
		);
	}

	/**
	 * De-activate the site with ExactDN/Easy IO.
	 */
	public function deactivate_service() {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
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
	 * Adds a global settings page to the network admin settings menu.
	 */
	public function network_admin_menu() {
		// Add options page to the settings menu.
		\add_submenu_page(
			'settings.php',                                       // Slug of parent.
			'Easy Image Optimizer',                               // Page Title.
			'Easy Image Optimizer',                               // Menu title.
			\apply_filters( 'easyio_superadmin_permissions', '' ), // Capability.
			'easy-image-optimizer-options',                       // Slug.
			array( $this, 'display_network_settings' )            // Function to call.
		);
	}

	/**
	 * Adds various items to the admin menu.
	 */
	public function admin_menu() {
		// Add options page to the settings menu.
		\add_options_page(
			'Easy Image Optimizer',                                        // Page title.
			'Easy Image Optimizer',                                        // Menu title.
			\apply_filters( 'easyio_admin_permissions', 'manage_options' ), // Capability.
			'easy-image-optimizer-options',                                // Slug.
			array( $this, 'display_settings' )                             // Function to call.
		);
	}

	/**
	 * Adds a link on the Plugins page for the Easy IO settings.
	 *
	 * @param array $links A list of links to display next to the plugin listing.
	 * @return array The new list of links to be displayed.
	 */
	public function settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=easy-image-optimizer-options">' . \esc_html__( 'Settings', 'easy-image-optimizer' ) . '</a>';
		// Load the settings link into the plugin links array.
		\array_unshift( $links, $settings_link );
		// Send back the plugin links array.
		return $links;
	}

	/**
	 * JS needed for the settings page.
	 *
	 * @param string $hook The hook name of the page being loaded.
	 */
	public function settings_script( $hook ) {
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		// Make sure we are being called from the settings page.
		if ( ! \str_starts_with( $hook, 'settings_page_easy-image-optimizer' ) ) {
			return;
		}
		\delete_option( 'easyio_exactdn_checkin' );
		global $exactdn;
		if ( \is_object( $exactdn ) && ! empty( $exactdn->domain_mismatch ) ) {
			$this->debug_message( 'detected domain mismatch, clearing options and re-running setup' );
			\delete_option( 'easyio_exactdn_domain' );
			\delete_option( 'easyio_exactdn_local_domain' );
			\delete_option( 'easyio_exactdn_plan_id' );
			\delete_option( 'easyio_exactdn_failures' );
			\delete_option( 'easyio_exactdn_verified' );
			$exactdn->setup();
		}
		\wp_enqueue_script( 'easyio-settings-script', \plugins_url( '/includes/eio.js', EASYIO_PLUGIN_FILE ), array( 'jquery' ), EASYIO_VERSION );
		\wp_localize_script(
			'easyio-settings-script',
			'easyio_vars',
			array(
				'_wpnonce'         => \wp_create_nonce( 'easy-image-optimizer-settings' ),
				'invalid_response' => \esc_html__( 'Received an invalid response from your website, please check for errors in the Developer Tools console of your browser.', 'easy-image-optimizer' ),
			)
		);
	}

	/**
	 * Displays the Easy IO network settings page.
	 */
	public function display_network_settings() {
		$icon_link = \plugins_url( '/images/easyio-toon-car.png', EASYIO_PLUGIN_FILE );
		?>
		<div class='wrap'>
			<img style='float:right;' src='<?php \esc_url( $icon_link ); ?>' />
			<h1>Easy Image Optimizer</h1>
			<p><?php \esc_html_e( 'The Easy Image Optimizer must be configured and activated on each individual site.', 'easy-image-optimizer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Displays the Easy IO options along with status information, and debugging information.
	 */
	public function display_settings() {
		global $exactdn;
		$this->debug_message( '<b>' . __METHOD__ . '()</b>' );
		$this->debug_info();

		\easyio()->hs_beacon->admin_notice( 'singlesite' );

		$icon_link         = \plugins_url( '/images/easyio-toon-car.png', EASYIO_PLUGIN_FILE );
		$loading_image_url = \plugins_url( '/images/spinner.gif', EASYIO_PLUGIN_FILE );
		$site_url          = $this->content_url();

		$eio_exclude_paths = $this->get_option( 'exactdn_exclude' ) ? \esc_html( \implode( "\n", $this->get_option( 'exactdn_exclude' ) ) ) : '';
		$ll_exclude_paths  = $this->get_option( 'easyio_ll_exclude' ) ? \esc_html( \implode( "\n", $this->get_option( 'easyio_ll_exclude' ) ) ) : '';
		?>
	<style>
		#easyio-header-flex { border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); display: flex; flex-direction:row; margin: 0; padding: 0; background-color: white; }
		#easyio-header-wrapper { padding: 10px 0; width: 100%; clear: right; }
		#easyio-logo { padding: 15px 40px 15px 40px; }
		#easyio-status { padding-bottom: 10px; }
		.easyio-tab span { font-size: 15px; font-weight: 700; color: #555; text-decoration: none; line-height: 36px; padding: 0 10px; }
		.easyio-tab span:hover { color: #464646; }
		.easyio-tab { margin: 0 0 0 5px; padding: 0px; border-width: 1px 1px 1px; border-style: solid solid none; border-image: none; border-color: #ccc; display: inline-block; background-color: #e4e4e4; cursor: pointer }
		.easyio-tab:hover { background-color: #fff }
		.easyio-selected { background-color: #f1f1f1; margin-bottom: -1px; border-bottom: 1px solid #f1f1f1 }
		.easyio-selected span { color: #000; }
		.easyio-selected:hover { background-color: #f1f1f1; }
		.easyio-tab-nav { list-style: none; margin: 10px 0 0; padding-left: 5px; border-bottom: 1px solid #ccc; }
		#easyio-inactive { display: none; }
		.easyio-help-beacon-single, .easyio-help-beacon-multi { margin: 3px; }
		#easyio-activation-result { display: none; background-color: white; border: 1px solid #ccd0d4; border-left: 4px solid #3eadc9; margin: 10px 10px 15px 0; padding: 12px; }
		#easyio-activation-result.error { border-left-color: #dc3232; }
		#easyio-activation-processing { display: none; }
	</style>
	<div class='wrap'>
		<h1 style="display: none;">Easy Image Optimizer</h1>
		<div id="easyio-header-wrapper">
			<div id='easyio-header-flex'>
				<div id='easyio-logo'>
					<img width='128' height='80' src='<?php echo \esc_url( $icon_link ); ?>' />
				</div>
				<div id='easyio-status'>
					<h1>Easy Image Optimizer</h1>
					<?php if ( \class_exists( '\EasyIO\ExactDN' ) && $this->get_option( 'easyio_exactdn' ) ) : ?>
						<?php if ( \class_exists( '\Jetpack' ) && \method_exists( 'Jetpack', 'is_module_active' ) && \Jetpack::is_module_active( 'photon' ) ) : ?>
							<span style="color: red; font-weight: bolder;">
								<?php \esc_html_e( 'Inactive, please disable the Site Accelerator option in the Jetpack settings.', 'easy-image-optimizer' ); ?>
							</span>
						<?php elseif ( \class_exists( '\Automattic\Jetpack_Boost\Jetpack_Boost' ) && \get_option( 'jetpack_boost_status_image-cdn' ) ) : ?>
							<span style="color: red; font-weight: bolder;">
								<?php \esc_html_e( 'Inactive, please disable the Image CDN option in the Jetpack Boost settings.', 'easy-image-optimizer' ); ?>
							</span>
						<?php else : ?>
							<?php
							if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) :
								$exactdn_savings = $exactdn->savings();
								?>
								<span style="color: #3eadc9; font-weight: bolder;"><?php \esc_html_e( 'Verified', 'easy-image-optimizer' ); ?></span><br>
								<span style="font-weight:normal;line-height:1.8em;"><?php echo \esc_html( $exactdn->get_exactdn_domain() ); ?></span>
								<?php
								if ( ! empty( $exactdn_savings ) && ! empty( $exactdn_savings['original'] ) && ! empty( $exactdn_savings['savings'] ) ) :
									$exactdn_percent = \round( $exactdn_savings['savings'] / $exactdn_savings['original'], 3 ) * 100;
									?>
									<br><?php \esc_html_e( 'Image Savings:', 'easy-image-optimizer' ); ?>
									<span style="font-weight:normal;"><?php echo \esc_html( $exactdn_percent . '% (' . $this->size_format( $exactdn_savings['savings'], 1 ) . ')' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<?php
								$this->debug_message( 'could not verify: ' . $exactdn->get_exactdn_domain() );
								if ( $exactdn->get_exactdn_domain() ) {
									\update_option( 'easyio_exactdn', false );
									\delete_option( 'easyio_exactdn_domain' );
								}
								?>
								<span style="color: red; font-weight: bolder"><a href="https://ewww.io/manage-sites/" target="_blank"><?php \esc_html_e( 'Not Verified', 'easy-image-optimizer' ); ?></a></span>
							<?php endif; ?>
							<?php if ( \function_exists( 'remove_query_strings_link' ) || \function_exists( 'rmqrst_loader_src' ) || \function_exists( 'qsr_remove_query_strings_1' ) ) : ?>
								<p>
									<em><?php \esc_html_e( 'Plugins that remove query strings are obsolete and should not be used with Easy IO. You may remove them at your convenience.', 'easy-image-optimizer' ); ?></em>
									<?php $this->help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' ); ?>
								</p>
							<?php endif; ?>
							<?php
						endif;
					else :
						\delete_option( 'easyio_exactdn_domain' );
						\delete_option( 'easyio_exactdn_local_domain' );
						\delete_option( 'easyio_exactdn_verified' );
						\delete_option( 'easyio_exactdn_validation' );
						?>
						<span style="color: #747474; font-weight: bolder;"><?php \esc_html_e( 'Inactive', 'easy-image-optimizer' ); ?></span>
					<?php endif; ?>
				</div><!-- end easyio-status -->
			</div><!-- end easyio-header-flex -->
		</div><!-- end easyio-header-wrapper -->
		<ul class='easyio-tab-nav'>
			<li class='easyio-tab easyio-general-nav easyio-selected'>
				<span class='easyio-tab-hidden'><?php \esc_html_e( 'Configure', 'easy-image-optimizer' ); ?></span>
			</li>
			<li class='easyio-tab easyio-support-nav'>
				<span class='easyio-tab-hidden'><?php \esc_html_e( 'Support', 'easy-image-optimizer' ); ?></span>
			</li>
		</ul>
		<form method='post' action='options.php'>
			<input type='hidden' name='option_page' value='easyio_options' />
			<input type='hidden' name='action' value='update' />
			<?php \wp_nonce_field( 'easyio_options-options' ); ?>

			<div id='easyio-general-settings'>
				<noscript><h2><?php \esc_html_e( 'Configure', 'easy-image-optimizer' ); ?></h2></noscript>
				<?php if ( ! $this->get_option( 'easyio_exactdn' ) ) : ?>
				<table class='form-table easyio-inactive'>
					<tr>
						<td>
							<div id='easyio-activation-result'></div>
							<ol>
								<li>
									<a href="https://ewww.io/easy/" target="_blank">
										<?php \esc_html_e( 'Start a free trial subscription for your site.', 'easy-image-optimizer' ); ?>
									</a>
								</li>
								<li>
									<a href="<?php echo \esc_url( \add_query_arg( 'site_url', \trim( $site_url ), 'https://ewww.io/manage-sites/' ) ); ?>" target="_blank">
										<?php \esc_html_e( 'Add your Site URL to your account:', 'easy-image-optimizer' ); ?>
									</a>
									<?php echo \esc_html( $site_url ); ?>
								</li>
								<li>
									<a id="easyio-activate" href="<?php echo \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=easyio_activate' ), 'easy-image-optimizer-settings' ) ); ?>" class="button-primary">
										<?php \esc_html_e( 'Activate', 'easy-image-optimizer' ); ?>
									</a>
									<span id='easyio-activation-processing'><img src='<?php echo \esc_url( $loading_image_url ); ?>' alt='loading' /></span>
								</li>
								<li>
									<?php \esc_html_e( 'Done!', 'easy-image-optimizer' ); ?>
								</li>
							</ol>
						</td>
					</tr>
				</table>
				<table class='form-table easyio-settings-table' style='display:none;'>
				<?php else : ?>
				<table class='form-table easyio-settings-table'>
				<?php endif; ?>
					<tr>
						<th scope='row'>&nbsp;</th>
						<td>
							<a href="https://ewww.io/subscriptions/" class="page-title-action">
								<?php \esc_html_e( 'Manage Subscription', 'easy-image-optimizer' ); ?>
							</a>&nbsp;&nbsp;
							<a href="<?php echo \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=easyio_deactivate' ), 'easy-image-optimizer-settings' ) ); ?>" class="page-title-action">
								<?php \esc_html_e( 'Disable Optimizer', 'easy-image-optimizer' ); ?>
							</a>
						</td>
					</tr>
					<tr>
						<th scope='row'>&nbsp;</th>
						<td>
							<p class='description'>
								<a href='https://ewww.io/manage-sites/' target='_blank'>
									<?php \esc_html_e( 'Manage Premium Compression and WebP/AVIF Conversion in the site settings at ewww.io.', 'easy-image-optimizer' ); ?>
								</a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope='row'>
							<label for='exactdn_all_the_things'><?php \esc_html_e( 'Include All Resources', 'easy-image-optimizer' ); ?></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ); ?>
						</th>
						<td>
							<input type='checkbox' name='exactdn_all_the_things' value='true' id='exactdn_all_the_things' <?php \checked( $this->get_option( 'exactdn_all_the_things' ) ); ?> />
							<?php \esc_html_e( 'Replace URLs for all resources in wp-includes/ and wp-content/, including JavaScript, CSS, fonts, etc.', 'easy-image-optimizer' ); ?>
						</td>
					</tr>
					<tr>
						<th scope='row'>
							<label for='exactdn_exclude'><?php \esc_html_e( 'Exclusions', 'easy-image-optimizer' ); ?></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/68-exactdn-exclude', '5c0042892c7d3a31944e88a4' ); ?>
						</th>
						<td>
							<textarea id='exactdn_exclude' name='exactdn_exclude' rows='3' cols='60'><?php echo \esc_html( $eio_exclude_paths ); ?></textarea>
							<p class='description'>
								<?php \esc_html_e( 'One exclusion per line, no wildcards (*) needed. Any pattern or path provided will not be optimized by Easy IO.', 'easy-image-optimizer' ); ?>
								<?php \esc_html_e( 'Exclude entire pages with page:/xyz/ syntax.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope='row'>
							<label for='easyio_add_missing_dims'><?php \esc_html_e( 'Add Missing Dimensions', 'easy-image-optimizer' ); ?></label>
						</th>
						<td>
							<input type='checkbox' id='easyio_add_missing_dims' name='easyio_add_missing_dims' value='true' <?php \checked( $this->get_option( 'easyio_add_missing_dims' ) ); ?> <?php \disabled( $this->get_option( 'easyio_lazy_load' ), false ); ?> />
							<?php \esc_html_e( 'Add width/height attributes to reduce layout shifts and improve user experience.', 'easy-image-optimizer' ); ?>
							<?php if ( ! $this->get_option( 'easyio_lazy_load' ) ) : ?>
								<p class ='description'>*<?php \esc_html_e( 'Requires Lazy Load.', 'easy-image-optimizer' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope='row'>
							<label for='easyio_lazy_load'><?php \esc_html_e( 'Lazy Load', 'easy-image-optimizer' ); ?></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?>
						</th>
						<td>
							<input type='checkbox' id='easyio_lazy_load' name='easyio_lazy_load' value='true' <?php \checked( $this->get_option( 'easyio_lazy_load' ) ); ?> />
							<?php \esc_html_e( 'Improves actual and perceived loading time by deferring off-screen images.', 'easy-image-optimizer' ); ?>
							<p class='description'>
								<?php \esc_html_e( 'If you have any problems, try disabling Lazy Load and contact support for further assistance.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<label for='easyio_ll_abovethefold'><strong><?php \esc_html_e( 'Above the Fold', 'easy-image-optimizer' ); ?></strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?>
							<input type='number' step='1' min='0' class='small-text' id='easyio_ll_abovethefold' name='easyio_ll_abovethefold' value='<?php	echo \defined( 'EIO_LAZY_FOLD' ) ? (int) \constant( 'EIO_LAZY_FOLD' ) : (int) $this->get_option( 'easyio_ll_abovethefold' ); ?>' <?php \disabled( \defined( 'EIO_LAZY_FOLD' ) ); ?> />
							<?php \esc_html_e( 'Skip this many images from lazy loading so that above the fold images load more quickly.', 'easy-image-optimizer' ); ?>
							<p class='description'>
								<?php \esc_html_e( 'This will exclude images from auto-scaling, which may decrease performance if those images are not properly sized.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<input type='checkbox' name='easyio_use_lqip' value='true' id='easyio_use_lqip' <?php \checked( $this->get_option( 'easyio_use_lqip' ) ); ?> />
							<label for='easyio_use_lqip'><strong>LQIP</strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
							<p>
								<?php \esc_html_e( 'Use low-quality versions of your images as placeholders. Can improve user experience, but may be slower than blank placeholders.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<input type='checkbox' name='easyio_use_dcip' value='true' id='easyio_use_dcip' <?php \checked( $this->get_option( 'easyio_use_dcip' ) ); ?> />
							<label for='easyio_use_dcip'><strong>DCIP</strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ); ?>
							<p>
								<?php \esc_html_e( 'Use dominant-color versions of your images as placeholders. Can improve user experience, but may be slower than blank placeholders.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>
							<label for='easyio_ll_exclude'><strong><?php \esc_html_e( 'Exclusions', 'easy-image-optimizer' ); ?></strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
							<textarea id='easyio_ll_exclude' name='easyio_ll_exclude' rows='3' cols='60'><?php echo \esc_html( $ll_exclude_paths ); ?></textarea>
							<p class='description'>
								<?php \esc_html_e( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc. The class "skip-lazy" and attribute "data-skip-lazy" are excluded by default.', 'easy-image-optimizer' ); ?>
								<?php \esc_html_e( 'Exclude entire pages with page:/xyz/ syntax.', 'easy-image-optimizer' ); ?>
							</p>
						</td>
					</tr>
					<?php if ( \function_exists( 'swis' ) ) : ?>
					<tr>
						<td>&nbsp;</td>
						<td>
							<input type='checkbox' name='easyio_ll_external_bg' value='true' id='easyio_ll_external_bg' <?php \checked( $this->get_option( 'easyio_ll_external_bg' ) && \function_exists( 'swis' ) ); ?> />
							<label for='easyio_ll_external_bg'><strong><?php \esc_html_e( 'External Background Images', 'easy-image-optimizer' ); ?></strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
						</td>
					</tr>
					<?php else : ?>
					<tr>
						<td>&nbsp;</td>
						<td>
							<label for='easyio_ll_all_things'><strong><?php \esc_html_e( 'External Background Images', 'easy-image-optimizer' ); ?></strong></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ); ?><br>
							<textarea id='easyio_ll_all_things' name='easyio_ll_all_things' rows='3' cols='60'><?php echo \esc_html( $this->get_option( 'easyio_ll_all_things' ) ); ?></textarea>
							<p class='description'>
								<?php \esc_html_e( 'Specify class/id values of elements with CSS background images (comma-separated).', 'easy-image-optimizer' ); ?>
								<?php \esc_html_e( 'Can match any text within the target element, like elementor-widget-container or et_pb_column.', 'easy-image-optimizer' ); ?>
								<br>*<?php \esc_html_e( 'Background images directly attached via inline style attributes will be lazy loaded by default.', 'easy-image-optimizer' ); ?>
								<br><a href='https://ewww.io/swis/'><?php \esc_html_e( 'Install SWIS Performance for automatic detection of all background images.', 'easy-image-optimizer' ); ?></a>
							</p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
			<div id='easyio-support-settings' style='display:none;'>
				<noscript><h2><?php \esc_html_e( 'Support', 'easy-image-optimizer' ); ?></h2></noscript>
				<p>
					<a class='easyio-docs-root' href='https://docs.ewww.io/category/76-easy-io'><?php \esc_html_e( 'Documentation', 'easy-image-optimizer' ); ?></a> |
					<a class='easyio-docs-root' href='https://ewww.io/contact-us/'><?php \esc_html_e( 'Plugin Support', 'easy-image-optimizer' ); ?></a> |
					<a href='https://status.ewww.io/'><?php \esc_html_e( 'Server Status', 'easy-image-optimizer' ); ?></a> |
					<a href='https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer/'><?php \esc_html_e( 'Translate Easy IO', 'easy-image-optimizer' ); ?></a> |
					<a href='https://wordpress.org/support/view/plugin-reviews/easy-image-optimizer#postform'><?php \esc_html_e( 'Write a review', 'easy-image-optimizer' ); ?></a>
				</p>
				<p>
					<strong><a class='easyio-docs-root' href='https://ewww.io/contact-us/'>
						<?php \esc_html_e( 'If Easy IO is not working like you think it should, we want to know!', 'easy-image-optimizer' ); ?>
					</a></strong>
				</p>
				<table class='form-table'>
					<tr>
						<th scope='row'>
							<label for='easyio_enable_help'><?php \esc_html_e( 'Enable Embedded Help', 'easy-image-optimizer' ); ?></label>
						</th>
						<td>
							<input type='checkbox' id='easyio_enable_help' name='easyio_enable_help' value='true' <?php \checked( $this->get_option( 'easyio_enable_help' ) ); ?> />
							<?php \esc_html_e( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'easy-image-optimizer' ); ?>
						</td>
					</tr>
					<tr>
						<th scope='row'>
							<label for='easyio_debug'><?php \esc_html_e( 'Debugging', 'easy-image-optimizer' ); ?></label>
							<?php $this->help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ); ?>
						</th>
						<td>
							<input type='checkbox' id='easyio_debug' name='easyio_debug' value='true' <?php \checked( $this->get_option( 'easyio_debug' ) ); ?> />
							<?php \esc_html_e( 'Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', 'easy-image-optimizer' ); ?>
						</td>
					</tr>
				<?php if ( \is_file( $this->debug_log_path() ) ) : ?>
					<tr>
						<th scope='row'>
							<?php \esc_html_e( 'Debug Log', 'easy-image-optimizer' ); ?>
						</th>
						<td>
							<p>
								<a target='_blank' href='<?php echo \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=easyio_view_debug_log' ), 'easy-image-optimizer-settings' ) ); ?>'><?php \esc_html_e( 'View Log', 'easy-image-optimizer' ); ?></a> -
								<a href='<?php echo \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=easyio_delete_debug_log' ), 'easy-image-optimizer-settings' ) ); ?>'><?php \esc_html_e( 'Clear Log', 'easy-image-optimizer' ); ?></a>
							</p>
							<p><a class='button button-secondary' target='_blank' href='<?php echo \esc_url( \wp_nonce_url( \admin_url( 'admin.php?action=easyio_download_debug_log' ), 'easy-image-optimizer-settings' ) ); ?>'><?php \esc_html_e( 'Download Log', 'easy-image-optimizer' ); ?></a></p>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( Base::$debug_data ) ) : ?>
					<tr>
						<th scope='row'>
							<?php \esc_html_e( 'System Info', 'easy-image-optimizer' ); ?>
						</th>
						<td>
							<p class="debug-actions">
								<button id="easyio-copy-debug" class="button button-secondary" type="button"><?php \esc_html_e( 'Copy', 'easy-image-optimizer' ); ?></button>
							</p>
							<div id="easyio-debug-info" style="border:1px solid #e5e5e5;background:#fff;overflow:auto;height:300px;width:800px;margin-top:5px;" contenteditable="true">
								<?php echo \wp_kses_post( Base::$debug_data ); ?>
							</div>
						</td>
					</tr>
				<?php endif; ?>
				</table>

			</div>
			<?php if ( $this->get_option( 'easyio_exactdn' ) ) : ?>
				<p class='submit'>
					<input type='submit' class='button-primary' value='<?php \esc_attr_e( 'Save Changes', 'easy-image-optimizer' ); ?>' />
				</p>
			<?php else : ?>
				<p id='easyio-hidden-submit' style='display:none;' class='submit'>
					<input type='submit' class='button-primary' value='<?php \esc_attr_e( 'Save Changes', 'easy-image-optimizer' ); ?>' />
				</p>
			<?php endif; ?>
		</form>
	</div><!-- end of wrap -->

		<?php
		if ( $this->get_option( 'easyio_enable_help' ) ) {
			$current_user = \wp_get_current_user();
			$help_email   = $current_user->user_email;
			$hs_debug     = '';
			if ( ! empty( Base::$debug_data ) ) {
				$hs_debug = \str_replace( array( "'", '<br>', '<b>', '</b>' ), array( "\'", '\n', '<', '>' ), Base::$debug_data );
			}
			?>
	<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
	<script type="text/javascript">
		window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
		Beacon( 'prefill', {
			email: '<?php echo \esc_js( $help_email ); ?>',
			text: '\n\n----------------------------------------\n<?php echo \wp_kses_post( $hs_debug ); ?>',
		});
	</script>
			<?php
		}
		$this->temp_debug_end();
	}

	/**
	 * Gets the HTML for a help icon linked to the docs.
	 *
	 * @param string $link A link to the documentation.
	 * @param string $hsid The HelpScout ID for the docs article. Optional.
	 * @return string An HTML hyperlink element with a help icon.
	 */
	public function get_help_link( $link, $hsid = '' ) {
		\ob_start();
		$this->help_link( $link, $hsid );
		return \ob_get_clean();
	}

	/**
	 * Displays a help icon linked to the docs.
	 *
	 * @param string $link A link to the documentation.
	 * @param string $hsid The HelpScout ID for the docs article. Optional.
	 * @return string An HTML hyperlink element with a help icon.
	 */
	public function help_link( $link, $hsid = '' ) {
		$help_icon   = \plugins_url( '/images/question-circle.png', EASYIO_PLUGIN_FILE );
		$beacon_attr = '';
		$link_class  = 'easyio-help-icon';
		if ( \str_contains( $hsid, ',' ) ) {
			$beacon_attr = 'data-beacon-articles';
			$link_class  = 'easyio-help-beacon-multi';
		} elseif ( $hsid ) {
			$beacon_attr = 'data-beacon-article';
			$link_class  = 'easyio-help-beacon-single';
		}
		if ( empty( $hsid ) ) {
			echo '<a class="easyio-help-external" href="' . \esc_url( $link ) . '" target="_blank">' .
				'<img title="' . \esc_attr__( 'Help', 'easy-image-optimizer' ) . '" src="' . \esc_url( $help_icon ) . '">' .
				'</a>';
			return;
		}
		echo '<a class="' . \esc_attr( $link_class ) . '" href="' . \esc_url( $link ) . '" target="_blank" ' . \esc_attr( $beacon_attr ) . '="' . \esc_attr( $hsid ) . '">' .
			'<img title="' . \esc_attr__( 'Help', 'easy-image-optimizer' ) . '" src="' . \esc_url( $help_icon ) . '">' .
			'</a>';
	}

	/**
	 * Adds version information to the in-memory debug log.
	 *
	 * @global int $wp_version
	 */
	public function debug_version_info() {
		$eio_debug = 'Easy IO version: ' . EASYIO_VERSION . '<br>';

		// Check the WP version.
		global $wp_version;
		$eio_debug .= "WP version: $wp_version<br>";

		if ( \defined( 'PHP_VERSION_ID' ) ) {
			$eio_debug .= 'PHP version: ' . PHP_VERSION_ID . '<br>';
		}
		Base::$debug_data .= $eio_debug;
	}

	/**
	 * Send our debug information to the log/buffer for the options page.
	 */
	public function debug_info() {
		global $content_width;
		$this->debug_version_info();
		$this->debug_message( 'ABSPATH: ' . ABSPATH );
		$this->debug_message( 'WP_CONTENT_DIR: ' . WP_CONTENT_DIR );
		$this->debug_message( 'home url: ' . \get_home_url() );
		$this->debug_message( 'site url: ' . \get_site_url() );
		$this->debug_message( 'content_url: ' . \content_url() );
		$upload_info = \wp_upload_dir( null, false );
		$this->debug_message( 'upload_dir: ' . $upload_info['basedir'] );
		$this->debug_message( "content_width: $content_width" );

		if ( \class_exists( '\Automattic\Jetpack_Boost\Jetpack_Boost' ) && \get_option( 'jetpack_boost_status_image-cdn' ) ) {
			if ( \get_option( 'jetpack_boost_status_image-cdn' ) ) {
				$this->debug_message( 'Jetpack Boost CDN active' );
			}
		}
		if ( \class_exists( 'Jetpack' ) && \method_exists( 'Jetpack', 'is_module_active' ) && \Jetpack::is_module_active( 'photon' ) ) {
			$this->debug_message( 'Jetpack Photon active' );
		}

		$eio_exclude_paths = $this->get_option( 'exactdn_exclude' ) ? \esc_html( \implode( "\n", $this->get_option( 'exactdn_exclude' ) ) ) : '';
		$ll_exclude_paths  = $this->get_option( 'easyio_ll_exclude' ) ? \esc_html( \implode( "\n", $this->get_option( 'easyio_ll_exclude' ) ) ) : '';
		$this->debug_message( 'ExactDN enabled: ' . ( $this->get_option( 'easyio_exactdn' ) ? 'on' : 'off' ) );
		$this->debug_message( 'ExactDN all the things: ' . ( $this->get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
		$this->debug_message( 'ExactDN resize existing: ' . ( $this->get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
		$this->debug_message( 'ExactDN attachment queries: ' . ( $this->get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
		$this->debug_message( 'Easy IO exclusions:' );
		$this->debug_message( $eio_exclude_paths );
		$this->debug_message( 'LL add missing dims: ' . ( $this->get_option( 'easyio_add_missing_dims' ) ? 'on' : 'off' ) );
		$this->debug_message( 'lazy load: ' . ( $this->get_option( 'easyio_lazy_load' ) ? 'on' : 'off' ) );
		$this->debug_message( 'LL above the fold: ' . $this->get_option( 'easyio_ll_abovethefold' ) );
		$this->debug_message( 'LQIP: ' . ( $this->get_option( 'easyio_use_lqip' ) ? 'on' : 'off' ) );
		$this->debug_message( 'DCIP: ' . ( $this->get_option( 'easyio_use_dcip' ) ? 'on' : 'off' ) );
		$this->debug_message( 'external CSS background (automatic): ' . ( $this->get_option( 'easyio_ll_external_bg' ) ? 'on' : 'off' ) );
		$this->debug_message( 'External CSS Background (all things): ' . $this->get_option( 'easyio_ll_all_things' ) );
		$this->debug_message( 'LL exclusions:' );
		$this->debug_message( $ll_exclude_paths );
		$this->debug_message( 'remove metadata: ' . ( $this->get_option( 'easyio_metadata_remove' ) ? 'on' : 'off' ) );
		$this->debug_message( 'enable help beacon: ' . ( $this->get_option( 'easyio_enable_help' ) ? 'yes' : 'no' ) );
	}

	/**
	 * View the debug log file from the wp-admin.
	 */
	public function view_debug_log() {
		\check_admin_referer( 'easy-image-optimizer-settings' );
		if ( ! \current_user_can( \apply_filters( 'easyio_admin_permissions', 'manage_options' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'easy-image-optimizer' ) );
		}
		if ( \is_file( $this->debug_log_path() ) ) {
			$this->ob_clean();
			\header( 'Content-Type: text/plain;charset=UTF-8' );
			\readfile( $this->debug_log_path() );
			exit;
		}
		\wp_die( \esc_html__( 'The Debug Log is empty.', 'easy-image-optimizer' ) );
	}

	/**
	 * Removes the debug log file from the plugin folder.
	 */
	public function delete_debug_log() {
		\check_admin_referer( 'easy-image-optimizer-settings' );
		if ( ! \current_user_can( \apply_filters( 'easyio_admin_permissions', 'manage_options' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'easy-image-optimizer' ) );
		}
		if ( \is_file( $this->debug_log_path() ) ) {
			\unlink( $this->debug_log_path() );
		}
		$sendback = \wp_get_referer();
		if ( empty( $sendback ) ) {
			$sendback = \admin_url( 'options-general.php?page=easy-image-optimizer-options' );
		}
		\wp_safe_redirect( $sendback );
		exit;
	}

	/**
	 * Download the debug log file from the wp-admin.
	 */
	public function download_debug_log() {
		\check_admin_referer( 'easy-image-optimizer-settings' );
		if ( ! \current_user_can( \apply_filters( 'easyio_admin_permissions', 'manage_options' ) ) ) {
			\wp_die( \esc_html__( 'Access denied.', 'easy-image-optimizer' ) );
		}
		$debug_log = $this->debug_log_path();
		if ( \is_file( $debug_log ) ) {
			$this->ob_clean();
			\header( 'Content-Description: File Transfer' );
			\header( 'Content-Type: text/plain;charset=UTF-8' );
			\header( 'Content-Disposition: attachment; filename=easyio-debug-log-' . \gmdate( 'Ymd-His' ) . '.txt' );
			\header( 'Expires: 0' );
			\header( 'Cache-Control: must-revalidate' );
			\header( 'Pragma: public' );
			\header( 'Content-Length: ' . \filesize( $debug_log ) );
			\readfile( $debug_log );
			exit;
		}
		\wp_die( \esc_html__( 'The Debug Log is empty.', 'easy-image-optimizer' ) );
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
			\add_action( 'network_admin_notices', array( $this, 'network_settings_saved' ) );
		} elseif ( isset( $_POST['easyio_allow_multisite_override_active'] ) && \current_user_can( 'manage_network_options' ) && ! empty( $_REQUEST['_wpnonce'] ) && \wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'easyio_options-options' ) ) {
			$this->debug_message( 'network-wide settings, single-site overriding' );
			$easyio_allow_multisite_override = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			\update_site_option( 'easyio_allow_multisite_override', $easyio_allow_multisite_override );
			\add_action( 'network_admin_notices', array( $this, 'network_settings_saved' ) );
		} // End if().
	}

	/**
	 * Lets the user know their network settings have been saved.
	 */
	public function network_settings_saved() {
		echo "<div id='easy-image-optimizer-settings-saved' class='notice notice-success updated fade'><p><strong>" . \esc_html__( 'Settings saved', 'easy-image-optimizer' ) . '.</strong></p></div>';
	}
}
