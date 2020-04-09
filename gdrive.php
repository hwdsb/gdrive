<?php
/*
Plugin Name: Media Explorer - Google Drive
Version: 0.1.1-alpha
Description: Allows users to select files from their own Google Drive to insert into posts.  Requires the Media Explorer and Google Docs Shortcode plugins.
Author: r-a-y
Text Domain: gdrive
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die();

add_action( 'mexp_init', array( 'MEXP_GDrive', 'init' ) );

/**
 * Google Drive integration with Media Explorer.
 *
 * @package MEXP_GDrive
 *
 * @link https://github.com/automattic/media-explorer Get the Media Explorer plugin here.
 */
class MEXP_GDrive {
	/**
	 * Absolute path to this directory.
	 *
	 * @var string
	 */
	public static $PATH = '';

	/**
	 * URL to this directory.
	 *
	 * @var string
	 */
	public static $URL = '';

	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// make sure our Google constants are defined before proceeding
		if ( false === defined( 'MEXP_GDRIVE_CLIENT_ID' ) || false === defined( 'MEXP_GDRIVE_CLIENT_SECRET' ) ) {
			add_action( 'admin_head', array( $this, 'show_constants_notice' ) );
			return;
		}

		// make sure Google Docs Shortcode v0.4 is installed before proceeding
		if ( false === function_exists( 'ray_gdoc_shortcode_init' ) ) {
			add_action( 'admin_head', array( $this, 'show_shortcode_notice' ) );
			return;
		}

		// properties
		$this->properties();

		// admin loader
		add_filter( 'mexp_services', array( $this, 'register_mexp_service' ) );
	}

	/**
	 * Properties.
	 */
	protected function properties() {
		self::$PATH  = dirname( __FILE__ );
		self::$URL   = plugins_url( basename( self::$PATH ) );
	}

	/**
	 * Registers our 'gdrive' service with Media Explorer.
	 *
	 * @param  array $services Current registered services.
	 * @return array
	 */
	public function register_mexp_service( array $services ) {
		if ( false === class_exists( 'MEXP_GDrive_Service' ) ) {
			require dirname( __FILE__ ) . '/includes/mexp-service.php';
		}

		$services['gdrive'] = new MEXP_GDrive_Service;

		return $services;
	}

	/**
	 * Helper function to add a notice.
	 */
	protected function add_notice( $notice = '' ) {
		// only show notice for admins
		if ( false === current_user_can( 'install_plugins' ) ) {
			return;
		}

		$hook = is_network_admin() ? 'network_admin_notices' : 'admin_notices';

		add_action( $hook, function() use ( $notice ) {
			printf( '<div class="error"><p>%s</p></div>', $notice );
		} );
	}

	/**
	 * Displays an admin notice if our Google constants are not defined yet.
	 */
	public function show_constants_notice() {
		$notice = sprintf( __( '<strong>Media Explorer - Google Drive</strong> requires the %s and %s constants to be defined.  Please view the readme.md file for more information.', 'gdrive' ), '<code>MEXP_GDRIVE_CLIENT_ID</code>', '<code>MEXP_GDRIVE_CLIENT_SECRET</code>' );

		$this->add_notice( $notice );
	}

	/**
	 * Displays an admin notice if Google Docs Shortcode v0.4 isn't installed.
	 */
	public function show_shortcode_notice() {
		$notice = __( '<strong>Media Explorer - Google Drive</strong> requires the latest version of the Google Docs Shortcode plugin.  Please install and activate it.', 'gdrive' );

		$this->add_notice( $notice );
	}
}