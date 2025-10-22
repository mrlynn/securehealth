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
        // Start unread polling for care team
        if (this.isDoctor || this.isNurse) {
            this.startStaffUnreadPolling();
        }
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

        // Check for doctor // super hacky to get around the fact that the user role is not set in the database
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
        
        // Initialize Bootstrap dropdowns after rendering
        this.initializeDropdowns();
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
                    <a class="navbar-brand fw-bold fs-3" href="/dashboard.html">
                        <img src="/images/securehealth-logo.png" alt="SecureHealth Logo" height="60" class="me-2">SecureHealth
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
                <a class="nav-link ${currentPage === '/dashboard.html' ? 'active' : ''}" href="/dashboard.html">Dashboard</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle ${currentPage === '/documentation.html' || currentPage.includes('/docs/') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Resources
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item ${currentPage === '/role-documentation.html' ? 'active' : ''}" href="/role-documentation.html">
                        <i class="fas fa-user-md me-1"></i>My Documentation
                    </a></li>
                    <li><a class="dropdown-item ${currentPage === '/documentation.html' ? 'active' : ''}" href="/documentation.html">
                        <i class="fas fa-book me-1"></i>Developer Docs
                    </a></li>
                    <li><a class="dropdown-item" href="/index.html#features">
                        <i class="fas fa-star me-1"></i>Features
                    </a></li>
                    <li><a class="dropdown-item" href="/index.html#security">
                        <i class="fas fa-shield me-1"></i>Security
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ${currentPage === '/queryable-encryption-search.html' ? 'active' : ''}" href="/queryable-encryption-search.html">
                        <i class="fas fa-search me-1"></i>Encryption Demo
                    </a></li>
                    <li><a class="dropdown-item ${currentPage === '/wizard.html' ? 'active' : ''}" href="/wizard.html">
                        <i class="fas fa-magic me-1"></i>Demo Wizard
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

        // Calendar - visible to all authenticated users
        items += `
            <li class="nav-item">
                <a class="nav-link ${currentPage === '/calendar.html' ? 'active' : ''}" href="/calendar.html">
                    <i class="fas fa-calendar-alt me-1"></i>Calendar
                </a>
            </li>
        `;

        // Patients dropdown - visible to all authenticated users with role-specific options
        items += `
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle ${currentPage.includes('/patient') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-users me-1"></i>Patients
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item ${currentPage === '/patients.html' ? 'active' : ''}" href="/patients.html">
                        <i class="fas fa-list me-1"></i>View All Patients
                    </a></li>
                    <li><a class="dropdown-item ${currentPage === '/patient-add.html' ? 'active' : ''}" href="/patient-add.html">
                        <i class="fas fa-user-plus me-1"></i>Add New Patient
                    </a></li>
                    ${(this.isDoctor || this.isNurse) ? `
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ${currentPage === '/patient-notes-demo.html' ? 'active' : ''}" href="/patient-notes-demo.html">
                        <i class="fas fa-notes-medical me-1"></i>${this.isDoctor ? 'Manage' : 'View'} Patient Notes
                    </a></li>` : ''}
                    ${this.isReceptionist ? `
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item ${currentPage === '/scheduling.html' ? 'active' : ''}" href="/scheduling.html">
                        <i class="fas fa-calendar-check me-1"></i>Scheduling
                    </a></li>` : ''}
                </ul>
            </li>
        `;

        // Doctor-specific dropdown for clinical tools
        if (this.isDoctor) {
            items += `
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle ${currentPage.includes('/medical-knowledge') || currentPage === '/ai-documentation.html' || currentPage === '/admin.html' ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-stethoscope me-1"></i>Clinical Tools
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item ${currentPage === '/ai-documentation.html' ? 'active' : ''}" href="/ai-documentation.html">
                            <i class="fas fa-robot me-1"></i>AI Documentation
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item ${currentPage === '/medical-knowledge-search.html' ? 'active' : ''}" href="/medical-knowledge-search.html">
                            <i class="fas fa-book-medical me-1"></i>Medical Knowledge
                        </a></li>
                        <li><a class="dropdown-item" href="/medical-knowledge-search.html?tool=clinical-decision">
                            <i class="fas fa-brain me-1"></i>Clinical Decision Support
                        </a></li>
                        <li><a class="dropdown-item" href="/medical-knowledge-search.html?tool=drug-interactions">
                            <i class="fas fa-pills me-1"></i>Drug Interactions
                        </a></li>
                        <li><a class="dropdown-item" href="/medical-knowledge-search.html?tool=treatment-guidelines">
                            <i class="fas fa-clipboard-list me-1"></i>Treatment Guidelines
                        </a></li>
                        <li><a class="dropdown-item" href="/medical-knowledge-search.html?tool=diagnostics">
                            <i class="fas fa-microscope me-1"></i>Diagnostic Criteria
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item ${currentPage === '/admin.html' ? 'active' : ''}" href="/admin.html">
                            <i class="fas fa-file-alt me-1"></i>Audit Logs
                        </a></li>
                    </ul>
                </li>
            `;
        }

        // Nurse-specific medical tools (limited access)
        if (this.isNurse && !this.isDoctor) {
            items += `
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle ${currentPage.includes('/medical-knowledge') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-medkit me-1"></i>Medical Tools
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/medical-knowledge-search.html?tool=drug-interactions">
                            <i class="fas fa-pills me-1"></i>Drug Interactions
                        </a></li>
                        <li><a class="dropdown-item ${currentPage === '/medical-knowledge-search.html' ? 'active' : ''}" href="/medical-knowledge-search.html">
                            <i class="fas fa-book-medical me-1"></i>Medical Knowledge (View)
                        </a></li>
                    </ul>
                </li>
            `;
        }

        // Care team messages (doctors/nurses)
        if (this.isDoctor || this.isNurse) {
            items += `
                <li class="nav-item">
                    <a class="nav-link ${currentPage === '/staff/messages' ? 'active' : ''}" href="/staff/messages">
                        <i class="fas fa-envelope me-1"></i>Messages
                        <span id="navMessagesBadge" class="badge bg-light text-dark ms-1"></span>
                    </a>
                </li>
            `;
        }

        // Receptionist-specific tools
        if (this.isReceptionist && !this.isDoctor && !this.isNurse) {
            items += `
                <li class="nav-item">
                    <a class="nav-link ${currentPage === '/scheduling.html' ? 'active' : ''}" href="/scheduling.html">
                        <i class="fas fa-calendar-check me-1"></i>Scheduling
                    </a>
                </li>
            `;
        }

        // Admin-specific dropdown
        if (this.isAdmin) {
            items += `
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle ${(currentPage.includes('/admin') || currentPage.includes('/queryable') || currentPage === '/ai-documentation.html') ? 'active' : ''}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item ${currentPage === '/admin.html' ? 'active' : ''}" href="/admin.html">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a></li>
                        <li><a class="dropdown-item ${currentPage === '/admin-demo-data.html' ? 'active' : ''}" href="/admin-demo-data.html">
                            <i class="fas fa-database me-1"></i>Demo Data
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item ${currentPage === '/ai-documentation.html' ? 'active' : ''}" href="/ai-documentation.html">
                            <i class="fas fa-robot me-1"></i>AI Documentation
                        </a></li>
                        <li><a class="dropdown-item ${currentPage === '/medical-knowledge-search.html' ? 'active' : ''}" href="/medical-knowledge-search.html">
                            <i class="fas fa-book-medical me-1"></i>Medical Knowledge
                        </a></li>
                        <li><a class="dropdown-item ${currentPage === '/queryable-encryption-search.html' ? 'active' : ''}" href="/queryable-encryption-search.html">
                            <i class="fas fa-lock me-1"></i>Encryption Search
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/admin.html#users">
                            <i class="fas fa-users-cog me-1"></i>User Management
                        </a></li>
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
     * Poll unread staff conversations and update navbar badge
     */
    startStaffUnreadPolling() {
        const refresh = () => {
            fetch('/api/conversations/inbox/unread-count', { credentials: 'include' })
                .then(r => r.json())
                .then(j => {
                    if (!j || !j.success) return;
                    const n = j.count || 0;
                    const el = document.getElementById('navMessagesBadge');
                    if (el) el.textContent = n > 0 ? String(n) : '';
                })
                .catch(() => {});
        };
        // initial and interval
        refresh();
        this._staffUnreadInterval && clearInterval(this._staffUnreadInterval);
        this._staffUnreadInterval = setInterval(refresh, 15000);
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

    /**
     * Initialize Bootstrap dropdowns
     */
    initializeDropdowns() {
        // Wait for Bootstrap to be available
        if (typeof bootstrap === 'undefined') {
            console.warn('Bootstrap not loaded yet, retrying in 100ms...');
            setTimeout(() => this.initializeDropdowns(), 100);
            return;
        }

        // Initialize all dropdowns
        const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        console.log(`Initialized ${dropdownElementList.length} dropdowns`);
    }
}

// Global instance
window.secureHealthNavbar = new SecureHealthNavbar();

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.secureHealthNavbar.init();
});
