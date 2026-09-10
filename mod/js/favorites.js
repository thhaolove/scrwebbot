/**
 * Favorites Page Module
 * Handles favorite products management
 */
(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiUrl: window.BASE_URL + 'ajaxs/client/create.php',
        userToken: '',
        animationDuration: 300
    };

    // Translations
    const TRANSLATIONS = window.FAVORITES_TRANSLATIONS || {
        removeSuccess: 'Đã xóa khỏi danh sách yêu thích',
        errorOccurred: 'Đã xảy ra lỗi, vui lòng thử lại',
        confirmRemove: 'Bạn có chắc muốn xóa sản phẩm này khỏi danh sách yêu thích?',
        product: 'sản phẩm',
        noFavorites: 'Chưa có sản phẩm yêu thích',
        noFavoritesText: 'Hãy thêm sản phẩm vào danh sách yêu thích để xem lại sau',
        explore: 'Khám phá sản phẩm'
    };

    // State
    const state = {
        isProcessing: false,
        currentFilter: 'all'
    };

    // DOM Elements
    const elements = {};

    /**
     * Initialize
     */
    function init() {
        // Get user token
        const tokenEl = document.getElementById('userToken');
        if (tokenEl) {
            CONFIG.userToken = tokenEl.value;
        }

        // Cache elements
        elements.list = document.querySelector('.favorites-list');
        elements.headerCount = document.getElementById('favoritesCount');
        elements.filterBtns = document.querySelectorAll('.favorites-filter-btn');

        // Bind events
        bindEvents();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Remove buttons (event delegation)
        document.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.favorite-item-btn-remove');
            if (removeBtn) {
                e.preventDefault();
                const productId = removeBtn.dataset.productId;
                if (productId) {
                    removeFavorite(removeBtn, productId);
                }
            }
        });

        // Filter buttons
        elements.filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterFavorites(this.dataset.filter);
            });
        });
    }

    /**
     * Get CSRF Token
     */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Remove favorite
     */
    function removeFavorite(btn, productId) {
        if (state.isProcessing) return;

        // Confirm
        if (!confirm(TRANSLATIONS.confirmRemove)) return;

        state.isProcessing = true;

        // Show loading
        const item = btn.closest('.favorite-item');
        btn.classList.add('is-loading');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'toggleProductFavorite');
        formData.append('token', CONFIG.userToken);
        formData.append('product_id', productId);
        formData.append('csrf_token', getCSRFToken());

        // Send request
        fetch(CONFIG.apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Animate removal
                item.classList.add('is-removing');
                
                setTimeout(() => {
                    item.remove();
                    updateFavoritesCount();
                    showToast(TRANSLATIONS.removeSuccess, 'success');
                }, CONFIG.animationDuration);
            } else {
                showToast(data.msg || TRANSLATIONS.errorOccurred, 'error');
                resetRemoveButton(btn);
            }
        })
        .catch(() => {
            showToast(TRANSLATIONS.errorOccurred, 'error');
            resetRemoveButton(btn);
        })
        .finally(() => {
            state.isProcessing = false;
        });
    }

    /**
     * Reset remove button
     */
    function resetRemoveButton(btn) {
        btn.classList.remove('is-loading');
        btn.innerHTML = '<i class="fa-solid fa-trash-alt"></i>';
    }

    /**
     * Update favorites count
     */
    function updateFavoritesCount() {
        const items = document.querySelectorAll('.favorite-item');
        const count = items.length;

        // Update header count
        if (elements.headerCount) {
            elements.headerCount.textContent = count;
        }

        // Update nav count
        const navCount = document.getElementById('numFavorites');
        if (navCount) {
            navCount.textContent = count;
            navCount.style.display = count > 0 ? '' : 'none';
        }

        // Update filter counts
        updateFilterCounts();

        // Show empty state if no favorites
        if (count === 0) {
            showEmptyState();
        }
    }

    /**
     * Update filter counts
     */
    function updateFilterCounts() {
        const allItems = document.querySelectorAll('.favorite-item');
        const instantItems = document.querySelectorAll('.favorite-item[data-type="instant"]');
        const orderItems = document.querySelectorAll('.favorite-item[data-type="order"]');

        elements.filterBtns.forEach(btn => {
            const filter = btn.dataset.filter;
            const countEl = btn.querySelector('.count');
            if (countEl) {
                if (filter === 'all') {
                    countEl.textContent = allItems.length;
                } else if (filter === 'instant') {
                    countEl.textContent = instantItems.length;
                } else if (filter === 'order') {
                    countEl.textContent = orderItems.length;
                }
            }
        });
    }

    /**
     * Filter favorites
     */
    function filterFavorites(filter) {
        state.currentFilter = filter;

        // Update active button
        elements.filterBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        // Filter items
        const items = document.querySelectorAll('.favorite-item');
        items.forEach(item => {
            const type = item.dataset.type;
            if (filter === 'all' || type === filter) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        if (!elements.list) return;

        // Hide filters
        const filtersEl = document.querySelector('.favorites-filters');
        if (filtersEl) {
            filtersEl.style.display = 'none';
        }

        elements.list.innerHTML = `
            <div class="favorites-empty">
                <div class="favorites-empty-icon">
                    <i class="fa-solid fa-heart-crack"></i>
                </div>
                <h3 class="favorites-empty-title">${TRANSLATIONS.noFavorites}</h3>
                <p class="favorites-empty-text">${TRANSLATIONS.noFavoritesText}</p>
                <a href="${window.BASE_URL}products" class="favorites-empty-btn">
                    <i class="fa-solid fa-shopping-bag"></i>
                    ${TRANSLATIONS.explore}
                </a>
            </div>
        `;
    }

    /**
     * Show toast
     */
    function showToast(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: type === 'error' ? 'error' : 'success',
                title: message
            });
        } else {
            alert(message);
        }
    }

    // Init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
