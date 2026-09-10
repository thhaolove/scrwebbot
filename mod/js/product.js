/**
 * Product Module - Hiển thị sản phẩm theo chuyên mục
 * Hỗ trợ: AJAX loading, Lazy Loading, Load More
 * Filter: all (tất cả), parent (theo chuyên mục cha), category (theo chuyên mục con)
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        ajaxUrl: typeof BASE_URL !== 'undefined' ? BASE_URL + 'ajaxs/client/view.php' : window.location.origin + '/ajaxs/client/view.php',
        productsPerPage: 8,
        lazyLoadThreshold: 100 // px before element comes into view
    };

    // State
    let state = {
        filterType: 'all', // 'all', 'parent', 'category'
        currentCategoryId: null,
        currentParentId: null,
        currentCategoryName: '',
        currentCategorySlug: '',
        currentPage: 1,
        totalPages: 1,
        totalProducts: 0,
        isLoading: false,
        products: []
    };

    // DOM Elements Cache
    let elements = {};

    /**
     * Initialize the module
     */
    function init() {
        cacheElements();
        bindEvents();
        setupLazyLoading();
        
        // Tự động load tất cả sản phẩm khi trang được tải
        loadAllProducts();
    }

    /**
     * Cache DOM elements for performance
     */
    function cacheElements() {
        elements = {
            productsSection: document.getElementById('productsSection'),
            productsGrid: document.getElementById('productsGrid'),
            productsLoading: document.getElementById('productsLoading'),
            productsEmptyState: document.getElementById('productsEmptyState'),
            productsLoadMore: document.getElementById('productsLoadMore'),
            productsSectionTitle: document.getElementById('productsSectionTitle'),
            viewAllProductsLink: document.getElementById('viewAllProductsLink'),
            btnLoadMore: document.getElementById('btnLoadMore'),
            loadMoreSpinner: document.getElementById('loadMoreSpinner'),
            loadMoreText: document.getElementById('loadMoreText'),
            categoryCards: document.querySelectorAll('.category-card[data-category-id]'),
            parentCategoryBtns: document.querySelectorAll('.btn-parent-category[data-parent-id]')
        };
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Click on category card (chuyên mục con)
        if (elements.categoryCards) {
            elements.categoryCards.forEach(card => {
                card.addEventListener('click', handleCategoryClick);
                card.style.cursor = 'pointer';
            });
        }

        // Click on parent category button (chuyên mục cha)
        if (elements.parentCategoryBtns) {
            elements.parentCategoryBtns.forEach(btn => {
                btn.addEventListener('click', handleParentCategoryClick);
            });
        }

        // Load more button
        if (elements.btnLoadMore) {
            elements.btnLoadMore.addEventListener('click', handleLoadMore);
        }
    }

    /**
     * Load all products (mặc định khi vào trang)
     */
    function loadAllProducts() {
        state.filterType = 'all';
        state.currentCategoryId = null;
        state.currentParentId = null;
        state.currentPage = 1;
        state.products = [];
        
        loadProducts(true, false); // isNewFilter = true, shouldScroll = false
    }

    /**
     * Handle parent category button click (chuyên mục cha)
     */
    function handleParentCategoryClick(e) {
        const btn = e.currentTarget;
        const parentId = btn.getAttribute('data-parent-id');
        
        // Remove active-category from all child category cards
        if (elements.categoryCards) {
            elements.categoryCards.forEach(c => c.classList.remove('active-category'));
        }

        // Kiểm tra xem có chuyên mục con không
        const hasChildCategories = checkHasChildCategories(parentId);

        if (parentId === 'all') {
            // Load tất cả sản phẩm
            loadAllProducts();
        } else if (hasChildCategories) {
            // Load sản phẩm theo chuyên mục cha (chỉ khi có chuyên mục con)
            state.filterType = 'parent';
            state.currentParentId = parentId;
            state.currentCategoryId = null;
            state.currentPage = 1;
            state.products = [];
            
            loadProducts(true, false);
        } else {
            // Không có chuyên mục con - ẩn section sản phẩm
            hideProductsSection();
        }
    }

    /**
     * Kiểm tra xem chuyên mục cha có chuyên mục con không
     */
    function checkHasChildCategories(parentId) {
        if (parentId === 'all') return true;
        
        let count = 0;
        if (elements.categoryCards) {
            elements.categoryCards.forEach(card => {
                if (card.getAttribute('data-parent-id') === parentId) {
                    count++;
                }
            });
        }
        return count > 0;
    }

    /**
     * Ẩn section sản phẩm
     */
    function hideProductsSection() {
        if (elements.productsSection) {
            elements.productsSection.style.display = 'none';
        }
    }

    /**
     * Handle category card click (chuyên mục con)
     */
    function handleCategoryClick(e) {
        e.preventDefault();
        
        const card = e.currentTarget;
        const categoryId = card.getAttribute('data-category-id');
        const categoryName = card.getAttribute('data-category-name');
        
        if (!categoryId) return;

        // Remove active class from all cards
        if (elements.categoryCards) {
            elements.categoryCards.forEach(c => c.classList.remove('active-category'));
        }
        
        // Add active class to clicked card
        card.classList.add('active-category');

        // Reset state and load products
        state.filterType = 'category';
        state.currentCategoryId = categoryId;
        state.currentParentId = null;
        state.currentCategoryName = categoryName;
        state.currentPage = 1;
        state.products = [];

        loadProducts(true, true);
    }

    /**
     * Handle load more button click
     */
    function handleLoadMore() {
        if (state.isLoading || state.currentPage >= state.totalPages) return;
        
        state.currentPage++;
        loadProducts(false, false);
    }

    /**
     * Load products via AJAX
     * @param {boolean} isNewFilter - Có phải filter mới không
     * @param {boolean} shouldScroll - Có cuộn đến section sản phẩm không
     */
    function loadProducts(isNewFilter, shouldScroll) {
        if (state.isLoading) return;
        
        state.isLoading = true;

        // Show loading state
        if (isNewFilter) {
            showLoading();
        } else {
            showLoadMoreSpinner(true);
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'getProductsByCategory');
        formData.append('filter_type', state.filterType);
        formData.append('page', state.currentPage);
        formData.append('limit', CONFIG.productsPerPage);
        
        if (state.filterType === 'category' && state.currentCategoryId) {
            formData.append('category_id', state.currentCategoryId);
        } else if (state.filterType === 'parent' && state.currentParentId) {
            formData.append('parent_id', state.currentParentId);
        }

        // Make AJAX request
        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            state.isLoading = false;
            
            if (data.status === 'success') {
                handleProductsResponse(data.data, isNewFilter, shouldScroll);
            } else {
                showError(data.msg || 'Đã xảy ra lỗi');
            }
        })
        .catch(error => {
            state.isLoading = false;
            console.error('Error loading products:', error);
            showError('Không thể tải sản phẩm. Vui lòng thử lại.');
        });
    }

    /**
     * Handle successful products response
     */
    function handleProductsResponse(data, isNewFilter, shouldScroll) {
        const { products, pagination, category } = data;

        // Update state
        state.totalPages = pagination.total_pages;
        state.totalProducts = pagination.total_products;
        state.currentCategorySlug = category.slug;

        if (isNewFilter) {
            state.products = products;
        } else {
            state.products = [...state.products, ...products];
        }

        // Update UI
        updateSectionHeader(category);
        
        if (products.length === 0 && isNewFilter) {
            showEmptyState();
        } else {
            // Chỉ gọi showProductsSection() khi có sản phẩm
            showProductsSection();
            renderProducts(products, isNewFilter);
            updateLoadMoreButton(pagination);
        }
        
        // Hide loading
        hideLoading();
        showLoadMoreSpinner(false);

        // Scroll to products section if needed
        if (shouldScroll && isNewFilter) {
            scrollToProducts();
        }

        // Initialize lazy loading for new images
        refreshLazyLoading();
    }

    /**
     * Update section header with category info
     */
    function updateSectionHeader(category) {
        if (elements.productsSectionTitle) {
            const fireIcon = '<i class="fa-solid fa-fire" style="color: #ff6b35; margin-right: 8px;"></i>';
            // Nếu đang hiển thị tất cả sản phẩm, giữ tiêu đề mặc định
            if (state.filterType === 'all') {
                elements.productsSectionTitle.innerHTML = fireIcon + TRANSLATIONS.text_featured_products;
            } else {
                elements.productsSectionTitle.innerHTML = fireIcon + category.name;
            }
        }
        
        if (elements.viewAllProductsLink) {
            // Luôn hiển thị link "Xem tất cả" và cập nhật href theo filter hiện tại
            let productsUrl = typeof base_url === 'function' ? base_url('products') : '/products';
            
            // Sử dụng URL đẹp /category/slug nếu có slug
            if (category.slug) {
                productsUrl = typeof base_url === 'function' 
                    ? base_url('category/' + category.slug) 
                    : '/category/' + category.slug;
            }
            
            elements.viewAllProductsLink.href = productsUrl;
            elements.viewAllProductsLink.style.display = 'inline-flex';
        }
    }

    /**
     * Render products to grid
     */
    function renderProducts(products, clearExisting) {
        if (!elements.productsGrid) return;

        if (clearExisting) {
            elements.productsGrid.innerHTML = '';
        }

        const fragment = document.createDocumentFragment();

        products.forEach((product, index) => {
            const card = createProductCard(product, index);
            fragment.appendChild(card);
        });

        elements.productsGrid.appendChild(fragment);
    }

    /**
     * Create a product card element - Match Design
     */
    function createProductCard(product, index) {
        const card = document.createElement('div');
        card.className = 'product-card fade-in';
        card.style.animationDelay = `${index * 0.04}s`;

        // Build image HTML
        // Check if product is favorited (only if user is logged in)
        const isFavorited = product.is_favorited || false;
        const favoriteClass = isFavorited ? 'active' : '';
        const favoriteIcon = isFavorited ? 'fa-solid' : 'fa-regular';
        const favoriteTitle = isFavorited ? 'Bỏ yêu thích' : 'Yêu thích';
        
        let imageHtml = '';
        if (product.image) {
            imageHtml = `
                <img 
                    class="lazy" 
                    data-src="${escapeHtml(product.image)}"
                    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 250'%3E%3Crect fill='%23f3f4f6' width='400' height='250'/%3E%3C/svg%3E"
                    alt="${escapeHtml(product.name)}"
                    loading="lazy"
                    decoding="async"
                >
            `;
        } else {
            imageHtml = `
                <div class="product-image-placeholder">
                    <i class="fa-solid fa-image"></i>
                </div>
            `;
        }

        // Build discount badge for image overlay
        const discountBadge = (product.has_sale && product.discount_percent > 0) 
            ? `<span class="product-discount-badge">Sale ${product.discount_percent}%</span>` 
            : '';
        
        // Favorite button HTML
        const favoriteButtonHtml = `
            <button type="button" class="product-favorite-btn ${favoriteClass}" 
                    data-product-id="${product.id}" 
                    title="${favoriteTitle}"
                    onclick="event.preventDefault(); event.stopPropagation(); ProductModule.toggleFavorite(this, ${product.id});">
                <i class="${favoriteIcon} fa-heart"></i>
            </button>
        `;

        // Build price HTML: price range hoặc single price
        let priceHtml = '';
        
        // Nếu có price_range (nhiều mức giá)
        if (product.price_range && product.price_range !== '') {
            priceHtml = `<span class="product-price-range">${escapeHtml(product.price_range)}</span>`;
        } else {
            priceHtml = `<span class="product-price-current">${escapeHtml(product.price_display)}</span>`;
        }

        // Lấy dữ liệu rating, sold từ backend
        const rating = product.rating ? parseFloat(product.rating).toFixed(1) : 0;
        const ratingCount = product.rating_count || 0;
        const sold = product.sold || 0;
        // is_instant từ backend: true nếu có gói giao ngay, false nếu chỉ có order
        const isInstant = product.is_instant === true;

        // Check if show sold is enabled
        const showSold = typeof IS_SHOW_SOLD !== 'undefined' && IS_SHOW_SOLD === true;

        // Build meta HTML (rating, sold, delivery)
        let metaHtml = `
            <div class="product-card-meta">
                ${rating > 0 ? `
                <span class="product-rating">
                    <i class="fa-solid fa-star"></i>
                    <span>${rating}</span>
                    ${ratingCount > 0 ? `<span class="rating-count">(${ratingCount})</span>` : ''}
                </span>
                <span class="product-meta-divider">•</span>
                ` : ''}
                ${showSold && sold > 0 ? `
                <span class="product-sold">${(typeof TRANSLATIONS !== 'undefined' ? TRANSLATIONS.sold : 'Đã bán')} ${sold >= 1000 ? (sold/1000).toFixed(1) + 'k' : sold}</span>
                <span class="product-meta-divider">•</span>
                ` : ''}
                <span class="product-delivery ${isInstant ? 'delivery-instant' : 'delivery-order'}">
                    <i class="fa-solid ${isInstant ? 'fa-bolt' : 'fa-shopping-cart'}"></i>
                    ${isInstant ? (typeof TRANSLATIONS !== 'undefined' ? TRANSLATIONS.deliveryInstant : 'Giao ngay') : (typeof TRANSLATIONS !== 'undefined' ? TRANSLATIONS.deliveryOrder : 'Order')}
                </span>
            </div>
        `;

        card.innerHTML = `
            <a href="${escapeHtml(product.url)}" class="product-card-link">
                <div class="product-card-image">
                    ${imageHtml}
                    ${discountBadge}
                    ${favoriteButtonHtml}
                </div>
                <div class="product-card-content">
                    <h4 class="product-card-title">${escapeHtml(product.name)}</h4>
                    <div class="product-card-price">
                        ${priceHtml}
                    </div>
                    ${metaHtml}
                </div>
            </a>
        `;

        return card;
    }

    /**
     * Update load more button visibility and text
     */
    function updateLoadMoreButton(pagination) {
        if (!elements.productsLoadMore) return;

        if (pagination.has_more) {
            elements.productsLoadMore.style.display = 'flex';
        } else {
            elements.productsLoadMore.style.display = 'none';
        }
    }

    /**
     * Show products section
     */
    function showProductsSection() {
        if (elements.productsSection) {
            elements.productsSection.style.display = 'block';
        }
        if (elements.productsEmptyState) {
            elements.productsEmptyState.style.display = 'none';
        }
        if (elements.productsGrid) {
            elements.productsGrid.style.display = 'grid';
        }
    }

    /**
     * Show loading skeleton
     */
    function showLoading() {
        if (elements.productsLoading) {
            elements.productsLoading.style.display = 'block';
        }
        if (elements.productsGrid) {
            elements.productsGrid.style.display = 'none';
        }
        if (elements.productsEmptyState) {
            elements.productsEmptyState.style.display = 'none';
        }
        if (elements.productsLoadMore) {
            elements.productsLoadMore.style.display = 'none';
        }
        if (elements.productsSection) {
            elements.productsSection.style.display = 'block';
        }
    }

    /**
     * Hide loading skeleton
     */
    function hideLoading() {
        if (elements.productsLoading) {
            elements.productsLoading.style.display = 'none';
        }
    }

    /**
     * Show/hide load more spinner
     */
    function showLoadMoreSpinner(show) {
        if (elements.loadMoreSpinner) {
            elements.loadMoreSpinner.style.display = show ? 'inline-block' : 'none';
        }
        if (elements.btnLoadMore) {
            elements.btnLoadMore.disabled = show;
        }
        if (elements.loadMoreText) {
            elements.loadMoreText.textContent = show ? 'Đang tải...' : 'Tải thêm sản phẩm';
        }
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        // Clear sản phẩm cũ trong grid
        if (elements.productsGrid) {
            elements.productsGrid.innerHTML = '';
            elements.productsGrid.style.display = 'none';
        }
        if (elements.productsEmptyState) {
            elements.productsEmptyState.style.display = 'flex';
        }
        if (elements.productsLoadMore) {
            elements.productsLoadMore.style.display = 'none';
        }
        // Vẫn hiển thị section (với empty state bên trong)
        if (elements.productsSection) {
            elements.productsSection.style.display = 'block';
        }
        hideLoading();
    }

    /**
     * Show error message
     */
    function showError(message) {
        hideLoading();
        showLoadMoreSpinner(false);
        
        // Use existing notification system if available
        if (typeof showMessage === 'function') {
            showMessage(message, 'error');
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            alert(message);
        }
    }

    /**
     * Scroll to products section
     */
    function scrollToProducts() {
        if (elements.productsSection) {
            const offset = 100;
            const elementPosition = elements.productsSection.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    }

    /**
     * Setup Intersection Observer for lazy loading
     */
    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            window.productLazyObserver = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            loadImage(img);
                            observer.unobserve(img);
                        }
                    });
                },
                {
                    rootMargin: `${CONFIG.lazyLoadThreshold}px`,
                    threshold: 0.01
                }
            );
        }
    }

    /**
     * Refresh lazy loading for new images
     */
    function refreshLazyLoading() {
        const lazyImages = document.querySelectorAll('.product-card-image img.lazy:not(.loaded)');
        
        if (window.productLazyObserver) {
            lazyImages.forEach(img => {
                window.productLazyObserver.observe(img);
            });
        } else {
            // Fallback for browsers without IntersectionObserver
            lazyImages.forEach(img => loadImage(img));
        }
    }

    /**
     * Load a lazy image
     */
    function loadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) return;

        // Create a new image to preload
        const tempImg = new Image();
        
        tempImg.onload = function() {
            img.src = src;
            img.classList.add('loaded');
            img.removeAttribute('data-src');
        };

        tempImg.onerror = function() {
            img.classList.add('loaded');
            img.removeAttribute('data-src');
        };

        tempImg.src = src;
    }

    /**
     * Toggle favorite status for a product
     */
    function toggleFavorite(btn, productId) {
        if (!btn || btn.disabled) return;
        
        // Check if user is logged in
        const userToken = getUserToken();
        if (!userToken) {
            const loginUrl = typeof base_url === 'function' 
                ? base_url('client/login?redirect=' + encodeURIComponent(window.location.href))
                : '/client/login?redirect=' + encodeURIComponent(window.location.href);
            showMessage('Vui lòng đăng nhập để sử dụng tính năng này', 'warning');
            setTimeout(() => {
                window.location.href = loginUrl;
            }, 1500);
            return;
        }
        
        const icon = btn.querySelector('i');
        const isActive = btn.classList.contains('active');
        const newState = !isActive;
        
        // Disable button temporarily
        btn.disabled = true;
        
        // Toggle UI immediately for better UX
        if (newState) {
            btn.classList.add('active');
            icon.classList.remove('fa-regular');
            icon.classList.add('fa-solid');
            btn.setAttribute('title', 'Bỏ yêu thích');
        } else {
            btn.classList.remove('active');
            icon.classList.remove('fa-solid');
            icon.classList.add('fa-regular');
            btn.setAttribute('title', 'Yêu thích');
        }
        
        // Call API to save favorite state
        const formData = new FormData();
        formData.append('action', 'toggleProductFavorite');
        formData.append('token', userToken);
        formData.append('product_id', productId);
        formData.append('is_favorite', newState ? '1' : '0');
        formData.append('csrf_token', getCSRFToken());
        
        fetch(CONFIG.ajaxUrl.replace('view.php', 'create.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            
            if (data.status === 'success') {
                // Update UI based on actual server response
                if (data.is_favorited) {
                    btn.classList.add('active');
                    icon.classList.remove('fa-regular');
                    icon.classList.add('fa-solid');
                    btn.setAttribute('title', 'Bỏ yêu thích');
                } else {
                    btn.classList.remove('active');
                    icon.classList.remove('fa-solid');
                    icon.classList.add('fa-regular');
                    btn.setAttribute('title', 'Yêu thích');
                }
                // Update nav favorites count
                updateNavFavoritesCount(data.is_favorited);
                // Show success message
                showMessage(data.msg, 'success');
            } else {
                // Revert UI on error
                if (isActive) {
                    btn.classList.add('active');
                    icon.classList.remove('fa-regular');
                    icon.classList.add('fa-solid');
                    btn.setAttribute('title', 'Bỏ yêu thích');
                } else {
                    btn.classList.remove('active');
                    icon.classList.remove('fa-solid');
                    icon.classList.add('fa-regular');
                    btn.setAttribute('title', 'Yêu thích');
                }
                showMessage(data.msg || 'Đã xảy ra lỗi', 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            console.error('Error toggling favorite:', error);
            // Revert UI on error
            if (isActive) {
                btn.classList.add('active');
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
                btn.setAttribute('title', 'Bỏ yêu thích');
            } else {
                btn.classList.remove('active');
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
                btn.setAttribute('title', 'Yêu thích');
            }
            showMessage('Đã xảy ra lỗi, vui lòng thử lại', 'error');
        });
    }
    
    /**
     * Get user token from hidden input or cookie
     */
    function getUserToken() {
        // Try to get from hidden input first
        const tokenInput = document.getElementById('userToken');
        if (tokenInput && tokenInput.value) {
            return tokenInput.value;
        }
        // Try to get from window variable
        if (typeof USER_TOKEN !== 'undefined' && USER_TOKEN) {
            return USER_TOKEN;
        }
        return null;
    }
    
    /**
     * Get CSRF token from meta tag
     */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    
    /**
     * Update nav favorites count
     */
    function updateNavFavoritesCount(isAdded) {
        const navCount = document.getElementById('numFavorites');
        if (!navCount) return;
        
        let currentCount = parseInt(navCount.textContent) || 0;
        
        if (isAdded) {
            currentCount++;
        } else {
            currentCount = Math.max(0, currentCount - 1);
        }
        
        navCount.textContent = currentCount;
        navCount.style.display = currentCount > 0 ? '' : 'none';
    }
    
    /**
     * Show message using notification system
     */
    function showMessage(message, type) {
        const notifyType = type === 'warning' ? 'error' : (type === 'error' ? 'error' : 'success');
        
        if (typeof Notify !== 'undefined') {
            new Notify({
                effect: 'fade',
                speed: 300,
                showIcon: true,
                showCloseButton: true,
                autoclose: true,
                autotimeout: 3000,
                gap: 20,
                distance: 20,
                type: 'outline',
                position: 'right top',
                status: notifyType,
                title: notifyType === 'success' ? 'Thành công!' : 'Thất bại!',
                text: message
            });
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose public API
    window.ProductModule = {
        loadAllProducts: loadAllProducts,
        loadProducts: loadProducts,
        refresh: refreshLazyLoading,
        getState: () => ({ ...state }),
        toggleFavorite: toggleFavorite
    };

})();
