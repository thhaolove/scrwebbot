/**
 * Product Order Detail - JavaScript
 */

// Copy single item
function copySingleItem(btn) {
    const text = btn.dataset.content;
    
    if(navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            showCopySingleSuccess(btn);
        }).catch(function() {
            fallbackCopySingle(text, btn);
        });
    } else {
        fallbackCopySingle(text, btn);
    }
}

// Fallback copy for browsers that don't support clipboard API
function fallbackCopy(text, btn, errorMsg) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-999999px';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showCopySuccess(btn);
    } catch(err) {
        if (typeof showMessage === 'function') {
            showMessage(errorMsg || 'Không thể sao chép', 'error');
        }
    }
    
    document.body.removeChild(textarea);
}

function fallbackCopySingle(text, btn, errorMsg) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.left = '-999999px';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showCopySingleSuccess(btn);
    } catch(err) {
        if (typeof showMessage === 'function') {
            showMessage(errorMsg || 'Không thể sao chép', 'error');
        }
    }
    
    document.body.removeChild(textarea);
}

// Show copy success with configurable text
function showCopySuccess(btn, successText) {
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + (successText || 'Đã sao chép');
    btn.classList.add('copied');
    
    setTimeout(function() {
        btn.innerHTML = originalHTML;
        btn.classList.remove('copied');
    }, 2000);
}

function showCopySingleSuccess(btn) {
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
    btn.classList.add('copied');
    
    setTimeout(function() {
        btn.innerHTML = originalHTML;
        btn.classList.remove('copied');
    }, 1500);
}

// Lấy data từ element cha .stock-copy-all
function getParentData(btn) {
    const parent = btn.closest('.stock-copy-all');
    return {
        content: parent.dataset.content,
        json: parent.dataset.json,
        filename: parent.dataset.filename
    };
}

function copyAllContent(btn) {
    const data = getParentData(btn);
    
    if(navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(data.content).then(function() {
            showCopySuccess(btn);
        }).catch(function() {
            fallbackCopy(data.content, btn);
        });
    } else {
        fallbackCopy(data.content, btn);
    }
}

function exportAllToTxt(btn) {
    const data = getParentData(btn);
    const filename = data.filename + '.txt';
    
    const blob = new Blob([data.content], { type: 'text/plain;charset=utf-8' });
    downloadBlob(blob, filename);
    showExportSuccess(btn);
}

function exportAllToCsv(btn, headerLabel) {
    const data = getParentData(btn);
    const lines = JSON.parse(data.json);
    const filename = data.filename + '.csv';
    
    // Tạo CSV với header và escape đúng cách
    let csvContent = '\ufeff'; // BOM cho UTF-8
    csvContent += '"STT","' + (headerLabel || 'Nội dung') + '"\n';
    
    lines.forEach(function(line, index) {
        // Escape dấu " trong CSV bằng cách thay " thành ""
        const escapedLine = line.replace(/"/g, '""');
        csvContent += '"' + (index + 1) + '","' + escapedLine + '"\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8' });
    downloadBlob(blob, filename);
    showExportSuccess(btn);
}

function downloadBlob(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

function showExportSuccess(btn, successText) {
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + (successText || 'Đã xuất');
    btn.classList.add('exported');
    
    setTimeout(function() {
        btn.innerHTML = originalHTML;
        btn.classList.remove('exported');
    }, 2000);
}

/**
 * Initialize reorder functionality
 * @param {Object} config - Configuration object
 * @param {Object} config.reorderData - Data for reorder (product_id, plan_id, etc.)
 * @param {Object} config.messages - Localized messages
 * @param {string} config.cartUrl - URL to cart page
 */
function initReorderFeature(config) {
    const btnReorder = document.getElementById('btnReorder');
    if (!btnReorder || !config.reorderData) return;
    
    btnReorder.addEventListener('click', function() {
        addToCartReorder(config.reorderData, config.messages, config.cartUrl);
    });
}

function getCart() {
    try {
        const cart = localStorage.getItem('shopkey_cart');
        return cart ? JSON.parse(cart) : [];
    } catch (e) {
        return [];
    }
}

function saveCart(cart) {
    localStorage.setItem('shopkey_cart', JSON.stringify(cart));
    window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { cart: cart } }));
}

function addToCartReorder(item, messages, cartUrl) {
    const cart = getCart();
    messages = messages || {};
    
    // Check if same product + plan + fields already exists
    const existingIndex = cart.findIndex(cartItem => {
        if (cartItem.product_id !== item.product_id || cartItem.plan_id !== item.plan_id) {
            return false;
        }
        const existingFields = JSON.stringify(cartItem.fields || {});
        const newFields = JSON.stringify(item.fields || {});
        return existingFields === newFields;
    });
    
    if (existingIndex >= 0) {
        // Update quantity
        cart[existingIndex].quantity += item.quantity;
        if (cart[existingIndex].quantity > 100) {
            cart[existingIndex].quantity = 100;
        }
    } else {
        // Add new item
        cart.push({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            product_name: item.product_name,
            plan_name: item.plan_name,
            product_image: item.product_image,
            product_slug: item.product_slug,
            final_price: item.final_price,
            fields: item.fields,
            added_at: new Date().toISOString()
        });
    }
    
    saveCart(cart);
    
    // Show success message
    if (typeof showMessage === 'function') {
        showMessage(messages.addedToCart || 'Đã thêm vào giỏ hàng!', 'success');
    }
    
    // Ask user if they want to go to cart
    setTimeout(function() {
        if (confirm(messages.goToCart || 'Đã thêm sản phẩm vào giỏ hàng. Bạn có muốn đến giỏ hàng ngay?')) {
            window.location.href = cartUrl || '/cart';
        }
    }, 500);
}

// ============================================
// REPORT ERROR FUNCTIONALITY
// ============================================
var ReportError = (function() {
    var currentAccount = '';
    var currentIndex = 0;
    var config = {};

    function init(cfg) {
        config = cfg || {};
        bindEvents();
    }

    function bindEvents() {
        var closeBtn = document.getElementById('closeReportErrorModal');
        var cancelBtn = document.getElementById('cancelReportErrorModal');
        var submitBtn = document.getElementById('submitReportError');
        var modal = document.getElementById('reportErrorModal');

        if (closeBtn) closeBtn.addEventListener('click', close);
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        if (submitBtn) submitBtn.addEventListener('click', submit);

        // Click overlay to close
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) close();
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('show')) {
                close();
            }
        });
    }

    function open(btn) {
        currentAccount = btn.getAttribute('data-content');
        currentIndex = btn.getAttribute('data-index');

        var accountContent = document.getElementById('errorAccountContent');
        var description = document.getElementById('errorDescription');
        var modal = document.getElementById('reportErrorModal');

        if (accountContent) accountContent.textContent = currentAccount;
        if (description) description.value = '';

        if (modal) {
            modal.style.display = 'flex';
            modal.offsetHeight; // Force reflow
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Focus textarea
        setTimeout(function() {
            if (description) description.focus();
        }, 100);
    }

    function close() {
        var modal = document.getElementById('reportErrorModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(function() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 200);
        }
    }

    function submit() {
        var description = document.getElementById('errorDescription');
        var descValue = description ? description.value.trim() : '';

        if (!descValue || descValue.length < 10) {
            showAlert(config.lang?.error || 'Lỗi', config.lang?.minLength || 'Vui lòng mô tả chi tiết lỗi (ít nhất 10 ký tự)', 'error');
            return;
        }

        var btn = document.getElementById('submitReportError');
        var btnText = btn ? btn.querySelector('.btn-text') : null;
        var btnSpinner = btn ? btn.querySelector('.btn-spinner') : null;

        // Disable button
        if (btn) btn.disabled = true;
        if (btnText) btnText.classList.add('d-none');
        if (btnSpinner) btnSpinner.classList.remove('d-none');

        // Build ticket content
        var ticketSubject = (config.lang?.reportTitle || 'Báo lỗi đơn hàng') + ' #' + config.transId;
        var ticketContent = (config.lang?.headerReport || '** BÁO LỖI ĐƠN HÀNG **') + '\n\n';
        ticketContent += (config.lang?.orderId || 'Mã đơn hàng:') + ' ' + config.transId + '\n';
        ticketContent += (config.lang?.product || 'Sản phẩm:') + ' ' + config.productName + '\n';
        ticketContent += (config.lang?.plan || 'Gói:') + ' ' + config.planName + '\n\n';
        ticketContent += (config.lang?.accountError || '** TÀI KHOẢN LỖI (STT #') + currentIndex + ') **\n';
        ticketContent += currentAccount + '\n\n';
        ticketContent += (config.lang?.descriptionLabel || '** MÔ TẢ LỖI **') + '\n';
        ticketContent += descValue;

        // Create ticket via AJAX
        var formData = new FormData();
        formData.append('action', 'createTicket');
        formData.append('token', config.userToken);
        formData.append('csrf_token', config.csrfToken);
        formData.append('subject', ticketSubject);
        formData.append('category', 'order');
        formData.append('order_id', config.transId);
        formData.append('content', ticketContent);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success') {
                close();
                showAlert(config.lang?.success || 'Thành công!', config.lang?.successMsg || 'Đã gửi báo lỗi thành công. Chúng tôi sẽ kiểm tra và phản hồi sớm nhất.', 'success');
            } else {
                showAlert(config.lang?.error || 'Lỗi', data.msg || (config.lang?.genericError || 'Có lỗi xảy ra'), 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showAlert(config.lang?.error || 'Lỗi', config.lang?.serverError || 'Không thể kết nối đến server', 'error');
        })
        .finally(function() {
            if (btn) btn.disabled = false;
            if (btnText) btnText.classList.remove('d-none');
            if (btnSpinner) btnSpinner.classList.add('d-none');
        });
    }

    function showAlert(title, text, type) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: type, title: title, text: text, confirmButtonText: config.lang?.close || 'Đóng' });
        } else {
            alert(text);
        }
    }

    return {
        init: init,
        open: open,
        close: close
    };
})();

// Global function for onclick handler
function openReportErrorModal(btn) {
    ReportError.open(btn);
}
