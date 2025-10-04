/**
 * Role-aware navbar for SecureHealth application
 * Provides consistent navigation with role-based visibility
 */

class SecureHealthNavbar {
    constructor() {
        this.user = null;
        this.userRole = null;
        this.isAdmin = false;
        this.isDoctor = false;
        this.isNurse = false;
        this.isReceptionist = false;
    }

    /**
     * Initialize the navbar with user data
     */
    init() {
        this.loadUserData();
        this.renderNavbar();
        this.setupEventListeners();
    }

    /**
     * Load user data from localStorage
     */
    loadUserData() {
        const storedUser = localStorage.getItem('securehealth_user');
        if (!storedUser) {
            this.user = null;
            return;
        }

        try {
            this.user = JSON.parse(storedUser);
            this.determineUserRole();
        } catch (e) {
            console.error('Error parsing user data:', e);
            this.user = null;
        }
    }

    /**
     * Determine user role and permissions
     */
    determineUserRole() {
        if (!this.user) {
            this.userRole = null;
            return;
        }

        const email = (this.user.email || '').toLowerCase();
        const username = (this.user.username || '').toLowerCase();
        const roles = this.user.roles || [];

        // Check for admin first
        this.isAdmin = this.user.isAdmin === true || 
                      roles.includes('ROLE_ADMIN') ||
                      email.includes('admin') || 
                      username.includes('admin');

        // Check for doctor
        this.isDoctor = roles.includes('ROLE_DOCTOR') ||
                       email.includes('doctor') || 
                       username.includes('doctor');

        // Check for nurse
        this.isNurse = roles.includes('ROLE_NURSE') ||
                      email.includes('nurse') || 
                      username.includes('nurse');

        // Check for receptionist
        this.isReceptionist = roles.includes('ROLE_RECEPTIONIST') ||
                             email.includes('receptionist') || 
                             username.includes('receptionist');

        // Determine primary role
        if (this.isAdmin) {
            this.userRole = 'ADMIN';
        } else if (this.isDoctor) {
            this.userRole = 'DOCTOR';
        } else if (this.isNurse) {
            this.userRole = 'NURSE';
        } else if (this.isReceptionist) {
            this.userRole = 'RECEPTIONIST';
        } else {
            this.userRole = 'USER';
        }
    }

    /**
     * Render the navbar HTML
     */
    renderNavbar() {
        const navbarContainer = document.getElementById('navbar-container');
        if (!navbarContainer) {
            console.error('Navbar container not found');
            return;
        }

        navbarContainer.innerHTML = this.getNavbarHTML();
    }

    /**
     * Get navbar HTML based on user role
     */
    getNavbarHTML() {
        const isLoggedIn = this.user !== null;
        const currentPage = window.location.pathname;

        return `
            <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
                <div class="container">
                    <a class="navbar-brand fw-bold fs-3" href="/index.html">
                        <i class="fas fa-shield-alt me-2"></i>SecureHealth
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            ${this.getPublicNavItems(currentPage)}
                            ${isLoggedIn ? this.getRoleBasedNavItems(currentPage) : ''}
                            ${isLoggedIn ? this.getUserNavItems() : this.getLoginNavItem()}
                        </ul>
                    </div>
                </div>
            </nav>
        `;
    }

    /**
     * Get public navigation items (visible to all users)
     */
    getPublicNavItems(currentPage) {
        return `
            <li class="nav-item">
                <a class="nav-link ${currentPage === '/index.html' ? 'active' : ''}" href="/index.html">Home</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle ${currentPage === '/documentation.html' || currentPage.includes('/docs/') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Resources
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item ${currentPage === '/documentation.html' ? 'active' : ''}" href="/documentation.html">
                        <i class="fas fa-book me-1"></i>Documentation
                    </a></li>
                    <li><a class="dropdown-item" href="/index.html#features">
                        <i class="fas fa-star me-1"></i>Features
                    </a></li>
                    <li><a class="dropdown-item" href="/index.html#security">
                        <i class="fas fa-shield-alt me-1"></i>Security
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ${currentPage === '/queryable-encryption-search.html' ? 'active' : ''}" href="/queryable-encryption-search.html">
                        <i class="fas fa-search me-1"></i>Encryption Demo
                    </a></li>
                </ul>
            </li>
        `;
    }

    /**
     * Get role-based navigation items
     */
    getRoleBasedNavItems(currentPage) {
        let items = '';

        // Patients dropdown - visible to all authenticated users
        items += `
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle ${currentPage.includes('/patient') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-users me-1"></i>Patients
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item ${currentPage === '/patients.html' ? 'active' : ''}" href="/patients.html">View All Patients</a></li>
                    <li><a class="dropdown-item ${currentPage === '/patient-add.html' ? 'active' : ''}" href="/patient-add.html">Add New Patient</a></li>
                    ${this.isReceptionist ? `<li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ${currentPage === '/scheduling.html' ? 'active' : ''}" href="/scheduling.html"><i class="fas fa-calendar-alt me-1"></i>Scheduling</a></li>` : ''}
                </ul>
            </li>
        `;

        // Admin-specific dropdown
        if (this.isAdmin) {
            items += `
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle ${(currentPage.includes('/admin') || currentPage.includes('/queryable')) ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item ${currentPage === '/admin.html' ? 'active' : ''}" href="/admin.html">Dashboard</a></li>
                        <li><a class="dropdown-item ${currentPage === '/admin-demo-data.html' ? 'active' : ''}" href="/admin-demo-data.html">Demo Data</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item ${currentPage === '/queryable-encryption-search.html' ? 'active' : ''}" href="/queryable-encryption-search.html"><i class="fas fa-lock me-1"></i>Encryption Search</a></li>
                    </ul>
                </li>
            `;
        }

        return items;
    }

    /**
     * Get user-specific navigation items (user info, logout)
     */
    getUserNavItems() {
        const userName = this.user.username || this.user.email || 'User';
        const roleClass = this.getRoleClass();

        return `
            <li class="nav-item d-flex align-items-center ms-3">
                <span class="text-white me-2">${userName}</span>
                <span class="role-badge ${roleClass} me-2">${this.userRole}</span>
                <button id="logoutBtn" class="btn btn-primary-custom btn-custom">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </button>
            </li>
        `;
    }

    /**
     * Get login navigation item (for non-authenticated users)
     */
    getLoginNavItem() {
        return `
            <li class="nav-item">
                <a class="btn btn-primary-custom btn-custom ms-3" href="/login.html">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            </li>
        `;
    }

    /**
     * Get CSS class for role badge
     */
    getRoleClass() {
        switch (this.userRole) {
            case 'ADMIN':
                return 'role-admin';
            case 'DOCTOR':
                return 'role-doctor';
            case 'NURSE':
                return 'role-nurse';
            case 'RECEPTIONIST':
                return 'role-receptionist';
            default:
                return 'role-user';
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.handleLogout());
        }
    }

    /**
     * Handle logout
     */
    async handleLogout() {
        try {
            // Call logout API
            await fetch('/api/logout', {
                method: 'POST',
                credentials: 'include'
            });
        } catch (error) {
            console.error('Logout API error:', error);
        } finally {
            // Clear local storage and redirect
            localStorage.removeItem('securehealth_user');
            window.location.href = '/login.html';
        }
    }

    /**
     * Check if user has required role
     */
    hasRole(requiredRole) {
        switch (requiredRole) {
            case 'admin':
                return this.isAdmin;
            case 'doctor':
                return this.isDoctor;
            case 'nurse':
                return this.isNurse;
            case 'receptionist':
                return this.isReceptionist;
            default:
                return false;
        }
    }

    /**
     * Redirect if user doesn't have required role
     */
    requireRole(requiredRole, redirectUrl = '/login.html') {
        if (!this.hasRole(requiredRole)) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }

    /**
     * Get current user data
     */
    getCurrentUser() {
        return this.user;
    }

    /**
     * Get current user role
     */
    getCurrentUserRole() {
        return this.userRole;
    }
}

// Global instance
window.secureHealthNavbar = new SecureHealthNavbar();

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.secureHealthNavbar.init();
});
