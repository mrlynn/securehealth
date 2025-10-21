/**
 * Dashboard JavaScript - Role-based dashboard content
 */

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

class DashboardManager {
    constructor() {
        this.user = null;
        this.role = null;
        this.init();
    }

    async init() {
        try {
            // Check authentication
            await this.checkAuth();
            
            // Load user info
            await this.loadUserInfo();
            
            // Load role-specific dashboard content
            await this.loadDashboardContent();
            
        } catch (error) {
            console.error('Dashboard initialization error:', error);
            this.showError('Failed to load dashboard. Please try logging in again.');
        }
    }

    async checkAuth() {
        const userData = localStorage.getItem('securehealth_user');
        if (!userData) {
            window.location.href = 'login.html';
            return;
        }

        try {
            this.user = JSON.parse(userData);
            this.role = this.getPrimaryRole(this.user.roles);
        } catch (error) {
            console.error('Error parsing user data:', error);
            localStorage.removeItem('securehealth_user');
            window.location.href = 'login.html';
        }
    }

    getPrimaryRole(roles) {
        if (roles.includes('ROLE_ADMIN')) return 'admin';
        if (roles.includes('ROLE_DOCTOR')) return 'doctor';
        if (roles.includes('ROLE_NURSE')) return 'nurse';
        if (roles.includes('ROLE_RECEPTIONIST')) return 'receptionist';
        if (roles.includes('ROLE_PATIENT')) return 'patient';
        return 'unknown';
    }

    async loadUserInfo() {
        // Set welcome message (navbar handles user info display)
        const welcomeMessages = {
            admin: 'Welcome to the SecureHealth Administration Dashboard',
            doctor: 'Welcome to your Clinical Dashboard',
            nurse: 'Welcome to your Medical Dashboard',
            receptionist: 'Welcome to your Reception Dashboard',
            patient: 'Welcome to your Patient Portal'
        };
        document.getElementById('welcomeMessage').textContent = welcomeMessages[this.role] || 'Welcome to your dashboard';
        
        // Update role badge
        const roleBadge = document.getElementById('roleBadge');
        if (roleBadge) {
            const roleLabels = {
                admin: 'Administrator',
                doctor: 'Doctor',
                nurse: 'Nurse',
                receptionist: 'Receptionist',
                patient: 'Patient'
            };
            roleBadge.textContent = roleLabels[this.role] || this.role;
        }
    }

    async loadDashboardContent() {
        const contentContainer = document.getElementById('dashboardContent');
        
        switch (this.role) {
            case 'admin':
                contentContainer.innerHTML = await this.getAdminDashboard();
                await this.loadAdminData();
                break;
            case 'doctor':
                contentContainer.innerHTML = await this.getDoctorDashboard();
                await this.loadDoctorData();
                break;
            case 'nurse':
                contentContainer.innerHTML = await this.getNurseDashboard();
                await this.loadNurseData();
                break;
            case 'receptionist':
                contentContainer.innerHTML = await this.getReceptionistDashboard();
                await this.loadReceptionistData();
                break;
            case 'patient':
                contentContainer.innerHTML = await this.getPatientDashboard();
                await this.loadPatientData();
                break;
            default:
                contentContainer.innerHTML = this.getErrorDashboard();
        }
    }

    async getAdminDashboard() {
        return `
            <div class="row">
                <!-- System Stats -->
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3 id="totalUsers">-</h3>
                            <p class="mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-injured fa-2x mb-2"></i>
                            <h3 id="totalPatients">-</h3>
                            <p class="mb-0">Total Patients</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <h3 id="totalAppointments">-</h3>
                            <p class="mb-0">Appointments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <img src="/images/securehealth-logo.png" alt="SecureHealth Logo" height="60" class="me-2">
                            <h3 id="auditLogs">-</h3>
                            <p class="mb-0">Audit Logs</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Quick Actions -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='admin.html'">
                                        <div class="card-body">
                                            <i class="fas fa-cog fa-2x text-primary mb-2"></i>
                                            <h6>System Settings</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='patients.html'">
                                        <div class="card-body">
                                            <i class="fas fa-user-injured fa-2x text-success mb-2"></i>
                                            <h6>Manage Patients</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='medical-knowledge-search.html'">
                                        <div class="card-body">
                                            <i class="fas fa-brain fa-2x text-info mb-2"></i>
                                            <h6>Medical Knowledge</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='encryption-search.html'">
                                        <div class="card-body">
                                            <i class="fas fa-lock fa-2x text-warning mb-2"></i>
                                            <h6>Encryption Demo</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='role-documentation.html'">
                                        <div class="card-body">
                                            <i class="fas fa-user-md fa-2x text-secondary mb-2"></i>
                                            <h6>My Documentation</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div id="recentActivity" class="recent-activity">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading recent activity...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async getDoctorDashboard() {
        return `
            <div class="row">
                <!-- Patient Stats -->
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-injured fa-2x mb-2"></i>
                            <h3 id="myPatients">-</h3>
                            <p class="mb-0">My Patients</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <h3 id="todayAppointments">-</h3>
                            <p class="mb-0">Today's Appointments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope fa-2x mb-2"></i>
                            <h3 id="unreadMessages">-</h3>
                            <p class="mb-0">Unread Messages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-stethoscope fa-2x mb-2"></i>
                            <h3 id="clinicalNotes">-</h3>
                            <p class="mb-0">Clinical Notes</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Quick Actions -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Clinical Tools</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='patients.html'">
                                        <div class="card-body">
                                            <i class="fas fa-user-injured fa-2x text-primary mb-2"></i>
                                            <h6>Manage Patients</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='medical-knowledge-search.html'">
                                        <div class="card-body">
                                            <i class="fas fa-brain fa-2x text-info mb-2"></i>
                                            <h6>Medical Knowledge</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='calendar.html'">
                                        <div class="card-body">
                                            <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                                            <h6>Appointments</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='staff-messages.html'">
                                        <div class="card-body">
                                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                                            <h6>Messages</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Patients -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-injured me-2"></i>Recent Patients</h5>
                        </div>
                        <div class="card-body">
                            <div id="recentPatients">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading patients...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async getNurseDashboard() {
        return `
            <div class="row">
                <!-- Stats -->
                <div class="col-md-4 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-injured fa-2x mb-2"></i>
                            <h3 id="assignedPatients">-</h3>
                            <p class="mb-0">Assigned Patients</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <h3 id="todayTasks">-</h3>
                            <p class="mb-0">Today's Tasks</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope fa-2x mb-2"></i>
                            <h3 id="unreadMessages">-</h3>
                            <p class="mb-0">Unread Messages</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Quick Actions -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Medical Tools</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='patients.html'">
                                        <div class="card-body">
                                            <i class="fas fa-user-injured fa-2x text-primary mb-2"></i>
                                            <h6>View Patients</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='medical-knowledge-search.html?tool=drug-interactions'">
                                        <div class="card-body">
                                            <i class="fas fa-pills fa-2x text-danger mb-2"></i>
                                            <h6>Drug Interactions</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='calendar.html'">
                                        <div class="card-body">
                                            <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                                            <h6>Schedule</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='staff-messages.html'">
                                        <div class="card-body">
                                            <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                                            <h6>Messages</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Tasks -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-tasks me-2"></i>Patient Tasks</h5>
                        </div>
                        <div class="card-body">
                            <div id="patientTasks">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading tasks...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async getReceptionistDashboard() {
        return `
            <div class="row">
                <!-- Stats -->
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <h3 id="todayAppointments">-</h3>
                            <p class="mb-0">Today's Appointments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-plus fa-2x mb-2"></i>
                            <h3 id="newPatients">-</h3>
                            <p class="mb-0">New Patients</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-phone fa-2x mb-2"></i>
                            <h3 id="pendingCalls">-</h3>
                            <p class="mb-0">Pending Calls</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt fa-2x mb-2"></i>
                            <h3 id="pendingForms">-</h3>
                            <p class="mb-0">Pending Forms</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Quick Actions -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='patients.html'">
                                        <div class="card-body">
                                            <i class="fas fa-user-injured fa-2x text-primary mb-2"></i>
                                            <h6>Manage Patients</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='calendar.html'">
                                        <div class="card-body">
                                            <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                                            <h6>Schedule</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='appointments.html'">
                                        <div class="card-body">
                                            <i class="fas fa-calendar-plus fa-2x text-info mb-2"></i>
                                            <h6>New Appointment</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card dashboard-card quick-action" onclick="window.location.href='patient-add.html?action=add'">
                                        <div class="card-body">
                                            <i class="fas fa-user-plus fa-2x text-warning mb-2"></i>
                                            <h6>Add Patient</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-day me-2"></i>Today's Schedule</h5>
                        </div>
                        <div class="card-body">
                            <div id="todaySchedule">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading schedule...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async getPatientDashboard() {
        return `
            <div class="row">
                <!-- Patient Info -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user me-2"></i>My Information</h5>
                        </div>
                        <div class="card-body">
                            <div id="patientInfo">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading your information...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="window.location.href='patient-portal/dashboard.html'">
                                    <i class="fas fa-tachometer-alt me-2"></i>My Dashboard
                                </button>
                                <button class="btn btn-success" onclick="window.location.href='patient-portal/appointments.html'">
                                    <i class="fas fa-calendar me-2"></i>My Appointments
                                </button>
                                <button class="btn btn-info" onclick="window.location.href='patient-portal/messages.html'">
                                    <i class="fas fa-envelope me-2"></i>Messages
                                </button>
                                <button class="btn btn-warning" onclick="window.location.href='patient-portal/records.html'">
                                    <i class="fas fa-file-medical me-2"></i>My Records
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Upcoming Appointments -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-check me-2"></i>Upcoming Appointments</h5>
                        </div>
                        <div class="card-body">
                            <div id="upcomingAppointments">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading appointments...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Messages -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-envelope me-2"></i>Recent Messages</h5>
                        </div>
                        <div class="card-body">
                            <div id="recentMessages">
                                <div class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading messages...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    getErrorDashboard() {
        return `
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Access Error</h4>
                        <p>Unable to determine your role. Please contact your administrator.</p>
                        <button class="btn btn-danger" onclick="window.location.href='/login.html'">Go to Login</button>
                    </div>
                </div>
            </div>
        `;
    }

    // Data loading methods for each role
    async loadAdminData() {
        try {
            const response = await fetch('/api/dashboard/data', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data.stats;
                document.getElementById('totalUsers').textContent = stats.totalUsers || '0';
                document.getElementById('totalPatients').textContent = stats.totalPatients || '0';
                document.getElementById('totalAppointments').textContent = stats.totalAppointments || '0';
                document.getElementById('auditLogs').textContent = stats.auditLogs || '0';

                // Load recent activity
                this.loadRecentActivity(data.data.recentActivity || []);
            }
        } catch (error) {
            console.error('Error loading admin data:', error);
        }
    }

    async loadDoctorData() {
        try {
            const data = await ApiUtils.get('/api/dashboard/data');
            
            if (data.success) {
                const stats = data.data.stats;
                document.getElementById('myPatients').textContent = stats.myPatients || '0';
                document.getElementById('todayAppointments').textContent = stats.todayAppointments || '0';
                document.getElementById('unreadMessages').textContent = stats.unreadMessages || '0';
                document.getElementById('clinicalNotes').textContent = stats.clinicalNotes || '0';

                // Load recent patients
                this.loadRecentPatients(data.data.recentPatients || []);
            }
        } catch (error) {
            console.error('Error loading doctor data:', error);
            this.showErrorMessage('Failed to load dashboard data: ' + error.message);
        }
    }

    async loadNurseData() {
        try {
            const response = await fetch('/api/dashboard/data', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data.stats;
                document.getElementById('assignedPatients').textContent = stats.assignedPatients || '0';
                document.getElementById('todayTasks').textContent = stats.todayTasks || '0';
                document.getElementById('unreadMessages').textContent = stats.unreadMessages || '0';

                // Load patient tasks
                this.loadPatientTasks(data.data.patientTasks || []);
            }
        } catch (error) {
            console.error('Error loading nurse data:', error);
        }
    }

    async loadReceptionistData() {
        try {
            const response = await fetch('/api/dashboard/data', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data.stats;
                document.getElementById('todayAppointments').textContent = stats.todayAppointments || '0';
                document.getElementById('newPatients').textContent = stats.newPatients || '0';
                document.getElementById('pendingCalls').textContent = stats.pendingCalls || '0';
                document.getElementById('pendingForms').textContent = stats.pendingForms || '0';

                // Load today's schedule
                this.loadTodaySchedule(data.data.todaySchedule || []);
            }
        } catch (error) {
            console.error('Error loading receptionist data:', error);
        }
    }

    async loadPatientData() {
        try {
            const response = await fetch('/api/dashboard/data', {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success) {
                this.displayPatientInfo(data.data);
                this.loadUpcomingAppointments(data.data.upcomingAppointments || []);
                this.loadRecentMessages(data.data.recentMessages || []);
            }
        } catch (error) {
            console.error('Error loading patient data:', error);
        }
    }

    // Helper methods for loading specific data
    loadRecentActivity(activities = []) {
        const container = document.getElementById('recentActivity');
        
        if (activities.length === 0) {
            container.innerHTML = '<p class="text-muted">No recent activity</p>';
            return;
        }

        const activitiesHtml = activities.map(activity => `
            <div class="list-group-item">
                <i class="fas fa-${this.getActivityIcon(activity.action)} text-${this.getActivityColor(activity.action)} me-2"></i>
                ${activity.description || activity.action}
                <small class="text-muted d-block">${new Date(activity.timestamp).toLocaleString()}</small>
            </div>
        `).join('');

        container.innerHTML = `<div class="list-group">${activitiesHtml}</div>`;
    }

    getActivityIcon(action) {
        const icons = {
            'PATIENT_CREATE': 'user-plus',
            'PATIENT_UPDATE': 'user-edit',
            'APPOINTMENT_CREATE': 'calendar-plus',
            'LOGIN': 'sign-in-alt',
            'LOGOUT': 'sign-out-alt',
            'AUDIT': 'shield-alt'
        };
        return icons[action] || 'circle';
    }

    getActivityColor(action) {
        const colors = {
            'PATIENT_CREATE': 'success',
            'PATIENT_UPDATE': 'primary',
            'APPOINTMENT_CREATE': 'info',
            'LOGIN': 'success',
            'LOGOUT': 'secondary',
            'AUDIT': 'warning'
        };
        return colors[action] || 'secondary';
    }

    loadRecentPatients(patients) {
        const container = document.getElementById('recentPatients');
        if (patients.length === 0) {
            container.innerHTML = '<p class="text-muted">No recent patients</p>';
            return;
        }

        const patientsHtml = patients.slice(0, 5).map(patient => `
            <div class="card patient-card mb-2">
                <div class="card-body py-2">
                    <h6 class="card-title mb-1">${patient.firstName} ${patient.lastName}</h6>
                    <small class="text-muted">${patient.email}</small>
                </div>
            </div>
        `).join('');

        container.innerHTML = patientsHtml;
    }

    loadPatientTasks(tasks = []) {
        const container = document.getElementById('patientTasks');
        
        if (tasks.length === 0) {
            container.innerHTML = '<p class="text-muted">No pending tasks</p>';
            return;
        }

        const tasksHtml = tasks.map(task => `
            <div class="list-group-item">
                <i class="fas fa-${this.getTaskIcon(task.type)} text-${this.getTaskColor(task.priority)} me-2"></i>
                ${task.title}
                <small class="text-muted d-block">Patient: ${task.patient}</small>
            </div>
        `).join('');

        container.innerHTML = `<div class="list-group">${tasksHtml}</div>`;
    }

    getTaskIcon(type) {
        const icons = {
            'medication_review': 'pills',
            'vital_signs': 'thermometer-half',
            'notes_update': 'file-medical',
            'appointment': 'calendar-check'
        };
        return icons[type] || 'circle';
    }

    getTaskColor(priority) {
        const colors = {
            'high': 'danger',
            'medium': 'warning',
            'low': 'info'
        };
        return colors[priority] || 'secondary';
    }

    loadTodaySchedule(schedule = []) {
        const container = document.getElementById('todaySchedule');
        
        if (schedule.length === 0) {
            container.innerHTML = '<p class="text-muted">No appointments today</p>';
            return;
        }

        const scheduleHtml = schedule.map(appointment => `
            <div class="list-group-item">
                <i class="fas fa-calendar-check text-primary me-2"></i>
                ${appointment.time} - ${appointment.doctor}
                <small class="text-muted d-block">Patient: ${appointment.patient}</small>
            </div>
        `).join('');

        container.innerHTML = `<div class="list-group">${scheduleHtml}</div>`;
    }

    displayPatientInfo(data) {
        const container = document.getElementById('patientInfo');
        container.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Personal Information</h6>
                    <p><strong>Name:</strong> ${data.patient.firstName} ${data.patient.lastName}</p>
                    <p><strong>Email:</strong> ${data.patient.email}</p>
                    <p><strong>Phone:</strong> ${data.patient.phoneNumber || 'Not provided'}</p>
                </div>
                <div class="col-md-6">
                    <h6>Medical Information</h6>
                    <p><strong>Medications:</strong> ${data.medications?.length || 0} active</p>
                    <p><strong>Insurance:</strong> ${data.insurance ? 'Active' : 'Not provided'}</p>
                    <p><strong>Last Updated:</strong> ${new Date(data.lastUpdated).toLocaleDateString()}</p>
                </div>
            </div>
        `;
    }

    loadUpcomingAppointments(appointments) {
        const container = document.getElementById('upcomingAppointments');
        if (appointments.length === 0) {
            container.innerHTML = '<p class="text-muted">No upcoming appointments</p>';
            return;
        }

        const appointmentsHtml = appointments.map(appt => `
            <div class="card appointment-card mb-2">
                <div class="card-body py-2">
                    <h6 class="card-title mb-1">${new Date(appt.scheduledAt).toLocaleDateString()}</h6>
                    <small class="text-muted">${new Date(appt.scheduledAt).toLocaleTimeString()}</small>
                </div>
            </div>
        `).join('');

        container.innerHTML = appointmentsHtml;
    }

    loadRecentMessages(messages = []) {
        const container = document.getElementById('recentMessages');
        
        if (messages.length === 0) {
            container.innerHTML = '<p class="text-muted">No recent messages</p>';
            return;
        }

        const messagesHtml = messages.map(message => `
            <div class="list-group-item">
                <i class="fas fa-envelope text-primary me-2"></i>
                ${message.subject || 'Message'}
                <small class="text-muted d-block">${new Date(message.createdAt).toLocaleString()}</small>
            </div>
        `).join('');

        container.innerHTML = `<div class="list-group">${messagesHtml}</div>`;
    }

    showError(message) {
        const container = document.getElementById('dashboardContent');
        container.innerHTML = `
            <div class="row">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Error</h4>
                        <p>${message}</p>
                        <button class="btn btn-danger" onclick="window.location.href='login.html'">Go to Login</button>
                    </div>
                </div>
            </div>
        `;
    }

    showErrorMessage(message) {
        // Create error message element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            max-width: 500px;
            margin: 0;
        `;
        errorDiv.innerHTML = `
            <strong>Error:</strong> ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()" style="float: right; background: none; border: none; font-size: 1.2em;">&times;</button>
        `;
        
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 10000);
    }
}

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', () => {
    new DashboardManager();
});

// Logout function
// Logout function is now handled by the navbar system
