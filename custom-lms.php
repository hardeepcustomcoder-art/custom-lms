<?php
/**
 * Plugin Name:       Custom LMS
 * Plugin URI:        https://example.com/custom-lms
 * Description:       A modern, modular Learning Management System for WordPress.
 * Version:           1.0.0
 * Author:            Your Team
 * Author URI:        https://example.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-lms
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.0
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'CLMS_VERSION', '1.0.0' );
define( 'CLMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLMS_URL', plugin_dir_url( __FILE__ ) );
define( 'CLMS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for Custom LMS classes located in includes/.
 *
 * Follows WordPress naming conventions:
 * Class CLMS_Post_Types -> includes/class-clms-post-types.php
 *
 * @param string $class_name Class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'CLMS_' ) ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$file_path = CLMS_PATH . 'includes/' . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, array( 'CLMS_Activator', 'activate' ) );

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, array( 'CLMS_Activator', 'deactivate' ) );

/**
 * Initialize and bootstrap the plugin.
 */
function clms_run_plugin() {
	$plugin = CLMS_Plugin::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'clms_run_plugin' );
