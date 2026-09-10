/**
 * API Documentation Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all features
    initApiKeyToggle();
    initCopyFunctions();
    initCodeTabs();
    initSmoothScroll();
    initScrollSpy();
});

/**
 * API Key visibility toggle
 */
let apiKeyVisible = false;

function initApiKeyToggle() {
    const toggleBtn = document.querySelector('.api-key-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleApiKey);
    }
}

function toggleApiKey() {
    const display = document.getElementById('displayApiKey');
    const icon = document.getElementById('apiKeyEyeIcon');
    const fullKey = display ? display.getAttribute('data-full-key') : '';
    
    if (!display || !fullKey) return;
    
    if (apiKeyVisible) {
        display.textContent = fullKey.substring(0, 10) + '••••••••••••';
        icon.className = 'fa-solid fa-eye';
    } else {
        display.textContent = fullKey;
        icon.className = 'fa-solid fa-eye-slash';
    }
    apiKeyVisible = !apiKeyVisible;
}

/**
 * Copy functions
 */
function initCopyFunctions() {
    // Add event listeners for all copy buttons
    document.querySelectorAll('[data-copy-text]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            copyToClipboard(this.getAttribute('data-copy-text'));
        });
    });
}

function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess();
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showCopySuccess();
    } catch (err) {
        showCopyError();
    }
    
    document.body.removeChild(textarea);
}

function showCopySuccess() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Đã sao chép!',
            showConfirmButton: false,
            timer: 1000,
            toast: true,
            position: 'top-end'
        });
    } else if (typeof showMessage === 'function') {
        showMessage('Đã sao chép!', 'success');
    }
}

function showCopyError() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Không thể sao chép',
            showConfirmButton: false,
            timer: 1500,
            toast: true,
            position: 'top-end'
        });
    }
}

// Global copy functions
function copyText(text) {
    copyToClipboard(text);
}

function copyCode(elementId) {
    var element = document.getElementById(elementId);
    if (element) {
        copyToClipboard(element.textContent);
    }
}

/**
 * Code tabs functionality
 */
function initCodeTabs() {
    document.querySelectorAll('.api-code-tabs').forEach(function(tabContainer) {
        var tabs = tabContainer.querySelectorAll('.api-code-tab');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                switchCodeTab(this);
            });
        });
    });
}

function switchCodeTab(tabElement) {
    var targetId = tabElement.getAttribute('data-target');
    var tabContainer = tabElement.closest('.api-code-tabs');
    var codeContainer = tabContainer.nextElementSibling;
    
    // Find the parent code examples wrapper
    while (codeContainer && !codeContainer.classList.contains('api-code-examples')) {
        codeContainer = codeContainer.nextElementSibling;
    }
    
    if (!codeContainer) return;
    
    // Update tabs
    tabContainer.querySelectorAll('.api-code-tab').forEach(function(t) {
        t.classList.remove('active');
    });
    tabElement.classList.add('active');
    
    // Update code blocks
    codeContainer.querySelectorAll('.api-code-example').forEach(function(block) {
        block.style.display = 'none';
    });
    
    var targetBlock = codeContainer.querySelector('[data-code="' + targetId + '"]');
    if (targetBlock) {
        targetBlock.style.display = 'block';
    }
}

// Alternative tab switching for sections
function showCodeExample(lang, sectionId) {
    var section = sectionId ? document.getElementById(sectionId) : document;
    
    // Hide all code examples in this section
    section.querySelectorAll('.code-example').forEach(function(el) {
        el.style.display = 'none';
    });
    
    // Show selected
    var targetExample = section.querySelector('#code-' + lang) || 
                        section.querySelector('[data-code="' + lang + '"]');
    if (targetExample) {
        targetExample.style.display = 'block';
    }
    
    // Update tabs
    var tabs = section.querySelectorAll('.api-code-tab, .api-tab');
    tabs.forEach(function(tab) {
        tab.classList.remove('active');
        if (tab.getAttribute('data-target') === lang || 
            tab.getAttribute('onclick')?.includes(lang)) {
            tab.classList.add('active');
        }
    });
}

/**
 * Smooth scroll for sidebar links
 */
function initSmoothScroll() {
    document.querySelectorAll('.api-nav-link[href^="#"]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            var target = document.getElementById(targetId);
            
            if (target) {
                var headerOffset = 100;
                var elementPosition = target.getBoundingClientRect().top;
                var offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
                
                // Update active state
                document.querySelectorAll('.api-nav-link').forEach(function(l) {
                    l.classList.remove('active');
                });
                this.classList.add('active');
            }
        });
    });
}

/**
 * Scroll spy - highlight active section in sidebar
 */
function initScrollSpy() {
    var sections = document.querySelectorAll('.api-section-card[id]');
    if (sections.length === 0) return;
    
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var id = entry.target.getAttribute('id');
                updateActiveNavLink(id);
            }
        });
    }, {
        rootMargin: '-100px 0px -70% 0px'
    });
    
    sections.forEach(function(section) {
        observer.observe(section);
    });
}

function updateActiveNavLink(sectionId) {
    document.querySelectorAll('.api-nav-link').forEach(function(link) {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + sectionId) {
            link.classList.add('active');
        }
    });
}

/**
 * Endpoint code examples toggle
 */
function toggleEndpointCode(endpointId) {
    var codeSection = document.getElementById(endpointId + '-code');
    if (codeSection) {
        var isVisible = codeSection.style.display !== 'none';
        codeSection.style.display = isVisible ? 'none' : 'block';
    }
}

/**
 * Format JSON for display
 */
function formatJson(json) {
    if (typeof json === 'string') {
        try {
            json = JSON.parse(json);
        } catch (e) {
            return json;
        }
    }
    return JSON.stringify(json, null, 2);
}

/**
 * Syntax highlight JSON
 */
function highlightJson(jsonString) {
    return jsonString
        .replace(/"([^"]+)":/g, '<span class="json-key">"$1"</span>:')
        .replace(/: "([^"]*)"/g, ': <span class="json-string">"$1"</span>')
        .replace(/: (\d+)/g, ': <span class="json-number">$1</span>')
        .replace(/: (true|false)/g, ': <span class="json-boolean">$1</span>')
        .replace(/: (null)/g, ': <span class="json-null">$1</span>');
}

/* ============================================
   API KEYS MANAGEMENT FUNCTIONS
   ============================================ */

/**
 * Modal Management
 */
function openApiModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeApiModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.api-modal.show').forEach(function(modal) {
            modal.classList.remove('show');
        });
        document.body.style.overflow = '';
    }
});

/**
 * API Keys - Create Modal
 */
function openCreateApiKeyModal() {
    var nameInput = document.getElementById('create_key_name');
    var ipInput = document.getElementById('create_ip_whitelist');
    var resultDiv = document.getElementById('createApiKeyResult');
    var btn = document.getElementById('btnCreateApiKey');
    
    if (nameInput) nameInput.value = '';
    if (ipInput) ipInput.value = '';
    if (resultDiv) resultDiv.style.display = 'none';
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Tạo API Key';
    }
    
    openApiModal('createApiKeyModal');
}

function closeCreateApiKeyModal() {
    closeApiModal('createApiKeyModal');
}

/**
 * API Keys - Edit Modal
 */
function openEditApiKeyModal(keyId, keyName, ipWhitelist) {
    var idInput = document.getElementById('edit_key_id');
    var nameInput = document.getElementById('edit_key_name');
    var ipInput = document.getElementById('edit_ip_whitelist');
    
    if (idInput) idInput.value = keyId;
    if (nameInput) nameInput.value = keyName || '';
    if (ipInput) ipInput.value = ipWhitelist || '';
    
    openApiModal('editApiKeyModal');
}

function closeEditApiKeyModal() {
    closeApiModal('editApiKeyModal');
}

/**
 * API Keys - AJAX Functions
 */
function createApiKey(ajaxUrl, translations) {
    var btn = document.getElementById('btnCreateApiKey');
    var token = document.getElementById('api_token').value;
    var csrfToken = document.getElementById('api_csrf_token').value;
    var keyName = document.getElementById('create_key_name').value;
    var ipWhitelist = document.getElementById('create_ip_whitelist').value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + (translations.processing || 'Đang xử lý...');
    
    $.ajax({
        url: ajaxUrl,
        method: 'POST',
        dataType: 'JSON',
        data: {
            action: 'create_api_key',
            token: token,
            csrf_token: csrfToken,
            key_name: keyName,
            ip_whitelist: ipWhitelist
        },
        success: function(res) {
            if (res.status == 'success') {
                // Ẩn các form group
                var formGroups = document.querySelectorAll('#createApiKeyModal .api-form-group');
                formGroups.forEach(function(fg) {
                    fg.style.display = 'none';
                });
                
                // Thay đổi header modal
                var modalHeader = document.querySelector('#createApiKeyModal .api-modal-header h5');
                if (modalHeader) {
                    modalHeader.innerHTML = '<i class="fa-solid fa-check-circle" style="color: #10b981;"></i> ' + (translations.success || 'Thành công');
                }
                
                var resultDiv = document.getElementById('createApiKeyResult');
                resultDiv.innerHTML = 
                    '<div class="api-secret-box">' +
                        '<div class="api-secret-box-header">' +
                            '<i class="fa-solid fa-triangle-exclamation"></i>' +
                            '<h6>' + (translations.saveSecret || 'Lưu lại API Secret ngay!') + '</h6>' +
                        '</div>' +
                        '<p class="api-secret-warning"><i class="fa-solid fa-exclamation-circle"></i> ' + (translations.secretOnce || 'Secret Key này chỉ hiển thị một lần!') + '</p>' +
                        '<div class="api-secret-field">' +
                            '<div class="api-secret-field-label">API Key</div>' +
                            '<div class="api-secret-field-value">' + res.data.api_key + '</div>' +
                        '</div>' +
                        '<div class="api-secret-field">' +
                            '<div class="api-secret-field-label">API Secret</div>' +
                            '<div class="api-secret-field-value">' + res.data.api_secret + '</div>' +
                        '</div>' +
                        '<button class="api-secret-copy-btn" onclick="copyToClipboard(\'' + res.data.api_secret + '\')">' +
                            '<i class="fa-solid fa-copy"></i> ' + (translations.copySecret || 'Sao chép Secret') +
                        '</button>' +
                    '</div>';
                resultDiv.style.display = 'block';
                
                // Ẩn nút "Tạo API Key", chỉ để nút "Đóng"
                btn.style.display = 'none';
            } else {
                Swal.fire(translations.error || 'Lỗi', res.msg, 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plus"></i> ' + (translations.createBtn || 'Tạo API Key');
            }
        },
        error: function() {
            Swal.fire(translations.error || 'Lỗi', translations.requestError || 'Không thể xử lý yêu cầu', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-plus"></i> ' + (translations.createBtn || 'Tạo API Key');
        }
    });
}

function updateApiKey(ajaxUrl, translations) {
    var btn = document.getElementById('btnUpdateApiKey');
    var token = document.getElementById('api_token').value;
    var csrfToken = document.getElementById('api_csrf_token').value;
    var keyId = document.getElementById('edit_key_id').value;
    var keyName = document.getElementById('edit_key_name').value;
    var ipWhitelist = document.getElementById('edit_ip_whitelist').value;
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    
    $.ajax({
        url: ajaxUrl,
        method: 'POST',
        dataType: 'JSON',
        data: {
            action: 'update_api_key',
            token: token,
            csrf_token: csrfToken,
            key_id: keyId,
            key_name: keyName,
            ip_whitelist: ipWhitelist
        },
        success: function(res) {
            if (res.status == 'success') {
                Swal.fire(translations.success || 'Thành công', res.msg, 'success').then(function() {
                    location.reload();
                });
            } else {
                Swal.fire(translations.error || 'Lỗi', res.msg, 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-save"></i> ' + (translations.save || 'Lưu thay đổi');
        },
        error: function() {
            Swal.fire(translations.error || 'Lỗi', translations.requestError || 'Không thể xử lý yêu cầu', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-save"></i> ' + (translations.save || 'Lưu thay đổi');
        }
    });
}

function toggleApiKey(id, ajaxUrl, translations) {
    Swal.fire({
        title: translations.confirm || 'Xác nhận',
        text: translations.toggleConfirm || 'Bạn có chắc chắn muốn thay đổi trạng thái API Key này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: translations.confirmBtn || 'Xác nhận',
        cancelButtonText: translations.cancel || 'Hủy'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'toggle_api_key',
                    token: document.getElementById('api_token').value,
                    csrf_token: document.getElementById('api_csrf_token').value,
                    key_id: id
                },
                success: function(res) {
                    if (res.status == 'success') {
                        Swal.fire(translations.success || 'Thành công', res.msg, 'success').then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire(translations.error || 'Lỗi', res.msg, 'error');
                    }
                }
            });
        }
    });
}

function regenerateApiSecret(id, ajaxUrl, translations) {
    Swal.fire({
        title: translations.warning || 'Cảnh báo',
        html: (translations.regenerateConfirm || 'Bạn có chắc chắn muốn tạo lại API Secret?') + '<br><br><strong class="text-danger">' + (translations.oldSecretInvalid || 'Secret cũ sẽ không còn hoạt động!') + '</strong>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: translations.regenerateBtn || 'Tạo lại',
        cancelButtonText: translations.cancel || 'Hủy'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'regenerate_secret',
                    token: document.getElementById('api_token').value,
                    csrf_token: document.getElementById('api_csrf_token').value,
                    key_id: id
                },
                success: function(res) {
                    if (res.status == 'success') {
                        Swal.fire({
                            title: translations.newSecret || 'API Secret mới',
                            html: '<div class="text-start"><p class="text-danger"><strong>' + (translations.saveNow || 'Lưu lại ngay! Secret chỉ hiển thị một lần.') + '</strong></p><div class="bg-light p-3 rounded" style="word-break:break-all;font-family:monospace;">' + res.new_secret + '</div></div>',
                            icon: 'success',
                            confirmButtonText: translations.saved || 'Đã lưu'
                        });
                    } else {
                        Swal.fire(translations.error || 'Lỗi', res.msg, 'error');
                    }
                }
            });
        }
    });
}

function deleteApiKey(id, ajaxUrl, translations) {
    Swal.fire({
        title: translations.deleteConfirmTitle || 'Xác nhận xóa',
        text: translations.deleteConfirm || 'Bạn có chắc chắn muốn xóa API Key này? Hành động này không thể hoàn tác.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: translations.deleteBtn || 'Xóa',
        cancelButtonText: translations.cancel || 'Hủy'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'delete_api_key',
                    token: document.getElementById('api_token').value,
                    csrf_token: document.getElementById('api_csrf_token').value,
                    key_id: id
                },
                success: function(res) {
                    if (res.status == 'success') {
                        Swal.fire(translations.success || 'Thành công', res.msg, 'success').then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire(translations.error || 'Lỗi', res.msg, 'error');
                    }
                }
            });
        }
    });
}

