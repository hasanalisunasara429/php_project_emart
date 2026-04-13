// assets/js/main.js — E-Mart Main JavaScript

// ============================================================
// 1. TOAST NOTIFICATION SYSTEM
// ============================================================
(function () {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
})();

function showToast(message, type = 'success', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()"
                style="background:none;border:none;color:inherit;cursor:pointer;margin-left:12px;font-size:16px">✕</button>
    `;
    document.getElementById('toastContainer').appendChild(toast);
    setTimeout(() => toast.remove(), duration);
}

// ============================================================
// 2. LIVE SEARCH (AJAX)
// ============================================================
(function () {
    const input    = document.getElementById('searchInput');
    const dropdown = document.getElementById('searchResults');
    if (!input || !dropdown) return;

    let debounceTimer;

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();

        if (q.length < 2) {
            dropdown.innerHTML = '';
            dropdown.classList.remove('open');
            return;
        }

        debounceTimer = setTimeout(() => {
            // Detect if we're in a subdirectory (user/) or root
            const base = window.location.pathname.includes('/user/') ||
                         window.location.pathname.includes('/admin/')
                         ? '../api/search.php'
                         : 'api/search.php';

            fetch(`${base}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.results || data.results.length === 0) {
                        dropdown.innerHTML = '<div style="padding:14px;color:#888;text-align:center">No results found</div>';
                        dropdown.classList.add('open');
                        return;
                    }
                    dropdown.innerHTML = data.results.map(item => `
                        <a href="${item.url}" class="search-result-item">
                            <img src="${item.image}" alt="${item.name}"
                                 onerror="this.src='assets/images/default.jpg'">
                            <div>
                                <div class="sri-name">${item.name}</div>
                                <div class="sri-cat">${item.category_name}</div>
                            </div>
                            <div class="sri-price">${item.price}</div>
                        </a>
                    `).join('');
                    dropdown.classList.add('open');
                })
                .catch(() => {});
        }, 280);
    });

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Search on Enter
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            const isSubDir = window.location.pathname.includes('/user/') ||
                             window.location.pathname.includes('/admin/');
            const base = isSubDir ? '../index.php' : 'index.php';
            window.location.href = `${base}?search=${encodeURIComponent(this.value.trim())}`;
        }
    });
})();

// ============================================================
// 3. ADD TO CART (AJAX) — called from product cards
// ============================================================
function addToCart(productId, buttonEl) {
    const isSubDir = window.location.pathname.includes('/user/') ||
                     window.location.pathname.includes('/admin/');
    const apiUrl   = isSubDir ? '../api/cart.php' : 'api/cart.php';

    // Visual feedback
    if (buttonEl) {
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding…';
    }

    fetch(apiUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ action: 'add', product_id: productId, quantity: 1 })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('✅ Added to cart!', 'success');
            // Update cart badge
            const badge = document.getElementById('cartBadge');
            if (badge) badge.textContent = data.cart_count;
            if (buttonEl) {
                buttonEl.innerHTML = '<i class="fas fa-check"></i> Added!';
                setTimeout(() => {
                    buttonEl.disabled = false;
                    buttonEl.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                }, 1500);
            }
        } else {
            showToast(data.message || 'Failed to add to cart.', 'error');
            if (data.redirect) {
                setTimeout(() => window.location.href = data.redirect, 1000);
            }
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
            }
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
        if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
        }
    });
}

// ============================================================
// 4. WISHLIST TOGGLE (AJAX)
// ============================================================
function toggleWishlist(productId, buttonEl) {
    const isSubDir = window.location.pathname.includes('/user/') ||
                     window.location.pathname.includes('/admin/');
    const apiUrl   = isSubDir ? '../api/wishlist.php' : 'api/wishlist.php';

    fetch(apiUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ product_id: productId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            if (buttonEl) {
                buttonEl.classList.toggle('active', data.action === 'added');
            }
        } else {
            showToast(data.message || 'Please login first.', 'error');
            if (data.redirect) {
                setTimeout(() => window.location.href = data.redirect, 1000);
            }
        }
    })
    .catch(() => showToast('Network error.', 'error'));
}

// ============================================================
// 5. HERO SLIDER
// ============================================================
(function () {
    const slides = document.querySelectorAll('.slide');
    const dots   = document.querySelectorAll('.dot');
    if (!slides.length) return;

    let current = 0;
    let autoTimer;

    window.goSlide = function (index) {
        slides[current].classList.remove('active');
        if (dots[current]) dots[current].classList.remove('active');
        current = (index + slides.length) % slides.length;
        slides[current].classList.add('active');
        if (dots[current]) dots[current].classList.add('active');
    };

    window.changeSlide = function (dir) {
        clearInterval(autoTimer);
        goSlide(current + dir);
        startAuto();
    };

    function startAuto() {
        autoTimer = setInterval(() => goSlide(current + 1), 4000);
    }
    startAuto();
})();

// ============================================================
// 6. SORT / FILTER PRODUCTS
// ============================================================
function applySort() {
    const val   = document.getElementById('sortSelect').value;
    const url   = new URL(window.location.href);
    if (val) url.searchParams.set('sort', val);
    else url.searchParams.delete('sort');
    window.location.href = url.toString();
}

// ============================================================
// 7. PASSWORD EYE TOGGLE
// ============================================================
function toggleEye(inputId, iconEl) {
    const inp  = document.getElementById(inputId);
    const icon = iconEl.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ============================================================
// 8. AUTO-DISMISS FLASH MESSAGES
// ============================================================
(function () {
    const flash = document.getElementById('flashMsg');
    if (flash) setTimeout(() => flash.remove(), 4000);
})();

// ============================================================
// 9. COUPON CODE UPPERCASE AUTO-FORMAT
// ============================================================
document.querySelectorAll('input[name="coupon_code"], #couponInput, #checkoutCoupon')
    .forEach(el => el.addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    }));

// ============================================================
// 10. MOBILE NAV TOGGLE (if needed)
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    // Mobile hamburger for admin sidebar
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('adminSidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
        });
    }
});
