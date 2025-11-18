<?php
/**
 * Class and methods to start an HTML buffer for parsing by other classes.
 *
 * @link https://ewww.io
 * @package Easy_Image_Optimizer
 */

namespace EasyIO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enables plugin to filter HTML through a variety of functions.
 */
class Buffer {

	/**
	 * Register hook function to startup buffer.
	 */
	public function __construct() {
		\add_action( 'template_redirect', array( $this, 'buffer_start' ), 9 );
	}

	/**
	 * Starts an output buffer and registers the callback function to do HTML parsing.
	 */
	public function buffer_start() {
		\ob_start( array( $this, 'filter_page_output' ) );
	}

	/**
	 * Parse page content through filter functions.
	 *
	 * @param string $buffer The HTML content to parse.
	 * @return string The filtered HTML content.
	 */
	public function filter_page_output( $buffer ) {
		return \apply_filters( 'easyio_filter_page_output', $buffer );
	}
}
