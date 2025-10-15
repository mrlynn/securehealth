<?php

namespace App\Command;

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:index-knowledge-base',
    description: 'Index documentation and code examples for RAG chatbot',
)]
class IndexKnowledgeBaseCommand extends Command
{
    private Client $mongoClient;
    private string $projectDir;
    private ?string $openaiApiKey;

    public function __construct(
        Client $mongoClient,
        string $projectDir,
        ?string $openaiApiKey = null
    ) {
        parent::__construct();
        $this->mongoClient = $mongoClient;
        $this->projectDir = $projectDir;
        $this->openaiApiKey = $openaiApiKey;
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force re-indexing (clear existing data)')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Index only specific category (blog, docs, code)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        if (!$this->openaiApiKey) {
            $io->error('OPENAI_API_KEY not set. Please configure it in .env');
            return Command::FAILURE;
        }

        $io->title('Indexing Knowledge Base for RAG Chatbot');

        $collection = $this->mongoClient
            ->selectDatabase('securehealth')
            ->selectCollection('knowledge_base');

        // Clear existing data if --force
        if ($input->getOption('force')) {
            $io->warning('Clearing existing knowledge base...');
            $collection->deleteMany([]);
        }

        $category = $input->getOption('category');
        
        $totalIndexed = 0;

        if (!$category || $category === 'blog') {
            $count = $this->indexBlogArticle($io, $collection);
            $totalIndexed += $count;
        }

        if (!$category || $category === 'docs') {
            $count = $this->indexDocumentation($io, $collection);
            $totalIndexed += $count;
        }

        if (!$category || $category === 'code') {
            $count = $this->indexCodeExamples($io, $collection);
            $totalIndexed += $count;
        }

        $io->success("Successfully indexed {$totalIndexed} documents!");
        
        $io->note([
            'Next steps:',
            '1. Create a vector search index in MongoDB Atlas',
            '2. Test the chatbot with queries like:',
            '   - "What is MongoDB Queryable Encryption?"',
            '   - "How do Symfony Voters work?"',
            '   - "Explain deterministic vs random encryption"',
        ]);

        return Command::SUCCESS;
    }

    private function indexBlogArticle(SymfonyStyle $io, $collection): int
    {
        $io->section('Indexing Blog Article');

        $articlePath = $this->projectDir . '/docs/building-hipaa-compliant-medical-records-improved.md';
        
        if (!file_exists($articlePath)) {
            $io->warning("Blog article not found at: $articlePath");
            return 0;
        }

        $content = file_get_contents($articlePath);
        $chunks = $this->chunkMarkdown($content, 1000);

        $io->progressStart(count($chunks));

        $indexed = 0;
        foreach ($chunks as $index => $chunk) {
            try {
                $embedding = $this->generateEmbedding($chunk['text']);

                $collection->insertOne([
                    'title' => $chunk['title'],
                    'content' => $chunk['text'],
                    'embedding' => $embedding,
                    'category' => 'blog',
                    'metadata' => [
                        'section' => $chunk['section'],
                        'chunk_index' => $index,
                        'total_chunks' => count($chunks)
                    ],
                    'sourceFile' => 'building-hipaa-compliant-medical-records-improved.md',
                    'createdAt' => new UTCDateTime(),
                    'updatedAt' => new UTCDateTime()
                ]);

                $indexed++;
                $io->progressAdvance();
            } catch (\Exception $e) {
                $io->error("Failed to index chunk $index: " . $e->getMessage());
            }
        }

        $io->progressFinish();
        $io->success("Indexed $indexed chunks from blog article");

        return $indexed;
    }

    private function indexDocumentation(SymfonyStyle $io, $collection): int
    {
        $io->section('Indexing Documentation Files');

        $docFiles = [
            'docs/AUTHENTICATION_FLOW.md' => 'authentication',
            'docs/SECURITY.md' => 'security',
            'docs/mongodb-encryption-guide.md' => 'encryption',
            'docs/hipaa-compliance.md' => 'compliance',
            'docs/COMMAND_LINE_TOOL.md' => 'tools',
            'docs/CHATBOT_RAG_IMPLEMENTATION.md' => 'rag',
        ];

        $totalIndexed = 0;

        foreach ($docFiles as $file => $category) {
            $fullPath = $this->projectDir . '/' . $file;
            
            if (!file_exists($fullPath)) {
                $io->note("Skipping $file (not found)");
                continue;
            }

            $io->text("Processing $file...");
            
            $content = file_get_contents($fullPath);
            $chunks = $this->chunkMarkdown($content, 800);

            foreach ($chunks as $chunk) {
                try {
                    $embedding = $this->generateEmbedding($chunk['text']);

                    $collection->insertOne([
                        'title' => $chunk['title'],
                        'content' => $chunk['text'],
                        'embedding' => $embedding,
                        'category' => $category,
                        'sourceFile' => basename($file),
                        'metadata' => ['section' => $chunk['section']],
                        'createdAt' => new UTCDateTime(),
                        'updatedAt' => new UTCDateTime()
                    ]);

                    $totalIndexed++;
                } catch (\Exception $e) {
                    $io->warning("Failed to index chunk from $file: " . $e->getMessage());
                }
            }
        }

        $io->success("Indexed $totalIndexed chunks from documentation");
        return $totalIndexed;
    }

    private function indexCodeExamples(SymfonyStyle $io, $collection): int
    {
        $io->section('Indexing Code Examples');

        $codeExamples = [
            [
                'title' => 'MongoDB Encryption Service',
                'file' => 'src/Service/MongoDBEncryptionService.php',
                'category' => 'code',
                'description' => 'Service that handles encryption and decryption using MongoDB Queryable Encryption with deterministic and random algorithms.'
            ],
            [
                'title' => 'Patient Voter - RBAC Implementation',
                'file' => 'src/Security/Voter/PatientVoter.php',
                'category' => 'code',
                'description' => 'Symfony voter implementing fine-grained role-based access control for patient data with 17+ granular permissions.'
            ],
            [
                'title' => 'Patient Document Model',
                'file' => 'src/Document/Patient.php',
                'category' => 'code',
                'description' => 'Patient entity with encrypted fields, toDocument/fromDocument methods for encryption/decryption.'
            ],
        ];

        $indexed = 0;

        foreach ($codeExamples as $example) {
            $fullPath = $this->projectDir . '/' . $example['file'];
            
            if (!file_exists($fullPath)) {
                $io->note("Skipping {$example['file']} (not found)");
                continue;
            }

            $code = file_get_contents($fullPath);
            
            // Truncate very long files
            if (strlen($code) > 4000) {
                $code = substr($code, 0, 4000) . "\n\n... (truncated)";
            }
            
            $text = $example['description'] . "\n\n```php\n" . $code . "\n```";

            try {
                $embedding = $this->generateEmbedding($text);

                $collection->insertOne([
                    'title' => $example['title'],
                    'content' => $text,
                    'embedding' => $embedding,
                    'category' => $example['category'],
                    'sourceFile' => basename($example['file']),
                    'metadata' => ['description' => $example['description']],
                    'createdAt' => new UTCDateTime(),
                    'updatedAt' => new UTCDateTime()
                ]);

                $indexed++;
                $io->text("✓ Indexed {$example['title']}");
            } catch (\Exception $e) {
                $io->warning("Failed to index {$example['file']}: " . $e->getMessage());
            }
        }

        return $indexed;
    }

    private function chunkMarkdown(string $content, int $maxTokens = 1000): array
    {
        $chunks = [];
        
        // Split by major sections (## headers)
        $sections = preg_split('/^##\s+/m', $content);
        
        foreach ($sections as $section) {
            if (empty(trim($section))) continue;
            
            $lines = explode("\n", $section);
            $title = trim($lines[0]);
            $text = implode("\n", array_slice($lines, 1));
            
            // Further split if too large (rough: 1 token ≈ 4 chars)
            $maxChars = $maxTokens * 4;
            
            if (strlen($text) <= $maxChars) {
                $chunks[] = [
                    'title' => $title,
                    'section' => $title,
                    'text' => trim($text)
                ];
            } else {
                // Split by paragraphs
                $paragraphs = explode("\n\n", $text);
                $currentChunk = '';
                $partNum = 1;
                
                foreach ($paragraphs as $para) {
                    if (strlen($currentChunk . $para) > $maxChars && !empty($currentChunk)) {
                        $chunks[] = [
                            'title' => "$title (Part $partNum)",
                            'section' => $title,
                            'text' => trim($currentChunk)
                        ];
                        $currentChunk = $para;
                        $partNum++;
                    } else {
                        $currentChunk .= "\n\n" . $para;
                    }
                }
                
                if (!empty(trim($currentChunk))) {
                    $chunks[] = [
                        'title' => count($chunks) > 0 && $partNum > 1 ? "$title (Part $partNum)" : $title,
                        'section' => $title,
                        'text' => trim($currentChunk)
                    ];
                }
            }
        }
        
        return $chunks;
    }

    private function generateEmbedding(string $text): array
    {
        $ch = curl_init('https://api.openai.com/v1/embeddings');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->openaiApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'text-embedding-3-small',
                'input' => $text
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \RuntimeException("OpenAI API error: HTTP $httpCode - $response");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['data'][0]['embedding'])) {
            throw new \RuntimeException("Invalid OpenAI response: " . $response);
        }
        
        return $data['data'][0]['embedding'];
    }
}

