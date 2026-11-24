<?php
/**
 * Frontend display & template handling for Custom LMS.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Frontend
 */
class CLMS_Frontend {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_purchase_modal' ) );
		add_action( 'wp_ajax_clms_ajax_login', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_clms_ajax_login', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_clms_ajax_register', array( __CLASS__, 'ajax_register' ) );
		add_filter( 'hello_elementor_page_title', array( __CLASS__, 'filter_theme_page_title' ) );
		add_filter( 'template_include', array( __CLASS__, 'filter_template_include' ) );
	}

	/**
	 * Route archive queries for clms_course to the custom archive template.
	 *
	 * @param string $template Path to template file.
	 * @return string
	 */
	public static function filter_template_include( $template ) {
		if ( is_post_type_archive( 'clms_course' ) ) {
			$archive_template = CLMS_PATH . 'templates/archive-clms_course.php';
			if ( file_exists( $archive_template ) ) {
				return $archive_template;
			}
		}
		return $template;
	}

	/**
	 * Suppress theme default page title on LMS custom single post types so our custom Hero headers take full stage.
	 *
	 * @param bool $show Default show title boolean.
	 * @return bool
	 */
	public static function filter_theme_page_title( $show ) {
		if ( is_singular( array( 'clms_course', 'clms_lesson', 'clms_topic', 'clms_quiz' ) ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Enqueue frontend CSS and JS.
	 */
	public static function enqueue_styles() {
		$css_path = CLMS_PATH . 'public/css/clms-frontend.css';
		$js_path  = CLMS_PATH . 'public/js/clms-frontend.js';
		$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : CLMS_VERSION;
		$js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : CLMS_VERSION;

		wp_enqueue_style(
			'clms-frontend',
			CLMS_URL . 'public/css/clms-frontend.css',
			array(),
			$css_ver
		);

		// Inject customized course, buttons, backgrounds, progress bar & quiz colors from backend settings.
		if ( class_exists( 'CLMS_Admin_Settings' ) ) {
			$global_custom_css = CLMS_Admin_Settings::generate_global_css();
			if ( ! empty( $global_custom_css ) ) {
				wp_add_inline_style( 'clms-frontend', $global_custom_css );
			}

			$quiz_custom_css = CLMS_Admin_Settings::generate_quiz_css();
			if ( ! empty( $quiz_custom_css ) ) {
				wp_add_inline_style( 'clms-frontend', $quiz_custom_css );
			}
		}

		wp_enqueue_script(
			'clms-frontend-js',
			CLMS_URL . 'public/js/clms-frontend.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'clms-frontend-js',
			'clms_vars',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'is_logged_in' => is_user_logged_in(),
				'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url(),
				'nonce'        => wp_create_nonce( 'clms_auth_nonce' ),
				'rest_url'     => esc_url_raw( rest_url( 'custom-lms/v1/' ) ),
				'rest_nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Filter post content for LMS post types.
	 *
	 * @param string $content Original post content.
	 * @return string Modified content.
	 */
	public static function filter_content( $content ) {
		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		if ( is_archive() && 'clms_course' === $post->post_type ) {
			return self::render_course_archive_item( $post, $content );
		}

		if ( ! is_singular() ) {
			return $content;
		}

		if ( 'clms_course' === $post->post_type ) {
			return self::render_course_view( $post, $content );
		}

		if ( 'clms_lesson' === $post->post_type ) {
			return self::render_lesson_view( $post, $content );
		}

		if ( 'clms_topic' === $post->post_type ) {
			return self::render_topic_view( $post, $content );
		}

		if ( 'clms_quiz' === $post->post_type ) {
			return self::render_quiz_view( $post, $content );
		}

		return $content;
	}

	/**
	 * Render archive snippet with flow preview and meta.
	 *
	 * @param WP_Post $course  Course post object.
	 * @param string  $content Original excerpt/content.
	 * @return string
	 */
	public static function render_course_archive_item( $course, $content = '' ) {
		$price_data    = class_exists( 'CLMS_WooCommerce' ) ? CLMS_WooCommerce::get_course_price_data( $course->ID ) : array( 'formatted' => __( 'Free', 'custom-lms' ) );
		$lessons       = self::get_course_lessons( $course->ID );
		$total_lessons = count( $lessons );
		$total_topics  = 0;
		$total_quizzes = 0;
		foreach ( $lessons as $l ) {
			$total_topics += count( self::get_lesson_topics( $l->ID ) );
			if ( self::get_lesson_quiz( $l->ID ) ) {
				$total_quizzes++;
			}
		}

		$instructor_id = get_post_meta( $course->ID, '_clms_instructor_id', true );
		if ( ! $instructor_id ) {
			$instructor_id = $course->post_author;
		}
		$instructor = get_userdata( $instructor_id );

		$has_thumb = has_post_thumbnail( $course->ID );

		$raw_desc   = ! empty( $course->post_excerpt ) ? $course->post_excerpt : ( ! empty( $content ) ? $content : $course->post_content );
		$clean_desc = preg_replace( '/<!--\s*\/?wp:[^\>]*-->/', '', $raw_desc );
		$excerpt    = wp_trim_words( wp_strip_all_tags( $clean_desc ), 24 );

		ob_start();
		?>
		<div class="clms-archive-card-wrapper">
			<div class="clms-archive-card-thumb">
				<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="clms-archive-thumb-link">
					<?php if ( $has_thumb ) : ?>
						<?php echo get_the_post_thumbnail( $course->ID, 'medium_large', array( 'class' => 'clms-archive-thumb-img' ) ); ?>
					<?php else : ?>
						<div class="clms-archive-thumb-fallback">
							<div class="clms-atf-glow"></div>
							<div class="clms-atf-inner">
								<span class="clms-atf-icon">💻</span>
								<span class="clms-atf-tag"><?php esc_html_e( 'Interactive Course', 'custom-lms' ); ?></span>
								<span class="clms-atf-title"><?php echo esc_html( $course->post_title ); ?></span>
							</div>
						</div>
					<?php endif; ?>
				</a>
				<span class="clms-archive-price-badge">💰 <?php echo esc_html( $price_data['formatted'] ); ?></span>
			</div>

			<div class="clms-archive-card-body">
				<div class="clms-archive-card-meta">
					<span class="clms-badge clms-badge-instructor">
						📚 <?php echo esc_html( sprintf( _n( '%d Lesson', '%d Lessons', $total_lessons, 'custom-lms' ), $total_lessons ) ); ?>
					</span>
					<span class="clms-badge clms-badge-instructor">
						📝 <?php echo esc_html( sprintf( _n( '%d Topic', '%d Topics', $total_topics, 'custom-lms' ), $total_topics ) ); ?>
					</span>
					<?php if ( $total_quizzes > 0 ) : ?>
						<span class="clms-badge clms-badge-instructor">
							🎯 <?php echo esc_html( sprintf( _n( '%d Quiz', '%d Quizzes', $total_quizzes, 'custom-lms' ), $total_quizzes ) ); ?>
						</span>
					<?php endif; ?>
				</div>

				<h3 class="clms-archive-card-title">
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>">
						<?php echo esc_html( $course->post_title ); ?>
					</a>
				</h3>

				<div class="clms-archive-card-content">
					<?php echo esc_html( $excerpt ); ?>
				</div>

				<div class="clms-archive-card-footer">
					<div class="clms-archive-instructor">
						<span class="clms-inst-avatar">👨‍🏫</span>
						<span class="clms-inst-name"><?php echo esc_html( $instructor ? $instructor->display_name : 'Instructor' ); ?></span>
					</div>
					<div class="clms-archive-card-action">
						<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="clms-btn clms-btn-primary clms-btn-sm">
							<?php esc_html_e( 'View Course →', 'custom-lms' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Course single view with its full flow: Lessons, Topics, and Quizzes.
	 *
	 * @param WP_Post $course  Course post object.
	 * @param string  $content Post content.
	 * @return string
	 */
	public static function render_course_view( $course, $content ) {
		$price_data    = class_exists( 'CLMS_WooCommerce' ) ? CLMS_WooCommerce::get_course_price_data( $course->ID ) : array( 'formatted' => __( 'Free', 'custom-lms' ) );
		$instructor_id = get_post_meta( $course->ID, '_clms_instructor_id', true );
		if ( ! $instructor_id ) {
			$instructor_id = $course->post_author;
		}
		$instructor = get_userdata( $instructor_id );

		// Fetch Lessons for this course, ordered by _clms_order ASC.
		$lessons       = self::get_course_lessons( $course->ID );
		$total_lessons = count( $lessons );
		$total_topics  = 0;
		$total_quizzes = 0;
		$first_topic   = null;
		$next_topic    = null;

		$user_id       = get_current_user_id();
		$is_enrolled   = ( $user_id && $course->ID ) ? CLMS_Access::is_enrolled( $user_id, $course->ID ) : false;
		$completed_ids = ( $user_id && $course->ID ) ? CLMS_Progress::get_completed_ids( $user_id, $course->ID ) : array();

		foreach ( $lessons as $l ) {
			$l_topics = self::get_lesson_topics( $l->ID );
			$total_topics += count( $l_topics );
			foreach ( $l_topics as $lt ) {
				if ( null === $first_topic ) {
					$first_topic = $lt;
				}
				if ( $is_enrolled && ! in_array( (int) $lt->ID, $completed_ids, true ) && null === $next_topic ) {
					$next_topic = $lt;
				}
			}
			$l_quiz = self::get_lesson_quiz( $l->ID );
			if ( $l_quiz ) {
				$total_quizzes++;
			}
		}
		if ( null === $next_topic ) {
			$next_topic = $first_topic;
		}

		$completed_count   = count( $completed_ids );
		$progress_pct      = ( $total_topics > 0 && $completed_count > 0 ) ? min( 100, round( ( $completed_count / $total_topics ) * 100 ) ) : 0;
		$wc_prod_id        = (int) get_post_meta( $course->ID, '_clms_wc_product_id', true );
		$has_thumb         = has_post_thumbnail( $course->ID );
		$raw_desc          = ! empty( $course->post_excerpt ) ? $course->post_excerpt : ( ! empty( $content ) ? $content : $course->post_content );
		$clean_desc        = preg_replace( '/<!--\s*\/?wp:[^\>]*-->/', '', $raw_desc );
		$course_desc_short = wp_trim_words( wp_strip_all_tags( $clean_desc ), 38 );

		ob_start();
		?>
		<div class="clms-course-page-wrapper">
			<!-- ==========================================
			     Modern Course Hero Header
			     ========================================== -->
			<div class="clms-course-hero-banner">
				<div class="clms-course-hero-grid">
					<!-- Left Column: Course Identity, Title, Badges & CTA -->
					<div class="clms-course-hero-left">
						<div class="clms-course-breadcrumbs">
							<a href="<?php echo esc_url( get_post_type_archive_link( 'clms_course' ) ); ?>" class="clms-hero-back-link">
								← <?php esc_html_e( 'All Courses', 'custom-lms' ); ?>
							</a>
							<span class="clms-breadcrumb-sep">/</span>
							<span class="clms-pill clms-pill-course-type"><?php esc_html_e( 'Self-Paced Course', 'custom-lms' ); ?></span>
						</div>

						<h1 class="clms-course-hero-title"><?php echo esc_html( $course->post_title ); ?></h1>

						<?php if ( ! empty( $course_desc_short ) ) : ?>
							<p class="clms-course-hero-lead"><?php echo esc_html( $course_desc_short ); ?></p>
						<?php endif; ?>

						<!-- Highlights & Meta Pills Bar -->
						<div class="clms-course-hero-stats">
							<div class="clms-hero-stat-pill">
								<span class="clms-stat-icon">👨‍🏫</span>
								<div class="clms-stat-text-group">
									<span class="clms-stat-label"><?php esc_html_e( 'Instructor', 'custom-lms' ); ?></span>
									<strong class="clms-stat-val"><?php echo esc_html( $instructor ? $instructor->display_name : 'Instructor' ); ?></strong>
								</div>
							</div>

							<div class="clms-hero-stat-pill">
								<span class="clms-stat-icon">📚</span>
								<div class="clms-stat-text-group">
									<span class="clms-stat-label"><?php esc_html_e( 'Curriculum', 'custom-lms' ); ?></span>
									<strong class="clms-stat-val">
										<?php echo esc_html( sprintf( _n( '%d Lesson', '%d Lessons', $total_lessons, 'custom-lms' ), $total_lessons ) ); ?>
										• <?php echo esc_html( sprintf( _n( '%d Topic', '%d Topics', $total_topics, 'custom-lms' ), $total_topics ) ); ?>
									</strong>
								</div>
							</div>

							<?php if ( $total_quizzes > 0 ) : ?>
								<div class="clms-hero-stat-pill">
									<span class="clms-stat-icon">🎯</span>
									<div class="clms-stat-text-group">
										<span class="clms-stat-label"><?php esc_html_e( 'Assessments', 'custom-lms' ); ?></span>
										<strong class="clms-stat-val"><?php echo esc_html( sprintf( _n( '%d Quiz', '%d Quizzes', $total_quizzes, 'custom-lms' ), $total_quizzes ) ); ?></strong>
									</div>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $is_enrolled ) : ?>
							<!-- Enrolled Student Progress Widget -->
							<div class="clms-enrolled-hero-banner">
								<div class="clms-enrolled-status-head">
									<span class="clms-enrolled-badge">✓ <?php esc_html_e( 'Enrolled Student', 'custom-lms' ); ?></span>
									<span class="clms-enrolled-pct"><strong><?php echo esc_html( $progress_pct ); ?>%</strong> <?php esc_html_e( 'Completed', 'custom-lms' ); ?></span>
								</div>
								<div class="clms-progress-track">
									<div class="clms-progress-fill" style="width: <?php echo esc_attr( $progress_pct ); ?>%;"></div>
								</div>
								<div class="clms-enrolled-footer">
									<span class="clms-enrolled-count"><?php echo esc_html( sprintf( __( '%1$d of %2$d topics completed', 'custom-lms' ), $completed_count, $total_topics ) ); ?></span>
									<?php if ( $next_topic ) : ?>
										<a href="<?php echo esc_url( get_permalink( $next_topic->ID ) ); ?>" class="clms-enrolled-continue-link">
											<?php echo esc_html( $completed_count > 0 ? __( 'Continue Learning →', 'custom-lms' ) : __( 'Start Learning →', 'custom-lms' ) ); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<!-- Right Column: Media, Price & Purchase Action Card -->
					<div class="clms-course-hero-right">
						<div class="clms-course-sticky-card">
							<div class="clms-hero-card-media">
								<?php if ( $has_thumb ) : ?>
									<?php echo get_the_post_thumbnail( $course->ID, 'large', array( 'class' => 'clms-hero-cover-img' ) ); ?>
								<?php else : ?>
									<div class="clms-hero-cover-fallback">
										<div class="clms-hcf-glow"></div>
										<div class="clms-hcf-content">
											<div class="clms-hcf-icon">💻</div>
											<span class="clms-hcf-tag"><?php esc_html_e( 'Complete Course', 'custom-lms' ); ?></span>
											<h4 class="clms-hcf-title"><?php echo esc_html( $course->post_title ); ?></h4>
										</div>
									</div>
								<?php endif; ?>
								<span class="clms-hero-media-badge"><?php echo esc_html( $price_data['formatted'] ); ?></span>
							</div>

							<div class="clms-hero-card-content">
								<div class="clms-hcc-price-wrap">
									<div class="clms-hcc-price-main">
										<span class="clms-hcc-price"><?php echo esc_html( $price_data['formatted'] ); ?></span>
										<span class="clms-hcc-period"><?php esc_html_e( 'One-time payment', 'custom-lms' ); ?></span>
									</div>
									<span class="clms-hcc-guarantee">🔒 <?php esc_html_e( 'Lifetime Access', 'custom-lms' ); ?></span>
								</div>

								<div class="clms-hcc-actions">
									<?php if ( $is_enrolled ) : ?>
										<?php if ( $next_topic ) : ?>
											<a href="<?php echo esc_url( get_permalink( $next_topic->ID ) ); ?>" class="clms-btn clms-btn-primary clms-btn-block">
												<?php echo esc_html( $completed_count > 0 ? __( 'Continue Learning →', 'custom-lms' ) : __( 'Start Course Now →', 'custom-lms' ) ); ?>
											</a>
										<?php endif; ?>
									<?php else : ?>
										<?php if ( $wc_prod_id ) : ?>
											<button type="button" 
											        class="clms-btn clms-btn-primary clms-btn-block clms-paid-topic-trigger"
											        data-course-id="<?php echo esc_attr( $course->ID ); ?>"
											        data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
											        data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
											        data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
												🛒 <?php echo esc_html( sprintf( __( 'Enroll Now (%s)', 'custom-lms' ), $price_data['formatted'] ) ); ?>
											</button>
										<?php endif; ?>

										<?php if ( $first_topic && CLMS_Access::is_free_topic( $first_topic->ID ) ) : ?>
											<?php if ( ! $user_id ) : ?>
												<button type="button"
												        class="clms-btn clms-btn-outline clms-btn-block clms-free-auth-trigger"
												        data-topic-id="<?php echo esc_attr( $first_topic->ID ); ?>"
												        data-topic-title="<?php echo esc_attr( $first_topic->post_title ); ?>"
												        data-topic-url="<?php echo esc_url( get_permalink( $first_topic->ID ) ); ?>"
												        data-course-id="<?php echo esc_attr( $course->ID ); ?>"
												        data-course-title="<?php echo esc_attr( $course->post_title ); ?>">
													🔓 <?php esc_html_e( 'Watch Free Preview (Login Required)', 'custom-lms' ); ?>
												</button>
											<?php else : ?>
												<a href="<?php echo esc_url( get_permalink( $first_topic->ID ) ); ?>" class="clms-btn clms-btn-outline clms-btn-block">
													🔓 <?php esc_html_e( 'Watch Free Preview Now', 'custom-lms' ); ?>
												</a>
											<?php endif; ?>
										<?php endif; ?>
									<?php endif; ?>
								</div>

								<div class="clms-hcc-includes">
									<span class="clms-includes-title"><?php esc_html_e( 'Course Highlights:', 'custom-lms' ); ?></span>
									<ul class="clms-includes-list">
										<li><span class="clms-inc-icon">✓</span> <?php echo esc_html( sprintf( __( 'Full access to all %d lessons', 'custom-lms' ), $total_lessons ) ); ?></li>
										<li><span class="clms-inc-icon">✓</span> <?php echo esc_html( sprintf( __( '%d structured video topics', 'custom-lms' ), $total_topics ) ); ?></li>
										<?php if ( $total_quizzes > 0 ) : ?>
											<li><span class="clms-inc-icon">✓</span> <?php echo esc_html( sprintf( __( '%d assessment quiz with auto-evaluation', 'custom-lms' ), $total_quizzes ) ); ?></li>
										<?php endif; ?>
										<li><span class="clms-inc-icon">✓</span> <?php esc_html_e( 'Automated score report emailed to student', 'custom-lms' ); ?></li>
										<li><span class="clms-inc-icon">✓</span> <?php esc_html_e( '100% responsive on desktop, tablet, and mobile', 'custom-lms' ); ?></li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- ==========================================
			     Main Layout: Overview & Curriculum
			     ========================================== -->
			<div class="clms-course-main-layout">
				<?php if ( ! empty( trim( wp_strip_all_tags( $content ) ) ) ) : ?>
					<div class="clms-course-about-card">
						<h2 class="clms-section-heading">📖 <?php esc_html_e( 'About This Course', 'custom-lms' ); ?></h2>
						<div class="clms-content-body clms-course-description">
							<?php echo wp_kses_post( $content ); ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="clms-curriculum-section">
					<div class="clms-curriculum-header-row">
						<div>
							<h2 class="clms-curriculum-heading">📋 <?php esc_html_e( 'Course Curriculum & Flow', 'custom-lms' ); ?></h2>
							<p class="clms-curriculum-sub"><?php esc_html_e( 'Follow the lesson steps below. Free preview topics are open to all visitors; other topics and quizzes unlock upon enrollment.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-curriculum-summary-pill">
							<?php echo esc_html( sprintf( __( '%1$d Lessons • %2$d Topics', 'custom-lms' ), $total_lessons, $total_topics ) ); ?>
						</div>
					</div>

				<?php if ( empty( $lessons ) ) : ?>
					<div class="clms-empty-notice">
						<p><?php esc_html_e( 'No lessons have been published for this course yet.', 'custom-lms' ); ?></p>
					</div>
				<?php else : ?>
					<div class="clms-lessons-accordion">
						<?php
						$lesson_index = 1;
						foreach ( $lessons as $lesson ) :
							$lesson_order = get_post_meta( $lesson->ID, '_clms_order', true );

							// Fetch topics for this lesson.
							$topics = self::get_lesson_topics( $lesson->ID );

							// Fetch quiz for this lesson.
							$quiz = self::get_lesson_quiz( $lesson->ID );

							// Find lowest order topic for free preview check.
							$lowest_order = CLMS_Meta_Boxes::get_lowest_topic_order( $lesson->ID );
							?>
							<div class="clms-lesson-card">
								<div class="clms-lesson-header">
									<div class="clms-lesson-title-wrap">
										<span class="clms-step-number"><?php echo esc_html( sprintf( __( 'Lesson %d', 'custom-lms' ), $lesson_index ) ); ?></span>
										<h3 class="clms-lesson-title">
											<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a>
										</h3>
									</div>
									<span class="clms-item-count">
										<?php
										/* translators: %d: number of topics */
										echo esc_html( sprintf( _n( '%d Topic', '%d Topics', count( $topics ), 'custom-lms' ), count( $topics ) ) );
										if ( $quiz ) {
											echo ' • ' . esc_html__( '1 Quiz', 'custom-lms' );
										}
										?>
									</span>
								</div>

								<div class="clms-lesson-body">
									<?php if ( ! empty( $lesson->post_content ) ) : ?>
										<div class="clms-lesson-excerpt">
											<?php echo wp_kses_post( wp_trim_words( $lesson->post_content, 25 ) ); ?>
										</div>
									<?php endif; ?>

									<ul class="clms-topic-list">
										<?php
										$user_id = get_current_user_id();
										$is_enrolled = $user_id ? CLMS_Access::is_enrolled( $user_id, $course->ID ) : false;
										$wc_prod_id = (int) get_post_meta( $course->ID, '_clms_wc_product_id', true );

										foreach ( $topics as $topic ) :
											$is_free    = CLMS_Access::is_free_topic( $topic->ID );
											$can_access = $user_id && ( $is_free || $is_enrolled );
											?>
											<li class="clms-topic-item <?php echo $is_free ? 'is-free' : ( $can_access ? 'is-enrolled-unlocked' : 'is-locked' ); ?>">
												<div class="clms-topic-left">
													<span class="clms-status-icon">
														<?php if ( $is_free ) : ?>
															<span class="clms-icon-unlock" title="<?php esc_attr_e( 'Free Preview (Login Required)', 'custom-lms' ); ?>">🔓</span>
														<?php elseif ( $can_access ) : ?>
															<span class="clms-icon-unlocked" title="<?php esc_attr_e( 'Unlocked for You', 'custom-lms' ); ?>">✓</span>
														<?php else : ?>
															<span class="clms-icon-lock" title="<?php esc_attr_e( 'Enrolled Students Only', 'custom-lms' ); ?>">🔒</span>
														<?php endif; ?>
													</span>
													<?php if ( $can_access ) : ?>
														<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>" class="clms-topic-link">
															<?php echo esc_html( $topic->post_title ); ?>
														</a>
													<?php elseif ( $is_free && ! $user_id ) : ?>
														<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>"
														   class="clms-topic-link clms-free-auth-trigger"
														   data-topic-id="<?php echo esc_attr( $topic->ID ); ?>"
														   data-topic-title="<?php echo esc_attr( $topic->post_title ); ?>"
														   data-topic-url="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>"
														   data-course-id="<?php echo esc_attr( $course->ID ); ?>"
														   data-course-title="<?php echo esc_attr( $course->post_title ); ?>">
															<?php echo esc_html( $topic->post_title ); ?>
														</a>
													<?php else : ?>
														<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>" 
														   class="clms-topic-link clms-paid-topic-trigger" 
														   data-topic-id="<?php echo esc_attr( $topic->ID ); ?>"
														   data-topic-title="<?php echo esc_attr( $topic->post_title ); ?>"
														   data-course-id="<?php echo esc_attr( $course->ID ); ?>"
														   data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
														   data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
														   data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
															<?php echo esc_html( $topic->post_title ); ?>
														</a>
													<?php endif; ?>
												</div>
												<div class="clms-topic-right">
													<?php if ( $is_free ) : ?>
														<span class="clms-pill clms-pill-free"><?php esc_html_e( 'Free Preview', 'custom-lms' ); ?></span>
													<?php elseif ( $can_access ) : ?>
														<span class="clms-pill clms-pill-enrolled"><?php esc_html_e( 'Enrolled', 'custom-lms' ); ?></span>
													<?php else : ?>
														<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Locked', 'custom-lms' ); ?></span>
													<?php endif; ?>
												</div>
											</li>
										<?php endforeach; ?>

										<?php if ( $quiz ) : ?>
											<?php
											$can_quiz          = $user_id && CLMS_Access::can_take_quiz( $user_id, $quiz->ID );
											$is_quiz_completed = $user_id && CLMS_Progress::is_completed( $user_id, $quiz->ID );
											?>
											<li class="clms-topic-item clms-quiz-item <?php echo $can_quiz ? 'is-unlocked' : ( $is_enrolled ? 'is-quiz-pending' : 'is-locked' ); ?>">
												<div class="clms-topic-left">
													<span class="clms-status-icon">📝</span>
													<?php if ( $is_enrolled || $can_quiz ) : ?>
														<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" class="clms-topic-link">
															<?php echo esc_html( $quiz->post_title ); ?>
														</a>
													<?php else : ?>
														<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" 
														   class="clms-topic-link clms-paid-topic-trigger"
														   data-topic-id="<?php echo esc_attr( $quiz->ID ); ?>"
														   data-topic-title="<?php echo esc_attr( $quiz->post_title ); ?>"
														   data-course-id="<?php echo esc_attr( $course->ID ); ?>"
														   data-course-title="<?php echo esc_attr( $course->post_title ); ?>"
														   data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
														   data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
															<?php echo esc_html( $quiz->post_title ); ?>
														</a>
													<?php endif; ?>
												</div>
												<div class="clms-topic-right">
													<?php if ( $is_quiz_completed ) : ?>
														<span class="clms-pill clms-pill-completed">✓ <?php esc_html_e( 'Passed', 'custom-lms' ); ?></span>
													<?php elseif ( $can_quiz ) : ?>
														<span class="clms-pill clms-pill-quiz"><?php esc_html_e( 'Quiz Unlocked', 'custom-lms' ); ?></span>
													<?php elseif ( $is_enrolled ) : ?>
														<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Complete Topics First', 'custom-lms' ); ?></span>
													<?php else : ?>
														<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Locked', 'custom-lms' ); ?></span>
													<?php endif; ?>
												</div>
											</li>
										<?php endif; ?>
									</ul>
								</div>
							</div>
							<?php
							$lesson_index++;
						endforeach;
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
	}

	/**
	 * Render Lesson single view.
	 *
	 * @param WP_Post $lesson  Lesson post.
	 * @param string  $content Original content.
	 * @return string
	 */
	public static function render_lesson_view( $lesson, $content ) {
		$course_id   = get_post_meta( $lesson->ID, '_clms_course_id', true );
		$course      = $course_id ? get_post( $course_id ) : null;
		$topics      = self::get_lesson_topics( $lesson->ID );
		$quiz        = self::get_lesson_quiz( $lesson->ID );
		$user_id     = get_current_user_id();
		$is_enrolled = ( $user_id && $course_id ) ? CLMS_Access::is_enrolled( $user_id, $course_id ) : false;
		$wc_prod_id  = $course_id ? (int) get_post_meta( $course_id, '_clms_wc_product_id', true ) : 0;
		$price_data  = ( $course_id && class_exists( 'CLMS_WooCommerce' ) ) ? CLMS_WooCommerce::get_course_price_data( $course_id ) : array( 'formatted' => __( 'Free', 'custom-lms' ) );

		// Lesson position & prev/next lesson
		$course_lessons = $course_id ? self::get_course_lessons( $course_id ) : array();
		$lesson_num     = 1;
		$total_lessons  = count( $course_lessons );
		$prev_lesson    = null;
		$next_lesson    = null;

		foreach ( $course_lessons as $idx => $cl ) {
			if ( (int) $cl->ID === (int) $lesson->ID ) {
				$lesson_num  = $idx + 1;
				$prev_lesson = $idx > 0 ? $course_lessons[ $idx - 1 ] : null;
				$next_lesson = ( $idx + 1 < $total_lessons ) ? $course_lessons[ $idx + 1 ] : null;
				break;
			}
		}

		// Progress calculation
		$completed_ids   = $user_id ? CLMS_Progress::get_completed_ids( $user_id, null, $lesson->ID ) : array();
		$total_topics    = count( $topics );
		$completed_count = 0;
		$next_topic      = null;

		foreach ( $topics as $t ) {
			if ( in_array( (int) $t->ID, $completed_ids, true ) ) {
				$completed_count++;
			} elseif ( null === $next_topic ) {
				$next_topic = $t;
			}
		}
		if ( null === $next_topic && ! empty( $topics ) ) {
			$next_topic = $topics[0];
		}

		$progress_pct = $total_topics > 0 ? round( ( $completed_count / $total_topics ) * 100 ) : 0;

		ob_start();
		?>
		<div class="clms-single-wrap clms-lesson-page">
			<?php if ( $course ) : ?>
				<div class="clms-breadcrumbs clms-lesson-breadcrumbs">
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="clms-breadcrumb-back">
						← <?php echo esc_html( $course->post_title ); ?>
					</a>
					<span class="clms-breadcrumb-sep">/</span>
					<span class="clms-breadcrumb-current"><?php echo esc_html( $lesson->post_title ); ?></span>
				</div>
			<?php endif; ?>

			<!-- Lesson Hero Overview Card -->
			<div class="clms-lesson-hero-card">
				<div class="clms-lesson-hero-top">
					<div class="clms-lesson-badges">
						<?php if ( $total_lessons > 0 ) : ?>
							<span class="clms-step-number">
								<?php
								/* translators: 1: current lesson number, 2: total lessons */
								echo esc_html( sprintf( __( 'Lesson %1$d of %2$d', 'custom-lms' ), $lesson_num, $total_lessons ) );
								?>
							</span>
						<?php endif; ?>
						<span class="clms-item-count-pill">
							<?php
							/* translators: %d: number of topics */
							echo esc_html( sprintf( _n( '%d Topic', '%d Topics', $total_topics, 'custom-lms' ), $total_topics ) );
							if ( $quiz ) {
								echo ' • ' . esc_html__( '1 Quiz', 'custom-lms' );
							}
							?>
						</span>
					</div>

					<?php if ( $user_id && $total_topics > 0 ) : ?>
						<div class="clms-lesson-progress-wrap">
							<div class="clms-lesson-progress-meta">
								<span>
									<?php
									/* translators: 1: completed count, 2: total topics */
									echo esc_html( sprintf( __( '%1$d of %2$d topics completed', 'custom-lms' ), $completed_count, $total_topics ) );
									?>
								</span>
								<strong><?php echo esc_html( $progress_pct ); ?>%</strong>
							</div>
							<div class="clms-progress-track">
								<div class="clms-progress-fill" style="width: <?php echo esc_attr( $progress_pct ); ?>%;"></div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( trim( wp_strip_all_tags( $content ) ) ) ) : ?>
					<div class="clms-content-body clms-lesson-desc">
						<?php echo wp_kses_post( $content ); ?>
					</div>
				<?php endif; ?>

				<?php 
				$all_topics_completed = ( $total_topics > 0 && $completed_count >= $total_topics );
				if ( $all_topics_completed && $quiz ) :
					$is_quiz_done = $user_id && CLMS_Progress::is_completed( $user_id, $quiz->ID );
					$quiz_cta_label = $is_quiz_done ? __( 'Review Lesson Quiz 📝', 'custom-lms' ) : __( 'Take Lesson Quiz 📝 →', 'custom-lms' );
					?>
					<div class="clms-lesson-hero-actions">
						<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" class="clms-btn clms-btn-primary">
							<?php echo esc_html( $quiz_cta_label ); ?>
						</a>
					</div>
				<?php elseif ( $next_topic ) : ?>
					<?php
					$nt_is_free    = CLMS_Access::is_free_topic( $next_topic->ID );
					$nt_can_access = $user_id && ( $nt_is_free || $is_enrolled );
					$cta_label     = $completed_count > 0 ? __( 'Continue Lesson →', 'custom-lms' ) : __( 'Start Lesson →', 'custom-lms' );
					?>
					<div class="clms-lesson-hero-actions">
						<?php if ( $nt_can_access ) : ?>
							<a href="<?php echo esc_url( get_permalink( $next_topic->ID ) ); ?>" class="clms-btn clms-btn-primary">
								<?php echo esc_html( $cta_label ); ?>
							</a>
						<?php elseif ( $nt_is_free && ! $user_id ) : ?>
							<button type="button"
							        class="clms-btn clms-btn-primary clms-free-auth-trigger"
							        data-topic-id="<?php echo esc_attr( $next_topic->ID ); ?>"
							        data-topic-title="<?php echo esc_attr( $next_topic->post_title ); ?>"
							        data-topic-url="<?php echo esc_url( get_permalink( $next_topic->ID ) ); ?>"
							        data-course-id="<?php echo esc_attr( $course_id ); ?>"
							        data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>">
								<?php echo esc_html( $cta_label ); ?>
							</button>
						<?php else : ?>
							<button type="button"
							        class="clms-btn clms-btn-primary clms-paid-topic-trigger"
							        data-topic-id="<?php echo esc_attr( $next_topic->ID ); ?>"
							        data-topic-title="<?php echo esc_attr( $next_topic->post_title ); ?>"
							        data-course-id="<?php echo esc_attr( $course_id ); ?>"
							        data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>"
							        data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
							        data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
								🔒 <?php esc_html_e( 'Unlock Lesson', 'custom-lms' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Topics & Quiz Card -->
			<div class="clms-lesson-contents-card">
				<div class="clms-lesson-contents-header">
					<h3><?php esc_html_e( 'Topics in this Lesson', 'custom-lms' ); ?></h3>
					<span class="clms-contents-hint"><?php esc_html_e( 'Complete all topics to unlock the lesson quiz', 'custom-lms' ); ?></span>
				</div>

				<ul class="clms-topic-list">
					<?php foreach ( $topics as $t_idx => $topic ) : ?>
						<?php
						$is_free      = CLMS_Access::is_free_topic( $topic->ID );
						$can_access   = $user_id && ( $is_free || $is_enrolled );
						$is_completed = $user_id && in_array( (int) $topic->ID, $completed_ids, true );
						?>
						<li class="clms-topic-item <?php echo $is_completed ? 'is-completed' : ( $is_free ? 'is-free' : ( $can_access ? 'is-enrolled-unlocked' : 'is-locked' ) ); ?>">
							<div class="clms-topic-left">
								<span class="clms-topic-step-num"><?php echo esc_html( $t_idx + 1 ); ?></span>
								<span class="clms-status-icon">
									<?php if ( $is_completed ) : ?>
										<span class="clms-icon-unlocked" title="<?php esc_attr_e( 'Completed', 'custom-lms' ); ?>">✓</span>
									<?php elseif ( $is_free ) : ?>
										<span class="clms-icon-unlock" title="<?php esc_attr_e( 'Free Preview (Login Required)', 'custom-lms' ); ?>">🔓</span>
									<?php elseif ( $can_access ) : ?>
										<span class="clms-icon-play" title="<?php esc_attr_e( 'Ready to Watch', 'custom-lms' ); ?>">▶</span>
									<?php else : ?>
										<span class="clms-icon-lock" title="<?php esc_attr_e( 'Enrolled Students Only', 'custom-lms' ); ?>">🔒</span>
									<?php endif; ?>
								</span>
								<?php if ( $can_access ) : ?>
									<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>" class="clms-topic-link">
										<?php echo esc_html( $topic->post_title ); ?>
									</a>
								<?php elseif ( $is_free && ! $user_id ) : ?>
									<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>"
									   class="clms-topic-link clms-free-auth-trigger"
									   data-topic-id="<?php echo esc_attr( $topic->ID ); ?>"
									   data-topic-title="<?php echo esc_attr( $topic->post_title ); ?>"
									   data-topic-url="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>"
									   data-course-id="<?php echo esc_attr( $course_id ); ?>"
									   data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>">
										<?php echo esc_html( $topic->post_title ); ?>
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>"
									   class="clms-topic-link clms-paid-topic-trigger"
									   data-topic-id="<?php echo esc_attr( $topic->ID ); ?>"
									   data-topic-title="<?php echo esc_attr( $topic->post_title ); ?>"
									   data-course-id="<?php echo esc_attr( $course_id ); ?>"
									   data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>"
									   data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
									   data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
										<?php echo esc_html( $topic->post_title ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="clms-topic-right">
								<?php if ( $is_completed ) : ?>
									<span class="clms-pill clms-pill-completed">✓ <?php esc_html_e( 'Completed', 'custom-lms' ); ?></span>
								<?php elseif ( $is_free ) : ?>
									<span class="clms-pill clms-pill-free"><?php esc_html_e( 'Free Preview', 'custom-lms' ); ?></span>
								<?php elseif ( $can_access ) : ?>
									<span class="clms-pill clms-pill-enrolled"><?php esc_html_e( 'Enrolled', 'custom-lms' ); ?></span>
								<?php else : ?>
									<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Locked', 'custom-lms' ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>

					<?php if ( $quiz ) : ?>
						<?php
						$can_quiz          = $user_id && CLMS_Access::can_take_quiz( $user_id, $quiz->ID );
						$is_quiz_completed = $user_id && CLMS_Progress::is_completed( $user_id, $quiz->ID );
						?>
						<li class="clms-topic-item clms-quiz-item <?php echo $can_quiz ? 'is-unlocked' : ( $is_enrolled ? 'is-quiz-pending' : 'is-locked' ); ?>">
							<div class="clms-topic-left">
								<span class="clms-topic-step-num clms-step-quiz">Q</span>
								<span class="clms-status-icon">📝</span>
								<?php if ( $is_enrolled || $can_quiz ) : ?>
									<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>" class="clms-topic-link">
										<?php echo esc_html( $quiz->post_title ); ?>
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( get_permalink( $quiz->ID ) ); ?>"
									   class="clms-topic-link clms-paid-topic-trigger"
									   data-topic-id="<?php echo esc_attr( $quiz->ID ); ?>"
									   data-topic-title="<?php echo esc_attr( $quiz->post_title ); ?>"
									   data-course-id="<?php echo esc_attr( $course_id ); ?>"
									   data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>"
									   data-product-id="<?php echo esc_attr( $wc_prod_id ); ?>"
									   data-price="<?php echo esc_attr( $price_data['formatted'] ); ?>">
										<?php echo esc_html( $quiz->post_title ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="clms-topic-right">
								<?php if ( $is_quiz_completed ) : ?>
									<span class="clms-pill clms-pill-completed">✓ <?php esc_html_e( 'Passed', 'custom-lms' ); ?></span>
								<?php elseif ( $can_quiz ) : ?>
									<span class="clms-pill clms-pill-quiz"><?php esc_html_e( 'Quiz Unlocked', 'custom-lms' ); ?></span>
								<?php elseif ( $is_enrolled ) : ?>
									<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Complete Topics First', 'custom-lms' ); ?></span>
								<?php else : ?>
									<span class="clms-pill clms-pill-locked"><?php esc_html_e( 'Locked', 'custom-lms' ); ?></span>
								<?php endif; ?>
							</div>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<!-- Lesson Prev / Next Footer Navigation -->
			<?php if ( $prev_lesson || $next_lesson || $course ) : ?>
				<div class="clms-lesson-nav-footer">
					<div class="clms-lesson-nav-prev">
						<?php if ( $prev_lesson ) : ?>
							<a href="<?php echo esc_url( get_permalink( $prev_lesson->ID ) ); ?>" class="clms-btn clms-btn-outline">
								← <?php echo esc_html( $prev_lesson->post_title ); ?>
							</a>
						<?php elseif ( $course ) : ?>
							<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="clms-btn clms-btn-outline">
								← <?php esc_html_e( 'Course Overview', 'custom-lms' ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="clms-lesson-nav-next">
						<?php if ( $next_lesson ) : ?>
							<a href="<?php echo esc_url( get_permalink( $next_lesson->ID ) ); ?>" class="clms-btn clms-btn-secondary">
								<?php echo esc_html( $next_lesson->post_title ); ?> →
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Topic single view.
	 *
	 * @param WP_Post $topic   Topic post.
	 * @param string  $content Original content.
	 * @return string
	 */
	public static function render_topic_view( $topic, $content ) {
		$user_id   = get_current_user_id();
		$can_view  = CLMS_Access::can_view_topic( $user_id, $topic->ID );
		$lesson_id = get_post_meta( $topic->ID, '_clms_lesson_id', true );
		$course_id = get_post_meta( $topic->ID, '_clms_course_id', true );
		$video_url = get_post_meta( $topic->ID, '_clms_video_url', true );

		$lesson  = $lesson_id ? get_post( $lesson_id ) : null;
		$course  = $course_id ? get_post( $course_id ) : null;
		$is_free = CLMS_Access::is_free_topic( $topic->ID );

		// If user cannot view, render the locked content state!
		if ( ! $can_view ) {
			return self::render_locked_state( $topic, 'topic', $course );
		}

		ob_start();
		?>
		<div class="clms-single-wrap">
			<div class="clms-breadcrumbs">
				<?php if ( $course ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>"><?php echo esc_html( $course->post_title ); ?></a> »
				<?php endif; ?>
				<?php if ( $lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a> »
				<?php endif; ?>
				<span><?php echo esc_html( $topic->post_title ); ?></span>
			</div>

			<div class="clms-topic-badge-row">
				<?php if ( $is_free ) : ?>
					<span class="clms-pill clms-pill-free">🔓 <?php esc_html_e( 'Free Preview Topic', 'custom-lms' ); ?></span>
				<?php else : ?>
					<span class="clms-pill clms-pill-locked">🔒 <?php esc_html_e( 'Enrolled Students Content', 'custom-lms' ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $video_url ) : ?>
				<div class="clms-video-container" style="margin: 20px 0;">
					<?php echo wp_oembed_get( $video_url ); ?>
				</div>
			<?php endif; ?>

			<div class="clms-content-body">
				<?php echo wp_kses_post( $content ); ?>
			</div>

			<!-- Topic Completion Bar & Action -->
			<?php if ( is_user_logged_in() ) : ?>
				<?php
				$is_completed = CLMS_Progress::is_completed( $user_id, $topic->ID );
				$all_lesson_topics = self::get_lesson_topics( $lesson_id );
				$completed_ids = CLMS_Progress::get_completed_ids( $user_id, null, $lesson_id );
				$lesson_quiz = self::get_lesson_quiz( $lesson_id );

				// Determine next topic / quiz link
				$next_url = '';
				$next_label = '';
				$found_current = false;
				foreach ( $all_lesson_topics as $lt ) {
					if ( $found_current ) {
						$next_url = get_permalink( $lt->ID );
						$next_label = __( 'Next Topic →', 'custom-lms' );
						break;
					}
					if ( (int) $lt->ID === (int) $topic->ID ) {
						$found_current = true;
					}
				}
				if ( ! $next_url && $lesson_quiz ) {
					$next_url = get_permalink( $lesson_quiz->ID );
					$next_label = __( 'Take Lesson Quiz →', 'custom-lms' );
				}
				?>
				<div class="clms-topic-action-bar" style="margin-top:36px;padding:24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
					<div>
						<?php if ( $is_completed ) : ?>
							<span style="color:#15803d;font-weight:700;display:flex;align-items:center;gap:6px;">
								<span style="background:#dcfce7;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">✓</span>
								<?php esc_html_e( 'You have completed this topic', 'custom-lms' ); ?>
							</span>
						<?php else : ?>
							<button type="button" class="clms-btn clms-btn-primary clms-complete-topic-btn" data-topic-id="<?php echo esc_attr( $topic->ID ); ?>">
								✓ <?php esc_html_e( 'Mark as Complete', 'custom-lms' ); ?>
							</button>
						<?php endif; ?>
					</div>

					<?php if ( $next_url ) : ?>
						<div>
							<a href="<?php echo esc_url( $next_url ); ?>" class="clms-btn clms-btn-outline">
								<?php echo esc_html( $next_label ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Quiz single view.
	 *
	 * @param WP_Post $quiz    Quiz post.
	 * @param string  $content Original content.
	 * @return string
	 */
	public static function render_quiz_view( $quiz, $content ) {
		$user_id   = get_current_user_id();
		$can_take  = CLMS_Access::can_take_quiz( $user_id, $quiz->ID );
		$lesson_id = (int) get_post_meta( $quiz->ID, '_clms_lesson_id', true );
		$course_id = (int) get_post_meta( $quiz->ID, '_clms_course_id', true );

		if ( ! $course_id && $lesson_id ) {
			$course_id = (int) get_post_meta( $lesson_id, '_clms_course_id', true );
		}

		$lesson = $lesson_id ? get_post( $lesson_id ) : null;
		$course = $course_id ? get_post( $course_id ) : null;

		// If user cannot take quiz, render locked screen!
		$stats    = class_exists( 'CLMS_Quiz' ) ? CLMS_Quiz::get_attempt_stats( $user_id, $quiz->ID ) : array();
		$can_take = CLMS_Access::can_take_quiz( $user_id, $quiz->ID );

		// If user cannot take quiz:
		if ( ! $can_take ) {
			// If user has already completed the quiz and has no attempts remaining:
			if ( ! empty( $stats['has_completed'] ) && empty( $stats['can_attempt'] ) ) {
				return self::render_completed_quiz_view( $quiz, $course, $lesson, $stats );
			}
			return self::render_locked_state( $quiz, 'quiz', $course, $lesson_id );
		}

		$pass_percent = (int) get_post_meta( $quiz->ID, '_clms_pass_percent', true );
		if ( ! $pass_percent ) {
			$pass_percent = 70;
		}
		$max_attempts = (int) get_post_meta( $quiz->ID, '_clms_max_attempts', true );

		// Check previous attempts
		global $wpdb;
		$table_attempts = CLMS_DB::get_quiz_attempts_table();
		$past_attempts  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_attempts} WHERE user_id = %d AND quiz_id = %d ORDER BY submitted_at DESC",
				$user_id,
				$quiz->ID
			)
		);
		$attempt_count  = count( $past_attempts );
		$latest_attempt = ! empty( $past_attempts ) ? $past_attempts[0] : null;
		$attempt_count  = isset( $stats['attempts_count'] ) ? $stats['attempts_count'] : 0;
		$total_allowed  = isset( $stats['total_allowed'] ) ? $stats['total_allowed'] : 1;
		$latest_attempt = isset( $stats['latest_attempt'] ) ? $stats['latest_attempt'] : null;

		$questions_json = get_post_meta( $quiz->ID, '_clms_questions', true );
		$questions      = ! empty( $questions_json ) ? json_decode( $questions_json, true ) : array();

		ob_start();
		?>
		<div class="clms-single-wrap clms-quiz-container">
			<div class="clms-breadcrumbs">
				<?php if ( $course ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>"><?php echo esc_html( $course->post_title ); ?></a> »
				<?php endif; ?>
				<?php if ( $lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a> »
				<?php endif; ?>
				<span><?php echo esc_html( $quiz->post_title ); ?></span>
			</div>

			<div class="clms-quiz-header-card">
				<div class="clms-quiz-header-badge">
					<span class="clms-pill clms-pill-quiz">📝 <?php esc_html_e( 'Lesson Assessment', 'custom-lms' ); ?></span>
				</div>
				<h1 class="clms-quiz-page-title"><?php echo esc_html( $quiz->post_title ); ?></h1>

				<div class="clms-quiz-meta-grid">
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Passing Score', 'custom-lms' ); ?></span>
						<span class="clms-meta-val"><?php echo esc_html( $pass_percent ); ?>%</span>
					</div>
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Questions', 'custom-lms' ); ?></span>
						<span class="clms-meta-val"><?php echo esc_html( count( $questions ) ); ?></span>
					</div>
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Attempt Limit', 'custom-lms' ); ?></span>
						<span class="clms-meta-val">
							<?php
							if ( $max_attempts > 0 ) {
								/* translators: 1: taken, 2: max */
								echo esc_html( sprintf( __( '%1$d / %2$d taken', 'custom-lms' ), $attempt_count, $max_attempts ) );
							} else {
								esc_html_e( 'Unlimited', 'custom-lms' );
							}
							echo esc_html( sprintf( __( '%1$d of %2$d taken', 'custom-lms' ), $attempt_count, $total_allowed ) );
							?>
						</span>
					</div>
					<?php if ( $latest_attempt ) : ?>
						<div class="clms-quiz-meta-col">
							<span class="clms-meta-label"><?php esc_html_e( 'Latest Result', 'custom-lms' ); ?></span>
							<span class="clms-meta-val <?php echo 'passed' === $latest_attempt->status ? 'clms-text-pass' : 'clms-text-fail'; ?>">
								<?php echo esc_html( strtoupper( $latest_attempt->status ) ); ?> (<?php echo esc_html( $latest_attempt->score_percent ); ?>%)
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( ! empty( $content ) ) : ?>
				<div class="clms-content-body" style="margin-bottom: 24px;">
					<?php echo wp_kses_post( $content ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $stats['retake_granted'] ) || $attempt_count > 0 ) : ?>
				<div class="clms-retake-notice-banner" style="margin-bottom:24px;padding:14px 18px;background:#f0fdf4;border:1px solid #bbf7d0;border-left:5px solid #16a34a;border-radius:8px;">
					<strong style="color:#15803d;font-size:15px;display:block;margin-bottom:4px;">
						🎉 <?php esc_html_e( 'Admin Permission Granted: Quiz Retake Unlocked', 'custom-lms' ); ?>
					</strong>
					<span style="color:#166534;font-size:13px;">
						<?php
						printf(
							/* translators: 1: attempt number, 2: total allowed */
							esc_html__( 'You have been authorized by the administrator to attempt this quiz again. (Attempt #%1$d of %2$d).', 'custom-lms' ),
							$attempt_count + 1,
							$total_allowed
						);
						?>
					</span>
				</div>
			<?php endif; ?>

			<!-- Submission Result Banner (Populated via JS) -->
			<div id="clms-quiz-live-result" style="display:none;margin-bottom:28px;"></div>

			<?php if ( empty( $questions ) ) : ?>
				<div class="clms-quiz-empty-box" style="padding:30px;background:#f8fafc;border-radius:12px;text-align:center;color:#64748b;">
					<p><?php esc_html_e( 'No questions available for this quiz yet.', 'custom-lms' ); ?></p>
				</div>
			<?php else : ?>
				<form id="clms-quiz-form" class="clms-quiz-form" data-quiz-id="<?php echo esc_attr( $quiz->ID ); ?>">
					<div class="clms-questions-list">
						<?php
						// Question order randomization for every attempt
						$shuffled_questions = $questions;
						if ( is_array( $shuffled_questions ) && count( $shuffled_questions ) > 1 ) {
							shuffle( $shuffled_questions );
						}
						?>
						<?php foreach ( $shuffled_questions as $q_display_idx => $q ) : ?>
							<?php
							$qid     = isset( $q['id'] ) ? $q['id'] : ( $q_display_idx + 1 );
							$qtext   = isset( $q['text'] ) ? $q['text'] : '';
							$options = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : array();

							// Answer options order randomization while preserving original index for server-side evaluation
							$shuffled_options = array();
							foreach ( $options as $orig_idx => $opt_val ) {
								$opt_text = is_array( $opt_val ) ? ( isset( $opt_val['text'] ) ? $opt_val['text'] : '' ) : $opt_val;
								$shuffled_options[] = array(
									'orig_idx' => $orig_idx,
									'label'    => $opt_text,
								);
							}
							if ( count( $shuffled_options ) > 1 ) {
								shuffle( $shuffled_options );
							}
							?>
							<div class="clms-quiz-question-card" data-question-id="<?php echo esc_attr( $qid ); ?>">
								<div class="clms-question-header">
									<span class="clms-question-number"><?php echo esc_html( sprintf( __( 'Question %d of %d', 'custom-lms' ), $q_display_idx + 1, count( $questions ) ) ); ?></span>
									<h3 class="clms-question-text"><?php echo esc_html( $qtext ); ?></h3>
								</div>

								<div class="clms-options-group">
									<?php foreach ( $shuffled_options as $s_opt ) : ?>
										<?php $opt_id = 'clms_q_' . esc_attr( $qid ) . '_opt_' . esc_attr( $s_opt['orig_idx'] ); ?>
										<label class="clms-option-label" for="<?php echo esc_attr( $opt_id ); ?>">
											<input type="radio" 
											       id="<?php echo esc_attr( $opt_id ); ?>" 
											       name="answers[<?php echo esc_attr( $qid ); ?>]" 
											       value="<?php echo esc_attr( $s_opt['orig_idx'] ); ?>" 
											       required />
											<span class="clms-option-text"><?php echo esc_html( $s_opt['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>

								<!-- Feedback container revealed after submission -->
								<div class="clms-question-feedback" style="display:none;"></div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="clms-quiz-submit-bar">
						<button type="submit" id="clms-quiz-submit-btn" class="clms-btn clms-btn-primary clms-quiz-submit-btn">
							<?php esc_html_e( 'Submit Quiz Answers', 'custom-lms' ); ?>
						</button>
						<span id="clms-quiz-spinner" style="display:none;margin-left:14px;color:#64748b;font-weight:600;">
							⏳ <?php esc_html_e( 'Evaluating your answers...', 'custom-lms' ); ?>
						</span>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Quiz Completed View with Single Submission Lock.
	 *
	 * Displayed when a user has already submitted the quiz and has no remaining attempts.
	 *
	 * @param WP_Post $quiz Quiz post object.
	 * @param WP_Post $course Parent course post object.
	 * @param WP_Post $lesson Parent lesson post object.
	 * @param array   $stats Attempt statistics.
	 * @return string HTML output.
	 */
	public static function render_completed_quiz_view( $quiz, $course, $lesson, $stats ) {
		$user_id        = get_current_user_id();
		$latest_attempt = isset( $stats['latest_attempt'] ) ? $stats['latest_attempt'] : null;
		$pass_percent   = (int) get_post_meta( $quiz->ID, '_clms_pass_percent', true );
		if ( ! $pass_percent ) {
			$pass_percent = 70;
		}

		$answers_log = array();
		if ( $latest_attempt && ! empty( $latest_attempt->answers_data ) ) {
			$answers_log = json_decode( $latest_attempt->answers_data, true );
		}

		$is_passed = $latest_attempt && ( 'passed' === $latest_attempt->status );

		ob_start();
		?>
		<div class="clms-single-wrap clms-quiz-container">
			<div class="clms-breadcrumbs">
				<?php if ( $course ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>"><?php echo esc_html( $course->post_title ); ?></a> »
				<?php endif; ?>
				<?php if ( $lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>"><?php echo esc_html( $lesson->post_title ); ?></a> »
				<?php endif; ?>
				<span><?php echo esc_html( $quiz->post_title ); ?></span>
			</div>

			<!-- Quiz Header -->
			<div class="clms-quiz-header-card">
				<div class="clms-quiz-header-badge">
					<span class="clms-pill clms-pill-completed">✓ <?php esc_html_e( 'Quiz Completed', 'custom-lms' ); ?></span>
				</div>
				<h1 class="clms-quiz-page-title"><?php echo esc_html( $quiz->post_title ); ?></h1>

				<div class="clms-quiz-meta-grid">
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Passing Score', 'custom-lms' ); ?></span>
						<span class="clms-meta-val"><?php echo esc_html( $pass_percent ); ?>%</span>
					</div>
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Attempts Taken', 'custom-lms' ); ?></span>
						<span class="clms-meta-val"><?php echo esc_html( $stats['attempts_count'] . ' of ' . $stats['total_allowed'] ); ?></span>
					</div>
					<div class="clms-quiz-meta-col">
						<span class="clms-meta-label"><?php esc_html_e( 'Status', 'custom-lms' ); ?></span>
						<span class="clms-meta-val" style="color:<?php echo $is_passed ? '#15803d' : '#b91c1c'; ?>;font-weight:700;">
							<?php echo esc_html( $is_passed ? __( 'PASSED', 'custom-lms' ) : __( 'FAILED', 'custom-lms' ) ); ?>
						</span>
					</div>
					<?php if ( $latest_attempt ) : ?>
						<div class="clms-quiz-meta-col">
							<span class="clms-meta-label"><?php esc_html_e( 'Latest Score', 'custom-lms' ); ?></span>
							<span class="clms-meta-val" style="color:<?php echo $is_passed ? '#15803d' : '#b91c1c'; ?>;font-weight:700;">
								<?php echo esc_html( $latest_attempt->score_percent ); ?>%
							</span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Prominent Completed & Retake Restriction Banner -->
			<div class="clms-quiz-completed-banner" style="background:#ffffff;border:1px solid #e2e8f0;border-left:5px solid #2563eb;border-radius:12px;padding:24px 28px;margin-bottom:28px;box-shadow:0 1px 3px rgba(0,0,0,0.05);display:flex;gap:18px;align-items:flex-start;">
				<div style="font-size:28px;line-height:1;">🔒</div>
				<div style="flex:1;">
					<h3 style="margin:0 0 6px 0;font-size:18px;color:#0f172a;font-weight:700;">
						<?php esc_html_e( 'You have already completed this quiz.', 'custom-lms' ); ?>
					</h3>
					<p style="margin:0 0 10px 0;color:#475569;font-size:14px;line-height:1.6;">
						<?php esc_html_e( 'This quiz allows one attempt only. Your answers have been evaluated and your result report has been sent to your registered email address. Quiz submissions are now closed.', 'custom-lms' ); ?>
					</p>
					<div style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#64748b;background:#f8fafc;padding:6px 12px;border-radius:6px;border:1px solid #e2e8f0;">
						ℹ️ <?php esc_html_e( 'Additional attempts require authorization from an administrator.', 'custom-lms' ); ?>
					</div>
				</div>
			</div>

			<!-- Admin Testing Quick Action Bar (Visible only to Administrators) -->
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="clms-admin-test-bar" style="background:#fef3c7;border:1px solid #fde68a;border-left:5px solid #f59e0b;border-radius:8px;padding:14px 18px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
					<div>
						<strong style="color:#92400e;font-size:14px;display:block;">
							⚙️ <?php esc_html_e( 'Administrator Testing Control', 'custom-lms' ); ?>
						</strong>
						<span style="color:#78350f;font-size:12px;">
							<?php esc_html_e( 'You are logged in as an Administrator. To test another attempt, grant yourself a retake below:', 'custom-lms' ); ?>
						</span>
					</div>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=clms_course&page=clms-quiz-attempts&action=clms_grant_retake&user_id=' . $user_id . '&quiz_id=' . $quiz->ID . '&redirect_to=' . rawurlencode( get_permalink( $quiz->ID ) ) ), 'clms_retake_action' ) ); ?>" class="clms-btn clms-btn-sm clms-btn-primary" style="text-decoration:none;">
						⚡ <?php esc_html_e( 'Grant Myself Another Attempt (+1 Retake)', 'custom-lms' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<!-- Submission Summary Box -->
			<?php if ( $latest_attempt ) : ?>
				<div class="clms-attempt-summary-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;margin-bottom:28px;">
					<h3 style="margin-top:0;margin-bottom:16px;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;">
						📊 <?php echo esc_html( sprintf( __( 'Your Results (Attempt #%d)', 'custom-lms' ), $latest_attempt->attempt_number ) ); ?>
					</h3>
					<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;background:#f8fafc;padding:16px 20px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:20px;">
						<div>
							<div style="font-size:12px;color:#64748b;font-weight:600;"><?php esc_html_e( 'Score Obtained', 'custom-lms' ); ?></div>
							<div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:4px;">
								<?php echo esc_html( "{$latest_attempt->obtained_marks} / {$latest_attempt->total_marks}" ); ?>
								<span style="font-size:14px;font-weight:600;color:#64748b;">(<?php echo esc_html( $latest_attempt->score_percent ); ?>%)</span>
							</div>
						</div>
						<div>
							<div style="font-size:12px;color:#64748b;font-weight:600;"><?php esc_html_e( 'Passing Mark', 'custom-lms' ); ?></div>
							<div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:4px;">
								<?php echo esc_html( $pass_percent ); ?>%
							</div>
						</div>
						<div>
							<div style="font-size:12px;color:#64748b;font-weight:600;"><?php esc_html_e( 'Result Status', 'custom-lms' ); ?></div>
							<div style="margin-top:4px;">
								<span style="display:inline-block;padding:4px 12px;background:<?php echo $is_passed ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $is_passed ? '#15803d' : '#b91c1c'; ?>;border-radius:9999px;font-weight:800;font-size:12px;">
									<?php echo esc_html( $is_passed ? __( 'PASSED', 'custom-lms' ) : __( 'FAILED', 'custom-lms' ) ); ?>
								</span>
							</div>
						</div>
						<div>
							<div style="font-size:12px;color:#64748b;font-weight:600;"><?php esc_html_e( 'Submitted On', 'custom-lms' ); ?></div>
							<div style="font-size:13px;font-weight:600;color:#334155;margin-top:6px;">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest_attempt->submitted_at ) ) ); ?>
							</div>
						</div>
					</div>

					<!-- Detailed Question Review -->
					<?php if ( ! empty( $answers_log ) && is_array( $answers_log ) ) : ?>
						<h4 style="margin:20px 0 12px 0;font-size:15px;color:#0f172a;">
							📝 <?php esc_html_e( 'Review Your Answers:', 'custom-lms' ); ?>
						</h4>
						<div class="clms-completed-questions-review">
							<?php foreach ( $answers_log as $idx => $item ) : ?>
								<div class="clms-review-card <?php echo $item['is_correct'] ? 'is-correct-card' : 'is-wrong-card'; ?>" style="margin-bottom:14px;padding:16px 20px;border-radius:8px;background:#f8fafc;border-left:4px solid <?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;border:1px solid #e2e8f0;border-left-width:4px;">
									<div style="font-weight:700;color:#0f172a;font-size:14px;margin-bottom:8px;">
										<?php echo esc_html( ( $idx + 1 ) . '. ' . $item['question'] ); ?>
									</div>
									<div style="font-size:13px;margin-bottom:4px;color:<?php echo $item['is_correct'] ? '#15803d' : '#b91c1c'; ?>;">
										<?php esc_html_e( 'Your Answer:', 'custom-lms' ); ?> <strong><?php echo esc_html( $item['user_answer'] ); ?></strong>
										<?php if ( $item['is_correct'] ) : ?>
											<span style="margin-left:8px;font-weight:700;">✓ Correct</span>
										<?php else : ?>
											<span style="margin-left:8px;font-weight:700;">✗ Incorrect</span>
										<?php endif; ?>
									</div>
									<?php if ( ! $item['is_correct'] && ! empty( $item['correct_answer'] ) ) : ?>
										<div style="font-size:13px;color:#475569;margin-top:4px;">
											<?php esc_html_e( 'Correct Answer:', 'custom-lms' ); ?> <strong style="color:#15803d;"><?php echo esc_html( $item['correct_answer'] ); ?></strong>
										</div>
									<?php endif; ?>
									<?php if ( ! empty( $item['explanation'] ) ) : ?>
										<div style="font-size:12px;color:#64748b;margin-top:6px;font-style:italic;">
											💡 <?php echo esc_html( $item['explanation'] ); ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Navigation Bar -->
			<div class="clms-completed-footer-nav" style="display:flex;gap:12px;align-items:center;margin-top:28px;">
				<?php if ( $course ) : ?>
					<a href="<?php echo esc_url( get_permalink( $course->ID ) ); ?>" class="clms-btn clms-btn-primary">
						&larr; <?php esc_html_e( 'Back to Course Curriculum', 'custom-lms' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $lesson ) : ?>
					<a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>" class="clms-btn clms-btn-outline">
						<?php esc_html_e( 'Back to Lesson', 'custom-lms' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Locked Content State with Excerpt, Lock Icon, and CTA.
	 *
	 * @param WP_Post      $post      Post object.
	 * @param string       $type      'topic' or 'quiz'.
	 * @param WP_Post|null $course    Parent course.
	 * @param int|null     $lesson_id Parent lesson ID.
	 * @return string
	 */
	public static function render_locked_state( $post, $type, $course = null, $lesson_id = null ) {
		$user_id = get_current_user_id();

		// Add no-cache headers to prevent proxy/caching leaks.
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		$title   = $post->post_title;
		$excerpt = ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 20 );

		$course_url    = $course ? get_permalink( $course->ID ) : home_url();
		$price_data    = $course && class_exists( 'CLMS_WooCommerce' ) ? CLMS_WooCommerce::get_course_price_data( $course->ID ) : array( 'formatted' => __( 'Free', 'custom-lms' ) );
		$wc_product_id = $course ? (int) get_post_meta( $course->ID, '_clms_wc_product_id', true ) : 0;

		$enroll_url = $course_url;
		if ( $wc_product_id && function_exists( 'wc_get_checkout_url' ) ) {
			$enroll_url = add_query_arg( 'add-to-cart', $wc_product_id, wc_get_checkout_url() );
		}

		$is_enrolled = ( $user_id && $course ) ? CLMS_Access::is_enrolled( $user_id, $course->ID ) : false;
		$topics_completed = ( $user_id && $lesson_id ) ? CLMS_Access::lesson_topics_completed( $user_id, $lesson_id ) : false;
		$incomplete_topics = array();

		$is_free_topic = ( 'topic' === $type && CLMS_Access::is_free_topic( $post->ID ) );
		$reason        = __( 'This content is exclusively for enrolled students.', 'custom-lms' );

		if ( 'topic' === $type && ! $user_id && $is_free_topic ) {
			$reason = __( 'This first topic is free! Please log in or create an account to watch it.', 'custom-lms' );
		} elseif ( 'quiz' === $type ) {
			if ( ! $user_id || ! $is_enrolled ) {
				$reason = __( 'You must be enrolled in the course to access this quiz.', 'custom-lms' );
			} elseif ( ! $topics_completed ) {
				$reason = __( 'This quiz is locked until you complete all topics in this lesson.', 'custom-lms' );
				// Find incomplete topics
				if ( $lesson_id ) {
					$all_topics = self::get_lesson_topics( $lesson_id );
					$completed_ids = CLMS_Progress::get_completed_ids( $user_id, null, $lesson_id );
					foreach ( $all_topics as $lt ) {
						if ( ! in_array( (int) $lt->ID, $completed_ids, true ) ) {
							$incomplete_topics[] = $lt;
						}
					}
				}
			} else {
				$reason = __( 'You have reached the maximum allowed attempts for this quiz.', 'custom-lms' );
			}
		}

		ob_start();
		?>
		<div class="clms-locked-card">
			<div class="clms-locked-icon-wrap">
				<span class="clms-locked-icon">🔒</span>
			</div>

			<h2 class="clms-locked-title"><?php echo esc_html( $title ); ?></h2>
			<p class="clms-locked-reason"><?php echo esc_html( $reason ); ?></p>

			<?php if ( ! empty( $incomplete_topics ) ) : ?>
				<div class="clms-locked-incomplete-box" style="margin: 20px 0; text-align: left; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px 22px;">
					<h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700; color: #334155;">
						<?php esc_html_e( 'Topics required to unlock this quiz:', 'custom-lms' ); ?>
					</h4>
					<ul style="margin: 0; padding-left: 20px;">
						<?php foreach ( $incomplete_topics as $it ) : ?>
							<li style="margin-bottom: 6px;">
								<a href="<?php echo esc_url( get_permalink( $it->ID ) ); ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;">
									<?php echo esc_html( $it->post_title ); ?> &rarr;
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php elseif ( ! empty( $excerpt ) ) : ?>
				<div class="clms-locked-preview">
					<p><strong><?php esc_html_e( 'Preview:', 'custom-lms' ); ?></strong> <?php echo esc_html( $excerpt ); ?></p>
				</div>
			<?php endif; ?>

			<div class="clms-locked-actions">
				<?php if ( ! is_user_logged_in() && $is_free_topic ) : ?>
					<button type="button"
					        class="clms-btn clms-btn-primary clms-free-auth-trigger"
					        data-topic-id="<?php echo esc_attr( $post->ID ); ?>"
					        data-topic-title="<?php echo esc_attr( $post->post_title ); ?>"
					        data-topic-url="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"
					        data-course-id="<?php echo esc_attr( $course ? $course->ID : 0 ); ?>"
					        data-course-title="<?php echo esc_attr( $course ? $course->post_title : '' ); ?>">
						🔓 <?php esc_html_e( 'Log In / Register to Watch Free', 'custom-lms' ); ?>
					</button>
				<?php else : ?>
					<?php if ( ! is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink( $post->ID ) ) ); ?>" class="clms-btn clms-btn-secondary">
							<?php esc_html_e( 'Log In', 'custom-lms' ); ?>
						</a>
					<?php endif; ?>

					<?php if ( 'quiz' === $type && $is_enrolled && ! $topics_completed && ! empty( $incomplete_topics ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $incomplete_topics[0]->ID ) ); ?>" class="clms-btn clms-btn-primary">
							<?php esc_html_e( 'Start Next Incomplete Topic →', 'custom-lms' ); ?>
						</a>
					<?php elseif ( $course && ! $is_enrolled ) : ?>
						<a href="<?php echo esc_url( $enroll_url ); ?>" class="clms-btn clms-btn-primary">
							<?php
							/* translators: %s: formatted price */
							echo esc_html( sprintf( __( 'Enroll in Course (%s)', 'custom-lms' ), $price_data['formatted'] ) );
							?>
						</a>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $course ) : ?>
					<a href="<?php echo esc_url( $course_url ); ?>" class="clms-btn clms-btn-outline">
						<?php esc_html_e( 'View Full Curriculum', 'custom-lms' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Helper: Get lessons for a course ordered by _clms_order.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get_course_lessons( $course_id ) {
		$all_lessons = get_posts(
			array(
				'post_type'      => 'clms_lesson',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$lessons = array();
		foreach ( $all_lessons as $l ) {
			if ( (int) get_post_meta( $l->ID, '_clms_course_id', true ) === (int) $course_id ) {
				$lessons[] = $l;
			}
		}
		usort(
			$lessons,
			function( $a, $b ) {
				$oa = (int) get_post_meta( $a->ID, '_clms_order', true );
				$ob = (int) get_post_meta( $b->ID, '_clms_order', true );
				return $oa <=> $ob;
			}
		);
		return $lessons;
	}

	/**
	 * Helper: Get topics for a lesson ordered by _clms_order.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array
	 */
	public static function get_lesson_topics( $lesson_id ) {
		$all_topics = get_posts(
			array(
				'post_type'      => 'clms_topic',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$topics = array();
		foreach ( $all_topics as $t ) {
			if ( (int) get_post_meta( $t->ID, '_clms_lesson_id', true ) === (int) $lesson_id ) {
				$topics[] = $t;
			}
		}

		usort(
			$topics,
			function( $a, $b ) {
				$oa = (int) get_post_meta( $a->ID, '_clms_order', true );
				$ob = (int) get_post_meta( $b->ID, '_clms_order', true );
				return $oa <=> $ob;
			}
		);

		return $topics;
	}

	/**
	 * Helper: Get quiz for a lesson.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return WP_Post|null
	 */
	public static function get_lesson_quiz( $lesson_id ) {
		if ( ! $lesson_id ) {
			return null;
		}

		$allowed_statuses = ( is_admin() || current_user_can( 'edit_posts' ) ) ? array( 'publish', 'draft' ) : array( 'publish' );

		// 1. Direct assignment check via lesson meta.
		$quiz_id = (int) get_post_meta( $lesson_id, '_clms_quiz_id', true );
		if ( $quiz_id > 0 ) {
			$quiz = get_post( $quiz_id );
			if ( $quiz && 'clms_quiz' === $quiz->post_type && in_array( $quiz->post_status, $allowed_statuses, true ) ) {
				return $quiz;
			}
		}

		// 2. Reverse lookup by quiz's _clms_lesson_id meta.
		$all_quizzes = get_posts(
			array(
				'post_type'      => 'clms_quiz',
				'post_status'    => $allowed_statuses,
				'posts_per_page' => -1,
			)
		);

		foreach ( $all_quizzes as $q ) {
			if ( (int) get_post_meta( $q->ID, '_clms_lesson_id', true ) === (int) $lesson_id ) {
				return $q;
			}
		}

		return null;
	}

	/**
	 * AJAX Login handler.
	 */
	public static function ajax_login() {
		check_ajax_referer( 'clms_auth_nonce', 'nonce' );

		$user_login    = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$user_password = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$product_id    = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$redirect_to   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		$credentials = array(
			'user_login'    => $user_login,
			'user_password' => $user_password,
			'remember'      => true,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			// Strip all raw HTML tags from WP error messages
			$clean_message = wp_strip_all_tags( $user->get_error_message() );
			// Remove any leftover prefix like 'Error:'
			$clean_message = preg_replace( '/^error:\s*/i', '', trim( $clean_message ) );
			wp_send_json_error( array( 'message' => $clean_message ) );
		}

		wp_set_current_user( $user->ID );

		if ( ! empty( $redirect_to ) ) {
			$target_url = wp_validate_redirect( $redirect_to, home_url() );
			wp_send_json_success(
				array(
					'message'      => __( 'Login successful! Opening your free topic...', 'custom-lms' ),
					'redirect_url' => $target_url,
					'checkout_url' => $target_url,
				)
			);
		}

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url();
		if ( $product_id ) {
			$checkout_url = add_query_arg( 'add-to-cart', $product_id, $checkout_url );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Login successful! Redirecting to checkout...', 'custom-lms' ),
				'redirect_url' => $checkout_url,
				'checkout_url' => $checkout_url,
			)
		);
	}

	/**
	 * AJAX Register handler.
	 */
	public static function ajax_register() {
		check_ajax_referer( 'clms_auth_nonce', 'nonce' );

		$username    = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password    = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all registration fields.', 'custom-lms' ) ) );
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'Username is already taken.', 'custom-lms' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email is already registered.', 'custom-lms' ) ) );
		}

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			$clean_message = wp_strip_all_tags( $user_id->get_error_message() );
			$clean_message = preg_replace( '/^error:\s*/i', '', trim( $clean_message ) );
			wp_send_json_error( array( 'message' => $clean_message ) );
		}

		// Auto sign-in the newly registered user
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		if ( ! empty( $redirect_to ) ) {
			$target_url = wp_validate_redirect( $redirect_to, home_url() );
			wp_send_json_success(
				array(
					'message'      => __( 'Account created! Opening your free topic...', 'custom-lms' ),
					'redirect_url' => $target_url,
					'checkout_url' => $target_url,
				)
			);
		}

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url();
		if ( $product_id ) {
			$checkout_url = add_query_arg( 'add-to-cart', $product_id, $checkout_url );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Account created! Redirecting to checkout...', 'custom-lms' ),
				'redirect_url' => $checkout_url,
				'checkout_url' => $checkout_url,
			)
		);
	}

	/**
	 * Render Purchase & Auth Modal in wp_footer.
	 */
	public static function render_purchase_modal() {
		?>
		<div id="clms-modal-backdrop" class="clms-modal-backdrop" style="display:none;">
			<div class="clms-modal-container">
				<button type="button" class="clms-modal-close" id="clms-modal-close-btn">&times;</button>

				<!-- 1. Purchase Step (When user is logged in) -->
				<div id="clms-modal-step-buy" class="clms-modal-view" style="display:none;">
					<div class="clms-modal-header">
						<span class="clms-modal-icon">🔒</span>
						<h3 class="clms-modal-title"><?php esc_html_e( 'Unlock This Course', 'custom-lms' ); ?></h3>
						<p class="clms-modal-subtitle">
							<strong id="clms-modal-topic-title"></strong> <?php esc_html_e( 'is a paid topic.', 'custom-lms' ); ?>
						</p>
					</div>

					<div class="clms-modal-course-summary">
						<div class="clms-summary-course-name" id="clms-modal-course-title"></div>
						<div class="clms-summary-price" id="clms-modal-course-price"></div>
					</div>

					<div class="clms-modal-actions">
						<a href="#" id="clms-modal-buynow-btn" class="clms-btn clms-btn-primary clms-btn-large">
							🛒 <?php esc_html_e( 'Buy Now & Get Instant Access', 'custom-lms' ); ?>
						</a>
						<button type="button" class="clms-btn clms-btn-outline clms-modal-cancel">
							<?php esc_html_e( 'Cancel', 'custom-lms' ); ?>
						</button>
					</div>
				</div>

				<!-- 2. Login/Register Step (When user is NOT logged in) -->
				<div id="clms-modal-step-auth" class="clms-modal-view" style="display:none;">
					<div class="clms-modal-header">
						<span class="clms-modal-icon">🔐</span>
						<h3 class="clms-modal-title"><?php esc_html_e( 'Sign in to Purchase', 'custom-lms' ); ?></h3>
						<p class="clms-modal-subtitle">
							<?php esc_html_e( 'Please log in or create an account to purchase and unlock this course.', 'custom-lms' ); ?>
						</p>
					</div>

					<div class="clms-auth-tabs">
						<button type="button" class="clms-tab-btn is-active" id="clms-tab-login"><?php esc_html_e( 'Log In', 'custom-lms' ); ?></button>
						<button type="button" class="clms-tab-btn" id="clms-tab-register"><?php esc_html_e( 'Create Account', 'custom-lms' ); ?></button>
					</div>

					<div id="clms-auth-alert" class="clms-auth-alert" style="display:none;"></div>

					<!-- Login Form -->
					<form id="clms-login-form" class="clms-auth-form">
						<div class="clms-form-field">
							<label for="clms_login_user"><?php esc_html_e( 'Username or Email', 'custom-lms' ); ?></label>
							<input type="text" id="clms_login_user" required autocomplete="username" />
						</div>
						<div class="clms-form-field">
							<label for="clms_login_pass"><?php esc_html_e( 'Password', 'custom-lms' ); ?></label>
							<input type="password" id="clms_login_pass" required autocomplete="current-password" />
						</div>
						<button type="submit" class="clms-btn clms-btn-primary clms-btn-large" id="clms-submit-login">
							<?php esc_html_e( 'Log In & Proceed to Checkout →', 'custom-lms' ); ?>
						</button>
					</form>

					<!-- Register Form -->
					<form id="clms-register-form" class="clms-auth-form" style="display:none;">
						<div class="clms-form-field">
							<label for="clms_reg_user"><?php esc_html_e( 'Username', 'custom-lms' ); ?></label>
							<input type="text" id="clms_reg_user" required autocomplete="username" />
						</div>
						<div class="clms-form-field">
							<label for="clms_reg_email"><?php esc_html_e( 'Email Address', 'custom-lms' ); ?></label>
							<input type="email" id="clms_reg_email" required autocomplete="email" />
						</div>
						<div class="clms-form-field">
							<label for="clms_reg_pass"><?php esc_html_e( 'Password', 'custom-lms' ); ?></label>
							<input type="password" id="clms_reg_pass" required autocomplete="new-password" />
						</div>
						<button type="submit" class="clms-btn clms-btn-primary clms-btn-large" id="clms-submit-register">
							<?php esc_html_e( 'Register & Proceed to Checkout →', 'custom-lms' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
