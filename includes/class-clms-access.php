<?php
/**
 * Central Access Control API for Custom LMS.
 * Single source of truth for access verification.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Access
 */
class CLMS_Access {

	/**
	 * Check if a user can manage a course (admin or course instructor).
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function can_manage_course( $user_id, $course_id ) {
		if ( ! $user_id ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$instructor_id = (int) get_post_meta( $course_id, '_clms_instructor_id', true );
		if ( ! $instructor_id ) {
			$post = get_post( $course_id );
			$instructor_id = $post ? (int) $post->post_author : 0;
		}

		return ( $instructor_id === (int) $user_id && user_can( $user_id, 'edit_posts' ) );
	}

	/**
	 * Check if a user is actively enrolled in a course.
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_enrolled( $user_id, $course_id ) {
		if ( ! $user_id || ! $course_id ) {
			return false;
		}

		// Admins / course managers always have access.
		if ( self::can_manage_course( $user_id, $course_id ) ) {
			return true;
		}

		global $wpdb;
		$table = CLMS_DB::get_enrollments_table();

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} 
				WHERE user_id = %d AND course_id = %d 
				AND (expires_at IS NULL OR expires_at > %s)",
				$user_id,
				$course_id,
				current_time( 'mysql' )
			)
		);

		return ( 'active' === $status );
	}

	/**
	 * Check if a topic is the free preview topic of its course.
	 * Free ONLY if it is the very first topic of the course (first lesson, first topic).
	 *
	 * @param int $topic_id Topic post ID.
	 * @return bool
	 */
	public static function is_free_topic( $topic_id ) {
		if ( ! $topic_id ) {
			return false;
		}

		$lesson_id = (int) get_post_meta( $topic_id, '_clms_lesson_id', true );
		if ( ! $lesson_id ) {
			return false;
		}

		$course_id = (int) get_post_meta( $topic_id, '_clms_course_id', true );
		if ( ! $course_id ) {
			$course_id = (int) get_post_meta( $lesson_id, '_clms_course_id', true );
		}

		if ( $course_id ) {
			$lessons = CLMS_Frontend::get_course_lessons( $course_id );
			if ( empty( $lessons ) || (int) $lessons[0]->ID !== $lesson_id ) {
				return false;
			}
		}

		$topics = CLMS_Frontend::get_lesson_topics( $lesson_id );
		if ( empty( $topics ) ) {
			return false;
		}

		return ( (int) $topics[0]->ID === (int) $topic_id );
	}

	/**
	 * Single source of truth: Can user view the topic?
	 *
	 * Logic:
	 * 0. User MUST be logged in (guest users cannot view any course video content)
	 * 1. Admin or owning instructor -> true
	 * 2. Free preview topic (first topic of course) -> true (for logged-in users)
	 * 3. User is actively enrolled -> true
	 * 4. Otherwise -> false
	 *
	 * @param int $user_id  User ID (0 for visitor).
	 * @param int $topic_id Topic ID.
	 * @return bool
	 */
	public static function can_view_topic( $user_id, $topic_id ) {
		if ( ! $user_id || ! $topic_id ) {
			return false;
		}

		$course_id = (int) get_post_meta( $topic_id, '_clms_course_id', true );
		if ( ! $course_id ) {
			$lesson_id = (int) get_post_meta( $topic_id, '_clms_lesson_id', true );
			if ( $lesson_id ) {
				$course_id = (int) get_post_meta( $lesson_id, '_clms_course_id', true );
			}
		}

		// 1. Manager check
		if ( $user_id && $course_id && self::can_manage_course( $user_id, $course_id ) ) {
			return true;
		}

		// 2. Free preview check
		if ( self::is_free_topic( $topic_id ) ) {
			return true;
		}

		// 3. Active enrollment check
		if ( $user_id && $course_id && self::is_enrolled( $user_id, $course_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if all topics in a lesson are completed by a user.
	 *
	 * @param int $user_id   User ID.
	 * @param int $lesson_id Lesson ID.
	 * @return bool
	 */
	public static function lesson_topics_completed( $user_id, $lesson_id ) {
		if ( ! $user_id || ! $lesson_id ) {
			return false;
		}

		$topics = CLMS_Frontend::get_lesson_topics( $lesson_id );
		if ( empty( $topics ) ) {
			return true;
		}

		$completed_ids = CLMS_Progress::get_completed_ids( $user_id, null, $lesson_id );

		foreach ( $topics as $topic ) {
			if ( ! in_array( (int) $topic->ID, $completed_ids, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Single source of truth: Can user take the quiz?
	 *
	 * Logic:
	 * 1. Must be logged in
	 * 2. Must be enrolled in parent course (or manager)
	 * 3. Must have completed ALL topics in that lesson
	 * 4. Must have remaining attempts (if max_attempts > 0)
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz ID.
	 * @return bool
	 */
	public static function can_take_quiz( $user_id, $quiz_id ) {
		if ( ! $user_id || ! $quiz_id ) {
			return false;
		}

		$course_id = (int) get_post_meta( $quiz_id, '_clms_course_id', true );
		$lesson_id = (int) get_post_meta( $quiz_id, '_clms_lesson_id', true );

		if ( ! $course_id && $lesson_id ) {
			$course_id = (int) get_post_meta( $lesson_id, '_clms_course_id', true );
		}

		$is_manager = self::can_manage_course( $user_id, $course_id );

		// 1. Enrollment check (managers bypass enrollment requirement)
		if ( ! $is_manager && ! self::is_enrolled( $user_id, $course_id ) ) {
			return false;
		}

		// 2. Lesson topics completion check (managers bypass topic prerequisites)
		if ( ! $is_manager && ! self::lesson_topics_completed( $user_id, $lesson_id ) ) {
			return false;
		}

		// 3. Single submission & attempt limit check (applies to all users to enforce 1-attempt default & admin retakes)
		if ( class_exists( 'CLMS_Quiz' ) ) {
			$stats = CLMS_Quiz::get_attempt_stats( $user_id, $quiz_id );
			if ( ! $stats['can_attempt'] ) {
				return false;
			}
		}

		return true;
	}
}
