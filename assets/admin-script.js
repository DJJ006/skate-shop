/**
 * Admin Dashboard Functionality
 * Handles Modals, Alerts, and Custom Multi-selects
 */

// --- MODAL MANAGEMENT ---
const openModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
};

const closeModal = (modalId) => {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
};

/**
 * Populates and opens the edit modal with product data
 * UPDATED to include condition parameter
 */
const openEditModal = (id, title, brand, price, category, description, condition) => {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_brand').value = brand; // <--- BRAND ADDED HERE
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_description').value = description;
    
    // Target the condition dropdown (mainly for marketplace-products.php)
    const conditionField = document.getElementById('edit_condition');
    if (conditionField) {
        conditionField.value = condition || ''; 
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
            e.target.style.display = 'none';
        }

        // Handle Custom Dropdown Outside Clicks
        const wrapper = document.getElementById("categoryDropdown");
        const dropdown = document.getElementById("dropdownOptions");
        
        if (wrapper && !wrapper.contains(e.target)) {
            if(dropdown) dropdown.classList.remove("show");
            if(wrapper) wrapper.classList.remove("active");
        }
    });
});