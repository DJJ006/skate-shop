/**
 * Admin Dashboard Functionality
 * Handles Modals, Alerts, and Custom Multi-selects
 */

// --- MODAL MANAGEMENT ---
const openModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
};

const closeModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
};

/**
 * Populates and opens the edit modal with product data
 * UPDATED to include condition/quantity parameter and discountPrice
 */
const openEditModal = (id, title, brand, price, category, description, extraParam, discountPrice) => {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_brand').value = brand; // <--- BRAND ADDED HERE
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_description').value = description;

    // Target the condition dropdown (mainly for marketplace-products.php)
    const conditionField = document.getElementById('edit_condition');
    if (conditionField) {
        conditionField.value = extraParam || '';
    }

    // Target the quantity field (mainly for shop-products.php)
    const quantityField = document.getElementById('edit_quantity');
    if (quantityField) {
        quantityField.value = extraParam || 0;
    }

    // Target the discount price field
    const discountField = document.getElementById('edit_discount_price');
    if (discountField) {
        discountField.value = discountPrice || '';
    }

    openModal('editModal');
};

// --- DROPDOWN MANAGEMENT ---
const toggleDropdown = () => {
    const dropdown = document.getElementById("dropdownOptions");
    const wrapper = document.getElementById("categoryDropdown");

    if (!dropdown || !wrapper) return;

    const isOpen = dropdown.classList.contains("show");

    if (isOpen) {
        dropdown.classList.remove("show");
        wrapper.classList.remove("active");
    } else {
        dropdown.classList.add("show");
        wrapper.classList.add("active");
    }
};

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {

    // 1. Auto-hide Admin Alerts
    const alert = document.querySelector('.admin-alert');
    if (alert) {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';

            // Remove from DOM after transition finishes
            setTimeout(() => {
                alert.style.display = 'none';
            }, 600);
        }, 5000);
    }

    // 2. Global Click Handler (Closes Modals & Dropdowns when clicking outside)
    document.addEventListener('click', (e) => {
        // Handle Modal Overlay Clicks
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
        }

        // Handle Custom Dropdown Outside Clicks
        const wrapper = document.getElementById("categoryDropdown");
        const dropdown = document.getElementById("dropdownOptions");

        if (wrapper && !wrapper.contains(e.target)) {
            if (dropdown) dropdown.classList.remove("show");
            if (wrapper) wrapper.classList.remove("active");
        }
    });

    // 3. Global Character Counter for Admin Forms
    document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(input => {
        // Skip hidden inputs or search bars
        if (input.type === 'hidden' || input.id === 'search' || input.id === 'admin-search') return;

        // Create the counter element
        const counter = document.createElement('div');
        counter.className = 'admin-char-counter';
        counter.style.cssText = "font-family:'Staatliches',sans-serif; font-size:0.85rem; text-align:right; margin-top:-0.5rem; margin-bottom:1rem; letter-spacing:1px;";

        // Insert counter right after the input
        input.parentNode.insertBefore(counter, input.nextSibling);

        const updateCounter = () => {
            const max = parseInt(input.getAttribute('maxlength'), 10);
            const remaining = max - input.value.length;
            counter.textContent = remaining + ' characters remaining';

            // Color logic matching client system
            if (remaining <= 10) {
                counter.style.color = 'var(--primary)'; // red
            } else if (remaining <= 25) {
                counter.style.color = '#e6b800'; // yellow/warning
            } else {
                counter.style.color = '#777'; // gray
            }
        };

        input.addEventListener('input', updateCounter);
        updateCounter(); // Initialize immediately
    });
});