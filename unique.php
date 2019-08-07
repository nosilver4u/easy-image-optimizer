Easy IO<?php
/**
 * Functions unique to Easy I.O. ported from EWWW I.O.
 *
 * @link https://ewww.io/resize/
 * @package Easy_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EASYIO_VERSION', '100.00' );

// Initialize a couple globals.
$eio_debug = '';

/*
 * Hooks
 */

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
		require_once( easyio_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * ExactDN class for parsing image urls and rewriting them.
		 */
		require_once( easyio_PLUGIN_PATH . 'classes/class-eio-exactdn.php' );
	}
	// If Lazy Load is enabled.
	if ( easyio_get_option( 'easyio_lazy_load' ) ) {
		$buffer_start = true;
		/**
		 * Page Parsing class for working with HTML content.
		 */
		require_once( easyio_PLUGIN_PATH . 'classes/class-eio-page-parser.php' );
		/**
		 * Lazy Load class for parsing image urls and deferring off-screen images.
		 */
		require_once( easyio_PLUGIN_PATH . 'classes/class-eio-lazy-load.php' );
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

if ( ! function_exists( 'boolval' ) ) {
	/**
	 * Cast a value to boolean.
	 *
	 * @param mixed $value Any value that can be cast to boolean.
	 * @return bool The boolean version of the provided value.
	 */
	function boolval( $value ) {
		return (bool) $value;
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
	global $eio_temp_debug;
	$eio_temp_debug = false;
	if ( is_admin() && ! wp_doing_ajax() ) {
		if ( ! easyio_get_option( 'easyio_debug' ) ) {
			$eio_temp_debug = true;
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
	add_option( 'exactdn_all_the_things', true );
	add_option( 'exactdn_lossy', true );
	add_option( 'easyio_lazy_load', true );

	// Set network defaults.
	add_site_option( 'easyio_metadata_remove', true );
	add_site_option( 'exactdn_all_the_things', true );
	add_site_option( 'exactdn_lossy', true );
	add_site_option( 'easyio_lazy_load', true );
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
		easyio_set_option( 'exactdn_prevent_db_queries', false );
		delete_option( 'easyio_exactdn_verify_method' );
		delete_site_option( 'easyio_exactdn_verify_method' );
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
	easyio_upgrade();
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( easyio_PLUGIN_FILE_REL ) ) {
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
			$_POST['easyio_lazy_load'] = ( empty( $_POST['easyio_lazy_load'] ) ? false : true );
			update_site_option( 'easyio_lazy_load', $_POST['easyio_lazy_load'] );
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
	register_setting( 'easyio_options', 'easyio_lazy_load', 'boolval' );
	// Prevent ShortPixel AIO messiness.
	remove_action( 'admin_notices', 'autoptimizeMain::notice_plug_imgopt' );
	if ( class_exists( 'autoptimizeExtra' ) ) {
		$ao_extra = get_option( 'autoptimize_extra_settings' );
		if ( easyio_get_option( 'easyio_exactdn' ) && ! empty( $ao_extra['autoptimize_extra_checkbox_field_5'] ) ) {
			easyio_debug_message( 'detected ExactDN + SP conflict' );
			$ao_extra['autoptimize_extra_checkbox_field_5'] = 0;
			update_option( 'autoptimize_extra_settings', $ao_extra );
			add_action( 'admin_notices', 'easyio_notice_sp_conflict' );
		}
	}

	// TODO: convert this for sub activation use.
	if ( ! empty( $_GET['ewww_pngout'] ) ) {
		add_action( 'admin_notices', 'easyio_pngout_installed' );
		add_action( 'network_admin_notices', 'easyio_pngout_installed' );
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
	$content .= wp_kses_post( __( 'User-submitted images that are displayed on this site will be transmitted and stored on a global network of third-party servers (a CDN).' ) ) . '</p>';
	wp_add_privacy_policy_content( 'Easy Image Optimizer', $content );
}

/**
 * Check the current screen, currently used to temporarily enable debugging on settings page.
 *
 * @param object $screen Information about the page/screen currently being loaded.
 */
function easyio_current_screen( $screen ) {
	global $eio_temp_debug;
	global $eio_debug;
	if ( false === strpos( $screen->id, 'settings_page_easy-image-optimizer' ) ) {
		$eio_temp_debug = false;
		$eio_debug      = '';
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Checks to see if this is an AJAX request.
	 *
	 * For backwards compatiblity with WordPress < 4.7.0.
	 *
	 * @since 3.3.0
	 *
	 * @return bool True if this is an AJAX request.
	 */
	function wp_doing_ajax() {
		return apply_filters( 'wp_doing_ajax', defined( 'DOING_AJAX' ) && DOING_AJAX );
	}
}

// TODO: use this as a template for Activation routine.
/**
 * Display a success or failure message after PNGOUT installation.
 */
function easyio_pngout_installed() {
	if ( 'success' === $_REQUEST['ewww_pngout'] ) {
		echo "<div id='easy-image-optimizer-pngout-success' class='updated fade'>\n" .
			'<p>' . esc_html__( 'Pngout was successfully installed.', 'easy-image-optimizer' ) . "</p>\n" .
			"</div>\n";
	}
	if ( 'failed' === $_REQUEST['ewww_pngout'] ) {
		echo "<div id='easy-image-optimizer-pngout-failure' class='error'>\n" .
			'<p>' . sprintf(
				/* translators: 1: An error message 2: The folder where pngout should be installed */
				esc_html__( 'Pngout was not installed: %1$s. Make sure this folder is writable: %2$s', 'easy-image-optimizer' ),
				sanitize_text_field( $_REQUEST['ewww_error'] ),
				easyio_TOOL_PATH
			) . "</p>\n" .
			"</div>\n";
	}
}

/**
 * Display a notice that PHP version 5.5 support is going away.
 */
function easyio_php55_warning() {
	echo '<div id="easy-image-optimizer-notice-php55" class="notice notice-info"><p><a href="https://docs.ewww.io/article/55-upgrading-php" target="_blank" data-beacon-article="5ab2baa6042863478ea7c2ae">' . esc_html__( 'The next major release of Easy Image Optimizer will require PHP 7.0 or greater. Newer versions of PHP, like 7.1 and 7.2, are significantly faster and much more secure. If you are unsure how to upgrade to a supported version, ask your webhost for instructions.', 'easy-image-optimizer' ) . '</a></p></div>';
}

/**
 * Inform the user that we disabled SP AIO to prevent conflicts with ExactDN.
 */
function easyio_notice_sp_conflict() {
	echo "<div id='easy-image-optimizer-sp-conflict' class='notice notice-warning'><p>" . esc_html__( 'ShortPixel/Autoptimize image optimization has been disabled to prevent conflicts with Easy Image Optimizer).', 'easy-image-optimizer' ) . '</p></div>';
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
	return;
	global $wpdb;
	if ( $network_wide ) {
		$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = %d", $wpdb->siteid ), ARRAY_A );
		if ( easyio_iterable( $blogs ) ) {
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				// TODO: clean out/reset ExactDN options.
				restore_current_blog();
			}
		}
	}
}

/**
 * Adds a global settings page to the network admin settings menu.
 */
function easyio_network_admin_menu() {
	// TODO: implement a separate network options page (if it even makes sense).
	return;
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( easyio_PLUGIN_FILE_REL ) ) {
		$permissions = apply_filters( 'easyio_superadmin_permissions', '' );
		// Add options page to the settings menu.
		$ewww_network_options_page = add_submenu_page(
			'settings.php',                        // Slug of parent.
			'Easy Image Optimizer',                // Page Title.
			'Easy Image Optimizer',                // Menu title.
			$permissions,                          // Capability.
			easyio_PLUGIN_FILE,      // Slug.
			'easyio_network_options' // Function to call.
		);
	}
}

/**
 * Adds various items to the admin menu.
 */
function easyio_admin_menu() {
	// Add options page to the settings menu.
	$ewww_options_page = add_options_page(
		'Easy Image Optimizer',                                        // Page title.
		'Easy Image Optimizer',                                        // Menu title.
		apply_filters( 'easyio_admin_permissions', 'manage_options' ), // Capability.
		EASYIO_PLUGIN_FILE,                                            // Slug.
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
	if ( is_multisite() && is_plugin_active_for_network( easyio_PLUGIN_FILE_REL ) ) {
		$settings_link = '<a href="network/settings.php?page=' . plugin_basename( easyio_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', 'easy-image-optimizer' ) . '</a>';
	} else {
		$settings_link = '<a href="options-general.php?page=' . plugin_basename( easyio_PLUGIN_FILE ) . '">' . esc_html__( 'Settings', 'easy-image-optimizer' ) . '</a>';
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
 * Retrieve option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * Retrieves multi-site and single-site options as appropriate as well as allowing overrides with
 * same-named constant. Overrides are only available for integer and boolean options.
 *
 * @param string $option_name The name of the option to retrieve.
 * @return mixed The value of the option.
 */
function easyio_get_option( $option_name ) {
	$constant_name = strtoupper( $option_name );
	if ( defined( $constant_name ) && ( is_int( constant( $constant_name ) ) || is_bool( constant( $constant_name ) ) ) ) {
		return constant( $constant_name );
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) && ! get_site_option( 'easyio_allow_multisite_override' ) ) {
		$option_value = get_site_option( $option_name );
	} else {
		$option_value = get_option( $option_name );
	}
	return $option_value;
}

/**
 * Set an option: use 'site' setting if plugin is network activated, otherwise use 'blog' setting.
 *
 * @param string $option_name The name of the option to save.
 * @param mixed  $option_value The value to save for the option.
 * @return bool True if the operation was successful.
 */
function easyio_set_option( $option_name, $option_value ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) && is_multisite() ) {
		// Need to include the plugin library for the is_plugin_active function.
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	if ( is_multisite() && is_plugin_active_for_network( EASYIO_PLUGIN_FILE_REL ) && ! get_site_option( 'easyio_allow_multisite_override' ) ) {
		$success = update_site_option( $option_name, $option_value );
	} else {
		$success = update_option( $option_name, $option_value );
	}
	return $success;
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
	wp_enqueue_script( 'easyio-settings-script', plugins_url( '/includes/eio.js', __FILE__ ), array( 'jquery' ), EASYIO_VERSION );
	// TODO: do we need this for single site?
	wp_localize_script( 'easyio-settings-script', 'ewww_vars', array( '_wpnonce' => wp_create_nonce( 'easy-image-optimizer-settings' ) ) );
	return;
}

/**
 * Displays the Easy IO options along with status information, and debugging information.
 *
 * @global string $eio_debug In memory debug log.
 *
 * @param string $network Indicates which options should be shown in multisite installations.
 */
function easyio_options( $network = 'singlesite' ) {
	global $eio_temp_debug;
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

	global $easyio_hs_beacon;
	$easyio_hs_beacon->admin_notice( $network_class );

	$output   = array();
	$output[] = "<script type='text/javascript'>\n" .
		'jQuery(document).ready(function($) {$(".fade").fadeTo(5000,1).fadeOut(3000);});' . "\n" .
		"</script>\n";
	$output[] = "<style>\n" .
		".ewww-tab span { font-size: 15px; font-weight: 700; color: #555; text-decoration: none; line-height: 36px; padding: 0 10px; }\n" .
		".ewww-tab span:hover { color: #464646; }\n" .
		".ewww-tab { margin: 0 0 0 5px; padding: 0px; border-width: 1px 1px 1px; border-style: solid solid none; border-image: none; border-color: #ccc; display: inline-block; background-color: #e4e4e4; cursor: pointer }\n" .
		".ewww-tab:hover { background-color: #fff }\n" .
		".ewww-selected { background-color: #f1f1f1; margin-bottom: -1px; border-bottom: 1px solid #f1f1f1 }\n" .
		".ewww-selected span { color: #000; }\n" .
		".ewww-selected:hover { background-color: #f1f1f1; }\n" .
		".ewww-tab-nav { list-style: none; margin: 10px 0 0; padding-left: 5px; border-bottom: 1px solid #ccc; }\n" .
	"</style>\n";
	$output[] = "<div class='wrap'>\n";
	$output[] = "<h1>Easy Image Optimizer</h1>\n";
	$output[] = '<p>' .
		sprintf(
			/* translators: %s: Bulk Optimize (link) */
			esc_html__( 'New images uploaded to the Media Library will be optimized automatically. If you have existing images you would like to optimize, you can use the %s tool.', 'easy-image-optimizer' ),
			$bulk_link
		) . easyio_help_link( 'https://docs.ewww.io/article/4-getting-started', '5853713bc697912ffd6c0b98' ) . ' ' .
		sprintf(
			/* translators: %s: S3 Image Optimizer (link) */
			esc_html__( 'Images stored in an Amazon S3 bucket can be optimized using our %s.' ),
			$s3_link
		) .
		"</p>\n";

	$compress_score = 0;
	$resize_score   = 0;
	$status_notices = '';

	$compress_recommendations = array();
	$resize_recommendations   = array();

	$status_output = "<div id='ewww-widgets' class='metabox-holder' style='max-width:1170px;'><div class='meta-box-sortables'><div id='ewww-status' class='postbox'>\n" .
		"<h2 class='ewww-hndle'>" . esc_html__( 'Optimization Status', 'easy-image-optimizer' ) . "</h2>\n<div class='inside'>";

	if ( easyio_get_option( 'easyio_cloud_key' ) ) {
		$status_notices .= '<p><b>' . esc_html__( 'Cloud optimization API Key', 'easy-image-optimizer' ) . ':</b> ';
		easyio_set_option( 'easyio_cloud_exceeded', 0 );
		$verify_cloud = easyio_cloud_verify( false );
		if ( false !== strpos( $verify_cloud, 'great' ) ) {
			$compress_score += 30;
			if ( easyio_get_option( 'easyio_jpg_level' ) > 20 ) {
				$compress_score += 50;
			} else {
				$compress_recommendations[] = esc_html__( 'Enable premium compression for JPG images.', 'easy-image-optimizer' );
			}
			if ( easyio_get_option( 'easyio_png_level' ) > 20 ) {
				$compress_score += 20;
			} else {
				$compress_recommendations[] = esc_html__( 'Enable premium compression for PNG images.', 'easy-image-optimizer' );
			}
			$status_notices .= '<span style="color: #3eadc9; font-weight: bolder">' . esc_html__( 'Verified,', 'easy-image-optimizer' ) . ' </span>' . easyio_cloud_quota();
		} elseif ( false !== strpos( $verify_cloud, 'exceeded' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Out of credits', 'easy-image-optimizer' ) . '</span> - <a href="https://ewww.io/plans/" target="_blank">' . esc_html__( 'Purchase more', 'easy-image-optimizer' ) . '</a>';
		} else {
			$status_notices .= '<span style="color: red; font-weight: bolder">' . esc_html__( 'Not Verified', 'easy-image-optimizer' ) . '</span>';
		}
		if ( false !== strpos( $verify_cloud, 'great' ) ) {
			$status_notices .= ' <a target="_blank" href="https://history.exactlywww.com/show/?api_key=' . easyio_get_option( 'easyio_cloud_key' ) . '">' . esc_html__( 'View Usage', 'easy-image-optimizer' ) . '</a>';
		}
		$status_notices .= "</p>\n";
		$disable_level   = '';
	} else {
		delete_option( 'easyio_cloud_key_invalid' );
		if ( ! class_exists( 'ExactDN' ) && ! easyio_get_option( 'easyio_exactdn' ) ) {
			$compress_recommendations[] = esc_html__( 'Enable premium compression with an API key or ExactDN.', 'easy-image-optimizer' );
		}
		$disable_level = "disabled='disabled'";
	}
	if ( class_exists( 'Jetpack_Photon' ) && Jetpack::is_module_active( 'photon' ) && easyio_get_option( 'easyio_exactdn' ) ) {
		$status_notices .= '<p><b>ExactDN:</b> <span style="color: red">' . esc_html__( 'Inactive, please disable the Image Performance option on the Jetpack Dashboard.', 'easy-image-optimizer' ) . '</span></p>';
	} elseif ( class_exists( 'ExactDN' ) && easyio_get_option( 'easyio_exactdn' ) ) {
		$status_notices .= '<p><b>ExactDN:</b> ';
		global $exactdn;
		if ( $exactdn->get_exactdn_domain() && $exactdn->verify_domain( $exactdn->get_exactdn_domain() ) ) {
			$status_notices .= '<span style="color: #3eadc9; font-weight: bolder">' . esc_html__( 'Verified', 'easy-image-optimizer' ) . ' </span>';
			if ( defined( 'WP_ROCKET_VERSION' ) ) {
				$status_notices .= '<br><i>' . esc_html__( 'If you use the File Optimization options within WP Rocket, you should also enter your ExactDN CNAME in the WP Rocket CDN settings (reserved for CSS and Javascript):', 'easy-image-optimizer' ) . ' ' . $exactdn->get_exactdn_domain() . '</i>';
			}
			if ( $compress_score < 50 ) {
				$compress_score = 50;
			}
			$resize_score += 50;
			if ( easyio_get_option( 'exactdn_lossy' ) ) {
				$compress_score = 100;
			} elseif ( easyio_get_option( 'easyio_jpg_level' ) < 30 ) {
				$compress_recommendations[] = esc_html__( 'Enable premium compression.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' );
			}
		} elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'verified' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Temporarily disabled.', 'easy-image-optimizer' ) . ' </span>';
		} elseif ( $exactdn->get_exactdn_domain() && $exactdn->get_exactdn_option( 'suspended' ) ) {
			$status_notices .= '<span style="color: orange; font-weight: bolder">' . esc_html__( 'Active, not yet verified.', 'easy-image-optimizer' ) . ' </span>';
		} else {
			easyio_debug_message( 'could not verify: ' . $exactdn->get_exactdn_domain() );
			$status_notices .= '<span style="color: red; font-weight: bolder"><a href="https://ewww.io/manage-sites/" target="_blank">' . esc_html__( 'Not Verified', 'easy-image-optimizer' ) . '</a></span>';
		}
		if ( function_exists( 'remove_query_strings_link' ) || function_exists( 'rmqrst_loader_src' ) || function_exists( 'qsr_remove_query_strings_1' ) ) {
			$status_notices .= '<br><i>' . esc_html__( 'Plugins that remove query strings are unnecessary with ExactDN. You may remove them at your convenience.', 'easy-image-optimizer' ) . '</i>' . easyio_help_link( 'https://docs.ewww.io/article/50-exactdn-and-query-strings', '5a3d278a2c7d3a1943677b52' );
		}
		$status_notices .= '</p>';
	} elseif ( ! easyio_get_option( 'easyio_exactdn' ) ) {
		$status_notices          .= '<p><b>ExactDN:</b> ' . esc_html__( 'Inactive, enable to activate automatic resizing and more', 'easy-image-optimizer' ) . '</p>';
		$resize_recommendations[] = esc_html__( 'Enable ExactDN for automatic resizing.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,59de6631042863379ddc953c,59c44349042863033a1d06d3,5ada43a12c7d3a0e93678b8c,5a3d278a2c7d3a1943677b52,5a9868eb04286374f7087795,59de68482c7d3a40f0ed6035,592dd69b2c7d3a074e8aed5b' );
		delete_option( 'easyio_exactdn_domain' );
		delete_option( 'easyio_exactdn_failures' );
		delete_option( 'easyio_exactdn_checkin' );
		delete_option( 'easyio_exactdn_verified' );
		delete_option( 'easyio_exactdn_validation' );
		delete_option( 'easyio_exactdn_suspended' );
		delete_site_option( 'easyio_exactdn_domain' );
		delete_site_option( 'easyio_exactdn_failures' );
		delete_site_option( 'easyio_exactdn_checkin' );
		delete_site_option( 'easyio_exactdn_verified' );
		delete_site_option( 'easyio_exactdn_validation' );
		delete_site_option( 'easyio_exactdn_suspended' );
	}
	if (
		easyio_get_option( 'easyio_maxmediawidth' ) ||
		easyio_get_option( 'easyio_maxmediaheight' ) ||
		easyio_get_option( 'easyio_maxotherwidth' ) ||
		easyio_get_option( 'easyio_maxotherheight' )
	) {
		$resize_score += 30;
	} elseif ( defined( 'IMSANITY_VERSION' ) ) {
		$resize_score += 30;
	} else {
		$resize_recommendations[] = esc_html__( 'Configure maximum image dimensions in Resize settings.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' );
	}
	$jpg_quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
	if ( $jpg_quality < 90 && $jpg_quality > 50 ) {
		$resize_score += 20;
	} else {
		$resize_recommendations[] = esc_html__( 'JPG quality level should be between 50 and 90 for optimal resizing.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' );
	}
	$skip = easyio_skip_tools();
	if ( ! $skip['jpegtran'] && ! easyio_NOEXEC ) {
		if ( easyio_JPEGTRAN ) {
			$jpegtran_installed = easyio_tool_found( easyio_JPEGTRAN, 'j' );
			if ( ! $jpegtran_installed ) {
				$jpegtran_installed = easyio_tool_found( easyio_JPEGTRAN, 'jb' );
			}
		}
		if ( ! empty( $jpegtran_installed ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install jpegtran.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['optipng'] && ! easyio_NOEXEC ) {
		if ( easyio_OPTIPNG ) {
			$optipng_version = easyio_tool_found( easyio_OPTIPNG, 'o' );
			if ( ! $optipng_version ) {
				$optipng_version = easyio_tool_found( easyio_OPTIPNG, 'ob' );
			}
		}
		if ( ! empty( $optipng_version ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install optipng.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['pngout'] && ! easyio_NOEXEC ) {
		if ( easyio_PNGOUT ) {
			$pngout_version = easyio_tool_found( easyio_PNGOUT, 'p' );
			if ( ! $pngout_version ) {
				$pngout_version = easyio_tool_found( easyio_PNGOUT, 'pb' );
			}
		}
		if ( ! empty( $pngout_version ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install pngout', 'easy-image-optimizer' ) . ': <a href="admin.php?action=easyio_install_pngout">' . esc_html__( 'automatically', 'easy-image-optimizer' ) . '</a> | <a href="https://docs.ewww.io/article/13-installing-pngout" data-beacon-article="5854531bc697912ffd6c1afa">' . esc_html__( 'manually', 'easy-image-optimizer' ) . '</a>';
		}
	}
	if ( ! $skip['pngquant'] && ! easyio_NOEXEC ) {
		if ( easyio_PNGQUANT ) {
			$pngquant_version = easyio_tool_found( easyio_PNGQUANT, 'q' );
			if ( ! $pngquant_version ) {
				$pngquant_version = easyio_tool_found( easyio_PNGQUANT, 'qb' );
			}
		}
		if ( ! empty( $pngquant_version ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install pngquant.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( ! $skip['gifsicle'] && ! easyio_NOEXEC ) {
		if ( easyio_GIFSICLE ) {
			$gifsicle_version = easyio_tool_found( easyio_GIFSICLE, 'g' );
			if ( ! $gifsicle_version ) {
				$gifsicle_version = easyio_tool_found( easyio_GIFSICLE, 'gb' );
			}
		}
		if ( ! empty( $gifsicle_version ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install gifsicle.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	if ( easyio_CWEBP && ! $skip['webp'] && ! easyio_NOEXEC ) {
		if ( easyio_CWEBP ) {
			$webp_version = easyio_tool_found( easyio_CWEBP, 'w' );
			if ( ! $webp_version ) {
				$webp_version = easyio_tool_found( easyio_CWEBP, 'wb' );
			}
		}
		if ( ! empty( $webp_version ) ) {
			$compress_score += 5;
		} else {
			$compress_recommendations[] = esc_html__( 'Install webp.', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/6-the-plugin-says-i-m-missing-something', '585371e3c697912ffd6c0ba1' );
		}
	}
	// Check that an image library exists for converting resizes. Originals can be done via the API, but resizes are done locally for speed.
	$toolkit_found = false;
	if ( easyio_gd_support() ) {
		$toolkit_found = true;
	}
	if ( easyio_gmagick_support() ) {
		$toolkit_found = true;
	}
	if ( easyio_imagick_support() ) {
		$toolkit_found = true;
	}
	if ( PHP_OS !== 'WINNT' && ! easyio_full_cloud() && ! easyio_NOEXEC ) {
		easyio_find_nix_binary( 'nice', 'n' );
	}

	// Begin building of status inside section.
	$status_output .= '<div class="ewww-row"><ul class="ewww-blocks">';
	$compress_score = min( $compress_score, 100 );
	$resize_score   = min( $resize_score, 100 );

	$guage_stroke_dasharray     = 2 * pi() * 54;
	$compress_stroke_dashoffset = $guage_stroke_dasharray * ( 1 - $compress_score / 100 );
	$resize_stroke_dashoffset   = $guage_stroke_dasharray * ( 1 - $resize_score / 100 );

	$status_output .= '<li><div id="ewww-compress" class="ewww-status-detail">';
	$compress_guage = '<div id="ewww-compress-guage" class="ewww-guage" data-score="' . $compress_score . '">' .
		'<svg width="120" height="120">' .
		'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
		'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $compress_stroke_dashoffset . 'px;"/>' .
		'</svg>' .
		'<div class="ewww-score">' . $compress_score . '%</div>' .
		'</div><!-- end .ewww-guage -->';
	$status_output .= $compress_guage;
	$status_output .= '<div id="ewww-compress-recommend" class="ewww-recommend"><strong>' . ( $compress_score < 100 ? esc_html__( 'How do I get to 100%?', 'easy-image-optimizer' ) : esc_html__( 'You got the perfect score!', 'easy-image-optimizer' ) ) . '</strong>';
	if ( $compress_score < 100 ) {
		$status_output .= '<ul class="ewww-tooltip">';
		foreach ( $compress_recommendations as $c_recommend ) {
			$status_output .= "<li>$c_recommend</li>";
		}
		$status_output .= '</ul>';
	}
	$status_output .= '</div><!-- end .ewww-recommend -->';
	$status_output .= '<p><strong>' . esc_html__( 'Compress', 'easy-image-optimizer' ) . '</strong></p>';
	$status_output .= '<p>' . esc_html__( 'Reduce the file size of your images without affecting quality.', 'easy-image-optimizer' ) . '</p>';
	$status_output .= '</div><!-- end .ewww-status-detail --></li>';

	$status_output .= '<li><div id="ewww-resize" class="ewww-status-detail">';
	$resize_guage   = '<div id="ewww-resize-guage" class="ewww-guage" data-score="' . $resize_score . '">' .
		'<svg width="120" height="120">' .
		'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
		'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $resize_stroke_dashoffset . 'px;"/>' .
		'</svg>' .
		'<div class="ewww-score">' . $resize_score . '%</div>' .
		'</div><!-- end .ewww-guage -->';
	$status_output .= $resize_guage;
	$status_output .= '<div id="ewww-resize-recommend" class="ewww-recommend"><strong>' . ( $resize_score < 100 ? esc_html__( 'How do I get to 100%?', 'easy-image-optimizer' ) : esc_html__( 'You got the perfect score!', 'easy-image-optimizer' ) ) . '</strong>';
	if ( $resize_score < 100 ) {
		$status_output .= '<ul class="ewww-tooltip">';
		foreach ( $resize_recommendations as $r_recommend ) {
			$status_output .= "<li>$r_recommend</li>";
		}
		$status_output .= '</ul>';
	}
	$status_output .= '</div><!-- end .ewww-recommend -->';
	$status_output .= '<p><strong>' . esc_html__( 'Resize', 'easy-image-optimizer' ) . '</strong></p>';
	$status_output .= '<p>' . esc_html__( 'Scale or reduce the dimensions of your images for more savings.', 'easy-image-optimizer' ) . '</p>';
	$status_output .= '</div><!-- end .ewww-status-detail --></li>';

	$total_sizes   = easyio_savings();
	$total_savings = $total_sizes[1] - $total_sizes[0];
	if ( $total_savings > 0 ) {
		$savings_stroke_dashoffset = $guage_stroke_dasharray * ( 1 - $total_savings / $total_sizes[1] );

		$status_output .= '<li><div id="ewww-compress" class="ewww-status-detail">';
		$savings_guage  = '<div id="ewww-savings-guage" class="ewww-guage" data-score="' . $total_savings / $total_sizes[1] . '">' .
			'<svg width="120" height="120">' .
			'<title>' . round( $total_savings / $total_sizes[1], 3 ) * 100 . '%</title>' .
			'<circle class="ewww-inactive" r="54" cy="60" cx="60" stroke-width="12"/>' .
			'<circle class="ewww-active" r="54" cy="60" cx="60" stroke-width="12" style="stroke-dasharray: ' . $guage_stroke_dasharray . 'px; stroke-dashoffset: ' . $savings_stroke_dashoffset . 'px;"/>' .
			'</svg>' .
			'<div class="ewww-score">' . easyio_size_format( $total_savings, 2 ) . '</div>' .
			'</div><!-- end .ewww-guage -->';
		$status_output .= $savings_guage;
		$status_output .= '<p style="text-align:center"><strong>' . esc_html__( 'Savings', 'easy-image-optimizer' ) . '</strong></p>';
		$status_output .= '</div><!-- end .ewww-status-detail --></li>';
	}
	easyio_debug_message( easyio_aux_images_table_count() . ' images have been optimized' );

	$status_output .= '<li><div class="ewww-status-detail"><div id="ewww-notices">' . $status_notices . '</div></div></li>';

	$status_output .= '</ul><!-- end .ewww-blocks --></div><!-- end .ewww-row -->';
	$status_output .= '</div><!-- end .inside -->';
	$status_output .= "</div></div></div>\n";

	// End status section.
	$output[] = $status_output;

	if ( ( 'network-multisite' !== $network || ! get_site_option( 'easyio_allow_multisite_override' ) ) && // Display tabs so long as this isn't the network admin OR single-site override is disabled.
		! ( 'network-singlesite' === $network && ! get_site_option( 'easyio_allow_multisite_override' ) ) ) { // Also make sure that this isn't single site without override mode.
		$output[] = "<ul class='ewww-tab-nav'>\n" .
			"<li class='ewww-tab ewww-general-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Basic', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-exactdn-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'ExactDN', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-optimization-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Advanced', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-resize-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Resize', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-conversion-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Convert', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-webp-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'WebP', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-overrides-nav'><span class='ewww-tab-hidden'><a href='https://docs.ewww.io/article/40-override-options' target='_blank'><span class='ewww-tab-hidden'>" . esc_html__( 'Overrides', 'easy-image-optimizer' ) . "</a></span></li>\n" .
			"<li class='ewww-tab ewww-support-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Support', 'easy-image-optimizer' ) . "</span></li>\n" .
			"<li class='ewww-tab ewww-contribute-nav'><span class='ewww-tab-hidden'>" . esc_html__( 'Contribute', 'easy-image-optimizer' ) . "</span></li>\n" .
		"</ul>\n";
	}
	if ( 'network-multisite' === $network ) {
		$output[] = "<form method='post' action=''>\n";
	} else {
		$output[] = "<form method='post' action='options.php'>\n";
	}
	$output[] = "<input type='hidden' name='option_page' value='easyio_options' />\n";
	$output[] = "<input type='hidden' name='action' value='update' />\n";
	$output[] = wp_nonce_field( 'easyio_options-options', '_wpnonce', true, false ) . "\n";
	if ( is_multisite() && is_plugin_active_for_network( easyio_PLUGIN_FILE_REL ) && ! get_site_option( 'easyio_allow_multisite_override' ) ) {
		$output[] = '<i class="network-singlesite"><strong>' . esc_html__( 'Configure network-wide settings in the Network Admin.', 'easy-image-optimizer' ) . "</strong></i>\n";
	}
	if ( easyio_get_option( 'easyio_noauto' ) ) {
		easyio_debug_message( 'automatic compression disabled' );
	} else {
		easyio_debug_message( 'automatic compression enabled' );
	}
	$output[] = "<div id='ewww-general-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Basic', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	if ( is_multisite() ) {
		if ( is_plugin_active_for_network( easyio_PLUGIN_FILE_REL ) ) {
			$output[] = "<tr class='network-only'><th scope='row'><label for='easyio_allow_multisite_override'>" . esc_html__( 'Allow Single-site Override', 'easy-image-optimizer' ) . "</label></th><td><input type='checkbox' id='easyio_allow_multisite_override' name='easyio_allow_multisite_override' value='true' " . ( get_site_option( 'easyio_allow_multisite_override' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'Allow individual sites to configure their own settings and override all network options.', 'easy-image-optimizer' ) . "</td></tr>\n";
		}
		if ( 'network-multisite' === $network && get_site_option( 'easyio_allow_multisite_override' ) ) {
			$output[] = "<input type='hidden' id='easyio_allow_multisite_override_active' name='easyio_allow_multisite_override_active' value='0'>";
			if ( get_site_option( 'easyio_cloud_key' ) ) {
				$output[] = "<input type='hidden' id='easyio_cloud_key' name='easyio_cloud_key' value='" . get_site_option( 'easyio_cloud_key' ) . "' />\n";
			}
			foreach ( $output as $line ) {
				echo $line;
			}
			echo '</table></div><!-- end container general settings -->';
			echo "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'easy-image-optimizer' ) . "' /></p>\n";
			echo '</form></div><!-- end container left --></div><!-- end container wrap -->';
			easyio_temp_debug_clear();
			return;
		}
	}
	if ( easyio_get_option( 'easyio_cloud_key' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_cloud_notkey'>" . esc_html__( 'Optimization API Key', 'easy-image-optimizer' ) . "</label></th><td><input type='text' id='easyio_cloud_notkey' name='easyio_cloud_notkey' readonly='readonly' value='****************************" . substr( easyio_get_option( 'easyio_cloud_key' ), 28 ) . "' size='32' /> <a href='admin.php?action=easyio_remove_cloud_key'>" . esc_html__( 'Remove API key', 'easy-image-optimizer' ) . "</a></td></tr>\n";
		$output[] = "<input type='hidden' id='easyio_cloud_key' name='easyio_cloud_key' value='" . easyio_get_option( 'easyio_cloud_key' ) . "' />\n";
	} else {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_cloud_key'>" . esc_html__( 'Optimization API Key', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5ad0c8e7042863075092650b,5a9efec62c7d3a7549516550' ) . "</th><td><input type='text' id='easyio_cloud_key' name='easyio_cloud_key' value='' size='32' /> " . esc_html__( 'API Key will be validated when you save your settings.', 'easy-image-optimizer' ) . " <a href='https://ewww.io/plans/' target='_blank'>" . esc_html__( 'Purchase an API key.', 'easy-image-optimizer' ) . "</a></td></tr>\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_metadata_remove'>" . esc_html__( 'Remove Metadata', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><input type='checkbox' id='easyio_metadata_remove' name='easyio_metadata_remove' value='true' " . ( easyio_get_option( 'easyio_metadata_remove' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'This will remove ALL metadata: EXIF, comments, color profiles, and anything else that is not pixel data.', 'easy-image-optimizer' ) .
		"<p class ='description'>" . esc_html__( 'Color profiles are preserved when using the API or ExactDN.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
	easyio_debug_message( 'remove metadata: ' . ( easyio_get_option( 'easyio_metadata_remove' ) ? 'on' : 'off' ) );

	$maybe_api_level = easyio_get_option( 'easyio_cloud_key' ) ? '*' : '';

	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_jpg_level'>" . esc_html__( 'JPG Optimization Level', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><span><select id='easyio_jpg_level' name='easyio_jpg_level'>\n" .
		"<option value='0'" . selected( easyio_get_option( 'easyio_jpg_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'easy-image-optimizer' ) . "</option>\n";
	if ( defined( 'easyio_TOOL_PATH' ) ) {
		$output[] = "<option class='$network_class' value='10'" . selected( easyio_get_option( 'easyio_jpg_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'easy-image-optimizer' ) . "</option>\n";
	}
	$output[] = "<option class='$network_class' $disable_level value='20'" . selected( easyio_get_option( 'easyio_jpg_level' ), 20, false ) . '>' . esc_html__( 'Pixel Perfect Plus', 'easy-image-optimizer' ) . " *</option>\n" .
		"<option $disable_level value='30'" . selected( easyio_get_option( 'easyio_jpg_level' ), 30, false ) . '>' . esc_html__( 'Premium', 'easy-image-optimizer' ) . " *</option>\n" .
		"<option $disable_level value='40'" . selected( easyio_get_option( 'easyio_jpg_level' ), 40, false ) . '>' . esc_html__( 'Premium Plus', 'easy-image-optimizer' ) . " *</option>\n" .
		"</select></td></tr>\n";
	easyio_debug_message( 'jpg level: ' . easyio_get_option( 'easyio_jpg_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_png_level'>" . esc_html__( 'PNG Optimization Level', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2,5854531bc697912ffd6c1afa' ) . "</th>\n" .
		"<td><span><select id='easyio_png_level' name='easyio_png_level'>\n" .
		"<option value='0'" . selected( easyio_get_option( 'easyio_png_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'easy-image-optimizer' ) . "</option>\n";
	if ( defined( 'easyio_TOOL_PATH' ) ) {
		$output[] = "<option class='$network_class' value='10'" . selected( easyio_get_option( 'easyio_png_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'easy-image-optimizer' ) . "</option>\n";
	}
	$output[] = "<option class='$network_class' $disable_level value='20' " . selected( easyio_get_option( 'easyio_png_level' ), 20, false ) .
		selected( easyio_get_option( 'easyio_png_level' ), 30, false ) . '>' . esc_html__( 'Pixel Perfect Plus', 'easy-image-optimizer' ) . " *</option>\n" .
		"<option value='40'" . selected( easyio_get_option( 'easyio_png_level' ), 40, false ) . '>' . esc_html__( 'Premium', 'easy-image-optimizer' ) . " $maybe_api_level</option>\n" .
		"<option $disable_level value='50'" . selected( easyio_get_option( 'easyio_png_level' ), 50, false ) . '>' . esc_html__( 'Premium Plus', 'easy-image-optimizer' ) . " *</option>\n" .
		"</select></td></tr>\n";
	easyio_debug_message( 'png level: ' . easyio_get_option( 'easyio_png_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_gif_level'>" . esc_html__( 'GIF Optimization Level', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . "</th>\n" .
		"<td><span><select id='easyio_gif_level' name='easyio_gif_level'>\n" .
		"<option value='0'" . selected( easyio_get_option( 'easyio_gif_level' ), 0, false ) . '>' . esc_html__( 'No Compression', 'easy-image-optimizer' ) . "</option>\n" .
		"<option value='10'" . selected( easyio_get_option( 'easyio_gif_level' ), 10, false ) . '>' . esc_html__( 'Pixel Perfect', 'easy-image-optimizer' ) . " $maybe_api_level</option>\n" .
		"</select></td></tr>\n";
	easyio_debug_message( 'gif level: ' . easyio_get_option( 'easyio_gif_level' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_backup_files'>" . esc_html__( 'Backup Originals', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . '</th>' .
		"<td><input type='checkbox' id='easyio_backup_files' name='easyio_backup_files' value='true' " .
		( easyio_get_option( 'easyio_backup_files' ) ? "checked='true'" : '' ) . " $disable_level > " . esc_html__( 'Store a copy of your original images on our secure server for 30 days. *Requires an active API key.', 'easy-image-optimizer' ) . "</td></tr>\n";
	easyio_debug_message( 'backup mode: ' . ( easyio_get_option( 'easyio_backup_files' ) ? 'on' : 'off' ) );
	if ( class_exists( 'Cloudinary' ) && Cloudinary::config_get( 'api_secret' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_enable_cloudinary'>" .
			esc_html__( 'Automatic Cloudinary Upload', 'easy-image-optimizer' ) .
			"</label></th><td><input type='checkbox' id='easyio_enable_cloudinary' name='easyio_enable_cloudinary' value='true' " .
			( easyio_get_option( 'easyio_enable_cloudinary' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'When enabled, uploads to the Media Library will be transferred to Cloudinary after optimization. Cloudinary generates resizes, so only the full-size image is uploaded.', 'easy-image-optimizer' ) .
			"</td></tr>\n";
		easyio_debug_message( 'cloudinary upload: ' . ( easyio_get_option( 'easyio_enable_cloudinary' ) ? 'on' : 'off' ) );
	}
	$output[] = "</table>\n</div>\n";
	$output[] = "<div id='ewww-exactdn-settings'>\n";
	$output[] = "<table class='form-table'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'ExactDN', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_exactdn'>" . esc_html__( 'ExactDN', 'easy-image-optimizer' ) .
		'</label>' . easyio_help_link( 'https://docs.ewww.io/article/44-introduction-to-exactdn', '59bc5ad6042863033a1ce370,59de6631042863379ddc953c,59c44349042863033a1d06d3,5ada43a12c7d3a0e93678b8c,5a3d278a2c7d3a1943677b52,5a9868eb04286374f7087795,59de68482c7d3a40f0ed6035,592dd69b2c7d3a074e8aed5b' ) . "</th><td><input type='checkbox' id='easyio_exactdn' name='easyio_exactdn' value='true' " .
		( easyio_get_option( 'easyio_exactdn' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Enables CDN and automatic image resizing to fit your pages.', 'easy-image-optimizer' ) .
		' <a href="https://ewww.io/resize/" target="_blank">' . esc_html__( 'Purchase a subscription for your site.', 'easy-image-optimizer' ) . '</a>' .
		'<p class="description">' .
		esc_html__( 'WebP Conversion', 'easy-image-optimizer' ) . easyio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) . '<br>' .
		esc_html__( 'Retina Support, use with WP Retina 2x for best results', 'easy-image-optimizer' ) . '<br>' .
		esc_html__( 'Premium Compression', 'easy-image-optimizer' ) . '<br>' .
		esc_html__( 'Adjustable Quality', 'easy-image-optimizer' ) . '<br>' .
		esc_html__( 'JS/CSS Minification and Compression', 'easy-image-optimizer' ) . '<br>' .
		'<a href="https://docs.ewww.io/article/44-introduction-to-exactdn" target="_blank" data-beacon-article="59bc5ad6042863033a1ce370">' . esc_html__( 'Learn more about ExactDN', 'easy-image-optimizer' ) . '</a>' .
		"</p></td></tr>\n";
	easyio_debug_message( 'ExactDN enabled: ' . ( easyio_get_option( 'easyio_exactdn' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_backup_files'>" . esc_html__( 'Include All Resources', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) . '</th>' .
		"<td><input type='checkbox' id='exactdn_all_the_things' name='exactdn_all_the_things' value='true' " .
		( easyio_get_option( 'exactdn_all_the_things' ) ? "checked='true'" : '' ) . '> ' . esc_html__( 'Use ExactDN for all resources in wp-includes/ and wp-content/, including JavaScript, CSS, fonts, etc.', 'easy-image-optimizer' ) . "</td></tr>\n";
	easyio_debug_message( 'ExactDN all the things: ' . ( easyio_get_option( 'exactdn_all_the_things' ) ? 'on' : 'off' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='exactdn_lossy'>" . esc_html__( 'Premium Compression', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/47-getting-more-from-exactdn', '59de6631042863379ddc953c' ) . '</th>' .
		"<td><input type='checkbox' id='exactdn_lossy' name='exactdn_lossy' value='true' " .
		( easyio_get_option( 'exactdn_lossy' ) ? "checked='true'" : '' ) . '> ' . esc_html__( 'Enable high quality premium compression for all images. Disable to use Pixel Perfect mode instead.', 'easy-image-optimizer' ) . "</td></tr>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_lazy_load'>" . esc_html__( 'Lazy Load', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/74-lazy-load', '5c6c36ed042863543ccd2d9b' ) .
		"</th><td><input type='checkbox' id='easyio_lazy_load' name='easyio_lazy_load' value='true' " . ( easyio_get_option( 'easyio_lazy_load' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Improves actual and perceived loading time as images will be loaded only as they enter (or are about to enter) the viewport. When used with the ExactDN and WebP features, the plugin will load the best available image size and format for each device.', 'easy-image-optimizer' ) . "</td></tr>\n";
	easyio_debug_message( 'ExactDN lossy: ' . intval( easyio_get_option( 'exactdn_lossy' ) ) );
	easyio_debug_message( 'ExactDN resize existing: ' . ( easyio_get_option( 'exactdn_resize_existing' ) ? 'on' : 'off' ) );
	easyio_debug_message( 'ExactDN attachment queries: ' . ( easyio_get_option( 'exactdn_prevent_db_queries' ) ? 'off' : 'on' ) );
	easyio_debug_message( 'lazy load: ' . ( easyio_get_option( 'easyio_lazy_load' ) ? 'on' : 'off' ) );
	if ( defined( 'EXACTDN_EXCLUDE' ) && EXACTDN_EXCLUDE ) {
		$exactdn_user_exclusions = EXACTDN_EXCLUDE;
		if ( is_array( $exactdn_user_exclusions ) ) {
			easyio_debug_message( 'ExactDN user exclusions : ' . implode( ',', $exactdn_user_exclusions ) );
		} elseif ( is_string( $exactdn_user_exclusions ) ) {
			easyio_debug_message( 'ExactDN user exclusions : ' . $exactdn_user_exclusions );
		} else {
			easyio_debug_message( 'ExactDN user exclusions invalid data type' );
		}
	}
	$output[] = "</table>\n</div>\n";
	$output[] = "<div id='ewww-optimization-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Advanced', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	if ( ! easyio_full_cloud() ) {
		easyio_debug_message( 'optipng level: ' . easyio_get_option( 'easyio_optipng_level' ) );
		easyio_debug_message( 'pngout disabled: ' . ( easyio_get_option( 'easyio_disable_pngout' ) ? 'yes' : 'no' ) );
		easyio_debug_message( 'pngout level: ' . easyio_get_option( 'easyio_pngout_level' ) );
	}
	$output[] = "<tr class='$network_class'><th scope='row'><span><label for='easyio_jpg_quality'>" . esc_html__( 'JPG Quality Level:', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,58543c69c697912ffd6c19a7' ) . "</th><td><input type='text' id='easyio_jpg_quality' name='easyio_jpg_quality' class='small-text' value='" . easyio_jpg_quality() . "' /> " . esc_html__( 'Valid values are 1-100.', 'easy-image-optimizer' ) . "\n<p class='description'>" . esc_html__( 'Use this to override the default WordPress quality level of 82. Applies to image editing, resizing, PNG to JPG conversion, and JPG to WebP conversion. Does not affect the original uploaded image unless maximum dimensions are set and resizing occurs.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_parallel_optimization'>" . esc_html__( 'Parallel Optimization', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,598cb8be2c7d3a73488be237' ) . "</th><td><input type='checkbox' id='easyio_parallel_optimization' name='easyio_parallel_optimization' value='true' " . ( easyio_get_option( 'easyio_parallel_optimization' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'All resizes generated from a single upload are optimized in parallel for faster optimization. If this is causing performance issues, disable parallel optimization to reduce the load on your server.', 'easy-image-optimizer' ) . "</td></tr>\n";
	easyio_debug_message( 'parallel optimization: ' . ( easyio_get_option( 'easyio_parallel_optimization' ) ? 'on' : 'off' ) );
	easyio_debug_message( 'background optimization: ' . ( easyio_get_option( 'easyio_background_optimization' ) ? 'on' : 'off' ) );
	if ( ! easyio_get_option( 'easyio_background_optimization' ) ) {
		$admin_ajax_url = admin_url( 'admin-ajax.php' );
		if ( strpos( $admin_ajax_url, 'admin-ajax.php' ) ) {
			easyio_debug_message( "admin ajax url: $admin_ajax_url" );
			$admin_ajax_host = parse_url( $admin_ajax_url, PHP_URL_HOST );
			easyio_debug_message( "admin ajax hostname: $admin_ajax_host" );
			$resolved = gethostbyname( $admin_ajax_host . '.' );
			easyio_debug_message( "resolved to $resolved" );
			if ( $resolved === $admin_ajax_host . '.' ) {
				easyio_debug_message( 'DNS lookup failed' );
			} else {
				$admin_ajax_url = add_query_arg(
					array(
						'action' => 'wp_easyio_test_optimize',
						'nonce'  => wp_create_nonce( 'wp_easyio_test_optimize' ),
					),
					$admin_ajax_url
				);
				easyio_debug_message( "admin ajax POST url: $admin_ajax_url" );
				$async_post_args = array(
					'body'      => array(
						'easyio_test_verify' => '949c34123cf2a4e4ce2f985135830df4a1b2adc24905f53d2fd3f5df5b16293245',
					),
					'cookies'   => $_COOKIE,
					'sslverify' => false,
				);
				// Don't lock up other requests while processing.
				session_write_close();
				$async_response = wp_remote_post( esc_url_raw( $admin_ajax_url ), $async_post_args );
				if ( is_wp_error( $async_response ) ) {
					$error_message = $async_response->get_error_message();
					easyio_debug_message( "async test failed: $error_message" );
				} elseif ( is_array( $async_response ) && isset( $async_response['body'] ) ) {
					easyio_debug_message( 'async success, possibly (response should be empty): ' . esc_html( substr( $async_response['body'], 0, 100 ) ) );
					if ( ! empty( $async_response['response']['code'] ) ) {
						easyio_debug_message( 'async response code: ' . $async_response['response']['code'] );
					}
				} else {
					easyio_debug_message( 'no async error, but no body either' );
				}
			}
		} else {
			easyio_debug_message( "invalid admin ajax url: $admin_ajax_url" );
		}
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_auto'>" . esc_html__( 'Scheduled Optimization', 'easy-image-optimizer' ) . '</label>' .
		easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) .
		"</th><td><input type='checkbox' id='easyio_auto' name='easyio_auto' value='true' " .
		( easyio_get_option( 'easyio_auto' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'This will enable scheduled optimization of unoptimized images for your theme, buddypress, and any additional folders you have configured below. Runs hourly: wp_cron only runs when your site is visited, so it may be even longer between optimizations.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	easyio_debug_message( 'scheduled optimization: ' . ( easyio_get_option( 'easyio_auto' ) ? 'on' : 'off' ) );
	$media_include_disable = '';
	if ( get_option( 'easyio_disable_resizes_opt' ) ) {
		$media_include_disable = 'disabled="disabled"';
		$output[]              = "<tr class='$network_class'><th>&nbsp;</th><td>" .
			'<p><span style="color: #3eadc9">' . esc_html__( '*Include Media Library Folders has been disabled because it will cause the scanner to ignore the disabled resizes.', 'easy-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_include_media_paths'>" . esc_html__( 'Include Media Folders', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) . "</th><td><input type='checkbox' id='easyio_include_media_paths' name='easyio_include_media_paths' $media_include_disable value='true' " . ( easyio_get_option( 'easyio_include_media_paths' ) && ! get_option( 'easyio_disable_resizes_opt' ) ? "checked='true'" : '' ) . ' /> ' . esc_html__( 'Scan all images from the latest two folders of the Media Library during the Bulk Optimizer and Scheduled Optimization.', 'easy-image-optimizer' ) . "</td></tr>\n";
	easyio_debug_message( 'include media library: ' . ( easyio_get_option( 'easyio_include_media_paths' ) ? 'on' : 'off' ) );
	$aux_paths = easyio_get_option( 'easyio_aux_paths' ) ? esc_html( implode( "\n", easyio_get_option( 'easyio_aux_paths' ) ) ) : '';
	$output[]  = "<tr class='$network_class'><th scope='row'><label for='easyio_aux_paths'>" . esc_html__( 'Folders to Optimize', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) . '</th><td>' .
		/* translators: %s: the folder where WordPress is installed */
		sprintf( esc_html__( 'One path per line, must be within %s. Use full paths, not relative paths.', 'easy-image-optimizer' ), ABSPATH ) . "<br>\n" .
		"<textarea id='easyio_aux_paths' name='easyio_aux_paths' rows='3' cols='60'>$aux_paths</textarea>\n" .
		"<p class='description'>" . esc_html__( 'Provide paths containing images to be optimized using the Bulk Optimizer and Scheduled Optimization.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
	easyio_debug_message( 'folders to optimize:' );
	easyio_debug_message( $aux_paths );

	$exclude_paths = easyio_get_option( 'easyio_exclude_paths' ) ? esc_html( implode( "\n", easyio_get_option( 'easyio_exclude_paths' ) ) ) : '';
	$output[]      = "<tr class='$network_class'><th scope='row'><label for='easyio_exclude_paths'>" . esc_html__( 'Folders to Ignore', 'easy-image-optimizer' ) . '</label>' . easyio_help_link( 'https://docs.ewww.io/article/11-advanced-configuration', '58542afac697912ffd6c18c0,5853713bc697912ffd6c0b98' ) . '</th><td>' . esc_html__( 'One path per line, partial paths allowed, but no urls.', 'easy-image-optimizer' ) . "<br>\n" .
		"<textarea id='easyio_exclude_paths' name='easyio_exclude_paths' rows='3' cols='60'>$exclude_paths</textarea>\n" .
		"<p class='description'>" . esc_html__( 'A file that matches any pattern or path provided will not be optimized.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
	easyio_debug_message( 'folders to ignore:' );
	easyio_debug_message( $exclude_paths );
	easyio_debug_message( 'skip images smaller than: ' . easyio_get_option( 'easyio_skip_size' ) . ' bytes' );
	easyio_debug_message( 'skip PNG images larger than: ' . easyio_get_option( 'easyio_skip_png_size' ) . ' bytes' );
	easyio_debug_message( 'exclude originals from lossy: ' . ( easyio_get_option( 'easyio_lossy_skip_full' ) ? 'on' : 'off' ) );
	easyio_debug_message( 'exclude originals from metadata removal: ' . ( easyio_get_option( 'easyio_metadata_skip_full' ) ? 'on' : 'off' ) );
	easyio_debug_message( 'use system binaries: ' . ( easyio_get_option( 'easyio_skip_bundle' ) ? 'yes' : 'no' ) );
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-resize-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Resize', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_resize_detection'>" . esc_html__( 'Resize Detection', 'easy-image-optimizer' ) . '</label>' .
		easyio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9' ) .
		"</th><td><input type='checkbox' id='easyio_resize_detection' name='easyio_resize_detection' value='true' " .
		( easyio_get_option( 'easyio_resize_detection' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Highlight images that need to be resized because the browser is scaling them down. Only visible for Admin users and adds a button to the admin bar to detect scaled images that have been lazy loaded.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	easyio_debug_message( 'resize detection: ' . ( easyio_get_option( 'easyio_resize_detection' ) ? 'on' : 'off' ) );

	$output[]           = '<tr class="network-singlesite"><th scope="row">' . esc_html__( 'Disable Resizes', 'easy-image-optimizer' ) .
		easyio_help_link( 'https://docs.ewww.io/article/41-resize-settings', '59849911042863033a1ba5f9,58598744c697912ffd6c3eb4' ) . '</th><td><p>' .
		esc_html__( 'WordPress, your theme, and other plugins generate various image sizes. You may disable optimization for certain sizes, or completely prevent those sizes from being created.', 'easy-image-optimizer' ) . '<br>' .
		'<i>' . esc_html__( 'Remember that each image size will affect your API credits.', 'easy-image-optimizer' ) . "</i></p>\n";
	$image_sizes        = easyio_get_image_sizes();
	$disabled_sizes     = get_option( 'easyio_disable_resizes' );
	$disabled_sizes_opt = get_option( 'easyio_disable_resizes_opt' );
	$output[]           = '<table><tr class="network-singlesite"><th scope="col">' . esc_html__( 'Disable Optimization', 'easy-image-optimizer' ) . '</th><th scope="col">' . esc_html__( 'Disable Creation', 'easy-image-optimizer' ) . "</th></tr>\n";
	easyio_debug_message( 'disabled resizes:' );
	foreach ( $image_sizes as $size => $dimensions ) {
		if ( 'thumbnail' === $size ) {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='easyio_disable_resizes_opt_$size' name='easyio_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='easyio_disable_resizes_$size' name='easyio_disable_resizes[$size]' value='true' disabled /></td><td><label for='easyio_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
		} elseif ( 'pdf-full' === $size ) {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='easyio_disable_resizes_opt_$size' name='easyio_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='easyio_disable_resizes_$size' name='easyio_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><label for='easyio_disable_resizes_$size'>$size - <span class='description'>" . esc_html__( 'Disabling creation of the full-size preview for PDF files will disable all PDF preview sizes', 'easy-image-optimizer' ) . "</span></label></td></tr>\n";
		} else {
			$output[] = "<tr class='network-singlesite'><td><input type='checkbox' id='easyio_disable_resizes_opt_$size' name='easyio_disable_resizes_opt[$size]' value='true' " . ( ! empty( $disabled_sizes_opt[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><input type='checkbox' id='easyio_disable_resizes_$size' name='easyio_disable_resizes[$size]' value='true' " . ( ! empty( $disabled_sizes[ $size ] ) ? "checked='true'" : '' ) . " /></td><td><label for='easyio_disable_resizes_$size'>$size - {$dimensions['width']}x{$dimensions['height']}</label></td></tr>\n";
		}
		easyio_debug_message( $size . ': ' . ( ! empty( $disabled_sizes_opt[ $size ] ) ? 'optimization=disabled ' : 'optimization=enabled ' ) . ( ! empty( $disabled_sizes[ $size ] ) ? 'creation=disabled' : 'creation=enabled' ) );
	}
	if ( 'network-multisite' !== $network ) {
		$output[] = "</table>\n";
		$output[] = "</td></tr>\n";
	} else {
		$output[] = '<tr><th scope="row">' . esc_html__( 'Disable Resizes', 'easy-image-optimizer' ) . '</th><td>';
		$output[] = '<p><span style="color: #3eadc9">' . esc_html__( '*Settings to disable creation and optimization of individual sizes must be configured for each individual site.', 'easy-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-conversion-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Convert', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = '<p>' . esc_html__( 'Conversion is only available for images in the Media Library (except WebP). By default, all images have a link available in the Media Library for one-time conversion. Turning on individual conversion operations below will enable conversion filters any time an image is uploaded or modified.', 'easy-image-optimizer' ) . "<br />\n" .
		'<b>' . esc_html__( 'NOTE:', 'easy-image-optimizer' ) . '</b> ' . esc_html__( 'The plugin will attempt to update image locations for any posts that contain the images. You may still need to manually update locations/urls for converted images.', 'easy-image-optimizer' ) . "\n" .
		"</p>\n";
	$output[] = "<table class='form-table'>\n";
	if ( $toolkit_found ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_disable_convert_links'>" . esc_html__( 'Hide Conversion Links', 'easy-image-optimizer' ) .
			'</label>' . easyio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><input type='checkbox' id='easyio_disable_convert_links' name='easyio_disable_convert_links' " .
			( easyio_get_option( 'easyio_disable_convert_links' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'Site or Network admins can use this to prevent other users from using the conversion links in the Media Library which bypass the settings below.', 'easy-image-optimizer' ) .
			"</td></tr>\n";
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_delete_originals'>" . esc_html__( 'Delete Originals', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><input type='checkbox' id='easyio_delete_originals' name='easyio_delete_originals' " .
			( easyio_get_option( 'easyio_delete_originals' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'This will remove the original image from the server after a successful conversion.', 'easy-image-optimizer' ) . "</td></tr>\n";
		easyio_debug_message( 'delete originals: ' . ( easyio_get_option( 'easyio_delete_originals' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_jpg_to_png'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'easy-image-optimizer' ), 'JPG', 'PNG' ) .
			'</label>' . easyio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><span><input type='checkbox' id='easyio_jpg_to_png' name='easyio_jpg_to_png' " .
			( easyio_get_option( 'easyio_jpg_to_png' ) ? "checked='true'" : '' ) . ' /> <b>' . esc_html__( 'WARNING:', 'easy-image-optimizer' ) . '</b> ' .
			esc_html__( 'Removes metadata and increases cpu usage dramatically.', 'easy-image-optimizer' ) . "</span>\n" .
			"<p class='description'>" . esc_html__( 'PNG is generally much better than JPG for logos and other images with a limited range of colors. Checking this option will slow down JPG processing significantly, and you may want to enable it only temporarily.', 'easy-image-optimizer' ) .
			"</p></td></tr>\n";
		easyio_debug_message( 'jpg2png: ' . ( easyio_get_option( 'easyio_jpg_to_png' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_png_to_jpg'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'easy-image-optimizer' ), 'PNG', 'JPG' ) .
			'</label>' . easyio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53,58543c69c697912ffd6c19a7,58542afac697912ffd6c18c0' ) .
			"</th><td><span><input type='checkbox' id='easyio_png_to_jpg' name='easyio_png_to_jpg' " .
			( easyio_get_option( 'easyio_png_to_jpg' ) ? "checked='true'" : '' ) . ' /> <b>' . esc_html__( 'WARNING:', 'easy-image-optimizer' ) . '</b> ' .
			esc_html__( 'This is not a lossless conversion.', 'easy-image-optimizer' ) . "</span>\n" .
			"<p class='description'>" . esc_html__( 'JPG is generally much better than PNG for photographic use because it compresses the image and discards data. PNGs with transparency are not converted by default.', 'easy-image-optimizer' ) . "</p>\n" .
			"<span><label for='easyio_jpg_background'> " . esc_html__( 'JPG Background Color:', 'easy-image-optimizer' ) . "</label> #<input type='text' id='easyio_jpg_background' name='easyio_jpg_background' size='6' value='" . easyio_jpg_background() . "' /> <span style='padding-left: 12px; font-size: 12px; border: solid 1px #555555; background-color: #" . easyio_jpg_background() . "'>&nbsp;</span> " . esc_html__( 'HEX format (#123def)', 'easy-image-optimizer' ) . ".</span>\n" .
			"<p class='description'>" . esc_html__( 'Background color is used only if the PNG has transparency. Leave this value blank to skip PNGs with transparency.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
		easyio_debug_message( 'png2jpg: ' . ( easyio_get_option( 'easyio_png_to_jpg' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_gif_to_png'>" .
			/* translators: 1: JPG, GIF or PNG 2: JPG or PNG */
			sprintf( esc_html__( '%1$s to %2$s Conversion', 'easy-image-optimizer' ), 'GIF', 'PNG' ) .
			'</label>' . easyio_help_link( 'https://docs.ewww.io/article/14-converting-images', '58545a86c697912ffd6c1b53' ) .
			"</th><td><span><input type='checkbox' id='easyio_gif_to_png' name='easyio_gif_to_png' " .
			( easyio_get_option( 'easyio_gif_to_png' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'No warnings here, just do it.', 'easy-image-optimizer' ) . "</span>\n" .
			"<p class='description'> " . esc_html__( 'PNG is generally better than GIF, but animated images cannot be converted.', 'easy-image-optimizer' ) . "</p></td></tr>\n";
		easyio_debug_message( 'gif2png: ' . ( easyio_get_option( 'easyio_gif_to_png' ) ? 'on' : 'off' ) );
	} else {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td>" .
			'<p><span style="color: #3eadc9">' . esc_html__( 'Image conversion requires one of the following PHP libraries: GD, Imagick, or GMagick.', 'easy-image-optimizer' ) . "</span></p></td></tr>\n";
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-webp-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'WebP', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<table class='form-table'>\n";
	if ( ! easyio_get_option( 'easyio_exactdn' ) || easyio_get_option( 'easyio_webp' ) ) {
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_webp'>" . esc_html__( 'JPG/PNG to WebP', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) .
			"</th><td><span><input type='checkbox' id='easyio_webp' name='easyio_webp' value='true' " .
			( easyio_get_option( 'easyio_webp' ) ? "checked='true'" : '' ) . ' /> <b>' .
			esc_html__( 'WARNING:', 'easy-image-optimizer' ) . '</b> ' . esc_html__( 'JPG to WebP conversion is lossy, but quality loss is minimal. PNG to WebP conversion is lossless.', 'easy-image-optimizer' ) .
			"</span>\n<p class='description'>" . esc_html__( 'Originals are never deleted, and WebP images should only be served to supported browsers.', 'easy-image-optimizer' ) .
			" <a href='#webp-rewrite'>" . ( easyio_get_option( 'easyio_webp' ) && ! easyio_get_option( 'easyio_webp_for_cdn' ) ? esc_html__( 'You can use the rewrite rules below to serve WebP images with Apache.', 'easy-image-optimizer' ) : '' ) . "</a></td></tr>\n";
		easyio_debug_message( 'webp conversion: ' . ( easyio_get_option( 'easyio_webp' ) ? 'on' : 'off' ) );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_webp_force'>" . esc_html__( 'Force WebP', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) .
			"</th><td><span><input type='checkbox' id='easyio_webp_force' name='easyio_webp_force' value='true' " .
			( easyio_get_option( 'easyio_webp_force' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'WebP images will be generated and saved for all JPG/PNG images regardless of their size. The JS WebP Rewriting will not check if a file exists, only that the domain matches the home url.', 'easy-image-optimizer' ) . "</span></td></tr>\n";
		easyio_debug_message( 'forced webp: ' . ( easyio_get_option( 'easyio_webp_force' ) ? 'on' : 'off' ) );
	}
	if ( ! easyio_ce_webp_enabled() && ! easyio_get_option( 'easyio_exactdn' ) ) {
		$webp_paths = easyio_get_option( 'easyio_webp_paths' ) ? esc_html( implode( "\n", easyio_get_option( 'easyio_webp_paths' ) ) ) : '';
		$output[]   = "<tr class='$network_class'><th scope='row'><label for='easyio_webp_paths'>" . esc_html__( 'WebP URLs', 'easy-image-optimizer' ) . '</label>' .
			easyio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89' ) . '</th><td>' .
			esc_html__( 'If Force WebP is enabled, enter URL patterns that should be permitted for JS WebP Rewriting. One pattern per line, may be partial URLs, but must include the domain name.', 'easy-image-optimizer' ) . '<br>' .
			"<textarea id='easyio_webp_paths' name='easyio_webp_paths' rows='3' cols='60'>$webp_paths</textarea></td></tr>\n";
		easyio_debug_message( 'webp paths:' );
		easyio_debug_message( $webp_paths );
		$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_webp_for_cdn'>" .
			esc_html__( 'JS WebP Rewriting', 'easy-image-optimizer' ) .
			'</label>' . easyio_help_link( 'https://docs.ewww.io/article/16-ewww-io-and-webp-images', '5854745ac697912ffd6c1c89,59443d162c7d3a0747cdf9f0' ) . "</th><td><span><input type='checkbox' id='easyio_webp_for_cdn' name='easyio_webp_for_cdn' value='true' " .
			( easyio_get_option( 'easyio_webp_for_cdn' ) ? "checked='true'" : '' ) . ' /> ' .
			esc_html__( 'Use this if the Apache rewrite rules do not work, or if your images are served from a CDN.', 'easy-image-optimizer' ) . ' ' .
			/* translators: %s: Cache Enabler (link) */
			sprintf( esc_html__( 'Sites using a CDN may also use the WebP option in the %s plugin.', 'easy-image-optimizer' ), '<a href="https://wordpress.org/plugins/cache-enabler/">Cache Enabler</a>' ) . '</span></td></tr>';
		easyio_debug_message( 'alt webp rewriting: ' . ( easyio_get_option( 'easyio_webp_for_cdn' ) ? 'on' : 'off' ) );
	} elseif ( easyio_get_option( 'easyio_exactdn' ) ) {
		$output[] = "<tr class='$network_class'><th>&nbsp;</th><td><p class='description'>" . esc_html__( 'WebP images are served automatically by ExactDN.', 'easy-image-optimizer' ) . '</p></td></tr>';
	}
	$output[] = "</table>\n</div>\n";

	$output[] = "<div id='ewww-support-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Support', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = "<p><a class='ewww-docs-root' href='https://docs.ewww.io/'>" . esc_html__( 'Documentation', 'easy-image-optimizer' ) . '</a> | ' .
		"<a class='ewww-docs-root' href='https://ewww.io/contact-us/'>" . esc_html__( 'Plugin Support', 'easy-image-optimizer' ) . '</a> | ' .
		"<a href='https://ewww.io/status/'>" . esc_html__( 'Server Status', 'easy-image-optimizer' ) . '</a>' .
		"</p>\n";
	$output[] = "<table class='form-table'>\n";
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_enable_help'>" . esc_html__( 'Enable Embedded Help', 'easy-image-optimizer' ) .
		"</label></th><td><input type='checkbox' id='easyio_enable_help' name='easyio_enable_help' value='true' " .
		( easyio_get_option( 'easyio_enable_help' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Enable the support beacon, which gives you access to documentation and our support team right from your WordPress dashboard. To assist you more efficiently, we may collect the current url, IP address, browser/device information, and debugging information.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	easyio_debug_message( 'enable help beacon: ' . ( easyio_get_option( 'easyio_enable_help' ) ? 'yes' : 'no' ) );
	$output[] = "<tr class='$network_class'><th scope='row'><label for='easyio_debug'>" . esc_html__( 'Debugging', 'easy-image-optimizer' ) . '</label>' .
		easyio_help_link( 'https://docs.ewww.io/article/7-basic-configuration', '585373d5c697912ffd6c0bb2' ) . '</th>' .
		"<td><input type='checkbox' id='easyio_debug' name='easyio_debug' value='true' " .
		( ! $eio_temp_debug && easyio_get_option( 'easyio_debug' ) ? "checked='true'" : '' ) . ' /> ' .
		esc_html__( 'Use this to provide information for support purposes, or if you feel comfortable digging around in the code to fix a problem you are experiencing.', 'easy-image-optimizer' ) .
		"</td></tr>\n";
	$output[] = "</table>\n";

	$output[] = 'DEBUG_PLACEHOLDER';

	$output[] = "</div>\n";

	$output[] = "<div id='ewww-contribute-settings'>\n";
	$output[] = '<noscript><h2>' . esc_html__( 'Contribute', 'easy-image-optimizer' ) . '</h2></noscript>';
	$output[] = '<p><strong>' . esc_html__( 'Here are some ways you can contribute to the development of this plugin:', 'easy-image-optimizer' ) . "</strong></p>\n";
	$output[] = "<p><a href='https://translate.wordpress.org/projects/wp-plugins/easy-image-optimizer/'>" . esc_html__( 'Translate Easy I.O.', 'easy-image-optimizer' ) . '</a> | ' .
		"<a href='https://wordpress.org/support/view/plugin-reviews/easy-image-optimizer#postform'>" . esc_html__( 'Write a review', 'easy-image-optimizer' ) . "</a></p>\n";
	$output[] = "</div>\n";

	$output[] = "<p class='submit'><input type='submit' class='button-primary' value='" . esc_attr__( 'Save Changes', 'easy-image-optimizer' ) . "' /></p>\n";
	$output[] = "</form>\n";
	// Make sure .htaccess rules are terminated when ExactDN is enabled.
	if ( easyio_get_option( 'easyio_exactdn' ) ) {
		easyio_webp_rewrite_verify();
	}
	if ( easyio_get_option( 'easyio_webp' ) && ! easyio_get_option( 'easyio_webp_for_cdn' ) && ! easyio_ce_webp_enabled() && ! easyio_get_option( 'easyio_exactdn' ) ) {
		if ( ! apache_mod_loaded( 'mod_rewrite' ) ) {
			easyio_debug_message( 'webp missing mod_rewrite' );
			/* translators: %s: mod_rewrite or mod_headers */
			$output[] = '<p><strong>' . sprintf( esc_html( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.' ), 'mod_rewrite' ) . "</strong></p>\n";
		}
		if ( ! apache_mod_loaded( 'mod_headers' ) ) {
			/* translators: %s: mod_rewrite or mod_headers */
			$output[] = '<p><strong>' . sprintf( esc_html( 'Your site appears to be missing %s, please contact your webhost or system administrator to enable this Apache module.' ), 'mod_headers' ) . "</strong></p>\n";
			easyio_debug_message( 'webp missing mod_headers' );
		}
		$output[] = "<form id='ewww-webp-rewrite'>\n";
		$output[] = '<p>' . esc_html__( 'There are many ways to serve WebP images to visitors with supported browsers. You may choose any you wish, but it is recommended to serve them with an .htaccess file using mod_rewrite and mod_headers. The plugin can insert the rules for you if the file is writable, or you can edit .htaccess yourself.', 'easy-image-optimizer' ) . "</p>\n";
		if ( ! easyio_webp_rewrite_verify() ) {
			$output[] = "<img id='webp-image' src='" . plugins_url( '/images/test.png', __FILE__ ) . "' style='float: right; padding: 0 0 10px 10px;'>\n" .
				"<p id='ewww-webp-rewrite-status'><b>" . esc_html__( 'Rules verified successfully', 'easy-image-optimizer' ) . "</b></p>\n" .
				"<button type='button' id='ewww-webp-remove' class='button-secondary action'>" . esc_html__( 'Remove Rewrite Rules', 'easy-image-optimizer' ) . "</button>\n";
			easyio_debug_message( 'webp .htaccess rewriting enabled' );
		} else {
			$output[] = "<pre id='webp-rewrite-rules' style='background: white; font-color: black; border: 1px solid black; clear: both; padding: 10px;'>\n" .
				"&lt;IfModule mod_rewrite.c&gt;\n" .
				"RewriteEngine On\n" .
				"RewriteCond %{HTTP_ACCEPT} image/webp\n" .
				"RewriteCond %{REQUEST_FILENAME} (.*)\.(jpe?g|png)$\n" .
				"RewriteCond %{REQUEST_FILENAME}\.webp -f\n" .
				"RewriteCond %{QUERY_STRING} !type=original\n" .
				"RewriteRule (.+)\.(jpe?g|png)$ %{REQUEST_FILENAME}.webp [T=image/webp,E=accept:1,L]\n" .
				"&lt;/IfModule&gt;\n" .
				"&lt;IfModule mod_headers.c&gt;\n" .
				"Header append Vary Accept env=REDIRECT_accept\n" .
				"&lt;/IfModule&gt;\n" .
				"AddType image/webp .webp</pre>\n" .
				"<img id='webp-image' src='" . plugins_url( '/images/test.png', __FILE__ ) . "' style='float: right; padding-left: 10px;'>\n" .
				"<p id='ewww-webp-rewrite-status'>" . esc_html__( 'The image to the right will display a WebP image with WEBP in white text, if your site is serving WebP images and your browser supports WebP.', 'easy-image-optimizer' ) . "</p>\n" .
				"<button type='button' id='ewww-webp-insert' class='button-secondary action'>" . esc_html__( 'Insert Rewrite Rules', 'easy-image-optimizer' ) . "</button>\n";
			easyio_debug_message( 'webp .htaccess rules not detected' );
		}
		$output[] = "</form>\n";
	} elseif ( easyio_get_option( 'easyio_webp_for_cdn' ) && ! easyio_ce_webp_enabled() ) {
		$test_webp_image = plugins_url( '/images/test.png.webp', __FILE__ );
		$test_png_image  = plugins_url( '/images/test.png', __FILE__ );
		$output[]        = "<noscript  data-img='$test_png_image' data-webp='$test_webp_image' data-style='float: right; padding: 0 0 10px 10px;' class='ewww_webp'><img src='$test_png_image' style='float: right; padding: 0 0 10px 10px;'></noscript>\n";
	}
	$output[] = "</div>\n";
	easyio_check_memory_available();
	$output = apply_filters( 'easyio_settings', $output );
	if ( easyio_get_option( 'easyio_webp_for_cdn' ) && ! easyio_ce_webp_enabled() && ! easyio_get_option( 'easyio_exactdn' ) ) {
		global $easyio_alt_webp;
		$easyio_alt_webp->inline_script();
	}

	$help_instructions = esc_html__( 'Enable the Debugging option and refresh this page to include debugging information with your question.', 'easy-image-optimizer' ) . ' ' .
		esc_html__( 'This will allow us to assist you more quickly.', 'easy-image-optimizer' );

	global $eio_debug;
	if ( ! empty( $eio_debug ) ) {
		$debug_output = '<p style="clear:both"><b>' . esc_html__( 'Debugging Information', 'easy-image-optimizer' ) . ':</b> <button id="ewww-copy-debug" class="button button-secondary" type="button">' . esc_html__( 'Copy', 'easy-image-optimizer' ) . '</button>';
		if ( is_file( WP_CONTENT_DIR . '/ewww/debug.log' ) ) {
			$debug_output .= "&emsp;<a href='admin.php?action=easyio_view_debug_log'>" . esc_html( 'View Debug Log', 'easy-image-optimizer' ) . "</a> - <a href='admin.php?action=easyio_delete_debug_log'>" . esc_html( 'Remove Debug Log', 'easy-image-optimizer' ) . '</a>';
		}
		$debug_output .= '</p>';
		$debug_output .= '<div id="ewww-debug-info" style="border:1px solid #e5e5e5;background:#fff;overflow:auto;height:300px;width:800px;" contenteditable="true">' . $eio_debug . '</div>';

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
		$hs_config    = array(
			'color'             => '#3eadc9',
			'icon'              => 'buoy',
			'instructions'      => $help_instructions,
			'poweredBy'         => false,
			'showContactFields' => true,
			'showSubject'       => true,
			'topArticles'       => true,
			'zIndex'            => 100000,
		);
		$hs_identify  = array(
			'email' => utf8_encode( $help_email ),
		);
		if ( ! empty( $eio_debug ) ) {
			$eio_debug_array = explode( '<br>', $eio_debug );
			$eio_debug_i     = 0;
			foreach ( $eio_debug_array as $eio_debug_line ) {
				$hs_identify[ 'debug_info_' . $eio_debug_i ] = $eio_debug_line;
				$eio_debug_i++;
			}
		}
		?>
<script type='text/javascript'>
	!function(e,o,n){window.HSCW=o,window.HS=n,n.beacon=n.beacon||{};var t=n.beacon;t.userConfig={},t.readyQueue=[],t.config=function(e){this.userConfig=e},t.ready=function(e){this.readyQueue.push(e)},o.config={docs:{enabled:!0,baseUrl:"//ewwwio.helpscoutdocs.com/"},contact:{enabled:!0,formId:"af75cf17-310a-11e7-9841-0ab63ef01522"}};var r=e.getElementsByTagName("script")[0],c=e.createElement("script");c.type="text/javascript",c.async=!0,c.src="https://djtflbt20bdde.cloudfront.net/",r.parentNode.insertBefore(c,r)}(document,window.HSCW||{},window.HS||{});
	HS.beacon.config(<?php echo json_encode( $hs_config ); ?>);
	HS.beacon.ready(function() {
		HS.beacon.identify(
			<?php echo json_encode( $hs_identify ); ?>
		);
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
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::debug( $message );
		return;
	}
	global $eio_temp_debug;
	if ( $eio_temp_debug || easyio_get_option( 'easyio_debug' ) ) {
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
	global $eio_debug;
	global $eio_temp_debug;
	$debug_log = WP_CONTENT_DIR . '/easyio/debug.log';
	if ( is_writable( WP_CONTENT_DIR ) && ! is_dir( WP_CONTENT_DIR . '/easyio\/' ) ) {
		mkdir( WP_CONTENT_DIR . '/easyio\/' );
	}
	if ( ! empty( $eio_debug ) && empty( $eio_temp_debug ) && easyio_get_option( 'easyio_debug' ) && is_writable( WP_CONTENT_DIR . '/easyio\/' ) ) {
		$memory_limit = easyio_memory_limit();
		clearstatcache();
		$timestamp = date( 'Y-m-d H:i:s' ) . "\n";
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
	if ( is_file( WP_CONTENT_DIR . '/easyio/debug.log' ) ) {
		easyio_ob_clean();
		header( 'Content-Type: text/plain;charset=UTF-8' );
		readfile( WP_CONTENT_DIR . '/easyio/debug.log' );
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
	if ( is_file( WP_CONTENT_DIR . '/easyio/debug.log' ) ) {
		unlink( WP_CONTENT_DIR . '/easyio/debug.log' );
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
	global $eio_temp_debug;
	global $eio_debug;
	if ( $eio_temp_debug ) {
		$eio_debug = '';
	}
	$eio_temp_debug = false;
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
	if ( strpos( $memory_limit, 'G' ) ) {
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
