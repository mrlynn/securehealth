/**
 * Patient Identity Verification Module
 * Handles patient verification workflow for HIPAA compliance
 */

class PatientVerification {
    constructor() {
        this.verificationCache = new Map(); // Cache verified patients
        this.verificationModal = null;
        this.currentPatientId = null;
        this.verificationCallback = null;
        this.cacheDuration = 5 * 60 * 1000; // 5 minutes

        // Load verification cache from sessionStorage
        this.loadVerificationCache();
    }

    /**
     * Initialize the verification system
     */
    init() {
        this.createVerificationModal();
        this.setupEventListeners();
    }

    /**
     * Create the verification modal
     */
    createVerificationModal() {
        const modalHtml = `
            <div class="modal fade" id="patientVerificationModal" tabindex="-1" aria-labelledby="patientVerificationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="patientVerificationModalLabel">
                                <i class="fas fa-shield-alt me-2"></i>Patient Identity Verification Required
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>HIPAA Compliance:</strong> Patient identity verification is required to access sensitive patient information.
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-flask me-2"></i>
                                <strong>Demo Mode:</strong> Patient verification data is pre-filled for demonstration purposes. In production, users would need to enter this information manually.
                            </div>
                            
                            <form id="patientVerificationForm">
                                <div class="mb-3">
                                    <label for="verificationBirthDate" class="form-label">
                                        <strong>Date of Birth</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="verificationBirthDate" required>
                                    <div class="form-text">
                                        <strong>Format:</strong> YYYY-MM-DD (e.g., 1989-06-19)<br>
                                        <small class="text-muted">Pre-filled for demo - matches the patient's actual birth date</small>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="convertDateBtn">
                                            <i class="fas fa-exchange-alt me-1"></i>Convert from MM/DD/YYYY
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="verificationLastFourSSN" class="form-label">
                                        <strong>Last 4 Digits of SSN</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="verificationLastFourSSN" 
                                           pattern="[0-9]{4}" maxlength="4" required>
                                    <div class="form-text">Pre-filled for demo - shows the last 4 digits of the patient's SSN</div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Security Notice:</strong> This information is used solely for patient identity verification and is logged for audit purposes.
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="verifyPatientBtn">
                                <i class="fas fa-check me-2"></i>Verify Identity
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if present
        const existingModal = document.getElementById('patientVerificationModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.verificationModal = new bootstrap.Modal(document.getElementById('patientVerificationModal'));
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Verify button click
        document.addEventListener('click', (e) => {
            if (e.target.id === 'verifyPatientBtn') {
                this.handleVerification();
            }
        });

        // Form submission
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'patientVerificationForm') {
                e.preventDefault();
                this.handleVerification();
            }
        });

        // Input validation
        document.addEventListener('input', (e) => {
            if (e.target.id === 'verificationLastFourSSN') {
                // Only allow digits
                e.target.value = e.target.value.replace(/\D/g, '');
            }
        });

        // Date conversion button
        document.addEventListener('click', (e) => {
            if (e.target.id === 'convertDateBtn') {
                this.showDateConverter();
            }
        });

        // Auto-convert date when modal is shown
        document.addEventListener('shown.bs.modal', (e) => {
            if (e.target.id === 'patientVerificationModal') {
                this.autoConvertDateIfNeeded();
            }
        });
    }

    /**
     * Check if patient verification is required
     */
    async checkVerificationRequired(patientId) {
        try {
            const response = await fetch(`/api/patients/${patientId}/verify/check`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include'
            });

            const data = await response.json();
            return data.verificationRequired || false;
        } catch (error) {
            console.error('Error checking verification requirements:', error);
            return false;
        }
    }

    /**
     * Verify patient identity
     */
    async verifyPatient(patientId, birthDate, lastFourSSN) {
        try {
            const response = await fetch(`/api/patients/${patientId}/verify`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    birthDate: birthDate,
                    lastFourSSN: lastFourSSN
                })
            });

            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error verifying patient:', error);
            return {
                success: false,
                message: 'Verification failed due to system error'
            };
        }
    }

    /**
     * Show verification modal
     */
    showVerificationModal(patientId, callback) {
        this.currentPatientId = patientId;
        this.verificationCallback = callback;

        // Clear form
        document.getElementById('verificationBirthDate').value = '';
        document.getElementById('verificationLastFourSSN').value = '';
        
        // Convert button is now always visible

        // Try to pre-fill patient data if available
        // Use setTimeout to ensure the modal is fully rendered before setting the values
        setTimeout(() => {
            this.prefillPatientData(patientId);
        }, 100);

        // Show modal
        this.verificationModal.show();
    }

    /**
     * Pre-fill birth date and SSN from patient data if available
     */
    async prefillPatientData(patientId) {
        try {
            // Check if we have patient data in the current page
            if (window.currentPatientsData) {
                const patient = window.currentPatientsData.find(p => p.id === patientId);
                console.log('Found patient for verification:', patient);
                
                if (patient) {
                    // Pre-fill birth date (already in YYYY-MM-DD format from API)
                    if (patient.birthDate) {
                        console.log('Patient birth date from API:', patient.birthDate);
                        const birthDateInput = document.getElementById('verificationBirthDate');
                        birthDateInput.value = patient.birthDate;
                        console.log('Set birth date input value to:', patient.birthDate);
                    }
                    
                    // Pre-fill last 4 digits of SSN (extract from full SSN)
                    if (patient.ssn) {
                        console.log('Patient SSN from API:', patient.ssn);
                        const ssnDigits = patient.ssn.replace(/\D/g, ''); // Remove non-digits
                        const lastFourSSN = ssnDigits.slice(-4); // Get last 4 digits
                        const ssnInput = document.getElementById('verificationLastFourSSN');
                        ssnInput.value = lastFourSSN;
                        console.log('Set SSN input value to:', lastFourSSN);
                    }
                }
            } else {
                console.log('No currentPatientsData available');
            }
        } catch (error) {
            console.log('Could not pre-fill patient data:', error);
        }
    }

    /**
     * Convert MM/DD/YYYY to YYYY-MM-DD format
     */
    convertDateFormat(dateString) {
        if (!dateString) return '';
        
        try {
            // Handle MM/DD/YYYY format
            if (dateString.includes('/')) {
                const parts = dateString.split('/');
                if (parts.length === 3) {
                    const month = parts[0].padStart(2, '0');
                    const day = parts[1].padStart(2, '0');
                    const year = parts[2];
                    return `${year}-${month}-${day}`;
                }
            }
            
            // Handle YYYY-MM-DD format (already correct)
            if (dateString.includes('-') && dateString.length === 10) {
                return dateString;
            }
            
            // Try to parse as Date and format
            const date = new Date(dateString);
            if (!isNaN(date.getTime())) {
                return date.toISOString().split('T')[0];
            }
            
            return dateString;
        } catch (error) {
            console.error('Date conversion error:', error);
            return dateString;
        }
    }

    /**
     * Show date converter dialog
     */
    showDateConverter() {
        const currentValue = document.getElementById('verificationBirthDate').value;
        const mmddyyyy = prompt(
            'Enter the date in MM/DD/YYYY format (e.g., 06/19/1989):\n\n' +
            'Current value: ' + (currentValue || 'empty'),
            currentValue ? this.convertToMMDDYYYY(currentValue) : ''
        );
        
        if (mmddyyyy) {
            const converted = this.convertDateFormat(mmddyyyy);
            if (converted) {
                document.getElementById('verificationBirthDate').value = converted;
                this.showSuccess('Date converted successfully!');
            } else {
                this.showError('Invalid date format. Please use MM/DD/YYYY');
            }
        }
    }

    /**
     * Convert YYYY-MM-DD to MM/DD/YYYY for display
     */
    convertToMMDDYYYY(dateString) {
        if (!dateString) return '';
        
        try {
            if (dateString.includes('-') && dateString.length === 10) {
                const parts = dateString.split('-');
                if (parts.length === 3) {
                    const year = parts[0];
                    const month = parts[1];
                    const day = parts[2];
                    return `${month}/${day}/${year}`;
                }
            }
            return dateString;
        } catch (error) {
            return dateString;
        }
    }

    /**
     * Auto-convert date format if needed when modal is shown
     */
    autoConvertDateIfNeeded() {
        const birthDateInput = document.getElementById('verificationBirthDate');
        if (birthDateInput && birthDateInput.value) {
            const currentValue = birthDateInput.value;
            console.log('Checking date format:', currentValue);
            
            // If the value contains slashes, it's in MM/DD/YYYY format
            if (currentValue.includes('/')) {
                console.log('Auto-converting date from MM/DD/YYYY to YYYY-MM-DD');
                const converted = this.convertDateFormat(currentValue);
                if (converted && converted !== currentValue) {
                    birthDateInput.value = converted;
                    console.log('Date converted to:', converted);
                }
            }
        }
    }

    /**
     * Handle verification form submission
     */
    async handleVerification() {
        let birthDate = document.getElementById('verificationBirthDate').value;
        const lastFourSSN = document.getElementById('verificationLastFourSSN').value;

        // Auto-convert date format if needed
        if (birthDate && birthDate.includes('/')) {
            console.log('Auto-converting date format from:', birthDate);
            birthDate = this.convertDateFormat(birthDate);
            console.log('Auto-converted to:', birthDate);
            document.getElementById('verificationBirthDate').value = birthDate;
        }

        // Validate inputs
        if (!birthDate || !lastFourSSN) {
            this.showError('Please fill in all required fields');
            return;
        }

        if (lastFourSSN.length !== 4) {
            this.showError('Please enter exactly 4 digits for the SSN');
            return;
        }

        // Disable button and show loading
        const verifyBtn = document.getElementById('verifyPatientBtn');
        const originalText = verifyBtn.innerHTML;
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';

        try {
            // Perform verification
            const result = await this.verifyPatient(this.currentPatientId, birthDate, lastFourSSN);

            if (result.success) {
                // Cache successful verification with the verification data
                this.verificationCache.set(this.currentPatientId, {
                    verified: true,
                    timestamp: Date.now(),
                    birthDate: birthDate,
                    lastFourSSN: lastFourSSN
                });
                
                // Save to sessionStorage
                this.saveVerificationCache();

                // Hide modal
                this.verificationModal.hide();

                // Execute callback
                if (this.verificationCallback) {
                    this.verificationCallback(true, {
                        birthDate: birthDate,
                        lastFourSSN: lastFourSSN
                    });
                }

                // Show success message
                this.showSuccess('Patient identity verified successfully');
            } else {
                this.showError(result.message || 'Verification failed');
            }
        } catch (error) {
            console.error('Verification error:', error);
            this.showError('Verification failed due to system error');
        } finally {
            // Re-enable button
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalText;
        }
    }

    /**
     * Load verification cache from sessionStorage
     */
    loadVerificationCache() {
        try {
            const cached = sessionStorage.getItem('patientVerificationCache');
            if (cached) {
                const cacheData = JSON.parse(cached);
                const now = Date.now();
                
                // Only load non-expired entries
                for (const [patientId, verification] of Object.entries(cacheData)) {
                    if (now - verification.timestamp < this.cacheDuration) {
                        this.verificationCache.set(patientId, verification);
                    }
                }
            }
        } catch (error) {
            console.warn('Failed to load verification cache from sessionStorage:', error);
        }
    }

    /**
     * Save verification cache to sessionStorage
     */
    saveVerificationCache() {
        try {
            const cacheData = {};
            for (const [patientId, verification] of this.verificationCache) {
                cacheData[patientId] = verification;
            }
            sessionStorage.setItem('patientVerificationCache', JSON.stringify(cacheData));
        } catch (error) {
            console.warn('Failed to save verification cache to sessionStorage:', error);
        }
    }

    /**
     * Check if patient is already verified
     */
    isPatientVerified(patientId) {
        const verification = this.verificationCache.get(patientId);
        if (!verification) return false;

        // Check if verification is still valid (5 minutes)
        if (Date.now() - verification.timestamp > this.cacheDuration) {
            this.verificationCache.delete(patientId);
            this.saveVerificationCache(); // Update sessionStorage
            return false;
        }

        return verification.verified;
    }

    /**
     * Get verified patient data
     */
    getVerifiedPatient(patientId) {
        const verification = this.verificationCache.get(patientId);
        if (!verification) return null;
        
        return {
            birthDate: verification.birthDate,
            lastFourSSN: verification.lastFourSSN
        };
    }

    /**
     * Clear verification cache
     */
    clearVerificationCache() {
        this.verificationCache.clear();
    }

    /**
     * Show error message
     */
    showError(message) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('#patientVerificationModal .alert-danger');
        existingAlerts.forEach(alert => alert.remove());

        // Create error alert
        const errorAlert = document.createElement('div');
        errorAlert.className = 'alert alert-danger';
        errorAlert.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${message}`;

        // Insert after form
        const form = document.getElementById('patientVerificationForm');
        form.parentNode.insertBefore(errorAlert, form.nextSibling);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorAlert.parentNode) {
                errorAlert.remove();
            }
        }, 5000);
    }

    /**
     * Show success message
     */
    showSuccess(message) {
        // Create success alert
        const successAlert = document.createElement('div');
        successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        successAlert.innerHTML = `
            <i class="fas fa-check-circle me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        document.body.appendChild(successAlert);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (successAlert.parentNode) {
                successAlert.remove();
            }
        }, 3000);
    }

    /**
     * Enhanced fetch with verification
     */
    async fetchWithVerification(url, options = {}) {
        // Extract patient ID from URL
        const patientIdMatch = url.match(/\/api\/patients\/([^\/]+)/);
        if (!patientIdMatch) {
            return fetch(url, options);
        }

        const patientId = patientIdMatch[1];

        // Check if verification is required
        const verificationRequired = await this.checkVerificationRequired(patientId);
        if (!verificationRequired) {
            return fetch(url, options);
        }

        // Check if already verified
        if (this.isPatientVerified(patientId)) {
            // Add verification headers to the request
            const verifiedPatient = this.getVerifiedPatient(patientId);
            if (verifiedPatient) {
                const headers = new Headers(options.headers || {});
                headers.set('X-Patient-Birth-Date', verifiedPatient.birthDate);
                headers.set('X-Patient-Last-Four-SSN', verifiedPatient.lastFourSSN);
                options.headers = headers;
            }
            return fetch(url, options);
        }

        // Show verification modal
        return new Promise((resolve, reject) => {
            this.showVerificationModal(patientId, async (success, patientData) => {
                if (success) {
                    try {
                        // Add verification headers to the request
                        const headers = new Headers(options.headers || {});
                        headers.set('X-Patient-Birth-Date', patientData.birthDate);
                        headers.set('X-Patient-Last-Four-SSN', patientData.lastFourSSN);
                        options.headers = headers;
                        
                        const response = await fetch(url, options);
                        resolve(response);
                    } catch (error) {
                        reject(error);
                    }
                } else {
                    reject(new Error('Patient verification failed'));
                }
            });
        });
    }
}

// Create global instance
window.patientVerification = new PatientVerification();

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.patientVerification.init();
});
