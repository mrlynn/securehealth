# Implementing RAG for HIPAA-Compliant Chatbot

## Overview

This guide shows how to implement Retrieval Augmented Generation (RAG) so your chatbot can answer questions about:
- MongoDB Queryable Encryption concepts
- How the SecureHealth application works
- The blog article content
- Implementation details

## Architecture

```
User Question
    ↓
1. Generate Embedding (OpenAI)
    ↓
2. Vector Search (MongoDB Atlas)
    ↓
3. Retrieve Relevant Documentation
    ↓
4. Combine with Question
    ↓
5. Send to LLM (GPT-4/Claude)
    ↓
6. Return Answer
```

## Step 1: Create Knowledge Base Collection

First, create a MongoDB collection to store documentation chunks with embeddings:

```javascript
// MongoDB Atlas - Create collection with vector search index
db.createCollection("knowledge_base")

// Create vector search index in Atlas UI:
// 1. Go to Atlas UI → Search → Create Search Index
// 2. Use JSON Editor with this configuration:
{
  "mappings": {
    "dynamic": true,
    "fields": {
      "embedding": {
        "type": "knnVector",
        "dimensions": 1536,
        "similarity": "cosine"
      },
      "category": {
        "type": "string"
      },
      "title": {
        "type": "string"
      }
    }
  }
}
```

## Step 2: Document Schema

```php
<?php
// src/Document/KnowledgeBase.php
namespace App\Document;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class KnowledgeBase
{
    private ?ObjectId $id = null;
    private string $title;           // "MongoDB Queryable Encryption Overview"
    private string $content;         // The actual text chunk
    private array $embedding;        // 1536-dimensional vector from OpenAI
    private string $category;        // "encryption", "app", "blog", "troubleshooting"
    private array $metadata;         // Additional context
    private ?string $sourceFile;     // "building-hipaa-compliant-medical-records-improved.md"
    private UTCDateTime $createdAt;
    private UTCDateTime $updatedAt;
    
    // Constructor, getters, setters...
}
```

## Step 3: Index Your Documentation

Create a command to index all documentation:

```php
<?php
// src/Command/IndexKnowledgeBaseCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use OpenAI;

class IndexKnowledgeBaseCommand extends Command
{
    protected static $defaultName = 'app:index-knowledge-base';
    
    private $openai;
    private $mongoClient;
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Indexing knowledge base...');
        
        // 1. Index blog article
        $this->indexBlogArticle($output);
        
        // 2. Index documentation files
        $this->indexDocumentation($output);
        
        // 3. Index code examples
        $this->indexCodeExamples($output);
        
        $output->writeln('✅ Knowledge base indexed successfully!');
        return Command::SUCCESS;
    }
    
    private function indexBlogArticle(OutputInterface $output): void
    {
        $output->writeln('Indexing blog article...');
        
        // Read the blog article
        $articlePath = __DIR__ . '/../../docs/building-hipaa-compliant-medical-records-improved.md';
        $content = file_get_contents($articlePath);
        
        // Split into chunks (important for context window limits)
        $chunks = $this->chunkMarkdown($content, 1000); // 1000 tokens per chunk
        
        foreach ($chunks as $index => $chunk) {
            $output->writeln("  Processing chunk $index...");
            
            // Generate embedding using OpenAI
            $embedding = $this->generateEmbedding($chunk['text']);
            
            // Store in MongoDB
            $this->mongoClient
                ->selectDatabase('securehealth')
                ->selectCollection('knowledge_base')
                ->insertOne([
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
                    'createdAt' => new \MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new \MongoDB\BSON\UTCDateTime()
                ]);
        }
    }
    
    private function chunkMarkdown(string $content, int $maxTokens = 1000): array
    {
        $chunks = [];
        $currentSection = 'Introduction';
        
        // Split by major sections (## headers)
        $sections = preg_split('/^##\s+/m', $content);
        
        foreach ($sections as $section) {
            if (empty(trim($section))) continue;
            
            // Extract title from first line
            $lines = explode("\n", $section);
            $title = trim($lines[0]);
            $text = implode("\n", array_slice($lines, 1));
            
            // Further split if too large
            $subchunks = $this->splitByTokenLimit($text, $maxTokens);
            
            foreach ($subchunks as $i => $subchunk) {
                $chunks[] = [
                    'title' => $title . ($i > 0 ? " (part " . ($i + 1) . ")" : ""),
                    'section' => $title,
                    'text' => $subchunk
                ];
            }
        }
        
        return $chunks;
    }
    
    private function splitByTokenLimit(string $text, int $maxTokens): array
    {
        // Rough estimate: 1 token ≈ 4 characters
        $maxChars = $maxTokens * 4;
        $chunks = [];
        
        if (strlen($text) <= $maxChars) {
            return [$text];
        }
        
        // Split by paragraphs first
        $paragraphs = explode("\n\n", $text);
        $currentChunk = '';
        
        foreach ($paragraphs as $para) {
            if (strlen($currentChunk . $para) > $maxChars && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $para;
            } else {
                $currentChunk .= "\n\n" . $para;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
    
    private function generateEmbedding(string $text): array
    {
        $response = $this->openai->embeddings()->create([
            'model' => 'text-embedding-3-small', // Cheaper and faster than ada-002
            'input' => $text
        ]);
        
        return $response->embeddings[0]->embedding;
    }
    
    private function indexDocumentation(OutputInterface $output): void
    {
        $output->writeln('Indexing documentation files...');
        
        $docFiles = [
            'docs/AUTHENTICATION_FLOW.md' => 'authentication',
            'docs/SECURITY.md' => 'security',
            'docs/mongodb-encryption-guide.md' => 'encryption',
            'docs/HIPAA-compliance.md' => 'compliance',
            'docs/COMMAND_LINE_TOOL.md' => 'tools'
        ];
        
        foreach ($docFiles as $file => $category) {
            if (!file_exists($file)) continue;
            
            $content = file_get_contents($file);
            $chunks = $this->chunkMarkdown($content, 800);
            
            foreach ($chunks as $chunk) {
                $embedding = $this->generateEmbedding($chunk['text']);
                
                $this->mongoClient
                    ->selectDatabase('securehealth')
                    ->selectCollection('knowledge_base')
                    ->insertOne([
                        'title' => $chunk['title'],
                        'content' => $chunk['text'],
                        'embedding' => $embedding,
                        'category' => $category,
                        'sourceFile' => basename($file),
                        'createdAt' => new \MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new \MongoDB\BSON\UTCDateTime()
                    ]);
            }
        }
    }
    
    private function indexCodeExamples(OutputInterface $output): void
    {
        $output->writeln('Indexing code examples...');
        
        // Index key code files with descriptions
        $codeExamples = [
            [
                'title' => 'MongoDB Encryption Service Implementation',
                'file' => 'src/Service/MongoDBEncryptionService.php',
                'category' => 'code',
                'description' => 'This service handles encryption and decryption of patient data using MongoDB Queryable Encryption.'
            ],
            [
                'title' => 'Patient Voter - Role-Based Access Control',
                'file' => 'src/Security/Voter/PatientVoter.php',
                'category' => 'code',
                'description' => 'Symfony voter that implements fine-grained RBAC for patient data access.'
            ],
            // Add more code examples...
        ];
        
        foreach ($codeExamples as $example) {
            if (!file_exists($example['file'])) continue;
            
            $code = file_get_contents($example['file']);
            $text = $example['description'] . "\n\n```php\n" . $code . "\n```";
            
            $embedding = $this->generateEmbedding($text);
            
            $this->mongoClient
                ->selectDatabase('securehealth')
                ->selectCollection('knowledge_base')
                ->insertOne([
                    'title' => $example['title'],
                    'content' => $text,
                    'embedding' => $embedding,
                    'category' => $example['category'],
                    'sourceFile' => basename($example['file']),
                    'createdAt' => new \MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new \MongoDB\BSON\UTCDateTime()
                ]);
        }
    }
}
```

## Step 4: Enhanced Chatbot Service with RAG

```php
<?php
// src/Service/RAGChatbotService.php
namespace App\Service;

use App\Security\Voter\PatientVoter;
use App\Repository\PatientRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use OpenAI;
use MongoDB\Client;

class RAGChatbotService
{
    private OpenAI\Client $openai;
    private Client $mongoClient;
    private PatientRepository $patientRepo;
    private AuthorizationCheckerInterface $authChecker;
    private AuditLogService $auditLog;
    private MongoDBEncryptionService $encryption;

    public function __construct(
        string $openaiApiKey,
        Client $mongoClient,
        PatientRepository $patientRepo,
        AuthorizationCheckerInterface $authChecker,
        AuditLogService $auditLog,
        MongoDBEncryptionService $encryption
    ) {
        $this->openai = OpenAI::client($openaiApiKey);
        $this->mongoClient = $mongoClient;
        $this->patientRepo = $patientRepo;
        $this->authChecker = $authChecker;
        $this->auditLog = $auditLog;
        $this->encryption = $encryption;
    }

    /**
     * Process a chatbot query with RAG
     */
    public function processQuery(string $query, User $user): array
    {
        // 1. Determine if this is a knowledge query or a data query
        $queryType = $this->classifyQuery($query);
        
        if ($queryType === 'knowledge') {
            // Answer using RAG
            return $this->answerWithRAG($query, $user);
        } else {
            // Answer using function calling (patient data)
            return $this->answerWithFunctionCalling($query, $user);
        }
    }
    
    /**
     * Classify the query type
     */
    private function classifyQuery(string $query): string
    {
        $knowledgeKeywords = [
            'how does', 'what is', 'explain', 'how to',
            'queryable encryption', 'mongodb', 'hipaa',
            'voter', 'authentication', 'session',
            'example', 'implement', 'configure'
        ];
        
        $queryLower = strtolower($query);
        
        foreach ($knowledgeKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                return 'knowledge';
            }
        }
        
        // Default to data query
        return 'data';
    }
    
    /**
     * Answer using RAG - retrieves relevant documentation
     */
    private function answerWithRAG(string $query, User $user): array
    {
        // 1. Generate embedding for the query
        $queryEmbedding = $this->generateEmbedding($query);
        
        // 2. Perform vector search to find relevant documentation
        $relevantDocs = $this->vectorSearch($queryEmbedding, 5);
        
        // 3. Build context from retrieved documents
        $context = $this->buildContext($relevantDocs);
        
        // 4. Create enhanced prompt with context
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getRAGSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => $this->buildRAGPrompt($query, $context)
            ]
        ];
        
        // 5. Get answer from LLM
        $response = $this->openai->chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000
        ]);
        
        $answer = $response->choices[0]->message->content;
        
        // 6. Log the interaction
        $this->auditLog->log($user, 'CHATBOT_RAG_QUERY', [
            'query' => $query,
            'sources_used' => count($relevantDocs),
            'categories' => array_unique(array_column($relevantDocs, 'category'))
        ]);
        
        return [
            'response' => $answer,
            'type' => 'knowledge',
            'sources' => $this->formatSources($relevantDocs)
        ];
    }
    
    /**
     * Perform vector search using MongoDB Atlas
     */
    private function vectorSearch(array $queryEmbedding, int $limit = 5): array
    {
        $pipeline = [
            [
                '$vectorSearch' => [
                    'index' => 'vector_index',
                    'path' => 'embedding',
                    'queryVector' => $queryEmbedding,
                    'numCandidates' => 100,
                    'limit' => $limit
                ]
            ],
            [
                '$project' => [
                    '_id' => 1,
                    'title' => 1,
                    'content' => 1,
                    'category' => 1,
                    'sourceFile' => 1,
                    'score' => ['$meta' => 'vectorSearchScore']
                ]
            ]
        ];
        
        $results = $this->mongoClient
            ->selectDatabase('securehealth')
            ->selectCollection('knowledge_base')
            ->aggregate($pipeline)
            ->toArray();
        
        return array_map(function($doc) {
            return [
                'title' => $doc['title'] ?? 'Untitled',
                'content' => $doc['content'] ?? '',
                'category' => $doc['category'] ?? 'general',
                'source' => $doc['sourceFile'] ?? 'Unknown',
                'score' => $doc['score'] ?? 0
            ];
        }, $results);
    }
    
    /**
     * Build context from retrieved documents
     */
    private function buildContext(array $docs): string
    {
        $context = "# Relevant Documentation\n\n";
        
        foreach ($docs as $i => $doc) {
            $context .= "## Source " . ($i + 1) . ": {$doc['title']}\n";
            $context .= "Category: {$doc['category']}\n";
            $context .= "Relevance Score: " . round($doc['score'], 3) . "\n\n";
            $context .= $doc['content'] . "\n\n";
            $context .= "---\n\n";
        }
        
        return $context;
    }
    
    /**
     * Build RAG prompt combining query and context
     */
    private function buildRAGPrompt(string $query, string $context): string
    {
        return <<<PROMPT
I need you to answer the following question based on the provided documentation.

QUESTION:
{$query}

CONTEXT FROM DOCUMENTATION:
{$context}

Please provide a clear, accurate answer based on the documentation provided. If the documentation doesn't contain enough information to answer the question, say so. Include specific details and code examples when relevant.

ANSWER:
PROMPT;
    }
    
    /**
     * System prompt for RAG mode
     */
    private function getRAGSystemPrompt(): string
    {
        return <<<PROMPT
You are a technical assistant specialized in MongoDB Queryable Encryption and HIPAA-compliant healthcare applications. You have access to documentation about the SecureHealth application.

Your role:
1. Answer questions accurately based on the provided documentation
2. Explain technical concepts clearly
3. Provide code examples when helpful
4. Cite sources from the documentation when possible
5. If unsure, admit limitations rather than guess

Remember:
- This is technical documentation, be precise
- Use concrete examples from the docs
- Mention relevant file names or sections
- If the question is about patient data, defer to the function calling system
PROMPT;
    }
    
    /**
     * Format sources for response
     */
    private function formatSources(array $docs): array
    {
        return array_map(function($doc) {
            return [
                'title' => $doc['title'],
                'category' => $doc['category'],
                'source' => $doc['source'],
                'relevance' => round($doc['score'], 3)
            ];
        }, $docs);
    }
    
    /**
     * Generate embedding using OpenAI
     */
    private function generateEmbedding(string $text): array
    {
        $response = $this->openai->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);
        
        return $response->embeddings[0]->embedding;
    }
    
    /**
     * Fallback to function calling for patient data queries
     */
    private function answerWithFunctionCalling(string $query, User $user): array
    {
        // Use the original ChatbotService logic for patient data
        // (from the article example)
        $functions = $this->getAvailableFunctions($user);
        
        $response = $this->openai->chat()->create([
            'model' => 'gpt-4-turbo-preview',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getDataQuerySystemPrompt($user)
                ],
                [
                    'role' => 'user',
                    'content' => $query
                ]
            ],
            'functions' => $functions,
            'function_call' => 'auto'
        ]);
        
        $message = $response->choices[0]->message;
        
        if (isset($message->function_call)) {
            return $this->executeFunction(
                $message->function_call->name,
                json_decode($message->function_call->arguments, true),
                $user
            );
        }
        
        return [
            'response' => $message->content,
            'type' => 'text'
        ];
    }
    
    // ... rest of function calling methods from ChatbotService ...
}
```

## Step 5: Run the Indexing

```bash
# Index all documentation
docker-compose exec php bin/console app:index-knowledge-base

# Output:
# Indexing knowledge base...
# Indexing blog article...
#   Processing chunk 0...
#   Processing chunk 1...
#   ...
# Indexing documentation files...
# Indexing code examples...
# ✅ Knowledge base indexed successfully!
```

## Step 6: Test the RAG Chatbot

```javascript
// Frontend test
async function testRAGChatbot() {
    const queries = [
        "What is MongoDB Queryable Encryption?",
        "How do Symfony Voters work in this application?",
        "Explain the difference between deterministic and random encryption",
        "How do I set up the encryption keys?",
        "What are the HIPAA compliance requirements?"
    ];
    
    for (const query of queries) {
        console.log(`\n❓ Question: ${query}`);
        
        const response = await fetch('/api/chatbot/query', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ query })
        });
        
        const data = await response.json();
        console.log(`\n✅ Answer: ${data.response}`);
        
        if (data.sources) {
            console.log('\n📚 Sources:');
            data.sources.forEach(s => {
                console.log(`  - ${s.title} (${s.category}) - ${s.source}`);
            });
        }
    }
}
```

## Example Interactions

### Query: "What is MongoDB Queryable Encryption?"

**Vector Search Retrieves:**
- "Understanding MongoDB Queryable Encryption" (blog, score: 0.92)
- "The Core Concept" (blog, score: 0.88)
- "MongoDB Encryption Guide" (encryption doc, score: 0.85)

**Response:**
> MongoDB Queryable Encryption is a feature that allows you to search encrypted data without decrypting it on the server. It uses three types of encryption:
>
> 1. **Deterministic Encryption** (AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic): Same plaintext always produces the same ciphertext, enabling equality queries. Used for names, emails, phone numbers.
>
> 2. **Random Encryption** (AEAD_AES_256_CBC_HMAC_SHA_512-Random): Maximum security, no queries possible. Used for SSN, diagnoses, medical notes.
>
> 3. **Range Encryption**: Enables range queries on encrypted numeric/date fields.
>
> The key innovation is that even MongoDB's database administrators cannot read your encrypted data without the encryption keys, which are managed separately using AWS KMS, Azure Key Vault, or locally for development.
>
> **Sources:** building-hipaa-compliant-medical-records-improved.md, mongodb-encryption-guide.md

### Query: "How do I implement a PatientVoter?"

**Vector Search Retrieves:**
- "Patient Voter - Role-Based Access Control" (code, score: 0.94)
- "Role-Based Access Control with Symfony Voters" (blog, score: 0.91)

**Response:**
> To implement a PatientVoter, create a class that extends Symfony's `Voter` class. Here's the structure:
>
> ```php
> class PatientVoter extends Voter
> {
>     const VIEW = 'PATIENT_VIEW';
>     const EDIT = 'PATIENT_EDIT';
>     const VIEW_DIAGNOSIS = 'PATIENT_VIEW_DIAGNOSIS';
>     
>     protected function supports(string $attribute, mixed $subject): bool {
>         // Check if this voter handles this permission
>         return in_array($attribute, [self::VIEW, self::EDIT, ...])
>             && $subject instanceof Patient;
>     }
>     
>     protected function voteOnAttribute(...): bool {
>         // Check permissions based on user roles
>     }
> }
> ```
>
> Key permissions you should implement:
> - PATIENT_VIEW: All healthcare staff
> - PATIENT_VIEW_DIAGNOSIS: Doctors and nurses only
> - PATIENT_VIEW_SSN: Doctors only
> - PATIENT_EDIT_DIAGNOSIS: Doctors only
>
> See src/Security/Voter/PatientVoter.php for the complete implementation.

## Performance Optimization

### 1. Cache Embeddings
```php
// Cache frequently asked questions
$cache = new FilesystemCache();
$cacheKey = 'embedding_' . md5($query);

if ($cache->has($cacheKey)) {
    $embedding = $cache->get($cacheKey);
} else {
    $embedding = $this->generateEmbedding($query);
    $cache->set($cacheKey, $embedding, 3600); // 1 hour
}
```

### 2. Limit Context Size
```php
// Only use top 3 most relevant docs to save tokens
$relevantDocs = $this->vectorSearch($queryEmbedding, 3);
```

### 3. Smart Chunking
```php
// Chunk size optimization
$chunkSize = 800; // tokens
$overlap = 100;   // token overlap between chunks
```

## Cost Estimates

**Indexing (one-time):**
- Blog article (2,500 lines) → ~50 chunks
- Documentation (5 files) → ~30 chunks  
- Code examples → ~10 chunks
- **Total:** ~90 embeddings × $0.00002 = **$0.0018**

**Per Query:**
- Query embedding: $0.00002
- LLM response (1000 tokens): ~$0.01
- **Total per query:** ~**$0.01**

**Monthly (1000 queries):**
- **~$10-15/month**

## Monitoring

```php
// Log RAG performance metrics
$this->auditLog->log($user, 'RAG_METRICS', [
    'query_length' => strlen($query),
    'num_sources_retrieved' => count($relevantDocs),
    'top_source_score' => $relevantDocs[0]['score'] ?? 0,
    'response_length' => strlen($answer),
    'categories_used' => array_unique(array_column($relevantDocs, 'category'))
]);
```

## Next Steps

1. **Index your documentation:** Run the indexing command
2. **Test queries:** Try various questions about encryption, HIPAA, etc.
3. **Tune retrieval:** Adjust number of documents retrieved
4. **Add more sources:** Index additional documentation as you create it
5. **Monitor usage:** Track which queries work well vs. poorly

This RAG setup gives your chatbot deep knowledge about MongoDB Queryable Encryption, your application, and how everything works together!

