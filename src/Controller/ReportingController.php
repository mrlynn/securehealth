<?php

namespace App\Controller;

use App\Document\AuditLog;
use App\Document\Patient;
use App\Document\Appointment;
use App\Document\MedicalKnowledge;
use App\Repository\AuditLogRepository;
use App\Repository\PatientRepository;
use App\Repository\AppointmentRepository;
use App\Repository\UserRepository;
use App\Repository\MedicalKnowledgeRepository;
use App\Service\AuditLogService;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reporting')]
#[IsGranted('ROLE_ADMIN')]
class ReportingController extends AbstractController
{
    private DocumentManager $documentManager;
    private PatientRepository $patientRepository;
    private AppointmentRepository $appointmentRepository;
    private UserRepository $userRepository;
    private AuditLogRepository $auditLogRepository;
    private MedicalKnowledgeRepository $medicalKnowledgeRepository;
    private AuditLogService $auditLogService;

    public function __construct(
        DocumentManager $documentManager,
        PatientRepository $patientRepository,
        AppointmentRepository $appointmentRepository,
        UserRepository $userRepository,
        AuditLogRepository $auditLogRepository,
        MedicalKnowledgeRepository $medicalKnowledgeRepository,
        AuditLogService $auditLogService
    ) {
        $this->documentManager = $documentManager;
        $this->patientRepository = $patientRepository;
        $this->appointmentRepository = $appointmentRepository;
        $this->userRepository = $userRepository;
        $this->auditLogRepository = $auditLogRepository;
        $this->medicalKnowledgeRepository = $medicalKnowledgeRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Reporting dashboard
     */
    #[Route('', name: 'app_reporting_dashboard')]
    public function dashboard(): Response
    {
        // Get high-level stats
        $patientCount = $this->patientRepository->count([]);
        $appointmentCount = $this->appointmentRepository->count([]);
        $userCount = $this->userRepository->count([]);
        $auditLogCount = $this->auditLogRepository->count([]);
        $knowledgeCount = $this->medicalKnowledgeRepository->count([]);
        
        // Activity by date (last 30 days)
        $startDate = new \DateTime('-30 days');
        $startMongoDate = new UTCDateTime($startDate);
        
        $auditLogs = $this->auditLogRepository->findByDateRange($startMongoDate, new UTCDateTime());
        
        // Group logs by date for chart
        $activityByDate = [];
        $reportTypes = [
            'patient_activity' => [],
            'user_activity' => [],
            'security_events' => []
        ];
        
        // Initialize date buckets
        for ($i = 0; $i < 30; $i++) {
            $date = (clone $startDate)->modify("+$i days");
            $dateKey = $date->format('Y-m-d');
            $activityByDate[$dateKey] = 0;
            
            foreach (array_keys($reportTypes) as $type) {
                $reportTypes[$type][$dateKey] = 0;
            }
        }
        
        // Fill with actual data
        foreach ($auditLogs as $log) {
            $logDate = $log->getCreatedAt()->toDateTime()->format('Y-m-d');
            
            if (isset($activityByDate[$logDate])) {
                $activityByDate[$logDate]++;
                
                // Categorize by type
                if (str_contains($log->getAction(), 'patient')) {
                    $reportTypes['patient_activity'][$logDate]++;
                } elseif (str_contains($log->getAction(), 'login') || 
                          str_contains($log->getAction(), 'password') ||
                          str_contains($log->getAction(), 'security')) {
                    $reportTypes['security_events'][$logDate]++;
                } else {
                    $reportTypes['user_activity'][$logDate]++;
                }
            }
        }
        
        // Convert to arrays for charts
        $chartData = [
            'dates' => array_keys($activityByDate),
            'activity' => array_values($activityByDate),
            'patient_activity' => array_values($reportTypes['patient_activity']),
            'user_activity' => array_values($reportTypes['user_activity']),
            'security_events' => array_values($reportTypes['security_events'])
        ];
        
        // Log access to reporting
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_dashboard'
            ]
        );
        
        return $this->render('admin/reporting/dashboard.html.twig', [
            'patientCount' => $patientCount,
            'appointmentCount' => $appointmentCount,
            'userCount' => $userCount,
            'auditLogCount' => $auditLogCount,
            'knowledgeCount' => $knowledgeCount,
            'chartData' => $chartData
        ]);
    }

    /**
     * Patient demographics report
     */
    #[Route('/patient-demographics', name: 'app_reporting_patient_demographics')]
    public function patientDemographics(Request $request): Response
    {
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_patient_demographics_report'
            ]
        );
        
        return $this->render('admin/reporting/patient_demographics.html.twig');
    }

    /**
     * Get patient demographics data for charts
     */
    #[Route('/patient-demographics-data', name: 'app_reporting_patient_demographics_data')]
    public function patientDemographicsData(): JsonResponse
    {
        // This would normally query the database
        // For demo, we'll create sample data
        
        $ageGroups = [
            '0-17' => 53,
            '18-29' => 142,
            '30-44' => 208,
            '45-59' => 186,
            '60-74' => 124,
            '75+' => 87
        ];
        
        $genders = [
            'Male' => 384,
            'Female' => 410,
            'Other' => 6
        ];
        
        $insuranceTypes = [
            'Private Insurance' => 415,
            'Medicare' => 164,
            'Medicaid' => 112,
            'Self-Pay' => 58,
            'Other' => 51
        ];
        
        $conditions = [
            'Hypertension' => 203,
            'Type 2 Diabetes' => 142,
            'Asthma' => 86,
            'Depression' => 98,
            'Anxiety' => 124,
            'COPD' => 47,
            'Coronary Artery Disease' => 78,
            'Obesity' => 165,
            'Cancer' => 32,
            'Arthritis' => 112
        ];
        
        return $this->json([
            'success' => true,
            'data' => [
                'ageGroups' => $ageGroups,
                'genders' => $genders,
                'insuranceTypes' => $insuranceTypes,
                'conditions' => $conditions
            ]
        ]);
    }

    /**
     * Appointment statistics report
     */
    #[Route('/appointment-statistics', name: 'app_reporting_appointment_statistics')]
    public function appointmentStatistics(Request $request): Response
    {
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_appointment_statistics_report'
            ]
        );
        
        return $this->render('admin/reporting/appointment_statistics.html.twig');
    }

    /**
     * Get appointment statistics data for charts
     */
    #[Route('/appointment-statistics-data', name: 'app_reporting_appointment_statistics_data')]
    public function appointmentStatisticsData(): JsonResponse
    {
        // Start and end dates for reports (last 6 months)
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-6 months');
        
        // For demo purposes, we'll create sample data
        // In a real app, this would query the actual appointments
        
        // Generate month labels
        $months = [];
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            $months[] = $currentDate->format('M Y');
            $currentDate->modify('+1 month');
        }
        
        // Generate appointment counts
        $scheduled = [125, 137, 146, 158, 142, 162]; 
        $completed = [112, 124, 131, 143, 129, 147];
        $cancelled = [8, 9, 10, 11, 9, 11];
        $noShow = [5, 4, 5, 4, 4, 4];
        
        // Appointment types
        $appointmentTypes = [
            'Annual Physical' => 184,
            'Follow-up' => 246, 
            'New Patient' => 92,
            'Specialist Referral' => 124,
            'Urgent Care' => 78,
            'Telehealth' => 146
        ];
        
        // Providers with most appointments
        $providerAppointments = [
            'Dr. Smith' => 142,
            'Dr. Johnson' => 128,
            'Dr. Williams' => 114,
            'Dr. Davis' => 98,
            'Dr. Miller' => 86,
            'Dr. Brown' => 72,
            'Dr. Wilson' => 63,
            'Dr. Lee' => 57
        ];
        
        // Appointment hour distribution
        $appointmentHours = [
            '8 AM' => 86,
            '9 AM' => 124,
            '10 AM' => 138,
            '11 AM' => 142,
            '12 PM' => 78,
            '1 PM' => 98,
            '2 PM' => 126,
            '3 PM' => 114,
            '4 PM' => 104,
            '5 PM' => 62
        ];
        
        return $this->json([
            'success' => true,
            'data' => [
                'months' => $months,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'noShow' => $noShow,
                'appointmentTypes' => $appointmentTypes,
                'providerAppointments' => $providerAppointments,
                'appointmentHours' => $appointmentHours
            ]
        ]);
    }

    /**
     * User activity report
     */
    #[Route('/user-activity', name: 'app_reporting_user_activity')]
    public function userActivity(Request $request): Response
    {
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_user_activity_report'
            ]
        );
        
        return $this->render('admin/reporting/user_activity.html.twig');
    }

    /**
     * Get user activity data for reports
     */
    #[Route('/user-activity-data', name: 'app_reporting_user_activity_data')]
    public function userActivityData(): JsonResponse
    {
        // For demo purposes, we'll create sample data
        
        // User logins by role
        $loginsByRole = [
            'Admin' => 246,
            'Doctor' => 842,
            'Nurse' => 1248,
            'Patient' => 3642,
            'Staff' => 1156
        ];
        
        // Activity by hour of day
        $activityByHour = [
            '12 AM' => 42,
            '1 AM' => 28,
            '2 AM' => 18,
            '3 AM' => 12,
            '4 AM' => 8,
            '5 AM' => 14,
            '6 AM' => 36,
            '7 AM' => 84,
            '8 AM' => 224,
            '9 AM' => 386,
            '10 AM' => 462,
            '11 AM' => 486,
            '12 PM' => 324,
            '1 PM' => 368,
            '2 PM' => 412,
            '3 PM' => 378,
            '4 PM' => 296,
            '5 PM' => 214,
            '6 PM' => 148,
            '7 PM' => 124,
            '8 PM' => 98,
            '9 PM' => 76,
            '10 PM' => 62,
            '11 PM' => 48
        ];
        
        // Most active users
        $mostActiveUsers = [
            'john.smith' => 842,
            'sarah.johnson' => 764,
            'david.williams' => 692,
            'lisa.miller' => 578,
            'robert.davis' => 546,
            'jennifer.wilson' => 512,
            'michael.brown' => 486,
            'elizabeth.jones' => 442
        ];
        
        // Action types
        $actionTypes = [
            'View Patient Record' => 2846,
            'Login' => 3624,
            'Update Patient Info' => 842,
            'Schedule Appointment' => 764,
            'Cancel Appointment' => 142,
            'View Test Results' => 1624,
            'Prescribe Medication' => 548,
            'Update User Settings' => 324
        ];
        
        return $this->json([
            'success' => true,
            'data' => [
                'loginsByRole' => $loginsByRole,
                'activityByHour' => $activityByHour,
                'mostActiveUsers' => $mostActiveUsers,
                'actionTypes' => $actionTypes
            ]
        ]);
    }

    /**
     * Medical knowledge usage report
     */
    #[Route('/knowledge-usage', name: 'app_reporting_knowledge_usage')]
    public function knowledgeUsage(Request $request): Response
    {
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_knowledge_usage_report'
            ]
        );
        
        return $this->render('admin/reporting/knowledge_usage.html.twig');
    }

    /**
     * Get knowledge usage data for reports
     */
    #[Route('/knowledge-usage-data', name: 'app_reporting_knowledge_usage_data')]
    public function knowledgeUsageData(): JsonResponse
    {
        // For demo purposes, we'll create sample data
        
        // Usage by category
        $usageByCategory = [
            'Medications' => 2846,
            'Conditions' => 2124,
            'Procedures' => 1842,
            'Lab Tests' => 1648,
            'Guidelines' => 986,
            'Allergies' => 764,
            'Interactions' => 642
        ];
        
        // Most viewed entries
        $mostViewed = [
            'Hypertension Treatment Guidelines' => 486,
            'Type 2 Diabetes Management' => 442,
            'COVID-19 Testing Protocol' => 412,
            'Antibiotic Selection Guide' => 378,
            'Drug Interactions Database' => 346,
            'Pregnancy Risk Categories' => 324,
            'Hepatitis C Treatment' => 298,
            'Cancer Screening Recommendations' => 286,
            'Vaccination Schedule' => 274,
            'Heart Failure Management' => 268
        ];
        
        // Usage by user role
        $usageByRole = [
            'Doctor' => 4286,
            'Nurse' => 2648,
            'Admin' => 486,
            'Staff' => 842,
            'Patient' => 1242
        ];
        
        // Search terms frequency
        $searchTerms = [
            'diabetes' => 348,
            'hypertension' => 324,
            'antibiotics' => 298,
            'covid' => 286,
            'pregnancy' => 274,
            'heart failure' => 246,
            'warfarin' => 228,
            'asthma' => 214,
            'depression' => 196,
            'vaccination' => 184
        ];
        
        return $this->json([
            'success' => true,
            'data' => [
                'usageByCategory' => $usageByCategory,
                'mostViewed' => $mostViewed,
                'usageByRole' => $usageByRole,
                'searchTerms' => $searchTerms
            ]
        ]);
    }

    /**
     * Security audit report
     */
    #[Route('/security-audit', name: 'app_reporting_security_audit')]
    public function securityAudit(Request $request): Response
    {
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_access',
            [
                'action' => 'view_security_audit_report'
            ]
        );
        
        return $this->render('admin/reporting/security_audit.html.twig');
    }

    /**
     * Get security audit data for reports
     */
    #[Route('/security-audit-data', name: 'app_reporting_security_audit_data')]
    public function securityAuditData(): JsonResponse
    {
        // For demo purposes, we'll create sample data
        
        // Recent security events (last 7 days)
        $recentEvents = [
            [
                'timestamp' => '2024-05-24 08:32:15',
                'user' => 'john.smith',
                'action' => 'failed_login_attempt',
                'details' => 'Invalid password',
                'ipAddress' => '192.168.1.100',
                'severity' => 'medium'
            ],
            [
                'timestamp' => '2024-05-23 14:22:48',
                'user' => 'admin',
                'action' => 'user_role_change',
                'details' => 'Changed user robert.davis role from ROLE_STAFF to ROLE_ADMIN',
                'ipAddress' => '192.168.1.10',
                'severity' => 'high'
            ],
            [
                'timestamp' => '2024-05-23 10:45:12',
                'user' => 'robert.davis',
                'action' => 'patient_record_access',
                'details' => 'Accessed patient record without treatment relationship',
                'ipAddress' => '192.168.1.120',
                'severity' => 'high'
            ],
            [
                'timestamp' => '2024-05-22 16:08:37',
                'user' => 'system',
                'action' => 'system_update',
                'details' => 'Security patches applied',
                'ipAddress' => 'localhost',
                'severity' => 'low'
            ],
            [
                'timestamp' => '2024-05-22 09:12:05',
                'user' => 'lisa.miller',
                'action' => 'bulk_export',
                'details' => 'Exported 250 patient records',
                'ipAddress' => '192.168.1.45',
                'severity' => 'medium'
            ]
        ];
        
        // Login attempts by status
        $loginStatus = [
            'Successful' => 4862,
            'Failed - Invalid Password' => 324,
            'Failed - Account Locked' => 42,
            'Failed - Unknown User' => 186
        ];
        
        // Access by IP address
        $accessByIP = [
            '192.168.1.10' => 842,
            '192.168.1.20' => 764,
            '192.168.1.30' => 686,
            '192.168.1.40' => 624,
            '192.168.1.50' => 548,
            '10.0.0.15' => 486,
            '10.0.0.25' => 424,
            'Others' => 2148
        ];
        
        // Severity distribution
        $severityDistribution = [
            'Low' => 3624,
            'Medium' => 842,
            'High' => 186,
            'Critical' => 24
        ];
        
        return $this->json([
            'success' => true,
            'data' => [
                'recentEvents' => $recentEvents,
                'loginStatus' => $loginStatus,
                'accessByIP' => $accessByIP,
                'severityDistribution' => $severityDistribution
            ]
        ]);
    }

    /**
     * Export data report as CSV
     */
    #[Route('/export/{reportType}', name: 'app_reporting_export')]
    public function exportReport(string $reportType, Request $request): Response
    {
        // Validate report type
        $validTypes = ['patient_demographics', 'appointment_statistics', 'user_activity', 'knowledge_usage', 'security_audit'];
        if (!in_array($reportType, $validTypes)) {
            throw $this->createNotFoundException('Invalid report type');
        }
        
        // Create a StreamedResponse
        $response = new StreamedResponse(function() use ($reportType) {
            $handle = fopen('php://output', 'w+');
            
            // Write CSV header
            switch($reportType) {
                case 'patient_demographics':
                    fputcsv($handle, ['Age Group', 'Count', 'Percentage']);
                    
                    // Sample data
                    $data = [
                        ['0-17', 53, '8.3%'],
                        ['18-29', 142, '22.3%'],
                        ['30-44', 208, '32.7%'],
                        ['45-59', 186, '29.2%'],
                        ['60-74', 124, '19.5%'],
                        ['75+', 87, '13.7%']
                    ];
                    break;
                    
                case 'appointment_statistics':
                    fputcsv($handle, ['Month', 'Scheduled', 'Completed', 'Cancelled', 'No-Show']);
                    
                    // Sample data
                    $data = [
                        ['Jan 2024', 125, 112, 8, 5],
                        ['Feb 2024', 137, 124, 9, 4],
                        ['Mar 2024', 146, 131, 10, 5],
                        ['Apr 2024', 158, 143, 11, 4],
                        ['May 2024', 142, 129, 9, 4],
                        ['Jun 2024', 162, 147, 11, 4]
                    ];
                    break;
                    
                case 'user_activity':
                    fputcsv($handle, ['User Role', 'Login Count', 'Percentage']);
                    
                    // Sample data
                    $data = [
                        ['Admin', 246, '4.3%'],
                        ['Doctor', 842, '14.6%'],
                        ['Nurse', 1248, '21.7%'],
                        ['Patient', 3642, '63.3%'],
                        ['Staff', 1156, '20.1%']
                    ];
                    break;
                    
                case 'knowledge_usage':
                    fputcsv($handle, ['Category', 'Views', 'Percentage']);
                    
                    // Sample data
                    $data = [
                        ['Medications', 2846, '26.2%'],
                        ['Conditions', 2124, '19.6%'],
                        ['Procedures', 1842, '17.0%'],
                        ['Lab Tests', 1648, '15.2%'],
                        ['Guidelines', 986, '9.1%'],
                        ['Allergies', 764, '7.0%'],
                        ['Interactions', 642, '5.9%']
                    ];
                    break;
                    
                case 'security_audit':
                    fputcsv($handle, ['Date', 'User', 'Action', 'Details', 'IP Address', 'Severity']);
                    
                    // Sample data
                    $data = [
                        ['2024-05-24 08:32:15', 'john.smith', 'failed_login_attempt', 'Invalid password', '192.168.1.100', 'medium'],
                        ['2024-05-23 14:22:48', 'admin', 'user_role_change', 'Changed user role', '192.168.1.10', 'high'],
                        ['2024-05-23 10:45:12', 'robert.davis', 'patient_record_access', 'Accessed patient record', '192.168.1.120', 'high'],
                        ['2024-05-22 16:08:37', 'system', 'system_update', 'Security patches applied', 'localhost', 'low'],
                        ['2024-05-22 09:12:05', 'lisa.miller', 'bulk_export', 'Exported 250 patient records', '192.168.1.45', 'medium']
                    ];
                    break;
            }
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            
            fclose($handle);
        });
        
        // Log the export
        $this->auditLogService->log(
            $this->getUser(),
            'reporting_export',
            [
                'action' => 'export_report',
                'reportType' => $reportType
            ]
        );
        
        // Set headers
        $filename = sprintf('%s_report_%s.csv', $reportType, date('Y-m-d'));
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        
        return $response;
    }
}