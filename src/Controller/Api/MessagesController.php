<?php

namespace App\Controller\Api;

use App\Document\Message;
use App\Repository\MessageRepository;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use MongoDB\BSON\ObjectId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/messages')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessagesController extends AbstractController
{
    public function __construct(
        private MessageRepository $messageRepository,
        private PatientRepository $patientRepository,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Staff: Inbox for messages sent to staff
     */
    #[Route('/inbox', name: 'messages_staff_inbox', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function staffInbox(UserInterface $user, Request $request): JsonResponse
    {
        $roles = $user->getRoles();
        // Limit to care team roles
        $staffRoles = array_values(array_intersect($roles, ['ROLE_DOCTOR', 'ROLE_NURSE']));
        try {
            $messages = $this->messageRepository->findForStaff($staffRoles, 200);
            $data = array_map(fn($m) => $m->toArray(), $messages);
            return $this->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Failed to load inbox: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Staff: Unread count
     */
    #[Route('/inbox/unread-count', name: 'messages_staff_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function staffUnreadCount(UserInterface $user): JsonResponse
    {
        $roles = $user->getRoles();
        $staffRoles = array_values(array_intersect($roles, ['ROLE_DOCTOR', 'ROLE_NURSE']));
        try {
            $count = $this->messageRepository->countUnreadForStaff($staffRoles);
            return $this->json(['success' => true, 'data' => ['unread' => $count]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Failed to get unread count: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Staff: Mark message read
     */
    #[Route('/{id}/read', name: 'messages_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_NURSE')]
    public function markRead(string $id, UserInterface $user): JsonResponse
    {
        try {
            $objectId = new ObjectId($id);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Invalid message ID'], Response::HTTP_BAD_REQUEST);
        }

        $message = $this->messageRepository
            ->findByPatient(new ObjectId('000000000000000000000000'));
        // Quick fetch via DocumentManager repository
        $doc = $this->getDoctrine()->getManager()->getRepository(\App\Document\Message::class)->find($objectId);
        if (!$doc) {
            return $this->json(['success' => false, 'message' => 'Message not found'], Response::HTTP_NOT_FOUND);
        }

        $this->messageRepository->markReadByStaff($doc, true, true);
        return $this->json(['success' => true]);
    }
    /**
     * Staff: List messages for a specific patient
     */
    #[Route('/patient/{patientId}', name: 'messages_list_by_patient', methods: ['GET'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function listByPatient(string $patientId, UserInterface $user): JsonResponse
    {
        try {
            $objectId = new ObjectId($patientId);
            $messages = $this->messageRepository->findByPatient($objectId, 200);
            $data = array_map(fn($m) => $m->toArray(), $messages);

            $this->auditLogService->log($user, 'messages_list_by_patient', [
                'entityType' => 'Patient',
                'entityId' => $patientId,
                'action' => 'list_messages'
            ]);

            return $this->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to retrieve messages: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Staff: Create message to patient
     */
    #[Route('/patient/{patientId}', name: 'messages_create_for_patient', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function createForPatient(string $patientId, Request $request, UserInterface $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $subject = $payload['subject'] ?? null;
        $body = trim($payload['body'] ?? '');

        if ($body === '') {
            return $this->json([
                'success' => false,
                'message' => 'Message body is required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate patient exists
        try {
            $existing = $this->patientRepository->find($patientId);
            if (!$existing) {
                return $this->json([
                    'success' => false,
                    'message' => 'Patient not found.'
                ], Response::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error validating patient: ' . $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        $message = new Message();
        $message->setPatientId($patientId);
        $message->setSenderUserId(method_exists($user, 'getId') ? (string)$user->getId() : $user->getUserIdentifier());
        $message->setSenderName(method_exists($user, 'getUsername') ? $user->getUsername() : $user->getUserIdentifier());
        $message->setSenderRoles($user->getRoles());
        $message->setDirection('to_patient');
        $message->setRecipientRoles(null);
        $message->setSubject($subject);
        $message->setBody($body);

        try {
            $this->messageRepository->save($message, true);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create message: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->auditLogService->log($user, 'messages_create_for_patient', [
            'entityType' => 'Patient',
            'entityId' => $patientId,
            'action' => 'create_message'
        ]);

        return $this->json([
            'success' => true,
            'data' => $message->toArray()
        ], Response::HTTP_CREATED);
    }
}


