/**
 * Access Control Audit Tool
 * Diagnoses authentication and authorization issues
 */

class AccessAudit {
    constructor() {
        this.results = [];
        this.errors = [];
    }

    async runDiagnostics() {
        console.log('🔍 Starting Access Control Audit...\n');
        this.results = [];
        this.errors = [];

        await this.checkLocalStorage();
        await this.checkSessionAPI();
        await this.checkAPIAccess();
        await this.checkPageAccess();

        this.displayResults();
        return this.results;
    }

    async checkLocalStorage() {
        console.log('📦 Checking localStorage...');
        const userData = localStorage.getItem('securehealth_user');
        
        if (!userData) {
            this.logError('localStorage', 'No user data in localStorage');
            return;
        }

        try {
            const user = JSON.parse(userData);
            this.logSuccess('localStorage', 'User data found', {
                username: user.username,
                roles: user.roles
            });
        } catch (e) {
            this.logError('localStorage', 'Invalid JSON in localStorage', e.message);
        }
    }

    async checkSessionAPI() {
        console.log('🔐 Checking server-side session...');
        
        try {
            const response = await fetch('/api/user', {
                credentials: 'include',
                method: 'GET'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.user) {
                    this.logSuccess('session', 'Server session valid', {
                        username: data.user.username,
                        roles: data.user.roles
                    });
                } else {
                    this.logError('session', 'Server returned unsuccessful response', data);
                }
            } else {
                this.logError('session', `Server returned ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            this.logError('session', 'Failed to check session', error.message);
        }
    }

    async checkAPIAccess() {
        console.log('🌐 Checking API endpoint access...');
        
        const endpoints = [
            { url: '/api/appointments', name: 'Appointments API' },
            { url: '/api/patients', name: 'Patients API' },
            { url: '/api/medical-knowledge/stats', name: 'Medical Knowledge API' },
            { url: '/api/audit-logs', name: 'Audit Logs API' },
            { url: '/api/conversations/inbox', name: 'Messages API' },
        ];

        for (const endpoint of endpoints) {
            try {
                const response = await fetch(endpoint.url, {
                    credentials: 'include',
                    method: 'GET'
                });

                if (response.ok) {
                    this.logSuccess('api', `${endpoint.name} accessible`);
                } else if (response.status === 401) {
                    this.logError('api', `${endpoint.name} - Not authenticated (401)`);
                } else if (response.status === 403) {
                    this.logWarning('api', `${endpoint.name} - Access denied (403) - You may not have permission for this feature`);
                } else {
                    this.logWarning('api', `${endpoint.name} - Status ${response.status}`);
                }
            } catch (error) {
                this.logError('api', `${endpoint.name} - Request failed`, error.message);
            }
        }
    }

    async checkPageAccess() {
        console.log('📄 Checking page access permissions...');
        
        const pages = [
            'calendar',
            'patients',
            'patient-add',
            'scheduling',
            'medical-knowledge-search',
            'admin',
            'admin-demo-data',
            'queryable-encryption-search'
        ];

        for (const page of pages) {
            try {
                const response = await fetch(`/api/verify-access/${page}`, {
                    credentials: 'include',
                    method: 'GET'
                });

                const data = await response.json();

                if (data.success && data.hasAccess) {
                    this.logSuccess('page', `✓ ${page}.html - Access granted`);
                } else if (response.status === 403) {
                    this.logWarning('page', `⊘ ${page}.html - Access denied (insufficient permissions)`);
                } else if (response.status === 401) {
                    this.logError('page', `⊘ ${page}.html - Not authenticated`);
                } else {
                    this.logWarning('page', `⊘ ${page}.html - ${data.message || 'Unknown error'}`);
                }
            } catch (error) {
                this.logError('page', `${page}.html - Request failed`, error.message);
            }
        }
    }

    logSuccess(category, message, data = null) {
        const result = { category, status: 'success', message, data };
        this.results.push(result);
        console.log(`  ✅ ${message}`, data || '');
    }

    logWarning(category, message, data = null) {
        const result = { category, status: 'warning', message, data };
        this.results.push(result);
        console.warn(`  ⚠️  ${message}`, data || '');
    }

    logError(category, message, data = null) {
        const result = { category, status: 'error', message, data };
        this.results.push(result);
        this.errors.push(result);
        console.error(`  ❌ ${message}`, data || '');
    }

    displayResults() {
        console.log('\n' + '='.repeat(60));
        console.log('📊 AUDIT SUMMARY');
        console.log('='.repeat(60));

        const successCount = this.results.filter(r => r.status === 'success').length;
        const warningCount = this.results.filter(r => r.status === 'warning').length;
        const errorCount = this.errors.length;

        console.log(`✅ Success: ${successCount}`);
        console.log(`⚠️  Warnings: ${warningCount}`);
        console.log(`❌ Errors: ${errorCount}`);

        if (errorCount > 0) {
            console.log('\n' + '⚠️  CRITICAL ISSUES FOUND ⚠️'.padStart(40));
            console.log('─'.repeat(60));
            this.errors.forEach((error, i) => {
                console.log(`${i + 1}. [${error.category}] ${error.message}`);
                if (error.data) {
                    console.log(`   Details: ${JSON.stringify(error.data)}`);
                }
            });
        }

        if (errorCount === 0 && warningCount === 0) {
            console.log('\n🎉 All checks passed! Access control is working correctly.');
        } else if (errorCount === 0) {
            console.log('\n✓ Authentication is working. Some features may be restricted based on your role.');
        } else {
            console.log('\n🔧 RECOMMENDED ACTIONS:');
            console.log('1. If "Not authenticated" errors appear:');
            console.log('   - Clear cookies and localStorage');
            console.log('   - Log out and log back in');
            console.log('   - Check that session cookies are being sent');
            console.log('2. If "Access denied" errors appear:');
            console.log('   - Verify your user role has the required permissions');
            console.log('   - Check config/packages/security.yaml for role hierarchy');
            console.log('3. If API errors persist:');
            console.log('   - Check browser console for CORS errors');
            console.log('   - Verify the API endpoint exists');
            console.log('   - Check Symfony logs for server errors');
        }

        console.log('='.repeat(60) + '\n');

        return {
            total: this.results.length,
            success: successCount,
            warnings: warningCount,
            errors: errorCount,
            details: this.results
        };
    }

    generateReport() {
        return {
            timestamp: new Date().toISOString(),
            results: this.results,
            summary: {
                total: this.results.length,
                success: this.results.filter(r => r.status === 'success').length,
                warnings: this.results.filter(r => r.status === 'warning').length,
                errors: this.errors.length
            }
        };
    }
}

// Global instance
window.accessAudit = new AccessAudit();

// Quick access function
async function runAccessAudit() {
    return await window.accessAudit.runDiagnostics();
}

console.log('🔍 Access Audit Tool loaded. Run runAccessAudit() to diagnose issues.');

