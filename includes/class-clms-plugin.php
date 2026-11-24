<?php
/**
 * Main plugin class.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Plugin
 */
class CLMS_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CLMS_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return CLMS_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private for singleton.
	}

	/**
	 * Run and initialize the plugin modules.
	 */
	public function run() {
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Initialize Custom Post Types & Meta.
		CLMS_Post_Types::init();

		// Initialize Settings & Quiz Design Customizer.
		require_once CLMS_PATH . 'admin/class-clms-admin-settings.php';
		if ( is_admin() ) {
			CLMS_Admin_Settings::init();
		}

		// Initialize Meta Boxes & Admin Columns.
		if ( is_admin() ) {
			CLMS_Meta_Boxes::init();
			require_once CLMS_PATH . 'admin/class-clms-admin-enrollments.php';
			CLMS_Admin_Enrollments::init();
		}

		// Initialize Frontend Views and Flow.
		CLMS_Frontend::init();

		// Initialize REST API endpoints.
		CLMS_Rest::init();

		// Initialize Quiz Engine & Admin Attempts.
		CLMS_Quiz::init();

		// Initialize WooCommerce integration hooks.
		if ( class_exists( 'CLMS_WooCommerce' ) ) {
			CLMS_WooCommerce::init();
		}
	}

	/**
	 * Load plugin textdomain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'custom-lms',
			false,
			dirname( CLMS_BASENAME ) . '/languages'
		);
	}
}
