/**
 * MongoDB Queryable Encryption Demo Wizard
 * RBAC-aware step-by-step wizard interface for demonstrating encryption capabilities
 */

class EncryptionDemoWizard {
    constructor() {
        this.wizardConfig = null;
        this.wizardSteps = [];
        this.completedSteps = [];
        this.currentUser = null;
        this.currentRole = null;
        this.currentStepIndex = 0;
        this.isLoading = false;
        
        this.init();
    }
    
    /**
     * Initialize the wizard
     */
    async init() {
        try {
            this.showLoading(true);
            await this.loadWizardData();
            this.renderWizard();
            this.setupEventListeners();
            this.showLoading(false);
        } catch (error) {
            console.error('Wizard initialization error:', error);
            this.showError('Failed to initialize wizard. Please try again.');
            this.showLoading(false);
        }
    }
    
    /**
     * Load wizard configuration and steps
     */
    async loadWizardData() {
        try {
            // Load wizard configuration
            const configResponse = await fetch('/api/wizard/config', {
                credentials: 'include'
            });
            
            if (!configResponse.ok) {
                throw new Error('Failed to load wizard configuration');
            }
            
            const configData = await configResponse.json();
            if (!configData.success) {
                throw new Error('Invalid wizard configuration response');
            }
            
            this.wizardConfig = configData.wizard;
            this.currentUser = configData.user;
            this.currentRole = configData.user.primaryRole;
            
            // Load wizard steps
            const stepsResponse = await fetch('/api/wizard/steps', {
                credentials: 'include'
            });
            
            if (!stepsResponse.ok) {
                throw new Error('Failed to load wizard steps');
            }
            
            const stepsData = await stepsResponse.json();
            if (!stepsData.success) {
                throw new Error('Invalid wizard steps response');
            }
            
            this.wizardSteps = stepsData.steps;
            
            // Load progress
            await this.loadProgress();
            
        } catch (error) {
            console.error('Error loading wizard data:', error);
            throw error;
        }
    }
    
    /**
     * Load wizard progress
     */
    async loadProgress() {
        try {
            const response = await fetch('/api/wizard/progress', {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.completedSteps = data.progress.completedSteps || [];
                }
            }
        } catch (error) {
            console.error('Error loading progress:', error);
        }
    }
    
    /**
     * Render the wizard interface
     */
    renderWizard() {
        if (!this.wizardConfig || !this.wizardSteps.length) {
            this.showError('No wizard data available');
            return;
        }
        
        // Update header with role-specific information
        this.updateHeader();
        
        // Update progress
        this.updateProgress();
        
        // Render step indicators
        this.renderStepIndicators();
        
        // Render current step
        this.renderCurrentStep();
        
        // Update navigation buttons
        this.updateNavigationButtons();
        
        // Apply role-specific styling
        this.applyRoleStyling();
    }
    
    /**
     * Update wizard header with role-specific information
     */
    updateHeader() {
        const roleBadge = document.getElementById('roleBadge');
        if (roleBadge && this.wizardConfig) {
            const roleIcon = this.getRoleIcon(this.currentRole);
            const roleName = this.getRoleDisplayName(this.currentRole);
            
            roleBadge.innerHTML = `
                <i class="${roleIcon} me-2"></i>${roleName}
            `;
            roleBadge.className = `role-badge role-${this.currentRole.toLowerCase().replace('role_', '')}`;
        }
    }
    
    /**
     * Update progress display
     */
    updateProgress() {
        const completedCount = this.completedSteps.length;
        const totalCount = this.wizardSteps.length;
        const percentage = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;
        
        // Update progress bar
        const progressFill = document.getElementById('progressFill');
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }
        
        // Update stats
        const completedStepsEl = document.getElementById('completedSteps');
        const progressPercentageEl = document.getElementById('progressPercentage');
        const currentStepNumberEl = document.getElementById('currentStepNumber');
        const totalStepsEl = document.getElementById('totalSteps');
        
        if (completedStepsEl) completedStepsEl.textContent = completedCount;
        if (progressPercentageEl) progressPercentageEl.textContent = `${percentage}%`;
        if (currentStepNumberEl) currentStepNumberEl.textContent = this.currentStepIndex + 1;
        if (totalStepsEl) totalStepsEl.textContent = totalCount;
    }
    
    /**
     * Render step indicators
     */
    renderStepIndicators() {
        const indicatorsContainer = document.getElementById('stepIndicators');
        if (!indicatorsContainer) return;
        
        indicatorsContainer.innerHTML = '';
        
        this.wizardSteps.forEach((step, index) => {
            const indicator = document.createElement('div');
            indicator.className = 'step-indicator';
            indicator.textContent = index + 1;
            
            if (index === this.currentStepIndex) {
                indicator.classList.add('active');
            } else if (this.completedSteps.includes(step.id)) {
                indicator.classList.add('completed');
            }
            
            indicator.addEventListener('click', () => {
                this.goToStep(index);
            });
            
            indicatorsContainer.appendChild(indicator);
        });
    }
    
    /**
     * Render current step
     */
    renderCurrentStep() {
        const container = document.getElementById('currentStepContainer');
        if (!container || !this.wizardSteps[this.currentStepIndex]) return;
        
        const step = this.wizardSteps[this.currentStepIndex];
        const isCompleted = this.completedSteps.includes(step.id);
        
        container.innerHTML = `
            <div class="current-step-card">
                <div class="step-header">
                    <div class="d-flex align-items-center">
                        <div class="step-icon ${this.getRoleClass(this.currentRole)}">
                            <i class="${step.icon}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h4 class="mb-1">${step.title}</h4>
                            <p class="mb-0 text-muted">${step.description}</p>
                        </div>
                        ${isCompleted ? '<div class="text-success"><i class="fas fa-check-circle fa-2x"></i></div>' : ''}
                    </div>
                </div>
                <div class="step-content">
                    ${step.screenshot ? `
                        <div class="step-screenshot mb-4">
                            <img src="${step.screenshot}" alt="${step.title} Screenshot" class="img-fluid rounded shadow-sm" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div class="screenshot-placeholder" style="display: none; background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; padding: 3rem; text-align: center; color: #6c757d;">
                                <i class="fas fa-image fa-3x mb-3"></i>
                                <p class="mb-0">Screenshot placeholder for ${step.title}</p>
                                <small>Add your screenshot at: ${step.screenshot}</small>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="step-features">
                        <h6><i class="fas fa-list me-2"></i>Key Features</h6>
                        <ul>
                            ${step.features.map(feature => `<li>${feature}</li>`).join('')}
                        </ul>
                    </div>
                    
                    <div class="encryption-demo">
                        <h6><i class="fas fa-lock me-2"></i>Encryption Demonstration</h6>
                        <p class="mb-0">${step.encryption_demo}</p>
                    </div>
                    
                    <div class="step-actions">
                        <a href="${step.url}" class="btn btn-wizard btn-wizard-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>Try This Feature
                        </a>
                        ${!isCompleted ? `
                            <button class="btn btn-wizard btn-wizard-success" onclick="wizard.markStepCompleted('${step.id}')">
                                <i class="fas fa-check me-2"></i>Mark Complete
                            </button>
                        ` : `
                            <button class="btn btn-wizard btn-wizard-secondary" disabled>
                                <i class="fas fa-check me-2"></i>Completed
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * Update navigation buttons
     */
    updateNavigationButtons() {
        const prevBtn = document.getElementById('prevStep');
        const nextBtn = document.getElementById('nextStep');
        
        if (prevBtn) {
            prevBtn.disabled = this.currentStepIndex === 0;
        }
        
        if (nextBtn) {
            const isLastStep = this.currentStepIndex === this.wizardSteps.length - 1;
            nextBtn.innerHTML = isLastStep ? 
                'Complete Wizard<i class="fas fa-check ms-2"></i>' : 
                'Next Step<i class="fas fa-arrow-right ms-2"></i>';
        }
    }
    
    /**
     * Go to specific step
     */
    goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < this.wizardSteps.length) {
            this.currentStepIndex = stepIndex;
            this.renderWizard();
        }
    }
    
    /**
     * Go to next step
     */
    nextStep() {
        if (this.currentStepIndex < this.wizardSteps.length - 1) {
            this.goToStep(this.currentStepIndex + 1);
        } else {
            // Wizard completed
            this.showSuccess('Congratulations! You have completed the MongoDB Queryable Encryption Demo Wizard!');
        }
    }
    
    /**
     * Go to previous step
     */
    prevStep() {
        if (this.currentStepIndex > 0) {
            this.goToStep(this.currentStepIndex - 1);
        }
    }
    
    /**
     * Mark a step as completed
     */
    async markStepCompleted(stepId) {
        try {
            const response = await fetch('/api/wizard/complete-step', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ stepId: stepId })
            });
            
            if (!response.ok) {
                throw new Error('Failed to mark step as completed');
            }
            
            const data = await response.json();
            if (data.success) {
                this.completedSteps = data.completedSteps || [];
                this.renderWizard(); // Re-render to update UI
                this.showSuccess(`Step "${this.getStepTitle(stepId)}" marked as completed!`);
            } else {
                throw new Error(data.message || 'Failed to complete step');
            }
        } catch (error) {
            console.error('Error marking step as completed:', error);
            this.showError('Failed to mark step as completed. Please try again.');
        }
    }
    
    /**
     * Restart wizard
     */
    restartWizard() {
        this.currentStepIndex = 0;
        this.completedSteps = [];
        this.renderWizard();
        this.showSuccess('Wizard restarted!');
    }
    
    /**
     * Get step title by ID
     */
    getStepTitle(stepId) {
        const step = this.wizardSteps.find(s => s.id === stepId);
        return step ? step.title : 'Unknown Step';
    }
    
    /**
     * Get role icon
     */
    getRoleIcon(role) {
        const icons = {
            'ROLE_ADMIN': 'fas fa-user-shield',
            'ROLE_DOCTOR': 'fas fa-user-md',
            'ROLE_NURSE': 'fas fa-user-nurse',
            'ROLE_RECEPTIONIST': 'fas fa-user-tie',
            'ROLE_PATIENT': 'fas fa-hospital-user',
            'ROLE_USER': 'fas fa-user'
        };
        return icons[role] || 'fas fa-user';
    }
    
    /**
     * Get role display name
     */
    getRoleDisplayName(role) {
        const names = {
            'ROLE_ADMIN': 'System Administrator',
            'ROLE_DOCTOR': 'Healthcare Provider',
            'ROLE_NURSE': 'Nursing Staff',
            'ROLE_RECEPTIONIST': 'Reception Staff',
            'ROLE_PATIENT': 'Patient',
            'ROLE_USER': 'General User'
        };
        return names[role] || 'User';
    }
    
    /**
     * Get role CSS class
     */
    getRoleClass(role) {
        return role.toLowerCase().replace('role_', '');
    }
    
    /**
     * Apply role-specific styling
     */
    applyRoleStyling() {
        const container = document.querySelector('.wizard-container');
        if (container) {
            container.className = `wizard-container role-specific-colors ${this.currentRole.toLowerCase().replace('role_', '')}`;
        }
    }
    
    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Previous step button
        const prevBtn = document.getElementById('prevStep');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this.prevStep());
        }
        
        // Next step button
        const nextBtn = document.getElementById('nextStep');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this.nextStep());
        }
        
        // Back to dashboard button
        const backBtn = document.getElementById('backToDashboard');
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                window.location.href = '/dashboard.html';
            });
        }
        
        // Restart wizard button
        const restartBtn = document.getElementById('restartWizard');
        if (restartBtn) {
            restartBtn.addEventListener('click', () => {
                this.restartWizard();
            });
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft' && this.currentStepIndex > 0) {
                this.prevStep();
            } else if (e.key === 'ArrowRight' && this.currentStepIndex < this.wizardSteps.length - 1) {
                this.nextStep();
            }
        });
    }
    
    /**
     * Show loading spinner
     */
    showLoading(show) {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) {
            spinner.style.display = show ? 'block' : 'none';
        }
        
        const stepContainer = document.getElementById('currentStepContainer');
        if (stepContainer) {
            stepContainer.style.display = show ? 'none' : 'block';
        }
        
        this.isLoading = show;
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const errorEl = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        
        if (errorEl && errorText) {
            errorText.textContent = message;
            errorEl.style.display = 'block';
            
            // Hide after 5 seconds
            setTimeout(() => {
                errorEl.style.display = 'none';
            }, 5000);
        }
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        const successEl = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        
        if (successEl && successText) {
            successText.textContent = message;
            successEl.style.display = 'block';
            
            // Hide after 3 seconds
            setTimeout(() => {
                successEl.style.display = 'none';
            }, 3000);
        }
    }
    
    /**
     * Get current wizard state
     */
    getState() {
        return {
            config: this.wizardConfig,
            steps: this.wizardSteps,
            completedSteps: this.completedSteps,
            currentStep: this.currentStepIndex,
            user: this.currentUser,
            role: this.currentRole,
            progress: {
                completed: this.completedSteps.length,
                total: this.wizardSteps.length,
                percentage: this.wizardSteps.length > 0 ? 
                    Math.round((this.completedSteps.length / this.wizardSteps.length) * 100) : 0
            }
        };
    }
}

// Global wizard instance
let wizard = null;

// Initialize wizard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    wizard = new EncryptionDemoWizard();
    
    // Make wizard globally accessible for debugging
    window.encryptionDemoWizard = wizard;
});

// Handle page visibility change to refresh data when user returns
document.addEventListener('visibilitychange', function() {
    if (!document.hidden && wizard) {
        wizard.renderWizard();
    }
});