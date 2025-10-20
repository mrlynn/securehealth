/**
 * Comprehensive Authentication Session Manager
 * Handles session persistence, automatic re-authentication, and proper error handling
 */
class AuthSessionManager {
    constructor() {
        this.sessionCheckInterval = null;
        this.isReauthenticating = false;
        this.maxRetries = 3;
        this.retryCount = 0;
        this.init();
    }

    init() {
        console.log('AuthSessionManager: Initializing...');
        this.setupSessionMonitoring();
        this.setupGlobalErrorHandling();
        this.checkSessionStatus();
    }

    setupSessionMonitoring() {
        // Check session every 30 seconds
        this.sessionCheckInterval = setInterval(() => {
            this.checkSessionStatus();
        }, 30000);

        // Check session on page focus
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkSessionStatus();
            }
        });

        // Check session on user activity
        ['mousemove', 'keydown', 'click'].forEach(event => {
            document.addEventListener(event, () => {
                this.checkSessionStatus();
            }, { passive: true, once: true });
        });
    }

    setupGlobalErrorHandling() {
        // Override fetch to handle auth errors globally
        const originalFetch = window.fetch;
        window.fetch = async (url, options = {}) => {
            try {
                const response = await originalFetch(url, options);
                
                // Check for authentication errors
                if (response.status === 401) {
                    console.log('AuthSessionManager: Detected 401 error, checking session...');
                    await this.handleAuthError();
                    return response; // Return original response for proper error handling
                }
                
                return response;
            } catch (error) {
                console.error('AuthSessionManager: Fetch error:', error);
                throw error;
            }
        };
    }

    async checkSessionStatus() {
        if (this.isReauthenticating) {
            return;
        }

        try {
            const response = await fetch('/api/health', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                console.log('AuthSessionManager: Session check failed, attempting re-authentication...');
                await this.handleAuthError();
            } else {
                console.log('AuthSessionManager: Session is valid');
                this.retryCount = 0; // Reset retry count on success
            }
        } catch (error) {
            console.error('AuthSessionManager: Session check error:', error);
            await this.handleAuthError();
        }
    }

    async handleAuthError() {
        if (this.isReauthenticating) {
            return;
        }

        this.isReauthenticating = true;
        
        try {
            const storedUser = localStorage.getItem('securehealth_user');
            if (!storedUser) {
                console.log('AuthSessionManager: No stored user, redirecting to login...');
                this.redirectToLogin();
                return;
            }

            const user = JSON.parse(storedUser);
            console.log('AuthSessionManager: Attempting re-authentication for:', user.email);

            // Get stored password for re-authentication
            const storedPassword = localStorage.getItem('securehealth_password');
            if (!storedPassword) {
                console.log('AuthSessionManager: No stored password available');
                this.handleReauthFailure();
                return;
            }

            // Attempt to re-authenticate
            const success = await this.reauthenticate(user.email, storedPassword);
            
            if (success) {
                console.log('AuthSessionManager: Re-authentication successful');
                this.retryCount = 0;
                
                // Show success message
                this.showNotification('Session restored successfully', 'success');
                
                // Reload current page to refresh data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                console.log('AuthSessionManager: Re-authentication failed');
                this.handleReauthFailure();
            }
        } catch (error) {
            console.error('AuthSessionManager: Re-authentication error:', error);
            this.handleReauthFailure();
        } finally {
            this.isReauthenticating = false;
        }
    }

    async reauthenticate(email, password) {
        try {
            const response = await fetch('/api/login', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    _username: email,
                    _password: password
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Update stored user data
                localStorage.setItem('securehealth_user', JSON.stringify(data.user));
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('AuthSessionManager: Re-authentication request failed:', error);
            return false;
        }
    }

    handleReauthFailure() {
        this.retryCount++;
        
        if (this.retryCount >= this.maxRetries) {
            console.log('AuthSessionManager: Max retries reached, clearing session...');
            this.clearSession();
            this.showNotification('Session expired. Please log in again.', 'error');
            this.redirectToLogin();
        } else {
            console.log(`AuthSessionManager: Retry ${this.retryCount}/${this.maxRetries}...`);
            this.showNotification(`Session issue detected. Retrying... (${this.retryCount}/${this.maxRetries})`, 'warning');
        }
    }

    clearSession() {
        try {
            localStorage.removeItem('securehealth_user');
            localStorage.removeItem('securehealth_password');
            // Clear any other auth-related data
            localStorage.removeItem('auth_token');
            localStorage.removeItem('session_data');
        } catch (error) {
            console.error('AuthSessionManager: Error clearing session:', error);
        }
    }

    redirectToLogin() {
        // Clear session first
        this.clearSession();
        
        // Redirect to enhanced login page
        window.location.href = '/login-enhanced.html';
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 16px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        `;

        // Set colors based on type
        switch (type) {
            case 'success':
                notification.style.backgroundColor = '#28a745';
                break;
            case 'error':
                notification.style.backgroundColor = '#dc3545';
                break;
            case 'warning':
                notification.style.backgroundColor = '#ffc107';
                notification.style.color = '#212529';
                break;
            default:
                notification.style.backgroundColor = '#17a2b8';
        }

        notification.textContent = message;
        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }, 5000);
    }

    // Public method to force session check
    async forceSessionCheck() {
        await this.checkSessionStatus();
    }

    // Public method to clear session and logout
    logout() {
        this.clearSession();
        window.location.href = '/api/logout';
    }

    // Cleanup method
    destroy() {
        if (this.sessionCheckInterval) {
            clearInterval(this.sessionCheckInterval);
        }
    }
}

// Initialize the session manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.authSessionManager = new AuthSessionManager();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading, wait for DOMContentLoaded
} else {
    // DOM is already loaded
    window.authSessionManager = new AuthSessionManager();
}

// Export for use in other scripts
window.AuthSessionManager = AuthSessionManager;
