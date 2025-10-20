<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class RoleDocumentationController extends AbstractController
{
    /**
     * Get role-specific documentation content
     */
    #[Route('/role-documentation', name: 'api_role_documentation', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getRoleDocumentation(): JsonResponse
    {
        $user = $this->getUser();
        $roles = $user->getRoles();
        
        // Determine primary role
        $primaryRole = $this->determinePrimaryRole($roles);
        
        // Get role-specific documentation content
        $documentation = $this->getDocumentationForRole($primaryRole, $user);
        
        return $this->json([
            'success' => true,
            'user' => [
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $roles,
                'primaryRole' => $primaryRole
            ],
            'documentation' => $documentation
        ]);
    }
    
    /**
     * Get available features for user's role
     */
    #[Route('/role-features', name: 'api_role_features', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getRoleFeatures(): JsonResponse
    {
        $user = $this->getUser();
        $roles = $user->getRoles();
        $primaryRole = $this->determinePrimaryRole($roles);
        
        $features = $this->getFeaturesForRole($primaryRole);
        
        return $this->json([
            'success' => true,
            'primaryRole' => $primaryRole,
            'features' => $features
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
    
    private function getDocumentationForRole(string $role, $user): array
    {
        $baseUrl = '/role-documentation.html';
        
        switch ($role) {
            case 'ROLE_ADMIN':
                return [
                    'title' => 'Administrator Documentation',
                    'description' => 'Complete system administration guide for SecureHealth administrators.',
                    'url' => $baseUrl,
                    'sections' => [
                        'system_management' => 'System Management Capabilities',
                        'audit_monitoring' => 'Audit & Monitoring',
                        'data_management' => 'Data Management',
                        'security_compliance' => 'Security & Compliance'
                    ],
                    'quickStart' => [
                        'Access the Admin Dashboard to view system overview and audit logs',
                        'Use Demo Data Management to generate test patients and medical records',
                        'Explore the Medical Knowledge Base for AI-powered clinical decision support',
                        'Test Encryption Search capabilities to understand MongoDB Queryable Encryption',
                        'Monitor Patient Management to understand healthcare workflows'
                    ],
                    'capabilities' => [
                        'View comprehensive audit logs',
                        'Monitor system performance',
                        'Generate demo patient data',
                        'Manage medical knowledge base',
                        'Configure encryption settings',
                        'Export system data'
                    ]
                ];
                
            case 'ROLE_DOCTOR':
                return [
                    'title' => 'Doctor Documentation',
                    'description' => 'Complete clinical workflow guide for healthcare providers.',
                    'url' => $baseUrl,
                    'sections' => [
                        'clinical_workflow' => 'Clinical Workflow Navigation',
                        'patient_care' => 'Patient Care Capabilities',
                        'clinical_tools' => 'Clinical Tools',
                        'hipaa_compliance' => 'HIPAA Compliance & Security'
                    ],
                    'quickStart' => [
                        'Check your Appointment Calendar for the day\'s patients',
                        'Review patient records in Patient Management before appointments',
                        'Use Medical Knowledge Search for clinical decision support',
                        'Update patient records with new diagnoses, medications, or notes',
                        'Communicate with patients through the messaging system'
                    ],
                    'capabilities' => [
                        'View complete patient medical records',
                        'Access encrypted diagnosis and medications',
                        'View patient SSN and insurance information',
                        'Create and update patient records',
                        'Delete patient records when necessary',
                        'AI-powered medical knowledge search'
                    ]
                ];
                
            case 'ROLE_NURSE':
                return [
                    'title' => 'Nurse Documentation',
                    'description' => 'Patient care workflow guide for nursing staff.',
                    'url' => $baseUrl,
                    'sections' => [
                        'patient_care_navigation' => 'Patient Care Navigation',
                        'patient_care_capabilities' => 'Patient Care Capabilities',
                        'medication_management' => 'Medication Management',
                        'access_limitations' => 'Access Limitations'
                    ],
                    'quickStart' => [
                        'Review your Appointment Calendar for scheduled patients',
                        'Access patient records to review medical history and current medications',
                        'Use Medical Knowledge Search for drug interaction checks',
                        'Update patient care notes and medication administration records',
                        'Communicate with doctors through the messaging system'
                    ],
                    'capabilities' => [
                        'View patient basic information',
                        'Access patient diagnosis and medical history',
                        'View current medications and dosages',
                        'Read clinical notes and care instructions',
                        'Update patient care notes',
                        'Check for drug interactions'
                    ]
                ];
                
            case 'ROLE_RECEPTIONIST':
                return [
                    'title' => 'Receptionist Documentation',
                    'description' => 'Administrative workflow guide for front desk staff.',
                    'url' => $baseUrl,
                    'sections' => [
                        'administrative_navigation' => 'Administrative Navigation',
                        'administrative_capabilities' => 'Administrative Capabilities',
                        'scheduling_appointments' => 'Scheduling & Appointments',
                        'patient_registration' => 'Patient Registration'
                    ],
                    'quickStart' => [
                        'Check the Appointment Calendar for the day\'s schedule',
                        'Process patient check-ins and verify information',
                        'Update patient insurance information as needed',
                        'Schedule new appointments and manage cancellations',
                        'Send appointment reminders and follow-up communications'
                    ],
                    'capabilities' => [
                        'Schedule new patient appointments',
                        'Reschedule existing appointments',
                        'View doctor availability',
                        'Send appointment reminders',
                        'Manage appointment cancellations',
                        'Register new patients'
                    ]
                ];
                
            case 'ROLE_PATIENT':
                return [
                    'title' => 'Patient Portal Documentation',
                    'description' => 'Self-service guide for patients using the SecureHealth portal.',
                    'url' => $baseUrl,
                    'sections' => [
                        'patient_portal_navigation' => 'Patient Portal Navigation',
                        'portal_capabilities' => 'Patient Portal Capabilities',
                        'appointment_management' => 'Appointment Management',
                        'medical_records_access' => 'Medical Records Access'
                    ],
                    'quickStart' => [
                        'Access your My Profile to ensure your information is up to date',
                        'Check My Appointments to see your upcoming visits',
                        'Review My Medical Records to stay informed about your health',
                        'Use the Messages feature to communicate with your care team',
                        'Set up Notifications to receive appointment reminders'
                    ],
                    'capabilities' => [
                        'View upcoming appointments',
                        'Schedule new appointments',
                        'Reschedule existing appointments',
                        'Cancel appointments',
                        'Receive appointment reminders',
                        'View your medical history'
                    ]
                ];
                
            default:
                return [
                    'title' => 'User Documentation',
                    'description' => 'General user guide for SecureHealth.',
                    'url' => $baseUrl,
                    'sections' => [],
                    'quickStart' => [
                        'Contact your administrator for role-specific access',
                        'Review the general documentation',
                        'Explore available features based on your permissions'
                    ],
                    'capabilities' => [
                        'Access system based on your assigned role',
                        'View role-appropriate documentation',
                        'Use features according to your permissions'
                    ]
                ];
        }
    }
    
    private function getFeaturesForRole(string $role): array
    {
        switch ($role) {
            case 'ROLE_ADMIN':
                return [
                    'dashboard' => ['url' => '/admin.html', 'name' => 'Admin Dashboard', 'description' => 'System overview and audit logs'],
                    'demo_data' => ['url' => '/admin-demo-data.html', 'name' => 'Demo Data Management', 'description' => 'Generate and manage test data'],
                    'medical_knowledge' => ['url' => '/medical-knowledge-search.html', 'name' => 'Medical Knowledge Base', 'description' => 'AI-powered medical information'],
                    'encryption_search' => ['url' => '/queryable-encryption-search.html', 'name' => 'Encryption Search', 'description' => 'Test MongoDB encryption capabilities'],
                    'patients' => ['url' => '/patients.html', 'name' => 'Patient Management', 'description' => 'View and manage patient records']
                ];
                
            case 'ROLE_DOCTOR':
                return [
                    'patients' => ['url' => '/patients.html', 'name' => 'Patient Management', 'description' => 'Access all patient records and medical data'],
                    'calendar' => ['url' => '/calendar.html', 'name' => 'Appointment Calendar', 'description' => 'Manage patient appointments and schedules'],
                    'medical_knowledge' => ['url' => '/medical-knowledge-search.html', 'name' => 'Medical Knowledge', 'description' => 'AI-powered clinical decision support'],
                    'audit_logs' => ['url' => '/admin.html', 'name' => 'Audit Logs', 'description' => 'View patient access and care activity logs'],
                    'patient_add' => ['url' => '/patient-add.html', 'name' => 'Add New Patient', 'description' => 'Register new patients to the system']
                ];
                
            case 'ROLE_NURSE':
                return [
                    'patients' => ['url' => '/patients.html', 'name' => 'Patient Records', 'description' => 'View patient medical information'],
                    'calendar' => ['url' => '/calendar.html', 'name' => 'Appointment Calendar', 'description' => 'View patient schedules'],
                    'medical_knowledge' => ['url' => '/medical-knowledge-search.html', 'name' => 'Medical Knowledge', 'description' => 'Clinical decision support'],
                    'patient_add' => ['url' => '/patient-add.html', 'name' => 'Add New Patient', 'description' => 'Register new patients'],
                    'messages' => ['url' => '/messages.html', 'name' => 'Staff Messaging', 'description' => 'Communicate with doctors and staff']
                ];
                
            case 'ROLE_RECEPTIONIST':
                return [
                    'patients' => ['url' => '/patients.html', 'name' => 'Patient Directory', 'description' => 'View basic patient information'],
                    'calendar' => ['url' => '/calendar.html', 'name' => 'Appointment Scheduling', 'description' => 'Manage patient appointments'],
                    'patient_add' => ['url' => '/patient-add.html', 'name' => 'Patient Registration', 'description' => 'Register new patients'],
                    'insurance' => ['url' => '/patients.html', 'name' => 'Insurance Management', 'description' => 'View and update insurance information'],
                    'messages' => ['url' => '/messages.html', 'name' => 'Patient Communication', 'description' => 'Send appointment reminders']
                ];
                
            case 'ROLE_PATIENT':
                return [
                    'profile' => ['url' => '/patient-portal.html', 'name' => 'My Profile', 'description' => 'View and update your personal information'],
                    'appointments' => ['url' => '/patient-portal/appointments.html', 'name' => 'My Appointments', 'description' => 'Schedule and manage appointments'],
                    'records' => ['url' => '/patient-portal/records.html', 'name' => 'My Medical Records', 'description' => 'View your health information'],
                    'messages' => ['url' => '/patient-portal/messages.html', 'name' => 'Messages', 'description' => 'Secure communication with your care team'],
                    'notifications' => ['url' => '/patient-portal/notifications.html', 'name' => 'Notifications', 'description' => 'Appointment reminders and updates']
                ];
                
            default:
                return [
                    'documentation' => ['url' => '/documentation.html', 'name' => 'Documentation', 'description' => 'System documentation and guides'],
                    'help' => ['url' => '/help/', 'name' => 'Help Center', 'description' => 'Troubleshooting and support']
                ];
        }
    }
}
