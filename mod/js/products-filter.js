/**
 * Products Filter Module
 * Xử lý bộ lọc và hiển thị sản phẩm trên trang products
 */
(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        productsPerPage: 12,
        ajaxUrl: (typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'ajaxs/client/view.php'
    };

    // State
    const state = {
        currentPage: 1,
        totalPages: 0,
        totalProducts: 0,
        isLoading: false,
        currentCategorySlug: '',
        filters: {
            parent_id: 0,
            category_id: 0,
            price_min: '',
            price_max: '',
            sort: 'default',
            keyword: ''
        }
    };

    // Elements
    let elements = {};

    /**
     * Initialize
     */
    function init() {
        // Get DOM elements
        elements = {
            filterKeyword: document.getElementById('filterKeyword'),
            filterParentCategory: document.getElementById('filterParentCategory'),
            filterChildCategory: document.getElementById('filterChildCategory'),
            filterPriceMin: document.getElementById('filterPriceMin'),
            filterPriceMax: document.getElementById('filterPriceMax'),
            filterSort: document.getElementById('filterSort'),
            btnApplyFilter: document.getElementById('btnApplyFilter'),
            btnResetFilter: document.getElementById('btnResetFilter'),
            btnLoadMore: document.getElementById('btnLoadMore'),
            loadMoreSpinner: document.getElementById('loadMoreSpinner'),
            loadMoreText: document.getElementById('loadMoreText'),
            productsGrid: document.getElementById('productsGrid'),
            productsLoading: document.getElementById('productsLoading'),
            productsEmptyState: document.getElementById('productsEmptyState'),
            productsLoadMore: document.getElementById('productsLoadMore'),
            resultsCount: document.getElementById('resultsCount')
        };

        // Set initial filters from URL parameters
        if (typeof INITIAL_CATEGORY_ID !== 'undefined' && INITIAL_CATEGORY_ID > 0) {
            state.filters.category_id = INITIAL_CATEGORY_ID;
        }
        if (typeof INITIAL_PARENT_ID !== 'undefined' && INITIAL_PARENT_ID > 0) {
            state.filters.parent_id = INITIAL_PARENT_ID;
        }
        if (typeof INITIAL_CATEGORY_SLUG !== 'undefined' && INITIAL_CATEGORY_SLUG) {
            state.currentCategorySlug = INITIAL_CATEGORY_SLUG;
        }
        if (typeof INITIAL_KEYWORD !== 'undefined' && INITIAL_KEYWORD) {
            state.filters.keyword = INITIAL_KEYWORD;
        }

        // Bind events
        bindEvents();

        // Filter child categories based on parent
        filterChildCategories();

        // Load initial products
        loadProducts(true);

        // Setup lazy loading
        setupLazyLoading();
    }

    /**
     * Bind events
     */
    function bindEvents() {
        // Parent category change
        if (elements.filterParentCategory) {
            elements.filterParentCategory.addEventListener('change', function() {
                state.filters.parent_id = this.value ? parseInt(this.value) : 0;
                state.filters.category_id = 0; // Reset child category
                // Lấy slug của parent được chọn
                const selectedOption = this.options[this.selectedIndex];
                state.currentCategorySlug = selectedOption.dataset.slug || '';
                
                if (elements.filterChildCategory) {
                    elements.filterChildCategory.value = '';
                }
                filterChildCategories();
            });
        }

        // Child category change
        if (elements.filterChildCategory) {
            elements.filterChildCategory.addEventListener('change', function() {
                state.filters.category_id = this.value ? parseInt(this.value) : 0;
                // Lấy slug của category được chọn
                const selectedOption = this.options[this.selectedIndex];
                state.currentCategorySlug = selectedOption.dataset.slug || '';
            });
        }

        // Apply filter button
        if (elements.btnApplyFilter) {
            elements.btnApplyFilter.addEventListener('click', applyFilters);
        }

        // Reset filter button
        if (elements.btnResetFilter) {
            elements.btnResetFilter.addEventListener('click', resetFilters);
        }

        // Load more button
        if (elements.btnLoadMore) {
            elements.btnLoadMore.addEventListener('click', loadMoreProducts);
        }

        // Enter key on price inputs
        if (elements.filterPriceMin) {
            elements.filterPriceMin.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyFilters();
            });
        }
        if (elements.filterPriceMax) {
            elements.filterPriceMax.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyFilters();
            });
        }
        
        // Enter key on keyword input
        if (elements.filterKeyword) {
            elements.filterKeyword.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') applyFilters();
            });
        }
    }

    /**
     * Filter child categories based on selected parent
     */
    function filterChildCategories() {
        if (!elements.filterChildCategory) return;

        const parentId = state.filters.parent_id;
        const options = elements.filterChildCategory.querySelectorAll('option[data-parent]');

        options.forEach(option => {
            if (parentId === 0 || parseInt(option.dataset.parent) === parentId) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
                if (option.selected) {
                    elements.filterChildCategory.value = '';
                    state.filters.category_id = 0;
                }
            }
        });
    }

    /**
     * Apply filters
     */
    function applyFilters() {
        // Get filter values
        state.filters.keyword = elements.filterKeyword ? elements.filterKeyword.value.trim() : '';
        state.filters.price_min = elements.filterPriceMin ? elements.filterPriceMin.value.trim() : '';
        state.filters.price_max = elements.filterPriceMax ? elements.filterPriceMax.value.trim() : '';
        state.filters.sort = elements.filterSort ? elements.filterSort.value : 'default';

        // Reset pagination
        state.currentPage = 1;

        // Load products with new filters
        loadProducts(true);

        // Update URL
        updateURL();
    }

    /**
     * Reset filters
     */
    function resetFilters() {
        // Reset state
        state.filters = {
            parent_id: 0,
            category_id: 0,
            price_min: '',
            price_max: '',
            sort: 'default',
            keyword: ''
        };
        state.currentPage = 1;
        state.currentCategorySlug = '';

        // Reset form elements
        if (elements.filterKeyword) elements.filterKeyword.value = '';
        if (elements.filterParentCategory) elements.filterParentCategory.value = '';
        if (elements.filterChildCategory) elements.filterChildCategory.value = '';
        if (elements.filterPriceMin) elements.filterPriceMin.value = '';
        if (elements.filterPriceMax) elements.filterPriceMax.value = '';
        if (elements.filterSort) elements.filterSort.value = 'default';

        // Show all child categories
        filterChildCategories();

        // Load products
        loadProducts(true);

        // Update URL - redirect to products page
        const productsUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 'products';
        window.history.pushState({}, '', productsUrl);
    }

    /**
     * Update URL with current filters
     */
    function updateURL() {
        let newURL = '';
        const params = new URLSearchParams();
        
        // Nếu có slug, sử dụng URL đẹp /category/slug
        if (state.currentCategorySlug) {
            newURL = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 'category/' + state.currentCategorySlug;
        } else {
            newURL = window.location.pathname;
            
            // Fallback: dùng query params nếu không có slug
            if (state.filters.parent_id > 0) {
                params.set('parent', state.filters.parent_id);
            }
            if (state.filters.category_id > 0) {
                params.set('category', state.filters.category_id);
            }
        }
        
        // Thêm các filter khác vào query string
        if (state.filters.keyword) {
            params.set('keyword', state.filters.keyword);
        }
        if (state.filters.price_min) {
            params.set('price_min', state.filters.price_min);
        }
        if (state.filters.price_max) {
            params.set('price_max', state.filters.price_max);
        }
        if (state.filters.sort && state.filters.sort !== 'default') {
            params.set('sort', state.filters.sort);
        }

        const queryString = params.toString();
        const finalURL = newURL + (queryString ? '?' + queryString : '');
        window.history.pushState({}, '', finalURL);
    }

    /**
     * Load products
     */
    function loadProducts(isNewFilter = false) {
        if (state.isLoading) return;

        state.isLoading = true;

        if (isNewFilter) {
            showLoading();
        } else {
            showLoadMoreSpinner(true);
        }

        // Prepare request data
        const formData = new FormData();
        formData.append('action', 'getProductsFiltered');
        formData.append('page', state.currentPage);
        formData.append('limit', CONFIG.productsPerPage);

        // Add filters
        if (state.filters.category_id > 0) {
            formData.append('filter_type', 'category');
            formData.append('category_id', state.filters.category_id);
        } else if (state.filters.parent_id > 0) {
            formData.append('filter_type', 'parent');
            formData.append('parent_id', state.filters.parent_id);
        } else {
            formData.append('filter_type', 'all');
        }

        if (state.filters.keyword) {
            formData.append('keyword', state.filters.keyword);
        }
        if (state.filters.price_min) {
            formData.append('price_min', state.filters.price_min);
        }
        if (state.filters.price_max) {
            formData.append('price_max', state.filters.price_max);
        }
        if (state.filters.sort) {
            formData.append('sort', state.filters.sort);
        }

        fetch(CONFIG.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            state.isLoading = false;

            if (data.status === 'success') {
                handleProductsResponse(data.data, isNewFilter);
            } else {
                showError(data.msg || 'Có lỗi xảy ra');
            }
        })
        .catch(error => {
            state.isLoading = false;
            console.error('Error loading products:', error);
            showError('Không thể tải sản phẩm. Vui lòng thử lại.');
        });
    }

    /**
     * Handle products response
     */
    function handleProductsResponse(data, isNewFilter) {
        const { products, pagination } = data;

        // Update state
        state.totalPages = pagination.total_pages;
        state.totalProducts = pagination.total_products;

        // Update results count
        if (elements.resultsCount) {
            elements.resultsCount.textContent = state.totalProducts;
        }

        if (products.length === 0 && isNewFilter) {
            showEmptyState();
        } else {
            showProductsSection();
            renderProducts(products, isNewFilter);
            updateLoadMoreButton(pagination);
        }

        // Hide loading
        hideLoading();
        showLoadMoreSpinner(false);

        // Refresh lazy loading
        refreshLazyLoading();
    }

    /**
     * Load more products
     */
    function loadMoreProducts() {
        if (state.isLoading || state.currentPage >= state.totalPages) return;

        state.currentPage++;
        loadProducts(false);
    }

    /**
     * Render products
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
     * Create product card
     */
    function createProductCard(product, index) {
        const card = document.createElement('div');
        card.className = 'product-card fade-in';
        card.style.animationDelay = `${index * 0.04}s`;

        // Check if product is favorited
        const isFavorited = product.is_favorited || false;
        const favoriteClass = isFavorited ? 'active' : '';
        const favoriteIcon = isFavorited ? 'fa-solid' : 'fa-regular';
        const favoriteTitle = isFavorited ? 'Bỏ yêu thích' : 'Yêu thích';

        // Build image HTML
        let imageHtml = '';
        if (product.image) {
            imageHtml = `
                <img 
                    class="lazy"
                    data-src="${escapeHtml(product.image)}"
                    src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 200'%3E%3Crect fill='%23f3f4f6' width='400' height='200'/%3E%3C/svg%3E"
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
        
        // Favorite button HTML
        const favoriteButtonHtml = `
            <button type="button" class="product-favorite-btn ${favoriteClass}" 
                    data-product-id="${product.id}" 
                    title="${favoriteTitle}"
                    onclick="event.preventDefault(); event.stopPropagation(); toggleProductFavorite(this, ${product.id});">
                <i class="${favoriteIcon} fa-heart"></i>
            </button>
        `;

        // Build price HTML
        let priceHtml = '';
        if (product.price_range && product.price_range !== '') {
            priceHtml = `<span class="product-price-range">${escapeHtml(product.price_range)}</span>`;
        } else {
            priceHtml = `<span class="product-price-current">${escapeHtml(product.price_display)}</span>`;
        }

        // Add original price and discount if available
        if (product.has_sale && product.original_price) {
            priceHtml += `
                <div class="product-price-row">
                    <span class="product-price-original">${escapeHtml(product.original_price)}</span>
                    ${product.discount_percent > 0 ? `<span class="product-discount-badge">-${product.discount_percent}%</span>` : ''}
                </div>
            `;
        }

        // Delivery status
        const isInstant = product.is_instant === true;
        
        // Check if show sold is enabled
        const showSold = typeof IS_SHOW_SOLD !== 'undefined' && IS_SHOW_SOLD === true;
        const sold = product.sold || 0;

        // Meta HTML
        let metaHtml = `
            <div class="product-card-meta">
                <span class="product-rating">
                    <i class="fa-solid fa-star"></i>
                    <span>${product.rating || (Math.random() * 1 + 4).toFixed(1)}</span>
                </span>
                ${showSold && sold > 0 ? `
                <span class="product-meta-divider">•</span>
                <span class="product-sold">${(typeof TRANSLATIONS !== 'undefined' ? TRANSLATIONS.sold : 'Đã bán')} ${sold >= 1000 ? (sold/1000).toFixed(1) + 'k' : sold}</span>
                ` : ''}
                <span class="product-meta-divider">•</span>
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
     * Show loading
     */
    function showLoading() {
        if (elements.productsLoading) elements.productsLoading.style.display = 'block';
        if (elements.productsGrid) elements.productsGrid.style.display = 'none';
        if (elements.productsEmptyState) elements.productsEmptyState.style.display = 'none';
        if (elements.productsLoadMore) elements.productsLoadMore.style.display = 'none';
    }

    /**
     * Hide loading
     */
    function hideLoading() {
        if (elements.productsLoading) elements.productsLoading.style.display = 'none';
    }

    /**
     * Show products section
     */
    function showProductsSection() {
        if (elements.productsGrid) elements.productsGrid.style.display = 'grid';
        if (elements.productsEmptyState) elements.productsEmptyState.style.display = 'none';
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        if (elements.productsGrid) {
            elements.productsGrid.innerHTML = '';
            elements.productsGrid.style.display = 'none';
        }
        if (elements.productsEmptyState) elements.productsEmptyState.style.display = 'flex';
        if (elements.productsLoadMore) elements.productsLoadMore.style.display = 'none';
        hideLoading();
    }

    /**
     * Show error
     */
    function showError(message) {
        hideLoading();
        showLoadMoreSpinner(false);
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi',
                text: message,
                confirmButtonColor: 'var(--primary)'
            });
        } else {
            alert(message);
        }
    }

    /**
     * Update load more button
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
     * Escape HTML
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Lazy Loading
    let lazyLoadObserver = null;

    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            lazyLoadObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.add('loaded');
                            lazyLoadObserver.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '100px 0px'
            });
        }
    }

    function refreshLazyLoading() {
        if (!lazyLoadObserver) return;
        
        const lazyImages = document.querySelectorAll('img.lazy:not(.loaded)');
        lazyImages.forEach(img => {
            lazyLoadObserver.observe(img);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/**
 * Toggle product favorite (global function)
 * @param {HTMLElement} btn - The button element
 * @param {number} productId - Product ID
 */
function toggleProductFavorite(btn, productId) {
    if (!btn || btn.disabled) return;
    
    // Check if user is logged in
    const userToken = getUserTokenGlobal();
    if (!userToken) {
        const loginUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 
            'client/login?redirect=' + encodeURIComponent(window.location.href);
        showMessageGlobal('Vui lòng đăng nhập để sử dụng tính năng này', 'warning');
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
    formData.append('csrf_token', getCSRFTokenGlobal());
    
    const ajaxUrl = (typeof BASE_URL !== 'undefined' ? BASE_URL : '/') + 'ajaxs/client/create.php';
    
    fetch(ajaxUrl, {
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
            updateNavFavoritesCountGlobal(data.is_favorited);
            // Show success message
            showMessageGlobal(data.msg, 'success');
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
            showMessageGlobal(data.msg || 'Đã xảy ra lỗi', 'error');
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
        showMessageGlobal('Đã xảy ra lỗi, vui lòng thử lại', 'error');
    });
}

/**
 * Get user token (global helper)
 */
function getUserTokenGlobal() {
    const tokenInput = document.getElementById('userToken');
    if (tokenInput && tokenInput.value) {
        return tokenInput.value;
    }
    if (typeof USER_TOKEN !== 'undefined' && USER_TOKEN) {
        return USER_TOKEN;
    }
    return null;
}

/**
 * Get CSRF token (global helper)
 */
function getCSRFTokenGlobal() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Update nav favorites count (global helper)
 */
function updateNavFavoritesCountGlobal(isAdded) {
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
 * Show message (global helper)
 */
function showMessageGlobal(message, type) {
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
