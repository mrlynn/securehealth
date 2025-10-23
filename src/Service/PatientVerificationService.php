<?php

namespace App\Service;

use App\Document\Patient;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use Symfony\Component\Security\Core\User\UserInterface;

class PatientVerificationService
{
    private PatientRepository $patientRepository;
    private AuditLogService $auditLogService;

    public function __construct(
        PatientRepository $patientRepository,
        AuditLogService $auditLogService
    ) {
        $this->patientRepository = $patientRepository;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Verify patient identity using birth date only
     *
     * @param string $patientId Patient ID
     * @param string $birthDate Birth date in Y-m-d format
     * @param UserInterface $user User performing the verification
     * @return array Result with success status and patient data if verified
     */
    public function verifyPatientIdentity(
        string $patientId,
        string $birthDate,
        UserInterface $user
    ): array {
        try {
            // Get patient by ID
            $patient = $this->patientRepository->findByIdString($patientId);
            if (!$patient) {
                $this->logVerificationAttempt($user, $patientId, false, 'Patient not found');
                return [
                    'success' => false,
                    'message' => 'Patient not found',
                    'patient' => null
                ];
            }

            // Verify birth date
            $patientBirthDate = $patient->getBirthDate();
            if (!$patientBirthDate) {
                $this->logVerificationAttempt($user, $patientId, false, 'Patient birth date not available');
                return [
                    'success' => false,
                    'message' => 'Patient birth date not available',
                    'patient' => null
                ];
            }

            $expectedBirthDate = $patientBirthDate->toDateTime()->format('Y-m-d');
            if ($expectedBirthDate !== $birthDate) {
                $this->logVerificationAttempt($user, $patientId, false, 'Birth date mismatch');
                return [
                    'success' => false,
                    'message' => 'Birth date does not match patient records',
                    'patient' => null
                ];
            }


            // Verification successful
            $this->logVerificationAttempt($user, $patientId, true, 'Identity verified successfully');
            
            return [
                'success' => true,
                'message' => 'Patient identity verified successfully',
                'patient' => $patient
            ];

        } catch (\Exception $e) {
            $this->logVerificationAttempt($user, $patientId, false, 'Verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Verification failed due to system error',
                'patient' => null
            ];
        }
    }

    /**
     * Check if patient verification is required for the current user role
     *
     * @param UserInterface $user
     * @return bool
     */
    public function isVerificationRequired(UserInterface $user): bool
    {
        $roles = $user->getRoles();
        
        // Only doctors and nurses require verification for sensitive operations
        // This method is called in conjunction with isSensitiveOperation()
        // to determine if verification is needed for specific operations
        return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_NURSE', $roles);
    }

    /**
     * Log verification attempt for audit purposes
     *
     * @param UserInterface $user
     * @param string $patientId
     * @param bool $success
     * @param string $reason
     */
    private function logVerificationAttempt(
        UserInterface $user,
        string $patientId,
        bool $success,
        string $reason
    ): void {
        $this->auditLogService->logPatientAccess(
            $user,
            $success ? 'VERIFY_IDENTITY_SUCCESS' : 'VERIFY_IDENTITY_FAILED',
            $patientId,
            [
                'description' => 'Patient identity verification attempt',
                'success' => $success,
                'reason' => $reason,
                'verificationMethod' => 'birth_date_only'
            ]
        );
    }

    /**
     * Get verification requirements for display
     *
     * @return array
     */
    public function getVerificationRequirements(): array
    {
        return [
            'birthDate' => [
                'label' => 'Date of Birth',
                'format' => 'YYYY-MM-DD',
                'required' => true,
                'description' => 'Patient\'s date of birth as recorded in our system'
            ]
        ];
    }
}
