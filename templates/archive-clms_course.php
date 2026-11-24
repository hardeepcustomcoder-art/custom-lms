<?php
/**
 * Custom LMS - Courses Archive Template
 *
 * Displays a modern, responsive courses catalog grid.
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

global $wp_query;
$total_courses = $wp_query->found_posts ? $wp_query->found_posts : 0;
?>
<main id="content" class="site-main clms-archive-site-main">
	<div class="clms-courses-archive-wrapper">
		<!-- Archive Hero Header -->
		<div class="clms-archive-hero">
			<div class="clms-archive-hero-content">
				<div class="clms-archive-badge-wrap">
					<span class="clms-pill clms-pill-course-type"><?php esc_html_e( 'Course Catalog', 'custom-lms' ); ?></span>
					<span class="clms-archive-count-pill">
						📚 <?php echo esc_html( sprintf( _n( '%d Course Available', '%d Courses Available', $total_courses, 'custom-lms' ), $total_courses ) ); ?>
					</span>
				</div>
				<h1 class="clms-archive-heading"><?php esc_html_e( 'Explore Our Courses', 'custom-lms' ); ?></h1>
				<p class="clms-archive-lead">
					<?php esc_html_e( 'Expand your technical skills with our structured, hands-on courses. Learn at your own pace with interactive topics, progress tracking, and automated knowledge checks.', 'custom-lms' ); ?>
				</p>
			</div>
		</div>

		<!-- Courses Cards Grid -->
		<?php if ( have_posts() ) : ?>
			<div class="clms-courses-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					echo CLMS_Frontend::render_course_archive_item( get_post() );
				endwhile;
				?>
			</div>

			<?php if ( $wp_query->max_num_pages > 1 ) : ?>
				<nav class="clms-pagination">
					<div class="nav-previous"><?php previous_posts_link( esc_html__( '← Previous', 'custom-lms' ) ); ?></div>
					<div class="nav-next"><?php next_posts_link( esc_html__( 'Next →', 'custom-lms' ) ); ?></div>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<div class="clms-empty-notice">
				<p><?php esc_html_e( 'No courses have been published yet. Please check back soon!', 'custom-lms' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
