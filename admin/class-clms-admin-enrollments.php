<?php
/**
 * Admin Enrollments Management (Manual Enrollment, Search, Revoke).
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Admin_Enrollments
 */
class CLMS_Admin_Enrollments {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'wp_ajax_clms_get_lesson_items', array( __CLASS__, 'ajax_get_lesson_items' ) );
	}

	/**
	 * Register submenu under Custom LMS.
	 */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=clms_course',
			__( 'Enrollments', 'custom-lms' ),
			__( 'Enrollments', 'custom-lms' ),
			'manage_options',
			'clms-enrollments',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * AJAX handler: Get topics and quizzes dynamically for a selected lesson.
	 */
	public static function ajax_get_lesson_items() {
		check_ajax_referer( 'clms_enrollment_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'custom-lms' ) ) );
		}

		$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
		if ( ! $lesson_id ) {
			wp_send_json_success(
				array(
					'topics'  => array(),
					'quizzes' => array(),
				)
			);
		}

		// Get topics for this lesson.
		$all_topics = get_posts(
			array(
				'post_type'      => 'clms_topic',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
			)
		);
		$topics = array();
		foreach ( $all_topics as $t ) {
			if ( (int) get_post_meta( $t->ID, '_clms_lesson_id', true ) === (int) $lesson_id ) {
				$order    = get_post_meta( $t->ID, '_clms_order', true );
				$topics[] = array(
					'id'    => $t->ID,
					'title' => $t->post_title . ' (Order: ' . ( $order ? $order : 1 ) . ')',
				);
			}
		}

		// Get quizzes for this lesson.
		$all_quizzes = get_posts(
			array(
				'post_type'      => 'clms_quiz',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
			)
		);
		$quizzes = array();
		foreach ( $all_quizzes as $q ) {
			if ( (int) get_post_meta( $q->ID, '_clms_lesson_id', true ) === (int) $lesson_id ) {
				$quizzes[] = array(
					'id'    => $q->ID,
					'title' => $q->post_title,
				);
			}
		}

		wp_send_json_success(
			array(
				'topics'  => $topics,
				'quizzes' => $quizzes,
			)
		);
	}

	/**
	 * Handle form actions (manual enroll / revoke).
	 */
	public static function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'clms-enrollments' !== $_GET['page'] ) {
			return;
		}

		// 1. Manual Enroll action.
		if ( isset( $_POST['clms_action'] ) && 'manual_enroll' === $_POST['clms_action'] ) {
			check_admin_referer( 'clms_manual_enroll_action', 'clms_manual_enroll_nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'custom-lms' ) );
			}

			$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
			$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
			$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
			$topic_id  = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
			$quiz_id   = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

			// If course_id was not explicitly chosen but a lesson was chosen, derive course_id.
			if ( ! $course_id && $lesson_id ) {
				$course_id = absint( get_post_meta( $lesson_id, '_clms_course_id', true ) );
			}

			if ( ! $user_id || ! $course_id ) {
				wp_safe_redirect( add_query_arg( array( 'clms_msg' => 'error_missing_fields' ), admin_url( 'edit.php?post_type=clms_course&page=clms-enrollments' ) ) );
				exit;
			}

			global $wpdb;
			$table = CLMS_DB::get_enrollments_table();

			$wpdb->replace(
				$table,
				array(
					'user_id'     => $user_id,
					'course_id'   => $course_id,
					'status'      => 'active',
					'source'      => 'manual',
					'order_id'    => 0,
					'enrolled_at' => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s' )
			);

			// If specific topic or quiz progress was also initialized:
			if ( $topic_id ) {
				$prog_table = CLMS_DB::get_progress_table();
				$wpdb->insert(
					$prog_table,
					array(
						'user_id'        => $user_id,
						'course_id'      => $course_id,
						'lesson_id'      => $lesson_id,
						'object_id'      => $topic_id,
						'object_type'    => 'topic',
						'status'         => 'completed',
						'attempt_number' => 1,
						'completed_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
				);
			}

			wp_safe_redirect( add_query_arg( array( 'clms_msg' => 'enrolled_success' ), admin_url( 'edit.php?post_type=clms_course&page=clms-enrollments' ) ) );
			exit;
		}

		// 2. Revoke action.
		if ( isset( $_GET['action'] ) && 'revoke' === $_GET['action'] && isset( $_GET['enrollment_id'] ) ) {
			check_admin_referer( 'clms_revoke_enrollment_' . absint( $_GET['enrollment_id'] ) );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'custom-lms' ) );
			}

			$id = absint( $_GET['enrollment_id'] );
			global $wpdb;
			$table = CLMS_DB::get_enrollments_table();
			$wpdb->update(
				$table,
				array( 'status' => 'revoked' ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);

			wp_safe_redirect( add_query_arg( array( 'clms_msg' => 'revoked_success' ), admin_url( 'edit.php?post_type=clms_course&page=clms-enrollments' ) ) );
			exit;
		}
	}

	/**
	 * Render the Enrollments management page.
	 */
	public static function render_page() {
		global $wpdb;
		$table = CLMS_DB::get_enrollments_table();

		// Fetch courses, lessons, quizzes for dropdowns.
		$courses = get_posts(
			array(
				'post_type'      => 'clms_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$lessons = get_posts(
			array(
				'post_type'      => 'clms_lesson',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$quizzes = get_posts(
			array(
				'post_type'      => 'clms_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$users = get_users(
			array(
				'orderby' => 'display_name',
				'number'  => 100,
			)
		);

		// Search & Pagination.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$where  = 'WHERE 1=1';
		if ( $search ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where      .= $wpdb->prepare(
				" AND (user_id IN (SELECT ID FROM {$wpdb->users} WHERE user_login LIKE %s OR user_email LIKE %s OR display_name LIKE %s)
				   OR course_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s))",
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		$enrollments = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY enrolled_at DESC LIMIT 50" );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Course Enrollments', 'custom-lms' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['clms_msg'] ) ) : ?>
				<?php if ( 'enrolled_success' === $_GET['clms_msg'] ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'User enrolled successfully.', 'custom-lms' ); ?></p></div>
				<?php elseif ( 'revoked_success' === $_GET['clms_msg'] ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Enrollment status set to revoked.', 'custom-lms' ); ?></p></div>
				<?php elseif ( 'error_missing_fields' === $_GET['clms_msg'] ) : ?>
					<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Error: Please select both a student and a course/lesson.', 'custom-lms' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<div style="display:flex;gap:30px;margin-top:20px;flex-wrap:wrap;">
				<!-- Manual Enrollment Form with Dynamic Lesson, Topic & Quiz Dropdowns -->
				<div style="flex:1;min-width:320px;max-width:440px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<h2 style="margin-top:0;border-bottom:1px solid #eee;padding-bottom:10px;">
						<?php esc_html_e( 'Create Manual Enrollment', 'custom-lms' ); ?>
					</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-enrollments' ) ); ?>">
						<?php wp_nonce_field( 'clms_manual_enroll_action', 'clms_manual_enroll_nonce' ); ?>
						<input type="hidden" name="clms_action" value="manual_enroll" />

						<!-- User Dropdown -->
						<p>
							<label for="clms_user_id"><strong><?php esc_html_e( 'Select Student / User *', 'custom-lms' ); ?></strong></label><br>
							<select name="user_id" id="clms_user_id" style="width:100%;margin-top:5px;" required>
								<option value=""><?php esc_html_e( '— Select User —', 'custom-lms' ); ?></option>
								<?php foreach ( $users as $u ) : ?>
									<option value="<?php echo esc_attr( $u->ID ); ?>">
										<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>

						<!-- Course Dropdown -->
						<p>
							<label for="clms_course_id"><strong><?php esc_html_e( 'Select Course *', 'custom-lms' ); ?></strong></label><br>
							<select name="course_id" id="clms_course_id" style="width:100%;margin-top:5px;">
								<option value=""><?php esc_html_e( '— Select Course —', 'custom-lms' ); ?></option>
								<?php foreach ( $courses as $c ) : ?>
									<option value="<?php echo esc_attr( $c->ID ); ?>"><?php echo esc_html( $c->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>

						<!-- 1. Lesson Dropdown (Dynamic) -->
						<p>
							<label for="clms_lesson_id"><strong><?php esc_html_e( '1. Lesson Dropdown', 'custom-lms' ); ?></strong></label><br>
							<select name="lesson_id" id="clms_lesson_id" style="width:100%;margin-top:5px;">
								<option value=""><?php esc_html_e( '— Select Existing Lesson —', 'custom-lms' ); ?></option>
								<?php foreach ( $lessons as $l ) : ?>
									<?php
									$parent_c = get_post_meta( $l->ID, '_clms_course_id', true );
									$c_title  = $parent_c ? get_the_title( $parent_c ) : __( 'No Course', 'custom-lms' );
									?>
									<option value="<?php echo esc_attr( $l->ID ); ?>" data-course="<?php echo esc_attr( $parent_c ); ?>">
										<?php echo esc_html( $l->post_title . ' [' . $c_title . ']' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="description" style="font-size:12px;color:#666;"><?php esc_html_e( 'Select a lesson to dynamically load associated topics & quizzes.', 'custom-lms' ); ?></span>
						</p>

						<!-- 2. Topic Dropdown (Dynamic AJAX) -->
						<p>
							<label for="clms_topic_id"><strong><?php esc_html_e( '2. Topic Dropdown', 'custom-lms' ); ?></strong></label><br>
							<select name="topic_id" id="clms_topic_id" style="width:100%;margin-top:5px;">
								<option value=""><?php esc_html_e( '— Select Lesson First —', 'custom-lms' ); ?></option>
							</select>
							<span class="description" style="font-size:12px;color:#666;"><?php esc_html_e( 'Populated dynamically from topics in the selected lesson.', 'custom-lms' ); ?></span>
						</p>

						<!-- 3. Quiz Dropdown (Dynamic AJAX) -->
						<p>
							<label for="clms_quiz_id"><strong><?php esc_html_e( '3. Quiz Dropdown', 'custom-lms' ); ?></strong></label><br>
							<select name="quiz_id" id="clms_quiz_id" style="width:100%;margin-top:5px;">
								<option value=""><?php esc_html_e( '— All Quizzes / Select Lesson —', 'custom-lms' ); ?></option>
								<?php foreach ( $quizzes as $q ) : ?>
									<?php $q_lesson = get_post_meta( $q->ID, '_clms_lesson_id', true ); ?>
									<option value="<?php echo esc_attr( $q->ID ); ?>" data-lesson="<?php echo esc_attr( $q_lesson ); ?>">
										<?php echo esc_html( $q->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<span class="description" style="font-size:12px;color:#666;"><?php esc_html_e( 'Populated dynamically from available quizzes.', 'custom-lms' ); ?></span>
						</p>

						<p style="margin-top:20px;">
							<input type="submit" class="button button-primary button-large" style="width:100%;" value="<?php esc_attr_e( 'Enroll Student', 'custom-lms' ); ?>" />
						</p>
					</form>
				</div>

				<!-- Enrollments List Table -->
				<div style="flex:2;min-width:480px;">
					<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
						<input type="hidden" name="post_type" value="clms_course" />
						<input type="hidden" name="page" value="clms-enrollments" />
						<p class="search-box">
							<label class="screen-reader-text" for="enrollment-search-input"><?php esc_html_e( 'Search Enrollments:', 'custom-lms' ); ?></label>
							<input type="search" id="enrollment-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
							<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'custom-lms' ); ?>">
						</p>
					</form>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Student / User', 'custom-lms' ); ?></th>
								<th><?php esc_html_e( 'Course', 'custom-lms' ); ?></th>
								<th><?php esc_html_e( 'Status', 'custom-lms' ); ?></th>
								<th><?php esc_html_e( 'Source', 'custom-lms' ); ?></th>
								<th><?php esc_html_e( 'Enrolled Date', 'custom-lms' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'custom-lms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $enrollments ) ) : ?>
								<tr>
									<td colspan="6"><?php esc_html_e( 'No enrollments found.', 'custom-lms' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $enrollments as $e ) : ?>
									<?php
									$user   = get_userdata( $e->user_id );
									$course = get_post( $e->course_id );
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $user ? $user->display_name : '#' . $e->user_id ); ?></strong><br>
											<span style="color:#666;"><?php echo esc_html( $user ? $user->user_email : '' ); ?></span>
										</td>
										<td>
											<?php if ( $course ) : ?>
												<a href="<?php echo esc_url( get_edit_post_link( $course->ID ) ); ?>">
													<?php echo esc_html( $course->post_title ); ?>
												</a>
											<?php else : ?>
												#<?php echo esc_html( $e->course_id ); ?>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( 'active' === $e->status ) : ?>
												<span style="color:#059669;font-weight:600;"><?php esc_html_e( 'Active', 'custom-lms' ); ?></span>
											<?php else : ?>
												<span style="color:#dc2626;font-weight:600;"><?php echo esc_html( ucfirst( $e->status ) ); ?></span>
											<?php endif; ?>
										</td>
										<td><code><?php echo esc_html( $e->source ); ?></code></td>
										<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $e->enrolled_at ) ) ); ?></td>
										<td>
											<?php if ( 'active' === $e->status ) : ?>
												<?php
												$revoke_url = wp_nonce_url(
													add_query_arg(
														array(
															'post_type' => 'clms_course',
															'page'      => 'clms-enrollments',
															'action'    => 'revoke',
															'enrollment_id' => $e->id,
														),
														admin_url( 'edit.php' )
													),
													'clms_revoke_enrollment_' . $e->id
												);
												?>
												<a href="<?php echo esc_url( $revoke_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke this enrollment?', 'custom-lms' ); ?>');">
													<?php esc_html_e( 'Revoke', 'custom-lms' ); ?>
												</a>
											<?php else : ?>
												—
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Dynamic Dropdowns JavaScript -->
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var lessonSelect = document.getElementById('clms_lesson_id');
			var topicSelect = document.getElementById('clms_topic_id');
			var quizSelect = document.getElementById('clms_quiz_id');
			var courseSelect = document.getElementById('clms_course_id');

			// When lesson changes, auto-select course and populate topic & quiz dynamically
			if (lessonSelect) {
				lessonSelect.addEventListener('change', function() {
					var selectedOption = lessonSelect.options[lessonSelect.selectedIndex];
					var lessonId = selectedOption.value;
					var courseId = selectedOption.getAttribute('data-course');

					if (courseId && courseSelect) {
						courseSelect.value = courseId;
					}

					if (!lessonId) {
						topicSelect.innerHTML = '<option value="">— Select Lesson First —</option>';
						return;
					}

					topicSelect.innerHTML = '<option value="">Loading topics...</option>';

					var data = new FormData();
					data.append('action', 'clms_get_lesson_items');
					data.append('lesson_id', lessonId);
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'clms_enrollment_nonce' ) ); ?>');

					fetch(ajaxurl, {
						method: 'POST',
						body: data
					})
					.then(function(res) { return res.json(); })
					.then(function(response) {
						if (response.success) {
							// Populate Topics
							topicSelect.innerHTML = '<option value="">— Select Topic (Optional) —</option>';
							if (response.data.topics && response.data.topics.length > 0) {
								response.data.topics.forEach(function(item) {
									var opt = document.createElement('option');
									opt.value = item.id;
									opt.textContent = item.title;
									topicSelect.appendChild(opt);
								});
							} else {
								var opt = document.createElement('option');
								opt.value = '';
								opt.textContent = 'No topics found for this lesson';
								topicSelect.appendChild(opt);
							}

							// Filter Quizzes
							if (quizSelect && response.data.quizzes) {
								quizSelect.innerHTML = '<option value="">— Select Quiz (Optional) —</option>';
								if (response.data.quizzes.length > 0) {
									response.data.quizzes.forEach(function(item) {
										var opt = document.createElement('option');
										opt.value = item.id;
										opt.textContent = item.title;
										quizSelect.appendChild(opt);
									});
								}
							}
						}
					})
					.catch(function(err) {
						topicSelect.innerHTML = '<option value="">Error loading topics</option>';
					});
				});
			}
		});
		</script>
		<?php
	}
}
