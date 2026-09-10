// Categories Filter Functionality
document.addEventListener('DOMContentLoaded', function() {
    const parentButtons = document.querySelectorAll('.btn-parent-category');
    const categoryCards = document.querySelectorAll('.category-card');
    const emptyState = document.querySelector('.categories-empty-state');
    const categoriesGrid = document.querySelector('.categories-grid');
    const productsSection = document.getElementById('productsSection');
    
    function checkEmptyState() {
        // Đếm số category card đang visible
        let visibleCount = 0;
        categoryCards.forEach(card => {
            if (card.style.display !== 'none') {
                visibleCount++;
            }
        });
        
        // Get carousel nav buttons
        const navPrev = document.getElementById('categoriesNavPrev');
        const navNext = document.getElementById('categoriesNavNext');
        
        // Hiển thị empty state nếu không có category nào
        if (visibleCount === 0) {
            if (categoriesGrid) {
                categoriesGrid.style.display = 'none';
            }
            if (emptyState) {
                emptyState.style.display = 'flex';
            }
            // Ẩn phần sản phẩm khi không có chuyên mục con
            if (productsSection) {
                productsSection.style.display = 'none';
            }
            // Ẩn mũi tên carousel
            if (navPrev) navPrev.style.display = 'none';
            if (navNext) navNext.style.display = 'none';
        } else {
            if (categoriesGrid) {
                categoriesGrid.style.display = '';
            }
            if (emptyState) {
                emptyState.style.display = 'none';
            }
            // Hiển thị phần sản phẩm khi có chuyên mục con
            if (productsSection) {
                productsSection.style.display = 'block';
            }
            // Hiển thị lại mũi tên carousel
            if (navPrev) navPrev.style.display = '';
            if (navNext) navNext.style.display = '';
        }
        
        return visibleCount;
    }
    
    if (parentButtons.length > 0 && categoryCards.length > 0) {
        parentButtons.forEach(button => {
            button.addEventListener('click', function() {
                const parentId = this.getAttribute('data-parent-id');
                
                // Update active button
                parentButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Filter categories
                categoryCards.forEach(card => {
                    const cardParentId = card.getAttribute('data-parent-id');
                    
                    if (parentId === 'all' || cardParentId === parentId) {
                        // Show ngay lập tức - không có delay
                        card.style.display = '';
                        card.classList.remove('fade-out');
                    } else {
                        // Hide ngay lập tức - không có delay
                        card.style.display = 'none';
                        card.classList.add('fade-out');
                    }
                });
                
                // Kiểm tra empty state và cập nhật hiển thị sản phẩm
                const visibleCount = checkEmptyState();
                
                // Nếu có chuyên mục con thì load sản phẩm, nếu không thì không load
                if (visibleCount > 0 && typeof window.ProductModule !== 'undefined') {
                    // Product module sẽ tự xử lý việc load sản phẩm
                }

                // Update carousel navigation visibility if has arrows
                updateCategoriesCarouselNav();
            });
        });
        
        // Kiểm tra lần đầu khi load trang
        checkEmptyState();
    }

    // ============================================
    // Categories Carousel Navigation
    // ============================================
    const categoriesCarouselGrid = document.getElementById('categoriesGrid');
    const categoriesNavPrev = document.getElementById('categoriesNavPrev');
    const categoriesNavNext = document.getElementById('categoriesNavNext');

    function updateCategoriesCarouselNav() {
        if (!categoriesCarouselGrid || (!categoriesNavPrev && !categoriesNavNext)) return;

        const scrollLeft = categoriesCarouselGrid.scrollLeft;
        const maxScrollLeft = categoriesCarouselGrid.scrollWidth - categoriesCarouselGrid.clientWidth;

        if (categoriesNavPrev) {
            categoriesNavPrev.disabled = scrollLeft <= 5;
        }
        if (categoriesNavNext) {
            categoriesNavNext.disabled = scrollLeft >= maxScrollLeft - 5;
        }
    }

    function scrollCategories(direction) {
        if (!categoriesCarouselGrid) return;

        const scrollAmount = 300; // pixels to scroll
        const targetScroll = categoriesCarouselGrid.scrollLeft + (direction * scrollAmount);
        
        categoriesCarouselGrid.scrollTo({
            left: targetScroll,
            behavior: 'smooth'
        });
    }

    if (categoriesNavPrev) {
        categoriesNavPrev.addEventListener('click', function() {
            scrollCategories(-1);
        });
    }

    if (categoriesNavNext) {
        categoriesNavNext.addEventListener('click', function() {
            scrollCategories(1);
        });
    }

    if (categoriesCarouselGrid) {
        categoriesCarouselGrid.addEventListener('scroll', function() {
            updateCategoriesCarouselNav();
        }, { passive: true });

        // Initial check
        updateCategoriesCarouselNav();
    }
});

// Modal Notification Functionality
document.addEventListener("DOMContentLoaded", function() {
    var modal = document.getElementById('modal_notification');
    var dontShowAgainBtn = document.getElementById('dontShowAgainBtn');
    
    // Cleanup backdrop khi modal đóng
    function cleanupBackdrop() {
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(backdrop) {
            backdrop.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
    
    if (modal) {
        var modalClosedTime = localStorage.getItem('modalClosedTime');

        // Nếu modalClosedTime chưa được lưu hoặc đã quá 2 giờ, hiển thị modal
        if (!modalClosedTime || (Date.now() - parseInt(modalClosedTime) > 2 * 60 * 60 * 1000)) {
            if (typeof bootstrap !== 'undefined') {
                var bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
            }
        }
        
        // Cleanup khi modal bị đóng (bất kỳ cách nào: nút X, click outside, nút button)
        modal.addEventListener('hidden.bs.modal', function() {
            cleanupBackdrop();
        });

        // Lưu thời gian khi modal được đóng khi người dùng click vào nút "Không hiển thị lại" và ẩn modal
        if (dontShowAgainBtn) {
            dontShowAgainBtn.addEventListener('click', function() {
                localStorage.setItem('modalClosedTime', Date.now());
                var bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) bootstrapModal.hide();
            });
        }
    }
});

// Announcement Bar - Close Function
function closeAnnouncement() {
    var bar = document.getElementById('announcementBar');
    if (bar) {
        bar.style.transition = 'opacity 0.2s ease, transform 0.2s ease, margin 0.2s ease, padding 0.2s ease';
        bar.style.opacity = '0';
        bar.style.transform = 'translateY(-10px)';
        
        setTimeout(function() {
            bar.style.marginBottom = '0';
            bar.style.padding = '0';
            bar.style.overflow = 'hidden';
            bar.style.height = '0';
            bar.style.border = 'none';
            
            // Lưu vào localStorage (ẩn 24 giờ)
            localStorage.setItem('announcementClosedTime', Date.now().toString());
        }, 200);
    }
}

// Check if announcement should be hidden
document.addEventListener('DOMContentLoaded', function() {
    var bar = document.getElementById('announcementBar');
    if (bar) {
        var closedTime = localStorage.getItem('announcementClosedTime');
        if (closedTime) {
            var timeDiff = Date.now() - parseInt(closedTime);
            // Ẩn nếu chưa qua 24 giờ
            if (timeDiff < 24 * 60 * 60 * 1000) {
                bar.style.display = 'none';
            } else {
                localStorage.removeItem('announcementClosedTime');
            }
        }
    }
});

// Fixed Sidebar Banners Position & Scroll Effect
// Sử dụng window.onload để đảm bảo CSS đã load xong trước khi hiển thị banner
window.addEventListener("load", function() {
    const sidebarBanners = document.querySelectorAll('.home-sidebar-banner');
    const sidebarLeft = document.querySelector('.home-sidebar-banner-left');
    const sidebarRight = document.querySelector('.home-sidebar-banner-right');
    
    // Kiểm tra trạng thái đóng banner từ localStorage (tạm đóng 24h)
    const CLOSE_DURATION = 24 * 60 * 60 * 1000; // 24 giờ tính bằng milliseconds
    
    function checkBannerStatus(side, bannerElement) {
        if (!bannerElement) return false;
        
        const storageKey = 'sidebarBanner' + (side === 'left' ? 'Left' : 'Right') + 'ClosedTime';
        const closedTime = localStorage.getItem(storageKey);
        
        if (closedTime) {
            const now = Date.now();
            const timeDiff = now - parseInt(closedTime);
            
            // Nếu chưa qua 24 giờ thì ẩn banner
            if (timeDiff < CLOSE_DURATION) {
                bannerElement.style.display = 'none';
                return false;
            } else {
                // Đã qua 24 giờ, xóa localStorage
                localStorage.removeItem(storageKey);
            }
        }
        return true;
    }
    
    const showLeft = checkBannerStatus('left', sidebarLeft);
    const showRight = checkBannerStatus('right', sidebarRight);
    
    if (sidebarBanners.length > 0) {
        // Tính toán vị trí banner dựa trên container
        function updateBannerPosition() {
            const container = document.querySelector('.section.feature-part .container');
            if (!container) return;
            
            const containerRect = container.getBoundingClientRect();
            const containerLeft = containerRect.left;
            const containerRight = window.innerWidth - containerRect.right;
            const bannerWidth = 180; // max-width của banner
            const gap = 20; // Khoảng cách từ container đến banner
            
            if (sidebarLeft && sidebarLeft.style.display !== 'none') {
                const leftPosition = Math.max(20, containerLeft - bannerWidth - gap);
                sidebarLeft.style.left = leftPosition + 'px';
            }
            
            if (sidebarRight && sidebarRight.style.display !== 'none') {
                const rightPosition = Math.max(20, containerRight - bannerWidth - gap);
                sidebarRight.style.right = rightPosition + 'px';
            }
        }
        
        // Animation bay xuống mượt mà
        function animateBannerEntry(banner, delay) {
            if (!banner || banner.style.display === 'none') return;
            
            // Đặt trạng thái ban đầu (ẩn và ở trên)
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(calc(-50% - 80px))';
            banner.style.transition = 'none';
            
            // Force reflow
            banner.offsetHeight;
            
            // Thêm transition và animate
            setTimeout(function() {
                banner.style.transition = 'opacity 0.5s ease-out, transform 0.5s cubic-bezier(0.34, 1.2, 0.64, 1)';
                banner.style.opacity = '1';
                banner.style.transform = 'translateY(-50%)';
            }, delay);
        }
        
        // Tính toán vị trí trước, sau đó mới animate
        updateBannerPosition();
        
        // Animate banners sau khi vị trí đã được set
        requestAnimationFrame(function() {
            if (showLeft) animateBannerEntry(sidebarLeft, 50);
            if (showRight) animateBannerEntry(sidebarRight, 150);
        });
        
        window.addEventListener('resize', function() {
            updateBannerPosition();
        });
        
        // Scroll effect
        let lastScrollTop = 0;
        let ticking = false;
        
        function handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            sidebarBanners.forEach(function(banner) {
                if (banner.style.display === 'none') return;
                
                // Thêm class khi scroll xuống
                if (scrollTop > 100) {
                    banner.classList.add('scrolled');
                } else {
                    banner.classList.remove('scrolled');
                }
            });
            
            lastScrollTop = scrollTop;
            ticking = false;
        }
        
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(handleScroll);
                ticking = true;
            }
        }, { passive: true });
        
        // Kiểm tra lần đầu
        handleScroll();
    }
});

// Hàm đóng banner (tạm đóng 24h)
function closeSidebarBanner(side) {
    const bannerId = side === 'left' ? 'sidebarBannerLeft' : 'sidebarBannerRight';
    const banner = document.getElementById(bannerId);
    
    if (banner) {
        // Ẩn banner với animation
        banner.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(-50%) scale(0.9)';
        
        setTimeout(function() {
            banner.style.display = 'none';
            // Lưu timestamp vào localStorage (tạm đóng 24h)
            const storageKey = 'sidebarBanner' + (side === 'left' ? 'Left' : 'Right') + 'ClosedTime';
            localStorage.setItem(storageKey, Date.now().toString());
        }, 300);
    }
}

// ============================================
// Home Slider Controller - Transform-based with Drag Support
// ============================================
let currentSlide = 0;
let slideInterval;
let sliderContainer, sliderTrack, slides, indicators, totalSlides;

// Drag/Swipe variables
let startX = 0;
let startY = 0;
let isDragging = false;
let currentX = 0;
let initialTransform = 0;
let slideWidth = 0;

function updateSliderPosition(animate = true) {
    if (!sliderTrack) return;
    
    slideWidth = sliderContainer ? sliderContainer.offsetWidth : 0;
    
    if (animate) {
        sliderTrack.classList.remove('dragging');
    } else {
        sliderTrack.classList.add('dragging');
    }
    
    sliderTrack.style.transform = `translateX(-${currentSlide * slideWidth}px)`;
    
    // Update indicators
    if (indicators) {
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentSlide);
        });
    }
}

function showSlide(index) {
    if (index < 0) {
        currentSlide = totalSlides - 1;
    } else if (index >= totalSlides) {
        currentSlide = 0;
    } else {
        currentSlide = index;
    }
    
    updateSliderPosition(true);
}

function changeSlide(direction) {
    showSlide(currentSlide + direction);
    resetAutoplay();
}

function goToSlide(index) {
    showSlide(index);
    resetAutoplay();
}

function startAutoplay() {
    if (totalSlides > 1) {
        slideInterval = setInterval(() => {
            showSlide(currentSlide + 1);
        }, 5000); // Change slide every 5 seconds
    }
}

function resetAutoplay() {
    clearInterval(slideInterval);
    startAutoplay();
}

function getPositionX(event) {
    return event.type.includes("mouse") ? event.clientX : event.touches[0].clientX;
}

function getPositionY(event) {
    return event.type.includes("mouse") ? event.clientY : event.touches[0].clientY;
}

// Touch/Mouse event handlers with real-time visual feedback
function touchStart(event) {
    // Don't start drag if clicking on a link
    if (event.target.closest("a")) {
        return;
    }
    
    startX = getPositionX(event);
    startY = getPositionY(event);
    isDragging = true;
    currentX = startX;
    
    slideWidth = sliderContainer ? sliderContainer.offsetWidth : 0;
    initialTransform = currentSlide * slideWidth;
    
    if (sliderContainer) {
        sliderContainer.classList.add('grabbing');
    }
    if (sliderTrack) {
        sliderTrack.classList.add('dragging');
    }
    
    // Pause autoplay when dragging
    clearInterval(slideInterval);
}

function touchMove(event) {
    if (!isDragging || !sliderTrack) return;
    
    currentX = getPositionX(event);
    const currentY = getPositionY(event);
    const diffX = Math.abs(currentX - startX);
    const diffY = Math.abs(currentY - startY);
    
    // Only handle horizontal drag
    if (diffX > diffY && diffX > 5) {
        event.preventDefault();
        
        const deltaX = currentX - startX;
        let newTransform = initialTransform - deltaX;
        
        // Add resistance at boundaries
        const maxTransform = (totalSlides - 1) * slideWidth;
        if (newTransform < 0) {
            newTransform = newTransform * 0.3; // Resistance at start
        } else if (newTransform > maxTransform) {
            newTransform = maxTransform + (newTransform - maxTransform) * 0.3; // Resistance at end
        }
        
        sliderTrack.style.transform = `translateX(-${newTransform}px)`;
    }
}

function touchEnd() {
    if (!isDragging) return;
    
    const movedBy = currentX - startX;
    isDragging = false;
    
    if (sliderContainer) {
        sliderContainer.classList.remove('grabbing');
    }
    if (sliderTrack) {
        sliderTrack.classList.remove('dragging');
    }
    
    // Determine slide change based on drag distance or velocity
    const threshold = slideWidth * 0.2; // 20% of slide width
    
    if (movedBy < -threshold && currentSlide < totalSlides - 1) {
        // Swipe left - next slide
        showSlide(currentSlide + 1);
    } else if (movedBy > threshold && currentSlide > 0) {
        // Swipe right - previous slide
        showSlide(currentSlide - 1);
    } else {
        // Snap back to current slide
        updateSliderPosition(true);
    }
    
    resetAutoplay();
}

// Initialize slider when DOM is ready
document.addEventListener("DOMContentLoaded", function() {
    sliderContainer = document.getElementById("homeSlider");
    sliderTrack = document.getElementById("homeSliderTrack");
    slides = sliderTrack ? sliderTrack.querySelectorAll(".home-slider-item") : [];
    indicators = document.querySelectorAll(".home-slider-indicator");
    totalSlides = slides.length;
    
    if (totalSlides > 0 && sliderContainer) {
        // Initial setup
        slideWidth = sliderContainer.offsetWidth;
        updateSliderPosition(false);
        startAutoplay();
        
        // Pause autoplay on hover
        sliderContainer.addEventListener("mouseenter", function() {
            clearInterval(slideInterval);
        });
        
        sliderContainer.addEventListener("mouseleave", function() {
            if (!isDragging) {
                startAutoplay();
            }
        });
        
        // Touch events
        sliderContainer.addEventListener("touchstart", touchStart, { passive: false });
        sliderContainer.addEventListener("touchmove", touchMove, { passive: false });
        sliderContainer.addEventListener("touchend", touchEnd);
        sliderContainer.addEventListener("touchcancel", touchEnd);
        
        // Mouse events
        sliderContainer.addEventListener("mousedown", touchStart);
        sliderContainer.addEventListener("mousemove", touchMove);
        sliderContainer.addEventListener("mouseup", touchEnd);
        sliderContainer.addEventListener("mouseleave", function() {
            if (isDragging) {
                touchEnd();
            }
        });
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                slideWidth = sliderContainer.offsetWidth;
                updateSliderPosition(false);
            }, 250);
        });
    }
});

// ============================================
// Banner Carousel Controller - Enhanced Drag Support
// ============================================
let currentBannerSlide = 0;
let bannerCarousel, bannerTrack, bannerItems, totalBannerSlides;

// Drag/Swipe variables for banner carousel
let bannerStartX = 0;
let bannerStartY = 0;
let bannerIsDragging = false;
let bannerCurrentX = 0;
let bannerInitialTransform = 0;
let bannerItemWidth = 0;

function getBannersPerView() {
    return window.innerWidth > 991 ? 4 : (window.innerWidth > 768 ? 2 : 1);
}

function getBannerPositionX(event) {
    return event.type.includes("mouse") ? event.clientX : event.touches[0].clientX;
}

function getBannerPositionY(event) {
    return event.type.includes("mouse") ? event.clientY : event.touches[0].clientY;
}

function updateBannerCarousel(animate = true) {
    if (!bannerTrack || !bannerItems || totalBannerSlides === 0) return;
    
    const bannersPerView = getBannersPerView();
    bannerItemWidth = bannerItems[0].offsetWidth + 15; // width + gap
    const maxSlide = Math.max(0, totalBannerSlides - bannersPerView);
    
    if (currentBannerSlide > maxSlide) {
        currentBannerSlide = maxSlide;
    }
    if (currentBannerSlide < 0) {
        currentBannerSlide = 0;
    }
    
    if (animate) {
        bannerTrack.classList.remove('dragging');
    } else {
        bannerTrack.classList.add('dragging');
    }
    
    bannerTrack.style.transform = `translateX(-${currentBannerSlide * bannerItemWidth}px)`;
}

function changeBannerSlide(direction) {
    if (!bannerTrack || !bannerItems || totalBannerSlides === 0) return;
    
    const bannersPerView = getBannersPerView();
    const maxSlide = Math.max(0, totalBannerSlides - bannersPerView);
    currentBannerSlide += direction;
    
    if (currentBannerSlide > maxSlide) {
        currentBannerSlide = 0;
    } else if (currentBannerSlide < 0) {
        currentBannerSlide = maxSlide;
    }
    
    updateBannerCarousel(true);
}

// Touch/Mouse event handlers for banner carousel
function bannerTouchStart(event) {
    // Don't start drag if clicking on a link
    if (event.target.closest("a")) {
        return;
    }
    
    bannerStartX = getBannerPositionX(event);
    bannerStartY = getBannerPositionY(event);
    bannerIsDragging = true;
    bannerCurrentX = bannerStartX;
    
    if (bannerTrack && bannerCarousel) {
        bannerItemWidth = bannerItems[0].offsetWidth + 15;
        bannerInitialTransform = currentBannerSlide * bannerItemWidth;
        
        bannerCarousel.classList.add("grabbing");
        bannerTrack.classList.add("dragging");
    }
}

function bannerTouchMove(event) {
    if (!bannerIsDragging || !bannerTrack) return;
    
    bannerCurrentX = getBannerPositionX(event);
    
    // Prevent vertical scrolling while dragging horizontally
    const currentY = getBannerPositionY(event);
    const diffX = Math.abs(bannerCurrentX - bannerStartX);
    const diffY = Math.abs(currentY - bannerStartY);
    
    if (diffX > diffY && diffX > 5) {
        event.preventDefault();
        
        const deltaX = bannerCurrentX - bannerStartX;
        let newTransform = bannerInitialTransform - deltaX;
        
        // Calculate bounds
        const bannersPerView = getBannersPerView();
        const maxSlide = Math.max(0, totalBannerSlides - bannersPerView);
        const maxTransform = maxSlide * bannerItemWidth;
        
        // Add resistance at boundaries
        if (newTransform < 0) {
            newTransform = newTransform * 0.3; // Resistance at start
        } else if (newTransform > maxTransform) {
            newTransform = maxTransform + (newTransform - maxTransform) * 0.3; // Resistance at end
        }
        
        bannerTrack.style.transform = `translateX(-${newTransform}px)`;
    }
}

function bannerTouchEnd() {
    if (!bannerIsDragging) return;
    
    const movedBy = bannerCurrentX - bannerStartX;
    bannerIsDragging = false;
    
    if (bannerCarousel) {
        bannerCarousel.classList.remove("grabbing");
    }
    if (bannerTrack) {
        bannerTrack.classList.remove("dragging");
    }
    
    // Determine slide change based on drag distance
    const threshold = bannerItemWidth * 0.3; // 30% of item width
    const bannersPerView = getBannersPerView();
    const maxSlide = Math.max(0, totalBannerSlides - bannersPerView);
    
    if (movedBy < -threshold && currentBannerSlide < maxSlide) {
        // Swipe left - next slide
        currentBannerSlide++;
        updateBannerCarousel(true);
    } else if (movedBy > threshold && currentBannerSlide > 0) {
        // Swipe right - previous slide
        currentBannerSlide--;
        updateBannerCarousel(true);
    } else {
        // Snap back to current position
        updateBannerCarousel(true);
    }
}

// Initialize banner carousel
document.addEventListener("DOMContentLoaded", function() {
    bannerCarousel = document.getElementById("homeBannerCarousel");
    bannerTrack = bannerCarousel ? bannerCarousel.querySelector(".home-banner-carousel-track") : null;
    bannerItems = bannerTrack ? bannerTrack.querySelectorAll(".home-banner-carousel-item") : [];
    totalBannerSlides = bannerItems.length;
    
    if (totalBannerSlides > 0 && bannerCarousel) {
        // Initial setup
        bannerItemWidth = bannerItems[0].offsetWidth + 15;
        updateBannerCarousel(false);
        
        // Touch events
        bannerCarousel.addEventListener("touchstart", bannerTouchStart, { passive: false });
        bannerCarousel.addEventListener("touchmove", bannerTouchMove, { passive: false });
        bannerCarousel.addEventListener("touchend", bannerTouchEnd);
        bannerCarousel.addEventListener("touchcancel", bannerTouchEnd);
        
        // Mouse events
        bannerCarousel.addEventListener("mousedown", bannerTouchStart);
        bannerCarousel.addEventListener("mousemove", bannerTouchMove);
        bannerCarousel.addEventListener("mouseup", bannerTouchEnd);
        bannerCarousel.addEventListener("mouseleave", function() {
            if (bannerIsDragging) {
                bannerTouchEnd();
            }
        });
        
        // Update on window resize
        let resizeTimeout;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                bannerItemWidth = bannerItems[0].offsetWidth + 15;
                updateBannerCarousel(false);
            }, 250);
        });
    }
});

// ============================================
// Recently Viewed Products Widget
// ============================================
function scrollRecentlyViewed(direction) {
    const carousel = document.getElementById('recentlyViewedCarousel');
    if (carousel) {
        const cardWidth = carousel.querySelector('.rv-product-card')?.offsetWidth || 240;
        const scrollAmount = cardWidth + 24; // card width + gap
        carousel.scrollBy({
            left: direction * scrollAmount,
            behavior: 'smooth'
        });
    }
}

// Load recently viewed products from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('recentlyViewedSection');
    const carousel = document.getElementById('recentlyViewedCarousel');
    const countEl = document.getElementById('recentlyViewedCount');

    if (!section || !carousel) return;

    let viewed = [];
    try {
        viewed = JSON.parse(localStorage.getItem('recently_viewed') || '[]');
    } catch (e) {
        viewed = [];
    }

    // Cần ít nhất 1 sản phẩm để hiển thị
    if (viewed.length === 0) {
        return;
    }

    // Fetch products info via AJAX
    fetch(BASE_URL + 'ajaxs/client/view.php?action=recently_viewed_products', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ids: viewed
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.products && data.products.length > 0) {
                // Update subtitle with count
                if (countEl) {
                    countEl.textContent = LANG_RECENTLY_VIEWED_PREFIX + ' ' + data.products.length + ' ' + LANG_RECENTLY_VIEWED_SUFFIX;
                }

                // Build HTML - Match product-card structure
                let html = '';
                data.products.forEach(product => {
                    const hasDiscount = product.original_price > product.price;
                    const discountPercent = hasDiscount ? Math.round((1 - product.price / product.original_price) * 100) : 0;

                    html += `
                    <div class="rv-product-card">
                        <a href="${product.url}" class="rv-product-card-link">
                            <div class="rv-product-image">
                                ${hasDiscount && discountPercent >= 5 ? 
                                    `<span class="rv-discount-badge">-${discountPercent}%</span>` : ''
                                }
                                ${product.image ? 
                                    `<img src="${product.image}" alt="${product.name}" loading="lazy">` :
                                    `<div class="rv-image-placeholder"><i class="fa-solid fa-image"></i></div>`
                                }
                            </div>
                            <div class="rv-product-content">
                                <h4 class="rv-product-title">${product.name}</h4>
                                <div class="rv-product-price">
                                    <span class="rv-price-current">${product.price_formatted}</span>
                                    ${hasDiscount ? 
                                        `<div class="rv-price-row">
                                            <span class="rv-price-original">${product.original_price_formatted}</span>
                                        </div>` : ''
                                    }
                                </div>
                                <div class="rv-product-meta">
                                    ${product.rating > 0 ? `
                                        <span class="rv-product-rating">
                                            <i class="fa-solid fa-star"></i>
                                            ${product.rating}
                                            <span class="rating-count">(${product.rating_count})</span>
                                        </span>
                                        <span class="rv-meta-divider">•</span>
                                    ` : ''}
                                    ${product.sold > 0 && IS_SHOW_SOLD ? `
                                        <span class="rv-product-sold">${LANG_SOLD} ${product.sold}</span>
                                        <span class="rv-meta-divider">•</span>
                                    ` : ''}
                                    <span class="rv-product-delivery ${product.is_instant ? 'delivery-instant' : 'delivery-order'}">
                                        <i class="fa-solid ${product.is_instant ? 'fa-bolt' : 'fa-truck'}"></i>
                                        ${product.is_instant ? LANG_INSTANT_DELIVERY : LANG_ORDER}
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>`;
                });

                carousel.innerHTML = html;
                section.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Error loading recently viewed:', err);
        });
});
