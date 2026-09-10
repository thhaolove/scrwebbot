/**
 * Product Orders - AJAX Loading
 */
(function() {
    'use strict';

    // Config
    const CONFIG = {
        perPage: 10,
        apiUrl: window.BASE_URL + 'ajaxs/client/product-orders.php'
    };

    // State
    let state = {
        page: 1,
        status: '',
        search: '',
        isLoading: false,
        hasMore: true,
        totalOrders: 0
    };

    // DOM Elements
    let elements = {};

    /**
     * Initialize
     */
    function init() {
        // Get DOM elements
        elements = {
            table: document.querySelector('.orders-table'),
            tableBody: document.getElementById('ordersTableBody'),
            mobileList: document.getElementById('ordersMobileList'),
            loadMoreWrapper: document.getElementById('loadMoreWrapper'),
            btnLoadMore: document.getElementById('btnLoadMore'),
            userToken: document.getElementById('userToken'),
            csrfToken: document.getElementById('csrfToken'),
            loadingDesktop: document.getElementById('ordersLoadingDesktop'),
            loadingMobile: document.getElementById('ordersLoadingMobile'),
            emptyState: document.getElementById('ordersEmptyState'),
            filterTabs: document.querySelectorAll('.filter-tab'),
            searchForm: document.querySelector('.orders-search-form'),
            searchInput: document.querySelector('.orders-search-form input[name="q"]'),
            resultsInfo: document.getElementById('ordersResultsInfo')
        };

        // Hide content tbody initially (skeleton will show)
        if (elements.tableBody) {
            elements.tableBody.style.display = 'none';
        }

        // Get initial state from URL
        const urlParams = new URLSearchParams(window.location.search);
        state.status = urlParams.get('status') || '';
        state.search = urlParams.get('q') || '';

        // Bind events
        bindEvents();

        // Load initial orders
        loadOrders(true);
    }

    /**
     * Bind Events
     */
    function bindEvents() {
        // Load more button
        if (elements.btnLoadMore) {
            elements.btnLoadMore.addEventListener('click', function() {
                if (!state.isLoading && state.hasMore) {
                    state.page++;
                    loadOrders(false);
                }
            });
        }

        // Filter tabs - use AJAX instead of page reload
        elements.filterTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Support both <a href> and <button data-url>
                const urlString = this.dataset.url || this.href;
                const url = new URL(urlString);
                state.status = url.searchParams.get('status') || '';
                state.page = 1;
                state.hasMore = true;

                // Update active tab
                elements.filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Update URL without reload
                updateURL();

                // Load orders
                loadOrders(true);
            });
        });

        // Search form - use AJAX
        if (elements.searchForm) {
            elements.searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                state.search = elements.searchInput.value.trim();
                state.page = 1;
                state.hasMore = true;

                // Update URL without reload
                updateURL();

                // Load orders
                loadOrders(true);
            });
        }

        // Search clear button
        const searchClear = document.querySelector('.search-clear');
        if (searchClear) {
            searchClear.addEventListener('click', function(e) {
                e.preventDefault();
                
                state.search = '';
                elements.searchInput.value = '';
                state.page = 1;
                state.hasMore = true;

                // Update URL
                updateURL();

                // Hide clear button
                this.style.display = 'none';

                // Load orders
                loadOrders(true);
            });
        }

        // Show/hide clear button on input
        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', function() {
                const clearBtn = document.querySelector('.search-clear');
                if (clearBtn) {
                    clearBtn.style.display = this.value.length > 0 ? 'flex' : 'none';
                }
            });
        }
    }

    /**
     * Update URL without page reload
     */
    function updateURL() {
        const url = new URL(window.location.href);
        
        if (state.status) {
            url.searchParams.set('status', state.status);
        } else {
            url.searchParams.delete('status');
        }
        
        if (state.search) {
            url.searchParams.set('q', state.search);
        } else {
            url.searchParams.delete('q');
        }

        window.history.pushState({}, '', url.toString());
    }

    /**
     * Load Orders via AJAX
     */
    async function loadOrders(isInitial = false) {
        if (state.isLoading) return;

        state.isLoading = true;

        // Show loading state
        if (isInitial) {
            showLoading();
        } else {
            updateLoadMoreButton(true);
        }

        try {
            const formData = new FormData();
            formData.append('action', 'loadOrders');
            formData.append('page', state.page);
            formData.append('status', state.status);
            formData.append('q', state.search);
            formData.append('token', elements.userToken ? elements.userToken.value : '');
            formData.append('csrf_token', elements.csrfToken ? elements.csrfToken.value : '');

            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.status === 'success') {
                // Update state
                state.hasMore = data.has_more;
                state.totalOrders = data.total;

                // Render orders
                if (isInitial) {
                    renderOrders(data.html, data.mobile_html, true);
                } else {
                    renderOrders(data.html, data.mobile_html, false);
                }

                // Update UI
                updateLoadMoreButton(false);
                updateResultsInfo();

                // Show empty state if no orders
                if (state.totalOrders === 0) {
                    showEmptyState();
                }
            } else {
                if (typeof showMessage === 'function') {
                    showMessage(data.msg || 'Có lỗi xảy ra', 'error');
                }
                hideLoading();
            }
        } catch (error) {
            console.error('Error loading orders:', error);
            if (typeof showMessage === 'function') {
                showMessage('Có lỗi xảy ra khi tải đơn hàng', 'error');
            }
            hideLoading();
        }

        state.isLoading = false;
    }

    /**
     * Render Orders
     */
    function renderOrders(desktopHtml, mobileHtml, isReplace = false) {
        hideLoading();

        if (isReplace) {
            // Replace content
            if (elements.tableBody) {
                elements.tableBody.innerHTML = desktopHtml;
            }
            if (elements.mobileList) {
                elements.mobileList.innerHTML = mobileHtml;
            }
        } else {
            // Append content
            if (elements.tableBody) {
                elements.tableBody.insertAdjacentHTML('beforeend', desktopHtml);
            }
            if (elements.mobileList) {
                elements.mobileList.insertAdjacentHTML('beforeend', mobileHtml);
            }
        }

        // Show/hide empty state
        if (state.totalOrders === 0) {
            showEmptyState();
        } else {
            hideEmptyState();
        }
    }

    /**
     * Show Loading State
     */
    function showLoading() {
        // Hide content tbody
        if (elements.tableBody) {
            elements.tableBody.innerHTML = '';
            elements.tableBody.style.display = 'none';
        }
        if (elements.mobileList) {
            elements.mobileList.innerHTML = '';
        }

        // Reset table display - let CSS media query handle it
        // Don't force display: '' which would override media query 'display: none !important'
        if (elements.table) {
            elements.table.style.removeProperty('display');
        }

        // Show loading skeleton - add class to let CSS control visibility based on viewport
        if (elements.loadingDesktop) {
            elements.loadingDesktop.classList.add('is-loading');
        }
        if (elements.loadingMobile) {
            elements.loadingMobile.classList.add('is-loading');
        }

        // Hide empty state
        hideEmptyState();

        // Hide load more
        if (elements.loadMoreWrapper) {
            elements.loadMoreWrapper.style.display = 'none';
        }
    }

    /**
     * Hide Loading State
     */
    function hideLoading() {
        // Hide skeleton - remove loading class
        if (elements.loadingDesktop) {
            elements.loadingDesktop.classList.remove('is-loading');
        }
        if (elements.loadingMobile) {
            elements.loadingMobile.classList.remove('is-loading');
        }
        // Show content tbody
        if (elements.tableBody) {
            elements.tableBody.style.display = '';
        }
        // Show table
        if (elements.table && state.totalOrders > 0) {
            elements.table.style.display = '';
        }
    }

    /**
     * Show Empty State
     */
    function showEmptyState() {
        if (elements.emptyState) {
            elements.emptyState.style.display = 'block';
        }
        if (elements.loadMoreWrapper) {
            elements.loadMoreWrapper.style.display = 'none';
        }
        // Hide table
        if (elements.table) {
            elements.table.style.display = 'none';
        }
    }

    /**
     * Hide Empty State
     */
    function hideEmptyState() {
        if (elements.emptyState) {
            elements.emptyState.style.display = 'none';
        }
    }

    /**
     * Update Load More Button
     */
    function updateLoadMoreButton(isLoading) {
        if (!elements.loadMoreWrapper || !elements.btnLoadMore) return;

        if (isLoading) {
            elements.btnLoadMore.disabled = true;
            elements.btnLoadMore.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tải...';
        } else {
            if (state.hasMore) {
                const remaining = state.totalOrders - (state.page * CONFIG.perPage);
                if (remaining > 0) {
                    elements.loadMoreWrapper.style.display = 'block';
                    elements.btnLoadMore.disabled = false;
                    elements.btnLoadMore.innerHTML = '<i class="fa-solid fa-plus"></i> Xem thêm <span class="load-more-count">(' + remaining + ' còn lại)</span>';
                } else {
                    elements.loadMoreWrapper.style.display = 'none';
                }
            } else {
                elements.loadMoreWrapper.style.display = 'none';
            }
        }
    }

    /**
     * Update Results Info
     */
    function updateResultsInfo() {
        if (elements.resultsInfo) {
            elements.resultsInfo.textContent = state.totalOrders + ' đơn hàng';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

