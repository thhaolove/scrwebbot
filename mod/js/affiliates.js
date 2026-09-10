/**
 * Affiliates Module JavaScript
 * Modern UI interactions and functionality
 */

(function() {
    'use strict';

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initClipboard();
        initWithdrawForm();
        initFilterForm();
        initAnimations();
    });

    /**
     * Initialize Clipboard functionality
     */
    function initClipboard() {
        const copyBtn = document.querySelector('.affiliate-copy-btn');
        const linkInput = document.getElementById('affiliateUrl');
        
        if (copyBtn && linkInput) {
            copyBtn.addEventListener('click', function() {
                // Select and copy
                linkInput.select();
                linkInput.setSelectionRange(0, 99999);
                
                try {
                    document.execCommand('copy');
                    
                    // Show success feedback
                    const originalHTML = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                    copyBtn.style.background = '#10b981';
                    
                    setTimeout(function() {
                        copyBtn.innerHTML = originalHTML;
                        copyBtn.style.background = '';
                    }, 2000);
                    
                    // Show toast notification if available
                    if (typeof showMessage === 'function') {
                        showMessage(window.LANG_COPIED || 'Đã sao chép link giới thiệu!', 'success');
                    }
                } catch (err) {
                    console.error('Copy failed:', err);
                }
            });
        }
        
        // Also initialize ClipboardJS if available
        if (typeof ClipboardJS !== 'undefined') {
            new ClipboardJS('.affiliate-copy-btn');
        }
    }

    /**
     * Initialize Withdraw Form
     */
    function initWithdrawForm() {
        const withdrawBtn = document.getElementById('btnWithdraw');
        
        if (!withdrawBtn) return;
        
        withdrawBtn.addEventListener('click', function() {
            const bank = document.getElementById('bank');
            const stk = document.getElementById('stk');
            const name = document.getElementById('name');
            const amount = document.getElementById('amount');
            const token = document.getElementById('token');
            
            // Validation
            if (!bank || !bank.value) {
                showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_SELECT_BANK || 'Vui lòng chọn ngân hàng', 'error');
                return;
            }
            
            if (!stk || !stk.value.trim()) {
                showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_ENTER_STK || 'Vui lòng nhập số tài khoản', 'error');
                return;
            }
            
            if (!name || !name.value.trim()) {
                showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_ENTER_NAME || 'Vui lòng nhập tên chủ tài khoản', 'error');
                return;
            }
            
            const minWithdraw = parseFloat(withdrawBtn.dataset.minWithdraw) || 100000;
            if (!amount || !amount.value || parseFloat(amount.value) < minWithdraw) {
                showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_MIN_WITHDRAW || 'Số tiền rút tối thiểu là ' + formatCurrency(minWithdraw), 'error');
                return;
            }
            
            // Captcha validation (if enabled)
            const captchaEnabled = withdrawBtn.dataset.captchaEnabled === '1';
            let captchaResponse = '';
            
            if (captchaEnabled) {
                captchaResponse = getCaptchaResponseValue();
                if (!captchaResponse) {
                    showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_CAPTCHA_REQUIRED || 'Vui lòng xác nhận Captcha', 'error');
                    return;
                }
            }
            
            // Disable button and show loading
            const originalHTML = withdrawBtn.innerHTML;
            withdrawBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (window.LANG_PROCESSING || 'Đang xử lý...');
            withdrawBtn.disabled = true;
            
            // Send AJAX request
            const formData = new FormData();
            const csrfToken = document.getElementById('csrf_token');
            formData.append('action', 'WithdrawCommission');
            formData.append('token', token ? token.value : '');
            formData.append('csrf_token', csrfToken ? csrfToken.value : '');
            formData.append('bank', bank.value);
            formData.append('stk', stk.value);
            formData.append('name', name.value);
            formData.append('amount', amount.value);
            
            // Captcha response (support multiple captcha types)
            if (captchaEnabled && captchaResponse) {
                formData.append('captcha_response', captchaResponse);
                formData.append('recaptcha', captchaResponse);
                formData.append('cf-turnstile-response', captchaResponse);
            }
            
            fetch(window.BASE_URL + 'ajaxs/client/create.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    showAlert(window.LANG_SUCCESS || 'Thành công', result.msg, 'success')
                        .then(() => {
                            window.location.reload();
                        });
                } else {
                    showAlert(window.LANG_ERROR || 'Lỗi', result.msg, 'error');
                    // Reset captcha on error
                    resetCaptcha();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert(window.LANG_ERROR || 'Lỗi', window.LANG_ERROR_OCCURRED || 'Đã xảy ra lỗi, vui lòng thử lại', 'error');
                // Reset captcha on error
                resetCaptcha();
            })
            .finally(() => {
                withdrawBtn.innerHTML = originalHTML;
                withdrawBtn.disabled = false;
            });
        });
        
        // Format amount input
        const amountInput = document.getElementById('amount');
        if (amountInput) {
            amountInput.addEventListener('input', function() {
                // Remove non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    }

    /**
     * Initialize Filter Form auto-submit
     */
    function initFilterForm() {
        const filterForm = document.querySelector('.affiliate-filter-form');
        const shortByDateSelect = document.querySelector('select[name="shortByDate"]');
        
        if (shortByDateSelect && filterForm) {
            shortByDateSelect.addEventListener('change', function() {
                filterForm.submit();
            });
        }
    }

    /**
     * Initialize Animations
     */
    function initAnimations() {
        // Animate stat cards on scroll
        const statCards = document.querySelectorAll('.affiliate-stat-card');
        
        if (statCards.length > 0 && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry, index) {
                    if (entry.isIntersecting) {
                        setTimeout(function() {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            statCards.forEach(function(card) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'all 0.4s ease';
                observer.observe(card);
            });
        }
        
        // Animate progress bar
        const progressFill = document.querySelector('.affiliate-progress-fill');
        if (progressFill) {
            const targetWidth = progressFill.style.width;
            progressFill.style.width = '0%';
            
            setTimeout(function() {
                progressFill.style.width = targetWidth;
            }, 300);
        }
        
        // Animate balance value
        const balanceValue = document.querySelector('.affiliate-balance-value');
        if (balanceValue && balanceValue.dataset.value) {
            animateNumber(balanceValue, 0, parseFloat(balanceValue.dataset.value), 1000);
        }
    }

    /**
     * Show Alert using SweetAlert if available
     */
    function showAlert(title, message, type) {
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                title: title,
                text: message,
                icon: type,
                confirmButtonText: 'OK'
            });
        } else {
            alert(message);
            return Promise.resolve();
        }
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    /**
     * Get Captcha Response Value
     * Supports: getCaptchaResponse (custom), Google reCAPTCHA, Cloudflare Turnstile
     */
    function getCaptchaResponseValue() {
        // Custom getCaptchaResponse function (if defined globally)
        if (typeof getCaptchaResponse === 'function') {
            return getCaptchaResponse();
        }
        
        // Google reCAPTCHA v2
        const gRecaptchaResponse = document.getElementById('g-recaptcha-response');
        if (gRecaptchaResponse && gRecaptchaResponse.value) {
            return gRecaptchaResponse.value;
        }
        
        // Cloudflare Turnstile
        const turnstileResponse = document.querySelector('[name="cf-turnstile-response"]');
        if (turnstileResponse && turnstileResponse.value) {
            return turnstileResponse.value;
        }
        
        // Google reCAPTCHA v3 (if available through grecaptcha object)
        if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.getResponse === 'function') {
            try {
                return grecaptcha.getResponse();
            } catch (e) {
                console.warn('grecaptcha.getResponse() error:', e);
            }
        }
        
        return '';
    }

    /**
     * Reset Captcha
     * Supports: Google reCAPTCHA, Cloudflare Turnstile
     */
    function resetCaptcha() {
        // Google reCAPTCHA
        if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.reset === 'function') {
            try {
                grecaptcha.reset();
            } catch (e) {
                console.warn('grecaptcha.reset() error:', e);
            }
        }
        
        // Cloudflare Turnstile
        if (typeof turnstile !== 'undefined' && typeof turnstile.reset === 'function') {
            try {
                turnstile.reset();
            } catch (e) {
                console.warn('turnstile.reset() error:', e);
            }
        }
    }

    /**
     * Animate number counting
     */
    function animateNumber(element, start, end, duration) {
        const startTime = performance.now();
        
        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutQuart = 1 - Math.pow(1 - progress, 4);
            const current = start + (end - start) * easeOutQuart;
            
            element.textContent = formatCurrency(Math.round(current));
            
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        
        requestAnimationFrame(update);
    }

    // Expose to global scope if needed
    window.AffiliateModule = {
        initClipboard: initClipboard,
        initWithdrawForm: initWithdrawForm,
        showAlert: showAlert
    };

})();

