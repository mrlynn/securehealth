<?php

namespace App\Security\Voter;

use App\Document\MedicalKnowledge;
use App\Service\AuditLogService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class MedicalKnowledgeVoter extends Voter
{
    // Define constants for medical knowledge permissions
    public const VIEW = 'MEDICAL_KNOWLEDGE_VIEW';
    public const SEARCH = 'MEDICAL_KNOWLEDGE_SEARCH';
    public const CREATE = 'MEDICAL_KNOWLEDGE_CREATE';
    public const EDIT = 'MEDICAL_KNOWLEDGE_EDIT';
    public const DELETE = 'MEDICAL_KNOWLEDGE_DELETE';
    public const IMPORT = 'MEDICAL_KNOWLEDGE_IMPORT';
    public const CLINICAL_DECISION_SUPPORT = 'MEDICAL_KNOWLEDGE_CLINICAL_DECISION_SUPPORT';
    public const DRUG_INTERACTIONS = 'MEDICAL_KNOWLEDGE_DRUG_INTERACTIONS';
    public const TREATMENT_GUIDELINES = 'MEDICAL_KNOWLEDGE_TREATMENT_GUIDELINES';
    public const DIAGNOSTIC_CRITERIA = 'MEDICAL_KNOWLEDGE_DIAGNOSTIC_CRITERIA';
    public const VIEW_STATS = 'MEDICAL_KNOWLEDGE_VIEW_STATS';

    private AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        $supportedAttributes = [
            self::VIEW,
            self::SEARCH,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::IMPORT,
            self::CLINICAL_DECISION_SUPPORT,
            self::DRUG_INTERACTIONS,
            self::TREATMENT_GUIDELINES,
            self::DIAGNOSTIC_CRITERIA,
            self::VIEW_STATS,
        ];

        if (!in_array($attribute, $supportedAttributes)) {
            return false;
        }

        // For search, clinical decision support, drug interactions, treatment guidelines, 
        // diagnostic criteria, and stats we don't need a subject
        if (in_array($attribute, [
            self::SEARCH,
            self::CLINICAL_DECISION_SUPPORT,
            self::DRUG_INTERACTIONS,
            self::TREATMENT_GUIDELINES,
            self::DIAGNOSTIC_CRITERIA,
            self::VIEW_STATS
        ])) {
            return true;
        }

        // For other permissions we need a MedicalKnowledge object or null for creation
        return $subject instanceof MedicalKnowledge || $subject === null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        // User must be logged in
        if (!$user instanceof UserInterface) {
            return false;
        }

        $roles = $user->getRoles();

        // Audit the access attempt
        $this->auditLogService->log(
            $user,
            'MEDICAL_KNOWLEDGE_ACCESS',
            [
                'attribute' => $attribute,
                'subjectId' => $subject instanceof MedicalKnowledge ? (string)$subject->getId() : null,
                'granted' => null // Will update this after determining permission
            ]
        );

        // Check permission based on role and attribute
        $granted = $this->checkPermission($attribute, $roles, $subject, $user);

        // Update audit log with result
        $this->auditLogService->updateLastLog([
            'granted' => $granted
        ]);

        return $granted;
    }

    private function checkPermission(string $attribute, array $roles, $subject, UserInterface $user): bool
    {
        switch ($attribute) {
            case self::SEARCH:
                // Only doctors and admins can search medical knowledge
                return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_ADMIN', $roles);

            case self::CLINICAL_DECISION_SUPPORT:
                // Only doctors can get clinical decision support
                return in_array('ROLE_DOCTOR', $roles);

            case self::DRUG_INTERACTIONS:
                // Doctors and nurses can check drug interactions
                return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_NURSE', $roles);

            case self::TREATMENT_GUIDELINES:
                // Only doctors can access treatment guidelines
                return in_array('ROLE_DOCTOR', $roles);

            case self::DIAGNOSTIC_CRITERIA:
                // Only doctors can access diagnostic criteria
                return in_array('ROLE_DOCTOR', $roles);

            case self::VIEW:
                // Doctors and nurses can view medical knowledge
                if ($subject instanceof MedicalKnowledge) {
                    return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_NURSE', $roles);
                }
                return false;

            case self::VIEW_STATS:
                // Only doctors and admins can view statistics
                return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_ADMIN', $roles);

            case self::CREATE:
                // Only doctors and admins can create medical knowledge
                return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_ADMIN', $roles);

            case self::EDIT:
                // Only doctors and admins can edit medical knowledge
                if ($subject instanceof MedicalKnowledge) {
                    return in_array('ROLE_DOCTOR', $roles) || in_array('ROLE_ADMIN', $roles);
                }
                return false;

            case self::DELETE:
                // Only admins can delete medical knowledge
                return in_array('ROLE_ADMIN', $roles);

            case self::IMPORT:
                // Only admins can import medical knowledge
                return in_array('ROLE_ADMIN', $roles);

            default:
                return false;
        }
    }
}
