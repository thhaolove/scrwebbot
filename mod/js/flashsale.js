/**
 * Flash Sale Widget JavaScript
 */
(function() {
    'use strict';

    const widget = document.getElementById('flashSaleWidget');
    if (!widget) return;

    const carousel = document.getElementById('flashSaleCarousel');
    const endTime = parseInt(widget.dataset.endTime) * 1000;
    const hoursEl = document.getElementById('countdownHours');
    const minutesEl = document.getElementById('countdownMinutes');
    const secondsEl = document.getElementById('countdownSeconds');

    // Countdown Timer
    function updateCountdown() {
        const now = Date.now();
        const remaining = Math.max(0, endTime - now);

        if (remaining <= 0) {
            widget.style.display = 'none';
            return;
        }

        const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
        const hours = Math.floor((remaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((remaining % (1000 * 60)) / 1000);

        // Show/hide days block
        const daysBlock = document.getElementById('countdownDaysBlock');
        const daysColon = document.getElementById('countdownDaysColon');
        const daysEl = document.getElementById('countdownDays');
        
        if (days > 0 && daysBlock && daysColon && daysEl) {
            daysBlock.style.display = '';
            daysColon.style.display = '';
            daysEl.textContent = days.toString().padStart(2, '0');
        } else if (daysBlock && daysColon) {
            daysBlock.style.display = 'none';
            daysColon.style.display = 'none';
        }

        hoursEl.textContent = hours.toString().padStart(2, '0');
        minutesEl.textContent = minutes.toString().padStart(2, '0');
        secondsEl.textContent = seconds.toString().padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);

    // Carousel Scroll
    window.scrollFlashSale = function(direction) {
        if (!carousel) return;
        const scrollAmount = carousel.offsetWidth * 0.8;
        carousel.scrollBy({
            left: direction * scrollAmount,
            behavior: 'smooth'
        });
    };

    // Lazy Loading
    const lazyImages = widget.querySelectorAll('img.lazy');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        }, { rootMargin: '100px 0px' });

        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.classList.add('loaded');
        });
    }
})();
