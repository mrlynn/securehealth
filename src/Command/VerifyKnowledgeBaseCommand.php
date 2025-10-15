<?php

namespace App\Command;

use MongoDB\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-knowledge-base',
    description: 'Verify knowledge base indexing status',
)]
class VerifyKnowledgeBaseCommand extends Command
{
    private Client $mongoClient;

    public function __construct(Client $mongoClient)
    {
        parent::__construct();
        $this->mongoClient = $mongoClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Knowledge Base Verification');

        $collection = $this->mongoClient
            ->selectDatabase('securehealth')
            ->selectCollection('knowledge_base');

        // Count total documents
        $totalCount = $collection->countDocuments();
        
        if ($totalCount === 0) {
            $io->error('No documents found in knowledge_base collection!');
            $io->note('Run: bin/console app:index-knowledge-base --force');
            return Command::FAILURE;
        }

        $io->success("Total documents indexed: $totalCount");

        // Count by category
        $categories = $collection->aggregate([
            ['$group' => ['_id' => '$category', 'count' => ['$sum' => 1]]]
        ])->toArray();

        $io->section('Documents by Category');
        $rows = [];
        foreach ($categories as $cat) {
            $rows[] = [$cat['_id'], $cat['count']];
        }
        $io->table(['Category', 'Count'], $rows);

        // Sample documents
        $io->section('Sample Documents');
        $samples = $collection->find([], ['limit' => 3])->toArray();
        
        foreach ($samples as $i => $doc) {
            $io->text(sprintf(
                "%d. %s (%s) - %d chars",
                $i + 1,
                $doc['title'] ?? 'Untitled',
                $doc['category'] ?? 'unknown',
                strlen($doc['content'] ?? '')
            ));
        }

        $io->newLine();
        $io->note([
            'Next step: Create vector search index in MongoDB Atlas',
            'Index name: vector_index',
            'Database: securehealth',
            'Collection: knowledge_base',
            'See docs/CHATBOT_QUICK_START.md for configuration'
        ]);

        return Command::SUCCESS;
    }
}

