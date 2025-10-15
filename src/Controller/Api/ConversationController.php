<?php

namespace App\Controller\Api;

use App\Document\Conversation;
use App\Document\Message;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\PatientRepository;
use App\Service\AuditLogService;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/conversations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ConversationController extends AbstractController
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private MessageRepository $messageRepository,
        private PatientRepository $patientRepository,
        private AuditLogService $auditLogService
    ) {}

    /**
     * Get conversations for a patient (staff view)
     */
    #[Route('/patient/{patientId}', name: 'api_conversations_get_for_patient', methods: ['GET'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function getConversationsForPatient(string $patientId, UserInterface $user): JsonResponse
    {
        try {
            $conversations = $this->conversationRepository->findByPatient(new ObjectId($patientId));

            $this->auditLogService->log($user, 'staff_conversations_view_patient', ['patientId' => $patientId]);

            return $this->json(['success' => true, 'data' => array_map(fn($c) => $c->toArray(), $conversations)]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'staff_conversations_view_patient_failed', ['patientId' => $patientId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to load conversations: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get conversations for staff inbox
     */
    #[Route('/inbox', name: 'api_conversations_staff_inbox', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function getStaffInbox(UserInterface $user, Request $request): JsonResponse
    {
        $staffRoles = $user->getRoles();
        $limit = $request->query->getInt('limit', 50);
        $skip = $request->query->getInt('skip', 0);

        try {
            $conversations = $this->conversationRepository->findForStaff($staffRoles, $limit, $skip);

            $this->auditLogService->log($user, 'staff_conversations_inbox_view', ['staffId' => $user->getUserIdentifier(), 'roles' => $staffRoles]);

            return $this->json(['success' => true, 'data' => array_map(fn($c) => $c->toArray(), $conversations)]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'staff_conversations_inbox_view_failed', ['staffId' => $user->getUserIdentifier(), 'roles' => $staffRoles, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to load conversations: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get conversations for patient portal
     */
    #[Route('/patient-portal', name: 'api_conversations_patient_portal', methods: ['GET'])]
    #[IsGranted('ROLE_PATIENT')]
    public function getPatientConversations(UserInterface $user): JsonResponse
    {
        if (!$user->isPatient()) {
            return $this->json(['success' => false, 'message' => 'Access denied. Patient access required.'], Response::HTTP_FORBIDDEN);
        }

        $patientId = $user->getPatientId();
        if (!$patientId) {
            return $this->json(['success' => false, 'message' => 'No patient record associated with this account.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $conversations = $this->conversationRepository->findByPatient(new ObjectId($patientId));

            $this->auditLogService->log($user, 'patient_conversations_view', ['patientId' => (string)$patientId]);

            return $this->json(['success' => true, 'data' => array_map(fn($c) => $c->toArray(), $conversations)]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'patient_conversations_view_failed', ['patientId' => (string)$patientId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to load conversations: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get messages in a conversation (threaded view)
     */
    #[Route('/{conversationId}/messages', name: 'api_conversations_get_messages', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function getConversationMessages(string $conversationId, UserInterface $user): JsonResponse
    {
        try {
            $messages = $this->messageRepository->findByConversation(new ObjectId($conversationId));

            $this->auditLogService->log($user, 'conversation_messages_view', ['conversationId' => $conversationId]);

            return $this->json(['success' => true, 'data' => array_map(fn($m) => $m->toArray(), $messages)]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'conversation_messages_view_failed', ['conversationId' => $conversationId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to load messages: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new conversation
     */
    #[Route('/create', name: 'api_conversations_create', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function createConversation(Request $request, UserInterface $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $patientId = $data['patientId'] ?? null;
        $subject = $data['subject'] ?? null;
        $initialMessage = $data['message'] ?? null;

        if (!$patientId || !$subject || !$initialMessage) {
            return $this->json(['success' => false, 'message' => 'Patient ID, subject, and initial message are required.'], Response::HTTP_BAD_REQUEST);
        }

        // Validate patient exists
        $patient = $this->patientRepository->find($patientId);
        if (!$patient) {
            return $this->json(['success' => false, 'message' => 'Patient not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            // Create conversation
            $conversation = new Conversation();
            $conversation->setPatientId(new ObjectId($patientId));
            $conversation->setSubject($subject);
            $conversation->setParticipants([$user->getUserIdentifier()]);
            $conversation->setStatus('active');
            $conversation->setLastMessagePreview(substr($initialMessage, 0, 100));
            $conversation->setMessageCount(1);

            $this->conversationRepository->save($conversation);

            // Create initial message
            $message = new Message();
            $message->setPatientId(new ObjectId($patientId));
            $message->setSenderUserId($user->getUserIdentifier());
            $message->setSenderName($user->getUsername());
            $message->setSenderRoles($user->getRoles());
            $message->setDirection('to_patient');
            $message->setRecipientRoles(null);
            $message->setSubject($subject);
            $message->setBody($initialMessage);
            $message->setConversationId($conversation->getId());
            $message->setThreadLevel(0);
            $message->setIsReadByStaff(true);
            $message->setIsReadByPatient(false);

            $this->messageRepository->save($message);

            $this->auditLogService->log($user, 'conversation_created', [
                'conversationId' => (string)$conversation->getId(),
                'patientId' => $patientId
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Conversation created successfully.',
                'data' => [
                    'conversation' => $conversation->toArray(),
                    'message' => $message->toArray()
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'conversation_create_failed', ['patientId' => $patientId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to create conversation: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reply to a conversation
     */
    #[Route('/{conversationId}/reply', name: 'api_conversations_reply', methods: ['POST'])]
    #[IsGranted('ROLE_NURSE')]
    public function replyToConversation(string $conversationId, Request $request, UserInterface $user): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $body = $data['body'] ?? null;
        $parentMessageId = $data['parentMessageId'] ?? null; // Optional: for direct replies

        if (!$body) {
            return $this->json(['success' => false, 'message' => 'Message body is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $conversation = $this->conversationRepository->findById($conversationId);
            if (!$conversation) {
                return $this->json(['success' => false, 'message' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
            }

            // Determine direction and recipient roles based on user role
            $isStaff = in_array('ROLE_DOCTOR', $user->getRoles()) || in_array('ROLE_NURSE', $user->getRoles());
            $direction = $isStaff ? 'to_patient' : 'to_staff';
            $recipientRoles = $isStaff ? null : ['ROLE_DOCTOR', 'ROLE_NURSE'];

            // Calculate thread level
            $threadLevel = 0;
            if ($parentMessageId) {
                $parentMessage = $this->messageRepository->findById($parentMessageId);
                if ($parentMessage) {
                    $threadLevel = $parentMessage->getThreadLevel() + 1;
                }
            }

            // Create reply message
            $message = new Message();
            $message->setPatientId($conversation->getPatientId());
            $message->setSenderUserId($user->getUserIdentifier());
            $message->setSenderName($user->getUsername());
            $message->setSenderRoles($user->getRoles());
            $message->setDirection($direction);
            $message->setRecipientRoles($recipientRoles);
            $message->setSubject($conversation->getSubject());
            $message->setBody($body);
            $message->setConversationId($conversation->getId());
            $message->setParentMessageId($parentMessageId ? new ObjectId($parentMessageId) : null);
            $message->setThreadLevel($threadLevel);
            $message->setIsReadByStaff($isStaff);
            $message->setIsReadByPatient(!$isStaff);

            $this->messageRepository->save($message);

            // Update conversation
            $this->conversationRepository->updateLastMessage(
                $conversationId,
                substr($body, 0, 100),
                $isStaff
            );

            $this->auditLogService->log($user, 'conversation_reply', [
                'conversationId' => $conversationId,
                'messageId' => (string)$message->getId()
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Reply sent successfully.',
                'data' => $message->toArray()
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'conversation_reply_failed', ['conversationId' => $conversationId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to send reply: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark conversation as read
     */
    #[Route('/{conversationId}/read', name: 'api_conversations_mark_read', methods: ['POST'])]
    #[IsGranted('ROLE_NURSE')]
    public function markConversationAsRead(string $conversationId, UserInterface $user): JsonResponse
    {
        try {
            $isStaff = in_array('ROLE_DOCTOR', $user->getRoles()) || in_array('ROLE_NURSE', $user->getRoles());
            
            if ($isStaff) {
                $conversation = $this->conversationRepository->markAsReadByStaff($conversationId);
            } else {
                $conversation = $this->conversationRepository->markAsReadByPatient($conversationId);
            }

            if (!$conversation) {
                return $this->json(['success' => false, 'message' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->auditLogService->log($user, 'conversation_mark_read', ['conversationId' => $conversationId]);

            return $this->json(['success' => true, 'message' => 'Conversation marked as read.', 'data' => $conversation->toArray()]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'conversation_mark_read_failed', ['conversationId' => $conversationId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to mark conversation as read: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Close a conversation
     */
    #[Route('/{conversationId}/close', name: 'api_conversations_close', methods: ['POST'])]
    #[IsGranted('ROLE_DOCTOR')]
    public function closeConversation(string $conversationId, UserInterface $user): JsonResponse
    {
        try {
            $conversation = $this->conversationRepository->closeConversation($conversationId);

            if (!$conversation) {
                return $this->json(['success' => false, 'message' => 'Conversation not found.'], Response::HTTP_NOT_FOUND);
            }

            $this->auditLogService->log($user, 'conversation_closed', ['conversationId' => $conversationId]);

            return $this->json(['success' => true, 'message' => 'Conversation closed.', 'data' => $conversation->toArray()]);
        } catch (\Exception $e) {
            $this->auditLogService->log($user, 'conversation_close_failed', ['conversationId' => $conversationId, 'error' => $e->getMessage()]);
            return $this->json(['success' => false, 'message' => 'Failed to close conversation: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get unread conversation count for staff
     */
    #[Route('/inbox/unread-count', name: 'api_conversations_staff_unread_count', methods: ['GET'])]
    #[IsGranted('ROLE_NURSE')]
    public function getUnreadCountForStaff(UserInterface $user): JsonResponse
    {
        $staffRoles = $user->getRoles();

        try {
            $count = $this->conversationRepository->countUnreadForStaff($staffRoles);

            return $this->json(['success' => true, 'count' => $count]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Failed to get unread count: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
