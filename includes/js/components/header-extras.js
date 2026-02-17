// Header Extras JavaScript - Profile Dropdown Mobile Click Handler

document.addEventListener('DOMContentLoaded', function() {
    // Handle mobile profile dropdown click
    const accountDropdowns = document.querySelectorAll('.account-dropdown-wrapper');
    
    accountDropdowns.forEach(dropdown => {
        const button = dropdown.querySelector('.account-dropdown-btn');
        const menu = dropdown.querySelector('.account-dropdown-menu');
        
        if (button && menu) {
            // Toggle dropdown on button click (mobile)
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close other dropdowns
                accountDropdowns.forEach(other => {
                    if (other !== dropdown) {
                        other.classList.remove('active');
                    }
                });
                
                // Toggle current dropdown
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        accountDropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    });
    
    // Prevent dropdown from closing when clicking inside it
    accountDropdowns.forEach(dropdown => {
        const menu = dropdown.querySelector('.account-dropdown-menu');
        if (menu) {
            menu.addEventListener('click', function(e) {
                // Allow links to work normally
                if (e.target.tagName === 'A' || e.target.closest('a')) {
                    return;
                }
                e.stopPropagation();
            });
        }
    });
    
    // Close dropdown on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            accountDropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
});