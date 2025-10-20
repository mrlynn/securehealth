/**
 * API Utility Functions
 * Provides consistent API calling with proper error handling and authentication
 */
class ApiUtils {
    static async makeApiCall(url, options = {}) {
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, finalOptions);
            
            // Handle authentication errors
            if (response.status === 401) {
                console.log('ApiUtils: 401 error detected, triggering auth check...');
                
                // Let the AuthSessionManager handle this
                if (window.authSessionManager) {
                    window.authSessionManager.forceSessionCheck();
                }
                
                throw new Error('Authentication required. Please log in again.');
            }
            
            // Handle other HTTP errors
            if (!response.ok) {
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                
                try {
                    const errorData = await response.json();
                    if (errorData.message) {
                        errorMessage = errorData.message;
                    }
                } catch (e) {
                    // If response is not JSON, try to get text
                    try {
                        const errorText = await response.text();
                        if (errorText && errorText !== response.statusText) {
                            errorMessage = errorText;
                        }
                    } catch (e2) {
                        // Ignore text parsing errors
                    }
                }
                
                throw new Error(errorMessage);
            }
            
            // Try to parse as JSON, but handle non-JSON responses gracefully
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                // Return response object for non-JSON responses
                return {
                    success: true,
                    data: await response.text(),
                    status: response.status
                };
            }
            
        } catch (error) {
            console.error('ApiUtils: API call failed:', error);
            
            // Re-throw with more context
            if (error.name === 'SyntaxError' && error.message.includes('JSON')) {
                throw new Error('Invalid response format from server');
            }
            
            throw error;
        }
    }
    
    static async get(url, options = {}) {
        return this.makeApiCall(url, { ...options, method: 'GET' });
    }
    
    static async post(url, data = null, options = {}) {
        const postOptions = {
            ...options,
            method: 'POST'
        };
        
        if (data) {
            postOptions.body = JSON.stringify(data);
        }
        
        return this.makeApiCall(url, postOptions);
    }
    
    static async put(url, data = null, options = {}) {
        const putOptions = {
            ...options,
            method: 'PUT'
        };
        
        if (data) {
            putOptions.body = JSON.stringify(data);
        }
        
        return this.makeApiCall(url, putOptions);
    }
    
    static async delete(url, options = {}) {
        return this.makeApiCall(url, { ...options, method: 'DELETE' });
    }
}

// Make it globally available
window.ApiUtils = ApiUtils;
