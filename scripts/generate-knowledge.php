<?php
/**
 * Generate and insert knowledge documents into MongoDB with proper embeddings
 * This script helps seed the medical_knowledge collection with useful content
 * for RAG chatbot testing
 */

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';

use App\Document\MedicalKnowledge;
use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use OpenAI;
use MongoDB\Client;

// Get container
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$dm = $container->get(DocumentManager::class);
$encryptionService = $container->get(MongoDBEncryptionService::class);
$openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? null;

if (!$openaiApiKey) {
    echo "Error: OpenAI API key not found in environment variables.\n";
    exit(1);
}

// Initialize OpenAI client
$openai = OpenAI::client($openaiApiKey);

// Sample HIPAA and security knowledge entries
$knowledgeEntries = [
    [
        'title' => 'HIPAA Compliance Overview',
        'content' => "HIPAA (Health Insurance Portability and Accountability Act) is a US federal law established in 1996 to protect sensitive patient health information. It includes two main rules: the Privacy Rule and the Security Rule. The Privacy Rule governs the use and disclosure of Protected Health Information (PHI), while the Security Rule sets standards for securing electronic PHI (ePHI). Covered entities include healthcare providers, health plans, and healthcare clearinghouses. Business associates who handle PHI on behalf of covered entities must also comply with HIPAA regulations.",
        'summary' => 'Overview of HIPAA compliance requirements and key components',
        'tags' => ['HIPAA', 'compliance', 'healthcare', 'regulations', 'privacy'],
        'specialties' => ['General', 'Administration', 'Compliance'],
        'source' => 'HIPAA Regulations',
        'sourceUrl' => 'https://www.hhs.gov/hipaa/index.html',
        'confidenceLevel' => 10,
        'evidenceLevel' => 5,
        'relatedConditions' => [],
        'relatedMedications' => [],
        'relatedProcedures' => [],
    ],
    [
        'title' => 'HIPAA Security Rule Requirements',
        'content' => "The HIPAA Security Rule requires appropriate administrative, physical, and technical safeguards to ensure the confidentiality, integrity, and security of electronic protected health information. Administrative safeguards include risk analysis, risk management, and staff training. Physical safeguards include facility access controls and workstation security. Technical safeguards include access controls, audit controls, integrity controls, and transmission security. Organizations must implement security measures that reasonably and appropriately protect electronic PHI from anticipated threats or hazards.",
        'summary' => 'Detailed overview of HIPAA Security Rule requirements',
        'tags' => ['HIPAA', 'security', 'ePHI', 'safeguards', 'compliance'],
        'specialties' => ['IT Security', 'Administration', 'Compliance'],
        'source' => 'HHS Office for Civil Rights',
        'sourceUrl' => 'https://www.hhs.gov/hipaa/for-professionals/security/index.html',
        'confidenceLevel' => 9,
        'evidenceLevel' => 5,
        'relatedConditions' => [],
        'relatedMedications' => [],
        'relatedProcedures' => [],
    ],
    [
        'title' => 'MongoDB Queryable Encryption',
        'content' => "MongoDB Queryable Encryption allows organizations to encrypt sensitive data while still allowing specific query operations. This is especially valuable for healthcare applications that must maintain HIPAA compliance while still providing searchable access to data. Field-level encryption can be configured as either deterministic (allowing equality queries) or random (providing maximum security but limited query capability). This helps healthcare organizations maintain compliance with HIPAA Security Rule requirements while still providing necessary application functionality. The encryption keys are managed separately from the database, ensuring that even database administrators cannot access the encrypted patient data without the proper keys.",
        'summary' => 'Overview of MongoDB Queryable Encryption for healthcare applications',
        'tags' => ['MongoDB', 'encryption', 'security', 'HIPAA', 'database'],
        'specialties' => ['IT Security', 'Database Administration'],
        'source' => 'MongoDB Documentation',
        'sourceUrl' => 'https://www.mongodb.com/docs/manual/core/queryable-encryption/',
        'confidenceLevel' => 9,
        'evidenceLevel' => 4,
        'relatedConditions' => [],
        'relatedMedications' => [],
        'relatedProcedures' => [],
    ],
    [
        'title' => 'Patient Data Security Best Practices',
        'content' => "Protecting patient data requires a comprehensive approach to security. Best practices include: 1) Implementing strong access controls with role-based permissions, 2) Encrypting data both at rest and in transit, 3) Regularly conducting security risk assessments, 4) Maintaining detailed audit logs of all data access, 5) Training staff on security awareness and HIPAA compliance, 6) Implementing secure backup and disaster recovery procedures, 7) Using secure communication channels for sharing patient information, 8) Properly disposing of electronic media containing PHI, 9) Having a breach notification procedure, and 10) Regularly updating and patching systems to address security vulnerabilities.",
        'summary' => 'Essential security best practices for protecting patient data',
        'tags' => ['security', 'best practices', 'patient data', 'HIPAA', 'protection'],
        'specialties' => ['IT Security', 'Administration', 'General Practice'],
        'source' => 'Healthcare Information and Management Systems Society (HIMSS)',
        'sourceUrl' => 'https://www.himss.org/',
        'confidenceLevel' => 8,
        'evidenceLevel' => 4,
        'relatedConditions' => [],
        'relatedMedications' => [],
        'relatedProcedures' => [],
    ],
    [
        'title' => 'Implementing RAG in Healthcare Applications',
        'content' => "Retrieval-Augmented Generation (RAG) is a powerful approach for healthcare chatbots that combines the strengths of large language models with domain-specific knowledge retrieval. In healthcare applications, RAG helps provide accurate, evidence-based responses by retrieving relevant medical knowledge before generating answers. This approach is particularly valuable for ensuring compliance with medical standards and HIPAA regulations. When implementing RAG, vector databases store embeddings of medical knowledge documents, allowing semantic search for relevant information based on patient queries. The retrieved context is then used to augment the language model's generation, resulting in more accurate and contextually appropriate responses.",
        'summary' => 'How to implement Retrieval-Augmented Generation in healthcare applications',
        'tags' => ['RAG', 'AI', 'chatbot', 'healthcare', 'knowledge retrieval'],
        'specialties' => ['Medical Informatics', 'AI in Healthcare', 'IT'],
        'source' => 'Journal of Healthcare Informatics Research',
        'sourceUrl' => null,
        'confidenceLevel' => 7,
        'evidenceLevel' => 3,
        'relatedConditions' => [],
        'relatedMedications' => [],
        'relatedProcedures' => [],
    ],
];

echo "Generating embeddings and inserting knowledge documents...\n";

// Function to generate embeddings
function generateEmbedding($text, $openai) {
    try {
        $response = $openai->embeddings()->create([
            'model' => 'text-embedding-3-small',
            'input' => $text
        ]);

        return $response->embeddings[0]->embedding;
    } catch (\Exception $e) {
        echo "Error generating embedding: " . $e->getMessage() . "\n";
        // Return a placeholder embedding with the correct dimensions
        return array_fill(0, 1536, 0.1);
    }
}

// Generate embeddings and insert documents
$count = 0;
foreach ($knowledgeEntries as $entry) {
    try {
        // Generate embedding for content
        $contentForEmbedding = $entry['title'] . ' ' . $entry['content'];
        $embedding = generateEmbedding($contentForEmbedding, $openai);

        // Create document
        $knowledge = new MedicalKnowledge();
        $knowledge->setTitle($entry['title']);
        $knowledge->setContent($entry['content']);
        $knowledge->setSummary($entry['summary']);
        $knowledge->setTags($entry['tags']);
        $knowledge->setSpecialties($entry['specialties']);
        $knowledge->setSource($entry['source']);

        if ($entry['sourceUrl']) {
            $knowledge->setSourceUrl($entry['sourceUrl']);
        }

        $knowledge->setConfidenceLevel($entry['confidenceLevel']);
        $knowledge->setEvidenceLevel($entry['evidenceLevel']);
        $knowledge->setEmbedding($embedding);
        $knowledge->setRelatedConditions($entry['relatedConditions']);
        $knowledge->setRelatedMedications($entry['relatedMedications']);
        $knowledge->setRelatedProcedures($entry['relatedProcedures']);
        $knowledge->setRequiresReview(false);
        $knowledge->setIsActive(true);

        // Convert to document for MongoDB storage
        $document = $knowledge->toDocument($encryptionService);

        // Insert document
        $collection = $dm->getDocumentCollection(MedicalKnowledge::class);
        $result = $collection->insertOne($document);

        if ($result->getInsertedCount() > 0) {
            $count++;
            echo "Inserted document: {$entry['title']}\n";
        } else {
            echo "Failed to insert document: {$entry['title']}\n";
        }

    } catch (\Exception $e) {
        echo "Error processing document {$entry['title']}: " . $e->getMessage() . "\n";
    }
}

echo "Successfully inserted $count out of " . count($knowledgeEntries) . " knowledge documents.\n";
echo "Don't forget to create the vector search index 'medical_knowledge_vector_index' in MongoDB Atlas!\n";