<?php
/**
 * Functions unique to Easy IO ported from EWWW IO
 *
 * @link https://ewww.io/easy/
 * @package Easy_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EASYIO_VERSION', '321' );

// Initialize a couple globals.
$eio_debug = '';

/*
 * Hooks
 */

// Activation routine for Easy IO/ExactDN.
add_action( 'admin_action_easyio_activate', 'easyio_activate' );
// De-activation routine for Easy IO/ExactDN.
add_action( 'admin_action_easyio_deactivate', 'easyio_deactivate' );
// Filters to set default permissions, admins can override these if they wish.
add_filter( 'easyio_admin_permissions', 'easyio_admin_permissions', 8 );
add_filter( 'easyio_superadmin_permissions', 'easyio_superadmin_permissions', 8 );
// Add a link to the plugins page so the user can go straight to the settings page.
$easyio_plugin_slug = plugin_basename( EASYIO_PLUGIN_FILE );
add_filter( "plugin_action_links_$easyio_plugin_slug", 'easyio_settings_link' );
// Runs any checks that need to run everywhere and early.
add_action( 'init', 'easyio_init', 9 );
// Load our front-end parsers for ExactDN and/or Alt WebP.
add_action( 'init', 'easyio_parser_init', 99 );
// Initializes the plugin for admin interactions, like saving network settings and scheduling cron jobs.
add_action( 'admin_init', 'easyio_admin_init' );
// Check the current screen ID to see if temp debugging should still be enabled.
add_action( 'current_screen', 'easyio_current_screen', 10, 1 );
// Adds the Easy IO pages to the admin menu.
add_action( 'admin_menu', 'easyio_admin_menu', 60 );
// Adds the Easy IO settings to the network admin menu.
add_action( 'network_admin_menu', 'easyio_network_admin_menu' );
// Adds scripts for the Easy IO settings page.
add_action( 'admin_enqueue_scripts', 'easyio_settings_script' );
// Non-AJAX handler to view the debug log, and display it.
add_action( 'admin_action_easyio_view_debug_log', 'easyio_view_debug_log' );
// Non-AJAX handler to delete the debug log, and reroute back to the settings page.
add_action( 'admin_action_easyio_delete_debug_log', 'easyio_delete_debug_log' );
// Makes sure to flush out any scheduled jobs on deactivation.
register_deactivation_hook( EASYIO_PLUGIN_FILE, 'easyio_network_deactivate' );
// Makes sure we flush the debug info to the log on shutdown.
add_action( 'shutdown', 'easyio_debug_log' );
// Disable core WebP generation since we already do that.
add_filter( 'wp_upload_image_mime_transforms', '__return_empty_array' );

/**
 * Attempt to activate the site with ExactDN/Easy IO.
 */
function easyio_activate() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'easyio_admin_permissions', '' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'You do not have permission to activate the Easy Image Optimizer service.', 'easy-image-optimizer' ) );
	}
	update_option( 'easyio_exactdn', true );
	update_option( 'exactdn_all_the_things', true );
	update_option( 'exactdn_lossy', true );
	update_option( 'easyio_lazy_load', true );
	if ( easyio_get_option( 'ewww_image_optimizer_exactdn' ) ) {
		update_option( 'ewww_image_optimizer_exactdn', false );
	}
	if ( easyio_get_option( 'ewww_image_optimizer_lazy_load' ) ) {
		update_option( 'ewww_image_optimizer_lazy_load', false );
	}
	if ( easyio_get_option( 'ewww_image_optimizer_webp_for_cdn' ) ) {
		update_option( 'ewww_image_optimizer_webp_for_cdn', false );
	}
	$sendback = wp_get_referer();
	wp_redirect( esc_url_raw( $sendback ) );
	exit( 0 );
}

/**
 * De-activate the site with ExactDN/Easy IO.
 */
function easyio_deactivate() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	$permissions = apply_filters( 'easyio_admin_permissions', '' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'You do not have permission to activate the Easy Image Optimizer service.', 'easy-image-optimizer' ) );
	}
	update_option( 'easyio_exactdn', false );
	update_option( 'easyio_lazy_load', false );
	global $exactdn;
	if ( isset( $exactdn ) && is_object( $exactdn ) ) {
		$exactdn->cron_setup( false );
	}
	wp_safe_redirect( wp_get_referer() );
	exit( 0 );
}

/**
 * Setup page parsing classes after theme functions.php is loaded and plugins have run init routines.
 */
function easyio_parser_init() {
	$buffer_start = false;
	// If ExactDN is enabled.
	if ( easyio_get_option( 'easyio_exactdn' ) && empty( $_GET['exactdn_disable'] ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EASYIO_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once( EASYIO_PLUGIN_PATH . 'classes/class-exactdn.php' );
	}
	// If Lazy Load is enabled.
	if ( easyio_get_option( 'easyio_lazy_load' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( EASYIO_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * Lazy Load class for parsing image urls and deferring off-screen images.
		 */
		require_once( EASYIO_PLUGIN_PATH . 'classes/class-eio-lazy-load.php' );

		global $eio_lazy_load;
		$eio_lazy_load = new EIO_Lazy_Load();
	}
	if ( $buffer_start ) {
		// Start an output buffer before any output starts.
		add_action( 'template_redirect', 'easyio_buffer_start', 0 );
	}
}

/**
 * Starts an output buffer and registers the callback function to do WebP replacement.
 */
function easyio_buffer_start() {
	ob_start( 'easyio_filter_page_output' );
}

/**
 * Run the page through any registered Easy IO filters.
 *
 * @param string $buffer The full HTML page generated since the output buffer was started.
 * @return string The altered buffer (HTML page).
 */
function easyio_filter_page_output( $buffer ) {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	return apply_filters( 'easyio_filter_page_output', $buffer );
}

/**
 * Checks to see if the WebP option from the Cache Enabler plugin is enabled.
 *
 * @return bool True if the WebP option for CE is enabled.
 */
function easyio_ce_webp_enabled() {
	if ( class_exists( 'Cache_Enabler' ) ) {
		$ce_options = Cache_Enabler::$options;
		if ( $ce_options['webp'] ) {
			easyio_debug_message( 'Cache Enabler webp option enabled' );
			return true;
		}
	}
	return false;
}

/**
 * Checks to see if the WebP rules from WPFC are enabled.
 *
 * @return bool True if the WebP rules from WPFC are found.
 */
function easyio_wpfc_webp_enabled() {
	if ( class_exists( 'WpFastestCache' ) ) {
		$wpfc_abspath = get_home_path() . '.htaccess';
		easyio_debug_message( "looking for WPFC rules in $wpfc_abspath" );
		$wpfc_rules = easyio_extract_from_markers( $wpfc_abspath, 'WEBPWpFastestCache' );
		if ( empty( $wpfc_rules ) ) {
			$wpfc_abspath = ABSPATH . '.htaccess';
			easyio_debug_message( "looking for WPFC rules in $wpfc_abspath" );
			$wpfc_rules = easyio_extract_from_markers( $wpfc_abspath, 'WEBPWpFastestCache' );
		}
		if ( ! empty( $wpfc_rules ) ) {
			easyio_debug_message( 'WPFC webp rules enabled' );
			if ( easyio_get_option( 'easyio_exactdn' ) ) {
				easyio_debug_message( 'removing htaccess webp to prevent ExactDN problems' );
				insert_with_markers( $wpfc_abspath, 'WEBPWpFastestCache', '' );
				return false;
			}
			return true;
		}
	}
	return false;
}

/**
 * Set default permissions for admin (configuration) and bulk operations.
 *
 * @param string $permissions A valid WP capability level.
 * @return string Either the original value, unchanged, or the default capability level.
 */
function easyio_admin_permissions( $permissions ) {
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
function easyio_superadmin_permissions( $permissions ) {
	if ( empty( $permissions ) ) {
		return 'manage_network_options';
	}
	return $permissions;
}

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in WP 5.9 or PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @since 3.2.0
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		if ( '' === $haystack && '' !== $needle ) {
			return false;
		}

		$len = strlen( $needle );

		return 0 === substr_compare( $haystack, $needle, -$len, $len );
	}
}

/**
 * Checks if a function is disabled or does not exist.
 *
 * @param string $function The name of a function to test.
 * @param bool   $debug Whether to output debugging.
 * @return bool True if the function is available, False if not.
 */
function easyio_function_exists( $function, $debug = false ) {
	if ( function_exists( 'ini_get' ) ) {
		$disabled = @ini_get( 'disable_functions' );
		if ( $debug ) {
			easyio_debug_message( "disable_functions: $disabled" );
		}
	}
	if ( extension_loaded( 'suhosin' ) && function_exists( 'ini_get' ) ) {
		$suhosin_disabled = @ini_get( 'suhosin.executor.func.blacklist' );
		if ( $debug ) {
			easyio_debug_message( "suhosin_blacklist: $suhosin_disabled" );
		}
		if ( ! empty( $suhosin_disabled ) ) {
			$suhosin_disabled = explode( ',', $suhosin_disabled );
			$suhosin_disabled = array_map( 'trim', $suhosin_disabled );
			$suhosin_disabled = array_map( 'strtolower', $suhosin_disabled );
			if ( function_exists( $function ) && ! in_array( $function, $suhosin_disabled, true ) ) {
				return true;
			}
			return false;
		}
	}
	return function_exists( $function );
}

/**
 * Runs early for checks that need to happen on init before anything else.
 */
function easyio_init() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Check to see if this is the settings page and enable debugging temporarily if it is.
	global $easyio_temp_debug;
	$easyio_temp_debug = false;
	if ( is_admin() && ! wp_doing_ajax() ) {
		if ( ! easyio_get_option( 'easyio_debug' ) ) {
			$easyio_temp_debug = true;
		}
	}
}

/**
 * Set some default option values.
 */
function easyio_set_defaults() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Set defaults for all options that need to be autoloaded.
	add_option( 'easyio_debug', false );
	add_option( 'easyio_metadata_remove', true );
	add_option( 'easyio_exactdn', false );
	add_option( 'easyio_plan_id', 0 );
	add_option( 'exactdn_all_the_things', false );
	add_option( 'exactdn_lossy', false );
	add_option( 'exactdn_exclude', '' );
	add_option( 'exactdn_sub_folder', false );
	add_option( 'exactdn_prevent_db_queries', true );
	add_option( 'easyio_add_missing_dims', true );
	add_option( 'easyio_lazy_load', false );
	add_option( 'easyio_use_lqip', false );
	add_option( 'easyio_use_siip', false );
	add_option( 'easyio_ll_autoscale', true );
	add_option( 'easyio_ll_exclude', '' );

	// Set network defaults.
	add_site_option( 'easyio_metadata_remove', true );
	add_site_option( 'easyio_add_missing_dims', true );
	add_site_option( 'easyio_ll_autoscale', true );
	add_site_option( 'exactdn_sub_folder', false );
	add_site_option( 'exactdn_prevent_db_queries', true );
}

/**
 * Plugin upgrade function
 *
 * @global object $wpdb
 */
function easyio_upgrade() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( get_option( 'easyio_version' ) < EASYIO_VERSION ) {
		if ( wp_doing_ajax() ) {
			return;
		}
		easyio_set_defaults();
		// This will get re-enabled if things are too slow.
		update_option( 'exactdn_prevent_db_queries', true );
		if ( easyio_get_option( 'easyio_exactdn_verify_method' ) > 0 ) {
			delete_option( 'easyio_exactdn_verify_method' );
			delete_site_option( 'easyio_exactdn_verify_method' );
		}
		if ( ! get_option( 'easyio_version' ) && ! easyio_get_option( 'easyio_exactdn' ) ) {
			add_option( 'exactdn_never_been_active', true, '', false );
		}
		update_option( 'easyio_version', EASYIO_VERSION );
		easyio_debug_log();
	}
}

/**
 * Plugin initialization for admin area.
 *
 * Saves settings when run network-wide, registers all 'common' settings, schedules wp-cron tasks,
 * includes necessary files for bulk operations, runs tool initialization, and ensures
 * compatibility with AJAX calls from other media generation plugins.
 */
function easyio_admin_init() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	/**
	 * EWWWIO_HS_Beacon class for embedding the HelpScout Beacon.
	 */
	require_once( EASYIO_PLUGIN_PATH . 'classes/class-eio-hs-beacon.php' );
	easyio_upgrade();
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) ) {
		easyio_debug_message( 'saving network settings' );
		// Set the common network settings if they have been POSTed.
		if ( isset( $_POST['option_page'] ) && false !== strpos( $_POST['option_page'], 'easyio_options' ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'easyio_options-options' ) && current_user_can( 'manage_network_options' ) && ! get_site_option( 'easyio_allow_multisite_override' ) && false === strpos( $_POST['_wp_http_referer'], 'options-general' ) ) {
			easyio_debug_message( 'network-wide settings, no override' );
			$_POST['easyio_debug'] = ( empty( $_POST['easyio_debug'] ) ? false : true );
			update_site_option( 'easyio_debug', $_POST['easyio_debug'] );
			$_POST['easyio_metadata_remove'] = ( empty( $_POST['easyio_metadata_remove'] ) ? false : true );
			update_site_option( 'easyio_metadata_remove', $_POST['easyio_metadata_remove'] );
			$_POST['easyio_exactdn'] = ( empty( $_POST['easyio_exactdn'] ) ? false : true );
			update_site_option( 'easyio_exactdn', $_POST['easyio_exactdn'] );
			$_POST['exactdn_all_the_things'] = ( empty( $_POST['exactdn_all_the_things'] ) ? false : true );
			update_site_option( 'exactdn_all_the_things', $_POST['exactdn_all_the_things'] );
			$_POST['exactdn_lossy'] = ( empty( $_POST['exactdn_lossy'] ) ? false : true );
			update_site_option( 'exactdn_lossy', $_POST['exactdn_lossy'] );
			$_POST['exactdn_exclude'] = empty( $_POST['exactdn_exclude'] ) ? '' : $_POST['exactdn_exclude'];
			update_site_option( 'exactdn_exclude', easyio_exclude_paths_sanitize( $_POST['exactdn_exclude'] ) );
			$_POST['easyio_add_missing_dims'] = ( empty( $_POST['easyio_add_missing_dims'] ) ? false : true );
			update_site_option( 'easyio_add_missing_dims', $_POST['easyio_add_missing_dims'] );
			$_POST['easyio_lazy_load'] = ( empty( $_POST['easyio_lazy_load'] ) ? false : true );
			update_site_option( 'easyio_lazy_load', $_POST['easyio_lazy_load'] );
			$_POST['easyio_use_lqip'] = ( empty( $_POST['easyio_use_lqip'] ) ? false : true );
			update_site_option( 'easyio_use_lqip', $_POST['easyio_use_lqip'] );
			$_POST['easyio_ll_exclude'] = empty( $_POST['easyio_ll_exclude'] ) ? '' : $_POST['easyio_ll_exclude'];
			update_site_option( 'easyio_ll_exclude', easyio_exclude_paths_sanitize( $_POST['easyio_ll_exclude'] ) );
			$_POST['easyio_allow_multisite_override'] = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			update_site_option( 'easyio_allow_multisite_override', $_POST['easyio_allow_multisite_override'] );
			$_POST['easyio_enable_help'] = empty( $_POST['easyio_enable_help'] ) ? false : true;
			update_site_option( 'easyio_enable_help', $_POST['easyio_enable_help'] );
			add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} elseif ( isset( $_POST['easyio_allow_multisite_override_active'] ) && current_user_can( 'manage_network_options' ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'easyio_options-options' ) ) {
			easyio_debug_message( 'network-wide settings, single-site overriding' );
			$_POST['easyio_allow_multisite_override'] = empty( $_POST['easyio_allow_multisite_override'] ) ? false : true;
			update_site_option( 'easyio_allow_multisite_override', $_POST['easyio_allow_multisite_override'] );
			add_action( 'network_admin_notices', 'easyio_network_settings_saved' );
		} // End if().
	} // End if().
	// Register all the common Easy IO settings.
	register_setting( 'easyio_options', 'easyio_debug', 'boolval' );
	register_setting( 'easyio_options', 'easyio_enable_help', 'boolval' );
	register_setting( 'easyio_options', 'easyio_exactdn', 'boolval' );
	register_setting( 'easyio_options', 'exactdn_all_the_things', 'boolval' );
	register_setting( 'easyio_options', 'exactdn_lossy', 'boolval' );
	register_setting( 'easyio_options', 'exactdn_exclude', 'easyio_exclude_paths_sanitize' );
	register_setting( 'easyio_options', 'easyio_add_missing_dims', 'boolval' );
	register_setting( 'easyio_options', 'easyio_lazy_load', 'boolval' );
	register_setting( 'easyio_options', 'easyio_use_lqip', 'boolval' );
	register_setting( 'easyio_options', 'easyio_ll_exclude', 'easyio_exclude_paths_sanitize' );
	if ( ! class_exists( 'ExactDN' ) || ! easyio_get_option( 'easyio_exactdn' ) ) {
		add_action( 'network_admin_notices', 'easyio_notice_inactive' );
		add_action( 'admin_notices', 'easyio_notice_inactive' );
	}
	// Prevent ShortPixel AIO messiness.
	remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
	if ( class_exists( 'autoptimizeExtra' ) ) {
		$ao_extra = get_option( 'autoptimize_imgopt_settings' );
		if ( easyio_get_option( 'easyio_exactdn' ) && ! empty( $ao_extra['autoptimize_imgopt_checkbox_field_1'] ) ) {
			easyio_debug_message( 'detected ExactDN + SP conflict' );
			$ao_extra['autoptimize_imgopt_checkbox_field_1'] = 0;
			update_option( 'autoptimize_imgopt_settings', $ao_extra );
			add_action( 'admin_notices', 'easyio_notice_sp_conflict' );
		}
	}

	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		easyio_privacy_policy_content();
	}
	// Increase the version when the next bump is coming.
	if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID < 50600 ) {
		add_action( 'network_admin_notices', 'easyio_php55_warning' );
		add_action( 'admin_notices', 'easyio_php55_warning' );
	}
}

/**
 * Adds suggested privacy policy content for site admins.
 *
 * Note that this is just a suggestion, it should be customized for your site.
 */
function easyio_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! function_exists( 'wp_kses_post' ) ) {
		return;
	}
	$content  = '<p class="privacy-policy-tutorial">';
	$content .= wp_kses_post( __( 'Normally, this plugin does not process any information about your visitors. However, if you accept user-submitted images and display them on your site, you can use this language to keep your visitors informed.', 'easy-image-optimizer' ) ) . '</p>';
	$content .= '<p>' . wp_kses_post( __( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).' ) ) . '</p>';
	wp_add_privacy_policy_content( 'Easy Image Optimizer', $content );
}

/**
 * Check the current screen, currently used to temporarily enable debugging on settings page.
 *
 * @param object $screen Information about the page/screen currently being loaded.
 */
function easyio_current_screen( $screen ) {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	global $easyio_temp_debug;
	global $eio_debug;
	if ( false === strpos( $screen->id, 'settings_page_ewww-image-optimizer' ) && false === strpos( $screen->id, 'settings_page_easy-image-optimizer' ) ) {
		$easyio_temp_debug = false;
		if ( ! function_exists( 'ewww_image_optimizer' ) && ! easyio_get_option( 'easyio_debug' ) ) {
			$eio_debug = '';
		}
	}
}

/**
 * Let the user know they need to take action!
 */
function easyio_notice_inactive() {
	$settings_url = esc_url( admin_url( 'options-general.php?page=easy-image-optimizer-options' ) );
	echo "<div id='easyio-inactive' class='notice notice-warning'><p>" .
		"<a href='$settings_url'>" . esc_html__( 'Please visit the settings page to complete activation of the Easy Image Optimizer.', 'easy-image-optimizer' ) . '</a></p></div>';
}

/**
 * Display a notice that we could not activate an ExactDN domain.
 */
function easyio_notice_exactdn_activation_error() {
	global $exactdn_activate_error;
	if ( empty( $exactdn_activate_error ) ) {
		$exactdn_activate_error = 'error unknown';
	}
	echo '<div id="easyio-notice-exactdn-error" class="notice notice-error"><p>' .
		sprintf(
			/* translators: %s: A link to the documentation */
			esc_html__( 'Could not activate Easy Image Optimizer, please try again in a few minutes. If this error continues, please see %s for troubleshooting steps.', 'easy-image-optimizer' ),
			'https://docs.ewww.io/article/66-exactdn-not-verified'
		) .
		'<br><code>' . $exactdn_activate_error . '</code>' .
		'</p></div>';
}

/**
 * Let the user know ExactDN setup was successful.
 */
function easyio_notice_exactdn_activation_success() {
	echo '<div id="easyio-notice-exactdn-success" class="notice notice-success"><p>' .
		esc_html__( 'Easy Image Optimizer setup and verification is complete.', 'easy-image-optimizer' ) .
		'</p></div>';
}

/**
 * Let the user know the local domain appears to have changed from what Easy IO has recorded in the db.
 */
function easyio_notice_exactdn_domain_mismatch() {
	global $exactdn;
	if ( ! isset( $exactdn->upload_domain ) ) {
		return;
	}
	?>
	<div id="easyio-notice-exactdn-domain-mismatch" class="notice notice-warning">
		<p>
	<?php
			printf(
				/* translators: 1: old domain name, 2: current domain name */
				esc_html__( 'Easy IO detected that the Site URL has changed since the initial activation (previously %1$s, currently %2$s).', 'easy-image-optimizer' ),
				'<strong>' . esc_html( easyio_get_option( 'easyio_exactdn_local_domain' ) ) . '</strong>',
				'<strong>' . esc_html( $exactdn->upload_domain ) . '</strong>'
			);
	?>
			<br>
		<?php
		printf(
			/* translators: %s: settings page */
			esc_html__( 'Please visit the %s to refresh the Easy IO settings and verify activation status.', 'easy-image-optimizer' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=easy-image-optimizer-options' ) ) . '">' . esc_html__( 'settings page', 'easy-image-optimizer' ) . '</a>'
		);
		?>
		</p>
	</div>
	<?php
}

/**
 * Let the user know they need to disable the WP Offload Media CNAME.
 */
function easyio_notice_exactdn_as3cf_cname_active() {
	?>
	<div id="easyio-notice-exactdn-as3cf-cname-active" class="notice notice-error">
		<p>
			<?php esc_html_e( 'Easy IO cannot optimize your images while using a custom domain (CNAME) in WP Offload Media. Please disable the custom domain in the WP Offload Media settings.', 'easy-image-optimizer' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
 */
function easyio_notice_sp_conflict() {
	echo "<div id='easyio-sp-conflict' class='notice notice-warning'><p>" .
		esc_html__( 'ShortPixel/Autoptimize image optimization has been disabled to prevent conflicts with Easy Image Optimizer).', 'easy-image-optimizer' ) .
		'</p></div>';
}

/**
 * Display a notice that PHP version 5.5 support is going away.
 */
function easyio_php55_warning() {
	echo '<div id="easyio-notice-php55" class="notice notice-info"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' .
		esc_html__( 'The next major release of Easy Image Optimizer will require PHP 7.0 or greater. Newer versions of PHP, like 7.1 and 7.2, are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'easy-image-optimizer' ) .
		'</a></p></div>';
}

/**
 * Inform the user of our beacon function so that they can opt-in.
 */
function easyio_notice_beacon() {
	$optin_url  = 'admin.php?action=eio_opt_into_hs_beacon';
	$optout_url = 'admin.php?action=eio_opt_out_of_hs_beacon';
	echo '<div id="easyio-hs-beacon" class="notice notice-info"><p>' .
		esc_html__( 'Enable the Easy IO support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we collect the current url, IP address, browser/device information, and debugging information.', 'easy-image-optimizer' ) .
		'<br><a href="' . esc_url( $optin_url ) . '" class="button-secondary">' . esc_html__( 'Allow', 'easy-image-optimizer' ) . '</a>' .
		'&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary">' . esc_html__( 'Do not allow', 'easy-image-optimizer' ) . '</a>' .
		'</p></div>';
}

/**
 * Lets the user know their network settings have been saved.
 */
function easyio_network_settings_saved() {
	echo "<div id='easy-image-optimizer-settings-saved' class='notice notice-success updated fade'><p><strong>" . esc_html__( 'Settings saved', 'easy-image-optimizer' ) . '.</strong></p></div>';
}

/**
 * Clears scheduled jobs for multisite when the plugin is deactivated.
 *
 * @global object $wpdb
 *
 * @param bool $network_wide True if plugin was network-activated.
 */
function easyio_network_deactivate( $network_wide ) {
	global $wpdb;
	if ( $network_wide ) {
		$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
		if ( easyio_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				update_option( 'easyio_exactdn', false );
				update_option( 'easyio_lazy_load', false );
				restore_current_blog();
			}
		}
	} else {
		update_option( 'easyio_exactdn', false );
		update_option( 'easyio_lazy_load', false );
	}
}

/**
 * Adds a global settings page to the network admin settings menu.
 */
function easyio_network_admin_menu() {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) ) {
		$permissions = apply_filters( 'easyio_superadmin_permissions', '' );
		// Add options page to the settings menu.
		$easyio_network_options_page = add_submenu_page(
			'settings.php',                 // Slug of parent.
			'Easy Image Optimizer',         // Page Title.
			'Easy Image Optimizer',         // Menu title.
			$permissions,                   // Capability.
			'easy-image-optimizer-options', // Slug.
			'easyio_network_options'        // Function to call.
		);
	}
}

/**
 * Adds various items to the admin menu.
 */
function easyio_admin_menu() {
	// Add options page to the settings menu.
	$easyio_options_page = add_options_page(
		'Easy Image Optimizer',                                        // Page title.
		'Easy Image Optimizer',                                        // Menu title.
		apply_filters( 'easyio_admin_permissions', 'manage_options' ), // Capability.
		'easy-image-optimizer-options',                                // Slug.
		'easyio_options'                                               // Function to call.
	);
}

/**
 * Adds a link on the Plugins page for the Easy IO settings.
 *
 * @param array $links A list of links to display next to the plugin listing.
 * @return array The new list of links to be displayed.
 */
function easyio_settings_link( $links ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	// Load the html for the settings link.
	if ( is_multisite() && is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) ) {
		$settings_link = '<a href="network/settings.php?page=easy-image-optimizer-options">' . esc_html__( 'Settings', 'easy-image-optimizer' ) . '</a>';
	} else {
		$settings_link = '<a href="options-general.php?page=easy-image-optimizer-options">' . esc_html__( 'Settings', 'easy-image-optimizer' ) . '</a>';
	}
	// Load the settings link into the plugin links array.
	array_unshift( $links, $settings_link );
	// Send back the plugin links array.
	return $links;
}

/**
 * Check filesize, and prevent errors by ensuring file exists, and that the cache has been cleared.
 *
 * @param string $file The name of the file.
 * @return int The size of the file or zero.
 */
function easyio_filesize( $file ) {
	if ( is_file( $file ) ) {
		// Flush the cache for filesize.
		clearstatcache();
		// Find out the size of the new PNG file.
		return filesize( $file );
	} else {
		return 0;
	}
}

/**
 * Adds the Easy IO version to the useragent for http requests.
 *
 * @param string $useragent The current useragent used in http requests.
 * @return string The useragent with the Easy IO version appended.
 */
function easyio_cloud_useragent( $useragent ) {
	if ( strpos( $useragent, 'EIO' ) === false ) {
		$useragent .= ' EIO/' . EASYIO_VERSION . ' ';
	}
	return $useragent;
}

/**
 * Make sure an array/object can be parsed by a foreach().
 *
 * @param mixed $var A variable to test for iteration ability.
 * @return bool True if the variable is iterable.
 */
function easyio_iterable( $var ) {
	return ! empty( $var ) && ( is_array( $var ) || $var instanceof Traversable );
}

/**
 * Wrapper around size_format to remove the decimal from sizes in bytes.
 *
 * @param int $size A filesize in bytes.
 * @param int $precision Number of places after the decimal separator.
 * @return string Human-readable filesize.
 */
function easyio_size_format( $size, $precision = 1 ) {
		// Convert it to human readable format.
		$size_str = size_format( $size, $precision );
		// Remove spaces and extra decimals when measurement is in bytes.
		return preg_replace( '/\.0+ B ?/', ' B', $size_str );
}

/**
 * Retrieves the path of an attachment via the $id and the $meta.
 *
 * @param array  $meta The attachment metadata.
 * @param int    $id The attachment ID number.
 * @param string $file Optional. Path relative to the uploads folder. Default ''.
 * @param bool   $refresh_cache Optional. True to flush cache prior to fetching path. Default true.
 * @return array {
 *     Information about the file.
 *
 *     @type string The full path to the image.
 *     @type string The path to the uploads folder.
 * }
 */
function easyio_attachment_path( $meta, $id, $file = '', $refresh_cache = true ) {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );

	// Retrieve the location of the WordPress upload folder.
	$upload_dir  = wp_upload_dir( null, false, $refresh_cache );
	$upload_path = trailingslashit( $upload_dir['basedir'] );
	if ( ! $file ) {
		$file = get_post_meta( $id, '_wp_attached_file', true );
	} else {
		easyio_debug_message( 'using prefetched _wp_attached_file' );
	}
	$file_path          = ( 0 !== strpos( $file, '/' ) && ! preg_match( '|^.:\\\|', $file ) ? $upload_path . $file : $file );
	$filtered_file_path = apply_filters( 'get_attached_file', $file_path, $id );
	easyio_debug_message( "WP (filtered) thinks the file is at: $filtered_file_path" );
	if (
		(
			! easyio_stream_wrapped( $filtered_file_path ) ||
			easyio_stream_wrapper_exists()
		)
		&& is_file( $filtered_file_path )
	) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $filtered_file_path ), $upload_path );
	}
	easyio_debug_message( "WP (unfiltered) thinks the file is at: $file_path" );
	if (
		(
			! easyio_stream_wrapped( $file_path ) ||
			easyio_stream_wrapper_exists()
		)
		&& is_file( $file_path )
	) {
		return array( str_replace( '//_imsgalleries/', '/_imsgalleries/', $file_path ), $upload_path );
	}
	if ( 'ims_image' === get_post_type( $id ) && is_array( $meta ) && ! empty( $meta['file'] ) ) {
		easyio_debug_message( "finding path for IMS image: $id " );
		if ( is_dir( $file_path ) && is_file( $file_path . $meta['file'] ) ) {
			// Generate the absolute path.
			$file_path   = $file_path . $meta['file'];
			$upload_path = easyio_upload_path( $file_path, $upload_path );
			easyio_debug_message( "found path for IMS image: $file_path" );
		} elseif ( is_file( $meta['file'] ) ) {
			$file_path   = $meta['file'];
			$upload_path = easyio_upload_path( $file_path, $upload_path );
			easyio_debug_message( "found path for IMS image: $file_path" );
		} else {
			$upload_path = trailingslashit( WP_CONTENT_DIR );
			$file_path   = $upload_path . ltrim( $meta['file'], '/' );
			easyio_debug_message( "checking path for IMS image: $file_path" );
			if ( ! file_exists( $file_path ) ) {
				$file_path = '';
			}
		}
		return array( $file_path, $upload_path );
	}
	if ( is_array( $meta ) && ! empty( $meta['file'] ) ) {
		$file_path = $meta['file'];
		if ( easyio_stream_wrapped( $file_path ) && ! easyio_stream_wrapper_exists() ) {
			return array( '', $upload_path );
		}
		easyio_debug_message( "looking for file at $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$file_path = trailingslashit( $upload_path ) . $file_path;
		easyio_debug_message( "that did not work, try it with the upload_dir: $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
		$upload_path = trailingslashit( WP_CONTENT_DIR ) . 'uploads/';
		$file_path   = $upload_path . $meta['file'];
		easyio_debug_message( "one last shot, using the wp-content/ constant: $file_path" );
		if ( is_file( $file_path ) ) {
			return array( $file_path, $upload_path );
		}
	}
	return array( '', $upload_path );
}

/**
 * Get mimetype based on file extension instead of file contents when speed outweighs accuracy.
 *
 * @param string $path The name of the file.
 * @return string|bool The mime type based on the extension or false.
 */
function easyio_quick_mimetype( $path ) {
	$pathextension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	switch ( $pathextension ) {
		case 'jpg':
		case 'jpeg':
		case 'jpe':
			return 'image/jpeg';
		case 'png':
			return 'image/png';
		case 'gif':
			return 'image/gif';
		case 'webp':
			return 'image/webp';
		case 'pdf':
			return 'application/pdf';
		default:
			if ( empty( $pathextension ) && ! easyio_stream_wrapped( $path ) && is_file( $path ) ) {
				return easyio_mimetype( $path, 'i' );
			}
			return false;
	}
}

/**
 * Check for GD support of both PNG and JPG.
 *
 * @return bool True if full GD support is detected.
 */
function easyio_gd_support() {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( function_exists( 'gd_info' ) ) {
		$gd_support = gd_info();
		easyio_debug_message( 'GD found, supports:' );
		if ( easyio_iterable( $gd_support ) ) {
			foreach ( $gd_support as $supports => $supported ) {
				easyio_debug_message( "$supports: $supported" );
			}
			if ( ( ! empty( $gd_support['JPEG Support'] ) || ! empty( $gd_support['JPG Support'] ) ) && ! empty( $gd_support['PNG Support'] ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Sanitize an array of exclusions.
 *
 * @param string $input A list of URL exclusions, from a textarea.
 * @return array The sanitized list of paths/patterns to exclude.
 */
function easyio_exclude_paths_sanitize( $input ) {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	if ( empty( $input ) ) {
		return '';
	}
	$path_array = array();
	if ( is_array( $input ) ) {
		$paths = $input;
	} elseif ( is_string( $input ) ) {
		$paths = explode( "\n", $input );
	}
	if ( easyio_iterable( $paths ) ) {
		$i = 0;
		foreach ( $paths as $path ) {
			$i++;
			easyio_debug_message( "validating path exclusion: $path" );
			$path = trim( sanitize_text_field( $path ), '*' );
			if ( ! empty( $path ) ) {
				$path_array[] = $path;
			}
		}
	}
	return $path_array;
}

/**
 * Retrieve option: use single-site setting or override from constant.
 *
 * Retrieves single-site options as appropriate as well as allowing overrides with
 * same-named constant.
 *
 * @param string $option_name The name of the option to retrieve.
 * @param mixed  $default The default to use if not found/set, defaults to false, but not currently used.
 * @return mixed The value of the option.
 */
function easyio_get_option( $option_name, $default = false ) {
	$constant_name = strtoupper( $option_name );
	if ( defined( $constant_name ) && ( is_int( constant( $constant_name ) ) || is_bool( constant( $constant_name ) ) ) ) {
		return constant( $constant_name );
	}
	if (
		(
			'exactdn_exclude' === $option_name ||
			'easyio_ll_exclude' === $option_name
		)
		&& defined( $constant_name )
	) {
		return easyio_exclude_paths_sanitize( constant( $constant_name ) );
	}
	return get_option( $option_name );
}

/**
 * Clear output buffers without throwing a fit.
 */
function easyio_ob_clean() {
	if ( ob_get_length() ) {
		ob_end_clean();
	}
}

/**
 * JS needed for the settings page.
 *
 * @param string $hook The hook name of the page being loaded.
 */
function easyio_settings_script( $hook ) {
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	// Make sure we are being called from the settings page.
	if ( strpos( $hook, 'settings_page_easy-image-optimizer' ) !== 0 ) {
		return;
	}
	delete_option( 'easyio_exactdn_checkin' );
	global $exactdn;
	if ( has_action( 'admin_notices', 'easyio_notice_exactdn_domain_mismatch' ) ) {
		delete_option( 'easyio_exactdn_domain' );
		delete_option( 'easyio_exactdn_local_domain' );
		delete_option( 'easyio_exactdn_plan_id' );
		delete_option( 'easyio_exactdn_failures' );
		delete_option( 'easyio_exactdn_verified' );
		remove_action( 'admin_notices', 'easyio_notice_exactdn_domain_mismatch' );
		$exactdn->setup();
	}
	wp_enqueue_script( 'easyio-settings-script', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery' ), EASYIO_VERSION );
	wp_localize_script( 'easyio-settings-script', 'easyio_vars', array( '_wpnonce' => wp_create_nonce( 'easy-image-optimizer-settings' ) ) );
	return;
}

/**
 * Displays the Easy IO network settings page.
 */
function easyio_network_options() {
	$output  = '';
	$output .= "<div class='wrap'>\n";

	$icon_link = plugins_url( '/images/easyio-toon-car.png', __FILE__ );
	$output   .= "<img style='float:right;' src='$icon_link' />";

	$output .= "<h1>Easy Image Optimizer</h1>\n";
	$output .= '<p>' . esc_html__( 'The Easy Image Optimizer must be configured and activated on each individual site.', 'easy-image-optimizer' ) . '</p>';
	$output .= '</div>';
	echo $output;
}

/**
 * Displays the Easy IO options along with status information, and debugging information.
 *
 * @global string $eio_debug In memory debug log.
 *
 * @param string $network Indicates which options should be shown in multisite installations.
 */
function easyio_options( $network = 'singlesite' ) {
	global $easyio_temp_debug;
	global $content_width;
	easyio_debug_message( '<b>' . __FUNCTION__ . '()</b>' );
	easyio_debug_version_info();
	easyio_debug_message( 'ABSPATH: ' . ABSPATH );
	easyio_debug_message( 'WP_CONTENT_DIR: ' . WP_CONTENT_DIR );
	easyio_debug_message( 'home url: ' . get_home_url() );
	easyio_debug_message( 'site url: ' . get_site_url() );
	easyio_debug_message( 'content_url: ' . content_url() );
	$upload_info = wp_upload_dir( null, false );
	easyio_debug_message( 'upload_dir: ' . $upload_info['basedir'] );
	easyio_debug_message( "content_width: $content_width" );

	global $eio_hs_beacon;
	$eio_hs_beacon->admin_notice( 'singlesite' );

	$output = array();

	$output[] = "<style>\n" .
		".easyio-tab span { font-size: 15px; font-weight: 700; color: #555; text-decoration: none; line-height: 36px; padding: 0 10px; }\n" .
		".easyio-tab span:hover { color: #464646; }\n" .
		".easyio-tab { margin: 0 0 0 5px; padding: 0px; border-width: 1px 1px 1px; border-style: solid solid none; border-image: none; border-color: #ccc; display: inline-block; background-color: #e4e4e4; cursor: pointer }\n" .
		".easyio-tab:hover { background-color: #fff }\n" .
		".easyio-selected { background-color: #f1f1f1; margin-bottom: -1px; border-bottom: 1px solid #f1f1f1 }\n" .
		".easyio-selected span { color: #000; }\n" .
		".easyio-selected:hover { background-color: #f1f1f1; }\n" .
		".easyio-tab-nav { list-style: none; margin: 10px 0 0; padding-left: 5px; border-bottom: 1px solid #ccc; }\n" .
		"#easyio-inactive { display: none; }\n" .
	"</style>\n";

	$output[]  = "<div class='wrap'>\n";
	$icon_link = plugins_url( '/images/easyio-toon-car.png', __FILE__ );
	$output[]  = "<img style='float:right;' src='$icon_link' />";
	$output[]  = "<h1>Easy Image Optimizer</h1>\n";

	$status_notices = array();
	$status_output  = '<h2>' . esc_html__( 'Status:', 'easy-image-optimizer' ) . ' ';

	global $exactdn;
	if ( class_exists( 'Jetpack_Photon' ) && Jetpack::is_module_active( 'photon' ) && easyio_get_option( 'easyio_exactdn' ) ) {
		$status_output .= '<span style="color: red">' . esc_html__( 'Inactive, please disable the Site Accelerator option in the Jetpack settings.', 'easy-image-optimizer' ) . '</span>';
	} elseif ( class_exists( 'ExactDN' ) && easyio_get_option( 'easyio_exactdn' ) ) {
		if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) {
			$exactdn_savings = $exactdn->savings();
			$status_output  .= '<span style="color: #3eadc9;">' . esc_html__( 'Verified', 'easy-image-optimizer' ) . ' </span>';
			$status_output  .= '<br><span style="font-weight:normal;line-height:1.8em;">' . esc_html( $exactdn->get_exactdn_domain() ) . '</span>';
			if ( ! empty( $exactdn_savings ) && ! empty( $exactdn_savings['original'] ) && ! empty( $exactdn_savings['savings'] ) ) {
				$exactdn_percent = round( $exactdn_savings['savings'] / $exactdn_savings['original'], 3 ) * 100;
				$status_output  .= '<br>' . esc_html__( 'Image Savings:', 'easy-image-optimizer' ) . ' <span style="font-weight:normal;">' . $exactdn_percent . '% (' . esc_html( easyio_size_format( $exactdn_savings['savings'], 1 ) ) . ')</span>';
			}
		} else {
			easyio_debug_message( 'could not verify: ' . $exactdn->get_exactdn_domain() );
			$status_output .= '<span style="color: red; font-weight: bolder"><a href="https://ewww.io/manage-sites/" target="_blank">' . esc_html__( 'Not Verified', 'easy-image-optimizer' ) . '</a></span>';
		}
		if ( function_exists( 'remove_query_strings_link' ) || function_exists( 'rmqrst_loader_src' ) || function_exists( 'qsr_remove_query_strings_1' ) ) {
			$status_notices[] = esc_html__( 'Plugins that remove query strings are unnecessary with Easy IO You may remove them at your convenience.', 'easy-image-optimizer' ) . ' ' . easyio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' );
		}
	} elseif ( ! easyio_get_option( 'easyio_exactdn' ) ) {
		$status_output .= esc_html__( 'Complete activation below to enable automatic resizing and more', 'easy-image-optimizer' );
		delete_option( 'easyio_exactdn_domain' );
		delete_option( 'easyio_exactdn_verified' );
		delete_option( 'easyio_exactdn_validation' );
	}
	$status_output .= '</h2>';

	$status_output .= '<p>';
	foreach ( $status_notices as $notice ) {
		$status_output .= '<i>' . $notice . '</i><br>';
	}
	$status_output .= '</p>';

	// End status section.
	if ( easyio_get_option( 'easyio_exactdn' ) ) {
		$output[] = $status_output;
	}

	$output[] = "<ul class='easyio-tab-nav'>\n" .
		"<li class='easyio-tab easyio-general-nav'><span class='easyio-tab-hidden'>" . esc_html__( 'Configure', 'easy-image-optimizer' ) . "</span></li>\n" .
		"<li class='easyio-tab easyio-support-nav'><span class='easyio-tab-hidden'>" . esc_html__( 'Support', 'easy-image-optimizer' ) . "</span></li>\n" .
		"</ul>\n";
	$output[] = "<form method='post' action='options.php'>\n";
	$output[] = "<input type='hidden' name='option_page' value='easyio_options' />\n";
	$output[] = "<input type='hidden' name='action' value='update' />\n";
	$output[] = wp_nonce_field( 'easyio_options-options', '_wpnonce', true, false ) . "\n";

	$output[] = "<div id='easyio-general-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Configure', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	if ( ! easyio_get_option( 'easyio_exactdn' ) ) {
		$eio_base = new EIO_Base();
		$site_url = $eio_base->content_url();
		$output[] = '<tr><td>' .
			'<ol><li><a href="https://ewww.io/easy/" target="_blank">' . esc_html__( 'Start a free trial subscription for your site.', 'easy-image-optimizer' ) . '</a></li>' .
			'<li><a href="https://ewww.io/manage-sites/" target="_blank">' . esc_html__( 'Add your Site URL to your account:', 'easy-image-optimizer' ) . "</a> $site_url</li>" .
			'<li><a id="easyio-activate" href="admin.php?action=easyio_activate" class="button-primary">' . esc_html__( 'Activate', 'easy-image-optimizer' ) . '</a></li>' .
			'<li>' . esc_html__( 'Done!', 'easy-image-optimizer' ) . '</li>' .
			"</ol></td></tr>\n";
	} else {
		$output[] = "<tr><th scope='row'>&nbsp;</th><td>" .
			'<a href="https://ewww.io/subscriptions/" class="page-title-action">' . esc_html__( 'Manage Subscription', 'easy-image-optimizer' ) . '</a>' . '&nbsp;&nbsp;' .
			'<a href="admin.php?action=easyio_deactivate" class="page-title-action">' . esc_html__( 'Disable Optimizer', 'easy-image-optimizer' ) . '</a>' .
			"<input type='hidden' id='easyio_exactdn' name='easyio_exactdn' value='true' />" .
			"</td></tr>\n";
		easyio_debug_message( 'ExactDN enabled: ' . ( easyio_get_option( 'easyio_exactdn' ) ? 'on' : 'off' ) );
		$output[] = "<tr><th scope='row'><label for='exactdn_all_the_things'>" . esc_html__( 'Include All Resources', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) . '</th>' .
			"<td><input type='checkbox' name='exactdn_all_the_things' value='true' id='exactdn_all_the_things' " .
			( easyio_get_option( 'exactdn_all_the_things' ) ? "checked='true'" : '' ) . '> ' . esc_html__( 'Replace URLs for all resources in wp-includes/ and wp-content/, including JavaScript, CSS, fonts, etc.', 'easy-image-optimizer' ) . "</td></tr>\n";
		easyio_debug_message( 'ExactDN all the things: ' . ( easyio_get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
		$output[] = "<tr><th scope='row'><label for='exactdn_lossy'>" . esc_html__( 'Premium Compression', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) . '</th>' .
			"<td><input type='checkbox' name='exactdn_lossy' value='true' id='exactdn_lossy' " .
			( easyio_get_option( 'exactdn_lossy' ) ? "checked='true'" : '' ) . '> ' . esc_html__( 'Enable high quality premium compression for all images. Disable to use lossless mode instead.', 'easy-image-optimizer' ) . "</td></tr>\n";
		easyio_debug_message( 'ExactDN lossy: ' . intval( easyio_get_option( 'exactdn_lossy' ) ) );
		easyio_debug_message( 'ExactDN resize existing: ' . ( easyio_get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
		easyio_debug_message( 'ExactDN attachment queries: ' . ( easyio_get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
		$output[]          = "<input type='hidden' id='easyio_use_lqip' name='easyio_use_lqip' value='0'>\n";
		$eio_exclude_paths = easyio_get_option( 'exactdn_exclude' ) ? esc_html( implode( "\n", easyio_get_option( 'exactdn_exclude' ) ) ) : '';
		$output[]          = "<tr><th scope='row'>" .
			"<label for='exactdn_exclude'>" . esc_html__( 'Exclusions', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/68-exactdn-exclude', '5c0042892c7d3a31944e88a4' ) . '</th><td>' .
			"<textarea id='exactdn_exclude' name='exactdn_exclude' rows='3' cols='60'>$eio_exclude_paths</textarea>\n" .
			"<p class='description'>" . esc_html__( 'One exclusion per line, no wildcards (*) needed. Any pattern or path provided will not be optimized by Easy IO.', 'easy-image-optimizer' ) .
			' ' . esc_html__( 'Exclude entire pages with page:/xyz/ syntax.', 'easy-image-optimizer' ) .
			"</p></td></tr>\n";
		easyio_debug_message( 'Easy IO exclusions:' );
		easyio_debug_message( $eio_exclude_paths );
		$output[] = "<tr><th scope='row'>" .
			"<label for='easyio_add_missing_dims'>" . esc_html__( 'Add Missing Dimensions', 'easy-image-optimizer' ) . '</label></th>' .
			"<td><input type='checkbox' id='easyio_add_missing_dims' name='easyio_add_missing_dims' value='true' " .
				checked( easyio_get_option( 'easyio_add_missing_dims' ), true, false ) . ' ' . disabled( easyio_get_option( 'easyio_lazy_load' ), false, false ) . ' /> ' .
			esc_html__( 'Add width/height attributes to reduce layout shifts and improve user experience.', 'easy-image-optimizer' ) .
			( ! easyio_get_option( 'easyio_lazy_load' ) ? "<p class ='description'>*" . esc_html__( 'Requires Lazy Load.', 'easy-image-optimizer' ) . '</p>' : '' ) .
			"</td></tr>\n";
		$output[] = "<tr><th scope='row'><p><label for='easyio_lazy_load'>" . esc_html__( 'Lazy Load', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ) .
			"</th><td><input type='checkbox' id='easyio_lazy_load' name='easyio_lazy_load' value='true' " .
			( easyio_get_option( 'easyio_lazy_load' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'Improves actual and perceived loading time by deferring off-screen images.', 'easy-image-optimizer' ) . "</p>\n" .
			"<p class='description'>" . esc_html__( 'If you have any problems, try disabling Lazy Load and contact support for further assistance.', 'easy-image-optimizer' ) . "</p>\n" .
			"</td></tr>\n";
		easyio_debug_message( 'lazy load: ' . ( easyio_get_option( 'easyio_lazy_load' ) ? 'on' : 'off' ) );
		$output[] = '<tr><td>&nbsp;</td><td>' .
			"<p><input type='checkbox' name='easyio_use_lqip' value='true' id='easyio_use_lqip' " .
			( easyio_get_option( 'easyio_use_lqip' ) ? "checked='true'" : '' ) . ' /> ' .
			"<label for='easyio_use_lqip'><strong>LQIP:</strong></label> " . esc_html__( 'Use low-quality versions of your images as placeholders. Can improve user experience, but may be slower than blank placeholders.', 'easy-image-optimizer' ) .
			easyio_help_link( 'https://docs.ewww.io/article/75-lazy-load-placeholders', '5c9a7a302c7d3a1544615e47' ) . "</p>\n" .
			"</td></tr>\n";
			easyio_debug_message( 'LQIP: ' . ( easyio_get_option( 'easyio_use_lqip' ) ? 'on' : 'off' ) );
		$ll_exclude_paths = easyio_get_option( 'easyio_ll_exclude' ) ? esc_html( implode( "\n", easyio_get_option( 'easyio_ll_exclude' ) ) ) : '';
		$output[]         = '<tr><td>&nbsp;</td>' .
			"<td><label for='easyio_ll_exclude'><strong>" . esc_html__( 'Exclusions', 'easy-image-optimizer' ) . '</strong></label>' .
			easyio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ) . '<br>' .
			"<textarea id='easyio_ll_exclude' name='easyio_ll_exclude' rows='3' cols='60'>$ll_exclude_paths</textarea>\n" .
			"<p class='description'>" .
			esc_html__( 'One exclusion per line, no wildcards (*) needed. Use any string that matches the desired element(s) or exclude entire element types like "div", "span", etc. The class "skip-lazy" and attribute "data-skip-lazy" are excluded by default.', 'easy-image-optimizer' ) .
			' ' . esc_html__( 'Exclude entire pages with page:/xyz/ syntax.', 'easy-image-optimizer' ) .
			"</p></td></tr>\n";
		easyio_debug_message( 'LL exclusions:' );
		easyio_debug_message( $ll_exclude_paths );
	}
	easyio_debug_message( 'remove metadata: ' . ( easyio_get_option( 'easyio_metadata_remove' ) ? 'on' : 'off' ) );
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='easyio-support-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Support', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<p><a class='easyio-docs-root' href='https://docs.ewww.io/category/76-easy-io'>" . esc_html__( 'Documentation', 'easy-image-optimizer' ) . '</a> | ' .
		"<a class='easyio-docs-root' href='https://ewww.io/contact-us/'>" . esc_html__( 'Plugin Support', 'easy-image-optimizer' ) . '</a> | ' .
		"<a href='https://ewww.io/status/'>" . esc_html__( 'Server Status', 'easy-image-optimizer' ) . '</a> | ' .
		"<a href='https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer/'>" . esc_html__( 'Translate Easy IO', 'easy-image-optimizer' ) . '</a> | ' .
		"<a href='https://wordpress.org/support/view/plugin-reviews/easy-image-optimizer#postform'>" . esc_html__( 'Write a review', 'easy-image-optimizer' ) . '</a>';
		"</p>\n";
	$output[] = "<p><strong><a class='easyio-docs-root' href='https://ewww.io/contact-us/'>" . esc_html__( 'If Easy IO is not working like you think it should, we want to know!', 'easy-image-optimizer' ) . '</a></strong></p>';
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr><th scope='row'><label for='easyio_enable_help'>" . esc_html__( 'Enable Embedded Help', 'easy-image-optimizer' ) .
		"</label></th><td><input type='checkbox' id='easyio_enable_help' name='easyio_enable_help' value='true' " .
		( easyio_get_option( 'easyio_enable_help' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	easyio_debug_message( 'enable help beacon: ' . ( easyio_get_option( 'easyio_enable_help' ) ? 'yes' : 'no' ) );
	$output[] = "<tr><th scope='row'><label for='easyio_debug'>" . esc_html__( 'Debugging', 'easy-image-optimizer' ) . '</label>' .
		easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . '</th>' .
		"<td><input type='checkbox' id='easyio_debug' name='easyio_debug' value='true' " .
		( ! $easyio_temp_debug && easyio_get_option( 'easyio_debug' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	$output[] = "</table>\n";

	$output[] = 'DEBUG_PLACEHOLDER';

	$output[] = "</div>\n";

	if ( easyio_get_option( 'easyio_exactdn' ) ) {
		$output[] = "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'easy-image-optimizer' ) . "' /></p>\n";
	} else {
		$output[] = "<p id='easyio-hidden-submit' style='display:none;' class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'easy-image-optimizer' ) . "' /></p>\n";
	}
	$output[] = "</form>\n";
	$output[] = "</div><!-- end of wrap -->\n";

	$page_output = '';
	foreach ( $output as $line ) {
		$page_output .= $line;
	}
	$output = $page_output;

	global $eio_debug;
	if ( ! empty( $eio_debug ) ) {
		$debug_output = '<p style="clear:both"><b>' . esc_html__( 'Debugging Information', 'easy-image-optimizer' ) . ':</b> <button id="easyio-copy-debug" class="button button-secondary" type="button">' . esc_html__( 'Copy', 'easy-image-optimizer' ) . '</button>';
		if ( is_file( EASYIO_CONTENT_DIR . 'debug.log' ) || is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
			$debug_output .= "&emsp;<a href='admin.php?action=easyio_view_debug_log'>" . esc_html( 'View Debug Log', 'easy-image-optimizer' ) . "</a> - <a href='admin.php?action=easyio_delete_debug_log'>" . esc_html( 'Remove Debug Log', 'easy-image-optimizer' ) . '</a>';
		}
		$debug_output .= '</p>';
		$debug_output .= '<div id="easyio-debug-info" style="border:1px solid #e5e5e5;background:#fff;overflow:auto;height:300px;width:800px;" contenteditable="true">' . $eio_debug . '</div>';

		$help_instructions = esc_html__( 'Debugging information will be included with your message automatically.', 'easy-image-optimizer' ) . ' ' .
			esc_html__( 'This will allow us to assist you more quickly.', 'easy-image-optimizer' );

		$output = str_replace( 'DEBUG_PLACEHOLDER', $debug_output, $output );
	} else {
		$output = str_replace( 'DEBUG_PLACEHOLDER', '', $output );
	}

	echo $output;
	if ( easyio_get_option( 'easyio_enable_help' ) ) {
		$current_user = wp_get_current_user();
		$help_email   = $current_user->user_email;
		$hs_debug     = '';
		if ( ! empty( $eio_debug ) ) {
			$hs_debug = str_replace( array( "'", '<br>', '<b>', '</b>' ), array( "\'", '\n', '<', '>' ), $eio_debug );
		}
		?>
<script type="text/javascript">!function(e,t,n){function a(){var e=t.getElementsByTagName("script")[0],n=t.createElement("script");n.type="text/javascript",n.async=!0,n.src="https://beacon-v2.helpscout.net",e.parentNode.insertBefore(n,e)}if(e.Beacon=n=function(t,n,a){e.Beacon.readyQueue.push({method:t,options:n,data:a})},n.readyQueue=[],"complete"===t.readyState)return a();e.attachEvent?e.attachEvent("onload",a):e.addEventListener("load",a,!1)}(window,document,window.Beacon||function(){});</script>
<script type="text/javascript">
	window.Beacon('init', 'aa9c3d3b-d4bc-4e9b-b6cb-f11c9f69da87');
	Beacon( 'prefill', {
		email: '<?php echo utf8_encode( $help_email ); ?>',
		text: '\n\n----------------------------------------\n<?php echo $hs_debug; ?>',
	});
</script>
		<?php
	}
	easyio_temp_debug_clear();
}

/**
 * Displays a help icon linked to the docs.
 *
 * @param string $link A link to the documentation.
 * @param string $hsid The HelpScout ID for the docs article. Optional.
 * @return string An HTML hyperlink element with a help icon.
 */
function easyio_help_link( $link, $hsid = '' ) {
	$help_icon   = plugins_url( '/images/question-circle.png', __FILE__ );
	$beacon_attr = '';
	$link_class  = 'easyio-help-icon';
	if ( strpos( $hsid, ',' ) ) {
		$beacon_attr = " data-beacon-articles='$hsid'";
		$link_class  = 'easyio-help-beacon-multi';
	} elseif ( $hsid ) {
		$beacon_attr = " data-beacon-article='$hsid'";
		$link_class  = 'easyio-help-beacon-single';
	}
	return "<a class='$link_class' href='$link' target='_blank' style='margin: 3px'$beacon_attr><img title='" . esc_attr__( 'Help', 'easy-image-optimizer' ) . "' src='$help_icon'></a>";
}



/**
 * Checks to see if the current page being output is an AMP page.
 *
 * @return bool True for an AMP endpoint, false otherwise.
 */
function easyio_is_amp() {
	if ( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() ) {
		return true;
	}
	if ( function_exists( 'ampforwp_is_amp_endpoint' ) && ampforwp_is_amp_endpoint() ) {
		return true;
	}
	return false;
}

/**
 * Adds information to the in-memory debug log.
 *
 * @global string $eio_debug The in-memory debug log.
 *
 * @param string $message Debug information to add to the log.
 */
function easyio_debug_message( $message ) {
	if ( ! is_string( $message ) && ! is_int( $message ) && ! is_float( $message ) ) {
		return;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( $message );
		return;
	}
	global $easyio_temp_debug;
	if ( $easyio_temp_debug || easyio_get_option( 'easyio_debug' ) ) {
		$memory_limit = easyio_memory_limit();
		if ( strlen( $message ) + 4000000 + memory_get_usage( true ) <= $memory_limit ) {
			global $eio_debug;
			$message    = str_replace( "\n\n\n", '<br>', $message );
			$message    = str_replace( "\n\n", '<br>', $message );
			$message    = str_replace( "\n", '<br>', $message );
			$eio_debug .= "$message<br>";
		} else {
			global $eio_debug;
			$eio_debug = "not logging message, memory limit is $memory_limit";
		}
	}
}

/**
 * Saves the in-memory debug log to a logfile in the plugin folder.
 *
 * @global string $eio_debug The in-memory debug log.
 */
function easyio_debug_log() {
	if ( function_exists( 'ewww_image_optimizer_debug_log' ) && ewww_image_optimizer_get_option( 'ewww_image_optimizer_debug' ) ) {
		return;
	}
	global $eio_debug;
	global $easyio_temp_debug;
	$debug_log = EASYIO_CONTENT_DIR . 'debug.log';
	if ( ! is_dir( dirname( $debug_log ) ) && is_writable( dirname( EASYIO_CONTENT_DIR ) ) ) {
		wp_mkdir_p( dirname( $debug_log ) );
	}
	if (
		! empty( $eio_debug ) &&
		empty( $easyio_temp_debug ) &&
		easyio_get_option( 'easyio_debug' ) &&
		is_dir( dirname( $debug_log ) ) &&
		is_writable( dirname( $debug_log ) )
	) {
		$memory_limit = easyio_memory_limit();
		clearstatcache();
		$timestamp = gmdate( 'Y-m-d H:i:s' ) . "\n";
		if ( ! file_exists( $debug_log ) ) {
			touch( $debug_log );
		} else {
			if ( filesize( $debug_log ) + 4000000 + memory_get_usage( true ) > $memory_limit ) {
				unlink( $debug_log );
				touch( $debug_log );
			}
		}
		if ( filesize( $debug_log ) + strlen( $eio_debug ) + 4000000 + memory_get_usage( true ) <= $memory_limit && is_writable( $debug_log ) ) {
			$eio_debug = str_replace( '<br>', "\n", $eio_debug );
			file_put_contents( $debug_log, $timestamp . $eio_debug, FILE_APPEND );
		}
	}
	$eio_debug = '';
}

/**
 * View the debug.log file from the wp-admin.
 */
function easyio_view_debug_log() {
	$permissions = apply_filters( 'easyio_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'easy-image-optimizer' ) );
	}
	if ( is_file( EASYIO_CONTENT_DIR . 'debug.log' ) ) {
		easyio_ob_clean();
		header( 'Content-Type: text/plain;charset=UTF-8' );
		readfile( EASYIO_CONTENT_DIR . 'debug.log' );
		exit;
	}
	if ( is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
		easyio_ob_clean();
		header( 'Content-Type: text/plain;charset=UTF-8' );
		readfile( WP_CONTENT_DIR . '/ewww/debug.log' );
		exit;
	}
	wp_die( esc_html__( 'The Debug Log is empty.', 'easy-image-optimizer' ) );
}

/**
 * Removes the debug.log file from the plugin folder.
 */
function easyio_delete_debug_log() {
	$permissions = apply_filters( 'easyio_admin_permissions', 'manage_options' );
	if ( false === current_user_can( $permissions ) ) {
		wp_die( esc_html__( 'Access denied.', 'easy-image-optimizer' ) );
	}
	if ( is_file( EASYIO_CONTENT_DIR . 'debug.log' ) ) {
		unlink( EASYIO_CONTENT_DIR . 'debug.log' );
	}
	if ( is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
		unlink( WP_CONTENT_DIR . '/ewww/debug.log' );
	}
	$sendback = wp_get_referer();
	wp_redirect( esc_url_raw( $sendback ) );
	exit;
}

/**
 * Adds version information to the in-memory debug log.
 *
 * @global string $eio_debug The in-memory debug log.
 * @global int $wp_version
 */
function easyio_debug_version_info() {
	global $eio_debug;

	$eio_debug .= 'Easy IO version: ' . EASYIO_VERSION . '<br>';

	// Check the WP version.
	global $wp_version;
	$my_version = substr( $wp_version, 0, 3 );
	$eio_debug .= "WP version: $wp_version<br>";

	if ( defined( 'PHP_VERSION_ID' ) ) {
		$eio_debug .= 'PHP version: ' . PHP_VERSION_ID . '<br>';
	}
}

/**
 * Make sure to clear temp debug option on shutdown.
 */
function easyio_temp_debug_clear() {
	global $easyio_temp_debug;
	global $eio_debug;
	if ( $easyio_temp_debug ) {
		$eio_debug = '';
	}
	$easyio_temp_debug = false;
}

/**
 * Finds the current PHP memory limit or a reasonable default.
 *
 * @return int The memory limit in bytes.
 */
function easyio_memory_limit() {
	if ( defined( 'EASYIO_MEMORY_LIMIT' ) && EASYIO_MEMORY_LIMIT ) {
		$memory_limit = EASYIO_MEMORY_LIMIT;
	} elseif ( function_exists( 'ini_get' ) ) {
		$memory_limit = ini_get( 'memory_limit' );
	} else {
		if ( ! defined( 'EASYIO_MEMORY_LIMIT' ) ) {
			// Conservative default, current usage + 16M.
			$current_memory = memory_get_usage( true );
			$memory_limit   = round( $current_memory / ( 1024 * 1024 ) ) + 16;
			define( 'EASYIO_MEMORY_LIMIT', $memory_limit );
		}
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( "memory limit is set at $memory_limit" );
	}
	if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
		// Unlimited, set to 32GB.
		$memory_limit = '32000M';
	}
	if ( stripos( $memory_limit, 'g' ) ) {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024 * 1024;
	} else {
		$memory_limit = intval( $memory_limit ) * 1024 * 1024;
	}
	return $memory_limit;
}

/**
 * Implode a multi-dimensional array without throwing errors. Arguments can be reverse order, same as implode().
 *
 * @param string $delimiter The character to put between the array items (the glue).
 * @param array  $data The array to output with the glue.
 * @return string The array values, separated by the delimiter.
 */
function easyio_implode( $delimiter, $data = '' ) {
	if ( is_array( $delimiter ) ) {
		$temp_data = $delimiter;
		$delimiter = $data;
		$data      = $temp_data;
	}
	if ( is_array( $delimiter ) ) {
		return '';
	}
	$output = '';
	foreach ( $data as $value ) {
		if ( is_string( $value ) || is_numeric( $value ) ) {
			$output .= $value . $delimiter;
		} elseif ( is_bool( $value ) ) {
			$output .= ( $value ? 'true' : 'false' ) . $delimiter;
		} elseif ( is_array( $value ) ) {
			$output .= 'Array,';
		}
	}
	return rtrim( $output, ',' );
}

/**
 * Dumps data from any filter.
 *
 * @param mixed $var Could be anything, really.
 * @param mixed $var2 Default false. Could be anything, really.
 * @param mixed $var3 Default false. Could be anything, really.
 * @return mixed Whatever they gave us.
 */
function easyio_dump_var( $var, $var2 = false, $var3 = false ) {
	if ( ! easyio_function_exists( 'print_r' ) ) {
		return $var;
	}
	easyio_debug_message( 'dumping var' );
	easyio_debug_message( print_r( $var, true ) );
	if ( $var2 ) {
		easyio_debug_message( 'dumping var2' );
		easyio_debug_message( print_r( $var2, true ) );
	}
	if ( $var3 ) {
		easyio_debug_message( 'dumping var3' );
		easyio_debug_message( print_r( $var3, true ) );
	}
	return $var;
}
