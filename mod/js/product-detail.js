/**
 * Product Detail Module
 * Xử lý chọn gói, số lượng, và đặt hàng
 */

(function() {
    'use strict';

    // State
    let state = {
        selectedPlanId: null,
        selectedPlan: null,
        quantity: 1,
        isSubmitting: false,
        couponCode: '',
        couponData: null,
        couponDiscount: 0,
        isApplyingCoupon: false,
        isInitialLoad: true // Flag để không cuộn khi mới load trang
    };

    // DOM Elements
    let elements = {};

    /**
     * Initialize
     */
    function init() {
        cacheElements();
        bindEvents();
        
        // Set default selected plan
        if (typeof DEFAULT_PLAN_ID !== 'undefined' && DEFAULT_PLAN_ID) {
            selectPlan(DEFAULT_PLAN_ID);
        }
        
        // Sau khi init xong, set flag về false để cho phép cuộn khi user click chọn gói
        // Dùng setTimeout để đảm bảo loadPlanFields đã được gọi
        setTimeout(() => {
            state.isInitialLoad = false;
        }, 500);
    }

    /**
     * Cache DOM elements
     */
    function cacheElements() {
        elements = {
            plansGrid: document.getElementById('productPlansGrid'),
            planCards: document.querySelectorAll('.product-plan-card'),
            qtyInput: document.getElementById('orderQuantity'),
            qtyMinus: document.getElementById('qtyMinus'),
            qtyPlus: document.getElementById('qtyPlus'),
            orderFieldsContainer: document.getElementById('orderFieldsContainer'),
            orderFieldsSection: document.getElementById('orderFieldsSection'),
            originalPrice: document.getElementById('originalPrice'),
            memberDiscountRow: document.getElementById('memberDiscountRow'),
            memberDiscountPercent: document.getElementById('memberDiscountPercent'),
            memberDiscountAmount: document.getElementById('memberDiscountAmount'),
            flashSaleRow: document.getElementById('flashSaleRow'),
            flashSaleAmount: document.getElementById('flashSaleAmount'),
            saleRow: document.getElementById('saleRow'),
            saleAmount: document.getElementById('saleAmount'),
            couponDiscountRow: document.getElementById('couponDiscountRow'),
            couponDiscountAmount: document.getElementById('couponDiscountAmount'),
            totalPrice: document.getElementById('totalPrice'),
            stockInfo: document.getElementById('stockInfo'),
            stockCount: document.getElementById('stockCount'),
            btnSubmitOrder: document.getElementById('btnSubmitOrder'),
            btnAddToCart: document.getElementById('btnAddToCart'),
            productMainImage: document.getElementById('productMainImage'),
            favoriteBtn: document.getElementById('productFavoriteBtn'),
            selectedPlanDescription: document.getElementById('selectedPlanDescription'),
            selectedPlanName: document.querySelector('.selected-plan-name'),
            selectedPlanDescriptionContent: document.querySelector('.plan-desc-inner') || document.querySelector('.plan-desc-content') || document.querySelector('.selected-plan-description-content'),
            // Coupon elements
            couponToggle: document.getElementById('couponToggle'),
            couponFormWrapper: document.getElementById('couponFormWrapper'),
            couponCodeInput: document.getElementById('couponCode'),
            btnApplyCoupon: document.getElementById('btnApplyCoupon'),
            couponMessage: document.getElementById('couponMessage'),
            appliedCoupon: document.getElementById('appliedCoupon'),
            appliedCouponCode: document.getElementById('appliedCouponCode'),
            appliedCouponDesc: document.getElementById('appliedCouponDesc'),
            btnRemoveCoupon: document.getElementById('btnRemoveCoupon'),
            // Form element
            orderForm: document.querySelector('.order-form-card'),
            // Order section
            productOrderSection: document.querySelector('.product-order-section')
        };
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Plan card click
        if (elements.planCards) {
            elements.planCards.forEach(card => {
                card.addEventListener('click', handlePlanClick);
            });
        }

        // Quantity buttons
        if (elements.qtyMinus) {
            elements.qtyMinus.addEventListener('click', () => adjustQuantity(-1));
        }
        if (elements.qtyPlus) {
            elements.qtyPlus.addEventListener('click', () => adjustQuantity(1));
        }

        // Quantity input change
        if (elements.qtyInput) {
            elements.qtyInput.addEventListener('change', handleQuantityChange);
            elements.qtyInput.addEventListener('input', handleQuantityChange);
        }

        // Submit order button
        if (elements.btnSubmitOrder) {
            elements.btnSubmitOrder.addEventListener('click', handleSubmitOrder);
        }

        // Favorite button
        if (elements.favoriteBtn) {
            elements.favoriteBtn.addEventListener('click', handleFavoriteClick);
        }

        // Add to cart button
        if (elements.btnAddToCart) {
            elements.btnAddToCart.addEventListener('click', handleAddToCart);
        }

        // Coupon toggle
        if (elements.couponToggle) {
            elements.couponToggle.addEventListener('click', handleCouponToggle);
        }

        // Apply coupon button
        if (elements.btnApplyCoupon) {
            elements.btnApplyCoupon.addEventListener('click', handleApplyCoupon);
        }

        // Coupon input - enter key
        if (elements.couponCodeInput) {
            elements.couponCodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleApplyCoupon();
                }
            });
        }

        // Remove coupon button
        if (elements.btnRemoveCoupon) {
            elements.btnRemoveCoupon.addEventListener('click', handleRemoveCoupon);
        }

        // Prevent form submit (we handle submit via AJAX)
        if (elements.orderForm) {
            elements.orderForm.addEventListener('submit', function(e) {
                e.preventDefault();
                // If user presses Enter in input field, trigger submit button click
                if (elements.btnSubmitOrder && !elements.btnSubmitOrder.disabled) {
                    handleSubmitOrder();
                }
            });
        }

        // Collapse toggle handlers
        const collapseToggles = document.querySelectorAll('.collapse-toggle');
        collapseToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const target = document.getElementById(targetId);
                if (target) {
                    const isCollapsed = target.classList.contains('collapsed');
                    if (isCollapsed) {
                        // Expand
                        target.classList.remove('collapsed');
                        this.classList.remove('collapsed');
                    } else {
                        // Collapse
                        target.classList.add('collapsed');
                        this.classList.add('collapsed');
                    }
                }
            });
        });

        // Write review modal handler
        const writeReviewToggle = document.getElementById('writeReviewToggle');
        const writeReviewModal = document.getElementById('writeReviewModal');
        if (writeReviewToggle && writeReviewModal) {
            writeReviewToggle.addEventListener('click', function() {
                writeReviewModal.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }

        // Global modal close function
        window.closeReviewModal = function() {
            const modal = document.getElementById('writeReviewModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        };

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeReviewModal();
            }
        });
    }

    /**
     * Handle plan card click
     */
    function handlePlanClick(e) {
        const card = e.currentTarget;
        const planId = parseInt(card.getAttribute('data-plan-id'));
        selectPlan(planId);
    }

    /**
     * Check if device is mobile
     */
    function isMobileDevice() {
        return window.innerWidth <= 768;
    }

    /**
     * Scroll to plan description (desktop only)
     */
    function scrollToPlanDescription() {
        // Không cuộn khi mới load trang
        if (state.isInitialLoad) return;
        
        // Chỉ cuộn trên desktop
        if (isMobileDevice()) return;
        
        const targetElement = elements.selectedPlanDescription;
        if (!targetElement) return;
        
        // Wait a bit for content to update
        setTimeout(() => {
            // Get element position
            const rect = targetElement.getBoundingClientRect();
            const elementTop = rect.top + window.pageYOffset;
            
            // Thêm offset để header không che
            const headerOffset = 100;
            const scrollPosition = elementTop - headerOffset;
            
            window.scrollTo({
                top: Math.max(0, scrollPosition),
                behavior: 'smooth'
            });
        }, 100);
    }

    /**
     * Scroll to order form (mobile only)
     */
    function scrollToOrderForm() {
        // Không cuộn khi mới load trang - để user xem danh sách gói trước
        if (state.isInitialLoad) return;
        
        if (!isMobileDevice()) return;
        
        // Wait a bit for fields to load and render
        setTimeout(() => {
            const targetElement = elements.productOrderSection || elements.orderForm;
            if (!targetElement) return;
            
            // Force reflow to get accurate measurements
            void targetElement.offsetHeight;
            
            // Get element position and dimensions
            const rect = targetElement.getBoundingClientRect();
            const elementTop = rect.top + window.pageYOffset;
            const elementHeight = rect.height;
            
            // Calculate scroll position to center the element on screen
            const viewportHeight = window.innerHeight;
            // Add small offset to scroll a bit more
            const extraOffset = 100;
            const scrollPosition = elementTop - (viewportHeight / 2) + (elementHeight / 2) - extraOffset;
            
            window.scrollTo({
                top: Math.max(0, scrollPosition), // Ensure not negative
                behavior: 'smooth'
            });
        }, 200); // Increased delay to ensure content is fully rendered
    }

    /**
     * Select a plan
     */
    function selectPlan(planId) {
        // Find plan data
        const plan = PRODUCT_PLANS.find(p => p.id === planId);
        if (!plan) return;

        state.selectedPlanId = planId;
        state.selectedPlan = plan;

        // Update UI - remove active from all cards
        elements.planCards.forEach(card => {
            card.classList.remove('active');
            if (parseInt(card.getAttribute('data-plan-id')) === planId) {
                card.classList.add('active');
            }
        });

        // Update plan description
        updatePlanDescription(plan);

        // Scroll to plan description on desktop
        scrollToPlanDescription();

        // Load plan fields
        loadPlanFields(planId);

        // Reset coupon UI when plan changes (need to re-validate)
        resetCouponUI();

        // Update price display
        updatePriceDisplay();

        // Update stock info
        updateStockInfo();

        // Update main image if plan has custom image
        if (plan.image && elements.productMainImage) {
            elements.productMainImage.src = base_url(plan.image);
        }

        // Note: Scroll to order form is handled in loadPlanFields after fields are rendered
    }

    /**
     * Reset coupon UI (keep state for re-validation if coupon exists)
     */
    function resetCouponUI() {
        // Only reset UI, don't clear state - let recalculateCoupon handle validation
        if (state.couponCode) {
            // Coupon exists, hide input, show applied (will be validated by recalculateCoupon)
            const inputGroup = document.querySelector('.coupon-input-group');
            if (inputGroup) {
                inputGroup.style.display = 'none';
            }
        } else {
            // No coupon, show input
            const inputGroup = document.querySelector('.coupon-input-group');
            if (inputGroup) {
                inputGroup.style.display = 'flex';
            }
            
            // Hide applied coupon
            if (elements.appliedCoupon) {
                elements.appliedCoupon.style.display = 'none';
            }
        }
        
        // Clear message
        showCouponMessage('', '');
    }

    /**
     * Update plan description display
     */
    function updatePlanDescription(plan) {
        if (!elements.selectedPlanDescription || !elements.selectedPlanName || !elements.selectedPlanDescriptionContent) {
            return;
        }

        // Update plan name
        if (elements.selectedPlanName) {
            elements.selectedPlanName.textContent = plan.name || '';
        }

        // Update description content
        if (plan.description && plan.description.trim()) {
            elements.selectedPlanDescriptionContent.innerHTML = plan.description;
            elements.selectedPlanDescription.style.display = 'block';
        } else {
            elements.selectedPlanDescriptionContent.innerHTML = '<p class="text-muted">' + (typeof TRANSLATIONS !== 'undefined' && TRANSLATIONS.noPlanDescription ? TRANSLATIONS.noPlanDescription : 'Gói này chưa có mô tả') + '</p>';
            elements.selectedPlanDescription.style.display = 'block';
        }
    }

    /**
     * Load plan fields via AJAX
     */
    function loadPlanFields(planId) {
        if (!elements.orderFieldsContainer) return;

        // Show skeleton loading
        elements.orderFieldsContainer.innerHTML = `
            <div class="order-fields-skeleton">
                <div class="skeleton-field">
                    <div class="skeleton-input shimmer"></div>
                </div>
                <div class="skeleton-field">
                    <div class="skeleton-input shimmer"></div>
                </div>
            </div>
        `;

        // AJAX request to get plan fields
        const formData = new FormData();
        formData.append('action', 'getPlanFields');
        formData.append('plan_id', planId);
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/view.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderPlanFields(data.data);
                // Scroll to order form on mobile after fields are rendered
                scrollToOrderForm();
            } else {
                elements.orderFieldsContainer.innerHTML = '<p class="no-fields-message">' + (TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi') + '</p>';
                // Still scroll even if no fields
                scrollToOrderForm();
            }
        })
        .catch(error => {
            console.error('Error loading fields:', error);
            elements.orderFieldsContainer.innerHTML = '<p class="no-fields-message">' + (TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi') + '</p>';
            // Still scroll on error
            scrollToOrderForm();
        });
    }

    /**
     * Render plan fields
     */
    function renderPlanFields(fields) {
        if (!elements.orderFieldsContainer) return;

        if (!fields || fields.length === 0) {
            elements.orderFieldsContainer.innerHTML = '<p class="no-fields-message">Không có trường thông tin nào cần điền</p>';
            return;
        }

        let html = '';
        fields.forEach(field => {
            const label = field.label || field.field_key;
            const placeholder = label + (field.is_required == 1 ? ' *' : '');
            const required = field.is_required == 1 ? 'required' : '';
            
            // Xác định autocomplete value dựa trên field type
            let autocompleteValue = 'off';
            if (field.type === 'password') {
                autocompleteValue = 'new-password';
            } else {
                autocompleteValue = 'one-time-code';
            }

            html += '<div class="order-field">';
            
            if (field.type === 'textarea') {
                html += `<textarea 
                    name="field_${escapeHtml(field.field_key)}" 
                    id="field_${escapeHtml(field.field_key)}"
                    class="order-input" 
                    placeholder="${escapeHtml(placeholder)}"
                    ${required}
                    rows="3"
                    autocomplete="${autocompleteValue}"
                    data-lpignore="true"
                    data-form-type="other"
                ></textarea>`;
            } else {
                html += `<input 
                    type="${escapeHtml(field.type)}" 
                    name="field_${escapeHtml(field.field_key)}" 
                    id="field_${escapeHtml(field.field_key)}"
                    class="order-input" 
                    placeholder="${escapeHtml(placeholder)}"
                    ${required}
                    autocomplete="${autocompleteValue}"
                    data-lpignore="true"
                    data-form-type="other"
                >`;
            }
            
            html += '</div>';
        });

        elements.orderFieldsContainer.innerHTML = html;
    }

    /**
     * Adjust quantity
     */
    function adjustQuantity(delta) {
        const newQty = state.quantity + delta;
        
        // Validate
        if (newQty < 1) return;
        if (newQty > 1000000) return;

        // Check stock for instant delivery
        if (state.selectedPlan && state.selectedPlan.is_instant === 1) {
            if (newQty > state.selectedPlan.stock_count) {
                showMessage(TRANSLATIONS.outOfStock || 'Số lượng vượt quá kho hàng', 'warning');
                return;
            }
        }

        state.quantity = newQty;
        if (elements.qtyInput) {
            elements.qtyInput.value = newQty;
        }

        updatePriceDisplay();
    }

    /**
     * Handle quantity input change
     */
    function handleQuantityChange(e) {
        let newQty = parseInt(e.target.value) || 1;
        
        // Validate range
        if (newQty < 1) newQty = 1;
        if (newQty > 1000000) newQty = 1000000;

        // Check stock for instant delivery
        if (state.selectedPlan && state.selectedPlan.is_instant === 1) {
            if (newQty > state.selectedPlan.stock_count) {
                newQty = state.selectedPlan.stock_count;
                showMessage(TRANSLATIONS.outOfStock || 'Số lượng vượt quá kho hàng', 'warning');
            }
        }

        state.quantity = newQty;
        e.target.value = newQty;

        updatePriceDisplay();
    }

    /**
     * Update price display
     */
    /**
     * Update price display via AJAX (real-time pricing)
     */
    function updatePriceDisplay() {
        if (!state.selectedPlan) return;

        const planId = state.selectedPlanId;
        const quantity = state.quantity;

        // Show loading state
        if (elements.totalPrice) {
            elements.totalPrice.classList.add('loading');
        }

        // Call AJAX to get real-time pricing
        const formData = new FormData();
        formData.append('action', 'getPlanPricing');
        formData.append('plan_id', planId);
        formData.append('quantity', quantity);

        fetch(base_url('ajaxs/client/view.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (elements.totalPrice) {
                elements.totalPrice.classList.remove('loading');
            }

            if (data.status === 'success' && data.data[planId]) {
                const pricing = data.data[planId];
                
                // Update plan's cached prices
                state.selectedPlan.flash_price = pricing.flash_price;
                state.selectedPlan.final_price = pricing.final_price;
                state.selectedPlan.stock_count = pricing.stock_count;
                
                // Update DOM with server-calculated values
                if (elements.originalPrice) {
                    elements.originalPrice.textContent = pricing.subtotal_formatted;
                }

                // Show/hide Member Discount row (Ưu đãi thành viên)
                if (pricing.user_discount_percent > 0 && pricing.user_discount_amount > 0) {
                    if (elements.memberDiscountRow) {
                        elements.memberDiscountRow.style.display = '';
                    }
                    if (elements.memberDiscountPercent) {
                        elements.memberDiscountPercent.textContent = pricing.user_discount_percent;
                    }
                    if (elements.memberDiscountAmount) {
                        elements.memberDiscountAmount.textContent = '-' + formatCurrency(pricing.user_discount_amount * pricing.quantity);
                    }
                } else {
                    if (elements.memberDiscountRow) {
                        elements.memberDiscountRow.style.display = 'none';
                    }
                }

                // Show/hide Flash Sale row
                if (pricing.has_flash_sale && pricing.flash_discount_amount > 0) {
                    if (elements.flashSaleRow) {
                        elements.flashSaleRow.style.display = '';
                    }
                    if (elements.flashSaleAmount) {
                        elements.flashSaleAmount.textContent = '-' + formatCurrency(pricing.flash_discount_amount * pricing.quantity);
                    }
                    // Hide regular sale row (Flash Sale và Sale không cùng hiển thị)
                    if (elements.saleRow) {
                        elements.saleRow.style.display = 'none';
                    }
                    // KHÔNG ẩn memberDiscountRow - cả user discount và flash sale hiển thị cùng nhau
                } else {
                    // Hide flash sale row
                    if (elements.flashSaleRow) {
                        elements.flashSaleRow.style.display = 'none';
                    }
                    // Show regular sale row if has sale discount
                    if (pricing.sale_discount_amount > 0) {
                        if (elements.saleRow) {
                            elements.saleRow.style.display = '';
                        }
                        if (elements.saleAmount) {
                            elements.saleAmount.textContent = '-' + formatCurrency(pricing.sale_discount_amount * pricing.quantity);
                        }
                    } else {
                        if (elements.saleRow) {
                            elements.saleRow.style.display = 'none';
                        }
                    }
                }

                // If coupon is applied, recalculate
                if (state.couponCode) {
                    recalculateCoupon();
                } else {
                    // No coupon, use server total
                    if (elements.totalPrice) {
                        elements.totalPrice.textContent = pricing.total_formatted;
                    }
                    // Hide coupon discount row
                    if (elements.couponDiscountRow) {
                        elements.couponDiscountRow.style.display = 'none';
                    }
                }
                
                // Update stock info
                if (elements.stockCount && pricing.is_instant) {
                    elements.stockCount.textContent = pricing.stock_count;
                }
            } else {
                // Fallback to client-side calculation
                updatePriceDisplayFallback();
            }
        })
        .catch(error => {
            console.error('Error fetching pricing:', error);
            if (elements.totalPrice) {
                elements.totalPrice.classList.remove('loading');
            }
            // Fallback to client-side calculation
            updatePriceDisplayFallback();
        });
    }

    /**
     * Fallback price display (client-side calculation)
     */
    function updatePriceDisplayFallback() {
        if (!state.selectedPlan) return;

        const plan = state.selectedPlan;
        const price = plan.price;
        const salePrice = plan.sale_price;
        const flashPrice = plan.flash_price || 0;
        
        // Ưu tiên flash_price nếu có, sau đó sale_price
        let finalPrice = price;
        let hasSale = false;
        
        if (flashPrice > 0 && flashPrice < price) {
            finalPrice = flashPrice;
            hasSale = true;
        } else if (salePrice > 0 && salePrice < price) {
            finalPrice = salePrice;
            hasSale = true;
        }
        
        const quantity = state.quantity;

        // Calculate totals
        const originalTotal = price * quantity;
        const finalTotal = finalPrice * quantity;
        const discountTotal = originalTotal - finalTotal;

        // Update DOM
        if (elements.originalPrice) {
            elements.originalPrice.textContent = formatCurrency(originalTotal);
        }

        // Show/hide Flash Sale row vs Sale row
        if (flashPrice > 0 && flashPrice < price) {
            // Has Flash Sale
            const flashDiscount = (price - flashPrice) * quantity;
            if (elements.flashSaleRow) {
                elements.flashSaleRow.style.display = '';
            }
            if (elements.flashSaleAmount) {
                elements.flashSaleAmount.textContent = '-' + formatCurrency(flashDiscount);
            }
            if (elements.saleRow) {
                elements.saleRow.style.display = 'none';
            }
        } else if (salePrice > 0 && salePrice < price) {
            // Has regular Sale
            const saleDiscount = (price - salePrice) * quantity;
            if (elements.flashSaleRow) {
                elements.flashSaleRow.style.display = 'none';
            }
            if (elements.saleRow) {
                elements.saleRow.style.display = '';
            }
            if (elements.saleAmount) {
                elements.saleAmount.textContent = '-' + formatCurrency(saleDiscount);
            }
        } else {
            // No sale
            if (elements.flashSaleRow) {
                elements.flashSaleRow.style.display = 'none';
            }
            if (elements.saleRow) {
                elements.saleRow.style.display = 'none';
            }
        }

        // If coupon is applied, recalculate
        if (state.couponCode) {
            recalculateCoupon();
        } else {
            // No coupon, just update total
            if (elements.totalPrice) {
                elements.totalPrice.textContent = formatCurrency(finalTotal);
            }
            // Hide coupon discount row
            if (elements.couponDiscountRow) {
                elements.couponDiscountRow.style.display = 'none';
            }
        }
    }

    /**
     * Update stock info
     */
    function updateStockInfo() {
        if (!elements.stockInfo || !state.selectedPlan) return;

        const plan = state.selectedPlan;
        
        if (plan.is_instant === 1) {
            elements.stockInfo.style.display = '';
            if (elements.stockCount) {
                elements.stockCount.textContent = plan.stock_count;
            }
        } else {
            elements.stockInfo.style.display = 'none';
        }
    }

    /**
     * Handle submit order
     */
    function handleSubmitOrder() {
        if (state.isSubmitting) return;

        // Validate
        if (!state.selectedPlanId) {
            showMessage(TRANSLATIONS.selectPlan || 'Vui lòng chọn gói sản phẩm', 'warning');
            return;
        }

        // Check stock for instant delivery
        if (state.selectedPlan && state.selectedPlan.is_instant === 1) {
            if (state.quantity > state.selectedPlan.stock_count) {
                showMessage(TRANSLATIONS.outOfStock || 'Số lượng vượt quá kho hàng', 'error');
                return;
            }
        }

        // Validate required fields
        const requiredFields = document.querySelectorAll('#orderFieldsContainer [required]');
        let isValid = true;
        let firstInvalidField = null;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
                field.addEventListener('input', function() {
                    this.classList.remove('error');
                }, { once: true });
            }
        });

        if (!isValid) {
            showMessage('Vui lòng điền đầy đủ thông tin bắt buộc', 'warning');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            return;
        }

        // Create cart item and redirect to checkout
        const cartItem = createCartItem();
        if (!cartItem) return;

        // Add to cart
        addToCart(cartItem, false); // false = không hiển thị message

        // Redirect to cart page
        window.location.href = base_url('cart');
    }

    /**
     * Create cart item from current form data
     * @returns {Object|null} Cart item object or null if invalid
     */
    function createCartItem() {
        // Collect field values
        const fieldInputs = document.querySelectorAll('#orderFieldsContainer .order-input');
        const fieldData = {};
        const fieldLabels = {};
        
        fieldInputs.forEach(input => {
            const name = input.name.replace('field_', '');
            const value = input.value.trim();
            const placeholder = input.placeholder || name;
            // Remove * from placeholder to get clean label
            const label = placeholder.replace(/\s*\*\s*$/, '');
            
            if (value) {
                fieldData[name] = value;
                fieldLabels[name] = label;
            }
        });

        // Get current product info
        const productName = document.querySelector('.product-hero-title')?.textContent || '';
        const productImage = elements.productMainImage?.src || '';
        const plan = state.selectedPlan;
        
        if (!plan) return null;

        // Calculate price - ưu tiên flash_price nếu có
        const price = plan.price;
        const salePrice = plan.sale_price;
        const flashPrice = plan.flash_price || 0;
        
        let finalPrice = price;
        if (flashPrice > 0 && flashPrice < price) {
            finalPrice = flashPrice;
        } else if (salePrice > 0 && salePrice < price) {
            finalPrice = salePrice;
        }

        return {
            id: Date.now(), // Unique ID for cart item
            product_id: PRODUCT_ID,
            product_slug: PRODUCT_SLUG,
            product_name: productName,
            product_image: productImage,
            plan_id: plan.id,
            plan_name: plan.name,
            price: price,
            sale_price: salePrice,
            final_price: finalPrice,
            quantity: state.quantity,
            is_instant: plan.is_instant,
            stock_count: plan.stock_count,
            fields: fieldData,
            field_labels: fieldLabels,
            added_at: new Date().toISOString()
        };
    }

    /**
     * Handle coupon toggle
     */
    function handleCouponToggle() {
        if (!elements.couponToggle || !elements.couponFormWrapper) return;

        const isOpen = elements.couponFormWrapper.classList.contains('show');
        
        if (isOpen) {
            elements.couponFormWrapper.classList.remove('show');
            elements.couponToggle.classList.remove('active');
        } else {
            elements.couponFormWrapper.classList.add('show');
            elements.couponToggle.classList.add('active');
            // Focus on input
            if (elements.couponCodeInput) {
                setTimeout(() => elements.couponCodeInput.focus(), 100);
            }
        }
    }

    /**
     * Handle apply coupon
     */
    function handleApplyCoupon() {
        if (state.isApplyingCoupon) return;

        const couponCode = elements.couponCodeInput ? elements.couponCodeInput.value.trim() : '';
        
        if (!couponCode) {
            showCouponMessage(TRANSLATIONS.enterCouponCode || 'Vui lòng nhập mã giảm giá', 'error');
            return;
        }

        if (!state.selectedPlanId) {
            showCouponMessage(TRANSLATIONS.selectPlan || 'Vui lòng chọn gói sản phẩm trước', 'error');
            return;
        }

        // Calculate order total (before coupon) - ưu tiên flash_price
        const plan = state.selectedPlan;
        if (!plan) return;

        const price = plan.price;
        const salePrice = plan.sale_price;
        const flashPrice = plan.flash_price || 0;
        
        let finalPrice = price;
        if (flashPrice > 0 && flashPrice < price) {
            finalPrice = flashPrice;
        } else if (salePrice > 0 && salePrice < price) {
            finalPrice = salePrice;
        }
        const orderTotal = finalPrice * state.quantity;

        // Show loading
        state.isApplyingCoupon = true;
        if (elements.btnApplyCoupon) {
            elements.btnApplyCoupon.disabled = true;
            elements.btnApplyCoupon.textContent = TRANSLATIONS.applyingCoupon || 'Đang áp dụng...';
        }
        showCouponMessage('', '');

        // Call API
        const formData = new FormData();
        formData.append('action', 'applyCouponToOrder');
        formData.append('coupon_code', couponCode);
        formData.append('product_id', PRODUCT_ID);
        formData.append('plan_id', state.selectedPlanId);
        formData.append('quantity', state.quantity);
        formData.append('order_total', orderTotal);
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/view.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            state.isApplyingCoupon = false;
            if (elements.btnApplyCoupon) {
                elements.btnApplyCoupon.disabled = false;
                elements.btnApplyCoupon.textContent = 'Áp dụng';
            }

            if (data.status === 'success') {
                // Save coupon data
                state.couponCode = couponCode.toUpperCase();
                state.couponData = data.coupon;
                state.couponDiscount = data.discount_amount;

                // Show applied coupon
                showAppliedCoupon(data);
                
                // Update price display
                updatePriceDisplayWithCoupon(data);

                showCouponMessage(TRANSLATIONS.couponApplied || 'Áp dụng mã giảm giá thành công', 'success');
            } else {
                showCouponMessage(data.msg || TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
            }
        })
        .catch(error => {
            state.isApplyingCoupon = false;
            if (elements.btnApplyCoupon) {
                elements.btnApplyCoupon.disabled = false;
                elements.btnApplyCoupon.textContent = 'Áp dụng';
            }
            console.error('Error applying coupon:', error);
            showCouponMessage(TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
        });
    }

    /**
     * Handle remove coupon
     */
    function handleRemoveCoupon() {
        // Reset coupon state
        state.couponCode = '';
        state.couponData = null;
        state.couponDiscount = 0;

        // Hide applied coupon
        if (elements.appliedCoupon) {
            elements.appliedCoupon.style.display = 'none';
        }

        // Show input group
        const inputGroup = document.querySelector('.coupon-input-group');
        if (inputGroup) {
            inputGroup.style.display = 'flex';
        }

        // Clear input
        if (elements.couponCodeInput) {
            elements.couponCodeInput.value = '';
        }

        // Hide coupon discount row
        if (elements.couponDiscountRow) {
            elements.couponDiscountRow.style.display = 'none';
        }

        // Update total price without coupon
        if (state.selectedPlan) {
            const plan = state.selectedPlan;
            const price = plan.price;
            const salePrice = plan.sale_price;
            const flashPrice = plan.flash_price || 0;
            
            let finalPrice = price;
            if (flashPrice > 0 && flashPrice < price) {
                finalPrice = flashPrice;
            } else if (salePrice > 0 && salePrice < price) {
                finalPrice = salePrice;
            }
            const finalTotal = finalPrice * state.quantity;

            if (elements.totalPrice) {
                elements.totalPrice.textContent = formatCurrency(finalTotal);
            }
        }

        showCouponMessage(TRANSLATIONS.couponRemoved || 'Đã xóa mã giảm giá', 'success');
    }

    /**
     * Show coupon message
     */
    function showCouponMessage(message, type) {
        if (!elements.couponMessage) return;
        
        elements.couponMessage.textContent = message;
        elements.couponMessage.className = 'coupon-message';
        if (type) {
            elements.couponMessage.classList.add(type);
        }
    }

    /**
     * Show applied coupon info
     */
    function showAppliedCoupon(data) {
        if (!elements.appliedCoupon) return;

        if (elements.appliedCouponCode) {
            elements.appliedCouponCode.textContent = data.coupon.code;
        }

        if (elements.appliedCouponDesc) {
            let desc = '';
            if (data.coupon.type === 'percentage') {
                desc = '(-' + data.coupon.value + '%)';
            } else {
                desc = '(-' + formatCurrency(data.coupon.value) + ')';
            }
            elements.appliedCouponDesc.textContent = desc;
        }

        elements.appliedCoupon.style.display = 'flex';

        // Hide input group, show applied coupon
        const inputGroup = document.querySelector('.coupon-input-group');
        if (inputGroup) {
            inputGroup.style.display = 'none';
        }
    }

    /**
     * Update price display with coupon
     */
    function updatePriceDisplayWithCoupon(data) {
        if (!state.selectedPlan) return;

        // Show coupon discount row
        if (elements.couponDiscountRow) {
            elements.couponDiscountRow.style.display = '';
        }

        if (elements.couponDiscountAmount) {
            elements.couponDiscountAmount.textContent = '-' + formatCurrency(data.discount_amount);
        }

        // Update total price
        if (elements.totalPrice) {
            elements.totalPrice.textContent = formatCurrency(data.final_amount);
        }
    }

    /**
     * Recalculate coupon when plan/quantity changes
     */
    function recalculateCoupon() {
        if (!state.couponCode || !state.selectedPlan) {
            // Reset coupon if no code or plan
            state.couponDiscount = 0;
            if (elements.couponDiscountRow) {
                elements.couponDiscountRow.style.display = 'none';
            }
            return;
        }

        // Recalculate order total - ưu tiên flash_price
        const plan = state.selectedPlan;
        const price = plan.price;
        const salePrice = plan.sale_price;
        const flashPrice = plan.flash_price || 0;
        
        let finalPrice = price;
        let hasSale = false;
        
        if (flashPrice > 0 && flashPrice < price) {
            finalPrice = flashPrice;
            hasSale = true;
        } else if (salePrice > 0 && salePrice < price) {
            finalPrice = salePrice;
            hasSale = true;
        }
        const orderTotal = finalPrice * state.quantity;

        // Call API to recalculate
        const formData = new FormData();
        formData.append('action', 'applyCouponToOrder');
        formData.append('coupon_code', state.couponCode);
        formData.append('product_id', PRODUCT_ID);
        formData.append('plan_id', state.selectedPlanId);
        formData.append('quantity', state.quantity);
        formData.append('order_total', orderTotal);
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/view.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                state.couponDiscount = data.discount_amount;
                state.couponData = data.coupon;
                
                // Show applied coupon UI
                showAppliedCoupon(data);
                updatePriceDisplayWithCoupon(data);
            } else {
                // Coupon no longer valid for this plan/quantity
                clearCouponState();
                showCouponMessage(data.msg || 'Mã giảm giá không áp dụng được cho gói/số lượng này', 'error');
            }
        })
        .catch(error => {
            console.error('Error recalculating coupon:', error);
        });
    }

    /**
     * Clear coupon state without showing removal message
     */
    function clearCouponState() {
        state.couponCode = '';
        state.couponData = null;
        state.couponDiscount = 0;

        // Hide applied coupon
        if (elements.appliedCoupon) {
            elements.appliedCoupon.style.display = 'none';
        }

        // Show input group
        const inputGroup = document.querySelector('.coupon-input-group');
        if (inputGroup) {
            inputGroup.style.display = 'flex';
        }

        // Clear input
        if (elements.couponCodeInput) {
            elements.couponCodeInput.value = '';
        }

        // Hide coupon discount row
        if (elements.couponDiscountRow) {
            elements.couponDiscountRow.style.display = 'none';
        }

        // Update total price without coupon
        if (state.selectedPlan) {
            const plan = state.selectedPlan;
            const price = plan.price;
            const salePrice = plan.sale_price;
            const hasSale = salePrice > 0 && salePrice < price;
            const finalPrice = hasSale ? salePrice : price;
            const finalTotal = finalPrice * state.quantity;

            if (elements.totalPrice) {
                elements.totalPrice.textContent = formatCurrency(finalTotal);
            }
        }
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        // Get currency config from global variable (set by PHP)
        const currency = window.CURRENCY_CONFIG || {
            symbol_left: "",
            symbol_right: " đ",
            rate: 1,
            decimal: 0,
            seperator: "dot"
        };
        
        // Convert amount by currency rate
        const convertedAmount = amount / currency.rate;
        
        // Determine thousand separator
        let thousandSep = ".";
        if (currency.seperator === "comma") {
            thousandSep = ",";
        } else if (currency.seperator === "space") {
            thousandSep = " ";
        }
        
        // Format number with proper decimals and separator
        const parts = convertedAmount.toFixed(currency.decimal).split(".");
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
        const formattedNumber = currency.decimal > 0 ? parts.join(".") : parts[0];
        
        return currency.symbol_left + formattedNumber + currency.symbol_right;
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        // Map warning to error for Notify (only supports success/error)
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
        } else {
            alert(message);
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
     * Get CSRF token from meta tag
     */
    function getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /**
     * Get user token from hidden input
     */
    function getUserToken() {
        const tokenInput = document.getElementById('userToken');
        return tokenInput ? tokenInput.value : null;
    }

    /**
     * Check if user is logged in
     */
    function isUserLoggedIn() {
        const token = getUserToken();
        return token !== null && token.trim() !== '';
    }

    /**
     * Handle add to cart button click
     */
    function handleAddToCart(e) {
        e.preventDefault();
        
        // Validate plan selection
        if (!state.selectedPlanId) {
            showMessage(TRANSLATIONS.selectPlan || 'Vui lòng chọn gói sản phẩm', 'warning');
            return;
        }

        // Check stock for instant delivery
        if (state.selectedPlan && state.selectedPlan.is_instant === 1) {
            if (state.quantity > state.selectedPlan.stock_count) {
                showMessage(TRANSLATIONS.outOfStock || 'Số lượng vượt quá kho hàng', 'error');
                return;
            }
        }

        // Validate required fields
        const requiredFields = document.querySelectorAll('#orderFieldsContainer [required]');
        let isValid = true;
        let firstInvalidField = null;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
                if (!firstInvalidField) {
                    firstInvalidField = field;
                }
                field.addEventListener('input', function() {
                    this.classList.remove('error');
                }, { once: true });
            }
        });

        if (!isValid) {
            showMessage('Vui lòng điền đầy đủ thông tin bắt buộc', 'warning');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
            return;
        }

        // Create cart item and add to cart
        const cartItem = createCartItem();
        if (!cartItem) return;

        // Add to cart (LocalStorage) with message
        addToCart(cartItem, true);
    }

    /**
     * Get cart from LocalStorage
     */
    function getCart() {
        try {
            const cart = localStorage.getItem('shopkey_cart');
            return cart ? JSON.parse(cart) : [];
        } catch (e) {
            console.error('Error reading cart:', e);
            return [];
        }
    }

    /**
     * Save cart to LocalStorage
     */
    function saveCart(cart) {
        try {
            localStorage.setItem('shopkey_cart', JSON.stringify(cart));
            // Dispatch event for nav to update
            window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { cart: cart } }));
        } catch (e) {
            console.error('Error saving cart:', e);
        }
    }

    /**
     * Add item to cart
     * @param {Object} item - Cart item to add
     * @param {boolean} showMsg - Whether to show success message (default: true)
     */
    function addToCart(item, showMsg = true) {
        const cart = getCart();
        
        // Check if same product + plan + fields already exists
        const existingIndex = cart.findIndex(cartItem => {
            if (cartItem.product_id !== item.product_id || cartItem.plan_id !== item.plan_id) {
                return false;
            }
            // Compare fields - must be exactly the same
            const existingFields = JSON.stringify(cartItem.fields || {});
            const newFields = JSON.stringify(item.fields || {});
            return existingFields === newFields;
        });

        if (existingIndex >= 0) {
            // Update quantity
            cart[existingIndex].quantity += item.quantity;
            cart[existingIndex].added_at = item.added_at;
            if (showMsg) {
                showMessage(TRANSLATIONS.cartUpdated || 'Đã cập nhật giỏ hàng', 'success');
            }
        } else {
            // Add new item
            cart.push(item);
            if (showMsg) {
                showMessage(TRANSLATIONS.addedToCart || 'Đã thêm vào giỏ hàng', 'success');
            }
        }

        saveCart(cart);
        
        // Save coupon to cart if applied
        saveCouponToCart();
        
        // Update cart count in nav
        updateCartCount();
        
        // Fly animation to cart icon
        flyToCart();
    }

    /**
     * Fly to cart animation
     * Tạo hiệu ứng icon bay từ nút thêm giỏ hàng đến icon giỏ hàng trên nav
     */
    function flyToCart() {
        const addToCartBtn = elements.btnAddToCart;
        const cartIcon = document.querySelector('.header-widget[href*="cart"]') || document.querySelector('a[href*="cart"] i.fa-cart-shopping')?.parentElement;
        
        if (!addToCartBtn || !cartIcon) return;
        
        // Get positions
        const btnRect = addToCartBtn.getBoundingClientRect();
        const cartRect = cartIcon.getBoundingClientRect();
        
        // Create flying element
        const flyingEl = document.createElement('div');
        flyingEl.className = 'flying-cart-icon';
        flyingEl.innerHTML = '<i class="fa-solid fa-cart-shopping"></i>';
        
        // Set initial position (center of button)
        flyingEl.style.cssText = `
            position: fixed;
            z-index: 99999;
            left: ${btnRect.left + btnRect.width / 2}px;
            top: ${btnRect.top + btnRect.height / 2}px;
            width: 40px;
            height: 40px;
            background: var(--primary, #3b82f6);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            pointer-events: none;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            transform: translate(-50%, -50%) scale(1);
            transition: none;
        `;
        
        document.body.appendChild(flyingEl);
        
        // Calculate end position (center of cart icon)
        const endX = cartRect.left + cartRect.width / 2;
        const endY = cartRect.top + cartRect.height / 2;
        
        // Animate with requestAnimationFrame for smoother effect
        const startX = btnRect.left + btnRect.width / 2;
        const startY = btnRect.top + btnRect.height / 2;
        const duration = 600; // ms
        const startTime = performance.now();
        
        function animate(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function (ease-out cubic)
            const easeProgress = 1 - Math.pow(1 - progress, 3);
            
            // Current position with parabolic curve
            const currentX = startX + (endX - startX) * easeProgress;
            // Add arc effect (parabola)
            const arcHeight = -80 * Math.sin(progress * Math.PI);
            const currentY = startY + (endY - startY) * easeProgress + arcHeight;
            
            // Scale down as it flies
            const scale = 1 - (progress * 0.5);
            
            flyingEl.style.left = currentX + 'px';
            flyingEl.style.top = currentY + 'px';
            flyingEl.style.transform = `translate(-50%, -50%) scale(${scale})`;
            flyingEl.style.opacity = 1 - (progress * 0.3);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                // Animation complete
                flyingEl.remove();
                
                // Bounce effect on cart icon
                cartIcon.classList.add('cart-bounce');
                setTimeout(() => {
                    cartIcon.classList.remove('cart-bounce');
                }, 500);
                
                // Flash effect on cart badge
                const badge = cartIcon.querySelector('sup, .cart-badge, #numCart');
                if (badge) {
                    badge.classList.add('cart-badge-flash');
                    setTimeout(() => {
                        badge.classList.remove('cart-badge-flash');
                    }, 500);
                }
            }
        }
        
        requestAnimationFrame(animate);
    }

    /**
     * Save applied coupon to localStorage for cart page
     */
    function saveCouponToCart() {
        if (state.couponCode && state.couponData) {
            const couponData = {
                code: state.couponCode,
                data: state.couponData,
                discount: state.couponDiscount,
                saved_at: new Date().toISOString()
            };
            localStorage.setItem('shopkey_cart_coupon', JSON.stringify(couponData));
        }
    }

    /**
     * Update cart count in navigation
     */
    function updateCartCount() {
        const cart = getCart();
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        const cartCountEl = document.getElementById('numCart');
        
        if (cartCountEl) {
            cartCountEl.textContent = totalItems;
            cartCountEl.style.display = totalItems > 0 ? '' : 'none';
        }
    }

    /**
     * Handle favorite button click
     */
    function handleFavoriteClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const btn = elements.favoriteBtn;
        if (!btn) return;

        const productId = btn.getAttribute('data-product-id');
        if (!productId) return;

        // Check if user is logged in
        const userToken = getUserToken();
        if (!userToken) {
            const loginUrl = base_url('client/login?redirect=' + encodeURIComponent(window.location.href));
            showMessage(TRANSLATIONS.loginRequired || 'Vui lòng đăng nhập để sử dụng tính năng này', 'warning');
            setTimeout(() => {
                window.location.href = loginUrl;
            }, 1500);
            return;
        }

        const isActive = btn.classList.contains('active');
        const newState = !isActive;

        // Disable button temporarily
        btn.disabled = true;

        // Toggle UI immediately for better UX
        if (newState) {
            btn.classList.add('active');
            btn.setAttribute('title', TRANSLATIONS.unfavorite || 'Bỏ yêu thích');
        } else {
            btn.classList.remove('active');
            btn.setAttribute('title', TRANSLATIONS.favorite || 'Yêu thích');
        }

        // Call API to save favorite state
        const formData = new FormData();
        formData.append('action', 'toggleProductFavorite');
        formData.append('token', userToken);
        formData.append('product_id', productId);
        formData.append('is_favorite', newState ? '1' : '0');
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/create.php'), {
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
                    btn.setAttribute('title', TRANSLATIONS.unfavorite || 'Bỏ yêu thích');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('title', TRANSLATIONS.favorite || 'Yêu thích');
                }
                // Update nav favorites count
                updateNavFavoritesCount(data.is_favorited);
            } else {
                // Revert UI on error
                if (isActive) {
                    btn.classList.add('active');
                    btn.setAttribute('title', TRANSLATIONS.unfavorite || 'Bỏ yêu thích');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('title', TRANSLATIONS.favorite || 'Yêu thích');
                }
                showMessage(data.msg || TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            console.error('Error toggling favorite:', error);
            // Revert UI on error
            if (isActive) {
                btn.classList.add('active');
                btn.setAttribute('title', TRANSLATIONS.unfavorite || 'Bỏ yêu thích');
            } else {
                btn.classList.remove('active');
                btn.setAttribute('title', TRANSLATIONS.favorite || 'Yêu thích');
            }
            showMessage(TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi, vui lòng thử lại', 'error');
        });
    }

    // ========================================
    // REVIEW MODULE
    // ========================================
    
    /**
     * Initialize review functionality
     */
    function initReviewModule() {
        initRatingInput();
        initReviewForm();
        initReviewCharCounter();
        initHelpfulButtons();
        initLoadMoreReviews();
    }

    /**
     * Initialize rating input (star selection)
     */
    function initRatingInput() {
        const ratingInput = document.getElementById('ratingInput');
        const ratingHidden = document.getElementById('reviewRating');
        const ratingText = document.getElementById('ratingText');
        
        if (!ratingInput || !ratingHidden) return;

        const stars = ratingInput.querySelectorAll('i[data-rating]');
        const ratingLabels = {
            1: TRANSLATIONS.rating1 || 'Rất tệ',
            2: TRANSLATIONS.rating2 || 'Tệ',
            3: TRANSLATIONS.rating3 || 'Bình thường',
            4: TRANSLATIONS.rating4 || 'Tốt',
            5: TRANSLATIONS.rating5 || 'Rất tốt'
        };

        // Hover effect
        stars.forEach(star => {
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                highlightStars(stars, rating);
                if (ratingText) {
                    ratingText.textContent = ratingLabels[rating];
                }
            });
        });

        // Mouse leave - revert to selected rating
        ratingInput.addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingHidden.value) || 0;
            highlightStars(stars, currentRating);
            if (ratingText) {
                if (currentRating > 0) {
                    ratingText.textContent = ratingLabels[currentRating];
                } else {
                    ratingText.textContent = TRANSLATIONS.selectRating || 'Chọn số sao';
                }
            }
        });

        // Click to select
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                ratingHidden.value = rating;
                highlightStars(stars, rating);
                if (ratingText) {
                    ratingText.textContent = ratingLabels[rating];
                }
            });
        });
    }

    /**
     * Highlight stars up to given rating
     */
    function highlightStars(stars, rating) {
        stars.forEach(star => {
            const starRating = parseInt(star.getAttribute('data-rating'));
            if (starRating <= rating) {
                star.classList.remove('fa-regular');
                star.classList.add('fa-solid');
            } else {
                star.classList.remove('fa-solid');
                star.classList.add('fa-regular');
            }
        });
    }

    /**
     * Initialize review form submission
     */
    function initReviewForm() {
        const form = document.getElementById('writeReviewForm');
        if (!form) return;

        form.addEventListener('submit', handleReviewSubmit);
    }

    /**
     * Handle review form submission
     */
    function handleReviewSubmit(e) {
        e.preventDefault();

        const btnSubmit = document.getElementById('btnSubmitReview');
        if (!btnSubmit || btnSubmit.disabled) return;

        // Get form values
        const userToken = document.getElementById('reviewUserToken')?.value;
        const orderId = document.getElementById('reviewOrderSelect')?.value;
        const rating = document.getElementById('reviewRating')?.value;
        const content = document.getElementById('reviewContent')?.value?.trim() || '';

        // Validate
        if (!userToken) {
            showMessage(TRANSLATIONS.reviewLoginRequired || 'Vui lòng đăng nhập để đánh giá', 'error');
            return;
        }

        if (!orderId) {
            showMessage(TRANSLATIONS.reviewSelectOrder || 'Vui lòng chọn đơn hàng để đánh giá', 'warning');
            return;
        }

        if (!rating || rating < 1 || rating > 5) {
            showMessage(TRANSLATIONS.reviewSelectRating || 'Vui lòng chọn số sao đánh giá', 'warning');
            return;
        }

        if (!content) {
            showMessage(TRANSLATIONS.reviewEnterContent || 'Vui lòng nhập nội dung đánh giá', 'warning');
            return;
        }

        if (content.length < 5) {
            showMessage(TRANSLATIONS.reviewContentTooShort || 'Nội dung đánh giá quá ngắn (tối thiểu 5 ký tự)', 'warning');
            return;
        }

        // Disable button and show loading
        btnSubmit.disabled = true;
        const originalText = btnSubmit.innerHTML;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (TRANSLATIONS.reviewSubmitting || 'Đang gửi...');

        // Submit via AJAX
        const formData = new FormData();
        formData.append('action', 'submitProductReview');
        formData.append('token', userToken);
        formData.append('product_id', PRODUCT_ID);
        formData.append('order_id', orderId);
        formData.append('rating', rating);
        formData.append('content', content);
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/reviews.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;

            if (data.status === 'success') {
                showMessage(data.msg || TRANSLATIONS.reviewSubmitSuccess || 'Gửi đánh giá thành công!', 'success');
                
                // Close modal and reload page after short delay
                closeReviewModal();
                setTimeout(function() {
                    location.reload();
                }, 1500);
                const select = document.getElementById('reviewOrderSelect');
                const option = select.querySelector(`option[value="${orderId}"]`);
                if (option) {
                    option.remove();
                }

                // Reset form
                document.getElementById('writeReviewForm').reset();
                document.getElementById('reviewRating').value = '';
                const stars = document.querySelectorAll('#ratingInput i[data-rating]');
                highlightStars(stars, 0);
                document.getElementById('ratingText').textContent = TRANSLATIONS.selectRating || 'Chọn số sao';
                document.getElementById('reviewCharCount').textContent = '0';

                // Update pending orders count
                const pendingCount = select.options.length - 1; // -1 for the placeholder
                const pendingCountEl = document.querySelector('.pending-orders-count');
                if (pendingCountEl) {
                    if (pendingCount > 0) {
                        pendingCountEl.textContent = `(${pendingCount} đơn hàng chưa đánh giá)`;
                    } else {
                        // Hide entire write review section if no more orders
                        const writeSection = document.getElementById('writeReviewSection');
                        if (writeSection) {
                            writeSection.style.display = 'none';
                        }
                    }
                }

                // Update global PENDING_REVIEW_ORDERS
                if (typeof PENDING_REVIEW_ORDERS !== 'undefined') {
                    const idx = PENDING_REVIEW_ORDERS.findIndex(o => o.id == orderId);
                    if (idx > -1) {
                        PENDING_REVIEW_ORDERS.splice(idx, 1);
                    }
                }

            } else {
                showMessage(data.msg || TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
            }
        })
        .catch(error => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalText;
            console.error('Error submitting review:', error);
            showMessage(TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
        });
    }

    /**
     * Initialize review content character counter
     */
    function initReviewCharCounter() {
        const textarea = document.getElementById('reviewContent');
        const counter = document.getElementById('reviewCharCount');
        
        if (!textarea || !counter) return;

        textarea.addEventListener('input', function() {
            counter.textContent = this.value.length;
        });
    }

    /**
     * Initialize helpful buttons
     */
    function initHelpfulButtons() {
        const helpfulBtns = document.querySelectorAll('.btn-review-helpful');
        
        helpfulBtns.forEach(btn => {
            btn.addEventListener('click', handleHelpfulClick);
        });
    }

    /**
     * Initialize load more reviews button
     */
    function initLoadMoreReviews() {
        const loadMoreBtn = document.getElementById('btnLoadMoreReviews');
        const loadMoreContainer = document.getElementById('reviewsLoadMore');
        
        if (!loadMoreBtn || !loadMoreContainer) return;
        
        loadMoreBtn.addEventListener('click', function() {
            const productId = loadMoreContainer.getAttribute('data-product-id');
            let offset = parseInt(loadMoreContainer.getAttribute('data-offset')) || 5;
            const total = parseInt(loadMoreContainer.getAttribute('data-total')) || 0;
            
            // Show loading state
            const originalText = loadMoreBtn.innerHTML;
            loadMoreBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (TRANSLATIONS.loading || 'Đang tải...');
            loadMoreBtn.disabled = true;
            
            // AJAX request
            const formData = new FormData();
            formData.append('action', 'getMoreReviews');
            formData.append('product_id', productId);
            formData.append('offset', offset);
            formData.append('csrf_token', getCSRFToken());
            
            fetch(base_url('ajaxs/client/reviews.php'), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadMoreBtn.disabled = false;
                
                if (data.status === 'success' && data.html) {
                    // Insert HTML before load more button
                    loadMoreContainer.insertAdjacentHTML('beforebegin', data.html);
                    
                    // Update offset
                    loadMoreContainer.setAttribute('data-offset', data.new_offset);
                    
                    // Re-init helpful buttons for new reviews
                    initHelpfulButtons();
                    
                    // Update remaining count or hide button
                    const remaining = total - data.new_offset;
                    if (remaining <= 0) {
                        loadMoreContainer.style.display = 'none';
                    } else {
                        document.getElementById('hiddenReviewsCount').textContent = '(' + remaining + ')';
                        loadMoreBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> ' + (TRANSLATIONS.loadMore || 'Xem thêm') + ' <span id="hiddenReviewsCount">(' + remaining + ')</span>';
                    }
                } else {
                    loadMoreBtn.innerHTML = originalText;
                    showMessage(data.msg || 'Đã xảy ra lỗi', 'error');
                }
            })
            .catch(error => {
                console.error('Error loading reviews:', error);
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = originalText;
                showMessage('Đã xảy ra lỗi', 'error');
            });
        });
    }

    /**
     * Handle helpful button click
     */
    function handleHelpfulClick(e) {
        e.preventDefault();
        
        const btn = e.currentTarget;
        const reviewId = btn.getAttribute('data-review-id');
        
        if (!reviewId || btn.disabled) return;

        // Check if user is logged in
        const userToken = getUserToken();
        if (!userToken) {
            showMessage(TRANSLATIONS.loginRequired || 'Vui lòng đăng nhập để sử dụng tính năng này', 'warning');
            return;
        }

        // Disable button temporarily
        btn.disabled = true;

        // Submit via AJAX
        const formData = new FormData();
        formData.append('action', 'markReviewHelpful');
        formData.append('token', userToken);
        formData.append('review_id', reviewId);
        formData.append('csrf_token', getCSRFToken());

        fetch(base_url('ajaxs/client/reviews.php'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;

            if (data.status === 'success') {
                // Cập nhật toàn bộ nội dung nút
                const countText = data.helpful_count > 0 ? ` <span class="helpful-count">(${data.helpful_count})</span>` : '';
                
                if (data.voted) {
                    // Đã vote -> Hiển thị nút Bỏ Vote (màu đỏ)
                    btn.classList.add('voted');
                    btn.innerHTML = `<i class="fa-solid fa-thumbs-down"></i><span>${TRANSLATIONS.unvote || 'Bỏ vote'}</span>${countText}`;
                } else {
                    // Chưa vote -> Hiển thị nút Hữu ích
                    btn.classList.remove('voted');
                    btn.innerHTML = `<i class="fa-regular fa-thumbs-up"></i><span>${TRANSLATIONS.helpful || 'Hữu ích'}</span>${countText}`;
                }
                
                showMessage(data.msg || 'Cảm ơn bạn!', 'success');
            } else {
                showMessage(data.msg || TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
            }
        })
        .catch(error => {
            btn.disabled = false;
            console.error('Error marking helpful:', error);
            showMessage(TRANSLATIONS.errorOccurred || 'Đã xảy ra lỗi', 'error');
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            initReviewModule();
        });
    } else {
        init();
        initReviewModule();
    }

    // Expose public API
    window.ProductDetailModule = {
        selectPlan: selectPlan,
        getState: () => ({ ...state })
    };

    // ========================================
    // AFFILIATE SHARE MODULE
    // ========================================

    /**
     * Initialize affiliate share button
     */
    function initAffiliateShare() {
        const btnAffiliateShare = document.getElementById('btnAffiliateShare');
        if (btnAffiliateShare) {
            btnAffiliateShare.addEventListener('click', openAffiliateModal);
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAffiliateModal();
            }
        });
    }

    /**
     * Open affiliate share modal
     */
    function openAffiliateModal() {
        const modal = document.getElementById('affiliateShareModal');
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Close affiliate share modal
     */
    function closeAffiliateModal() {
        const modal = document.getElementById('affiliateShareModal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    /**
     * Copy affiliate link to clipboard
     */
    function copyAffiliateLink() {
        const input = document.getElementById('affiliateProductLink');
        const btn = document.querySelector('.btn-copy-affiliate');
        
        if (!input) return;

        // Select and copy
        input.select();
        input.setSelectionRange(0, 99999); // For mobile

        try {
            // Try modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(function() {
                    showCopySuccess(btn);
                }).catch(function() {
                    // Fallback to execCommand
                    document.execCommand('copy');
                    showCopySuccess(btn);
                });
            } else {
                // Fallback for older browsers
                document.execCommand('copy');
                showCopySuccess(btn);
            }
        } catch (err) {
            console.error('Copy failed:', err);
            showMessage(TRANSLATIONS.copyFailed || 'Không thể sao chép, vui lòng copy thủ công', 'error');
        }
    }

    /**
     * Show copy success feedback
     */
    function showCopySuccess(btn) {
        if (btn) {
            btn.classList.add('copied');
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            
            setTimeout(function() {
                btn.classList.remove('copied');
                btn.innerHTML = originalIcon;
            }, 2000);
        }
        
        showMessage(TRANSLATIONS.linkCopied || 'Đã sao chép link giới thiệu!', 'success');
    }

    /**
     * Share via Zalo
     */
    function shareViaZalo() {
        const input = document.getElementById('affiliateProductLink');
        if (!input) return;
        
        const link = input.value;
        const title = document.querySelector('.product-detail-title')?.textContent || '';
        
        // Zalo share URL
        const zaloUrl = 'https://zalo.me/share/url=' + encodeURIComponent(link) + '&title=' + encodeURIComponent(title);
        
        // Try to open Zalo app first, fallback to web
        window.open(zaloUrl, '_blank');
    }

    // Initialize affiliate share on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAffiliateShare);
    } else {
        initAffiliateShare();
    }

    // Expose affiliate functions globally for onclick handlers
    window.openAffiliateModal = openAffiliateModal;
    window.closeAffiliateModal = closeAffiliateModal;
    window.copyAffiliateLink = copyAffiliateLink;
    window.shareViaZalo = shareViaZalo;

})();

