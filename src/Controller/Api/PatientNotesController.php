<?php

namespace App\Controller\Api;

use App\Document\Patient;
use App\Document\User;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Security\Voter\PatientVoter;

#[Route('/api/patients/{patientId}/notes', name: 'patient_notes_')]
class PatientNotesController extends AbstractController
{
    public function __construct(
        private PatientRepository $patientRepository,
        private AuditLogService $auditLogService,
        private ValidatorInterface $validator,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    /**
     * Get all notes for a patient
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function listNotes(string $patientId): JsonResponse
    {
        $patient = $this->getPatientById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::VIEW_NOTES, $patient);

        // Log the access
        $this->auditLogService->log(
            $this->getUser(),
            'patient_notes_view',
            [
                'action' => 'view_notes',
                'patientId' => $patientId,
                'description' => 'Viewed patient notes'
            ]
        );

        $notesHistory = $patient->getNotesHistory();
        
        // Convert UTCDateTime objects to strings for JSON serialization
        foreach ($notesHistory as &$note) {
            if ($note['createdAt'] instanceof UTCDateTime) {
                $note['createdAt'] = $note['createdAt']->toDateTime()->format('Y-m-d H:i:s');
            }
            if ($note['updatedAt'] instanceof UTCDateTime) {
                $note['updatedAt'] = $note['updatedAt']->toDateTime()->format('Y-m-d H:i:s');
            }
        }

        return $this->json([
            'success' => true,
            'notes' => $notesHistory,
            'total' => count($notesHistory)
        ]);
    }

    /**
     * Add a new note to a patient
     */
    #[Route('', name: 'add', methods: ['POST'])]
    public function addNote(string $patientId, Request $request): JsonResponse
    {
        $patient = $this->getPatientById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::ADD_NOTE, $patient);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return $this->json(['message' => 'Note content is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        // Add the note
        $patient->addNote(
            trim($data['content']),
            new ObjectId($user->getId()),
            $user->getUsername()
        );

        // Update the patient's updated timestamp
        $patient->setUpdatedAt(new UTCDateTime());

        // Save the patient
        $this->patientRepository->save($patient);

        // Log the action
        $this->auditLogService->log(
            $user,
            'patient_notes_add',
            [
                'action' => 'add_note',
                'patientId' => $patientId,
                'noteContent' => substr(trim($data['content']), 0, 100) . (strlen(trim($data['content'])) > 100 ? '...' : ''),
                'description' => 'Added new note to patient record'
            ]
        );

        return $this->json([
            'success' => true,
            'message' => 'Note added successfully',
            'note' => $patient->getNotesHistory()[count($patient->getNotesHistory()) - 1]
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing note
     */
    #[Route('/{noteId}', name: 'update', methods: ['PUT'])]
    public function updateNote(string $patientId, string $noteId, Request $request): JsonResponse
    {
        $patient = $this->getPatientById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::UPDATE_NOTE, $patient);

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return $this->json(['message' => 'Note content is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if note exists
        $existingNote = $patient->getNoteById($noteId);
        if (!$existingNote) {
            return $this->json(['message' => 'Note not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the note
        $success = $patient->updateNote(
            $noteId,
            trim($data['content']),
            new ObjectId($user->getId()),
            $user->getUsername()
        );

        if (!$success) {
            return $this->json(['message' => 'Failed to update note'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Update the patient's updated timestamp
        $patient->setUpdatedAt(new UTCDateTime());

        // Save the patient
        $this->patientRepository->save($patient);

        // Log the action
        $this->auditLogService->log(
            $user,
            'patient_notes_update',
            [
                'action' => 'update_note',
                'patientId' => $patientId,
                'noteId' => $noteId,
                'noteContent' => substr(trim($data['content']), 0, 100) . (strlen(trim($data['content'])) > 100 ? '...' : ''),
                'description' => 'Updated patient note'
            ]
        );

        return $this->json([
            'success' => true,
            'message' => 'Note updated successfully',
            'note' => $patient->getNoteById($noteId)
        ]);
    }

    /**
     * Delete a note
     */
    #[Route('/{noteId}', name: 'delete', methods: ['DELETE'])]
    public function deleteNote(string $patientId, string $noteId): JsonResponse
    {
        $patient = $this->getPatientById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::DELETE_NOTE, $patient);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        // Check if note exists
        $existingNote = $patient->getNoteById($noteId);
        if (!$existingNote) {
            return $this->json(['message' => 'Note not found'], Response::HTTP_NOT_FOUND);
        }

        // Delete the note
        $success = $patient->removeNote($noteId);

        if (!$success) {
            return $this->json(['message' => 'Failed to delete note'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Update the patient's updated timestamp
        $patient->setUpdatedAt(new UTCDateTime());

        // Save the patient
        $this->patientRepository->save($patient);

        // Log the action
        $this->auditLogService->log(
            $user,
            'patient_notes_delete',
            [
                'action' => 'delete_note',
                'patientId' => $patientId,
                'noteId' => $noteId,
                'description' => 'Deleted patient note'
            ]
        );

        return $this->json([
            'success' => true,
            'message' => 'Note deleted successfully'
        ]);
    }

    /**
     * Get a specific note
     */
    #[Route('/{noteId}', name: 'show', methods: ['GET'])]
    public function showNote(string $patientId, string $noteId): JsonResponse
    {
        $patient = $this->getPatientById($patientId);
        if (!$patient) {
            return $this->json(['message' => 'Patient not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(PatientVoter::VIEW_NOTES, $patient);

        $note = $patient->getNoteById($noteId);
        if (!$note) {
            return $this->json(['message' => 'Note not found'], Response::HTTP_NOT_FOUND);
        }

        // Convert UTCDateTime objects to strings for JSON serialization
        if ($note['createdAt'] instanceof UTCDateTime) {
            $note['createdAt'] = $note['createdAt']->toDateTime()->format('Y-m-d H:i:s');
        }
        if ($note['updatedAt'] instanceof UTCDateTime) {
            $note['updatedAt'] = $note['updatedAt']->toDateTime()->format('Y-m-d H:i:s');
        }

        // Log the access
        $this->auditLogService->log(
            $this->getUser(),
            'patient_notes_view',
            [
                'action' => 'view_note',
                'patientId' => $patientId,
                'noteId' => $noteId,
                'description' => 'Viewed specific patient note'
            ]
        );

        return $this->json([
            'success' => true,
            'note' => $note
        ]);
    }

    /**
     * Helper method to get patient by ID
     */
    private function getPatientById(string $id): ?Patient
    {
        try {
            return $this->patientRepository->findByIdString($id);
        } catch (\Exception $e) {
            return null;
        }
    }
}
