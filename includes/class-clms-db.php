<?php
/**
 * Database table management, migrations, and schema definition for Custom LMS.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_DB
 */
class CLMS_DB {

	/**
	 * Current database schema version.
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * Get enrollments table name with prefix.
	 *
	 * @return string
	 */
	public static function get_enrollments_table() {
		global $wpdb;
		return $wpdb->prefix . 'clms_enrollments';
	}

	/**
	 * Get progress table name with prefix.
	 *
	 * @return string
	 */
	public static function get_progress_table() {
		global $wpdb;
		return $wpdb->prefix . 'clms_progress';
	}

	/**
	 * Get quiz attempts table name with prefix.
	 *
	 * @return string
	 */
	public static function get_quiz_attempts_table() {
		global $wpdb;
		return $wpdb->prefix . 'clms_quiz_attempts';
	}

	/**
	 * Create or upgrade custom tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate      = $wpdb->get_charset_collate();
		$enrollments_table    = self::get_enrollments_table();
		$progress_table       = self::get_progress_table();
		$quiz_attempts_table  = self::get_quiz_attempts_table();

		// 1. Enrollments Table.
		$sql_enrollments = "CREATE TABLE {$enrollments_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			source varchar(20) NOT NULL DEFAULT 'manual',
			order_id bigint(20) unsigned DEFAULT 0,
			enrolled_at datetime NOT NULL,
			expires_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id, course_id),
			KEY status (status)
		) {$charset_collate};";

		// 2. Progress Table.
		$sql_progress = "CREATE TABLE {$progress_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(20) NOT NULL DEFAULT 'topic',
			status varchar(20) NOT NULL DEFAULT 'completed',
			score float DEFAULT NULL,
			attempt_number int(11) unsigned NOT NULL DEFAULT 1,
			completed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_course (user_id, course_id),
			KEY user_object (user_id, object_id)
		) {$charset_collate};";

		// 3. Quiz Attempts Table (Detailed questions, answers, scores & pass/fail).
		$sql_quiz_attempts = "CREATE TABLE {$quiz_attempts_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			user_email varchar(100) NOT NULL,
			course_id bigint(20) unsigned NOT NULL,
			lesson_id bigint(20) unsigned NOT NULL,
			quiz_id bigint(20) unsigned NOT NULL,
			attempt_number int(11) unsigned NOT NULL DEFAULT 1,
			total_marks int(11) unsigned NOT NULL DEFAULT 0,
			obtained_marks int(11) unsigned NOT NULL DEFAULT 0,
			score_percent float NOT NULL DEFAULT 0,
			pass_percent int(11) unsigned NOT NULL DEFAULT 70,
			status varchar(20) NOT NULL DEFAULT 'failed',
			answers_data longtext DEFAULT NULL,
			submitted_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_quiz (user_id, quiz_id),
			KEY course_lesson (course_id, lesson_id),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql_enrollments );
		dbDelta( $sql_progress );
		dbDelta( $sql_quiz_attempts );

		update_option( 'clms_db_version', self::DB_VERSION );
	}

	/**
	 * Check if DB upgrade is needed.
	 */
	public static function check_upgrade() {
		$installed_ver = get_option( 'clms_db_version', '0.0.0' );
		if ( version_compare( $installed_ver, self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}
	}
}
