/**
 * Support Tickets JavaScript
 * Handle ticket interactions and AJAX table loading
 */

(function($) {
    'use strict';

    // ============================================
    // TICKET TABLE - AJAX LOADING
    // ============================================
    
    var TicketTable = {
        CONFIG: {
            perPage: 10,
            apiUrl: typeof TICKET_AJAX_URL !== 'undefined' ? TICKET_AJAX_URL : ''
        },

        state: {
            page: 1,
            status: '',
            subject: '',
            category: '',
            time: '',
            isLoading: false,
            hasMore: true,
            totalTickets: 0
        },

        elements: {},

        init: function() {
            var self = this;
            
            // Get DOM elements
            this.elements = {
                table: document.getElementById('ticketsTable'),
                tableBody: document.getElementById('ticketsTableBody'),
                loadingDesktop: document.getElementById('ticketsLoadingDesktop'),
                emptyState: document.getElementById('ticketsEmptyState'),
                loadMoreWrapper: document.getElementById('loadMoreWrapper'),
                btnLoadMore: document.getElementById('btnLoadMore'),
                userToken: document.getElementById('userToken'),
                csrfToken: document.getElementById('csrfToken'),
                filterForm: document.getElementById('ticketFilterForm'),
                filterStatus: document.getElementById('filterStatus'),
                filterSubject: document.getElementById('filterSubject'),
                filterCategory: document.getElementById('filterCategory'),
                filterTime: document.getElementById('flatpickr-range'),
                resetFilter: document.getElementById('resetFilter')
            };

            // Hide content tbody initially (skeleton will show)
            if (this.elements.tableBody) {
                this.elements.tableBody.style.display = 'none';
            }

            // Get initial state from URL
            var urlParams = new URLSearchParams(window.location.search);
            this.state.status = urlParams.get('status') || '';
            this.state.subject = urlParams.get('subject') || '';
            this.state.category = urlParams.get('category') || '';
            this.state.time = urlParams.get('time') || '';

            // Bind events
            this.bindEvents();

            // Load initial tickets
            this.loadTickets(true);
        },

        bindEvents: function() {
            var self = this;

            // Load more button
            if (this.elements.btnLoadMore) {
                this.elements.btnLoadMore.addEventListener('click', function() {
                    if (!self.state.isLoading && self.state.hasMore) {
                        self.state.page++;
                        self.loadTickets(false);
                    }
                });
            }

            // Filter form submit
            if (this.elements.filterForm) {
                this.elements.filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    self.applyFilters();
                });
            }

            // Reset filter button
            if (this.elements.resetFilter) {
                this.elements.resetFilter.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.resetFilters();
                });
            }
        },

        applyFilters: function() {
            // Get filter values
            this.state.status = this.elements.filterStatus ? this.elements.filterStatus.value : '';
            this.state.subject = this.elements.filterSubject ? this.elements.filterSubject.value.trim() : '';
            this.state.category = this.elements.filterCategory ? this.elements.filterCategory.value : '';
            this.state.time = this.elements.filterTime ? this.elements.filterTime.value : '';
            this.state.page = 1;
            this.state.hasMore = true;

            // Update URL without reload
            this.updateURL();

            // Load tickets
            this.loadTickets(true);
        },

        resetFilters: function() {
            // Reset form fields
            if (this.elements.filterStatus) this.elements.filterStatus.value = '';
            if (this.elements.filterSubject) this.elements.filterSubject.value = '';
            if (this.elements.filterCategory) this.elements.filterCategory.value = '';
            if (this.elements.filterTime) {
                this.elements.filterTime.value = '';
                // Clear flatpickr if exists
                if (this.elements.filterTime._flatpickr) {
                    this.elements.filterTime._flatpickr.clear();
                }
            }

            // Reset state
            this.state.status = '';
            this.state.subject = '';
            this.state.category = '';
            this.state.time = '';
            this.state.page = 1;
            this.state.hasMore = true;

            // Update URL
            this.updateURL();

            // Load tickets
            this.loadTickets(true);
        },

        updateURL: function() {
            var url = new URL(window.location.href);
            
            // Remove all filter params first
            url.searchParams.delete('status');
            url.searchParams.delete('subject');
            url.searchParams.delete('category');
            url.searchParams.delete('time');
            
            if (this.state.status) {
                url.searchParams.set('status', this.state.status);
            }
            if (this.state.subject) {
                url.searchParams.set('subject', this.state.subject);
            }
            if (this.state.category) {
                url.searchParams.set('category', this.state.category);
            }
            if (this.state.time) {
                url.searchParams.set('time', this.state.time);
            }

            window.history.pushState({}, '', url.toString());
        },

        loadTickets: function(isInitial) {
            var self = this;
            if (this.state.isLoading) return;

            this.state.isLoading = true;

            // Show loading state
            if (isInitial) {
                this.showLoading();
            } else {
                this.updateLoadMoreButton(true);
            }

            var formData = new FormData();
            formData.append('action', 'loadTickets');
            formData.append('page', this.state.page);
            formData.append('status', this.state.status);
            formData.append('subject', this.state.subject);
            formData.append('category', this.state.category);
            formData.append('time', this.state.time);
            formData.append('token', this.elements.userToken ? this.elements.userToken.value : '');
            formData.append('csrf_token', this.elements.csrfToken ? this.elements.csrfToken.value : '');

            fetch(this.CONFIG.apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.status === 'success') {
                    // Update state
                    self.state.hasMore = data.has_more;
                    self.state.totalTickets = data.total;

                    // Render tickets
                    self.renderTickets(data.html, isInitial);

                    // Update UI
                    self.updateLoadMoreButton(false);

                    // Show empty state if no tickets
                    if (self.state.totalTickets === 0) {
                        self.showEmptyState();
                    }
                } else {
                    if (typeof showMessage === 'function') {
                        showMessage(data.msg || 'Có lỗi xảy ra', 'error');
                    }
                    self.hideLoading();
                }
            })
            .catch(function(error) {
                console.error('Error loading tickets:', error);
                if (typeof showMessage === 'function') {
                    showMessage('Có lỗi xảy ra khi tải tickets', 'error');
                }
                self.hideLoading();
            })
            .finally(function() {
                self.state.isLoading = false;
            });
        },

        renderTickets: function(html, isReplace) {
            this.hideLoading();

            if (isReplace) {
                if (this.elements.tableBody) {
                    this.elements.tableBody.innerHTML = html;
                }
            } else {
                if (this.elements.tableBody) {
                    this.elements.tableBody.insertAdjacentHTML('beforeend', html);
                }
            }

            // Show/hide empty state
            if (this.state.totalTickets === 0) {
                this.showEmptyState();
            } else {
                this.hideEmptyState();
            }
        },

        showLoading: function() {
            // Hide content tbody
            if (this.elements.tableBody) {
                this.elements.tableBody.innerHTML = '';
                this.elements.tableBody.style.display = 'none';
            }

            // Show table 
            if (this.elements.table) {
                this.elements.table.style.display = '';
            }

            // Show loading skeleton
            if (this.elements.loadingDesktop) {
                this.elements.loadingDesktop.classList.add('is-loading');
            }

            // Hide empty state
            this.hideEmptyState();

            // Hide load more
            if (this.elements.loadMoreWrapper) {
                this.elements.loadMoreWrapper.style.display = 'none';
            }
        },

        hideLoading: function() {
            // Hide skeleton
            if (this.elements.loadingDesktop) {
                this.elements.loadingDesktop.classList.remove('is-loading');
            }
            // Show content tbody
            if (this.elements.tableBody) {
                this.elements.tableBody.style.display = '';
            }
            // Show table
            if (this.elements.table && this.state.totalTickets > 0) {
                this.elements.table.style.display = '';
            }
        },

        showEmptyState: function() {
            if (this.elements.emptyState) {
                this.elements.emptyState.style.display = 'block';
            }
            if (this.elements.loadMoreWrapper) {
                this.elements.loadMoreWrapper.style.display = 'none';
            }
            // Hide table
            if (this.elements.table) {
                this.elements.table.style.display = 'none';
            }
        },

        hideEmptyState: function() {
            if (this.elements.emptyState) {
                this.elements.emptyState.style.display = 'none';
            }
        },

        updateLoadMoreButton: function(isLoading) {
            if (!this.elements.loadMoreWrapper || !this.elements.btnLoadMore) return;

            var lang = typeof TICKET_LANG !== 'undefined' ? TICKET_LANG : {};

            if (isLoading) {
                this.elements.btnLoadMore.disabled = true;
                this.elements.btnLoadMore.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (lang.loading || 'Đang tải...');
            } else {
                if (this.state.hasMore && this.state.totalTickets > 0) {
                    var remaining = this.state.totalTickets - (this.state.page * this.CONFIG.perPage);
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

    // ============================================
    // TICKET LIST PAGE
    // ============================================
    
    var TicketList = {
        init: function() {
            this.bindEvents();
            this.initFlatpickr();
        },

        bindEvents: function() {
            var self = this;
            
            // Submit ticket form
            $(document).on('click', '#submitTicket', this.handleCreateTicket);
            
            // Show/hide order field based on category
            $('#category').on('change', this.toggleOrderField);
            
            // Open modal - main button
            $(document).on('click', '#openTicketModal, .btn-open-ticket-modal', function(e) {
                e.preventDefault();
                self.openModal();
            });
            
            // Close modal - close button
            $(document).on('click', '#closeTicketModal, #cancelTicketModal', function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            // Close modal - click overlay
            $(document).on('click', '.ticket-modal-overlay', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Close modal - ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#addTicketModal').hasClass('show')) {
                    self.closeModal();
                }
            });
        },
        
        openModal: function() {
            var $modal = $('#addTicketModal');
            $modal.css('display', 'flex');
            // Force reflow
            $modal[0].offsetHeight;
            $modal.addClass('show');
            $('body').css('overflow', 'hidden');
            // Focus first input
            setTimeout(function() {
                $('#category').trigger('change');
                $('#subject').focus();
            }, 100);
        },
        
        closeModal: function() {
            var $modal = $('#addTicketModal');
            $modal.removeClass('show');
            setTimeout(function() {
                $modal.css('display', 'none');
                $('body').css('overflow', '');
            }, 200);
            this.resetForm();
        },

        initFlatpickr: function() {
            if ($('#flatpickr-range').length && typeof flatpickr !== 'undefined') {
                flatpickr('#flatpickr-range', {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    enableTime: false,
                    altInput: true,
                    altFormat: 'd/m/Y',
                    locale: {
                        firstDayOfWeek: 1,
                        rangeSeparator: ' to '
                    }
                });
            }
        },

        toggleOrderField: function() {
            var selectedCategory = $(this).val();
            if (selectedCategory === 'order') {
                $('#orderIdField').slideDown(200);
            } else {
                $('#orderIdField').slideUp(200);
                $('#orderID').val('');
            }
        },

        handleCreateTicket: function() {
            var form = document.getElementById('addTicketForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            var $btn = $(this);
            var subject = $('#subject').val().trim();
            var category = $('#category').val();
            var order_id = $('#orderID').val().trim();
            var content = $('#ticketContent').val().trim();

            // Validate
            if (!subject || subject.length < 5) {
                TicketList.showMessage(TICKET_LANG.subject_min_length || 'Tiêu đề phải có ít nhất 5 ký tự', 'error');
                return;
            }

            if (!content || content.length < 10) {
                TicketList.showMessage(TICKET_LANG.content_min_length || 'Nội dung phải có ít nhất 10 ký tự', 'error');
                return;
            }

            // Get captcha if available
            var captchaResponse = '';
            if (typeof getSafeCaptchaResponse === 'function') {
                captchaResponse = getSafeCaptchaResponse();
            }

            // Check captcha if required
            if (typeof TICKET_CAPTCHA_REQUIRED !== 'undefined' && TICKET_CAPTCHA_REQUIRED && !captchaResponse) {
                TicketList.showMessage(TICKET_LANG.captcha_required || 'Vui lòng xác nhận Captcha', 'error');
                return;
            }

            // Disable button
            $btn.find('.btn-text').addClass('d-none');
            $btn.find('.btn-spinner').removeClass('d-none');
            $btn.prop('disabled', true);

            $.ajax({
                url: TICKET_AJAX_URL,
                type: 'POST',
                data: {
                    action: 'createTicket',
                    token: TICKET_USER_TOKEN,
                    subject: subject,
                    category: category,
                    order_id: order_id,
                    content: content,
                    captcha_response: captchaResponse,
                    recaptcha: captchaResponse,
                    'cf-turnstile-response': captchaResponse
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: TICKET_LANG.success || 'Thành công!',
                            text: response.msg,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            TicketList.closeModal();
                            location.reload();
                        });
                    } else {
                        TicketList.showMessage(response.msg, 'error');
                    }
                },
                error: function() {
                    TicketList.showMessage(TICKET_LANG.server_error || 'Không thể kết nối đến server', 'error');
                },
                complete: function() {
                    $btn.find('.btn-spinner').addClass('d-none');
                    $btn.find('.btn-text').removeClass('d-none');
                    $btn.prop('disabled', false);
                }
            });
        },

        resetForm: function() {
            $('#addTicketForm')[0].reset();
            $('#orderIdField').hide();
            if (typeof resetCaptcha === 'function') {
                resetCaptcha();
            }
        },

        showMessage: function(msg, type) {
            if (typeof showMessage === 'function') {
                showMessage(msg, type);
            } else if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: type === 'error' ? 'error' : 'success',
                    title: type === 'error' ? 'Lỗi!' : 'Thành công!',
                    text: msg
                });
            } else {
                alert(msg);
            }
        }
    };

    // ============================================
    // TICKET DETAIL PAGE
    // ============================================
    
    var TicketDetail = {
        audioEnabled: false,
        isLoadingMessages: false,
        justSentMessage: false,
        pollingInterval: null,

        init: function() {
            this.bindEvents();
            this.scrollToBottom();
            this.enableAudioOnInteraction();
            this.startPolling();
            this.initAutoResize();
        },

        bindEvents: function() {
            var self = this;

            // Submit reply form
            $('#replyForm').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Keyboard shortcut Ctrl+Enter / Cmd+Enter
            $('#replyMessage').on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (!$('#btnSendMessage').prop('disabled')) {
                        self.sendMessage();
                    }
                }
            });
        },

        enableAudioOnInteraction: function() {
            var self = this;
            var enableAudio = function() {
                self.audioEnabled = true;
                document.removeEventListener('click', enableAudio);
                document.removeEventListener('keydown', enableAudio);
            };
            document.addEventListener('click', enableAudio, { once: true });
            document.addEventListener('keydown', enableAudio, { once: true });
        },

        playNotificationSound: function() {
            if (!this.audioEnabled) return;
            
            try {
                var sound = document.getElementById('notification-sound');
                if (sound) {
                    sound.currentTime = 0;
                    sound.volume = 0.5;
                    sound.play().catch(function(e) {
                        console.log('Cannot play sound:', e);
                    });
                }
            } catch (e) {
                console.log('Error playing sound:', e);
            }
        },

        scrollToBottom: function() {
            var chatContainer = $('#chatMessages');
            if (chatContainer.length) {
                setTimeout(function() {
                    chatContainer.scrollTop(chatContainer[0].scrollHeight);
                }, 100);
            }
        },

        initAutoResize: function() {
            $('#replyMessage').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        },

        getLastMessageId: function() {
            var lastId = 0;
            $('.chat-message[data-message-id]').each(function() {
                var msgId = $(this).data('message-id');
                if (msgId && typeof msgId === 'number' && msgId > lastId) {
                    lastId = msgId;
                }
            });
            return lastId;
        },

        startPolling: function() {
            var self = this;
            
            // Initial load after 3 seconds
            setTimeout(function() {
                self.loadNewMessages();
            }, 3000);

            // Polling every 5 seconds
            this.pollingInterval = setInterval(function() {
                self.loadNewMessages();
            }, 5000);
        },

        loadNewMessages: function() {
            var self = this;
            
            if (this.isLoadingMessages || this.justSentMessage) return;
            
            this.isLoadingMessages = true;
            var currentLastId = this.getLastMessageId();

            $.ajax({
                url: TICKET_AJAX_URL,
                type: 'POST',
                data: {
                    action: 'getNewMessages',
                    ticket_id: TICKET_ID,
                    last_message_id: currentLastId,
                    token: TICKET_USER_TOKEN
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.messages && response.messages.length > 0) {
                        var chatContainer = $('#chatMessages');
                        var hasNewAdminMessages = false;

                        $.each(response.messages, function(index, msg) {
                            // Only process admin messages
                            if (msg.sender_type !== 'admin') return;

                            // Check duplicate
                            if ($('.chat-message[data-message-id="' + msg.id + '"]').length > 0) return;

                            // Play notification sound
                            self.playNotificationSound();

                            // Create message HTML
                            var messageHtml = self.createAdminMessageHtml(msg);
                            
                            // Remove waiting state if exists
                            chatContainer.find('.chat-waiting-state').remove();
                            
                            chatContainer.append(messageHtml);
                            hasNewAdminMessages = true;
                        });

                        if (hasNewAdminMessages) {
                            setTimeout(function() {
                                self.scrollToBottom();
                            }, 200);
                        }
                    }
                },
                complete: function() {
                    self.isLoadingMessages = false;
                }
            });
        },

        createAdminMessageHtml: function(msg) {
            return '<div class="chat-message is-admin" data-message-id="' + msg.id + '">' +
                '<div class="chat-avatar-admin">' +
                    '<i class="fa-solid fa-headset"></i>' +
                '</div>' +
                '<div class="chat-bubble">' +
                    '<div class="chat-bubble-meta">' +
                        '<span class="chat-sender">' + (TICKET_LANG.admin_support || 'Admin Support') + '</span>' +
                        '<span class="chat-time">' + msg.formatted_time + '</span>' +
                    '</div>' +
                    '<div class="chat-bubble-content">' +
                        '<p>' + this.escapeHtml(msg.message).replace(/\n/g, '<br>') + '</p>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        createUserMessageHtml: function(message) {
            var currentTime = new Date();
            var timeString = currentTime.toLocaleTimeString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit'
            }) + ' ' + currentTime.toLocaleDateString('vi-VN');
            
            var uniqueId = 'user-' + Date.now();

            return '<div class="chat-message is-user" data-message-id="' + uniqueId + '">' +
                '<div class="chat-avatar">' +
                    '<img src="' + TICKET_USER_AVATAR + '" alt="' + TICKET_USERNAME + '">' +
                '</div>' +
                '<div class="chat-bubble">' +
                    '<div class="chat-bubble-meta">' +
                        '<span class="chat-sender">' + TICKET_USERNAME + '</span>' +
                        '<span class="chat-time">' + timeString + '</span>' +
                    '</div>' +
                    '<div class="chat-bubble-content">' +
                        '<p>' + this.escapeHtml(message).replace(/\n/g, '<br>') + '</p>' +
                    '</div>' +
                '</div>' +
            '</div>';
        },

        sendMessage: function() {
            var self = this;
            var message = $('#replyMessage').val().trim();
            var $btn = $('#btnSendMessage');

            if (!message) {
                if (typeof showMessage === 'function') {
                    showMessage(TICKET_LANG.message_required || 'Vui lòng nhập tin nhắn', 'error');
                }
                return;
            }

            // Set flag to prevent auto-load
            this.justSentMessage = true;
            setTimeout(function() {
                self.justSentMessage = false;
            }, 10000);

            // Disable button
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

            $.ajax({
                url: TICKET_AJAX_URL,
                type: 'POST',
                data: {
                    action: 'replyTicket',
                    token: TICKET_USER_TOKEN,
                    ticket_id: TICKET_ID,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        var chatContainer = $('#chatMessages');
                        
                        // Remove waiting state
                        chatContainer.find('.chat-waiting-state').remove();
                        
                        // Add new message
                        chatContainer.append(self.createUserMessageHtml(message));
                        
                        // Clear input
                        $('#replyMessage').val('').css('height', 'auto');
                        
                        // Scroll to bottom
                        setTimeout(function() {
                            self.scrollToBottom();
                        }, 100);
                    } else {
                        if (typeof showMessage === 'function') {
                            showMessage(response.msg, 'error');
                        }
                    }
                },
                error: function() {
                    if (typeof showMessage === 'function') {
                        showMessage(TICKET_LANG.server_error || 'Không thể kết nối đến server', 'error');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i>');
                    setTimeout(function() {
                        self.justSentMessage = false;
                    }, 3000);
                }
            });
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ============================================
    // INITIALIZATION
    // ============================================
    
    $(document).ready(function() {
        // Initialize based on page
        if ($('.support-tickets-page').length) {
            TicketList.init();
            TicketTable.init();
        }
        
        if ($('.ticket-detail-page').length) {
            TicketDetail.init();
        }
    });

    // Expose to global for external use
    window.TicketList = TicketList;
    window.TicketTable = TicketTable;
    window.TicketDetail = TicketDetail;

})(jQuery);

