/**
 * Frontend JavaScript for Custom LMS:
 * - Free Topic watch handling
 * - Paid Topic popup trigger
 * - Login / Register Auth Popup
 * - Direct WooCommerce Checkout integration
 */

document.addEventListener('DOMContentLoaded', function () {
    var modalBackdrop = document.getElementById('clms-modal-backdrop');
    var closeBtn = document.getElementById('clms-modal-close-btn');
    var cancelBtns = document.querySelectorAll('.clms-modal-cancel');
    var buyStep = document.getElementById('clms-modal-step-buy');
    var authStep = document.getElementById('clms-modal-step-auth');

    var topicTitleElem = document.getElementById('clms-modal-topic-title');
    var courseTitleElem = document.getElementById('clms-modal-course-title');
    var coursePriceElem = document.getElementById('clms-modal-course-price');
    var buyNowBtn = document.getElementById('clms-modal-buynow-btn');

    var tabLogin = document.getElementById('clms-tab-login');
    var tabRegister = document.getElementById('clms-tab-register');
    var loginForm = document.getElementById('clms-login-form');
    var registerForm = document.getElementById('clms-register-form');
    var authAlert = document.getElementById('clms-auth-alert');

    var currentProductId = 0;
    var currentCourseId = 0;
    var currentRedirectUrl = '';

    var authTitleElem = authStep ? authStep.querySelector('.clms-modal-title') : null;
    var authSubElem = authStep ? authStep.querySelector('.clms-modal-subtitle') : null;
    var loginSubmitBtn = document.getElementById('clms-submit-login');
    var regSubmitBtn = document.getElementById('clms-submit-register');

    function openModal() {
        if (modalBackdrop) {
            modalBackdrop.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal() {
        if (modalBackdrop) {
            modalBackdrop.style.display = 'none';
            document.body.style.overflow = '';
            if (authAlert) {
                authAlert.style.display = 'none';
                authAlert.textContent = '';
            }
        }
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    cancelBtns.forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });

    if (modalBackdrop) {
        modalBackdrop.addEventListener('click', function (e) {
            if (e.target === modalBackdrop) {
                closeModal();
            }
        });
    }

    // Intercept clicks on paid topics / quizzes
    var paidTriggers = document.querySelectorAll('.clms-paid-topic-trigger');
    paidTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();

            var topicTitle = this.getAttribute('data-topic-title');
            var courseTitle = this.getAttribute('data-course-title');
            var price = this.getAttribute('data-price');
            currentProductId = parseInt(this.getAttribute('data-product-id'), 10) || 0;
            currentCourseId = parseInt(this.getAttribute('data-course-id'), 10) || 0;
            currentRedirectUrl = '';

            if (authTitleElem) authTitleElem.textContent = 'Sign in to Purchase';
            if (authSubElem) authSubElem.textContent = 'Please log in or create an account to purchase and unlock this course.';
            if (loginSubmitBtn) loginSubmitBtn.textContent = 'Log In & Proceed to Checkout →';
            if (regSubmitBtn) regSubmitBtn.textContent = 'Register & Proceed to Checkout →';

            if (topicTitleElem) topicTitleElem.textContent = topicTitle;
            if (courseTitleElem) courseTitleElem.textContent = courseTitle;
            if (coursePriceElem) coursePriceElem.textContent = price;

            // Build WooCommerce direct checkout url
            var checkoutUrl = clms_vars.checkout_url;
            if (currentProductId > 0) {
                var separator = checkoutUrl.indexOf('?') !== -1 ? '&' : '?';
                checkoutUrl = checkoutUrl + separator + 'add-to-cart=' + currentProductId;
            }
            if (buyNowBtn) {
                buyNowBtn.setAttribute('href', checkoutUrl);
            }

            // Check if user is logged in
            if (clms_vars.is_logged_in) {
                // Show Buy Step
                if (buyStep) buyStep.style.display = 'block';
                if (authStep) authStep.style.display = 'none';
            } else {
                // Show Auth Step
                if (buyStep) buyStep.style.display = 'none';
                if (authStep) authStep.style.display = 'block';
            }

            openModal();
        });
    });

    // Intercept clicks on free preview topics when user is logged out
    var freeAuthTriggers = document.querySelectorAll('.clms-free-auth-trigger');
    freeAuthTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();

            currentProductId = 0;
            currentCourseId = parseInt(this.getAttribute('data-course-id'), 10) || 0;
            currentRedirectUrl = this.getAttribute('data-topic-url') || this.getAttribute('href') || window.location.href;

            if (authTitleElem) authTitleElem.textContent = 'Sign in to Watch Free Topic';
            if (authSubElem) authSubElem.textContent = 'The first topic is free! Please log in or create an account to watch it.';
            if (loginSubmitBtn) loginSubmitBtn.textContent = 'Log In & Watch Free Topic →';
            if (regSubmitBtn) regSubmitBtn.textContent = 'Register & Watch Free Topic →';

            if (buyStep) buyStep.style.display = 'none';
            if (authStep) authStep.style.display = 'block';

            openModal();
        });
    });

    // Tab switching in Auth Step
    if (tabLogin && tabRegister) {
        tabLogin.addEventListener('click', function () {
            tabLogin.classList.add('is-active');
            tabRegister.classList.remove('is-active');
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            if (authAlert) authAlert.style.display = 'none';
        });

        tabRegister.addEventListener('click', function () {
            tabRegister.classList.add('is-active');
            tabLogin.classList.remove('is-active');
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
            if (authAlert) authAlert.style.display = 'none';
        });
    }

    // Handle AJAX Login
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = document.getElementById('clms-submit-login');
            var originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Verifying...';

            var formData = new FormData();
            formData.append('action', 'clms_ajax_login');
            formData.append('nonce', clms_vars.nonce);
            formData.append('username', document.getElementById('clms_login_user').value);
            formData.append('password', document.getElementById('clms_login_pass').value);
            formData.append('product_id', currentProductId);
            formData.append('redirect_to', currentRedirectUrl);

            fetch(clms_vars.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    authAlert.className = 'clms-auth-alert clms-alert-success';
                    authAlert.textContent = data.data.message;
                    authAlert.style.display = 'block';
                    setTimeout(function () {
                        window.location.href = data.data.redirect_url || data.data.checkout_url;
                    }, 800);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    authAlert.className = 'clms-auth-alert clms-alert-error';
                    // Ensure all HTML tags are cleaned in browser output as well
                    var cleanMsg = (data.data.message || 'Invalid credentials.').replace(/<\/?[^>]+(>|$)/g, "");
                    authAlert.textContent = cleanMsg;
                    authAlert.style.display = 'block';
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                authAlert.className = 'clms-auth-alert clms-alert-error';
                authAlert.textContent = 'Server error. Please try again.';
                authAlert.style.display = 'block';
            });
        });
    }

    // Handle AJAX Registration
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = document.getElementById('clms-submit-register');
            var originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';

            var formData = new FormData();
            formData.append('action', 'clms_ajax_register');
            formData.append('nonce', clms_vars.nonce);
            formData.append('username', document.getElementById('clms_reg_user').value);
            formData.append('email', document.getElementById('clms_reg_email').value);
            formData.append('password', document.getElementById('clms_reg_pass').value);
            formData.append('product_id', currentProductId);
            formData.append('redirect_to', currentRedirectUrl);

            fetch(clms_vars.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    authAlert.className = 'clms-auth-alert clms-alert-success';
                    authAlert.textContent = data.data.message;
                    authAlert.style.display = 'block';
                    setTimeout(function () {
                        window.location.href = data.data.redirect_url || data.data.checkout_url;
                    }, 800);
                } else {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    authAlert.className = 'clms-auth-alert clms-alert-error';
                    var cleanMsg = (data.data.message || 'Registration failed.').replace(/<\/?[^>]+(>|$)/g, "");
                    authAlert.textContent = cleanMsg;
                    authAlert.style.display = 'block';
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
                authAlert.className = 'clms-auth-alert clms-alert-error';
                authAlert.textContent = 'Server error. Please try again.';
                authAlert.style.display = 'block';
            });
        });
    }

    // Helper to safely escape HTML text in JS output
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ==========================================
    // Topic Completion Handler
    // ==========================================
    var completeTopicBtns = document.querySelectorAll('.clms-complete-topic-btn');
    completeTopicBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var topicId = this.getAttribute('data-topic-id');
            if (!topicId) return;

            var origBtnText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Saving...';

            fetch(clms_vars.rest_url + 'topics/' + topicId + '/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': clms_vars.rest_nonce
                },
                credentials: 'same-origin'
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    var parentWrap = btn.parentNode;
                    parentWrap.innerHTML = '<span style="color:#15803d;font-weight:700;display:flex;align-items:center;gap:6px;">' +
                        '<span style="background:#dcfce7;width:24px;height:24px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">✓</span>' +
                        'Completed!</span>';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = origBtnText;
                    alert(data.message || 'Error marking topic complete.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = origBtnText;
                alert('Network error. Please try again.');
            });
        });
    });

    // ==========================================
    // Interactive Quiz Submission Handler
    // ==========================================
    var quizForm = document.getElementById('clms-quiz-form');
    if (quizForm) {
        // Real-time radio answer option selection highlight
        quizForm.addEventListener('change', function (e) {
            if (e.target && e.target.type === 'radio') {
                var radioName = e.target.name;
                var allSameRadios = quizForm.querySelectorAll('input[type="radio"][name="' + radioName + '"]');
                allSameRadios.forEach(function (r) {
                    var parentLabel = r.closest('.clms-option-label');
                    if (parentLabel) {
                        parentLabel.classList.remove('is-selected');
                    }
                });
                var selectedLabel = e.target.closest('.clms-option-label');
                if (selectedLabel) {
                    selectedLabel.classList.add('is-selected');
                }
            }
        });

        quizForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var quizId = this.getAttribute('data-quiz-id');
            var submitBtn = document.getElementById('clms-quiz-submit-btn');
            var spinner = document.getElementById('clms-quiz-spinner');
            var resultContainer = document.getElementById('clms-quiz-live-result');

            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';

            // Collect answers [ question_id => option_idx ]
            var formData = new FormData(quizForm);
            var answers = {};
            formData.forEach(function (value, key) {
                var match = key.match(/answers\[(\d+)\]/);
                if (match) {
                    answers[match[1]] = parseInt(value, 10);
                }
            });

            fetch(clms_vars.rest_url + 'quizzes/' + quizId + '/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': clms_vars.rest_nonce
                },
                credentials: 'same-origin',
                body: JSON.stringify({ answers: answers })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (spinner) spinner.style.display = 'none';

                if (!data.success) {
                    if (submitBtn) submitBtn.disabled = false;
                    alert(data.message || 'Submission error. Please check your answers.');
                    return;
                }

                // Lock radio buttons to freeze answers
                var inputs = quizForm.querySelectorAll('input[type="radio"]');
                inputs.forEach(function (inp) {
                    inp.disabled = true;
                });

                // Render questions feedback
                if (data.results && Array.isArray(data.results)) {
                    data.results.forEach(function (item) {
                        var card = quizForm.querySelector('.clms-quiz-question-card[data-question-id="' + item.id + '"]');
                        if (!card) return;

                        var fb = card.querySelector('.clms-question-feedback');
                        if (!fb) return;

                        var isCorrect = item.is_correct;
                        card.classList.add(isCorrect ? 'is-correct-card' : 'is-wrong-card');

                        var html = '';
                        if (isCorrect) {
                            html += '<div class="clms-feedback-badge clms-feedback-correct">✓ Correct</div>';
                        } else {
                            html += '<div class="clms-feedback-badge clms-feedback-wrong">✗ Incorrect</div>';
                            html += '<div class="clms-feedback-solution">Correct Answer: <strong>' + escapeHtml(item.correct_answer) + '</strong></div>';
                        }

                        if (item.explanation) {
                            html += '<div class="clms-feedback-exp">💡 <em>' + escapeHtml(item.explanation) + '</em></div>';
                        }

                        fb.innerHTML = html;
                        fb.style.display = 'block';
                    });
                }

                // Render Banner at top
                var passed = (data.status === 'passed');
                var bannerHtml = '<div class="clms-quiz-banner ' + (passed ? 'is-pass' : 'is-fail') + '">' +
                    '<div class="clms-banner-icon">' + (passed ? '🎉' : '❌') + '</div>' +
                    '<div class="clms-banner-content">' +
                        '<h3 class="clms-banner-heading">' + (passed ? 'Congratulations! You Passed!' : 'Quiz Not Passed') + '</h3>' +
                        '<p class="clms-banner-stats">' +
                            'Score: <strong>' + data.score + '%</strong> (' + data.obtained_marks + ' of ' + data.total_marks + ' questions correct) • ' +
                            'Required to Pass: ' + data.pass_percent + '%' +
                        '</p>' +
                        '<p class="clms-banner-email-note">📧 A full copy of your results has been sent to your registered email address.</p>' +
                    '</div>' +
                '</div>';

                if (resultContainer) {
                    resultContainer.innerHTML = bannerHtml;
                    resultContainer.style.display = 'block';
                    resultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                // Replace submit button with Retake / Next actions
                // Show top result banner
                var isPassed = data.status === 'passed';
                var bannerHtml = '<div class="clms-quiz-result-banner ' + (isPassed ? 'is-passed' : 'is-failed') + '">' +
                    '<div class="clms-banner-icon">' + (isPassed ? '🎉' : '⚠️') + '</div>' +
                    '<div class="clms-banner-body">' +
                        '<h3 class="clms-banner-title">' + (isPassed ? 'Congratulations! You Passed!' : 'Quiz Not Passed') + '</h3>' +
                        '<p class="clms-banner-score">' +
                            'You scored <strong>' + data.obtained_marks + ' / ' + data.total_marks + ' (' + data.score + '%)</strong>. ' +
                            'Required to Pass: ' + data.pass_percent + '%' +
                        '</p>' +
                        '<p class="clms-banner-email-note">📧 A full copy of your results has been sent to your registered email address.</p>' +
                        '<div class="clms-completed-lock-notice" style="margin-top:14px;padding:12px 16px;background:#f8fafc;border-left:4px solid #2563eb;border-radius:4px;font-size:13px;color:#334155;text-align:left;">' +
                            '<strong>✓ You have already completed this quiz.</strong><br>' +
                            '<span>Quiz submissions are restricted to one attempt. Only an administrator can authorize another attempt for your account.</span>' +
                        '</div>' +
                    '</div>' +
                '</div>';

                if (resultContainer) {
                    resultContainer.innerHTML = bannerHtml;
                    resultContainer.style.display = 'block';
                    resultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                // Replace submit button with completed lock notice and curriculum navigation
                var submitBar = quizForm.querySelector('.clms-quiz-submit-bar');
                if (submitBar) {
                    submitBar.innerHTML = '';
                    var retakeBtn = document.createElement('button');
                    retakeBtn.type = 'button';
                    retakeBtn.className = 'clms-btn clms-btn-outline';
                    retakeBtn.textContent = '🔄 Retake Quiz';
                    retakeBtn.style.marginRight = '12px';
                    retakeBtn.addEventListener('click', function () {
                        // Reset form
                        inputs.forEach(function (inp) {
                            inp.disabled = false;
                            inp.checked = false;
                        });
                        quizForm.querySelectorAll('.clms-quiz-question-card').forEach(function (c) {
                            c.classList.remove('is-correct-card', 'is-wrong-card');
                        });
                        quizForm.querySelectorAll('.clms-question-feedback').forEach(function (f) {
                            f.style.display = 'none';
                            f.innerHTML = '';
                        });
                        quizForm.querySelectorAll('.clms-option-label').forEach(function (l) {
                            l.classList.remove('is-selected', 'is-correct-option', 'is-wrong-option');
                        });
                        if (resultContainer) resultContainer.style.display = 'none';

                        // Shuffle question cards and answer options dynamically in DOM on retake
                        var questionsList = quizForm.querySelector('.clms-questions-list');
                        if (questionsList) {
                            var cards = Array.prototype.slice.call(questionsList.querySelectorAll('.clms-quiz-question-card'));
                            // Shuffle question cards array (Fisher-Yates)
                            for (var i = cards.length - 1; i > 0; i--) {
                                var j = Math.floor(Math.random() * (i + 1));
                                var temp = cards[i];
                                cards[i] = cards[j];
                                cards[j] = temp;
                            }
                            // Re-append cards and shuffle options inside each card
                            cards.forEach(function (card, cIdx) {
                                questionsList.appendChild(card);
                                var numSpan = card.querySelector('.clms-question-number');
                                if (numSpan) {
                                    numSpan.textContent = 'Question ' + (cIdx + 1) + ' of ' + cards.length;
                                }
                    var completedBadge = document.createElement('div');
                    completedBadge.className = 'clms-quiz-completed-badge';
                    completedBadge.innerHTML = '🔒 ' + (data.message || 'You have already completed this quiz.');
                    completedBadge.style.display = 'inline-flex';
                    completedBadge.style.alignItems = 'center';
                    completedBadge.style.gap = '8px';
                    completedBadge.style.padding = '10px 18px';
                    completedBadge.style.background = '#f1f5f9';
                    completedBadge.style.color = '#475569';
                    completedBadge.style.borderRadius = '8px';
                    completedBadge.style.fontWeight = '600';
                    completedBadge.style.fontSize = '14px';
                    completedBadge.style.marginRight = '14px';
                    submitBar.appendChild(completedBadge);

                                var optsGroup = card.querySelector('.clms-options-group');
                                if (optsGroup) {
                                    var optLabels = Array.prototype.slice.call(optsGroup.querySelectorAll('.clms-option-label'));
                                    for (var oi = optLabels.length - 1; oi > 0; oi--) {
                                        var oj = Math.floor(Math.random() * (oi + 1));
                                        var oTemp = optLabels[oi];
                                        optLabels[oi] = optLabels[oj];
                                        optLabels[oj] = oTemp;
                                    }
                                    optLabels.forEach(function (lbl) {
                                        optsGroup.appendChild(lbl);
                                    });
                                }
                            });
                        }

                        submitBar.innerHTML = '';
                        submitBar.appendChild(submitBtn);
                        submitBtn.disabled = false;
                        quizForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });

                    submitBar.appendChild(retakeBtn);

                    var breadcrumbLinks = document.querySelectorAll('.clms-breadcrumbs a');
                    if (breadcrumbLinks.length > 0) {
                        var returnCourseLink = breadcrumbLinks[0].href;
                        var backLink = document.createElement('a');
                        backLink.href = returnCourseLink;
                        backLink.className = 'clms-btn clms-btn-primary';
                        backLink.textContent = 'Back to Course Curriculum →';
                        submitBar.appendChild(backLink);
                    }
                }
            })
            .catch(function () {
                if (spinner) spinner.style.display = 'none';
                if (submitBtn) submitBtn.disabled = false;
                alert('Server error occurred during quiz evaluation. Please try again.');
            });
        });
    }
});
