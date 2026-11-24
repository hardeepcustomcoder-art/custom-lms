<?php
/**
 * Admin Settings & LMS Design Customizer (Buttons, Backgrounds, Progress Bars & Quizzes).
 *
 * @package Custom_LMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLMS_Admin_Settings
 */
class CLMS_Admin_Settings {

	/**
	 * Option keys stored in wp_options.
	 */
	const GLOBAL_OPTION_KEY = 'clms_global_design_settings';
	const QUIZ_OPTION_KEY   = 'clms_quiz_design_settings';

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register Settings submenu under Custom LMS.
	 */
	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=clms_course',
			__( 'Custom LMS Settings', 'custom-lms' ),
			__( 'Settings', 'custom-lms' ),
			'manage_options',
			'clms-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Default course & global design settings (Buttons, Backgrounds, Progress Bars).
	 *
	 * @return array
	 */
	public static function get_default_global_settings() {
		return array(
			// Buttons
			'btn_primary_bg'       => '#2563eb', // Royal Blue
			'btn_primary_text'     => '#ffffff', // White
			'btn_primary_hover'    => '#1d4ed8', // Darker Blue
			// Backgrounds
			'hero_bg_start'        => '#0b0f19', // Dark Navy/Black Start
			'hero_bg_end'          => '#1e293b', // Deep Slate End
			'cover_bg_start'       => '#1e1b4b', // Deep Indigo Cover Start
			'cover_bg_end'         => '#312e81', // Rich Purple Cover End
			'card_bg_color'        => '#ffffff', // Card / Content Box Background
			// Progress Bar
			'progress_fill_color'  => '#10b981', // Emerald Green Fill
			'progress_track_color' => '#334155', // Slate Track
			// Enrolled Student Box
			'enrolled_box_bg'      => '#064e3b', // Dark Teal Box
			'enrolled_box_text'    => '#34d399', // Mint Accent/Text
			// Price Badges
			'price_badge_bg'       => '#0f172a', // Dark Navy Pill
			'price_badge_text'     => '#34d399', // Mint Green Text
		);
	}

	/**
	 * Retrieve saved course & global design settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_global_settings() {
		$defaults = self::get_default_global_settings();
		$saved    = get_option( self::GLOBAL_OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Default quiz design settings.
	 *
	 * @return array
	 */
	public static function get_default_quiz_settings() {
		return array(
			'primary_color'                => '#2563eb', // Brand accent / Question badges
			'secondary_color'              => '#475569', // Meta labels / Subtle borders
			'button_color'                 => '#2563eb', // Start Quiz, Submit, Next buttons
			'button_text_color'            => '#ffffff', // Button text & icons
			'bg_color'                     => '#ffffff', // Quiz container & question card background
			'question_text_color'          => '#0f172a', // Question title and body
			'answer_bg_color'              => '#ffffff', // Unselected option background
			'answer_text_color'            => '#1e293b', // Option typography
			'selected_answer_color'        => '#eff6ff', // Selected option background tint
			'selected_answer_border_color' => '#2563eb', // Selected option border accent
			'correct_answer_color'         => '#15803d', // Correct answer & pass banner
			'incorrect_answer_color'       => '#b91c1c', // Incorrect answer & fail banner
		);
	}

	/**
	 * Backward compatibility alias for quiz defaults.
	 */
	public static function get_default_settings() {
		return self::get_default_quiz_settings();
	}

	/**
	 * Retrieve saved quiz design settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_quiz_settings() {
		$defaults = self::get_default_quiz_settings();
		$saved    = get_option( self::QUIZ_OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Backward compatibility alias for quiz settings.
	 */
	public static function get_settings() {
		return self::get_quiz_settings();
	}

	/**
	 * Convert Hex color to RGB string (e.g., '37, 99, 235').
	 *
	 * @param string $hex Hex color string.
	 * @return string
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} elseif ( 6 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		} else {
			return '37, 99, 235';
		}
		return "{$r}, {$g}, {$b}";
	}

	/**
	 * Handle settings form submissions (Save Settings & Reset to Default).
	 */
	public static function handle_save_settings() {
		if ( ! isset( $_POST['clms_settings_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized user.', 'custom-lms' ) );
		}

		check_admin_referer( 'clms_save_settings_nonce', 'clms_settings_nonce_field' );

		$action = sanitize_text_field( wp_unslash( $_POST['clms_settings_action'] ) );
		$tab    = isset( $_POST['clms_current_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['clms_current_tab'] ) ) : 'course_design';

		if ( 'course_design' === $tab ) {
			if ( 'reset' === $action ) {
				delete_option( self::GLOBAL_OPTION_KEY );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'           => 'clms-settings',
							'post_type'      => 'clms_course',
							'tab'            => 'course_design',
							'settings_reset' => '1',
						),
						admin_url( 'edit.php' )
					)
				);
				exit;
			}

			if ( 'save' === $action ) {
				$defaults  = self::get_default_global_settings();
				$raw_input = isset( $_POST['clms_global'] ) && is_array( $_POST['clms_global'] ) ? wp_unslash( $_POST['clms_global'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$sanitized = array();

				foreach ( $defaults as $key => $default_val ) {
					if ( isset( $raw_input[ $key ] ) ) {
						$val = sanitize_hex_color( trim( $raw_input[ $key ] ) );
						$sanitized[ $key ] = ! empty( $val ) ? $val : $default_val;
					} else {
						$sanitized[ $key ] = $default_val;
					}
				}

				update_option( self::GLOBAL_OPTION_KEY, $sanitized );

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'           => 'clms-settings',
							'post_type'      => 'clms_course',
							'tab'            => 'course_design',
							'settings_saved' => '1',
						),
						admin_url( 'edit.php' )
					)
				);
				exit;
			}
		}

		if ( 'quiz_design' === $tab ) {
			if ( 'reset' === $action ) {
				delete_option( self::QUIZ_OPTION_KEY );
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'           => 'clms-settings',
							'post_type'      => 'clms_course',
							'tab'            => 'quiz_design',
							'settings_reset' => '1',
						),
						admin_url( 'edit.php' )
					)
				);
				exit;
			}

			if ( 'save' === $action ) {
				$defaults  = self::get_default_quiz_settings();
				$raw_input = isset( $_POST['clms_design'] ) && is_array( $_POST['clms_design'] ) ? wp_unslash( $_POST['clms_design'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$sanitized = array();

				foreach ( $defaults as $key => $default_val ) {
					if ( isset( $raw_input[ $key ] ) ) {
						$val = sanitize_hex_color( trim( $raw_input[ $key ] ) );
						$sanitized[ $key ] = ! empty( $val ) ? $val : $default_val;
					} else {
						$sanitized[ $key ] = $default_val;
					}
				}

				update_option( self::QUIZ_OPTION_KEY, $sanitized );

				wp_safe_redirect(
					add_query_arg(
						array(
							'page'           => 'clms-settings',
							'post_type'      => 'clms_course',
							'tab'            => 'quiz_design',
							'settings_saved' => '1',
						),
						admin_url( 'edit.php' )
					)
				);
				exit;
			}
		}
	}

	/**
	 * Enqueue scripts and styles on the Settings admin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'clms-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'clms-admin-settings-css',
			CLMS_URL . 'admin/css/clms-settings.css',
			array( 'wp-color-picker' ),
			CLMS_VERSION
		);

		wp_enqueue_script(
			'clms-admin-settings-js',
			CLMS_URL . 'admin/js/clms-settings.js',
			array( 'jquery', 'wp-color-picker' ),
			CLMS_VERSION,
			true
		);

		$current_tab = isset( $_GET['tab'] ) && 'quiz_design' === $_GET['tab'] ? 'quiz_design' : 'course_design';

		wp_localize_script(
			'clms-admin-settings-js',
			'clmsSettingsData',
			array(
				'currentTab'     => $current_tab,
				'globalDefaults' => self::get_default_global_settings(),
				'quizDefaults'   => self::get_default_quiz_settings(),
				'resetConfirm'   => __( 'Are you sure you want to reset all colors in this tab to their default values?', 'custom-lms' ),
			)
		);
	}

	/**
	 * Generate CSS variables and rules for course & global LMS elements (Buttons, Backgrounds, Progress Bars).
	 *
	 * @param array|null $settings Optional settings array; if null, loads saved settings.
	 * @return string CSS string.
	 */
	public static function generate_global_css( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_global_settings();
		}

		$btn_bg          = esc_attr( $settings['btn_primary_bg'] );
		$btn_text        = esc_attr( $settings['btn_primary_text'] );
		$btn_hover       = esc_attr( $settings['btn_primary_hover'] );
		$hero_start      = esc_attr( $settings['hero_bg_start'] );
		$hero_end        = esc_attr( $settings['hero_bg_end'] );
		$cover_start     = esc_attr( $settings['cover_bg_start'] );
		$cover_end       = esc_attr( $settings['cover_bg_end'] );
		$card_bg         = esc_attr( $settings['card_bg_color'] );
		$prog_fill       = esc_attr( $settings['progress_fill_color'] );
		$prog_track      = esc_attr( $settings['progress_track_color'] );
		$enrolled_bg     = esc_attr( $settings['enrolled_box_bg'] );
		$enrolled_text   = esc_attr( $settings['enrolled_box_text'] );
		$price_bg        = esc_attr( $settings['price_badge_bg'] );
		$price_text      = esc_attr( $settings['price_badge_text'] );

		return "
/* Custom LMS - Course, Buttons, Backgrounds & Progress Bar Customizer */
:root, #clms-live-preview-global {
	--clms-btn-primary-bg: {$btn_bg};
	--clms-btn-primary-text: {$btn_text};
	--clms-btn-primary-hover: {$btn_hover};
	--clms-hero-bg-start: {$hero_start};
	--clms-hero-bg-end: {$hero_end};
	--clms-cover-bg-start: {$cover_start};
	--clms-cover-bg-end: {$cover_end};
	--clms-card-bg: {$card_bg};
	--clms-progress-fill: {$prog_fill};
	--clms-progress-track: {$prog_track};
	--clms-enrolled-bg: {$enrolled_bg};
	--clms-enrolled-text: {$enrolled_text};
	--clms-price-badge-bg: {$price_bg};
	--clms-price-badge-text: {$price_text};
}

/* Primary Action Buttons Across Course, Archive & Lessons */
.clms-btn-primary,
.clms-btn-block,
.clms-archive-card-action .clms-btn-primary,
.clms-course-sticky-card .clms-btn-primary,
.clms-paid-topic-trigger,
.clms-btn-large,
.clms-complete-topic-btn,
#clms-live-preview-global .clms-btn-primary {
	background-color: var(--clms-btn-primary-bg) !important;
	border-color: var(--clms-btn-primary-bg) !important;
	color: var(--clms-btn-primary-text) !important;
}

.clms-btn-primary:hover,
.clms-archive-card-action .clms-btn-primary:hover,
.clms-course-sticky-card .clms-btn-primary:hover,
.clms-paid-topic-trigger:hover,
.clms-btn-large:hover,
.clms-complete-topic-btn:hover,
#clms-live-preview-global .clms-btn-primary:hover {
	background-color: var(--clms-btn-primary-hover) !important;
	border-color: var(--clms-btn-primary-hover) !important;
	color: var(--clms-btn-primary-text) !important;
}

/* Hero Banners (Course Single & Catalog Archive) */
.clms-course-hero-banner,
.clms-archive-hero,
#clms-live-preview-global .clms-course-hero-banner {
	background: radial-gradient(circle at 85% 15%, rgba(56, 189, 248, 0.16), transparent 45%),
	            radial-gradient(circle at 15% 85%, rgba(99, 102, 241, 0.14), transparent 50%),
	            linear-gradient(135deg, var(--clms-hero-bg-start) 0%, var(--clms-hero-bg-end) 100%) !important;
}

/* Media Cover Fallbacks (Course Sticky Card & Archive Cards) */
.clms-hero-cover-fallback,
.clms-archive-thumb-fallback,
#clms-live-preview-global .clms-hero-cover-fallback,
#clms-live-preview-global .clms-archive-thumb-fallback {
	background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.4), transparent 65%),
	            linear-gradient(135deg, var(--clms-cover-bg-start) 0%, var(--clms-cover-bg-end) 100%) !important;
}

/* Card & Content Box Backgrounds */
.clms-archive-card-wrapper,
.clms-course-sticky-card,
.clms-course-about-card,
.clms-lesson-card,
#clms-live-preview-global .clms-course-sticky-card,
#clms-live-preview-global .clms-archive-card-wrapper {
	background-color: var(--clms-card-bg) !important;
}

/* Progress Bar Track & Fill */
.clms-progress-track,
.clms-enrolled-hero-banner .clms-progress-track,
#clms-live-preview-global .clms-progress-track {
	background-color: var(--clms-progress-track) !important;
}

.clms-progress-fill,
.clms-enrolled-hero-banner .clms-progress-fill,
#clms-live-preview-global .clms-progress-fill {
	background: var(--clms-progress-fill) !important;
}

/* Enrolled Student Widget */
.clms-enrolled-hero-banner,
#clms-live-preview-global .clms-enrolled-hero-banner {
	background-color: var(--clms-enrolled-bg) !important;
	border-color: rgba(var(--clms-enrolled-text), 0.3) !important;
}

.clms-enrolled-hero-banner .clms-enrolled-badge,
.clms-enrolled-hero-banner .clms-enrolled-pct,
.clms-enrolled-continue-link,
#clms-live-preview-global .clms-enrolled-badge,
#clms-live-preview-global .clms-enrolled-pct,
#clms-live-preview-global .clms-enrolled-continue-link {
	color: var(--clms-enrolled-text) !important;
}

/* Price Badges */
.clms-archive-price-badge,
.clms-hero-media-badge,
#clms-live-preview-global .clms-archive-price-badge,
#clms-live-preview-global .clms-hero-media-badge {
	background: var(--clms-price-badge-bg) !important;
	color: var(--clms-price-badge-text) !important;
}
";
	}

	/**
	 * Generate CSS variables and rules for quiz customizer.
	 *
	 * @param array|null $settings Optional settings array; if null, loads saved settings.
	 * @return string CSS string.
	 */
	public static function generate_quiz_css( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_quiz_settings();
		}

		$primary       = esc_attr( $settings['primary_color'] );
		$primary_rgb   = self::hex_to_rgb( $primary );
		$secondary     = esc_attr( $settings['secondary_color'] );
		$secondary_rgb = self::hex_to_rgb( $secondary );
		$btn_bg        = esc_attr( $settings['button_color'] );
		$btn_text      = esc_attr( $settings['button_text_color'] );
		$bg_color      = esc_attr( $settings['bg_color'] );
		$q_text        = esc_attr( $settings['question_text_color'] );
		$ans_bg        = esc_attr( $settings['answer_bg_color'] );
		$ans_text      = esc_attr( $settings['answer_text_color'] );
		$sel_bg        = esc_attr( $settings['selected_answer_color'] );
		$sel_border    = esc_attr( $settings['selected_answer_border_color'] );
		$correct       = esc_attr( $settings['correct_answer_color'] );
		$correct_rgb   = self::hex_to_rgb( $correct );
		$incorrect     = esc_attr( $settings['incorrect_answer_color'] );
		$incorrect_rgb = self::hex_to_rgb( $incorrect );

		return "
/* Custom LMS - Quiz Design Customization */
:root, .clms-quiz-container, #clms-live-preview-root {
	--clms-primary: {$primary};
	--clms-primary-rgb: {$primary_rgb};
	--clms-secondary: {$secondary};
	--clms-secondary-rgb: {$secondary_rgb};
	--clms-btn-bg: {$btn_bg};
	--clms-btn-text: {$btn_text};
	--clms-quiz-bg: {$bg_color};
	--clms-question-text: {$q_text};
	--clms-answer-bg: {$ans_bg};
	--clms-answer-text: {$ans_text};
	--clms-selected-answer: {$sel_bg};
	--clms-selected-border: {$sel_border};
	--clms-correct-color: {$correct};
	--clms-correct-rgb: {$correct_rgb};
	--clms-incorrect-color: {$incorrect};
	--clms-incorrect-rgb: {$incorrect_rgb};
}

/* Container & Question Card Background */
.clms-quiz-container .clms-quiz-header-card,
.clms-quiz-container .clms-quiz-question-card,
#clms-live-preview-root .clms-quiz-header-card,
#clms-live-preview-root .clms-quiz-question-card {
	background-color: var(--clms-quiz-bg) !important;
	border-color: rgba(var(--clms-secondary-rgb), 0.2) !important;
}

/* Question Typography */
.clms-quiz-container .clms-question-text,
.clms-quiz-container .clms-quiz-page-title,
#clms-live-preview-root .clms-question-text,
#clms-live-preview-root .clms-quiz-page-title {
	color: var(--clms-question-text) !important;
}

/* Primary Accents (Question Number & Quiz Badge) */
.clms-quiz-container .clms-question-number,
#clms-live-preview-root .clms-question-number {
	color: var(--clms-primary) !important;
}
.clms-quiz-container .clms-pill-quiz,
#clms-live-preview-root .clms-pill-quiz {
	background-color: var(--clms-primary) !important;
	color: #ffffff !important;
}

/* Secondary Accents */
.clms-quiz-container .clms-meta-label,
#clms-live-preview-root .clms-meta-label {
	color: var(--clms-secondary) !important;
}

/* Buttons (Start Quiz, Submit, Next, Retake) */
.clms-quiz-container .clms-btn-primary,
.clms-quiz-container #clms-quiz-submit-btn,
.clms-quiz-container .clms-quiz-submit-btn,
#clms-live-preview-root .clms-btn-primary,
#clms-live-preview-root .clms-quiz-submit-btn {
	background-color: var(--clms-btn-bg) !important;
	border-color: var(--clms-btn-bg) !important;
	color: var(--clms-btn-text) !important;
}
.clms-quiz-container .clms-btn-primary:hover,
.clms-quiz-container #clms-quiz-submit-btn:hover,
#clms-live-preview-root .clms-btn-primary:hover {
	filter: brightness(0.92) !important;
	color: var(--clms-btn-text) !important;
}

/* Answer Option Background & Text */
.clms-quiz-container .clms-option-label,
#clms-live-preview-root .clms-option-label {
	background-color: var(--clms-answer-bg) !important;
	border-color: rgba(var(--clms-secondary-rgb), 0.28) !important;
}
.clms-quiz-container .clms-option-text,
#clms-live-preview-root .clms-option-text {
	color: var(--clms-answer-text) !important;
}

/* Selected Answer Option */
.clms-quiz-container .clms-option-label.is-selected,
.clms-quiz-container .clms-option-label:has(input[type='radio']:checked),
#clms-live-preview-root .clms-option-label.is-selected,
#clms-live-preview-root .clms-option-label:has(input[type='radio']:checked) {
	background-color: var(--clms-selected-answer) !important;
	border-color: var(--clms-selected-border) !important;
	box-shadow: 0 0 0 1.5px var(--clms-selected-border) !important;
}
.clms-quiz-container .clms-option-label input[type='radio']:checked,
#clms-live-preview-root .clms-option-label input[type='radio']:checked {
	accent-color: var(--clms-selected-border) !important;
}

/* Correct Answer Highlights & Feedback */
.clms-quiz-container .clms-quiz-question-card.is-correct-card,
.clms-quiz-container .clms-option-label.is-correct-option,
#clms-live-preview-root .clms-quiz-question-card.is-correct-card,
#clms-live-preview-root .clms-option-label.is-correct-option {
	border-color: var(--clms-correct-color) !important;
	background-color: rgba(var(--clms-correct-rgb), 0.08) !important;
}
.clms-quiz-container .clms-feedback-correct,
.clms-quiz-container .clms-text-pass,
.clms-quiz-container .clms-quiz-banner.is-pass .clms-banner-heading,
#clms-live-preview-root .clms-feedback-correct,
#clms-live-preview-root .clms-text-pass,
#clms-live-preview-root .clms-quiz-banner.is-pass .clms-banner-heading {
	color: var(--clms-correct-color) !important;
}
.clms-quiz-container .clms-feedback-correct,
#clms-live-preview-root .clms-feedback-correct {
	background-color: rgba(var(--clms-correct-rgb), 0.16) !important;
}
.clms-quiz-container .clms-quiz-banner.is-pass,
#clms-live-preview-root .clms-quiz-banner.is-pass {
	border-color: var(--clms-correct-color) !important;
	background-color: rgba(var(--clms-correct-rgb), 0.06) !important;
}

/* Incorrect Answer Highlights & Feedback */
.clms-quiz-container .clms-quiz-question-card.is-wrong-card,
.clms-quiz-container .clms-option-label.is-wrong-option,
#clms-live-preview-root .clms-quiz-question-card.is-wrong-card,
#clms-live-preview-root .clms-option-label.is-wrong-option {
	border-color: var(--clms-incorrect-color) !important;
	background-color: rgba(var(--clms-incorrect-rgb), 0.08) !important;
}
.clms-quiz-container .clms-feedback-wrong,
.clms-quiz-container .clms-text-fail,
.clms-quiz-container .clms-quiz-banner.is-fail .clms-banner-heading,
#clms-live-preview-root .clms-feedback-wrong,
#clms-live-preview-root .clms-text-fail,
#clms-live-preview-root .clms-quiz-banner.is-fail .clms-banner-heading {
	color: var(--clms-incorrect-color) !important;
}
.clms-quiz-container .clms-feedback-wrong,
#clms-live-preview-root .clms-feedback-wrong {
	background-color: rgba(var(--clms-incorrect-rgb), 0.16) !important;
}
.clms-quiz-container .clms-quiz-banner.is-fail,
#clms-live-preview-root .clms-quiz-banner.is-fail {
	border-color: var(--clms-incorrect-color) !important;
	background-color: rgba(var(--clms-incorrect-rgb), 0.06) !important;
}
";
	}

	/**
	 * Render the Settings page in WP Admin.
	 */
	public static function render_settings_page() {
		$current_tab = isset( $_GET['tab'] ) && 'quiz_design' === $_GET['tab'] ? 'quiz_design' : 'course_design';
		?>
		<div class="wrap clms-settings-wrap">
			<!-- Header Title & Branding -->
			<div class="clms-settings-header">
				<div class="clms-header-left">
					<h1 class="clms-header-title">
						<span class="clms-header-icon">🎓</span> <?php esc_html_e( 'Custom LMS Settings', 'custom-lms' ); ?>
					</h1>
					<p class="clms-header-subtitle">
						<?php esc_html_e( 'Complete control over all button, background, progress bar, and quiz colors across your LMS.', 'custom-lms' ); ?>
					</p>
				</div>
				<div class="clms-header-badge">
					<span><?php esc_html_e( 'Version 1.0.0', 'custom-lms' ); ?></span>
				</div>
			</div>

			<!-- Flash Notices -->
			<?php if ( isset( $_GET['settings_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible clms-admin-notice">
					<p><strong>✓ <?php esc_html_e( 'Settings saved successfully! Your frontend courses, buttons, backgrounds, and quizzes have been updated.', 'custom-lms' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['settings_reset'] ) ) : ?>
				<div class="notice notice-info is-dismissible clms-admin-notice">
					<p><strong>🔄 <?php esc_html_e( 'Settings have been reset to default values.', 'custom-lms' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<!-- Navigation Tabs -->
			<nav class="nav-tab-wrapper clms-nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-settings&tab=course_design' ) ); ?>" class="nav-tab <?php echo 'course_design' === $current_tab ? 'nav-tab-active' : ''; ?>">
					🎨 <?php esc_html_e( 'Buttons, Backgrounds & Progress Bar', 'custom-lms' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-settings&tab=quiz_design' ) ); ?>" class="nav-tab <?php echo 'quiz_design' === $current_tab ? 'nav-tab-active' : ''; ?>">
					📝 <?php esc_html_e( 'Quiz Design Customization', 'custom-lms' ); ?>
				</a>
			</nav>

			<?php
			if ( 'quiz_design' === $current_tab ) {
				self::render_quiz_design_tab();
			} else {
				self::render_course_design_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Tab 1: Course & Global Design (Buttons, Backgrounds, Progress Bars).
	 */
	private static function render_course_design_tab() {
		$settings = self::get_global_settings();
		$defaults = self::get_default_global_settings();
		?>
		<div class="clms-settings-grid">
			<!-- Left Column: Form Controls -->
			<div class="clms-settings-controls-col">
				<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-settings&tab=course_design' ) ); ?>" id="clms-global-design-form">
					<?php wp_nonce_field( 'clms_save_settings_nonce', 'clms_settings_nonce_field' ); ?>
					<input type="hidden" name="clms_settings_action" id="clms_settings_action" value="save" />
					<input type="hidden" name="clms_current_tab" value="course_design" />

					<!-- Group 1: Buttons -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🔘 <?php esc_html_e( 'All Action Buttons', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Controls "View Course →", "Continue Learning →", "Enroll Now", and all primary LMS buttons.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_global',
								'btn_primary_bg',
								__( 'Primary Button Color', 'custom-lms' ),
								__( 'Background color for all primary buttons across courses, catalog, and lessons.', 'custom-lms' ),
								$settings['btn_primary_bg'],
								$defaults['btn_primary_bg']
							);
							self::render_color_row(
								'clms_global',
								'btn_primary_text',
								__( 'Button Text Color', 'custom-lms' ),
								__( 'Typography and arrow icon color inside the buttons.', 'custom-lms' ),
								$settings['btn_primary_text'],
								$defaults['btn_primary_text']
							);
							self::render_color_row(
								'clms_global',
								'btn_primary_hover',
								__( 'Button Hover Color', 'custom-lms' ),
								__( 'Background color when a user hovers over any primary button.', 'custom-lms' ),
								$settings['btn_primary_hover'],
								$defaults['btn_primary_hover']
							);
							?>
						</div>
					</div>

					<!-- Group 2: Backgrounds -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🌌 <?php esc_html_e( 'Hero & Card Backgrounds', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Controls the dark hero banner gradient, card cover fallback, and card containers.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_global',
								'hero_bg_start',
								__( 'Hero Banner Gradient Start', 'custom-lms' ),
								__( 'Top-left background tone for the Course page & Archive catalog hero banners.', 'custom-lms' ),
								$settings['hero_bg_start'],
								$defaults['hero_bg_start']
							);
							self::render_color_row(
								'clms_global',
								'hero_bg_end',
								__( 'Hero Banner Gradient End', 'custom-lms' ),
								__( 'Bottom-right background tone for the Course page & Archive catalog hero banners.', 'custom-lms' ),
								$settings['hero_bg_end'],
								$defaults['hero_bg_end']
							);
							self::render_color_row(
								'clms_global',
								'cover_bg_start',
								__( 'Course Card Cover Start', 'custom-lms' ),
								__( 'Primary gradient tone for the interactive laptop fallback cover on cards.', 'custom-lms' ),
								$settings['cover_bg_start'],
								$defaults['cover_bg_start']
							);
							self::render_color_row(
								'clms_global',
								'cover_bg_end',
								__( 'Course Card Cover End', 'custom-lms' ),
								__( 'Secondary gradient tone for the interactive laptop fallback cover on cards.', 'custom-lms' ),
								$settings['cover_bg_end'],
								$defaults['cover_bg_end']
							);
							self::render_color_row(
								'clms_global',
								'card_bg_color',
								__( 'Card & Content Background', 'custom-lms' ),
								__( 'Background color for course cards, sticky purchase card, and curriculum cards.', 'custom-lms' ),
								$settings['card_bg_color'],
								$defaults['card_bg_color']
							);
							?>
						</div>
					</div>

					<!-- Group 3: Progress Bar & Enrolled Student Banner -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">📊 <?php esc_html_e( 'Progress Bar & Enrolled Status', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Controls the progress bar track, progress fill, and enrolled student banner.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_global',
								'progress_fill_color',
								__( 'Progress Bar Fill Color', 'custom-lms' ),
								__( 'Color of the active progress indicator fill (e.g. 67% Completed).', 'custom-lms' ),
								$settings['progress_fill_color'],
								$defaults['progress_fill_color']
							);
							self::render_color_row(
								'clms_global',
								'progress_track_color',
								__( 'Progress Bar Track Color', 'custom-lms' ),
								__( 'Background line behind the progress fill.', 'custom-lms' ),
								$settings['progress_track_color'],
								$defaults['progress_track_color']
							);
							self::render_color_row(
								'clms_global',
								'enrolled_box_bg',
								__( 'Enrolled Banner Background', 'custom-lms' ),
								__( 'Background color of the Enrolled Student status widget box in the hero.', 'custom-lms' ),
								$settings['enrolled_box_bg'],
								$defaults['enrolled_box_bg']
							);
							self::render_color_row(
								'clms_global',
								'enrolled_box_text',
								__( 'Enrolled Banner Accent & Text', 'custom-lms' ),
								__( 'Accent color for "✓ Enrolled Student", percentage text, and continue link.', 'custom-lms' ),
								$settings['enrolled_box_text'],
								$defaults['enrolled_box_text']
							);
							?>
						</div>
					</div>

					<!-- Group 4: Price Badge -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">💰 <?php esc_html_e( 'Price Badges', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Floating price badge on course media covers and catalog cards.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_global',
								'price_badge_bg',
								__( 'Price Badge Background', 'custom-lms' ),
								__( 'Background pill color for the price tag.', 'custom-lms' ),
								$settings['price_badge_bg'],
								$defaults['price_badge_bg']
							);
							self::render_color_row(
								'clms_global',
								'price_badge_text',
								__( 'Price Badge Text Color', 'custom-lms' ),
								__( 'Currency and price text color inside the floating badge.', 'custom-lms' ),
								$settings['price_badge_text'],
								$defaults['price_badge_text']
							);
							?>
						</div>
					</div>

					<!-- Form Action Buttons -->
					<div class="clms-form-actions-bar">
						<button type="submit" class="button button-primary button-large clms-btn-save-settings">
							💾 <?php esc_html_e( 'Save Settings', 'custom-lms' ); ?>
						</button>

						<button type="button" class="button button-secondary button-large clms-btn-reset-defaults" id="clms-btn-reset-global-js">
							🔄 <?php esc_html_e( 'Reset to Default', 'custom-lms' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Right Column: Sticky Live Interactive Preview -->
			<div class="clms-settings-preview-col">
				<div class="clms-preview-sticky-wrap">
					<div class="clms-preview-panel-header">
						<div>
							<h3 class="clms-preview-title">👁️ <?php esc_html_e( 'Live Course & Page Preview', 'custom-lms' ); ?></h3>
							<p class="clms-preview-subtitle"><?php esc_html_e( 'Updates in real time as you adjust button, background, and progress bar colors.', 'custom-lms' ); ?></p>
						</div>
						<span class="clms-preview-live-tag">● <?php esc_html_e( 'Real-time', 'custom-lms' ); ?></span>
					</div>

					<!-- Preview Frame Container -->
					<div class="clms-preview-frame">
						<div id="clms-live-preview-global">
							<!-- 1. Mock Course Hero Banner with Enrolled Progress Bar -->
							<div class="clms-course-hero-banner" style="margin-bottom:18px;">
								<div class="clms-course-breadcrumbs" style="margin-bottom:12px;">
									<span style="color:#38bdf8;font-weight:600;">← <?php esc_html_e( 'All Courses', 'custom-lms' ); ?></span>
									<span style="color:#64748b;margin:0 6px;">/</span>
									<span class="clms-pill clms-pill-course-type"><?php esc_html_e( 'Self-Paced Course', 'custom-lms' ); ?></span>
								</div>

								<h2 style="font-size:1.55rem;font-weight:800;color:#ffffff;margin:0 0 10px 0;">
									Mastering Modern PHP
								</h2>

								<p style="font-size:0.9rem;color:#cbd5e1;margin:0 0 16px 0;line-height:1.5;">
									Comprehensive guide to modern PHP and WordPress development.
								</p>

								<!-- Mock Enrolled Student Box with Progress Bar -->
								<div class="clms-enrolled-hero-banner">
									<div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:0.86rem;font-weight:700;">
										<span class="clms-enrolled-badge">✓ <?php esc_html_e( 'Enrolled Student', 'custom-lms' ); ?></span>
										<span class="clms-enrolled-pct">67% <?php esc_html_e( 'Completed', 'custom-lms' ); ?></span>
									</div>

									<div class="clms-progress-track" style="height:8px;border-radius:9999px;overflow:hidden;margin-bottom:8px;">
										<div class="clms-progress-fill" style="width:67%;height:100%;border-radius:9999px;"></div>
									</div>

									<div style="display:flex;justify-content:space-between;font-size:0.8rem;">
										<span style="color:#cbd5e1;">4 of 6 topics completed</span>
										<span class="clms-enrolled-continue-link" style="font-weight:600;"><?php esc_html_e( 'Continue Learning →', 'custom-lms' ); ?></span>
									</div>
								</div>
							</div>

							<!-- 2. Mock Sticky Course Card with Fallback Cover & Action Button -->
							<div class="clms-course-sticky-card" style="border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;margin-bottom:18px;">
								<div style="position:relative;aspect-ratio:16/9;overflow:hidden;" class="clms-hero-card-media">
									<div class="clms-hero-cover-fallback" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:16px;">
										<span style="font-size:2rem;line-height:1;">💻</span>
										<span style="font-size:0.7rem;font-weight:700;color:#c7d2fe;text-transform:uppercase;margin:4px 0;">Complete Course</span>
										<span style="font-size:0.95rem;font-weight:700;color:#ffffff;">Mastering Modern PHP</span>
									</div>
									<span class="clms-hero-media-badge" style="position:absolute;top:10px;right:10px;border-radius:9999px;padding:3px 10px;font-size:0.8rem;font-weight:700;">₹79.00</span>
								</div>

								<div style="padding:16px;">
									<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:14px;">
										<div>
											<span style="font-size:1.45rem;font-weight:800;color:#0f172a;">₹79.00</span>
											<span style="font-size:0.78rem;color:#64748b;display:block;"><?php esc_html_e( 'One-time payment', 'custom-lms' ); ?></span>
										</div>
										<span style="font-size:0.78rem;color:#15803d;font-weight:600;background:#dcfce7;padding:3px 8px;border-radius:9999px;">🔒 Lifetime Access</span>
									</div>

									<button type="button" class="clms-btn clms-btn-primary" style="width:100%;padding:11px;font-size:0.95rem;font-weight:700;border-radius:8px;cursor:pointer;justify-content:center;">
										<?php esc_html_e( 'Continue Learning →', 'custom-lms' ); ?>
									</button>
								</div>
							</div>

							<!-- 3. Mock Catalog Archive Card Preview -->
							<div class="clms-archive-card-wrapper" style="border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
								<div style="position:relative;aspect-ratio:16/9;overflow:hidden;">
									<div class="clms-archive-thumb-fallback" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:16px;">
										<span style="font-size:1.8rem;">💻</span>
										<span style="font-size:0.7rem;font-weight:700;color:#c7d2fe;text-transform:uppercase;margin:4px 0;">Interactive Course</span>
										<span style="font-size:0.92rem;font-weight:700;color:#ffffff;">Web Development Fundamentals</span>
									</div>
									<span class="clms-archive-price-badge" style="position:absolute;top:10px;right:10px;border-radius:9999px;padding:3px 10px;font-size:0.8rem;font-weight:700;">₹79.00</span>
								</div>

								<div style="padding:16px;">
									<div style="font-size:0.75rem;color:#64748b;margin-bottom:8px;display:flex;gap:8px;">
										<span>📚 1 Lesson</span>
										<span>📝 6 Topics</span>
										<span>🎯 1 Quiz</span>
									</div>
									<h4 style="margin:0 0 8px 0;font-size:1.05rem;font-weight:700;color:#0f172a;">Web Development Fundamentals</h4>
									<p style="font-size:0.82rem;color:#475569;margin:0 0 14px 0;line-height:1.4;">Learn the basics of web development and discover how to create beautiful websites...</p>
									<button type="button" class="clms-btn clms-btn-primary" style="width:100%;padding:9px;font-size:0.88rem;font-weight:700;border-radius:8px;cursor:pointer;justify-content:center;">
										<?php esc_html_e( 'View Course →', 'custom-lms' ); ?>
									</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Tab 2: Quiz Design Customization.
	 */
	private static function render_quiz_design_tab() {
		$settings = self::get_quiz_settings();
		$defaults = self::get_default_quiz_settings();
		?>
		<div class="clms-settings-grid">
			<!-- Left Column: Form Controls -->
			<div class="clms-settings-controls-col">
				<form method="post" action="<?php echo esc_url( admin_url( 'edit.php?post_type=clms_course&page=clms-settings&tab=quiz_design' ) ); ?>" id="clms-design-form">
					<?php wp_nonce_field( 'clms_save_settings_nonce', 'clms_settings_nonce_field' ); ?>
					<input type="hidden" name="clms_settings_action" id="clms_settings_action" value="save" />
					<input type="hidden" name="clms_current_tab" value="quiz_design" />

					<!-- Group 1: Brand & Theme Accents -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🎨 <?php esc_html_e( 'Quiz Theme & Brand Colors', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Base palette for question badges, numbering, and subtle UI lines.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_design',
								'primary_color',
								__( 'Primary Color', 'custom-lms' ),
								__( 'Applied to question index pills, active accents, and primary indicators.', 'custom-lms' ),
								$settings['primary_color'],
								$defaults['primary_color']
							);
							self::render_color_row(
								'clms_design',
								'secondary_color',
								__( 'Secondary Color', 'custom-lms' ),
								__( 'Applied to metadata labels, subtle card borders, and secondary text.', 'custom-lms' ),
								$settings['secondary_color'],
								$defaults['secondary_color']
							);
							?>
						</div>
					</div>

					<!-- Group 2: Action Buttons -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🔘 <?php esc_html_e( 'Quiz Action Buttons', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Colors for Start Quiz, Submit Answers, and Next/Retake buttons.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_design',
								'button_color',
								__( 'Button Color', 'custom-lms' ),
								__( 'Background fill for Start Quiz, Submit Answers, and Next Question buttons.', 'custom-lms' ),
								$settings['button_color'],
								$defaults['button_color']
							);
							self::render_color_row(
								'clms_design',
								'button_text_color',
								__( 'Button Text Color', 'custom-lms' ),
								__( 'Typography and icon color inside all quiz action buttons.', 'custom-lms' ),
								$settings['button_text_color'],
								$defaults['button_text_color']
							);
							?>
						</div>
					</div>

					<!-- Group 3: Container & Question Cards -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">📋 <?php esc_html_e( 'Question Card & Background', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Card background and question statement typography.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_design',
								'bg_color',
								__( 'Background Color', 'custom-lms' ),
								__( 'Background fill for the quiz header overview and question cards.', 'custom-lms' ),
								$settings['bg_color'],
								$defaults['bg_color']
							);
							self::render_color_row(
								'clms_design',
								'question_text_color',
								__( 'Question Text Color', 'custom-lms' ),
								__( 'Text color for question titles, statements, and quiz headings.', 'custom-lms' ),
								$settings['question_text_color'],
								$defaults['question_text_color']
							);
							?>
						</div>
					</div>

					<!-- Group 4: Answer Choices -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🔘 <?php esc_html_e( 'Answer Options & Selection', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Styling for default and active/selected multiple-choice answers.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_design',
								'answer_bg_color',
								__( 'Answer Option Background Color', 'custom-lms' ),
								__( 'Background color for unselected answer choice rows.', 'custom-lms' ),
								$settings['answer_bg_color'],
								$defaults['answer_bg_color']
							);
							self::render_color_row(
								'clms_design',
								'answer_text_color',
								__( 'Answer Option Text Color', 'custom-lms' ),
								__( 'Typography color for multiple-choice option text.', 'custom-lms' ),
								$settings['answer_text_color'],
								$defaults['answer_text_color']
							);
							self::render_color_row(
								'clms_design',
								'selected_answer_color',
								__( 'Selected Answer Background Color', 'custom-lms' ),
								__( 'Background highlight for the student\'s selected answer choice.', 'custom-lms' ),
								$settings['selected_answer_color'],
								$defaults['selected_answer_color']
							);
							self::render_color_row(
								'clms_design',
								'selected_answer_border_color',
								__( 'Selected Answer Border Color', 'custom-lms' ),
								__( 'Border accent and radio fill for the selected answer.', 'custom-lms' ),
								$settings['selected_answer_border_color'],
								$defaults['selected_answer_border_color']
							);
							?>
						</div>
					</div>

					<!-- Group 5: Evaluation & Feedback Results -->
					<div class="clms-settings-card">
						<div class="clms-card-header">
							<h2 class="clms-card-title">🎯 <?php esc_html_e( 'Evaluation & Result Colors', 'custom-lms' ); ?></h2>
							<p class="clms-card-desc"><?php esc_html_e( 'Accents for correct and incorrect answers and pass/fail score banners.', 'custom-lms' ); ?></p>
						</div>
						<div class="clms-card-body">
							<?php
							self::render_color_row(
								'clms_design',
								'correct_answer_color',
								__( 'Correct Answer Color', 'custom-lms' ),
								__( 'Accent color for correct answers, passed status, and positive feedback.', 'custom-lms' ),
								$settings['correct_answer_color'],
								$defaults['correct_answer_color']
							);
							self::render_color_row(
								'clms_design',
								'incorrect_answer_color',
								__( 'Incorrect Answer Color', 'custom-lms' ),
								__( 'Accent color for wrong answers, failed status, and error indicators.', 'custom-lms' ),
								$settings['incorrect_answer_color'],
								$defaults['incorrect_answer_color']
							);
							?>
						</div>
					</div>

					<!-- Form Action Buttons -->
					<div class="clms-form-actions-bar">
						<button type="submit" class="button button-primary button-large clms-btn-save-settings">
							💾 <?php esc_html_e( 'Save Settings', 'custom-lms' ); ?>
						</button>

						<button type="button" class="button button-secondary button-large clms-btn-reset-defaults" id="clms-btn-reset-quiz-js">
							🔄 <?php esc_html_e( 'Reset to Default', 'custom-lms' ); ?>
						</button>
					</div>
				</form>
			</div>

			<!-- Right Column: Sticky Live Interactive Quiz Preview -->
			<div class="clms-settings-preview-col">
				<div class="clms-preview-sticky-wrap">
					<div class="clms-preview-panel-header">
						<div>
							<h3 class="clms-preview-title">👁️ <?php esc_html_e( 'Live Quiz Preview', 'custom-lms' ); ?></h3>
							<p class="clms-preview-subtitle"><?php esc_html_e( 'Changes update instantly as you pick colors.', 'custom-lms' ); ?></p>
						</div>
						<span class="clms-preview-live-tag">● <?php esc_html_e( 'Real-time', 'custom-lms' ); ?></span>
					</div>

					<!-- Preview View Selector Tabs -->
					<div class="clms-preview-view-tabs">
						<button type="button" class="clms-pv-tab is-active" data-view="quiz">
							📝 <?php esc_html_e( 'During Quiz', 'custom-lms' ); ?>
						</button>
						<button type="button" class="clms-pv-tab" data-view="result">
							🏆 <?php esc_html_e( 'After Submission', 'custom-lms' ); ?>
						</button>
					</div>

					<!-- Live Preview Frame Container -->
					<div class="clms-preview-frame">
						<div id="clms-live-preview-root" class="clms-quiz-container">
							<!-- Preview Header Card -->
							<div class="clms-quiz-header-card">
								<div class="clms-quiz-header-badge">
									<span class="clms-pill clms-pill-quiz">📝 <?php esc_html_e( 'Lesson Assessment', 'custom-lms' ); ?></span>
								</div>
								<h2 class="clms-quiz-page-title"><?php esc_html_e( 'Lesson 1 Knowledge Check', 'custom-lms' ); ?></h2>
								<div class="clms-quiz-meta-grid">
									<div class="clms-quiz-meta-col">
										<span class="clms-meta-label"><?php esc_html_e( 'Passing Score', 'custom-lms' ); ?></span>
										<span class="clms-meta-val">70%</span>
									</div>
									<div class="clms-quiz-meta-col">
										<span class="clms-meta-label"><?php esc_html_e( 'Questions', 'custom-lms' ); ?></span>
										<span class="clms-meta-val">8</span>
									</div>
									<div class="clms-quiz-meta-col">
										<span class="clms-meta-label"><?php esc_html_e( 'Attempt Limit', 'custom-lms' ); ?></span>
										<span class="clms-meta-val"><?php esc_html_e( 'Unlimited', 'custom-lms' ); ?></span>
									</div>
								</div>
							</div>

							<!-- Result Banner (Shown in Result View) -->
							<div id="clms-pv-result-banner" class="clms-quiz-banner is-pass" style="display:none;margin-bottom:20px;">
								<div class="clms-banner-icon">🎉</div>
								<div class="clms-banner-content">
									<h3 class="clms-banner-heading"><?php esc_html_e( 'Congratulations! You Passed!', 'custom-lms' ); ?></h3>
									<p class="clms-banner-stats">
										<?php esc_html_e( 'Score:', 'custom-lms' ); ?> <strong>88%</strong> (7 of 8 questions correct) • <?php esc_html_e( 'Required: 70%', 'custom-lms' ); ?>
									</p>
									<p class="clms-banner-email-note">📧 <?php esc_html_e( 'A full score report has been emailed to student.', 'custom-lms' ); ?></p>
								</div>
							</div>

							<!-- Question 1: Unselected & Selected Options -->
							<div class="clms-quiz-question-card" id="clms-pv-card-1">
								<div class="clms-question-header">
									<span class="clms-question-number"><?php esc_html_e( 'Question 1 of 8', 'custom-lms' ); ?></span>
									<h3 class="clms-question-text"><?php esc_html_e( 'Which modern PHP feature allows strict type checking across execution?', 'custom-lms' ); ?></h3>
								</div>

								<div class="clms-options-group">
									<label class="clms-option-label" for="clms-pv-opt-1">
										<input type="radio" id="clms-pv-opt-1" name="clms_pv_q1" value="0" />
										<span class="clms-option-text">declare(encoding='UTF-8');</span>
									</label>

									<label class="clms-option-label is-selected" for="clms-pv-opt-2">
										<input type="radio" id="clms-pv-opt-2" name="clms_pv_q1" value="1" checked />
										<span class="clms-option-text">declare(strict_types=1); (Selected Answer)</span>
									</label>

									<label class="clms-option-label" for="clms-pv-opt-3">
										<input type="radio" id="clms-pv-opt-3" name="clms_pv_q1" value="2" />
										<span class="clms-option-text">ini_set('display_errors', 1);</span>
									</label>
								</div>
							</div>

							<!-- Question 2: Evaluation Feedback (Shown in Result View) -->
							<div class="clms-quiz-question-card is-correct-card" id="clms-pv-card-2" style="display:none;margin-top:20px;">
								<div class="clms-question-header">
									<span class="clms-question-number"><?php esc_html_e( 'Question 2 of 8', 'custom-lms' ); ?></span>
									<h3 class="clms-question-text"><?php esc_html_e( 'What is the correct syntax for declaring an arrow function in PHP 8?', 'custom-lms' ); ?></h3>
								</div>

								<div class="clms-options-group">
									<label class="clms-option-label is-correct-option is-selected">
										<input type="radio" checked disabled />
										<span class="clms-option-text">fn($x) =&gt; $x * 2;</span>
									</label>
									<label class="clms-option-label is-wrong-option">
										<input type="radio" disabled />
										<span class="clms-option-text">function =&gt; ($x * 2);</span>
									</label>
								</div>

								<div class="clms-question-feedback">
									<span class="clms-feedback-badge clms-feedback-correct">✓ <?php esc_html_e( 'Correct Answer', 'custom-lms' ); ?></span>
									<div class="clms-feedback-solution">
										<strong><?php esc_html_e( 'Solution:', 'custom-lms' ); ?></strong> fn($x) =&gt; $x * 2;
									</div>
								</div>
							</div>

							<!-- Action Buttons Bar -->
							<div class="clms-quiz-submit-bar" style="margin-top:20px;padding:0;">
								<button type="button" class="clms-btn clms-btn-primary clms-quiz-submit-btn">
									<?php esc_html_e( 'Submit Quiz Answers', 'custom-lms' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Helper to render an individual color setting row with WP Color Picker.
	 *
	 * @param string $input_prefix Form array name (e.g. 'clms_global' or 'clms_design').
	 * @param string $id           Key identifier.
	 * @param string $title        Human-readable title.
	 * @param string $desc         Helpful description.
	 * @param string $current_val  Current saved color value.
	 * @param string $default_val  Default fallback color.
	 */
	private static function render_color_row( $input_prefix, $id, $title, $desc, $current_val, $default_val ) {
		?>
		<div class="clms-color-setting-row" data-setting-key="<?php echo esc_attr( $id ); ?>">
			<div class="clms-csr-info">
				<label class="clms-csr-label" for="<?php echo esc_attr( $input_prefix . '_' . $id ); ?>">
					<?php echo esc_html( $title ); ?>
				</label>
				<p class="clms-csr-desc"><?php echo esc_html( $desc ); ?></p>
			</div>
			<div class="clms-csr-picker-wrap">
				<input type="text"
				       name="<?php echo esc_attr( $input_prefix . '[' . $id . ']' ); ?>"
				       id="<?php echo esc_attr( $input_prefix . '_' . $id ); ?>"
				       value="<?php echo esc_attr( $current_val ); ?>"
				       class="clms-color-picker"
				       data-default-color="<?php echo esc_attr( $default_val ); ?>"
				       data-setting="<?php echo esc_attr( $id ); ?>" />
				<span class="clms-default-hint">
					<?php esc_html_e( 'Default:', 'custom-lms' ); ?> <code><?php echo esc_html( $default_val ); ?></code>
				</span>
			</div>
		</div>
		<?php
	}
}
