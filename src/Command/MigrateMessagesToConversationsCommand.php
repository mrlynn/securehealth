<?php

namespace App\Command;

use App\Document\Conversation;
use App\Document\Message;
use App\Repository\MessageRepository;
use App\Repository\ConversationRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-messages-to-conversations',
    description: 'Migrate existing messages to conversation-based threading system'
)]
class MigrateMessagesToConversationsCommand extends Command
{
    public function __construct(
        private DocumentManager $documentManager,
        private MessageRepository $messageRepository,
        private ConversationRepository $conversationRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrating Messages to Conversations');

        try {
            // Get all existing messages
            $messages = $this->documentManager->createQueryBuilder(Message::class)
                ->getQuery()
                ->execute()
                ->toArray(false);

            if (empty($messages)) {
                $io->success('No messages found to migrate.');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Found %d messages to migrate', count($messages)));

            // Group messages by patient
            $messagesByPatient = [];
            foreach ($messages as $message) {
                $patientId = (string)$message->getPatientId();
                if (!isset($messagesByPatient[$patientId])) {
                    $messagesByPatient[$patientId] = [];
                }
                $messagesByPatient[$patientId][] = $message;
            }

            $conversationsCreated = 0;
            $messagesUpdated = 0;

            foreach ($messagesByPatient as $patientId => $patientMessages) {
                $io->info(sprintf('Processing %d messages for patient %s', count($patientMessages), $patientId));

                // Sort messages by creation time
                usort($patientMessages, function($a, $b) {
                    return $a->getCreatedAt()->toDateTime() <=> $b->getCreatedAt()->toDateTime();
                });

                // Create a conversation for this patient
                $conversation = new Conversation();
                $conversation->setPatientId(new ObjectId($patientId));
                $conversation->setSubject('Migrated Conversation');
                $conversation->setStatus('active');
                $conversation->setMessageCount(count($patientMessages));

                // Collect all participants
                $participants = [];
                foreach ($patientMessages as $message) {
                    if ($message->getSenderUserId()) {
                        $participants[] = $message->getSenderUserId();
                    }
                }
                $conversation->setParticipants(array_unique($participants));

                // Set last message info
                $lastMessage = end($patientMessages);
                $conversation->setLastMessageAt($lastMessage->getCreatedAt());
                $conversation->setLastMessagePreview(substr($lastMessage->getBody(), 0, 100));

                // Set unread flags
                $hasUnreadForPatient = false;
                $hasUnreadForStaff = false;
                foreach ($patientMessages as $message) {
                    if ($message->getDirection() === 'to_patient' && !$message->isReadByPatient()) {
                        $hasUnreadForPatient = true;
                    }
                    if ($message->getDirection() === 'to_staff' && !$message->isReadByStaff()) {
                        $hasUnreadForStaff = true;
                    }
                }
                $conversation->setHasUnreadForPatient($hasUnreadForPatient);
                $conversation->setHasUnreadForStaff($hasUnreadForStaff);

                // Save conversation
                $this->conversationRepository->save($conversation, false);
                $conversationsCreated++;

                // Update messages with conversation ID
                foreach ($patientMessages as $index => $message) {
                    $message->setConversationId($conversation->getId());
                    $message->setThreadLevel(0); // All messages are top-level in migrated conversations
                    $message->setUpdatedAt(new UTCDateTime());
                    
                    $this->messageRepository->save($message, false);
                    $messagesUpdated++;
                }

                $io->text(sprintf('  Created conversation %s with %d messages', (string)$conversation->getId(), count($patientMessages)));
            }

            // Flush all changes
            $this->documentManager->flush();

            $io->success(sprintf(
                'Migration completed successfully! Created %d conversations and updated %d messages.',
                $conversationsCreated,
                $messagesUpdated
            ));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
