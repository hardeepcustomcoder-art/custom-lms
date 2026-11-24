/**
 * Custom LMS - Settings & Live Customizer JavaScript (Course & Quiz Design)
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var globalPreviewRoot = document.getElementById('clms-live-preview-global');
        var quizPreviewRoot   = document.getElementById('clms-live-preview-root');

        /**
         * Helper: Convert Hex color to RGB string (e.g. "37, 99, 235").
         */
        function hexToRgb(hex) {
            if (!hex) return '37, 99, 235';
            hex = hex.replace('#', '');
            if (hex.length === 3) {
                hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
            }
            if (hex.length !== 6) return '37, 99, 235';
            var r = parseInt(hex.substring(0, 2), 16);
            var g = parseInt(hex.substring(2, 4), 16);
            var b = parseInt(hex.substring(4, 6), 16);
            return r + ', ' + g + ', ' + b;
        }

        /**
         * Update an individual Global / Course color setting in live preview.
         */
        function updateLiveGlobalColor(key, color) {
            if (!globalPreviewRoot || !key || !color) return;

            switch (key) {
                case 'btn_primary_bg':
                    globalPreviewRoot.style.setProperty('--clms-btn-primary-bg', color);
                    break;
                case 'btn_primary_text':
                    globalPreviewRoot.style.setProperty('--clms-btn-primary-text', color);
                    break;
                case 'btn_primary_hover':
                    globalPreviewRoot.style.setProperty('--clms-btn-primary-hover', color);
                    break;
                case 'hero_bg_start':
                    globalPreviewRoot.style.setProperty('--clms-hero-bg-start', color);
                    break;
                case 'hero_bg_end':
                    globalPreviewRoot.style.setProperty('--clms-hero-bg-end', color);
                    break;
                case 'cover_bg_start':
                    globalPreviewRoot.style.setProperty('--clms-cover-bg-start', color);
                    break;
                case 'cover_bg_end':
                    globalPreviewRoot.style.setProperty('--clms-cover-bg-end', color);
                    break;
                case 'card_bg_color':
                    globalPreviewRoot.style.setProperty('--clms-card-bg', color);
                    break;
                case 'progress_fill_color':
                    globalPreviewRoot.style.setProperty('--clms-progress-fill', color);
                    break;
                case 'progress_track_color':
                    globalPreviewRoot.style.setProperty('--clms-progress-track', color);
                    break;
                case 'enrolled_box_bg':
                    globalPreviewRoot.style.setProperty('--clms-enrolled-bg', color);
                    break;
                case 'enrolled_box_text':
                    globalPreviewRoot.style.setProperty('--clms-enrolled-text', color);
                    break;
                case 'price_badge_bg':
                    globalPreviewRoot.style.setProperty('--clms-price-badge-bg', color);
                    break;
                case 'price_badge_text':
                    globalPreviewRoot.style.setProperty('--clms-price-badge-text', color);
                    break;
            }
        }

        /**
         * Update an individual Quiz color setting in live preview.
         */
        function updateLiveQuizColor(key, color) {
            if (!quizPreviewRoot || !key || !color) return;

            var rgb = hexToRgb(color);

            switch (key) {
                case 'primary_color':
                    quizPreviewRoot.style.setProperty('--clms-primary', color);
                    quizPreviewRoot.style.setProperty('--clms-primary-rgb', rgb);
                    break;
                case 'secondary_color':
                    quizPreviewRoot.style.setProperty('--clms-secondary', color);
                    quizPreviewRoot.style.setProperty('--clms-secondary-rgb', rgb);
                    break;
                case 'button_color':
                    quizPreviewRoot.style.setProperty('--clms-btn-bg', color);
                    break;
                case 'button_text_color':
                    quizPreviewRoot.style.setProperty('--clms-btn-text', color);
                    break;
                case 'bg_color':
                    quizPreviewRoot.style.setProperty('--clms-quiz-bg', color);
                    break;
                case 'question_text_color':
                    quizPreviewRoot.style.setProperty('--clms-question-text', color);
                    break;
                case 'answer_bg_color':
                    quizPreviewRoot.style.setProperty('--clms-answer-bg', color);
                    break;
                case 'answer_text_color':
                    quizPreviewRoot.style.setProperty('--clms-answer-text', color);
                    break;
                case 'selected_answer_color':
                    quizPreviewRoot.style.setProperty('--clms-selected-answer', color);
                    break;
                case 'selected_answer_border_color':
                    quizPreviewRoot.style.setProperty('--clms-selected-border', color);
                    break;
                case 'correct_answer_color':
                    quizPreviewRoot.style.setProperty('--clms-correct-color', color);
                    quizPreviewRoot.style.setProperty('--clms-correct-rgb', rgb);
                    break;
                case 'incorrect_answer_color':
                    quizPreviewRoot.style.setProperty('--clms-incorrect-color', color);
                    quizPreviewRoot.style.setProperty('--clms-incorrect-rgb', rgb);
                    break;
            }
        }

        /**
         * Initialize WordPress Color Pickers.
         */
        $('.clms-color-picker').each(function () {
            var $input = $(this);
            var settingKey = $input.data('setting');
            var defaultColor = $input.data('default-color');
            var isQuiz = (typeof clmsSettingsData !== 'undefined' && clmsSettingsData.currentTab === 'quiz_design');

            $input.wpColorPicker({
                change: function (event, ui) {
                    var newColor = ui.color.toString();
                    if (isQuiz) {
                        updateLiveQuizColor(settingKey, newColor);
                    } else {
                        updateLiveGlobalColor(settingKey, newColor);
                    }
                },
                clear: function () {
                    if (isQuiz) {
                        updateLiveQuizColor(settingKey, defaultColor);
                    } else {
                        updateLiveGlobalColor(settingKey, defaultColor);
                    }
                }
            });

            // Also listen to raw input typing
            $input.on('input change', function () {
                var val = $(this).val();
                if (val && val.indexOf('#') === 0) {
                    if (isQuiz) {
                        updateLiveQuizColor(settingKey, val);
                    } else {
                        updateLiveGlobalColor(settingKey, val);
                    }
                }
            });
        });

        /**
         * Reset to Default button for Global / Course Design tab.
         */
        $('#clms-btn-reset-global-js').on('click', function (e) {
            e.preventDefault();

            var confirmMsg = (typeof clmsSettingsData !== 'undefined' && clmsSettingsData.resetConfirm)
                ? clmsSettingsData.resetConfirm
                : 'Reset all colors to default values?';

            if (!confirm(confirmMsg)) {
                return;
            }

            var defaults = (typeof clmsSettingsData !== 'undefined' && clmsSettingsData.globalDefaults)
                ? clmsSettingsData.globalDefaults
                : {};

            $('.clms-color-picker').each(function () {
                var $input = $(this);
                var key = $input.data('setting');
                var def = defaults[key] || $input.data('default-color');

                if (def) {
                    $input.val(def);
                    if ($input.wpColorPicker('instance')) {
                        $input.wpColorPicker('color', def);
                    }
                    updateLiveGlobalColor(key, def);
                }
            });

            $('#clms_settings_action').val('reset');
            $('#clms-global-design-form').submit();
        });

        /**
         * Reset to Default button for Quiz Design tab.
         */
        $('#clms-btn-reset-quiz-js').on('click', function (e) {
            e.preventDefault();

            var confirmMsg = (typeof clmsSettingsData !== 'undefined' && clmsSettingsData.resetConfirm)
                ? clmsSettingsData.resetConfirm
                : 'Reset all quiz colors to default values?';

            if (!confirm(confirmMsg)) {
                return;
            }

            var defaults = (typeof clmsSettingsData !== 'undefined' && clmsSettingsData.quizDefaults)
                ? clmsSettingsData.quizDefaults
                : {};

            $('.clms-color-picker').each(function () {
                var $input = $(this);
                var key = $input.data('setting');
                var def = defaults[key] || $input.data('default-color');

                if (def) {
                    $input.val(def);
                    if ($input.wpColorPicker('instance')) {
                        $input.wpColorPicker('color', def);
                    }
                    updateLiveQuizColor(key, def);
                }
            });

            $('#clms_settings_action').val('reset');
            $('#clms-design-form').submit();
        });

        /**
         * Quiz Preview View Switcher ("During Quiz" vs "After Submission").
         */
        $('.clms-pv-tab').on('click', function () {
            var view = $(this).data('view');
            $('.clms-pv-tab').removeClass('is-active');
            $(this).addClass('is-active');

            var $banner = $('#clms-pv-result-banner');
            var $card2 = $('#clms-pv-card-2');

            if (view === 'result') {
                $banner.slideDown(180);
                $card2.slideDown(180);
            } else {
                $banner.slideUp(180);
                $card2.slideUp(180);
            }
        });

        /**
         * Interactive mock option selection inside quiz preview.
         */
        $('#clms-live-preview-root').on('change', 'input[type="radio"]', function () {
            var $group = $(this).closest('.clms-options-group');
            $group.find('.clms-option-label').removeClass('is-selected');
            $(this).closest('.clms-option-label').addClass('is-selected');
        });
    });
})(jQuery);
