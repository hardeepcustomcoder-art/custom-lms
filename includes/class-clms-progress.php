<?php
/**
 * Progress tracking helpers for Custom LMS.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Progress
 */
class CLMS_Progress {

	/**
	 * Mark a topic or quiz as complete for a student.
	 *
	 * @param int         $user_id     User ID.
	 * @param int         $object_id   Topic or Quiz ID.
	 * @param string      $object_type 'topic' or 'quiz'.
	 * @param float|null  $score       Optional quiz score.
	 * @param string      $status      'completed', 'passed', or 'failed'.
	 * @return bool|int Insert ID or false.
	 */
	public static function mark_complete( $user_id, $object_id, $object_type = 'topic', $score = null, $status = 'completed' ) {
		global $wpdb;
		$table = CLMS_DB::get_progress_table();

		$lesson_id = absint( get_post_meta( $object_id, '_clms_lesson_id', true ) );
		$course_id = absint( get_post_meta( $object_id, '_clms_course_id', true ) );

		if ( ! $course_id && $lesson_id ) {
			$course_id = absint( get_post_meta( $lesson_id, '_clms_course_id', true ) );
		}

		// Count existing attempts for this object.
		$attempts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND object_id = %d",
				$user_id,
				$object_id
			)
		);

		$attempt_number = $attempts + 1;

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'        => $user_id,
				'course_id'      => $course_id,
				'lesson_id'      => $lesson_id,
				'object_id'      => $object_id,
				'object_type'    => $object_type,
				'status'         => $status,
				'score'          => $score,
				'attempt_number' => $attempt_number,
				'completed_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%d', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get array of completed object IDs (topics/quizzes) for a user in a course or lesson.
	 *
	 * @param int      $user_id   User ID.
	 * @param int|null $course_id Optional course ID filter.
	 * @param int|null $lesson_id Optional lesson ID filter.
	 * @return array Array of completed object IDs.
	 */
	public static function get_completed_ids( $user_id, $course_id = null, $lesson_id = null ) {
		global $wpdb;
		$table = CLMS_DB::get_progress_table();

		$where = 'WHERE user_id = %d AND status IN (\'completed\', \'passed\')';
		$params = array( $user_id );

		if ( $course_id ) {
			$where .= ' AND course_id = %d';
			$params[] = $course_id;
		}

		if ( $lesson_id ) {
			$where .= ' AND lesson_id = %d';
			$params[] = $lesson_id;
		}

		$sql = $wpdb->prepare( "SELECT DISTINCT object_id FROM {$table} {$where}", ...$params );
		$ids = $wpdb->get_col( $sql );

		return array_map( 'intval', $ids );
	}

	/**
	 * Check if a specific topic or quiz is completed by a user.
	 *
	 * @param int $user_id   User ID.
	 * @param int $object_id Object ID.
	 * @return bool
	 */
	public static function is_completed( $user_id, $object_id ) {
		global $wpdb;
		$table = CLMS_DB::get_progress_table();

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND object_id = %d AND status IN ('completed', 'passed')",
				$user_id,
				$object_id
			)
		);

		return ( (int) $count > 0 );
	}

	/**
	 * Calculate course completion percentage (0 to 100) for a user.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return int Percentage 0-100.
	 */
	public static function get_course_progress_percent( $user_id, $course_id ) {
		$lessons = CLMS_Frontend::get_course_lessons( $course_id );
		if ( empty( $lessons ) ) {
			return 0;
		}

		$total_items = 0;
		foreach ( $lessons as $l ) {
			$topics = CLMS_Frontend::get_lesson_topics( $l->ID );
			$total_items += count( $topics );
			$quiz = CLMS_Frontend::get_lesson_quiz( $l->ID );
			if ( $quiz ) {
				$total_items++;
			}
		}

		if ( 0 === $total_items ) {
			return 0;
		}

		$completed_ids = self::get_completed_ids( $user_id, $course_id );
		$completed_count = count( $completed_ids );

		$percent = round( ( $completed_count / $total_items ) * 100 );
		return (int) min( 100, max( 0, $percent ) );
	}
}
