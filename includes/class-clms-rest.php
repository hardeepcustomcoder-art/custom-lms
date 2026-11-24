<?php
/**
 * REST API routes and endpoints for Custom LMS.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Rest
 */
class CLMS_Rest {

	/**
	 * Namespace for Custom LMS endpoints.
	 */
	const NAMESPACE = 'custom-lms/v1';

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		// 1. Mark topic as complete.
		register_rest_route(
			self::NAMESPACE,
			'/topics/(?P<id>\d+)/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_complete_topic' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// 2. Submit quiz for server-side evaluation.
		register_rest_route(
			self::NAMESPACE,
			'/quizzes/(?P<id>\d+)/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_submit_quiz' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Check if current user is logged in.
	 *
	 * @return bool
	 */
	public static function permissions_check() {
		return is_user_logged_in();
	}

	/**
	 * Handle topic completion.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public static function handle_complete_topic( $request ) {
		$topic_id = absint( $request->get_param( 'id' ) );
		$user_id  = get_current_user_id();

		$topic = get_post( $topic_id );
		if ( ! $topic || 'clms_topic' !== $topic->post_type ) {
			return new WP_REST_Response( array( 'message' => __( 'Invalid topic.', 'custom-lms' ) ), 404 );
		}

		if ( ! CLMS_Access::can_view_topic( $user_id, $topic_id ) ) {
			return new WP_REST_Response( array( 'message' => __( 'You do not have access to complete this topic.', 'custom-lms' ) ), 403 );
		}

		// Mark complete using helper.
		$res = CLMS_Progress::mark_complete( $user_id, $topic_id, 'topic', null, 'completed' );

		$course_id = absint( get_post_meta( $topic_id, '_clms_course_id', true ) );
		$progress_pct = CLMS_Progress::get_course_progress_percent( $user_id, $course_id );

		return new WP_REST_Response(
			array(
				'success'  => true,
				'message'  => __( 'Topic marked as complete!', 'custom-lms' ),
				'progress' => $progress_pct,
			),
			200
		);
	}

	/**
	 * Handle quiz submission and server-side scoring.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_submit_quiz( $request ) {
		$quiz_id = absint( $request->get_param( 'id' ) );
		$user_id = get_current_user_id();
		$answers = $request->get_param( 'answers' ); // Array: [ question_id => chosen_index ]

		if ( ! is_array( $answers ) ) {
			$answers = array();
		}

		$result = CLMS_Quiz::process_submission( $user_id, $quiz_id, $answers );

		if ( ! $result['success'] ) {
			return new WP_REST_Response( array( 'message' => $result['message'] ), 400 );
		}

		$course_id    = absint( get_post_meta( $quiz_id, '_clms_course_id', true ) );
		$progress_pct = CLMS_Progress::get_course_progress_percent( $user_id, $course_id );
		$result['progress'] = $progress_pct;

		return new WP_REST_Response( $result, 200 );
	}
}
