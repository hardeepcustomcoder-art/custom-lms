<?php
/**
 * Register meta boxes and admin columns for Custom LMS.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Meta_Boxes
 */
class CLMS_Meta_Boxes {

	/**
	 * Hook registrations into WordPress.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );

		// Admin list-table columns.
		add_filter( 'manage_clms_lesson_posts_columns', array( __CLASS__, 'lesson_columns' ) );
		add_action( 'manage_clms_lesson_posts_custom_column', array( __CLASS__, 'render_lesson_columns' ), 10, 2 );
		add_filter( 'manage_edit-clms_lesson_sortable_columns', array( __CLASS__, 'lesson_sortable_columns' ) );

		add_filter( 'manage_clms_topic_posts_columns', array( __CLASS__, 'topic_columns' ) );
		add_action( 'manage_clms_topic_posts_custom_column', array( __CLASS__, 'render_topic_columns' ), 10, 2 );
		add_filter( 'manage_edit-clms_topic_sortable_columns', array( __CLASS__, 'topic_sortable_columns' ) );

		add_filter( 'manage_clms_quiz_posts_columns', array( __CLASS__, 'quiz_columns' ) );
		add_action( 'manage_clms_quiz_posts_custom_column', array( __CLASS__, 'render_quiz_columns' ), 10, 2 );

		// Custom column sorting handler.
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_order' ) );

		// Enqueue admin styling for meta boxes and dropdowns.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin styles.
	 */
	public static function enqueue_admin_assets() {
		wp_enqueue_style(
			'clms-admin-meta',
			CLMS_URL . 'admin/css/clms-admin.css',
			array(),
			CLMS_VERSION
		);
	}

	/**
	 * Register meta boxes.
	 */
	public static function add_meta_boxes() {
		// Course settings.
		add_meta_box(
			'clms_course_settings',
			__( 'Course Settings', 'custom-lms' ),
			array( __CLASS__, 'render_course_meta_box' ),
			'clms_course',
			'side',
			'high'
		);

		// Lesson settings.
		add_meta_box(
			'clms_lesson_settings',
			__( 'Lesson Settings', 'custom-lms' ),
			array( __CLASS__, 'render_lesson_meta_box' ),
			'clms_lesson',
			'side',
			'high'
		);

		// Topic settings.
		add_meta_box(
			'clms_topic_settings',
			__( 'Topic Settings', 'custom-lms' ),
			array( __CLASS__, 'render_topic_meta_box' ),
			'clms_topic',
			'side',
			'high'
		);

		// Quiz settings.
		add_meta_box(
			'clms_quiz_settings',
			__( 'Quiz Settings', 'custom-lms' ),
			array( __CLASS__, 'render_quiz_meta_box' ),
			'clms_quiz',
			'side',
			'high'
		);

		// Quiz questions & answers builder.
		add_meta_box(
			'clms_quiz_questions',
			__( 'Quiz Questions & Answers', 'custom-lms' ),
			array( __CLASS__, 'render_quiz_questions_meta_box' ),
			'clms_quiz',
			'normal',
			'high'
		);
	}

	/**
	 * Render Course Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_course_meta_box( $post ) {
		wp_nonce_field( 'clms_save_course_meta', 'clms_course_meta_nonce' );

		$price         = get_post_meta( $post->ID, '_clms_price', true );
		$wc_product_id = get_post_meta( $post->ID, '_clms_wc_product_id', true );
		$instructor_id = get_post_meta( $post->ID, '_clms_instructor_id', true );

		if ( empty( $instructor_id ) ) {
			$instructor_id = $post->post_author;
		}

		$instructors = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'author' ),
				'orderby'  => 'display_name',
			)
		);
		$wc_products = class_exists( 'CLMS_WooCommerce' ) ? CLMS_WooCommerce::get_available_products() : array();
		$wc_price_data = class_exists( 'CLMS_WooCommerce' ) ? CLMS_WooCommerce::get_course_price_data( $post->ID ) : array();
		?>
		<div class="clms-metabox-wrapper">
			<div class="clms-form-group">
				<label for="clms_wc_product_id" class="clms-form-label"><?php esc_html_e( 'Linked WooCommerce Product', 'custom-lms' ); ?></label>
				<select name="clms_wc_product_id" id="clms_wc_product_id" class="clms-select">
					<option value="0"><?php esc_html_e( '— Select WooCommerce Product —', 'custom-lms' ); ?></option>
					<?php foreach ( $wc_products as $prod ) : ?>
						<option value="<?php echo esc_attr( $prod['id'] ); ?>" <?php selected( $wc_product_id, $prod['id'] ); ?> data-price="<?php echo esc_attr( $prod['formatted'] ); ?>">
							<?php echo esc_html( $prod['name'] . ' (' . $prod['formatted'] . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="clms-form-help">
					<?php if ( ! empty( $wc_products ) ) : ?>
						<?php esc_html_e( 'Pricing and checkout will automatically sync with this product.', 'custom-lms' ); ?>
					<?php else : ?>
						<span style="color:#e11d48;"><?php esc_html_e( 'No WooCommerce products found. Create a product in Products → Add New.', 'custom-lms' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<div class="clms-form-group" id="clms_price_display_group">
				<label class="clms-form-label"><?php esc_html_e( 'Active Course Price', 'custom-lms' ); ?></label>
				<?php if ( ! empty( $wc_price_data['product_id'] ) ) : ?>
					<div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:8px 12px;border-radius:6px;">
						<span style="font-weight:700;font-size:15px;color:#166534;"><?php echo esc_html( $wc_price_data['formatted'] ); ?></span>
						<span style="font-size:11px;color:#15803d;margin-left:6px;"><?php esc_html_e( '(Synced dynamically with WooCommerce)', 'custom-lms' ); ?></span>
					</div>
				<?php else : ?>
					<div style="background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;border-radius:6px;color:#64748b;font-size:12px;">
						<?php esc_html_e( 'No product linked. Enter a fallback manual price below:', 'custom-lms' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="clms-form-group" id="clms_manual_price_group" style="<?php echo ( ! empty( $wc_product_id ) && ! empty( $wc_price_data['product_id'] ) ) ? 'display:none;' : ''; ?>">
				<label for="clms_price" class="clms-form-label"><?php esc_html_e( 'Manual Fallback Price ($)', 'custom-lms' ); ?></label>
				<input type="number" step="0.01" min="0" name="clms_price" id="clms_price" value="<?php echo esc_attr( $price ); ?>" class="clms-input clms-input-small" />
				<div class="clms-form-help"><?php esc_html_e( 'Used only if no WooCommerce product is selected.', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_instructor_id" class="clms-form-label"><?php esc_html_e( 'Instructor', 'custom-lms' ); ?></label>
				<select name="clms_instructor_id" id="clms_instructor_id" class="clms-select">
					<?php foreach ( $instructors as $instructor ) : ?>
						<option value="<?php echo esc_attr( $instructor->ID ); ?>" <?php selected( $instructor_id, $instructor->ID ); ?>>
							<?php echo esc_html( $instructor->display_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var prodSelect = document.getElementById('clms_wc_product_id');
			var manualGroup = document.getElementById('clms_manual_price_group');
			if (prodSelect && manualGroup) {
				prodSelect.addEventListener('change', function() {
					if (this.value && this.value !== '0') {
						manualGroup.style.display = 'none';
					} else {
						manualGroup.style.display = 'block';
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render Lesson Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_lesson_meta_box( $post ) {
		wp_nonce_field( 'clms_save_lesson_meta', 'clms_lesson_meta_nonce' );

		$course_id = get_post_meta( $post->ID, '_clms_course_id', true );
		$order     = get_post_meta( $post->ID, '_clms_order', true );

		$courses = get_posts(
			array(
				'post_type'      => 'clms_course',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$assigned_quiz_id = (int) get_post_meta( $post->ID, '_clms_quiz_id', true );
		if ( ! $assigned_quiz_id ) {
			$assigned_quiz    = CLMS_Frontend::get_lesson_quiz( $post->ID );
			$assigned_quiz_id = $assigned_quiz ? $assigned_quiz->ID : 0;
		}

		$quizzes = get_posts(
			array(
				'post_type'      => 'clms_quiz',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="clms-metabox-wrapper">
			<div class="clms-form-group">
				<label for="clms_course_id" class="clms-form-label"><?php esc_html_e( 'Parent Course', 'custom-lms' ); ?></label>
				<select name="clms_course_id" id="clms_course_id" class="clms-select" required>
					<option value=""><?php esc_html_e( '— Select Course —', 'custom-lms' ); ?></option>
					<?php foreach ( $courses as $course ) : ?>
						<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
							<?php echo esc_html( $course->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="clms-form-help"><?php esc_html_e( 'Course this lesson belongs to.', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_order" class="clms-form-label"><?php esc_html_e( 'Lesson Order', 'custom-lms' ); ?></label>
				<input type="number" min="1" step="1" name="clms_order" id="clms_order" value="<?php echo esc_attr( $order ? $order : 1 ); ?>" class="clms-input clms-input-small" />
				<div class="clms-form-help"><?php esc_html_e( 'Display order (1, 2, 3...).', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_quiz_id" class="clms-form-label"><?php esc_html_e( 'Assigned Quiz', 'custom-lms' ); ?></label>
				<select name="clms_quiz_id" id="clms_quiz_id" class="clms-select">
					<option value=""><?php esc_html_e( '— No Quiz Assigned —', 'custom-lms' ); ?></option>
					<?php foreach ( $quizzes as $q ) : ?>
						<option value="<?php echo esc_attr( $q->ID ); ?>" <?php selected( $assigned_quiz_id, $q->ID ); ?>>
							<?php echo esc_html( $q->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="clms-form-help"><?php esc_html_e( 'Quiz that unlocks when students finish all topics in this lesson.', 'custom-lms' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Topic Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_topic_meta_box( $post ) {
		wp_nonce_field( 'clms_save_topic_meta', 'clms_topic_meta_nonce' );

		$lesson_id = get_post_meta( $post->ID, '_clms_lesson_id', true );
		$order     = get_post_meta( $post->ID, '_clms_order', true );
		$video_url = get_post_meta( $post->ID, '_clms_video_url', true );

		$lessons = get_posts(
			array(
				'post_type'      => 'clms_lesson',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Check if this topic is the free preview topic of its course.
		$is_free_preview = CLMS_Access::is_free_topic( $post->ID );
		?>
		<div class="clms-metabox-wrapper">
			<div class="clms-form-group">
				<label for="clms_lesson_id" class="clms-form-label"><?php esc_html_e( 'Parent Lesson', 'custom-lms' ); ?></label>
				<select name="clms_lesson_id" id="clms_lesson_id" class="clms-select" required>
					<option value=""><?php esc_html_e( '— Select Lesson —', 'custom-lms' ); ?></option>
					<?php foreach ( $lessons as $lesson ) : ?>
						<?php
						$p_course_id = get_post_meta( $lesson->ID, '_clms_course_id', true );
						$p_course    = $p_course_id ? get_the_title( $p_course_id ) : __( 'No Course', 'custom-lms' );
						?>
						<option value="<?php echo esc_attr( $lesson->ID ); ?>" <?php selected( $lesson_id, $lesson->ID ); ?>>
							<?php echo esc_html( $lesson->post_title . ' [' . $p_course . ']' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="clms-form-help"><?php esc_html_e( 'Parent course links automatically.', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_order" class="clms-form-label"><?php esc_html_e( 'Topic Order', 'custom-lms' ); ?></label>
				<input type="number" min="1" step="1" name="clms_order" id="clms_order" value="<?php echo esc_attr( $order ? $order : 1 ); ?>" class="clms-input clms-input-small" />
				<?php if ( $is_free_preview ) : ?>
					<div>
						<span class="clms-meta-badge clms-meta-badge-free">
							<?php esc_html_e( '✓ Free Preview (Lowest Order)', 'custom-lms' ); ?>
						</span>
					</div>
				<?php elseif ( ! empty( $lesson_id ) ) : ?>
					<div>
						<span class="clms-meta-badge clms-meta-badge-locked">
							<?php esc_html_e( '🔒 Enrolled Students Only', 'custom-lms' ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="clms-form-group">
				<label for="clms_video_url" class="clms-form-label"><?php esc_html_e( 'Video URL (Optional)', 'custom-lms' ); ?></label>
				<input type="url" name="clms_video_url" id="clms_video_url" value="<?php echo esc_url( $video_url ); ?>" class="clms-input" placeholder="https://..." />
			</div>
		</div>
		<?php
	}

	/**
	 * Render Quiz Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_quiz_meta_box( $post ) {
		wp_nonce_field( 'clms_save_quiz_meta', 'clms_quiz_meta_nonce' );

		$lesson_id     = get_post_meta( $post->ID, '_clms_lesson_id', true );
		$pass_percent  = get_post_meta( $post->ID, '_clms_pass_percent', true );
		$max_attempts  = get_post_meta( $post->ID, '_clms_max_attempts', true );

		if ( '' === $pass_percent ) {
			$pass_percent = 70;
		}
		if ( '' === $max_attempts || false === $max_attempts || null === $max_attempts || 0 === (int) $max_attempts ) {
			$max_attempts = 1;
		}

		$lessons = get_posts(
			array(
				'post_type'      => 'clms_lesson',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="clms-metabox-wrapper">
			<div class="clms-form-group">
				<label for="clms_lesson_id" class="clms-form-label"><?php esc_html_e( 'Parent Lesson', 'custom-lms' ); ?></label>
				<select name="clms_lesson_id" id="clms_lesson_id" class="clms-select" required>
					<option value=""><?php esc_html_e( '— Select Lesson —', 'custom-lms' ); ?></option>
					<?php foreach ( $lessons as $lesson ) : ?>
						<?php
						$p_course_id = get_post_meta( $lesson->ID, '_clms_course_id', true );
						$p_course    = $p_course_id ? get_the_title( $p_course_id ) : __( 'No Course', 'custom-lms' );
						?>
						<option value="<?php echo esc_attr( $lesson->ID ); ?>" <?php selected( $lesson_id, $lesson->ID ); ?>>
							<?php echo esc_html( $lesson->post_title . ' [' . $p_course . ']' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="clms-form-help"><?php esc_html_e( 'Lesson this quiz concludes.', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_pass_percent" class="clms-form-label"><?php esc_html_e( 'Pass Percentage (%)', 'custom-lms' ); ?></label>
				<input type="number" min="1" max="100" name="clms_pass_percent" id="clms_pass_percent" value="<?php echo esc_attr( $pass_percent ); ?>" class="clms-input clms-input-small" />
				<div class="clms-form-help"><?php esc_html_e( 'Default is 70%.', 'custom-lms' ); ?></div>
			</div>

			<div class="clms-form-group">
				<label for="clms_max_attempts" class="clms-form-label"><?php esc_html_e( 'Max Attempts', 'custom-lms' ); ?></label>
				<input type="number" min="1" step="1" name="clms_max_attempts" id="clms_max_attempts" value="<?php echo esc_attr( $max_attempts ); ?>" class="clms-input clms-input-small" />
				<div class="clms-form-help"><?php esc_html_e( 'Default is 1 attempt. Students cannot retake unless authorized by an admin.', 'custom-lms' ); ?></div>
			</div>

			<?php
			global $wpdb;
			$table_att = CLMS_DB::get_quiz_attempts_table();
			$quiz_users = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, user_email, COUNT(*) as attempts_taken, MAX(submitted_at) as last_submitted FROM {$table_att} WHERE quiz_id = %d GROUP BY user_id ORDER BY last_submitted DESC LIMIT 15",
					$post->ID
				)
			);
			if ( ! empty( $quiz_users ) && class_exists( 'CLMS_Quiz' ) ) :
			?>
				<hr style="margin: 18px 0 14px 0; border: 0; border-top: 1px solid #e2e8f0;">
				<div class="clms-form-group">
					<label class="clms-form-label" style="margin-bottom:8px;font-size:12px;font-weight:700;">
						👥 <?php esc_html_e( 'Student Retake Control', 'custom-lms' ); ?>
					</label>
					<div style="font-size:12px;display:flex;flex-direction:column;gap:8px;">
						<?php foreach ( $quiz_users as $qu ) :
							$u_data = get_userdata( $qu->user_id );
							$u_stats = CLMS_Quiz::get_attempt_stats( $qu->user_id, $post->ID );
						?>
							<div style="padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
								<div>
									<strong><?php echo esc_html( $u_data ? $u_data->display_name : '#' . $qu->user_id ); ?></strong>
									<div style="color:#64748b;font-size:11px;"><?php echo esc_html( $qu->attempts_taken ); ?> attempt(s) taken</div>
								</div>
								<div>
									<?php if ( $u_stats['can_attempt'] ) : ?>
										<span style="color:#15803d;font-weight:700;font-size:11px;">✓ Allowed</span>
									<?php else : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_grant_retake&user_id=' . $qu->user_id . '&quiz_id=' . $post->ID . '&redirect_to=' . rawurlencode( get_edit_post_link( $post->ID, 'url' ) ) ), 'clms_retake_action' ) ); ?>" class="button button-small">
											🔓 Allow Retake
										</a>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Quiz Questions & Answers Builder Meta Box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_quiz_questions_meta_box( $post ) {
		$questions_json = get_post_meta( $post->ID, '_clms_questions', true );
		$questions      = ! empty( $questions_json ) ? json_decode( $questions_json, true ) : array();
		if ( ! is_array( $questions ) ) {
			$questions = array();
		}
		?>
		<input type="hidden" name="clms_questions_present" value="1" />
		<style>
			.clms-qb-wrap { margin-top: 8px; }
			.clms-qb-card {
				background: #f8fafc;
				border: 1px solid #cbd5e1;
				border-radius: 8px;
				padding: 16px 18px;
				margin-bottom: 16px;
			}
			.clms-qb-card-head {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 12px;
				padding-bottom: 8px;
				border-bottom: 1px solid #e2e8f0;
			}
			.clms-qb-title { font-weight: 700; font-size: 14px; color: #0f172a; }
			.clms-qb-field { margin-bottom: 12px; }
			.clms-qb-field label { display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 5px; }
			.clms-qb-field input[type="text"],
			.clms-qb-field textarea {
				width: 100%;
				padding: 8px 10px;
				border: 1px solid #cbd5e1;
				border-radius: 6px;
				font-size: 13px;
				box-sizing: border-box;
			}
			.clms-qb-options-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px; }
			.clms-qb-option-row {
				display: flex;
				align-items: center;
				gap: 10px;
				background: #ffffff;
				padding: 6px 10px;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
			}
			.clms-qb-option-row input[type="text"] { flex: 1; }
			.clms-qb-correct-lbl { display: flex !important; align-items: center; gap: 5px; margin: 0 !important; font-weight: 500 !important; font-size: 12px !important; color: #15803d !important; white-space: nowrap; cursor: pointer; }
			.clms-qb-remove-opt {
				background: none;
				border: none;
				color: #b91c1c;
				font-size: 16px;
				font-weight: 700;
				cursor: pointer;
				padding: 2px 6px;
			}
			.clms-qb-empty {
				padding: 18px;
				background: #f8fafc;
				border: 1px dashed #cbd5e1;
				border-radius: 8px;
				color: #64748b;
				text-align: center;
				margin-bottom: 14px;
			}
		</style>

		<div class="clms-qb-wrap">
			<div id="clms-qb-list">
				<?php if ( empty( $questions ) ) : ?>
					<div class="clms-qb-empty" id="clms-qb-empty-msg">
						<?php esc_html_e( 'No questions added yet. Click "+ Add Question" below to create quiz questions and answers.', 'custom-lms' ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( $questions as $q_idx => $q ) : ?>
						<?php
						$q_text        = isset( $q['text'] ) ? $q['text'] : '';
						$q_options     = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : array( '', '' );
						$correct_index = isset( $q['correct_index'] ) ? (int) $q['correct_index'] : 0;
						$explanation   = isset( $q['explanation'] ) ? $q['explanation'] : '';
						?>
						<div class="clms-qb-card" data-q-index="<?php echo esc_attr( $q_idx ); ?>">
							<div class="clms-qb-card-head">
								<span class="clms-qb-title"><?php echo esc_html( sprintf( __( 'Question #%d', 'custom-lms' ), $q_idx + 1 ) ); ?></span>
								<button type="button" class="button button-link-delete clms-qb-remove-q"><?php esc_html_e( 'Remove Question', 'custom-lms' ); ?></button>
							</div>

							<div class="clms-qb-field">
								<label><?php esc_html_e( 'Question Text', 'custom-lms' ); ?></label>
								<input type="text" name="clms_questions[<?php echo esc_attr( $q_idx ); ?>][text]" value="<?php echo esc_attr( $q_text ); ?>" placeholder="<?php esc_attr_e( 'Enter your question here...', 'custom-lms' ); ?>" required />
							</div>

							<div class="clms-qb-field">
								<label><?php esc_html_e( 'Answer Options (select the correct answer)', 'custom-lms' ); ?></label>
								<div class="clms-qb-options-list">
									<?php foreach ( $q_options as $opt_idx => $opt_text ) : ?>
										<div class="clms-qb-option-row">
											<label class="clms-qb-correct-lbl">
												<input type="radio" name="clms_questions[<?php echo esc_attr( $q_idx ); ?>][correct_index]" value="<?php echo esc_attr( $opt_idx ); ?>" <?php checked( $correct_index, $opt_idx ); ?> />
												<?php esc_html_e( 'Correct', 'custom-lms' ); ?>
											</label>
											<input type="text" name="clms_questions[<?php echo esc_attr( $q_idx ); ?>][options][]" value="<?php echo esc_attr( $opt_text ); ?>" placeholder="<?php esc_attr_e( 'Answer option...', 'custom-lms' ); ?>" required />
											<button type="button" class="clms-qb-remove-opt" title="<?php esc_attr_e( 'Remove option', 'custom-lms' ); ?>">&times;</button>
										</div>
									<?php endforeach; ?>
								</div>
								<button type="button" class="button button-small clms-qb-add-opt">+ <?php esc_html_e( 'Add Option', 'custom-lms' ); ?></button>
							</div>

							<div class="clms-qb-field" style="margin-bottom:0;">
								<label><?php esc_html_e( 'Explanation (Optional — shown after submission)', 'custom-lms' ); ?></label>
								<input type="text" name="clms_questions[<?php echo esc_attr( $q_idx ); ?>][explanation]" value="<?php echo esc_attr( $explanation ); ?>" placeholder="<?php esc_attr_e( 'Briefly explain why the correct answer is right...', 'custom-lms' ); ?>" />
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<button type="button" id="clms-qb-add-q" class="button button-primary">
				+ <?php esc_html_e( 'Add Question', 'custom-lms' ); ?>
			</button>
		</div>

		<script>
		(function () {
			var list = document.getElementById('clms-qb-list');
			var addQBtn = document.getElementById('clms-qb-add-q');
			if (!list || !addQBtn) return;

			function reindexQuestions() {
				var cards = list.querySelectorAll('.clms-qb-card');
				var emptyMsg = document.getElementById('clms-qb-empty-msg');
				if (emptyMsg) {
					emptyMsg.style.display = cards.length ? 'none' : 'block';
				}
				cards.forEach(function (card, qIdx) {
					card.setAttribute('data-q-index', qIdx);
					var title = card.querySelector('.clms-qb-title');
					if (title) title.textContent = 'Question #' + (qIdx + 1);

					var qInput = card.querySelector('input[name*="[text]"]');
					if (qInput) qInput.name = 'clms_questions[' + qIdx + '][text]';

					var expInput = card.querySelector('input[name*="[explanation]"]');
					if (expInput) expInput.name = 'clms_questions[' + qIdx + '][explanation]';

					var optRows = card.querySelectorAll('.clms-qb-option-row');
					optRows.forEach(function (row, oIdx) {
						var radio = row.querySelector('input[type="radio"]');
						var text = row.querySelector('input[type="text"]');
						if (radio) {
							radio.name = 'clms_questions[' + qIdx + '][correct_index]';
							radio.value = oIdx;
						}
						if (text) {
							text.name = 'clms_questions[' + qIdx + '][options][]';
						}
					});
				});
			}

			function createOptionRow(qIdx, oIdx, checked) {
				var row = document.createElement('div');
				row.className = 'clms-qb-option-row';
				row.innerHTML =
					'<label class="clms-qb-correct-lbl">' +
						'<input type="radio" name="clms_questions[' + qIdx + '][correct_index]" value="' + oIdx + '" ' + (checked ? 'checked' : '') + ' /> Correct' +
					'</label>' +
					'<input type="text" name="clms_questions[' + qIdx + '][options][]" value="" placeholder="Answer option..." required />' +
					'<button type="button" class="clms-qb-remove-opt" title="Remove option">&times;</button>';
				return row;
			}

			addQBtn.addEventListener('click', function () {
				var qIdx = list.querySelectorAll('.clms-qb-card').length;
				var card = document.createElement('div');
				card.className = 'clms-qb-card';
				card.setAttribute('data-q-index', qIdx);
				card.innerHTML =
					'<div class="clms-qb-card-head">' +
						'<span class="clms-qb-title">Question #' + (qIdx + 1) + '</span>' +
						'<button type="button" class="button button-link-delete clms-qb-remove-q">Remove Question</button>' +
					'</div>' +
					'<div class="clms-qb-field">' +
						'<label>Question Text</label>' +
						'<input type="text" name="clms_questions[' + qIdx + '][text]" value="" placeholder="Enter your question here..." required />' +
					'</div>' +
					'<div class="clms-qb-field">' +
						'<label>Answer Options (select the correct answer)</label>' +
						'<div class="clms-qb-options-list"></div>' +
						'<button type="button" class="button button-small clms-qb-add-opt">+ Add Option</button>' +
					'</div>' +
					'<div class="clms-qb-field" style="margin-bottom:0;">' +
						'<label>Explanation (Optional — shown after submission)</label>' +
						'<input type="text" name="clms_questions[' + qIdx + '][explanation]" value="" placeholder="Briefly explain why the correct answer is right..." />' +
					'</div>';

				var optList = card.querySelector('.clms-qb-options-list');
				for (var i = 0; i < 4; i++) {
					optList.appendChild(createOptionRow(qIdx, i, i === 0));
				}

				list.appendChild(card);
				reindexQuestions();
			});

			list.addEventListener('click', function (e) {
				if (e.target.classList.contains('clms-qb-remove-q')) {
					var card = e.target.closest('.clms-qb-card');
					if (card) {
						card.remove();
						reindexQuestions();
					}
				} else if (e.target.classList.contains('clms-qb-add-opt')) {
					var card = e.target.closest('.clms-qb-card');
					var qIdx = parseInt(card.getAttribute('data-q-index'), 10) || 0;
					var optList = card.querySelector('.clms-qb-options-list');
					var oIdx = optList.querySelectorAll('.clms-qb-option-row').length;
					optList.appendChild(createOptionRow(qIdx, oIdx, false));
					reindexQuestions();
				} else if (e.target.classList.contains('clms-qb-remove-opt')) {
					var card = e.target.closest('.clms-qb-card');
					var optList = card.querySelector('.clms-qb-options-list');
					if (optList.querySelectorAll('.clms-qb-option-row').length > 2) {
						e.target.closest('.clms-qb-option-row').remove();
						reindexQuestions();
					} else {
						alert('Each question must have at least 2 answer options.');
					}
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta_boxes( $post_id, $post ) {
		// Skip autosaves or revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// 1. Course.
		if ( 'clms_course' === $post->post_type ) {
			if ( ! isset( $_POST['clms_course_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['clms_course_meta_nonce'] ), 'clms_save_course_meta' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( isset( $_POST['clms_price'] ) ) {
				$price = CLMS_Post_Types::sanitize_price( wp_unslash( $_POST['clms_price'] ) );
				update_post_meta( $post_id, '_clms_price', $price );
			}
			if ( isset( $_POST['clms_wc_product_id'] ) ) {
				update_post_meta( $post_id, '_clms_wc_product_id', absint( wp_unslash( $_POST['clms_wc_product_id'] ) ) );
			}
			if ( isset( $_POST['clms_instructor_id'] ) ) {
				update_post_meta( $post_id, '_clms_instructor_id', absint( wp_unslash( $_POST['clms_instructor_id'] ) ) );
			}
		}

		// 2. Lesson.
		if ( 'clms_lesson' === $post->post_type ) {
			if ( ! isset( $_POST['clms_lesson_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['clms_lesson_meta_nonce'] ), 'clms_save_lesson_meta' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( isset( $_POST['clms_course_id'] ) ) {
				update_post_meta( $post_id, '_clms_course_id', absint( wp_unslash( $_POST['clms_course_id'] ) ) );
			}
			if ( isset( $_POST['clms_order'] ) ) {
				$order = absint( wp_unslash( $_POST['clms_order'] ) );
				update_post_meta( $post_id, '_clms_order', $order > 0 ? $order : 1 );
			}
			if ( isset( $_POST['clms_quiz_id'] ) ) {
				$new_quiz_id = absint( wp_unslash( $_POST['clms_quiz_id'] ) );
				$old_quiz_id = absint( get_post_meta( $post_id, '_clms_quiz_id', true ) );

				if ( $old_quiz_id && $old_quiz_id !== $new_quiz_id ) {
					delete_post_meta( $old_quiz_id, '_clms_lesson_id' );
				}

				if ( $new_quiz_id ) {
					update_post_meta( $post_id, '_clms_quiz_id', $new_quiz_id );
					update_post_meta( $new_quiz_id, '_clms_lesson_id', $post_id );
					$c_id = absint( get_post_meta( $post_id, '_clms_course_id', true ) );
					if ( $c_id ) {
						update_post_meta( $new_quiz_id, '_clms_course_id', $c_id );
					}
				} else {
					delete_post_meta( $post_id, '_clms_quiz_id' );
				}
			}
		}

		// 3. Topic.
		if ( 'clms_topic' === $post->post_type ) {
			if ( ! isset( $_POST['clms_topic_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['clms_topic_meta_nonce'] ), 'clms_save_topic_meta' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$lesson_id = isset( $_POST['clms_lesson_id'] ) ? absint( wp_unslash( $_POST['clms_lesson_id'] ) ) : 0;
			update_post_meta( $post_id, '_clms_lesson_id', $lesson_id );

			// Automatically derive parent course_id from lesson on save (per spec).
			$derived_course_id = $lesson_id ? absint( get_post_meta( $lesson_id, '_clms_course_id', true ) ) : 0;
			update_post_meta( $post_id, '_clms_course_id', $derived_course_id );

			if ( isset( $_POST['clms_order'] ) ) {
				$order = absint( wp_unslash( $_POST['clms_order'] ) );
				update_post_meta( $post_id, '_clms_order', $order > 0 ? $order : 1 );
			}
			if ( isset( $_POST['clms_video_url'] ) ) {
				update_post_meta( $post_id, '_clms_video_url', esc_url_raw( wp_unslash( $_POST['clms_video_url'] ) ) );
			}
		}

		// 4. Quiz.
		if ( 'clms_quiz' === $post->post_type ) {
			if ( ! isset( $_POST['clms_quiz_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['clms_quiz_meta_nonce'] ), 'clms_save_quiz_meta' ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$lesson_id     = isset( $_POST['clms_lesson_id'] ) ? absint( wp_unslash( $_POST['clms_lesson_id'] ) ) : 0;
			$old_lesson_id = absint( get_post_meta( $post_id, '_clms_lesson_id', true ) );

			if ( $old_lesson_id && $old_lesson_id !== $lesson_id ) {
				delete_post_meta( $old_lesson_id, '_clms_quiz_id' );
			}

			update_post_meta( $post_id, '_clms_lesson_id', $lesson_id );
			if ( $lesson_id ) {
				update_post_meta( $lesson_id, '_clms_quiz_id', $post_id );
			}

			// Automatically derive parent course_id from lesson.
			$derived_course_id = $lesson_id ? absint( get_post_meta( $lesson_id, '_clms_course_id', true ) ) : 0;
			update_post_meta( $post_id, '_clms_course_id', $derived_course_id );

			if ( isset( $_POST['clms_pass_percent'] ) ) {
				update_post_meta( $post_id, '_clms_pass_percent', CLMS_Post_Types::sanitize_pass_percent( wp_unslash( $_POST['clms_pass_percent'] ) ) );
			}
			if ( isset( $_POST['clms_max_attempts'] ) ) {
				update_post_meta( $post_id, '_clms_max_attempts', absint( wp_unslash( $_POST['clms_max_attempts'] ) ) );
			}

			if ( isset( $_POST['clms_questions_present'] ) ) {
				$cleaned_questions = array();
				if ( isset( $_POST['clms_questions'] ) && is_array( $_POST['clms_questions'] ) ) {
					$q_id = 1;
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$raw_questions = wp_unslash( $_POST['clms_questions'] );
					foreach ( $raw_questions as $raw_q ) {
						if ( ! is_array( $raw_q ) ) {
							continue;
						}
						$text = isset( $raw_q['text'] ) ? self::clean_quiz_field( $raw_q['text'] ) : '';
						if ( '' === $text ) {
							continue;
						}

						$raw_correct_idx = isset( $raw_q['correct_index'] ) ? (int) $raw_q['correct_index'] : 0;
						$options         = array();
						$correct_idx     = 0;

						if ( isset( $raw_q['options'] ) && is_array( $raw_q['options'] ) ) {
							foreach ( $raw_q['options'] as $raw_opt_idx => $opt ) {
								$opt_clean = self::clean_quiz_field( $opt );
								if ( '' !== $opt_clean ) {
									if ( (int) $raw_opt_idx === $raw_correct_idx ) {
										$correct_idx = count( $options );
									}
									$options[] = $opt_clean;
								}
							}
						}

						if ( count( $options ) < 2 ) {
							continue;
						}

						if ( $correct_idx >= count( $options ) ) {
							$correct_idx = 0;
						}

						$explanation = isset( $raw_q['explanation'] ) ? self::clean_quiz_field( $raw_q['explanation'] ) : '';

						$cleaned_questions[] = array(
							'id'            => $q_id++,
							'text'          => $text,
							'type'          => count( $options ) === 2 ? 'true_false' : 'multiple_choice',
							'options'       => $options,
							'correct_index' => $correct_idx,
							'explanation'   => $explanation,
						);
					}
				}
				update_post_meta( $post_id, '_clms_questions', wp_json_encode( $cleaned_questions ) );
			}
		}
	}

	/**
	 * Clean quiz text while safely preserving HTML tags, symbols, and code snippets as plain text.
	 *
	 * Unlike sanitize_text_field(), this does not strip <a>, <div>, <p> tags so web development
	 * and programming questions are never corrupted or emptied.
	 *
	 * @param mixed $text Raw input.
	 * @return string Cleaned string.
	 */
	public static function clean_quiz_field( $text ) {
		if ( ! is_string( $text ) ) {
			return '';
		}
		// Remove null bytes and non-printable control characters except line breaks/tabs
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
		// Strip dangerous active script/iframe execution tags
		$text = preg_replace( '/<\s*(script|iframe|object|embed)[^>]*>.*?<\s*\/\s*(script|iframe|object|embed)\s*>/is', '', $text );
		return trim( $text );
	}

	/**
	 * Compute lowest topic order for a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return int|null
	 */
	public static function get_lowest_topic_order( $lesson_id ) {
		global $wpdb;
		$order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(CAST(pm_order.meta_value AS UNSIGNED))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_lesson ON p.ID = pm_lesson.post_id AND pm_lesson.meta_key = '_clms_lesson_id'
				INNER JOIN {$wpdb->postmeta} pm_order ON p.ID = pm_order.post_id AND pm_order.meta_key = '_clms_order'
				WHERE p.post_type = 'clms_topic'
				AND p.post_status IN ('publish', 'draft')
				AND pm_lesson.meta_value = %d",
				$lesson_id
			)
		);

		return null !== $order ? (int) $order : null;
	}

	/**
	 * Lesson columns.
	 *
	 * @param array $columns Columns array.
	 * @return array
	 */
	public static function lesson_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['clms_course'] = __( 'Course', 'custom-lms' );
				$new_columns['clms_order']  = __( 'Order', 'custom-lms' );
				$new_columns['clms_quiz']   = __( 'Assigned Quiz', 'custom-lms' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render lesson column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_lesson_columns( $column, $post_id ) {
		if ( 'clms_course' === $column ) {
			$course_id = get_post_meta( $post_id, '_clms_course_id', true );
			if ( $course_id && get_post( $course_id ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a>';
			} else {
				echo '<span style="color:#94a3b8;">' . esc_html__( '— None —', 'custom-lms' ) . '</span>';
			}
		} elseif ( 'clms_order' === $column ) {
			$order = get_post_meta( $post_id, '_clms_order', true );
			echo esc_html( $order ? $order : '1' );
		} elseif ( 'clms_quiz' === $column ) {
			$quiz = CLMS_Frontend::get_lesson_quiz( $post_id );
			if ( $quiz ) {
				echo '<a href="' . esc_url( get_edit_post_link( $quiz->ID ) ) . '">📝 ' . esc_html( $quiz->post_title ) . '</a>';
			} else {
				echo '<span style="color:#94a3b8;">' . esc_html__( '— None Assigned —', 'custom-lms' ) . '</span>';
			}
		}
	}

	/**
	 * Sortable columns for lessons.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function lesson_sortable_columns( $columns ) {
		$columns['clms_order'] = 'clms_order';
		return $columns;
	}

	/**
	 * Topic columns.
	 *
	 * @param array $columns Columns array.
	 * @return array
	 */
	public static function topic_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['clms_lesson'] = __( 'Lesson', 'custom-lms' );
				$new_columns['clms_course'] = __( 'Course', 'custom-lms' );
				$new_columns['clms_order']  = __( 'Order', 'custom-lms' );
				$new_columns['clms_access'] = __( 'Access Type', 'custom-lms' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render topic column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_topic_columns( $column, $post_id ) {
		if ( 'clms_lesson' === $column ) {
			$lesson_id = get_post_meta( $post_id, '_clms_lesson_id', true );
			if ( $lesson_id && get_post( $lesson_id ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $lesson_id ) ) . '">' . esc_html( get_the_title( $lesson_id ) ) . '</a>';
			} else {
				echo '<span style="color:#94a3b8;">' . esc_html__( '— None —', 'custom-lms' ) . '</span>';
			}
		} elseif ( 'clms_course' === $column ) {
			$course_id = get_post_meta( $post_id, '_clms_course_id', true );
			if ( $course_id && get_post( $course_id ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $course_id ) ) . '">' . esc_html( get_the_title( $course_id ) ) . '</a>';
			} else {
				echo '<span style="color:#94a3b8;">' . esc_html__( '— None —', 'custom-lms' ) . '</span>';
			}
		} elseif ( 'clms_order' === $column ) {
			$order = get_post_meta( $post_id, '_clms_order', true );
			echo esc_html( $order ? $order : '1' );
		} elseif ( 'clms_access' === $column ) {
			if ( CLMS_Access::is_free_topic( $post_id ) ) {
				echo '<span style="color:#059669;font-weight:600;">' . esc_html__( 'Free Preview', 'custom-lms' ) . '</span>';
				return;
			}
			echo '<span style="color:#64748b;">' . esc_html__( '🔒 Enrolled Only', 'custom-lms' ) . '</span>';
		}
	}

	/**
	 * Sortable columns for topics.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function topic_sortable_columns( $columns ) {
		$columns['clms_order'] = 'clms_order';
		return $columns;
	}

	/**
	 * Quiz columns.
	 *
	 * @param array $columns Columns array.
	 * @return array
	 */
	public static function quiz_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['clms_lesson']          = __( 'Lesson', 'custom-lms' );
				$new_columns['clms_questions_count'] = __( 'Questions', 'custom-lms' );
				$new_columns['clms_pass_percent']    = __( 'Pass Mark', 'custom-lms' );
				$new_columns['clms_max_attempts']    = __( 'Max Attempts', 'custom-lms' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render quiz column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_quiz_columns( $column, $post_id ) {
		if ( 'clms_lesson' === $column ) {
			$lesson_id = get_post_meta( $post_id, '_clms_lesson_id', true );
			if ( $lesson_id && get_post( $lesson_id ) ) {
				echo '<a href="' . esc_url( get_edit_post_link( $lesson_id ) ) . '">' . esc_html( get_the_title( $lesson_id ) ) . '</a>';
			} else {
				echo '<span style="color:#94a3b8;">' . esc_html__( '— None —', 'custom-lms' ) . '</span>';
			}
		} elseif ( 'clms_questions_count' === $column ) {
			$questions_json = get_post_meta( $post_id, '_clms_questions', true );
			$questions      = ! empty( $questions_json ) ? json_decode( $questions_json, true ) : array();
			$count          = count( $questions );
			if ( $count > 0 ) {
				echo '<span style="font-weight:600;color:#0f172a;">' . esc_html( sprintf( _n( '%d Question', '%d Questions', $count, 'custom-lms' ), $count ) ) . '</span>';
			} else {
				echo '<span style="color:#e11d48;font-weight:600;">⚠️ ' . esc_html__( '0 Questions', 'custom-lms' ) . '</span>';
			}
		} elseif ( 'clms_pass_percent' === $column ) {
			$pass = get_post_meta( $post_id, '_clms_pass_percent', true );
			echo esc_html( ( $pass ? $pass : '70' ) . '%' );
		} elseif ( 'clms_max_attempts' === $column ) {
			$attempts = get_post_meta( $post_id, '_clms_max_attempts', true );
			echo esc_html( empty( $attempts ) ? __( 'Unlimited', 'custom-lms' ) : $attempts );
			echo esc_html( ( ! empty( $attempts ) && (int) $attempts > 0 ) ? sprintf( _n( '%d attempt', '%d attempts', (int) $attempts, 'custom-lms' ), (int) $attempts ) : __( '1 (Single attempt)', 'custom-lms' ) );
		}
	}

	/**
	 * Handle admin order column sorting.
	 *
	 * @param WP_Query $query Main query.
	 */
	public static function sort_by_order( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'clms_order' === $orderby ) {
			$query->set( 'meta_key', '_clms_order' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}
}
