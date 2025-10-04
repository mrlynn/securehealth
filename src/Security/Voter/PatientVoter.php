<?php

namespace App\Security\Voter;

use App\Document\Patient;
use App\Service\AuditLogService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class PatientVoter extends Voter
{
    // Define constants for patient-related permissions
    public const VIEW = 'PATIENT_VIEW';
    public const CREATE = 'PATIENT_CREATE';
    public const EDIT = 'PATIENT_EDIT';
    public const DELETE = 'PATIENT_DELETE';
    public const VIEW_DIAGNOSIS = 'PATIENT_VIEW_DIAGNOSIS';
    public const EDIT_DIAGNOSIS = 'PATIENT_EDIT_DIAGNOSIS';
    public const VIEW_MEDICATIONS = 'PATIENT_VIEW_MEDICATIONS';
    public const EDIT_MEDICATIONS = 'PATIENT_EDIT_MEDICATIONS';
    public const VIEW_SSN = 'PATIENT_VIEW_SSN';
    public const VIEW_INSURANCE = 'PATIENT_VIEW_INSURANCE';
    public const EDIT_INSURANCE = 'PATIENT_EDIT_INSURANCE';
    public const VIEW_NOTES = 'PATIENT_VIEW_NOTES';
    public const EDIT_NOTES = 'PATIENT_EDIT_NOTES';
    public const ADD_NOTE = 'PATIENT_ADD_NOTE';
    public const UPDATE_NOTE = 'PATIENT_UPDATE_NOTE';
    public const DELETE_NOTE = 'PATIENT_DELETE_NOTE';
    public const PATIENT_VIEW_OWN = 'PATIENT_VIEW_OWN';
    public const PATIENT_EDIT_OWN = 'PATIENT_EDIT_OWN';

    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Only vote on Patient objects and supported attributes
        $supportedAttributes = [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::VIEW_DIAGNOSIS,
            self::EDIT_DIAGNOSIS,
            self::VIEW_MEDICATIONS,
            self::EDIT_MEDICATIONS,
            self::VIEW_SSN,
            self::VIEW_INSURANCE,
            self::EDIT_INSURANCE,
            self::VIEW_NOTES,
            self::EDIT_NOTES,
            self::ADD_NOTE,
            self::UPDATE_NOTE,
            self::DELETE_NOTE,
            self::PATIENT_VIEW_OWN,
            self::PATIENT_EDIT_OWN,
        ];

        if (!in_array($attribute, $supportedAttributes)) {
            return false;
        }

        // For CREATE and VIEW permissions we don't need a subject (for listing)
        if ($attribute === self::CREATE || $attribute === self::VIEW) {
            return true;
        }

        // For all other permissions we need a Patient object
        return $subject instanceof Patient;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // User must be logged in
        if (!$user instanceof UserInterface) {
            return false;
        }

        $roles = $user->getRoles();

        // Audit the access attempt - regardless of permission result
        if ($subject instanceof Patient) {
            $this->auditLogService->log(
                $user,
                'security_access',
                [
                    'attribute' => $attribute,
                    'patientId' => $subject->getId() ? (string)$subject->getId() : null,
                    'granted' => null // Will update this after determining permission
                ]
            );
        }

        // Check permission based on role and attribute
        $granted = $this->checkPermission($attribute, $roles, $subject, $user);

        // Update audit log with result
        if ($subject instanceof Patient) {
            $this->auditLogService->updateLastLog([
                'granted' => $granted
            ]);
        }

        return $granted;
    }

    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        // ROLE_ADMIN has limited access - can view basic patient info but NOT medical data
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->checkAdminPermissions($attribute);
        }

        // Handle specific permissions
        switch ($attribute) {
            case self::CREATE:
                // All authenticated healthcare staff can create patients
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::VIEW:
                // All authenticated healthcare staff can view basic patient info
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::EDIT:
                // Only doctors and nurses can edit patient basic info
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);
                       
            case self::DELETE:
                // Only doctors can delete patients
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_DIAGNOSIS:
            case self::VIEW_MEDICATIONS:
                // Only doctors and nurses can view medical data
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::EDIT_DIAGNOSIS:
            case self::EDIT_MEDICATIONS:
                // Only doctors can edit medical data
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_SSN:
                // Only doctors can view SSN
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW_INSURANCE:
                // All authenticated staff can view insurance
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::EDIT_INSURANCE:
                // Only receptionists and doctors can edit insurance
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_RECEPTIONIST', $roles);

            case self::VIEW_NOTES:
                // Doctors and nurses can view notes
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);

            case self::EDIT_NOTES:
            case self::ADD_NOTE:
            case self::UPDATE_NOTE:
            case self::DELETE_NOTE:
                // Only doctors can add, edit, update, or delete notes
                return in_array('ROLE_DOCTOR', $roles);

            case self::PATIENT_VIEW_OWN:
                // Patients can only view their own records
                if (!in_array('ROLE_PATIENT', $roles)) {
                    return false;
                }
                // Check if the patient is trying to access their own record
                if ($subject instanceof Patient && $user instanceof \App\Document\User) {
                    return $user->getPatientId() && (string)$user->getPatientId() === (string)$subject->getId();
                }
                return false;

            case self::PATIENT_EDIT_OWN:
                // Patients can only edit limited fields of their own records
                if (!in_array('ROLE_PATIENT', $roles)) {
                    return false;
                }
                // Check if the patient is trying to edit their own record
                if ($subject instanceof Patient && $user instanceof \App\Document\User) {
                    return $user->getPatientId() && (string)$user->getPatientId() === (string)$subject->getId();
                }
                return false;
        }

        // Default deny
        return false;
    }

    /**
     * Check admin-specific permissions
     * Admins can view basic patient info but NOT medical data
     */
    private function checkAdminPermissions(string $attribute): bool
    {
        switch ($attribute) {
            case self::VIEW:
                // Admins can view basic patient information
                return true;
                
            case self::CREATE:
            case self::EDIT:
            case self::DELETE:
                // Admins cannot create, edit, or delete patient records
                return false;
                
            case self::VIEW_DIAGNOSIS:
            case self::EDIT_DIAGNOSIS:
            case self::VIEW_MEDICATIONS:
            case self::EDIT_MEDICATIONS:
            case self::VIEW_SSN:
                // Admins cannot access medical data or SSN
                return false;
                
            case self::VIEW_INSURANCE:
            case self::EDIT_INSURANCE:
                // Admins can view insurance for administrative purposes
                return true;

            case self::VIEW_NOTES:
                // Admins cannot view medical notes
                return false;

            case self::EDIT_NOTES:
            case self::ADD_NOTE:
            case self::UPDATE_NOTE:
            case self::DELETE_NOTE:
                // Admins cannot edit medical notes
                return false;
        }
        
        return false;
    }
}