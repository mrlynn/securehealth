<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wizard')]
class WizardController extends AbstractController
{
    /**
     * Get wizard configuration for user's role
     */
    #[Route('/config', name: 'api_wizard_config', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getWizardConfig(): JsonResponse
    {
        $user = $this->getUser();
        $roles = $user->getRoles();
        $primaryRole = $this->determinePrimaryRole($roles);
        
        $config = $this->getWizardConfigForRole($primaryRole, $user);
        
        return $this->json([
            'success' => true,
            'user' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $roles,
                'primaryRole' => $primaryRole
            ],
            'wizard' => $config
        ]);
    }
    
    /**
     * Get wizard steps for user's role
     */
    #[Route('/steps', name: 'api_wizard_steps', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getWizardSteps(): JsonResponse
    {
        $user = $this->getUser();
        $roles = $user->getRoles();
        $primaryRole = $this->determinePrimaryRole($roles);
        
        $steps = $this->getStepsForRole($primaryRole);
        
        return $this->json([
            'success' => true,
            'primaryRole' => $primaryRole,
            'steps' => $steps
        ]);
    }
    
    /**
     * Mark wizard step as completed
     */
    #[Route('/complete-step', name: 'api_wizard_complete_step', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function completeStep(): JsonResponse
    {
        $user = $this->getUser();
        $request = $this->container->get('request_stack')->getCurrentRequest();
        
        // Get stepId from JSON request body
        $data = json_decode($request->getContent(), true);
        $stepId = $data['stepId'] ?? null;
        
        if (!$stepId) {
            return $this->json([
                'success' => false,
                'message' => 'Step ID is required'
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Store completion in session for now (could be moved to database)
        $session = $request->getSession();
        $completedSteps = $session->get('wizard_completed_steps', []);
        
        // Only add if not already completed
        if (!in_array($stepId, $completedSteps)) {
            $completedSteps[] = $stepId;
            $session->set('wizard_completed_steps', $completedSteps);
        }
        
        return $this->json([
            'success' => true,
            'message' => 'Step completed successfully',
            'completedSteps' => $completedSteps
        ]);
    }
    
    /**
     * Get wizard progress
     */
    #[Route('/progress', name: 'api_wizard_progress', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getWizardProgress(): JsonResponse
    {
        $user = $this->getUser();
        $roles = $user->getRoles();
        $primaryRole = $this->determinePrimaryRole($roles);
        
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();
        $completedSteps = $session->get('wizard_completed_steps', []);
        
        $steps = $this->getStepsForRole($primaryRole);
        $totalSteps = count($steps);
        $completedCount = count(array_intersect($completedSteps, array_column($steps, 'id')));
        
        return $this->json([
            'success' => true,
            'primaryRole' => $primaryRole,
            'progress' => [
                'completed' => $completedCount,
                'total' => $totalSteps,
                'percentage' => $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0,
                'completedSteps' => $completedSteps
            ]
        ]);
    }
    
    private function determinePrimaryRole(array $roles): string
    {
        // Role hierarchy - more specific roles take precedence
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'ROLE_ADMIN';
        }
        if (in_array('ROLE_DOCTOR', $roles)) {
            return 'ROLE_DOCTOR';
        }
        if (in_array('ROLE_NURSE', $roles)) {
            return 'ROLE_NURSE';
        }
        if (in_array('ROLE_RECEPTIONIST', $roles)) {
            return 'ROLE_RECEPTIONIST';
        }
        if (in_array('ROLE_PATIENT', $roles)) {
            return 'ROLE_PATIENT';
        }
        
        return 'ROLE_USER';
    }
    
    private function getWizardConfigForRole(string $role, $user): array
    {
        switch ($role) {
            case 'ROLE_ADMIN':
                return [
                    'title' => 'Administrator Demonstration Wizard',
                    'description' => 'Explore MongoDB Queryable Encryption capabilities for system administrators',
                    'icon' => 'fas fa-user-shield',
                    'color' => 'primary',
                    'overview' => [
                        'title' => 'Admin Capabilities Overview',
                        'description' => 'As an administrator, you can explore the full power of MongoDB Queryable Encryption through system management, audit monitoring, and encryption testing.',
                    'features' => [
                        'System audit logs and monitoring',
                        'Demo data generation with encrypted fields',
                        'Medical knowledge base management',
                        'Encryption search capabilities testing',
                        'User management and access control',
                        'Patient portal administration and oversight'
                    ]
                    ],
                    'objectives' => [
                        'Understand encryption at the system level',
                        'Learn to monitor encrypted data access',
                        'Test queryable encryption features',
                        'Manage encrypted medical knowledge',
                        'Generate and manage encrypted demo data'
                    ]
                ];
                
            case 'ROLE_DOCTOR':
                return [
                    'title' => 'Doctor Demonstration Wizard',
                    'description' => 'Experience MongoDB Queryable Encryption in clinical workflows',
                    'icon' => 'fas fa-user-md',
                    'color' => 'success',
                    'overview' => [
                        'title' => 'Clinical Workflow Overview',
                        'description' => 'As a doctor, you have full access to encrypted patient data and clinical tools. Experience how MongoDB Queryable Encryption protects PHI while enabling efficient clinical workflows.',
                    'features' => [
                        'Complete patient medical records access',
                        'Encrypted diagnosis and medication data',
                        'AI-powered medical knowledge search',
                        'Clinical decision support tools',
                        'Drug interaction checking',
                        'Audit logs for patient care activities',
                        'Patient portal oversight and management'
                    ]
                    ],
                    'objectives' => [
                        'Learn to access encrypted patient PHI securely',
                        'Use medical knowledge base for clinical decisions',
                        'Understand role-based data visibility',
                        'Practice clinical workflows with encrypted data',
                        'Review audit trails for compliance'
                    ]
                ];
                
            case 'ROLE_NURSE':
                return [
                    'title' => 'Nurse Demonstration Wizard',
                    'description' => 'Explore MongoDB Queryable Encryption for nursing workflows',
                    'icon' => 'fas fa-user-nurse',
                    'color' => 'info',
                    'overview' => [
                        'title' => 'Nursing Workflow Overview',
                        'description' => 'As a nurse, you have limited access to patient PHI based on your scope of practice. Experience how MongoDB Queryable Encryption enforces these access controls.',
                    'features' => [
                        'Patient medical information (limited PHI)',
                        'Drug interaction checking for medication safety',
                        'Medical knowledge base (view-only)',
                        'Patient care notes (view-only)',
                        'Staff messaging system',
                        'Patient portal support and assistance'
                    ]
                    ],
                    'objectives' => [
                        'Understand nursing scope of practice in data access',
                        'Learn to check drug interactions safely',
                        'Practice patient care workflows',
                        'Use medical knowledge for patient care',
                        'Communicate securely with care team'
                    ]
                ];
                
            case 'ROLE_RECEPTIONIST':
                return [
                    'title' => 'Receptionist Demonstration Wizard',
                    'description' => 'Learn MongoDB Queryable Encryption for administrative workflows',
                    'icon' => 'fas fa-user-tie',
                    'color' => 'warning',
                    'overview' => [
                        'title' => 'Administrative Workflow Overview',
                        'description' => 'As a receptionist, you have access to basic patient demographics and administrative functions. Experience how MongoDB Queryable Encryption protects sensitive data while enabling efficient administrative workflows.',
                        'features' => [
                            'Basic patient demographics and contact info',
                            'Appointment scheduling and management',
                            'Insurance information management',
                            'Patient registration',
                            'Administrative communication'
                        ]
                    ],
                    'objectives' => [
                        'Learn administrative workflows with encrypted data',
                        'Understand data access limitations',
                        'Practice patient registration and scheduling',
                        'Manage insurance information securely',
                        'Communicate with patients and staff'
                    ]
                ];
                
            case 'ROLE_PATIENT':
                return [
                    'title' => 'Patient Portal Demonstration Wizard',
                    'description' => 'Experience secure patient portal with MongoDB Queryable Encryption',
                    'icon' => 'fas fa-hospital-user',
                    'color' => 'secondary',
                    'overview' => [
                        'title' => 'Patient Portal Overview',
                        'description' => 'As a patient, you can access your own medical records through a secure portal. Experience how MongoDB Queryable Encryption protects your health information while giving you control over your data.',
                        'features' => [
                            'View your own medical records',
                            'Schedule and manage appointments',
                            'Secure messaging with healthcare providers',
                            'Access to test results and medical history',
                            'Privacy controls and data access logs'
                        ]
                    ],
                    'objectives' => [
                        'Learn to access your medical records securely',
                        'Understand patient privacy rights',
                        'Practice appointment management',
                        'Use secure messaging with providers',
                        'Review your data access history'
                    ]
                ];
                
            default:
                return [
                    'title' => 'General User Demonstration Wizard',
                    'description' => 'Explore MongoDB Queryable Encryption capabilities',
                    'icon' => 'fas fa-user',
                    'color' => 'dark',
                    'overview' => [
                        'title' => 'General Overview',
                        'description' => 'Explore the general capabilities of MongoDB Queryable Encryption in healthcare applications.',
                        'features' => [
                            'Basic system navigation',
                            'Documentation access',
                            'General feature overview'
                        ]
                    ],
                    'objectives' => [
                        'Learn about MongoDB Queryable Encryption',
                        'Understand healthcare data security',
                        'Explore system capabilities'
                    ]
                ];
        }
    }
    
    private function getStepsForRole(string $role): array
    {
        switch ($role) {
            case 'ROLE_ADMIN':
                return [
                    [
                        'id' => 'admin_dashboard',
                        'title' => 'Admin Dashboard Overview',
                        'description' => 'Explore the admin dashboard and understand system monitoring capabilities',
                        'url' => '/admin.html',
                        'icon' => 'fas fa-tachometer-alt',
                        'screenshot' => '/images/wizard-screenshots/admin-dashboard.png',
                        'features' => [
                            'View system audit logs',
                            'Monitor user access patterns',
                            'Check system performance metrics',
                            'Review compliance reports'
                        ],
                        'encryption_demo' => 'See how audit logs track access to encrypted PHI data'
                    ],
                    [
                        'id' => 'demo_data_generation',
                        'title' => 'Demo Data Management',
                        'description' => 'Generate encrypted test data to demonstrate MongoDB Queryable Encryption',
                        'url' => '/admin-demo-data.html',
                        'icon' => 'fas fa-database',
                        'screenshot' => '/images/wizard-screenshots/admin-demo-data.png',
                        'features' => [
                            'Generate encrypted patient records',
                            'Create test medical data',
                            'View encryption status',
                            'Test data integrity'
                        ],
                        'encryption_demo' => 'Generate patient records with encrypted SSN, diagnosis, and medication fields'
                    ],
                    [
                        'id' => 'medical_knowledge_management',
                        'title' => 'Medical Knowledge Base Management',
                        'description' => 'Manage the AI-powered medical knowledge base with encrypted content',
                        'url' => '/medical-knowledge-search.html',
                        'icon' => 'fas fa-brain',
                        'screenshot' => '/images/wizard-screenshots/admin-medical-knowledge.png',
                        'features' => [
                            'Search medical knowledge',
                            'Manage clinical content',
                            'Test AI-powered search',
                            'Review knowledge base metrics'
                        ],
                        'encryption_demo' => 'Search encrypted medical knowledge using MongoDB vector search'
                    ],
                    [
                        'id' => 'encryption_search_testing',
                        'title' => 'Encryption Search Testing',
                        'description' => 'Test MongoDB Queryable Encryption search capabilities',
                        'url' => '/queryable-encryption-search.html',
                        'icon' => 'fas fa-search',
                        'screenshot' => '/images/wizard-screenshots/admin-encryption-search.png',
                        'features' => [
                            'Test encrypted field searches',
                            'Compare encrypted vs unencrypted queries',
                            'Verify encryption integrity',
                            'Performance testing'
                        ],
                        'encryption_demo' => 'Search encrypted patient data without decrypting the database'
                    ],
                    [
                        'id' => 'patient_management_admin',
                        'title' => 'Patient Management (Admin View)',
                        'description' => 'View patient management from an administrative perspective with filtered data access',
                        'url' => '/api/admin/patients',
                        'icon' => 'fas fa-users',
                        'screenshot' => '/images/wizard-screenshots/admin-patient-management.png',
                        'features' => [
                            'View basic patient information (no medical data)',
                            'Access insurance details for administrative purposes',
                            'Monitor data access patterns',
                            'Review patient data integrity',
                            'System-wide patient overview'
                        ],
                        'encryption_demo' => 'See how encrypted PHI is filtered for administrative access - medical data excluded for HIPAA compliance'
                    ]
                ];
                
            case 'ROLE_DOCTOR':
                return [
                    [
                        'id' => 'patient_records_full_access',
                        'title' => 'Complete Patient Records Access',
                        'description' => 'Access encrypted patient records with full PHI visibility',
                        'url' => '/patients.html',
                        'icon' => 'fas fa-user-md',
                        'screenshot' => '/images/wizard-screenshots/doctor-patient-records.png',
                        'features' => [
                            'View complete patient medical history',
                            'Access encrypted diagnosis data',
                            'View encrypted medication information',
                            'See patient SSN and insurance data',
                            'Review all clinical notes'
                        ],
                        'encryption_demo' => 'Access encrypted PHI fields (SSN, diagnosis, medications) with full doctor privileges'
                    ],
                    [
                        'id' => 'medical_knowledge_clinical',
                        'title' => 'Medical Knowledge for Clinical Decisions',
                        'description' => 'Use AI-powered medical knowledge for clinical decision support',
                        'url' => '/medical-knowledge-search.html',
                        'icon' => 'fas fa-brain',
                        'screenshot' => '/images/wizard-screenshots/doctor-medical-knowledge.png',
                        'features' => [
                            'Search medical knowledge base',
                            'Clinical decision support',
                            'Treatment guidelines access',
                            'Diagnostic criteria reference',
                            'Drug interaction checking'
                        ],
                        'encryption_demo' => 'Search encrypted medical knowledge using MongoDB vector search for clinical decisions'
                    ],
                    [
                        'id' => 'drug_interactions_doctor',
                        'title' => 'Drug Interaction Checking',
                        'description' => 'Check drug interactions using encrypted medication data',
                        'url' => '/medical-knowledge-search.html?tool=drug-interactions',
                        'icon' => 'fas fa-pills',
                        'screenshot' => '/images/wizard-screenshots/doctor-drug-interactions.png',
                        'features' => [
                            'Check patient medication interactions',
                            'Review drug compatibility',
                            'Access interaction databases',
                            'Generate interaction reports'
                        ],
                        'encryption_demo' => 'Query encrypted medication data to check for drug interactions'
                    ],
                    [
                        'id' => 'clinical_notes_management',
                        'title' => 'Clinical Notes Management',
                        'description' => 'Create and manage encrypted clinical notes',
                        'url' => '/patient-notes-demo.html',
                        'icon' => 'fas fa-notes-medical',
                        'screenshot' => '/images/wizard-screenshots/doctor-clinical-notes.png',
                        'features' => [
                            'Create encrypted clinical notes',
                            'Edit existing patient notes',
                            'Review note history',
                            'Manage note permissions'
                        ],
                        'encryption_demo' => 'Create and edit encrypted clinical notes with MongoDB Queryable Encryption'
                    ],
                    [
                        'id' => 'audit_logs_doctor',
                        'title' => 'Audit Logs for Patient Care',
                        'description' => 'Review audit logs for patient care activities',
                        'url' => '/admin.html',
                        'icon' => 'fas fa-file-alt',
                        'screenshot' => '/images/wizard-screenshots/doctor-audit-logs.png',
                        'features' => [
                            'View patient access logs',
                            'Review care activity history',
                            'Monitor PHI access patterns',
                            'Generate compliance reports'
                        ],
                        'encryption_demo' => 'Review audit trails showing access to encrypted patient data'
                    ]
                ];
                
            case 'ROLE_NURSE':
                return [
                    [
                        'id' => 'patient_records_nurse',
                        'title' => 'Patient Records (Nurse Access)',
                        'description' => 'Access patient records with nursing scope limitations',
                        'url' => '/patients.html',
                        'icon' => 'fas fa-user-nurse',
                        'screenshot' => '/images/wizard-screenshots/nurse-patient-records.png',
                        'features' => [
                            'View patient basic information',
                            'Access medical history (limited)',
                            'View current medications',
                            'See care instructions',
                            'No access to SSN or sensitive diagnoses'
                        ],
                        'encryption_demo' => 'Experience role-based access control with encrypted PHI - see what nurses can and cannot access'
                    ],
                    [
                        'id' => 'drug_interactions_nurse',
                        'title' => 'Drug Interaction Checking for Nurses',
                        'description' => 'Check drug interactions for medication administration safety',
                        'url' => '/medical-knowledge-search.html?tool=drug-interactions',
                        'icon' => 'fas fa-pills',
                        'screenshot' => '/images/wizard-screenshots/nurse-drug-interactions.png',
                        'features' => [
                            'Check medication interactions',
                            'Verify drug compatibility',
                            'Review administration guidelines',
                            'Access nursing protocols'
                        ],
                        'encryption_demo' => 'Query encrypted medication data for safe medication administration'
                    ],
                    [
                        'id' => 'medical_knowledge_nurse',
                        'title' => 'Medical Knowledge (View-Only)',
                        'description' => 'Access medical knowledge base with read-only permissions',
                        'url' => '/medical-knowledge-search.html',
                        'icon' => 'fas fa-book-medical',
                        'screenshot' => '/images/wizard-screenshots/nurse-medical-knowledge.png',
                        'features' => [
                            'Search medical knowledge (read-only)',
                            'View treatment guidelines',
                            'Access nursing protocols',
                            'Review clinical information'
                        ],
                        'encryption_demo' => 'Search encrypted medical knowledge with nursing-level permissions'
                    ],
                    [
                        'id' => 'patient_notes_view',
                        'title' => 'Patient Notes (View-Only)',
                        'description' => 'View patient clinical notes without editing permissions',
                        'url' => '/patient-notes-demo.html',
                        'icon' => 'fas fa-eye',
                        'screenshot' => '/images/wizard-screenshots/nurse-patient-notes.png',
                        'features' => [
                            'View clinical notes (read-only)',
                            'Review care instructions',
                            'See note history',
                            'Access nursing notes'
                        ],
                        'encryption_demo' => 'View encrypted clinical notes with read-only permissions'
                    ],
                    [
                        'id' => 'staff_messaging',
                        'title' => 'Staff Messaging System',
                        'description' => 'Communicate securely with doctors and other staff',
                        'url' => '/staff/messages',
                        'icon' => 'fas fa-envelope',
                        'screenshot' => '/images/wizard-screenshots/nurse-staff-messaging.png',
                        'features' => [
                            'Send secure messages to doctors',
                            'Receive care instructions',
                            'Coordinate patient care',
                            'Access message history'
                        ],
                        'encryption_demo' => 'Use encrypted messaging system for secure staff communication'
                    ]
                ];
                
            case 'ROLE_RECEPTIONIST':
                return [
                    [
                        'id' => 'patient_directory',
                        'title' => 'Patient Directory (Basic Info)',
                        'description' => 'Access basic patient demographic information',
                        'url' => '/patients.html',
                        'icon' => 'fas fa-address-book',
                        'features' => [
                            'View patient contact information',
                            'Access basic demographics',
                            'See appointment history',
                            'No access to medical data'
                        ],
                        'encryption_demo' => 'See how encrypted PHI is hidden from receptionist view while basic info remains accessible'
                    ],
                    [
                        'id' => 'appointment_scheduling',
                        'title' => 'Appointment Scheduling',
                        'description' => 'Schedule and manage patient appointments',
                        'url' => '/calendar.html',
                        'icon' => 'fas fa-calendar-check',
                        'features' => [
                            'Schedule new appointments',
                            'Reschedule existing appointments',
                            'Manage appointment cancellations',
                            'View doctor availability'
                        ],
                        'encryption_demo' => 'Schedule appointments while patient medical data remains encrypted and inaccessible'
                    ],
                    [
                        'id' => 'patient_registration',
                        'title' => 'Patient Registration',
                        'description' => 'Register new patients with encrypted data storage',
                        'url' => '/patient-add.html',
                        'icon' => 'fas fa-user-plus',
                        'features' => [
                            'Register new patients',
                            'Collect demographic information',
                            'Set up insurance information',
                            'Create encrypted patient records'
                        ],
                        'encryption_demo' => 'Create new patient records with automatic encryption of sensitive fields'
                    ],
                    [
                        'id' => 'insurance_management',
                        'title' => 'Insurance Information Management',
                        'description' => 'Manage patient insurance information',
                        'url' => '/patients.html',
                        'icon' => 'fas fa-credit-card',
                        'features' => [
                            'View insurance information',
                            'Update insurance details',
                            'Verify coverage',
                            'Process billing information'
                        ],
                        'encryption_demo' => 'Manage insurance data with encrypted storage and role-based access'
                    ]
                ];
                
            case 'ROLE_PATIENT':
                return [
                    [
                        'id' => 'patient_portal_access',
                        'title' => 'Patient Portal Access',
                        'description' => 'Access your personal medical records portal',
                        'url' => '/patient-portal/index.html',
                        'icon' => 'fas fa-hospital-user',
                        'features' => [
                            'View your medical records',
                            'Access appointment information',
                            'Review test results',
                            'Manage personal information'
                        ],
                        'encryption_demo' => 'Access your own encrypted medical records through secure patient portal'
                    ],
                    [
                        'id' => 'medical_records_view',
                        'title' => 'Your Medical Records',
                        'description' => 'View your personal medical history and records',
                        'url' => '/patient-portal/records.html',
                        'icon' => 'fas fa-file-medical',
                        'features' => [
                            'View medical history',
                            'Access test results',
                            'Review medications',
                            'See appointment history'
                        ],
                        'encryption_demo' => 'View your encrypted medical data with patient-level access controls'
                    ],
                    [
                        'id' => 'appointment_management',
                        'title' => 'Appointment Management',
                        'description' => 'Schedule and manage your appointments',
                        'url' => '/patient-portal/appointments.html',
                        'icon' => 'fas fa-calendar-alt',
                        'features' => [
                            'Schedule new appointments',
                            'Reschedule existing appointments',
                            'Cancel appointments',
                            'View appointment history'
                        ],
                        'encryption_demo' => 'Manage appointments while your medical data remains encrypted'
                    ],
                    [
                        'id' => 'secure_messaging',
                        'title' => 'Secure Messaging with Providers',
                        'description' => 'Communicate securely with your healthcare providers',
                        'url' => '/patient-portal/messages.html',
                        'icon' => 'fas fa-envelope',
                        'features' => [
                            'Send messages to doctors',
                            'Receive care instructions',
                            'Ask medical questions',
                            'Access message history'
                        ],
                        'encryption_demo' => 'Use encrypted messaging system for secure communication with healthcare providers'
                    ]
                ];
                
            default:
                return [
                    [
                        'id' => 'general_overview',
                        'title' => 'System Overview',
                        'description' => 'General overview of MongoDB Queryable Encryption capabilities',
                        'url' => '/documentation.html',
                        'icon' => 'fas fa-info-circle',
                        'features' => [
                            'Learn about MongoDB Queryable Encryption',
                            'Understand healthcare data security',
                            'Explore system capabilities'
                        ],
                        'encryption_demo' => 'General introduction to encrypted healthcare data management'
                    ]
                ];
        }
    }
}
