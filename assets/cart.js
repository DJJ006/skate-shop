// Shopping Cart JavaScript Logic
document.addEventListener('DOMContentLoaded', () => {
    const cartOverlay = document.getElementById('cartOverlay');
    const cartDrawer = document.getElementById('cartDrawer');
    const closeBtn = document.getElementById('closeCartBtn');
    const cartIcon = document.getElementById('cartIcon');
    const cartCountBadge = document.getElementById('cartCountBadge');

    const cartItemsContainer = document.getElementById('cartItemsContainer');
    const cartSubtotalValue = document.getElementById('cartSubtotalValue');
    const clearCartBtn = document.getElementById('clearCartBtn');
    const checkoutBtn = document.getElementById('checkoutBtn');

    // Detect checkout page — cart is read-only here
    const isCheckoutPage = window.location.pathname.endsWith('checkout.php');

    // Apply disabled state to cart icon on checkout page
    if (isCheckoutPage && cartIcon) {
        cartIcon.style.opacity = '0.4';
        cartIcon.style.cursor = 'not-allowed';
        cartIcon.style.pointerEvents = 'none';
        cartIcon.title = 'Cart editing is disabled during checkout';
    }

    // Toggle Drawer
    if (cartIcon) {
        cartIcon.addEventListener('click', (e) => {
            e.preventDefault();
            openCart();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeCart);
    }

    if (cartOverlay) {
        cartOverlay.addEventListener('click', closeCart);
    }

    function openCart() {
        if (isCheckoutPage) return; // Blocked during checkout
        if (cartOverlay && cartDrawer) {
            cartOverlay.classList.add('active');
            cartDrawer.classList.add('active');
            document.body.style.overflow = 'hidden';
            refreshCartUI();
        }
    }

    function closeCart() {
        if (cartOverlay && cartDrawer) {
            cartOverlay.classList.remove('active');
            cartDrawer.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }

    // Refresh Cart UI
    function refreshCartUI() {
        fetch('cart-api.php?action=get')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderCartItems(data.items);
                    cartSubtotalValue.innerText = '$' + parseFloat(data.subtotal).toFixed(2);
                    updateCartBadge(data.count);
                }
            })
            .catch(error => console.error('Error fetching cart:', error));
    }

    function renderCartItems(items) {
        cartItemsContainer.innerHTML = '';

        if (items.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="cart-empty-state">
                    <i class="fa-solid fa-cart-arrow-down"></i>
                    <h3>YOUR CART IS EMPTY</h3>
                    <p>Add some gear before you check out.</p>
                </div>
            `;
            return;
        }

        items.forEach(item => {
            const isMarket = item.is_marketplace;
            let qtyControls = '';

            if (isMarket) {
                qtyControls = `<span class="badge-market" style="font-size: 0.7rem; padding: 2px 6px;">1-OF-1 ITEM</span>`;
            } else {
                qtyControls = `
                    <div class="cart-qty-controls">
                        <button class="cart-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty - 1})">-</button>
                        <input type="number" class="cart-qty-input" value="${item.qty}" readonly>
                        <button class="cart-qty-btn" onclick="updateCartQty(${item.id}, ${item.qty + 1})">+</button>
                    </div>
                `;
            }

            const itemHTML = `
                <div class="cart-item">
                    <img src="${item.image_url}" alt="Product" class="cart-item-img">
                    <div class="cart-item-details">
                        <h4 class="cart-item-title">${item.title}</h4>
                        <p class="cart-item-brand">${item.brand}</p>
                        <div class="cart-item-price">$${parseFloat(item.price).toFixed(2)}</div>
                        ${qtyControls}
                    </div>
                    <button class="cart-remove-btn" onclick="removeFromCart(${item.id})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
            cartItemsContainer.insertAdjacentHTML('beforeend', itemHTML);
        });
    }

    // Global Window Functions for inline HTML handlers
    window.addToCart = function (productId, qty = 1, isMarketplace = false, buttonEl = null) {
        if (buttonEl) {
            buttonEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ADDING...';
            buttonEl.disabled = true;
        }

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('qty', qty);

        fetch('cart-api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.auth_required) {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                if (data.cart_locked) {
                    showCartAlert('Cart editing is disabled while checkout is in progress.', 'CHECKOUT ACTIVE');
                    if (buttonEl) {
                        buttonEl.innerHTML = isMarketplace ? 'BUY FROM SELLER <span class="material-icons">payments</span>' : 'ADD TO CART <span class="material-icons">shopping_cart</span>';
                        buttonEl.disabled = false;
                    }
                    return;
                }
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    if (buttonEl) {
                        buttonEl.innerHTML = '<i class="fa-solid fa-check"></i> ADDED!';
                        setTimeout(() => {
                            buttonEl.innerHTML = isMarketplace ? 'BUY FROM SELLER <span class="material-icons">payments</span>' : 'ADD TO CART <span class="material-icons">shopping_cart</span>';
                            buttonEl.disabled = false;
                        }, 2000);
                    }
                    openCart();
                } else {
                    showCartAlert(data.message || 'Error adding to cart', 'ERROR');
                    if (buttonEl) {
                        buttonEl.innerHTML = isMarketplace ? 'BUY FROM SELLER <span class="material-icons">payments</span>' : 'ADD TO CART <span class="material-icons">shopping_cart</span>';
                        buttonEl.disabled = false;
                    }
                }
            })
            .catch(err => {
                console.error(err);
                showCartAlert("Network error occurred. Please try again.", "NETWORK ERROR");
                if (buttonEl) { buttonEl.disabled = false; }
            });
    };

    window.updateCartQty = function (productId, newQty) {
        if (newQty <= 0) {
            removeFromCart(productId);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('product_id', productId);
        formData.append('qty', newQty);

        fetch('cart-api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshCartUI();
                } else {
                    showCartAlert(data.message || 'Error updating quantity', 'NOTICE');
                }
            });
    };

    window.removeFromCart = function (productId) {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', productId);

        fetch('cart-api.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    refreshCartUI();
                }
            });
    };

    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', () => {
            showCartConfirm('Are you sure you want to completely clear your cart?', 'CLEAR CART?', () => {
                const formData = new FormData();
                formData.append('action', 'clear');

                fetch('cart-api.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateCartBadge(0);
                            refreshCartUI();
                        }
                    });
            });
        });
    }

    function updateCartBadge(count) {
        if (cartCountBadge) {
            cartCountBadge.innerText = count;
            cartCountBadge.style.display = count > 0 ? 'block' : 'none';
        }
        updateCheckoutBtn(count);
    }

    function updateCheckoutBtn(count) {
        if (!checkoutBtn) return;
        if (count > 0) {
            checkoutBtn.removeAttribute('aria-disabled');
            checkoutBtn.style.opacity = '';
            checkoutBtn.style.pointerEvents = '';
            checkoutBtn.style.cursor = '';
            checkoutBtn.href = 'checkout.php';
        } else {
            checkoutBtn.setAttribute('aria-disabled', 'true');
            checkoutBtn.style.opacity = '0.35';
            checkoutBtn.style.pointerEvents = 'none';
            checkoutBtn.style.cursor = 'not-allowed';
            checkoutBtn.href = '#';
        }
    }

    // Initial Badge Load
    fetch('cart-api.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartBadge(data.count);
                updateCheckoutBtn(data.count);
            }
        });

    // Custom Brutalist Alert Modal for Cart
    function showCartAlert(message, title = 'NOTICE') {
        let overlay = document.getElementById('cartAlertModalOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'cartAlertModalOverlay';
            overlay.className = 'cart-modal-overlay';

            const content = document.createElement('div');
            content.className = 'cart-modal-content';

            const titleEl = document.createElement('h3');
            titleEl.id = 'cartAlertModalTitle';
            titleEl.className = 'cart-modal-title';

            const textEl = document.createElement('p');
            textEl.id = 'cartAlertModalText';
            textEl.className = 'cart-modal-text';

            const btnEl = document.createElement('button');
            btnEl.className = 'cart-modal-btn';
            btnEl.innerText = 'OK';
            btnEl.onclick = () => { overlay.classList.remove('active'); };

            content.appendChild(titleEl);
            content.appendChild(textEl);
            content.appendChild(btnEl);
            overlay.appendChild(content);
            document.body.appendChild(overlay);

            // Allow closing by clicking outside the modal content
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            };
        }

        document.getElementById('cartAlertModalTitle').innerHTML = title;
        document.getElementById('cartAlertModalText').innerHTML = message;

        overlay.classList.add('active');
    }

    // Custom Brutalist Confirm Modal for Cart
    function showCartConfirm(message, title, onConfirmCallback) {
        let overlay = document.getElementById('cartConfirmModalOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'cartConfirmModalOverlay';
            overlay.className = 'cart-modal-overlay';

            const content = document.createElement('div');
            content.className = 'cart-modal-content';

            const titleEl = document.createElement('h3');
            titleEl.id = 'cartConfirmModalTitle';
            titleEl.className = 'cart-modal-title';

            const textEl = document.createElement('p');
            textEl.id = 'cartConfirmModalText';
            textEl.className = 'cart-modal-text';

            const btnContainer = document.createElement('div');
            btnContainer.style.display = 'flex';
            btnContainer.style.gap = '10px';
            btnContainer.style.justifyContent = 'center';

            const confirmBtn = document.createElement('button');
            confirmBtn.className = 'cart-modal-btn cart-modal-btn-danger';
            confirmBtn.style.flex = '1';
            confirmBtn.id = 'cartConfirmYesBtn';
            confirmBtn.innerText = 'YES';

            const cancelBtn = document.createElement('button');
            cancelBtn.className = 'cart-modal-btn';
            cancelBtn.style.flex = '1';
            cancelBtn.innerText = 'CANCEL';
            cancelBtn.onclick = () => { overlay.classList.remove('active'); };

            btnContainer.appendChild(confirmBtn);
            btnContainer.appendChild(cancelBtn);

            content.appendChild(titleEl);
            content.appendChild(textEl);
            content.appendChild(btnContainer);
            overlay.appendChild(content);
            document.body.appendChild(overlay);

            // Allow closing by clicking outside the modal content
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            };
        }

        document.getElementById('cartConfirmModalTitle').innerHTML = title;
        document.getElementById('cartConfirmModalText').innerHTML = message;

        const yesBtn = document.getElementById('cartConfirmYesBtn');
        // Remove old event listeners by cloning the button
        const newYesBtn = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);

        newYesBtn.onclick = () => {
            overlay.classList.remove('active');
            onConfirmCallback();
        };

        overlay.classList.add('active');
    }
});
