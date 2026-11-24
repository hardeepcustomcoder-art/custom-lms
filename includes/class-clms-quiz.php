<?php
/**
 * Quiz engine for Custom LMS.
 * Handles question evaluation, scoring, attempt logging, and email notifications.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Quiz
 */
class CLMS_Quiz {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
		add_action( 'admin_post_clms_quick_grant_retake', array( __CLASS__, 'handle_quick_grant_post' ) );
	}

	/**
	 * Calculate attempt and retake statistics for a user on a quiz.
	 *
	 * Single submission is enforced by default: each user gets 1 attempt.
	 * Additional attempts require explicit administrator authorization.
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz post ID.
	 * @return array
	 */
	public static function get_attempt_stats( $user_id, $quiz_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$quiz_id = absint( $quiz_id );

		if ( ! $user_id || ! $quiz_id ) {
			return array(
				'attempts_count' => 0,
				'base_allowed'   => 1,
				'extra_allowed'  => 0,
				'total_allowed'  => 1,
				'remaining'      => 1,
				'can_attempt'    => false,
				'has_completed'  => false,
				'latest_attempt' => null,
				'past_attempts'  => array(),
				'retake_granted' => false,
			);
		}

		$table_attempts = CLMS_DB::get_quiz_attempts_table();
		$past_attempts  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_attempts} WHERE user_id = %d AND quiz_id = %d ORDER BY submitted_at DESC, id DESC",
				$user_id,
				$quiz_id
			)
		);

		$attempts_count = is_array( $past_attempts ) ? count( $past_attempts ) : 0;
		$latest_attempt = ! empty( $past_attempts ) ? $past_attempts[0] : null;

		$base_max = get_post_meta( $quiz_id, '_clms_max_attempts', true );
		if ( '' === $base_max || false === $base_max || null === $base_max || (int) $base_max <= 0 ) {
			$base_allowed = 1; // Default is single attempt!
		} else {
			$base_allowed = (int) $base_max;
		}

		$extra_allowed = (int) get_user_meta( $user_id, '_clms_extra_attempts_' . $quiz_id, true );
		if ( $extra_allowed < 0 ) {
			$extra_allowed = 0;
		}

		$total_allowed = $base_allowed + $extra_allowed;
		$remaining     = max( 0, $total_allowed - $attempts_count );
		$can_attempt   = ( $remaining > 0 );
		$has_completed = ( $attempts_count > 0 );
		$retake_granted = ( $extra_allowed > 0 && $attempts_count >= $base_allowed && $can_attempt );

		return array(
			'attempts_count' => $attempts_count,
			'base_allowed'   => $base_allowed,
			'extra_allowed'  => $extra_allowed,
			'total_allowed'  => $total_allowed,
			'remaining'      => $remaining,
			'can_attempt'    => $can_attempt,
			'has_completed'  => $has_completed,
			'latest_attempt' => $latest_attempt,
			'past_attempts'  => $past_attempts,
			'retake_granted' => $retake_granted,
		);
	}

	/**
	 * Grant 1 additional attempt (retake) to a user for a quiz.
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz post ID.
	 * @return bool
	 */
	public static function grant_retake( $user_id, $quiz_id ) {
		$user_id = absint( $user_id );
		$quiz_id = absint( $quiz_id );
		if ( ! $user_id || ! $quiz_id ) {
			return false;
		}

		$stats     = self::get_attempt_stats( $user_id, $quiz_id );
		// Ensure total_allowed = attempts_count + 1
		$new_extra = max( 1, ( $stats['attempts_count'] + 1 ) - $stats['base_allowed'] );
		update_user_meta( $user_id, '_clms_extra_attempts_' . $quiz_id, $new_extra );
		return true;
	}

	/**
	 * Revoke any extra granted attempts for a user on a quiz.
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz post ID.
	 * @return bool
	 */
	public static function revoke_retake( $user_id, $quiz_id ) {
		$user_id = absint( $user_id );
		$quiz_id = absint( $quiz_id );
		if ( ! $user_id || ! $quiz_id ) {
			return false;
		}
		delete_user_meta( $user_id, '_clms_extra_attempts_' . $quiz_id );
		return true;
	}

	/**
	 * Reset attempt history for a user on a quiz.
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz post ID.
	 * @return bool
	 */
	public static function reset_attempts( $user_id, $quiz_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$quiz_id = absint( $quiz_id );
		if ( ! $user_id || ! $quiz_id ) {
			return false;
		}

		delete_user_meta( $user_id, '_clms_extra_attempts_' . $quiz_id );

		$table_attempts = CLMS_DB::get_quiz_attempts_table();
		$wpdb->delete(
			$table_attempts,
			array(
				'user_id' => $user_id,
				'quiz_id' => $quiz_id,
			),
			array( '%d', '%d' )
		);

		$table_progress = CLMS_DB::get_progress_table();
		$wpdb->delete(
			$table_progress,
			array(
				'user_id'     => $user_id,
				'object_id'   => $quiz_id,
				'object_type' => 'quiz',
			),
			array( '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Handle admin quick grant form POST submission.
	 */
	public static function handle_quick_grant_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'custom-lms' ) );
		}

		check_admin_referer( 'clms_quick_grant_retake_action', 'clms_retake_nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

		if ( $user_id && $quiz_id ) {
			self::grant_retake( $user_id, $quiz_id );
			$redirect = add_query_arg(
				array(
					'post_type'      => 'clms_course',
					'page'           => 'clms-quiz-attempts',
					'retake_granted' => 1,
					'uid'            => $user_id,
					'qid'            => $quiz_id,
				),
				admin_url( 'edit.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts' ) );
		exit;
	}

	/**
	 * Handle admin retake actions from GET query parameters.
	 */
	public static function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'clms_grant_retake', 'clms_revoke_retake', 'clms_reset_attempts' ), true ) ) {
			return;
		}

		check_admin_referer( 'clms_retake_action' );

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$quiz_id = isset( $_GET['quiz_id'] ) ? absint( $_GET['quiz_id'] ) : 0;
		$act     = sanitize_key( $_GET['action'] );

		$redirect = remove_query_arg( array( 'action', '_wpnonce' ) );

		if ( $user_id && $quiz_id ) {
			if ( 'clms_grant_retake' === $act ) {
				self::grant_retake( $user_id, $quiz_id );
				$redirect = add_query_arg( 'retake_granted', 1, $redirect );
			} elseif ( 'clms_revoke_retake' === $act ) {
				self::revoke_retake( $user_id, $quiz_id );
				$redirect = add_query_arg( 'retake_revoked', 1, $redirect );
			} elseif ( 'clms_reset_attempts' === $act ) {
				self::reset_attempts( $user_id, $quiz_id );
				$redirect = add_query_arg( 'attempts_reset', 1, $redirect );
			}
		}

		if ( isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] ) ) {
			wp_safe_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) );
		} else {
			wp_safe_redirect( $redirect );
		}
		exit;
	}

	/**
	 * Register Quiz Attempts submenu under Custom LMS.
	 */
	public static function register_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=clms_course',
			__( 'Quiz Attempts', 'custom-lms' ),
			__( 'Quiz Attempts', 'custom-lms' ),
			'manage_options',
			'clms-quiz-attempts',
			array( __CLASS__, 'render_admin_attempts_page' )
		);
	}

	/**
	 * Process a quiz submission.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $quiz_id Quiz post ID.
	 * @param array $answers User submitted answers [ question_id => option_index ].
	 * @return array Evaluation results.
	 */
	public static function process_submission( $user_id, $quiz_id, $answers ) {
		global $wpdb;

		$quiz = get_post( $quiz_id );
		if ( ! $quiz || 'clms_quiz' !== $quiz->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid quiz.', 'custom-lms' ),
			);
		}

		$course_id = (int) get_post_meta( $quiz_id, '_clms_course_id', true );
		$lesson_id = (int) get_post_meta( $quiz_id, '_clms_lesson_id', true );
		if ( ! $course_id && $lesson_id ) {
			$course_id = (int) get_post_meta( $lesson_id, '_clms_course_id', true );
		}

		// Security: Access check
		if ( ! CLMS_Access::can_take_quiz( $user_id, $quiz_id ) ) {
			$stats = self::get_attempt_stats( $user_id, $quiz_id );
			if ( $stats['has_completed'] && ! $stats['can_attempt'] ) {
				return array(
					'success' => false,
					'message' => __( 'You have already completed this quiz. Additional attempts require administrator permission.', 'custom-lms' ),
				);
			}
			return array(
				'success' => false,
				'message' => __( 'You must complete all topics in this lesson before taking the quiz.', 'custom-lms' ),
			);
		}

		// Double-check attempt limit
		$stats = self::get_attempt_stats( $user_id, $quiz_id );
		if ( ! $stats['can_attempt'] ) {
			return array(
				'success' => false,
				'message' => __( 'You have already completed this quiz. Additional attempts require administrator permission.', 'custom-lms' ),
			);
		}

		$questions_json = get_post_meta( $quiz_id, '_clms_questions', true );
		$questions      = ! empty( $questions_json ) ? json_decode( $questions_json, true ) : array();

		if ( empty( $questions ) ) {
			return array(
				'success' => false,
				'message' => __( 'No questions configured for this quiz.', 'custom-lms' ),
			);
		}

		$total_questions = count( $questions );
		$obtained_marks  = 0;
		$detailed_log    = array();

		foreach ( $questions as $q ) {
			$qid          = isset( $q['id'] ) ? $q['id'] : 0;
			$qtext        = isset( $q['text'] ) ? $q['text'] : '';
			$options      = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : array();
			$correct_idx  = isset( $q['correct_index'] ) ? (int) $q['correct_index'] : 0;
			$user_ans_idx = isset( $answers[ $qid ] ) ? (int) $answers[ $qid ] : -1;

			$is_correct = ( $user_ans_idx === $correct_idx );
			if ( $is_correct ) {
				$obtained_marks++;
			}

			$user_ans_val    = isset( $options[ $user_ans_idx ] ) ? $options[ $user_ans_idx ] : __( '(No Answer)', 'custom-lms' );
			$correct_ans_val = isset( $options[ $correct_idx ] ) ? $options[ $correct_idx ] : '';

			$user_ans_text    = is_array( $user_ans_val ) ? ( isset( $user_ans_val['text'] ) ? $user_ans_val['text'] : '' ) : $user_ans_val;
			$correct_ans_text = is_array( $correct_ans_val ) ? ( isset( $correct_ans_val['text'] ) ? $correct_ans_val['text'] : '' ) : $correct_ans_val;

			$detailed_log[] = array(
				'id'               => $qid,
				'question'         => $qtext,
				'user_answer'      => $user_ans_text,
				'correct_answer'   => $correct_ans_text,
				'is_correct'       => $is_correct,
				'explanation'      => isset( $q['explanation'] ) ? $q['explanation'] : '',
			);
		}

		$score_percent = round( ( $obtained_marks / $total_questions ) * 100, 1 );
		$pass_percent  = (int) get_post_meta( $quiz_id, '_clms_pass_percent', true );
		if ( ! $pass_percent ) {
			$pass_percent = 70;
		}

		$status = ( $score_percent >= $pass_percent ) ? 'passed' : 'failed';

		// Determine attempt number
		$table_attempts = CLMS_DB::get_quiz_attempts_table();
		$attempt_count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_attempts} WHERE user_id = %d AND quiz_id = %d",
				$user_id,
				$quiz_id
			)
		);
		$attempt_number = $attempt_count + 1;

		$user = get_userdata( $user_id );
		$user_email = $user ? $user->user_email : '';

		// 1. Save detailed attempt to backend database table
		$wpdb->insert(
			$table_attempts,
			array(
				'user_id'        => $user_id,
				'user_email'     => $user_email,
				'course_id'      => $course_id,
				'lesson_id'      => $lesson_id,
				'quiz_id'        => $quiz_id,
				'attempt_number' => $attempt_number,
				'total_marks'    => $total_questions,
				'obtained_marks' => $obtained_marks,
				'score_percent'  => $score_percent,
				'pass_percent'   => $pass_percent,
				'status'         => $status,
				'answers_data'   => wp_json_encode( $detailed_log ),
				'submitted_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s' )
		);

		// 2. Record in clms_progress table
		CLMS_Progress::mark_complete( $user_id, $quiz_id, 'quiz', $score_percent, $status );

		// 3. Send automated Email Notification to student's registered email
		self::send_result_email( $user, $quiz, $lesson_id, $course_id, $obtained_marks, $total_questions, $score_percent, $pass_percent, $status, $detailed_log );

		return array(
			'success'        => true,
			'score'          => $score_percent,
			'obtained_marks' => $obtained_marks,
			'total_marks'    => $total_questions,
			'pass_percent'   => $pass_percent,
			'status'         => $status,
			'results'        => $detailed_log,
			'attempt_number' => $attempt_number,
		);
	}

	/**
	 * Send Quiz Result Email to the user.
	 */
	public static function send_result_email( $user, $quiz, $lesson_id, $course_id, $obtained, $total, $percent, $pass_percent, $status, $log ) {
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$course_title = $course_id ? get_the_title( $course_id ) : __( 'Course', 'custom-lms' );
		$lesson_title = $lesson_id ? get_the_title( $lesson_id ) : __( 'Lesson', 'custom-lms' );
		$quiz_title   = $quiz->post_title;
		$passed       = ( 'passed' === $status );

		$subject = sprintf(
			/* translators: 1: status, 2: quiz title */
			__( '[%1$s] Your Quiz Results: %2$s', 'custom-lms' ),
			$passed ? __( 'PASSED', 'custom-lms' ) : __( 'FAILED', 'custom-lms' ),
			$quiz_title
		);

		$badge_color = $passed ? '#15803d' : '#b91c1c';
		$badge_bg    = $passed ? '#dcfce7' : '#fee2e2';
		$badge_text  = $passed ? __( 'PASSED', 'custom-lms' ) : __( 'FAILED', 'custom-lms' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head><meta charset="utf-8"></head>
		<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1e293b;line-height:1.6;background:#f8fafc;padding:24px;">
			<div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;">
				<h2 style="margin-top:0;color:#0f172a;font-size:22px;border-bottom:1px solid #e2e8f0;padding-bottom:14px;">
					<?php esc_html_e( 'Quiz Completion Report', 'custom-lms' ); ?>
				</h2>

				<p><?php echo esc_html( sprintf( __( 'Hello %s,', 'custom-lms' ), $user->display_name ) ); ?></p>
				<p><?php esc_html_e( 'Here is your submission report for your recent lesson quiz:', 'custom-lms' ); ?></p>

				<table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border-radius:8px;overflow:hidden;">
					<tr>
						<td style="padding:10px 14px;font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Course:', 'custom-lms' ); ?></td>
						<td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><?php echo esc_html( $course_title ); ?></td>
					</tr>
					<tr>
						<td style="padding:10px 14px;font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Lesson:', 'custom-lms' ); ?></td>
						<td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><?php echo esc_html( $lesson_title ); ?></td>
					</tr>
					<tr>
						<td style="padding:10px 14px;font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Quiz Title:', 'custom-lms' ); ?></td>
						<td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;"><?php echo esc_html( $quiz_title ); ?></td>
					</tr>
					<tr>
						<td style="padding:10px 14px;font-weight:600;border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Score:', 'custom-lms' ); ?></td>
						<td style="padding:10px 14px;border-bottom:1px solid #e2e8f0;">
							<strong><?php echo esc_html( "{$obtained} / {$total} ({$percent}%)" ); ?></strong>
							(<?php echo esc_html( sprintf( __( 'Passing Mark: %d%%', 'custom-lms' ), $pass_percent ) ); ?>)
						</td>
					</tr>
					<tr>
						<td style="padding:10px 14px;font-weight:600;"><?php esc_html_e( 'Result:', 'custom-lms' ); ?></td>
						<td style="padding:10px 14px;">
							<span style="display:inline-block;padding:4px 12px;background:<?php echo esc_attr( $badge_bg ); ?>;color:<?php echo esc_attr( $badge_color ); ?>;border-radius:9999px;font-weight:700;font-size:12px;">
								<?php echo esc_html( $badge_text ); ?>
							</span>
						</td>
					</tr>
				</table>

				<h3 style="font-size:16px;color:#0f172a;margin-top:24px;"><?php esc_html_e( 'Question Breakdown:', 'custom-lms' ); ?></h3>
				<div style="margin-top:12px;">
					<?php foreach ( $log as $idx => $item ) : ?>
						<div style="margin-bottom:14px;padding:12px 14px;border-left:3px solid <?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;background:#f8fafc;border-radius:0 6px 6px 0;">
							<div style="font-weight:600;color:#1e293b;">
								<?php echo esc_html( ( $idx + 1 ) . '. ' . $item['question'] ); ?>
							</div>
							<div style="font-size:13px;margin-top:4px;color:<?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;">
								<?php esc_html_e( 'Your Answer:', 'custom-lms' ); ?> <strong><?php echo esc_html( $item['user_answer'] ); ?></strong>
								<?php if ( ! $item['is_correct'] && ! empty( $item['correct_answer'] ) ) : ?>
									<span style="color:#64748b;margin-left:8px;">(<?php esc_html_e( 'Correct:', 'custom-lms' ); ?> <?php echo esc_html( $item['correct_answer'] ); ?>)</span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $item['explanation'] ) ) : ?>
								<div style="font-size:12px;color:#64748b;margin-top:4px;font-style:italic;">
									💡 <?php echo esc_html( $item['explanation'] ); ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<p style="margin-top:28px;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:16px;">
					<?php esc_html_e( 'Keep learning and continue to the next lesson on your student dashboard.', 'custom-lms' ); ?>
				</p>
			</div>
		</body>
		</html>
		<?php
		$message = ob_get_clean();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $user->user_email, $subject, $message, $headers );
	}

	/**
	 * Render Quiz Attempts Admin Page.
	 */
	public static function render_admin_attempts_page() {
		global $wpdb;
		$table = CLMS_DB::get_quiz_attempts_table();

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$where  = 'WHERE 1=1';
		if ( $search ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where      .= $wpdb->prepare(
				" AND (user_email LIKE %s OR user_id IN (SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s)
				   OR quiz_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s))",
				$search_like,
				$search_like,
				$search_like
			);
		}

		$attempts = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY submitted_at DESC LIMIT 100" );

		// View single attempt modal/detail if requested
		$detail_id = isset( $_GET['view_attempt'] ) ? absint( $_GET['view_attempt'] ) : 0;
		$single_attempt = null;
		if ( $detail_id ) {
			$single_attempt = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $detail_id ) );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quiz Attempts & Results', 'custom-lms' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['retake_granted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><strong>✓ <?php esc_html_e( 'Retake Permission Granted!', 'custom-lms' ); ?></strong> <?php esc_html_e( 'The student has been authorized to submit 1 additional attempt for this quiz.', 'custom-lms' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['retake_revoked'] ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e( 'Retake permission has been revoked. The quiz remains locked for this student.', 'custom-lms' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['attempts_reset'] ) ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e( 'All previous quiz attempts have been reset for this student.', 'custom-lms' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Quick Action: Grant Quiz Retake to Student -->
			<div class="card" style="max-width:100%;margin:18px 0 24px 0;padding:16px 22px;border-left:4px solid #2563eb;background:#ffffff;">
				<h3 style="margin:0 0 6px 0;font-size:15px;color:#0f172a;display:flex;align-items:center;gap:6px;">
					⚡ <?php esc_html_e( 'Authorize Student Quiz Retake', 'custom-lms' ); ?>
				</h3>
				<p style="margin:0 0 14px 0;color:#64748b;font-size:13px;">
					<?php esc_html_e( 'Select any student and lesson quiz to grant permission for an additional attempt.', 'custom-lms' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
					<input type="hidden" name="action" value="clms_quick_grant_retake" />
					<?php wp_nonce_field( 'clms_quick_grant_retake_action', 'clms_retake_nonce' ); ?>
					
					<select name="user_id" required style="min-width:240px;height:36px;">
						<option value=""><?php esc_html_e( '— Select Student —', 'custom-lms' ); ?></option>
						<?php
						$all_students = get_users( array( 'number' => 250, 'orderby' => 'display_name' ) );
						foreach ( $all_students as $st_u ) :
						?>
							<option value="<?php echo esc_attr( $st_u->ID ); ?>">
								<?php echo esc_html( $st_u->display_name . ' (' . $st_u->user_email . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<select name="quiz_id" required style="min-width:260px;height:36px;">
						<option value=""><?php esc_html_e( '— Select Quiz —', 'custom-lms' ); ?></option>
						<?php
						$all_quizzes = get_posts(
							array(
								'post_type'      => 'clms_quiz',
								'post_status'    => 'publish',
								'posts_per_page' => -1,
								'orderby'        => 'title',
								'order'          => 'ASC',
							)
						);
						foreach ( $all_quizzes as $aq ) :
							$l_id = get_post_meta( $aq->ID, '_clms_lesson_id', true );
							$l_title = $l_id ? get_the_title( $l_id ) : '';
						?>
							<option value="<?php echo esc_attr( $aq->ID ); ?>">
								<?php echo esc_html( $aq->post_title . ( $l_title ? ' [' . $l_title . ']' : '' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button type="submit" class="button button-primary" style="height:36px;display:inline-flex;align-items:center;gap:6px;">
						🔓 <?php esc_html_e( 'Grant Retake (+1 Attempt)', 'custom-lms' ); ?>
					</button>
				</form>
			</div>

			<?php if ( $single_attempt ) : ?>
				<?php
				$s_user = get_userdata( $single_attempt->user_id );
				$s_quiz = get_post( $single_attempt->quiz_id );
				$s_user      = get_userdata( $single_attempt->user_id );
				$s_quiz      = get_post( $single_attempt->quiz_id );
				$answers_log = json_decode( $single_attempt->answers_data, true );
				$s_stats     = self::get_attempt_stats( $single_attempt->user_id, $single_attempt->quiz_id );
				?>
				<div class="card" style="max-width:850px;margin:20px 0;padding:20px 24px;">
					<h2><?php esc_html_e( 'Attempt Details', 'custom-lms' ); ?> #<?php echo esc_html( $single_attempt->id ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Student:', 'custom-lms' ); ?></strong> <?php echo esc_html( $s_user ? $s_user->display_name : '#' . $single_attempt->user_id ); ?> (<?php echo esc_html( $single_attempt->user_email ); ?>)<br>
						<strong><?php esc_html_e( 'Quiz:', 'custom-lms' ); ?></strong> <?php echo esc_html( $s_quiz ? $s_quiz->post_title : '#' . $single_attempt->quiz_id ); ?><br>
						<strong><?php esc_html_e( 'Score:', 'custom-lms' ); ?></strong> <?php echo esc_html( "{$single_attempt->obtained_marks} / {$single_attempt->total_marks} ({$single_attempt->score_percent}%)" ); ?><br>
						<strong><?php esc_html_e( 'Result:', 'custom-lms' ); ?></strong> 
						<span style="font-weight:700;color:<?php echo 'passed' === $single_attempt->status ? '#15803d' : '#b91c1c'; ?>;">
							<?php echo esc_html( strtoupper( $single_attempt->status ) ); ?>
						</span><br>
						<strong><?php esc_html_e( 'Date:', 'custom-lms' ); ?></strong> <?php echo esc_html( $single_attempt->submitted_at ); ?>
					</p>

					<!-- Retake Management for this user -->
					<div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:16px 20px;margin:20px 0;">
						<h3 style="margin-top:0;font-size:15px;color:#0f172a;">🎯 <?php esc_html_e( 'Student Quiz Retake Authorization', 'custom-lms' ); ?></h3>
						<p style="margin-bottom:12px;">
							<strong><?php esc_html_e( 'Attempts Completed:', 'custom-lms' ); ?></strong> <?php echo esc_html( $s_stats['attempts_count'] ); ?><br>
							<strong><?php esc_html_e( 'Total Attempts Allowed:', 'custom-lms' ); ?></strong> <?php echo esc_html( $s_stats['total_allowed'] ); ?><br>
							<strong><?php esc_html_e( 'Current Status:', 'custom-lms' ); ?></strong>
							<?php if ( $s_stats['can_attempt'] ) : ?>
								<span style="color:#15803d;font-weight:700;">🔓 <?php esc_html_e( 'Unlocked: Student can retake this quiz now', 'custom-lms' ); ?></span>
							<?php else : ?>
								<span style="color:#b91c1c;font-weight:700;">🔒 <?php esc_html_e( 'Completed & Locked: Retakes prohibited without admin permission', 'custom-lms' ); ?></span>
							<?php endif; ?>
						</p>
						<div style="display:flex;gap:10px;align-items:center;">
							<?php if ( ! $s_stats['can_attempt'] ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_grant_retake&user_id=' . $single_attempt->user_id . '&quiz_id=' . $single_attempt->quiz_id . '&view_attempt=' . $single_attempt->id ), 'clms_retake_action' ) ); ?>" class="button button-primary">
									🔓 <?php esc_html_e( 'Authorize Another Attempt (+1 Retake)', 'custom-lms' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_revoke_retake&user_id=' . $single_attempt->user_id . '&quiz_id=' . $single_attempt->quiz_id . '&view_attempt=' . $single_attempt->id ), 'clms_retake_action' ) ); ?>" class="button" style="color:#b91c1c;">
									🔒 <?php esc_html_e( 'Revoke Retake Authorization', 'custom-lms' ); ?>
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_reset_attempts&user_id=' . $single_attempt->user_id . '&quiz_id=' . $single_attempt->quiz_id ), 'clms_retake_action' ) ); ?>" class="button" onclick="return confirm('Warning: This will delete all attempt records for this student on this quiz. Proceed?');" style="color:#dc2626;">
								🗑️ <?php esc_html_e( 'Reset All Attempts', 'custom-lms' ); ?>
							</a>
						</div>
					</div>

					<h3><?php esc_html_e( 'Questions & Answers Log', 'custom-lms' ); ?></h3>
					<?php if ( ! empty( $answers_log ) && is_array( $answers_log ) ) : ?>
						<?php foreach ( $answers_log as $i => $item ) : ?>
							<div style="margin-bottom:12px;padding:12px;background:#f8fafc;border-left:4px solid <?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;border-radius:0 6px 6px 0;">
								<strong><?php echo esc_html( ( $i + 1 ) . '. ' . $item['question'] ); ?></strong>
								<div style="font-size:13px;margin-top:4px;">
									<span style="color:<?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;">
										<?php esc_html_e( 'Selected:', 'custom-lms' ); ?> <strong><?php echo esc_html( $item['user_answer'] ); ?></strong>
									</span>
									<?php if ( ! $item['is_correct'] ) : ?>
										<span style="margin-left:10px;color:#64748b;">(<?php esc_html_e( 'Correct:', 'custom-lms' ); ?> <?php echo esc_html( $item['correct_answer'] ); ?>)</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to All Attempts', 'custom-lms' ); ?></a></p>
				</div>
			<?php endif; ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
				<input type="hidden" name="post_type" value="clms_course" />
				<input type="hidden" name="page" value="clms-quiz-attempts" />
				<p class="search-box">
					<label class="screen-reader-text" for="attempt-search-input"><?php esc_html_e( 'Search Attempts:', 'custom-lms' ); ?></label>
					<input type="search" id="attempt-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'custom-lms' ); ?>">
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Quiz / Lesson / Course', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Attempt #', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Score (Marks)', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Date & Time', 'custom-lms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'custom-lms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $attempts ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No quiz attempts recorded yet.', 'custom-lms' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $attempts as $att ) : ?>
							<?php
							$u = get_userdata( $att->user_id );
							$q = get_post( $att->quiz_id );
							$l = get_post( $att->lesson_id );
							$c = get_post( $att->course_id );
							$row_stats = self::get_attempt_stats( $att->user_id, $att->quiz_id );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $u ? $u->display_name : '#' . $att->user_id ); ?></strong><br>
									<span style="color:#64748b;font-size:12px;"><?php echo esc_html( $att->user_email ); ?></span>
								</td>
								<td>
									<strong><?php echo esc_html( $q ? $q->post_title : '#' . $att->quiz_id ); ?></strong><br>
									<span style="color:#64748b;font-size:12px;">
										<?php echo esc_html( $l ? $l->post_title : '' ); ?> • <?php echo esc_html( $c ? $c->post_title : '' ); ?>
									</span>
								</td>
								<td>#<?php echo esc_html( $att->attempt_number ); ?></td>
								<td>
									<strong><?php echo esc_html( "{$att->obtained_marks} / {$att->total_marks}" ); ?></strong>
									(<?php echo esc_html( $att->score_percent ); ?>%)
								</td>
								<td>
									<?php if ( 'passed' === $att->status ) : ?>
										<span style="color:#15803d;font-weight:700;"><?php esc_html_e( 'PASSED', 'custom-lms' ); ?></span>
									<?php else : ?>
										<span style="color:#b91c1c;font-weight:700;"><?php esc_html_e( 'FAILED', 'custom-lms' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $att->submitted_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( 'view_attempt', $att->id, admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts' ) ) ); ?>" class="button button-small">
										<?php esc_html_e( 'View Details', 'custom-lms' ); ?>
									</a>
									<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
										<a href="<?php echo esc_url( add_query_arg( 'view_attempt', $att->id, admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts' ) ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'custom-lms' ); ?>
										</a>
										<?php if ( $row_stats['can_attempt'] ) : ?>
											<span style="display:inline-block;padding:2px 7px;background:#dcfce7;color:#15803d;border-radius:4px;font-size:11px;font-weight:700;">
												✓ <?php esc_html_e( 'Retake Allowed', 'custom-lms' ); ?>
											</span>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_revoke_retake&user_id=' . $att->user_id . '&quiz_id=' . $att->quiz_id ), 'clms_retake_action' ) ); ?>" class="button button-small" style="color:#b91c1c;" onclick="return confirm('Revoke retake permission for this student?');" title="<?php esc_attr_e( 'Revoke unlocked retake', 'custom-lms' ); ?>">
												✕
											</a>
										<?php else : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_grant_retake&user_id=' . $att->user_id . '&quiz_id=' . $att->quiz_id ), 'clms_retake_action' ) ); ?>" class="button button-small button-primary">
												🔓 <?php esc_html_e( 'Allow Retake', 'custom-lms' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
