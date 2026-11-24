<?php
/**
 * Register custom post types and post meta.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Post_Types
 */
class CLMS_Post_Types {

	/**
	 * Hook registrations into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 10 );
	}

	/**
	 * Register LMS Custom Post Types.
	 */
	public static function register_post_types() {
		// 1. Course.
		$course_labels = array(
			'name'                  => _x( 'Courses', 'Post type general name', 'custom-lms' ),
			'singular_name'         => _x( 'Course', 'Post type singular name', 'custom-lms' ),
			'menu_name'             => _x( 'Custom LMS', 'Admin Menu text', 'custom-lms' ),
			'name_admin_bar'        => _x( 'Course', 'Add New on Toolbar', 'custom-lms' ),
			'add_new'               => __( 'Add New Course', 'custom-lms' ),
			'add_new_item'          => __( 'Add New Course', 'custom-lms' ),
			'new_item'              => __( 'New Course', 'custom-lms' ),
			'edit_item'             => __( 'Edit Course', 'custom-lms' ),
			'view_item'             => __( 'View Course', 'custom-lms' ),
			'all_items'             => __( 'Courses', 'custom-lms' ),
			'search_items'          => __( 'Search Courses', 'custom-lms' ),
			'parent_item_colon'     => __( 'Parent Courses:', 'custom-lms' ),
			'not_found'             => __( 'No courses found.', 'custom-lms' ),
			'not_found_in_trash'    => __( 'No courses found in Trash.', 'custom-lms' ),
			'featured_image'        => _x( 'Course Cover Image', 'Overrides the "Featured Image" phrase', 'custom-lms' ),
			'set_featured_image'    => _x( 'Set course cover image', 'Overrides the "Set featured image" phrase', 'custom-lms' ),
			'remove_featured_image' => _x( 'Remove course cover image', 'Overrides the "Remove featured image" phrase', 'custom-lms' ),
			'use_featured_image'    => _x( 'Use as course cover image', 'Overrides the "Use as featured image" phrase', 'custom-lms' ),
		);

		$course_args = array(
			'labels'             => $course_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'courses',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 30,
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'show_in_rest'       => true,
		);
		register_post_type( 'clms_course', $course_args );

		// 2. Lesson.
		$lesson_labels = array(
			'name'               => _x( 'Lessons', 'Post type general name', 'custom-lms' ),
			'singular_name'      => _x( 'Lesson', 'Post type singular name', 'custom-lms' ),
			'menu_name'          => _x( 'Lessons', 'Admin Menu text', 'custom-lms' ),
			'name_admin_bar'     => _x( 'Lesson', 'Add New on Toolbar', 'custom-lms' ),
			'add_new'            => __( 'Add New Lesson', 'custom-lms' ),
			'add_new_item'       => __( 'Add New Lesson', 'custom-lms' ),
			'new_item'           => __( 'New Lesson', 'custom-lms' ),
			'edit_item'          => __( 'Edit Lesson', 'custom-lms' ),
			'view_item'          => __( 'View Lesson', 'custom-lms' ),
			'all_items'          => __( 'Lessons', 'custom-lms' ),
			'search_items'       => __( 'Search Lessons', 'custom-lms' ),
			'not_found'          => __( 'No lessons found.', 'custom-lms' ),
			'not_found_in_trash' => __( 'No lessons found in Trash.', 'custom-lms' ),
		);

		$lesson_args = array(
			'labels'             => $lesson_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=clms_course',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'lessons',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'show_in_rest'       => true,
		);
		register_post_type( 'clms_lesson', $lesson_args );

		// 3. Topic.
		$topic_labels = array(
			'name'               => _x( 'Topics', 'Post type general name', 'custom-lms' ),
			'singular_name'      => _x( 'Topic', 'Post type singular name', 'custom-lms' ),
			'menu_name'          => _x( 'Topics', 'Admin Menu text', 'custom-lms' ),
			'name_admin_bar'     => _x( 'Topic', 'Add New on Toolbar', 'custom-lms' ),
			'add_new'            => __( 'Add New Topic', 'custom-lms' ),
			'add_new_item'       => __( 'Add New Topic', 'custom-lms' ),
			'new_item'           => __( 'New Topic', 'custom-lms' ),
			'edit_item'          => __( 'Edit Topic', 'custom-lms' ),
			'view_item'          => __( 'View Topic', 'custom-lms' ),
			'all_items'          => __( 'Topics', 'custom-lms' ),
			'search_items'       => __( 'Search Topics', 'custom-lms' ),
			'not_found'          => __( 'No topics found.', 'custom-lms' ),
			'not_found_in_trash' => __( 'No topics found in Trash.', 'custom-lms' ),
		);

		$topic_args = array(
			'labels'             => $topic_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=clms_course',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'topics',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'show_in_rest'       => true,
		);
		register_post_type( 'clms_topic', $topic_args );

		// 4. Quiz.
		$quiz_labels = array(
			'name'               => _x( 'Quizzes', 'Post type general name', 'custom-lms' ),
			'singular_name'      => _x( 'Quiz', 'Post type singular name', 'custom-lms' ),
			'menu_name'          => _x( 'Quizzes', 'Admin Menu text', 'custom-lms' ),
			'name_admin_bar'     => _x( 'Quiz', 'Add New on Toolbar', 'custom-lms' ),
			'add_new'            => __( 'Add New Quiz', 'custom-lms' ),
			'add_new_item'       => __( 'Add New Quiz', 'custom-lms' ),
			'new_item'           => __( 'New Quiz', 'custom-lms' ),
			'edit_item'          => __( 'Edit Quiz', 'custom-lms' ),
			'view_item'          => __( 'View Quiz', 'custom-lms' ),
			'all_items'          => __( 'Quizzes', 'custom-lms' ),
			'search_items'       => __( 'Search Quizzes', 'custom-lms' ),
			'not_found'          => __( 'No quizzes found.', 'custom-lms' ),
			'not_found_in_trash' => __( 'No quizzes found in Trash.', 'custom-lms' ),
		);

		$quiz_args = array(
			'labels'             => $quiz_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=clms_course',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'quizzes',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor' ),
			'show_in_rest'       => true,
		);
		register_post_type( 'clms_quiz', $quiz_args );
	}

	/**
	 * Register post meta for each CPT with proper sanitization and REST exposure.
	 */
	public static function register_meta() {
		// --- Course Meta ---
		register_post_meta(
			'clms_course',
			'_clms_price',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'sanitize_callback' => array( __CLASS__, 'sanitize_price' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_course',
			'_clms_wc_product_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_course',
			'_clms_instructor_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		// --- Lesson Meta ---
		register_post_meta(
			'clms_lesson',
			'_clms_course_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_lesson',
			'_clms_order',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		// --- Topic Meta ---
		register_post_meta(
			'clms_topic',
			'_clms_lesson_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_topic',
			'_clms_course_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_topic',
			'_clms_order',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_topic',
			'_clms_video_url',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		// --- Quiz Meta ---
		register_post_meta(
			'clms_quiz',
			'_clms_lesson_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_quiz',
			'_clms_course_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_quiz',
			'_clms_pass_percent',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 70,
				'sanitize_callback' => array( __CLASS__, 'sanitize_pass_percent' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		register_post_meta(
			'clms_quiz',
			'_clms_max_attempts',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);

		// NOTE: _clms_questions must NEVER be exposed via REST (per spec).
		register_post_meta(
			'clms_quiz',
			'_clms_questions',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_json' ),
				'auth_callback'     => array( __CLASS__, 'auth_edit_post_meta' ),
			)
		);
	}

	/**
	 * Meta authorization callback.
	 *
	 * @param bool   $allowed   Whether user is allowed.
	 * @param string $meta_key  Meta key.
	 * @param int    $post_id   Post ID.
	 * @return bool
	 */
	public static function auth_edit_post_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize price value (float >= 0).
	 *
	 * @param mixed $value Input value.
	 * @return float
	 */
	public static function sanitize_price( $value ) {
		$float_val = floatval( $value );
		return $float_val >= 0 ? round( $float_val, 2 ) : 0.00;
	}

	/**
	 * Sanitize pass percent (1-100).
	 *
	 * @param mixed $value Input value.
	 * @return int
	 */
	public static function sanitize_pass_percent( $value ) {
		$val = absint( $value );
		if ( $val < 1 ) {
			return 70;
		}
		if ( $val > 100 ) {
			return 100;
		}
		return $val;
	}

	/**
	 * Sanitize JSON string.
	 *
	 * @param mixed $value Input JSON string.
	 * @return string
	 */
	public static function sanitize_json( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}
		$decoded = json_decode( $value, true );
		if ( null === $decoded ) {
			return '';
		}
		return wp_json_encode( $decoded );
	}
}
