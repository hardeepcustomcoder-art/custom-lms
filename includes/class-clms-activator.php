<?php
/**
 * Fired during plugin activation and deactivation.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Activator
 */
class CLMS_Activator {

	/**
	 * Plugin activation routine.
	 */
	public static function activate() {
		// Store DB version option for migrations (tables come in Stage 2).
		if ( ! get_option( 'clms_db_version' ) ) {
			add_option( 'clms_db_version', '1.0.0' );
		}

		// Register post types and rewrite rules immediately on activation.
		CLMS_Post_Types::register_post_types();
		CLMS_Post_Types::register_meta();

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation routine.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
