/**
 * Main Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Active navigation links
    setActiveNavLink();
    
    // Add documentation search functionality
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value.trim()) {
                window.location.href = '/docs?q=' + encodeURIComponent(searchInput.value.trim());
            }
        });
    }
});

/**
 * Set active navigation link based on current URL
 */
function setActiveNavLink() {
    // Get current path
    const currentPath = window.location.pathname;
    
    // Get all nav links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    // Loop through links and set active class
    navLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        
        // Skip dropdown toggles
        if (link.classList.contains('dropdown-toggle')) {
            return;
        }
        
        // Check if href matches current path
        if (href === currentPath || (href !== '/' && currentPath.startsWith(href))) {
            link.classList.add('active');
        }
    });
}