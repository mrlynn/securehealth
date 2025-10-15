/**
 * SecureHealth Authentication Guard
 * Provides consistent authentication and authorization checking for all pages
 */

class AuthGuard {
    constructor() {
        this.user = null;
        this.sessionVerified = false;
    }

    /**
     * Initialize authentication guard and verify session
     */
    async init() {
        await this.verifySession();
        return this.user !== null;
    }

    /**
     * Verify server-side session and sync with localStorage
     */
    async verifySession() {
        try {
            const response = await fetch('/api/user', {
                credentials: 'include',
                method: 'GET'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.user) {
                    this.user = data.user;
                    this.sessionVerified = true;
                    
                    // Sync with localStorage for navbar
                    localStorage.setItem('securehealth_user', JSON.stringify(data.user));
                    
                    console.log('✅ Session verified:', this.user.username);
                    return true;
                }
            }

            // Session verification failed
            console.warn('❌ Session verification failed:', response.status);
            this.clearSession();
            return false;

        } catch (error) {
            console.error('❌ Session verification error:', error);
            this.clearSession();
            return false;
        }
    }

    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return this.user !== null && this.sessionVerified;
    }

    /**
     * Check if user has required role(s)
     * @param {string|string[]} roles - Single role or array of roles
     * @returns {boolean}
     */
    hasRole(roles) {
        if (!this.user) return false;

        const requiredRoles = Array.isArray(roles) ? roles : [roles];
        const userRoles = this.user.roles || [];

        return requiredRoles.some(role => userRoles.includes(role));
    }

    /**
     * Check if user has any of the required roles (alias for hasRole)
     */
    hasAnyRole(roles) {
        return this.hasRole(roles);
    }

    /**
     * Check if user has all required roles
     */
    hasAllRoles(roles) {
        if (!this.user) return false;

        const requiredRoles = Array.isArray(roles) ? roles : [roles];
        const userRoles = this.user.roles || [];

        return requiredRoles.every(role => userRoles.includes(role));
    }

    /**
     * Verify access to a specific page
     * @param {string} pageName - Name of the page (without .html)
     * @returns {Promise<boolean>}
     */
    async verifyPageAccess(pageName) {
        try {
            const response = await fetch(`/api/verify-access/${pageName}`, {
                credentials: 'include',
                method: 'GET'
            });

            if (response.ok) {
                const data = await response.json();
                return data.success && data.hasAccess;
            }

            if (response.status === 401 || response.status === 403) {
                const data = await response.json();
                console.warn('Access denied to', pageName, ':', data.message);
                return false;
            }

            return false;

        } catch (error) {
            console.error('Error verifying page access:', error);
            return false;
        }
    }

    /**
     * Require authentication - redirect to login if not authenticated
     */
    async requireAuth(redirectUrl = '/login.html') {
        const isAuth = await this.init();
        if (!isAuth) {
            console.warn('❌ Authentication required - redirecting to login');
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }

    /**
     * Require specific role(s) - redirect if not authorized
     */
    async requireRole(roles, redirectUrl = '/patients.html') {
        const isAuth = await this.requireAuth();
        if (!isAuth) return false;

        if (!this.hasRole(roles)) {
            const roleNames = Array.isArray(roles) ? roles.join(' or ') : roles;
            console.warn('❌ Access denied - requires:', roleNames);
            alert(`Access denied. This page requires ${roleNames} permissions.`);
            window.location.href = redirectUrl;
            return false;
        }

        return true;
    }

    /**
     * Require page access verification
     */
    async requirePageAccess(pageName, redirectUrl = '/patients.html') {
        const isAuth = await this.requireAuth();
        if (!isAuth) return false;

        const hasAccess = await this.verifyPageAccess(pageName);
        if (!hasAccess) {
            console.warn('❌ Access denied to page:', pageName);
            alert(`Access denied. You don't have permission to access this page.`);
            window.location.href = redirectUrl;
            return false;
        }

        return true;
    }

    /**
     * Get current user
     */
    getUser() {
        return this.user;
    }

    /**
     * Clear session data
     */
    clearSession() {
        this.user = null;
        this.sessionVerified = false;
        localStorage.removeItem('securehealth_user');
    }

    /**
     * Logout and clear session
     */
    async logout() {
        try {
            await fetch('/api/logout', {
                method: 'POST',
                credentials: 'include'
            });
        } catch (error) {
            console.error('Logout API error:', error);
        } finally {
            this.clearSession();
            window.location.href = '/login.html';
        }
    }
}

// Global instance
window.authGuard = new AuthGuard();

// Helper function for quick authentication check
async function requireAuth(redirectUrl = '/login.html') {
    return await window.authGuard.requireAuth(redirectUrl);
}

// Helper function for role checking
async function requireRole(roles, redirectUrl = '/patients.html') {
    return await window.authGuard.requireRole(roles, redirectUrl);
}

// Helper function for page access checking
async function requirePageAccess(pageName, redirectUrl = '/patients.html') {
    return await window.authGuard.requirePageAccess(pageName, redirectUrl);
}

console.log('✅ AuthGuard loaded');

