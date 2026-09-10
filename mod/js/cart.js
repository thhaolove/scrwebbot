/**
 * Cart Page Module
 * Quản lý giỏ hàng và hiển thị trên trang cart
 */
(function() {
    "use strict";

    // Get config from global variables (set by PHP)
    const BASE_URL = window.CART_CONFIG?.BASE_URL || "";
    const TRANS = window.CART_CONFIG?.TRANS || {};
    const ORDERS_URL = window.CART_CONFIG?.ORDERS_URL || "";
    const TOPUP_URL = window.CART_CONFIG?.TOPUP_URL || "";
    const LOGIN_URL = window.CART_CONFIG?.LOGIN_URL || "";
    const IS_LOGGED_IN = window.CART_CONFIG?.IS_LOGGED_IN || false;
    const USER_TOKEN = window.CART_CONFIG?.USER_TOKEN || "";
    const CURRENCY = window.CART_CONFIG?.CURRENCY || {
        symbol_left: "",
        symbol_right: "",
        rate: 1,
        decimal: 0,
        seperator: "dot"
    };
    
    let userBalance = window.CART_CONFIG?.USER_BALANCE || 0;
    let isProcessing = false;
    let isFetchingPrices = false;
    
    // Coupon state
    let appliedCoupon = null;
    let couponDiscount = 0;
    let itemDiscounts = []; // Discount cho từng item đủ điều kiện
    
    // Real-time prices cache (session only)
    let realtimePrices = {};
    
    // Checkout step state (1 = cart, 2 = confirm, 3 = complete)
    let currentStep = 1;

    function init() {
        // Fetch real-time prices first, then render
        fetchRealtimePrices().then(() => {
            loadSavedCoupon();
            renderCart();
            bindEvents();
            
            // Thêm class loaded để hiển thị cart với animation mượt mà
            const container = document.getElementById("cartContainer");
            if (container) {
                container.classList.add("loaded");
            }
        });
    }
    
    /**
     * Fetch real-time prices for all cart items
     */
    async function fetchRealtimePrices() {
        const cart = getCart();
        if (cart.length === 0) return;
        
        // Show loading state
        const container = document.getElementById("cartContainer");
        if (container) {
            // Hiển thị loading ngay lập tức
            container.classList.add("loaded");
            container.innerHTML = `
                <div class="cart-loading">
                    <div class="cart-loading-spinner">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </div>
                    <p>${TRANS.loadingPrices || "Đang cập nhật giá..."}</p>
                </div>
            `;
        }
        
        // Prepare items for API
        const items = cart.map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id
        }));
        
        try {
            const formData = new FormData();
            formData.append("action", "getCartPrices");
            formData.append("items", JSON.stringify(items));
            // Gửi token để backend áp dụng user discount
            if (USER_TOKEN) {
                formData.append("token", USER_TOKEN);
            }
            
            const response = await fetch(BASE_URL + "ajaxs/client/cart.php", {
                method: "POST",
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === "success" && data.items) {
                // Update cart with real-time prices
                let cartUpdated = false;
                const updatedCart = [];
                
                for (const cartItem of cart) {
                    const priceInfo = data.items.find(p => 
                        p.product_id === cartItem.product_id && p.plan_id === cartItem.plan_id
                    );
                    
                    if (priceInfo) {
                        if (!priceInfo.available) {
                            // Item no longer available - mark for removal or notify
                            cartUpdated = true;
                            // Keep item but mark as unavailable
                            updatedCart.push({
                                ...cartItem,
                                unavailable: true,
                                unavailable_reason: priceInfo.reason
                            });
                        } else {
                            // Update prices
                            const newItem = {
                                ...cartItem,
                                product_name: priceInfo.product_name,
                                plan_name: priceInfo.plan_name,
                                product_image: priceInfo.product_image,
                                product_slug: priceInfo.product_slug,
                                original_price: priceInfo.original_price, // Giá gốc
                                price: priceInfo.price,
                                sale_price: priceInfo.sale_price,
                                flash_price: priceInfo.flash_price || 0,
                                has_flash_sale: priceInfo.has_flash_sale || false,
                                flash_sale: priceInfo.flash_sale || null,
                                final_price: priceInfo.final_price,
                                user_discount_percent: priceInfo.user_discount_percent || 0, // % discount user
                                user_discount_amount: priceInfo.user_discount_amount || 0, // Số tiền giảm
                                is_instant: priceInfo.is_instant,
                                stock_count: priceInfo.stock_count,
                                unavailable: false
                            };
                            
                            // Check if price changed
                            if (cartItem.final_price !== priceInfo.final_price) {
                                cartUpdated = true;
                                newItem.price_changed = true;
                                newItem.old_price = cartItem.final_price;
                            }
                            
                            // Check stock for instant delivery
                            if (priceInfo.is_instant && cartItem.quantity > priceInfo.stock_count) {
                                newItem.quantity = Math.max(1, priceInfo.stock_count);
                                cartUpdated = true;
                            }
                            
                            updatedCart.push(newItem);
                            
                            // Luôn đánh dấu cần update để lưu đầy đủ dữ liệu từ API
                            // (bao gồm has_flash_sale, user_discount, original_price, v.v.)
                            cartUpdated = true;
                        }
                    } else {
                        // Item not found in response - keep as is
                        updatedCart.push(cartItem);
                    }
                }
                
                // Save updated cart
                if (cartUpdated) {
                    saveCart(updatedCart);
                }
                
                // Store in session cache
                realtimePrices = {};
                data.items.forEach(item => {
                    const key = `${item.product_id}_${item.plan_id}`;
                    realtimePrices[key] = item;
                });
            }
        } catch (error) {
            console.error("Error fetching prices:", error);
            // Continue with cached prices if fetch fails
        }
    }

    /**
     * Load saved coupon from product page
     */
    function loadSavedCoupon() {
        try {
            const savedCoupon = localStorage.getItem("shopkey_cart_coupon");
            if (savedCoupon) {
                const couponData = JSON.parse(savedCoupon);
                
                // Check if coupon is not too old (max 24 hours)
                const savedAt = new Date(couponData.saved_at);
                const now = new Date();
                const hoursDiff = (now - savedAt) / (1000 * 60 * 60);
                
                if (hoursDiff <= 24 && couponData.code) {
                    // Auto-apply the coupon
                    autoApplySavedCoupon(couponData.code);
                } else {
                    // Remove expired coupon
                    localStorage.removeItem("shopkey_cart_coupon");
                }
            }
        } catch (e) {
            console.error("Error loading saved coupon:", e);
            localStorage.removeItem("shopkey_cart_coupon");
        }
    }

    /**
     * Auto apply saved coupon
     */
    function autoApplySavedCoupon(couponCode) {
        const cart = getCart();
        if (cart.length === 0 || !IS_LOGGED_IN || !couponCode) return;
        
        // Prepare cart items data
        const cartItems = cart.map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            final_price: item.final_price
        }));
        
        const cartTotal = cart.reduce((sum, item) => sum + ((parseFloat(item.final_price) || 0) * item.quantity), 0);
        
        const formData = new FormData();
        formData.append("action", "validateCartCoupon");
        formData.append("token", USER_TOKEN);
        formData.append("coupon_code", couponCode);
        formData.append("cart_items", JSON.stringify(cartItems));
        formData.append("cart_total", cartTotal);
        
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
        formData.append("csrf_token", csrfToken);
        
        fetch(BASE_URL + "ajaxs/client/order.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                appliedCoupon = data.coupon;
                couponDiscount = data.discount_amount;
                itemDiscounts = data.item_discounts || [];
                renderCart();
            } else {
                // Coupon no longer valid, remove it
                localStorage.removeItem("shopkey_cart_coupon");
            }
        })
        .catch(error => {
            console.error("Error auto-applying coupon:", error);
            localStorage.removeItem("shopkey_cart_coupon");
        });
    }

    function bindEvents() {
        // Events will be bound after render
    }

    function getCart() {
        try {
            const cart = localStorage.getItem("shopkey_cart");
            return cart ? JSON.parse(cart) : [];
        } catch (e) {
            console.error("Error reading cart:", e);
            return [];
        }
    }

    function saveCart(cart) {
        localStorage.setItem("shopkey_cart", JSON.stringify(cart));
        updateNavCartCount();
    }

    function updateNavCartCount() {
        const cart = getCart();
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        
        // Update element by ID
        const numCart = document.getElementById("numCart");
        if (numCart) {
            numCart.textContent = totalItems;
            numCart.style.display = totalItems > 0 ? "" : "none";
        }
        
        // Update all elements with class numCart (mobile cart badge, etc.)
        const numCartElements = document.querySelectorAll(".numCart");
        numCartElements.forEach(function(el) {
            el.textContent = totalItems;
            el.style.display = totalItems > 0 ? "" : "none";
        });
    }

    function formatCurrency(amount) {
        // Convert amount by currency rate
        const convertedAmount = amount / CURRENCY.rate;
        
        // Determine thousand separator
        let thousandSep = ".";
        if (CURRENCY.seperator === "comma") {
            thousandSep = ",";
        } else if (CURRENCY.seperator === "space") {
            thousandSep = " ";
        }
        
        // Format number with proper decimals and separator
        const parts = convertedAmount.toFixed(CURRENCY.decimal).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        const formattedNumber = CURRENCY.decimal > 0 ? parts.join(".") : parts[0];
        
        return CURRENCY.symbol_left + formattedNumber + CURRENCY.symbol_right;
    }

    function escapeHtml(text) {
        if (!text) return "";
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function renderCart() {
        const cart = getCart();
        const container = document.getElementById("cartContainer");
        if (!container) return;

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="cart-empty">
                    <div class="cart-empty-visual">
                        <div class="cart-empty-circles">
                            <div class="circle circle-1"></div>
                            <div class="circle circle-2"></div>
                            <div class="circle circle-3"></div>
                        </div>
                        <div class="cart-empty-icon">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                        <div class="cart-empty-float-items">
                            <i class="fa-solid fa-box float-item float-1"></i>
                            <i class="fa-solid fa-tag float-item float-2"></i>
                            <i class="fa-solid fa-gift float-item float-3"></i>
                        </div>
                    </div>
                    <div class="cart-empty-content">
                        <h3>${TRANS.emptyCart || "Giỏ hàng trống"}</h3>
                        <p>${TRANS.emptyCartDesc || "Hãy thêm sản phẩm vào giỏ hàng để mua sắm"}</p>
                    </div>
                    <div class="cart-empty-actions">
                        <a href="${BASE_URL}products" class="btn-browse-products">
                            <i class="fa-solid fa-bag-shopping"></i>
                            <span>${TRANS.viewProducts || "Xem sản phẩm"}</span>
                        </a>
                        <a href="${ORDERS_URL}" class="btn-view-history">
                            <i class="fa-solid fa-receipt"></i>
                            <span>${TRANS.viewOrders || "Xem đơn hàng đã đặt"}</span>
                        </a>
                    </div>
                </div>
            `;
            // Update header count
            const headerCount = document.getElementById("cartItemCount");
            const headerBadge = document.getElementById("cartIconBadge");
            if (headerCount) headerCount.textContent = "0";
            if (headerBadge) headerBadge.textContent = "0";
            return;
        }

        // Filter available items for calculations
        const availableCartItems = cart.filter(item => !item.unavailable);
        
        // Calculate totals (only for available items)
        const totalItems = availableCartItems.reduce((sum, item) => sum + item.quantity, 0);
        
        // Subtotal: tính từ giá gốc (original_price) nếu có
        const subtotal = availableCartItems.reduce((sum, item) => {
            const originalPrice = parseFloat(item.original_price) || parseFloat(item.price) || parseFloat(item.final_price) || 0;
            return sum + (originalPrice * item.quantity);
        }, 0);
        
        // User discount: tính từ original_price - price (sau khi áp dụng user discount)
        const userDiscount = availableCartItems.reduce((sum, item) => {
            const userDiscountAmt = parseFloat(item.user_discount_amount) || 0;
            return sum + (userDiscountAmt * item.quantity);
        }, 0);
        const userDiscountPercent = availableCartItems.length > 0 && availableCartItems[0].user_discount_percent 
            ? parseFloat(availableCartItems[0].user_discount_percent) 
            : 0;
        
        // Tách Flash Sale và Sale thường riêng biệt
        let flashSaleDiscount = 0;
        let regularSaleDiscount = 0;
        
        availableCartItems.forEach(item => {
            const priceAfterUserDiscount = parseFloat(item.price) || parseFloat(item.final_price) || 0;
            const salePrice = parseFloat(item.sale_price) || 0;
            const finalPrice = parseFloat(item.final_price) || 0;
            
            // Flash Sale
            if (item.has_flash_sale && finalPrice < priceAfterUserDiscount) {
                flashSaleDiscount += (priceAfterUserDiscount - finalPrice) * item.quantity;
            }
            // Sale thường (không phải Flash Sale)
            else if (salePrice > 0 && salePrice < priceAfterUserDiscount) {
                regularSaleDiscount += (priceAfterUserDiscount - salePrice) * item.quantity;
            }
        });
        
        const cartTotal = availableCartItems.reduce((sum, item) => sum + ((parseFloat(item.final_price) || 0) * item.quantity), 0);
        
        // Apply coupon discount
        const total = Math.max(0, cartTotal - couponDiscount);

        // Update header count
        const headerCount = document.getElementById("cartItemCount");
        const headerBadge = document.getElementById("cartIconBadge");
        if (headerCount) headerCount.textContent = totalItems;
        if (headerBadge) headerBadge.textContent = totalItems;

        // Render items
        let itemsHtml = "";
        let hasUnavailable = false;
        
        cart.forEach((item, index) => {
            const itemPrice = parseFloat(item.original_price) || parseFloat(item.price) || parseFloat(item.final_price) || 0;
            const itemSalePrice = parseFloat(item.sale_price) || 0;
            const hasSale = itemSalePrice > 0 && itemSalePrice < itemPrice;
            const isUnavailable = item.unavailable === true;
            
            if (isUnavailable) hasUnavailable = true;
            
            // Find discount for this item
            const itemDiscountInfo = itemDiscounts.find(d => 
                d.index === index || 
                (d.product_id === item.product_id && d.plan_id === item.plan_id)
            );
            const itemDiscount = itemDiscountInfo ? itemDiscountInfo.discount : 0;
            
            // Tính các loại giảm giá cho item này
            const itemUserDiscountAmt = parseFloat(item.user_discount_amount) || 0;
            const itemUserDiscountPct = parseFloat(item.user_discount_percent) || 0;
            const priceAfterUserDiscount = parseFloat(item.price) || 0;
            const itemSalePriceVal = parseFloat(item.sale_price) || 0;
            const itemFinalPriceVal = parseFloat(item.final_price) || 0;
            
            // Build discount badges HTML
            let discountBadgesHtml = "";
            
            // 1. Ưu đãi thành viên
            if (itemUserDiscountAmt > 0 && itemUserDiscountPct > 0) {
                discountBadgesHtml += `
                    <div class="cart-item-discount-badge cart-item-discount-member">
                        <i class="fa-solid fa-crown"></i>
                        <span>Ưu đãi thành viên (${itemUserDiscountPct}%): -${formatCurrency(itemUserDiscountAmt * item.quantity)}</span>
                    </div>
                `;
            }
            
            // 2. Flash Sale hoặc Giảm giá sản phẩm
            if (item.has_flash_sale && priceAfterUserDiscount > itemFinalPriceVal) {
                const flashDiscount = (priceAfterUserDiscount - itemFinalPriceVal) * item.quantity;
                discountBadgesHtml += `
                    <div class="cart-item-discount-badge cart-item-discount-flash">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Flash Sale: -${formatCurrency(flashDiscount)}</span>
                    </div>
                `;
            } else if (itemSalePriceVal > 0 && itemSalePriceVal < priceAfterUserDiscount) {
                const saleDiscountAmt = (priceAfterUserDiscount - itemSalePriceVal) * item.quantity;
                discountBadgesHtml += `
                    <div class="cart-item-discount-badge cart-item-discount-sale">
                        <i class="fa-solid fa-tag"></i>
                        <span>Giảm giá sản phẩm: -${formatCurrency(saleDiscountAmt)}</span>
                    </div>
                `;
            }
            
            // Render fields
            let fieldsHtml = "";
            if (item.fields && Object.keys(item.fields).length > 0) {
                fieldsHtml = `<div class="cart-item-fields">
                    <div class="cart-item-fields-title"><i class="fa-solid fa-clipboard-list"></i> ${TRANS.orderInfo || "Thông tin đơn hàng"}</div>`;
                for (const [key, value] of Object.entries(item.fields)) {
                    const label = item.field_labels && item.field_labels[key] ? item.field_labels[key] : key;
                    const displayValue = key.toLowerCase().includes("password") || key.toLowerCase().includes("pass") 
                        ? "••••••" 
                        : escapeHtml(value);
                    fieldsHtml += `<div class="cart-item-field"><strong>${escapeHtml(label)}:</strong> <span class="cart-item-field-value">${displayValue}</span></div>`;
                }
                fieldsHtml += "</div>";
            }
            
            // Render coupon discount for this item
            let itemDiscountHtml = "";
            if (itemDiscount > 0 && !isUnavailable) {
                itemDiscountHtml = `
                    <div class="cart-item-discount-badge cart-item-discount-coupon">
                        <i class="fa-solid fa-ticket"></i>
                        <span>Mã giảm giá: -${formatCurrency(itemDiscount)}</span>
                    </div>
                `;
            }
            
            // Price changed notice
            let priceChangedHtml = "";
            if (item.price_changed && item.old_price) {
                const priceDiff = (parseFloat(item.final_price) || 0) - (parseFloat(item.old_price) || 0);
                const priceChangeClass = priceDiff > 0 ? "price-increased" : "price-decreased";
                const priceChangeIcon = priceDiff > 0 ? "fa-arrow-up" : "fa-arrow-down";
                priceChangedHtml = `
                    <div class="cart-item-price-changed ${priceChangeClass}">
                        <i class="fa-solid ${priceChangeIcon}"></i>
                        ${priceDiff > 0 
                            ? (TRANS.priceIncreased || "Giá tăng") 
                            : (TRANS.priceDecreased || "Giá giảm")}: ${formatCurrency(Math.abs(priceDiff))}
                    </div>
                `;
            }
            
            // Unavailable notice
            let unavailableHtml = "";
            if (isUnavailable) {
                unavailableHtml = `
                    <div class="cart-item-unavailable">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        ${item.unavailable_reason || TRANS.itemUnavailable || "Sản phẩm không khả dụng"}
                    </div>
                `;
            }

            itemsHtml += `
                <div class="cart-item ${isUnavailable ? 'cart-item-disabled' : ''}" data-index="${index}">
                    <div class="cart-item-image">
                        <img src="${escapeHtml(item.product_image)}" alt="${escapeHtml(item.product_name)}">
                        ${isUnavailable ? '<div class="cart-item-image-overlay"><i class="fa-solid fa-ban"></i></div>' : ''}
                    </div>
                    <div class="cart-item-info">
                        <h4 class="cart-item-name">
                            <a href="${BASE_URL}product/${escapeHtml(item.product_slug)}">${escapeHtml(item.product_name)}</a>
                        </h4>
                        <p class="cart-item-plan">
                            <i class="fa-solid fa-box"></i>
                            ${escapeHtml(item.plan_name)}
                        </p>
                        ${(discountBadgesHtml || itemDiscountHtml) ? `
                        <div class="cart-item-discounts">
                            ${discountBadgesHtml}
                            ${itemDiscountHtml}
                        </div>
                        ` : ""}
                        ${unavailableHtml}
                        ${fieldsHtml}
                        <div class="cart-item-bottom">
                            <div class="cart-item-price">
                                ${!isUnavailable ? `
                                <span class="cart-item-final-price">${formatCurrency((parseFloat(item.final_price) || 0) * item.quantity)}</span>
                                ${hasSale || userDiscountPercent > 0 ? `<span class="cart-item-original-price">${formatCurrency(itemPrice * item.quantity)}</span>` : ""}
                                ` : `<span class="cart-item-final-price cart-item-price-na">--</span>`}
                            </div>
                            ${!isUnavailable ? `
                            <div class="cart-item-qty">
                                <button type="button" onclick="CartPage.updateQty(${index}, -1)">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <input type="number" 
                                       value="${item.quantity}" 
                                       min="1" 
                                       max="100" 
                                       class="cart-qty-input"
                                       onchange="CartPage.setQty(${index}, this.value)"
                                       onkeypress="return event.charCode >= 48 && event.charCode <= 57">
                                <button type="button" onclick="CartPage.updateQty(${index}, 1)">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <button type="button" class="cart-item-remove" onclick="CartPage.removeItem(${index})" title="${TRANS.remove || "Xóa"}">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            `;
        });

        // Check if there are unavailable items
        const availableItems = cart.filter(item => !item.unavailable);
        const canCheckout = availableItems.length > 0 && !hasUnavailable;
        
        // Recalculate totals excluding unavailable items
        const availableTotal = availableItems.reduce((sum, item) => sum + ((parseFloat(item.final_price) || 0) * item.quantity), 0);
        const finalTotal = Math.max(0, availableTotal - couponDiscount);
        
        // Check if user can pay
        const canPay = IS_LOGGED_IN && userBalance >= finalTotal && canCheckout;
        const balanceAfter = userBalance - finalTotal;

        // Build payment section
        let paymentHtml = "";
        
        // Warning if unavailable items
        if (hasUnavailable) {
            paymentHtml += `
                <div class="cart-unavailable-warning">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p>${TRANS.removeUnavailable || "Vui lòng xóa các sản phẩm không khả dụng để tiếp tục thanh toán"}</p>
                </div>
            `;
        }
        
        if (IS_LOGGED_IN) {
            paymentHtml += `
                <div class="cart-checkout-buttons">
                    ${canPay ? `
                    <button type="button" class="btn-pay-balance" id="btnPayWithBalance">
                        <i class="fa-solid fa-wallet"></i>
                        ${TRANS.payWithBalance || "Thanh toán đơn hàng"}
                    </button>
                    ` : hasUnavailable ? `
                    <button type="button" class="btn-pay-balance" disabled>
                        <i class="fa-solid fa-ban"></i>
                        ${TRANS.cannotCheckout || "Không thể thanh toán"}
                    </button>
                    ` : `
                    <button type="button" class="btn-topup btn-recharge-now" onclick="openRechargeSidebar()">
                        <i class="fa-solid fa-plus-circle"></i>
                        ${TRANS.topUp || "Nạp thêm tiền"}
                    </button>
                    `}
                </div>
            `;
        } else {
            paymentHtml += `
                <div class="cart-login-required">
                    <div class="cart-login-icon">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <p>${TRANS.loginRequired || "Vui lòng đăng nhập để thanh toán"}</p>
                    <a href="${LOGIN_URL}" class="btn-login">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        ${TRANS.login || "Đăng nhập"}
                    </a>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="cart-content">
                <div class="cart-items-wrapper">
                    <div class="cart-items-header">
                        <i class="fa-solid fa-list"></i> ${TRANS.productList || "Danh sách sản phẩm"} (${totalItems})
                    </div>
                    <div class="cart-items-list">
                        ${itemsHtml}
                    </div>
                </div>
                <div class="cart-summary-wrapper">
                    <!-- Order Summary Card -->
                    <div class="cart-summary">
                        <div class="cart-summary-header">
                            <i class="fa-solid fa-receipt"></i>
                            ${TRANS.orderSummary || "Tóm tắt đơn hàng"}
                        </div>
                        <div class="cart-summary-body">
                            <div class="cart-summary-row">
                                <span class="cart-summary-label">${TRANS.subtotal || "Tạm tính"} (${totalItems} ${TRANS.products || "sản phẩm"})</span>
                                <span class="cart-summary-value">${formatCurrency(subtotal)}</span>
                            </div>
                            ${userDiscount > 0 ? `
                            <div class="cart-summary-row cart-summary-row-user-discount">
                                <span class="cart-summary-label">
                                    <i class="fa-solid fa-crown"></i> 
                                    ${TRANS.memberDiscount || "Ưu đãi thành viên"} (${userDiscountPercent}%)
                                </span>
                                <span class="cart-summary-value discount">-${formatCurrency(userDiscount)}</span>
                            </div>
                            ` : ""}
                            ${flashSaleDiscount > 0 ? `
                            <div class="cart-summary-row cart-summary-row-flash">
                                <span class="cart-summary-label">
                                    <i class="fa-solid fa-bolt"></i>
                                    ${TRANS.flashSaleDiscount || "Flash Sale"}
                                </span>
                                <span class="cart-summary-value discount">-${formatCurrency(flashSaleDiscount)}</span>
                            </div>
                            ` : ""}
                            ${regularSaleDiscount > 0 ? `
                            <div class="cart-summary-row cart-summary-row-sale">
                                <span class="cart-summary-label">
                                    <i class="fa-solid fa-tag"></i>
                                    ${TRANS.saleDiscount || "Giảm giá sản phẩm"}
                                </span>
                                <span class="cart-summary-value discount">-${formatCurrency(regularSaleDiscount)}</span>
                            </div>
                            ` : ""}
                            ${couponDiscount > 0 ? `
                            <div class="cart-summary-row">
                                <span class="cart-summary-label">
                                    <i class="fa-solid fa-ticket"></i>
                                    ${TRANS.couponDiscount || "Mã giảm giá"}
                                </span>
                                <span class="cart-summary-value discount">-${formatCurrency(couponDiscount)}</span>
                            </div>
                            ` : ""}
                            <div class="cart-summary-row cart-summary-total">
                                <span class="cart-summary-label">${TRANS.total || "Tổng cộng"}</span>
                                <span class="cart-summary-value">${formatCurrency(total)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coupon Card -->
                    <div class="cart-coupon-card">
                        <div class="cart-coupon-header" onclick="CartPage.toggleCoupon()">
                            <span><i class="fa-solid fa-ticket"></i> ${TRANS.couponCode || "Mã giảm giá"}</span>
                            <i class="fa-solid fa-chevron-${appliedCoupon ? 'up' : 'down'}" id="couponChevron"></i>
                        </div>
                        <div class="cart-coupon-form ${appliedCoupon ? 'show' : ''}" id="couponForm">
                            ${appliedCoupon ? `
                            <div class="cart-coupon-applied">
                                <div class="coupon-info">
                                    <span class="coupon-code"><i class="fa-solid fa-check-circle"></i> ${appliedCoupon.code}</span>
                                    <span class="coupon-discount">-${formatCurrency(couponDiscount)}</span>
                                </div>
                                <button type="button" class="btn-remove-coupon" onclick="CartPage.removeCoupon()">
                                    <i class="fa-solid fa-times"></i>
                                </button>
                            </div>
                            ` : `
                            <div class="cart-coupon-input">
                                <input type="text" id="couponCodeInput" placeholder="${TRANS.enterCouponCode || "Nhập mã giảm giá"}" maxlength="50">
                                <button type="button" class="btn-apply-coupon" id="btnApplyCoupon">
                                    ${TRANS.apply || "Áp dụng"}
                                </button>
                            </div>
                            <div class="cart-coupon-message" id="couponMessage"></div>
                            `}
                        </div>
                    </div>
                    
                    <!-- Payment Card -->
                    <div class="cart-payment-card">
                        ${paymentHtml}
                    </div>
                    
                    <!-- Actions Card -->
                    <div class="cart-actions-card">
                        <a href="${ORDERS_URL}" class="btn-view-orders">
                            <i class="fa-solid fa-receipt"></i>
                            ${TRANS.viewOrders || "Xem đơn hàng đã đặt"}
                        </a>
                        <button type="button" class="btn-refresh-prices" id="btnRefreshPrices">
                            <i class="fa-solid fa-rotate"></i>
                            ${TRANS.refreshPrices || "Cập nhật giá"}
                        </button>
                        <button type="button" class="btn-clear-cart" id="btnClearAllCart">
                            <i class="fa-solid fa-trash"></i>
                            ${TRANS.clearAll || "Xóa tất cả"}
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Bind buttons after render
        bindPostRenderEvents();
    }

    function bindPostRenderEvents() {
        // Refresh prices button
        const refreshBtn = document.getElementById("btnRefreshPrices");
        if (refreshBtn) {
            refreshBtn.addEventListener("click", async function() {
                if (isFetchingPrices) return;
                
                isFetchingPrices = true;
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${TRANS.updating || "Đang cập nhật..."}`;
                
                await fetchRealtimePrices();
                
                // Re-validate coupon if applied
                if (appliedCoupon) {
                    await revalidateCouponAsync();
                }
                
                isFetchingPrices = false;
                renderCart();
                
                showMessage(TRANS.pricesUpdated || "Đã cập nhật giá mới nhất", "success");
            });
        }
        
        // Clear cart button
        const clearBtn = document.getElementById("btnClearAllCart");
        if (clearBtn) {
            clearBtn.addEventListener("click", function() {
                if (confirm(TRANS.confirmClear || "Bạn có chắc muốn xóa tất cả sản phẩm trong giỏ hàng?")) {
                    localStorage.setItem("shopkey_cart", "[]");
                    localStorage.removeItem("shopkey_cart_coupon");
                    appliedCoupon = null;
                    couponDiscount = 0;
                    itemDiscounts = [];
                    renderCart();
                    updateNavCartCount();
                }
            });
        }

        // Pay with balance button
        const payBtn = document.getElementById("btnPayWithBalance");
        if (payBtn) {
            payBtn.addEventListener("click", handleCheckout);
        }
        
        // Apply coupon button
        const applyCouponBtn = document.getElementById("btnApplyCoupon");
        if (applyCouponBtn) {
            applyCouponBtn.addEventListener("click", handleApplyCoupon);
        }
        
        // Coupon input enter key
        const couponInput = document.getElementById("couponCodeInput");
        if (couponInput) {
            couponInput.addEventListener("keypress", function(e) {
                if (e.key === "Enter") {
                    handleApplyCoupon();
                }
            });
        }
    }
    
    /**
     * Revalidate coupon async (for refresh)
     */
    async function revalidateCouponAsync() {
        const cart = getCart();
        if (cart.length === 0 || !appliedCoupon) return;
        
        const cartItems = cart.filter(item => !item.unavailable).map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            final_price: item.final_price
        }));
        
        if (cartItems.length === 0) {
            appliedCoupon = null;
            couponDiscount = 0;
            itemDiscounts = [];
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append("action", "validateCartCoupon");
            formData.append("token", USER_TOKEN);
            formData.append("coupon_code", appliedCoupon.code);
            formData.append("cart_items", JSON.stringify(cartItems));
            formData.append("csrf_token", getCSRFToken());
            
            const response = await fetch(BASE_URL + "ajaxs/client/order.php", {
                method: "POST",
                body: formData
            });
            
            const data = await response.json();
            
            if (data.status === "success") {
                couponDiscount = data.discount_amount;
                itemDiscounts = data.item_discounts || [];
            } else {
                appliedCoupon = null;
                couponDiscount = 0;
                itemDiscounts = [];
            }
        } catch (error) {
            console.error("Revalidate coupon error:", error);
        }
    }

    function handleApplyCoupon() {
        const couponInput = document.getElementById("couponCodeInput");
        const couponMessage = document.getElementById("couponMessage");
        const applyCouponBtn = document.getElementById("btnApplyCoupon");
        
        if (!couponInput || !applyCouponBtn) return;
        
        const code = couponInput.value.trim().toUpperCase();
        if (!code) {
            showCouponMessage(TRANS.enterCouponCode || "Vui lòng nhập mã giảm giá", "error");
            couponInput.focus();
            return;
        }
        
        // Get cart items
        const cart = getCart();
        if (cart.length === 0) {
            showCouponMessage(TRANS.emptyCart || "Giỏ hàng trống", "error");
            return;
        }
        
        // Prepare cart items data for API
        const cartItems = cart.map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            final_price: item.final_price
        }));
        
        // Disable button
        applyCouponBtn.disabled = true;
        applyCouponBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i>`;
        
        // Send request
        const formData = new FormData();
        formData.append("action", "validateCartCoupon");
        formData.append("token", USER_TOKEN);
        formData.append("coupon_code", code);
        formData.append("cart_items", JSON.stringify(cartItems));
        formData.append("csrf_token", getCSRFToken());
        
        fetch(BASE_URL + "ajaxs/client/order.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            applyCouponBtn.disabled = false;
            applyCouponBtn.innerHTML = TRANS.apply || "Áp dụng";
            
            if (data.status === "success") {
                appliedCoupon = data.coupon;
                couponDiscount = data.discount_amount;
                itemDiscounts = data.item_discounts || [];
                
                // Save coupon to localStorage
                saveCouponToStorage(appliedCoupon.code);
                
                showMessage(data.msg, "success");
                renderCart();
            } else {
                showCouponMessage(data.msg || TRANS.invalidCoupon || "Mã giảm giá không hợp lệ", "error");
            }
        })
        .catch(error => {
            applyCouponBtn.disabled = false;
            applyCouponBtn.innerHTML = TRANS.apply || "Áp dụng";
            console.error("Apply coupon error:", error);
            showCouponMessage(TRANS.errorOccurred || "Đã xảy ra lỗi", "error");
        });
    }
    
    function showCouponMessage(message, type) {
        const couponMessage = document.getElementById("couponMessage");
        if (!couponMessage) return;
        
        couponMessage.className = "cart-coupon-message " + type;
        couponMessage.textContent = message;
        couponMessage.style.display = "block";
        
        // Auto hide after 5s
        setTimeout(() => {
            couponMessage.style.display = "none";
        }, 5000);
    }

    /**
     * Update stepper state
     */
    function updateStepper(step) {
        currentStep = step;
        const stepperItems = document.querySelectorAll('.cart-stepper-item');
        const stepperLines = document.querySelectorAll('.stepper-line');
        
        stepperItems.forEach((item, index) => {
            const itemStep = index + 1;
            item.classList.remove('active', 'completed');
            
            if (itemStep < step) {
                item.classList.add('completed');
            } else if (itemStep === step) {
                item.classList.add('active');
            }
        });
        
        stepperLines.forEach((line, index) => {
            line.classList.remove('active');
            if (index < step - 1) {
                line.classList.add('active');
            }
        });
    }

    function handleCheckout() {
        if (isProcessing) return;
        
        const cart = getCart();
        if (cart.length === 0) {
            showMessage(TRANS.emptyCart || "Giỏ hàng trống", "error");
            return;
        }

        // Show confirmation step instead of Swal
        showConfirmationStep();
    }
    
    /**
     * Show confirmation step
     */
    function showConfirmationStep() {
        const cart = getCart();
        const availableCart = cart.filter(item => !item.unavailable);
        
        if (availableCart.length === 0) {
            showMessage(TRANS.emptyCart || "Giỏ hàng trống", "error");
            return;
        }
        
        // Update stepper to step 2
        updateStepper(2);
        
        // Hide cart page header
        const cartHeader = document.querySelector('.cart-page-header');
        if (cartHeader) {
            cartHeader.style.display = 'none';
        }
        
        // Calculate totals
        const totalItems = availableCart.reduce((sum, item) => sum + item.quantity, 0);
        
        // Subtotal: tính từ giá gốc (original_price) nếu có
        const subtotal = availableCart.reduce((sum, item) => {
            const originalPrice = parseFloat(item.original_price) || parseFloat(item.price) || parseFloat(item.final_price) || 0;
            return sum + (originalPrice * item.quantity);
        }, 0);
        
        // User discount
        const userDiscount = availableCart.reduce((sum, item) => {
            const userDiscountAmt = parseFloat(item.user_discount_amount) || 0;
            return sum + (userDiscountAmt * item.quantity);
        }, 0);
        const userDiscountPercent = availableCart.length > 0 && availableCart[0].user_discount_percent 
            ? parseFloat(availableCart[0].user_discount_percent) 
            : 0;
        
        // Tách Flash Sale và Sale thường riêng biệt
        let flashSaleDiscount = 0;
        let regularSaleDiscount = 0;
        
        availableCart.forEach(item => {
            const priceAfterUserDiscount = parseFloat(item.price) || parseFloat(item.final_price) || 0;
            const salePrice = parseFloat(item.sale_price) || 0;
            const finalPrice = parseFloat(item.final_price) || 0;
            
            // Flash Sale
            if (item.has_flash_sale && finalPrice < priceAfterUserDiscount) {
                flashSaleDiscount += (priceAfterUserDiscount - finalPrice) * item.quantity;
            }
            // Sale thường (không phải Flash Sale)
            else if (salePrice > 0 && salePrice < priceAfterUserDiscount) {
                regularSaleDiscount += (priceAfterUserDiscount - salePrice) * item.quantity;
            }
        });
        
        const cartTotal = availableCart.reduce((sum, item) => sum + ((parseFloat(item.final_price) || 0) * item.quantity), 0);
        const finalTotal = Math.max(0, cartTotal - couponDiscount);
        const balanceAfter = userBalance - finalTotal;
        
        // Build order items HTML
        let itemsHtml = '';
        availableCart.forEach(item => {
            const itemPrice = parseFloat(item.price) || parseFloat(item.final_price) || 0;
            const itemSalePrice = parseFloat(item.sale_price) || 0;
            const hasSale = itemSalePrice > 0 && itemSalePrice < itemPrice;
            
            itemsHtml += `
                <div class="confirm-item">
                    <div class="confirm-item-image">
                        <img src="${escapeHtml(item.product_image)}" alt="${escapeHtml(item.product_name)}">
                    </div>
                    <div class="confirm-item-info">
                        <div class="confirm-item-name">${escapeHtml(item.product_name)} <span class="confirm-item-qty">x${item.quantity}</span></div>
                        <div class="confirm-item-plan">
                            <i class="fa-solid fa-box"></i> ${escapeHtml(item.plan_name)}
                        </div>
                    </div>
                    <div class="confirm-item-price-col">
                        <span class="confirm-item-price">${formatCurrency((parseFloat(item.final_price) || 0) * item.quantity)}</span>
                        ${hasSale ? `<span class="confirm-item-original">${formatCurrency(itemPrice * item.quantity)}</span>` : ''}
                    </div>
                </div>
            `;
        });
        
        // Render confirmation content in main container
        const container = document.getElementById("cartContainer");
        if (container) {
            container.innerHTML = `
                <div class="cart-confirm-content">
                    <!-- Left: Order Items -->
                    <div class="confirm-items-wrapper">
                        <div class="confirm-section-card">
                            <div class="confirm-section-header">
                                <i class="fa-solid fa-shopping-bag"></i>
                                <h3>${TRANS.orderItems || "Sản phẩm đặt mua"}</h3>
                                <span class="confirm-item-count">${totalItems} ${TRANS.products || "sản phẩm"}</span>
                            </div>
                            <div class="confirm-items-list">
                                ${itemsHtml}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right: Payment Summary -->
                    <div class="confirm-summary-wrapper">
                        <!-- Payment Method -->
                        <div class="confirm-section-card">
                            <div class="confirm-section-header">
                                <i class="fa-solid fa-credit-card"></i>
                                <h3>${TRANS.paymentMethod || "Phương thức thanh toán"}</h3>
                            </div>
                            <div class="confirm-payment-method">
                                <div class="payment-method-item active">
                                    <div class="payment-method-icon">
                                        <i class="fa-solid fa-wallet"></i>
                                    </div>
                                    <div class="payment-method-info">
                                        <span class="payment-method-name">${TRANS.accountBalance || "Số dư tài khoản"}</span>
                                        <span class="payment-method-balance">${formatCurrency(userBalance)}</span>
                                    </div>
                                    <i class="fa-solid fa-check-circle payment-method-check"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="confirm-section-card">
                            <div class="confirm-section-header">
                                <i class="fa-solid fa-receipt"></i>
                                <h3>${TRANS.orderSummary || "Tóm tắt đơn hàng"}</h3>
                            </div>
                            <div class="confirm-summary-body">
                                <div class="confirm-summary-row cart-summary-row">
                                    <span class="cart-summary-label">${TRANS.subtotal || "Tạm tính"}</span>
                                    <span>${formatCurrency(subtotal)}</span>
                                </div>
                                ${userDiscount > 0 ? `
                                <div class="confirm-summary-row discount cart-summary-row cart-summary-row-user-discount">
                                    <span class="cart-summary-label"><i class="fa-solid fa-crown"></i> ${TRANS.memberDiscount || "Ưu đãi thành viên"} (${userDiscountPercent}%)</span>
                                    <span>-${formatCurrency(userDiscount)}</span>
                                </div>
                                ` : ''}
                                ${flashSaleDiscount > 0 ? `
                                <div class="confirm-summary-row discount cart-summary-row cart-summary-row-flash">
                                    <span class="cart-summary-label"><i class="fa-solid fa-bolt"></i> ${TRANS.flashSaleDiscount || "Flash Sale"}</span>
                                    <span>-${formatCurrency(flashSaleDiscount)}</span>
                                </div>
                                ` : ''}
                                ${regularSaleDiscount > 0 ? `
                                <div class="confirm-summary-row discount cart-summary-row cart-summary-row-sale">
                                    <span class="cart-summary-label"><i class="fa-solid fa-tag"></i> ${TRANS.saleDiscount || "Giảm giá sản phẩm"}</span>
                                    <span>-${formatCurrency(regularSaleDiscount)}</span>
                                </div>
                                ` : ''}
                                ${couponDiscount > 0 ? `
                                <div class="confirm-summary-row discount cart-summary-row">
                                    <span class="cart-summary-label"><i class="fa-solid fa-ticket"></i> ${TRANS.couponDiscount || "Mã giảm giá"}</span>
                                    <span>-${formatCurrency(couponDiscount)}</span>
                                </div>
                                ` : ''}
                                <div class="confirm-summary-total">
                                    <span>${TRANS.total || "Tổng cộng"}</span>
                                    <span>${formatCurrency(finalTotal)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="confirm-action-buttons">
                            <button type="button" class="btn-go-back" id="btnGoBack">
                                <i class="fa-solid fa-arrow-left"></i>
                                ${TRANS.goBack || "Quay lại"}
                            </button>
                            <button type="button" class="btn-confirm-checkout" id="btnConfirmCheckout">
                                <i class="fa-solid fa-check-circle"></i>
                                ${TRANS.confirmPayment || "Xác nhận thanh toán"}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Bind confirmation buttons
            document.getElementById('btnGoBack')?.addEventListener('click', goBackToCart);
            document.getElementById('btnConfirmCheckout')?.addEventListener('click', processCheckout);
        }
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    /**
     * Go back to cart from confirmation step
     */
    function goBackToCart() {
        updateStepper(1);
        
        // Show cart page header again
        const cartHeader = document.querySelector('.cart-page-header');
        if (cartHeader) {
            cartHeader.style.display = '';
        }
        
        renderCart();
    }
    
    /**
     * Show success step after checkout
     */
    function showSuccessStep(data) {
        const container = document.getElementById("cartContainer");
        if (!container) return;
        
        // Hide cart page header
        const cartHeader = document.querySelector('.cart-page-header');
        if (cartHeader) {
            cartHeader.style.display = 'none';
        }
        
        const orderCount = data.orders?.length || 0;
        // Get direct link to first order detail if available
        const orderDetailUrl = data.order_detail_url || data.redirect || ORDERS_URL;
        
        container.innerHTML = `
            <div class="cart-success">
                <div class="cart-success-visual">
                    <div class="success-circles">
                        <div class="circle circle-1"></div>
                        <div class="circle circle-2"></div>
                        <div class="circle circle-3"></div>
                    </div>
                    <div class="success-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div class="success-confetti">
                        <i class="confetti confetti-1 fa-solid fa-star"></i>
                        <i class="confetti confetti-2 fa-solid fa-circle"></i>
                        <i class="confetti confetti-3 fa-solid fa-heart"></i>
                        <i class="confetti confetti-4 fa-solid fa-star"></i>
                        <i class="confetti confetti-5 fa-solid fa-circle"></i>
                    </div>
                </div>
                
                <div class="cart-success-content">
                    <h2 class="success-title">${TRANS.checkoutSuccess || "Thanh toán thành công!"}</h2>
                    <p class="success-message">${orderCount} ${TRANS.orderCreated || "đơn hàng đã được tạo"}</p>
                </div>
                
                <div class="cart-success-actions">
                    <a href="${orderDetailUrl}" class="btn-success-primary">
                        <i class="fa-solid fa-receipt"></i>
                        ${TRANS.viewOrderDetails || "Xem chi tiết đơn hàng"}
                    </a>
                    <a href="${BASE_URL}products" class="btn-success-secondary">
                        <i class="fa-solid fa-bag-shopping"></i>
                        ${TRANS.viewProducts || "Tiếp tục mua sắm"}
                    </a>
                </div>
            </div>
        `;
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function processCheckout() {
        if (isProcessing) return;
        isProcessing = true;

        const cart = getCart();
        
        // Filter out unavailable items
        const availableCart = cart.filter(item => !item.unavailable);
        
        if (availableCart.length === 0) {
            showMessage(TRANS.emptyCart || "Giỏ hàng trống", "error");
            isProcessing = false;
            return;
        }
        
        // Update confirm button state
        const confirmBtn = document.getElementById("btnConfirmCheckout");
        const goBackBtn = document.getElementById("btnGoBack");
        
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${TRANS.processing || "Đang xử lý..."}`;
        }
        if (goBackBtn) {
            goBackBtn.disabled = true;
        }

        // Prepare cart data for API (only available items)
        const cartData = availableCart.map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            fields: item.fields || {},
            product_name: item.product_name,
            plan_name: item.plan_name
        }));

        const formData = new FormData();
        formData.append("action", "checkoutCart");
        formData.append("token", USER_TOKEN);
        formData.append("cart_items", JSON.stringify(cartData));
        formData.append("csrf_token", getCSRFToken());
        
        // Include coupon if applied
        if (appliedCoupon && appliedCoupon.code) {
            formData.append("coupon_code", appliedCoupon.code);
        }

        fetch(BASE_URL + "ajaxs/client/order.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            isProcessing = false;
            
            if (data.status === "success") {
                // Update stepper to complete
                updateStepper(3);
                
                // Clear cart and coupon
                localStorage.setItem("shopkey_cart", "[]");
                localStorage.removeItem("shopkey_cart_coupon");
                appliedCoupon = null;
                couponDiscount = 0;
                itemDiscounts = [];
                updateNavCartCount();
                
                // Update balance
                if (typeof data.new_balance !== "undefined") {
                    userBalance = data.new_balance;
                }

                // Show success UI in cartContainer
                showSuccessStep(data);
            } else {
                // Show error
                showMessage(data.msg || TRANS.checkoutError || "Thanh toán thất bại", "error");
                
                // Reset confirm button
                const confirmBtn = document.getElementById("btnConfirmCheckout");
                const goBackBtn = document.getElementById("btnGoBack");
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${TRANS.confirmPayment || "Xác nhận thanh toán"}`;
                }
                if (goBackBtn) {
                    goBackBtn.disabled = false;
                }
            }
        })
        .catch(error => {
            isProcessing = false;
            console.error("Checkout error:", error);
            showMessage(TRANS.checkoutError || "Thanh toán thất bại", "error");
            
            // Reset confirm button
            const confirmBtn = document.getElementById("btnConfirmCheckout");
            const goBackBtn = document.getElementById("btnGoBack");
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = `<i class="fa-solid fa-check-circle"></i> ${TRANS.confirmPayment || "Xác nhận thanh toán"}`;
            }
            if (goBackBtn) {
                goBackBtn.disabled = false;
            }
        });
    }

    function showMessage(message, type) {
        if (typeof Swal !== "undefined") {
            Swal.fire({
                icon: type === "error" ? "error" : (type === "warning" ? "warning" : "success"),
                title: type === "error" ? "Lỗi" : (type === "warning" ? "Cảnh báo" : "Thành công"),
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            alert(message);
        }
    }

    window.CartPage = {
        updateQty: function(index, delta) {
            const cart = getCart();
            if (index < 0 || index >= cart.length) return;

            const item = cart[index];
            const newQty = item.quantity + delta;

            if (newQty < 1) {
                this.removeItem(index);
                return;
            }

            // Check stock
            if (item.is_instant === 1 && newQty > item.stock_count) {
                showMessage(TRANS.outOfStock || "Số lượng vượt quá kho hàng", "warning");
                return;
            }

            cart[index].quantity = newQty;
            saveCart(cart);
            
            // Re-validate coupon if applied
            if (appliedCoupon) {
                revalidateCoupon();
            } else {
                renderCart();
            }
        },

        // Set quantity directly (cho phép nhập số lượng bằng tay)
        setQty: function(index, value) {
            const cart = getCart();
            if (index < 0 || index >= cart.length) return;

            const item = cart[index];
            let newQty = parseInt(value, 10);

            // Validate input
            if (isNaN(newQty) || newQty < 1) {
                newQty = 1;
            }
            
            // Max limit
            if (newQty > 100) {
                newQty = 100;
            }

            // Check stock for instant delivery
            if (item.is_instant === 1 && newQty > item.stock_count) {
                showMessage(TRANS.outOfStock || "Số lượng vượt quá kho hàng", "warning");
                newQty = item.stock_count > 0 ? item.stock_count : 1;
            }

            cart[index].quantity = newQty;
            saveCart(cart);
            
            // Re-validate coupon if applied
            if (appliedCoupon) {
                revalidateCoupon();
            } else {
                renderCart();
            }
        },

        removeItem: function(index) {
            const cart = getCart();
            if (index < 0 || index >= cart.length) return;
            
            cart.splice(index, 1);
            saveCart(cart);
            
            // If cart is empty, remove coupon
            if (cart.length === 0) {
                appliedCoupon = null;
                couponDiscount = 0;
                itemDiscounts = [];
            } else if (appliedCoupon) {
                // Re-validate coupon with new total
                revalidateCoupon();
                return;
            }
            
            renderCart();
        },
        
        toggleCoupon: function() {
            const couponForm = document.getElementById("couponForm");
            const chevron = document.getElementById("couponChevron");
            if (!couponForm) return;
            
            couponForm.classList.toggle("show");
            if (chevron) {
                chevron.className = couponForm.classList.contains("show") 
                    ? "fa-solid fa-chevron-up" 
                    : "fa-solid fa-chevron-down";
            }
        },
        
        removeCoupon: function() {
            appliedCoupon = null;
            couponDiscount = 0;
            itemDiscounts = [];
            // Remove coupon from localStorage
            localStorage.removeItem("shopkey_cart_coupon");
            renderCart();
        }
    };

    /**
     * Save coupon code to localStorage
     */
    function saveCouponToStorage(couponCode) {
        if (couponCode) {
            const couponData = {
                code: couponCode,
                saved_at: new Date().toISOString()
            };
            localStorage.setItem("shopkey_cart_coupon", JSON.stringify(couponData));
        }
    }
    
    function revalidateCoupon() {
        const cart = getCart();
        
        if (cart.length === 0) {
            appliedCoupon = null;
            couponDiscount = 0;
            itemDiscounts = [];
            renderCart();
            return;
        }
        
        // Prepare cart items data
        const cartItems = cart.map(item => ({
            product_id: item.product_id,
            plan_id: item.plan_id,
            quantity: item.quantity,
            final_price: item.final_price
        }));
        
        // Send request to re-validate
        const formData = new FormData();
        formData.append("action", "validateCartCoupon");
        formData.append("token", USER_TOKEN);
        formData.append("coupon_code", appliedCoupon.code);
        formData.append("cart_items", JSON.stringify(cartItems));
        formData.append("csrf_token", getCSRFToken());
        
        fetch(BASE_URL + "ajaxs/client/order.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                couponDiscount = data.discount_amount;
                itemDiscounts = data.item_discounts || [];
            } else {
                // Coupon no longer valid
                appliedCoupon = null;
                couponDiscount = 0;
                itemDiscounts = [];
                showMessage(data.msg || TRANS.couponNoLongerValid || "Mã giảm giá không còn hợp lệ", "warning");
            }
            renderCart();
        })
        .catch(error => {
            console.error("Revalidate coupon error:", error);
            renderCart();
        });
    }

    // Init when all resources (including CSS) are loaded
    if (document.readyState === "complete") {
        init();
    } else {
        window.addEventListener("load", init);
    }
})();
