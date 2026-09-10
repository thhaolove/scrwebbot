/**
 * Transactions Table AJAX Module
 */
(function() {
    'use strict';

    var TransactionsTable = {
        CONFIG: { perPage: 10 },
        state: {
            page: 1,
            isLoading: false,
            hasMore: true,
            totalItems: 0,
            content: '',
            shortByDate: '',
            time: '',
            type: ''
        },
        elements: {},

        init: function() {
            var self = this;
            
            this.elements = {
                tableBody: document.getElementById('transactionsTableBody'),
                loadingSkeleton: document.getElementById('transactionsLoading'),
                emptyState: document.getElementById('transactionsEmpty'),
                loadMoreWrapper: document.getElementById('loadMoreWrapper'),
                btnLoadMore: document.getElementById('btnLoadMore'),
                filterForm: document.getElementById('transactionsFilterForm'),
                filterContent: document.getElementById('filterContent'),
                filterShortByDate: document.getElementById('filterShortByDate'),
                filterTime: document.getElementById('flatpickr-range'),
                filterType: document.getElementById('filterType'),
                resetFilter: document.getElementById('resetFilter'),
                userToken: document.getElementById('userToken'),
                csrfToken: document.getElementById('csrfToken')
            };

            if (this.elements.tableBody) {
                this.elements.tableBody.style.display = 'none';
            }

            // Get initial filter values from form
            if (this.elements.filterContent) this.state.content = this.elements.filterContent.value || '';
            if (this.elements.filterShortByDate) this.state.shortByDate = this.elements.filterShortByDate.value || '';
            if (this.elements.filterTime) this.state.time = this.elements.filterTime.value || '';
            if (this.elements.filterType) this.state.type = this.elements.filterType.value || '';

            this.bindEvents();
            this.loadItems(true);
        },

        bindEvents: function() {
            var self = this;

            if (this.elements.btnLoadMore) {
                this.elements.btnLoadMore.addEventListener('click', function() {
                    if (!self.state.isLoading && self.state.hasMore) {
                        self.state.page++;
                        self.loadItems(false);
                    }
                });
            }

            if (this.elements.filterForm) {
                this.elements.filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    self.state.content = self.elements.filterContent ? self.elements.filterContent.value : '';
                    self.state.shortByDate = self.elements.filterShortByDate ? self.elements.filterShortByDate.value : '';
                    self.state.time = self.elements.filterTime ? self.elements.filterTime.value : '';
                    self.state.type = self.elements.filterType ? self.elements.filterType.value : '';
                    self.state.page = 1;
                    self.state.hasMore = true;
                    self.loadItems(true);
                });
            }

            if (this.elements.resetFilter) {
                this.elements.resetFilter.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (self.elements.filterContent) self.elements.filterContent.value = '';
                    if (self.elements.filterShortByDate) self.elements.filterShortByDate.value = '';
                    if (self.elements.filterTime) self.elements.filterTime.value = '';
                    if (self.elements.filterType) self.elements.filterType.value = '';
                    self.state.content = '';
                    self.state.shortByDate = '';
                    self.state.time = '';
                    self.state.type = '';
                    self.state.page = 1;
                    self.state.hasMore = true;
                    self.loadItems(true);
                });
            }
        },

        loadItems: function(isInitial) {
            var self = this;
            if (this.state.isLoading) return;

            this.state.isLoading = true;
            
            if (isInitial) {
                this.showLoading();
            } else {
                this.updateLoadMoreButton(true);
            }

            var formData = new FormData();
            formData.append('action', 'loadTransactions');
            formData.append('page', this.state.page);
            formData.append('content', this.state.content);
            formData.append('shortByDate', this.state.shortByDate);
            formData.append('time', this.state.time);
            formData.append('type', this.state.type);
            formData.append('token', typeof TRANSACTIONS_USER_TOKEN !== 'undefined' ? TRANSACTIONS_USER_TOKEN : '');
            formData.append('csrf_token', this.elements.csrfToken ? this.elements.csrfToken.value : '');

            fetch(TRANSACTIONS_AJAX_URL, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                self.state.isLoading = false;
                self.hideLoading();

                if (data.status === 'success') {
                    self.state.hasMore = data.has_more;
                    self.state.totalItems = data.total;
                    self.renderItems(data.html, isInitial);
                    self.updateLoadMoreButton(false);

                    if (self.state.totalItems === 0) {
                        self.showEmptyState();
                    }
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(data.message || 'Error loading data', 'error');
                    }
                }
            })
            .catch(function(error) {
                self.state.isLoading = false;
                self.hideLoading();
                console.error('Error:', error);
            });
        },

        renderItems: function(html, isInitial) {
            if (!this.elements.tableBody) return;

            if (isInitial) {
                this.elements.tableBody.innerHTML = html;
            } else {
                this.elements.tableBody.insertAdjacentHTML('beforeend', html);
            }
        },

        showLoading: function() {
            this.hideEmptyState();
            if (this.elements.loadingSkeleton) {
                this.elements.loadingSkeleton.classList.add('is-loading');
            }
            if (this.elements.loadMoreWrapper) {
                this.elements.loadMoreWrapper.style.display = 'none';
            }
        },

        hideLoading: function() {
            if (this.elements.loadingSkeleton) {
                this.elements.loadingSkeleton.classList.remove('is-loading');
            }
            if (this.elements.tableBody) {
                this.elements.tableBody.style.display = '';
            }
        },

        showEmptyState: function() {
            if (this.elements.emptyState) {
                this.elements.emptyState.style.display = 'block';
            }
            if (this.elements.loadMoreWrapper) {
                this.elements.loadMoreWrapper.style.display = 'none';
            }
        },

        hideEmptyState: function() {
            if (this.elements.emptyState) {
                this.elements.emptyState.style.display = 'none';
            }
        },

        updateLoadMoreButton: function(isLoading) {
            if (!this.elements.loadMoreWrapper || !this.elements.btnLoadMore) return;

            if (isLoading) {
                this.elements.btnLoadMore.disabled = true;
                this.elements.btnLoadMore.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang tải...';
            } else {
                if (this.state.hasMore && this.state.totalItems > 0) {
                    var remaining = this.state.totalItems - (this.state.page * this.CONFIG.perPage);
                    if (remaining > 0) {
                        this.elements.loadMoreWrapper.style.display = 'block';
                        this.elements.btnLoadMore.disabled = false;
                        this.elements.btnLoadMore.innerHTML = '<i class="fa-solid fa-arrow-down"></i> Tải thêm';
                    } else {
                        this.elements.loadMoreWrapper.style.display = 'none';
                    }
                } else {
                    this.elements.loadMoreWrapper.style.display = 'none';
                }
            }
        }
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('transactionsTableBody')) {
            TransactionsTable.init();
        }
    });

    window.TransactionsTable = TransactionsTable;
})();
