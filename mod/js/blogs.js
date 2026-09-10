/**
 * BLOGS JS - News Magazine Interactions
 */

document.addEventListener('DOMContentLoaded', function() {
    initBlogSearch();
    initLazyImages();
    initCardHoverEffects();
    initSmoothScroll();
    initReadingProgress();
});

/**
 * Blog Search Handler
 */
function initBlogSearch() {
    const searchForm = document.getElementById('blogSearchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const input = this.querySelector('input[name="search"]');
            if (input && input.value.trim() === '') {
                input.removeAttribute('name');
            }
        });
    }
}

/**
 * Lazy Loading Images with Intersection Observer
 */
function initLazyImages() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '100px 0px',
            threshold: 0.01
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        document.querySelectorAll('img[data-src]').forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
    }
}

/**
 * Card Hover Effects with Animation
 */
function initCardHoverEffects() {
    if ('IntersectionObserver' in window) {
        const cards = document.querySelectorAll('.news-card, .news-hero-main, .news-hero-side-item');
        
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 80);
                    cardObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '0px 0px -30px 0px',
            threshold: 0.1
        });

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            cardObserver.observe(card);
        });
    }
}

/**
 * Smooth Scroll for Navigation
 */
function initSmoothScroll() {
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function() {
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        });
    });
}

/**
 * Reading Progress Bar (Article Page)
 */
function initReadingProgress() {
    const article = document.querySelector('.article-content');
    
    if (article) {
        // Create progress bar
        const progressBar = document.createElement('div');
        progressBar.id = 'readingProgress';
        progressBar.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(135deg, var(--primary), var(--primary1));
            z-index: 9999;
            transition: width 0.1s ease;
        `;
        document.body.appendChild(progressBar);

        window.addEventListener('scroll', function() {
            const articleTop = article.offsetTop;
            const articleHeight = article.offsetHeight;
            const windowHeight = window.innerHeight;
            const scrollTop = window.pageYOffset;
            
            const start = articleTop - windowHeight;
            const end = articleTop + articleHeight - windowHeight;
            
            if (scrollTop > start && scrollTop < end) {
                const progress = ((scrollTop - start) / (end - start)) * 100;
                progressBar.style.width = Math.min(progress, 100) + '%';
            } else if (scrollTop >= end) {
                progressBar.style.width = '100%';
            } else {
                progressBar.style.width = '0%';
            }
        });
    }
}

/**
 * Copy Blog URL to Clipboard
 */
function copyBlogUrl(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showCopySuccess();
        }).catch(function() {
            fallbackCopy(url);
        });
    } else {
        fallbackCopy(url);
    }
}

/**
 * Fallback Copy Method for older browsers
 */
function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.cssText = 'position: fixed; left: -9999px; top: -9999px;';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        const successful = document.execCommand('copy');
        document.body.removeChild(textarea);
        if (successful) {
            showCopySuccess();
        } else {
            showCopyError();
        }
    } catch (err) {
        document.body.removeChild(textarea);
        showCopyError();
    }
}

/**
 * Show Success Message
 */
function showCopySuccess() {
    if (typeof showMessage === 'function') {
        showMessage('Đã sao chép link bài viết!', 'success');
    } else {
        showToast('Đã sao chép link bài viết!', 'success');
    }
}

/**
 * Show Error Message
 */
function showCopyError() {
    if (typeof showMessage === 'function') {
        showMessage('Không thể sao chép!', 'error');
    } else {
        showToast('Không thể sao chép!', 'error');
    }
}

/**
 * Simple Toast Notification
 */
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'blog-toast ' + type;
    toast.innerHTML = `
        <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    img.loaded {
        animation: fadeIn 0.4s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;
document.head.appendChild(style);
