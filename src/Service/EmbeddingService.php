<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmbeddingService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $openaiApiKey;
    private string $openaiApiUrl;
    private int $embeddingDimensions;

    public function __construct(LoggerInterface $logger, string $openaiApiKey = '', string $openaiApiUrl = 'https://api.openai.com/v1')
    {
        $this->httpClient = HttpClient::create();
        $this->logger = $logger;
        $this->openaiApiKey = $openaiApiKey;
        $this->openaiApiUrl = $openaiApiUrl;
        $this->embeddingDimensions = 1536; // OpenAI text-embedding-ada-002 dimensions
    }

    /**
     * Generate embedding for text using OpenAI API
     */
    public function generateEmbedding(string $text): array
    {
        if (empty($this->openaiApiKey)) {
            // Fallback to mock embedding for development
            return $this->generateMockEmbedding($text);
        }

        try {
            $response = $this->httpClient->request('POST', $this->openaiApiUrl . '/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'text-embedding-ada-002',
                    'input' => $this->preprocessText($text),
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            
            if (isset($data['data'][0]['embedding'])) {
                $embedding = $data['data'][0]['embedding'];
                
                $this->logger->info('Generated embedding successfully', [
                    'textLength' => strlen($text),
                    'embeddingDimensions' => count($embedding)
                ]);
                
                return $embedding;
            } else {
                throw new \Exception('Invalid response format from OpenAI API');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate embedding via OpenAI API', [
                'error' => $e->getMessage(),
                'textLength' => strlen($text)
            ]);
            
            // Fallback to mock embedding
            return $this->generateMockEmbedding($text);
        }
    }

    /**
     * Generate embeddings for multiple texts in batch
     */
    public function generateEmbeddingsBatch(array $texts): array
    {
        if (empty($this->openaiApiKey)) {
            // Fallback to mock embeddings for development
            return array_map([$this, 'generateMockEmbedding'], $texts);
        }

        try {
            $response = $this->httpClient->request('POST', $this->openaiApiUrl . '/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'text-embedding-ada-002',
                    'input' => array_map([$this, 'preprocessText'], $texts),
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            
            if (isset($data['data']) && is_array($data['data'])) {
                $embeddings = array_map(function($item) {
                    return $item['embedding'];
                }, $data['data']);
                
                $this->logger->info('Generated batch embeddings successfully', [
                    'textCount' => count($texts),
                    'embeddingCount' => count($embeddings)
                ]);
                
                return $embeddings;
            } else {
                throw new \Exception('Invalid response format from OpenAI API');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate batch embeddings via OpenAI API', [
                'error' => $e->getMessage(),
                'textCount' => count($texts)
            ]);
            
            // Fallback to mock embeddings
            return array_map([$this, 'generateMockEmbedding'], $texts);
        }
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have the same dimensions');
        }

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $norm1 += $embedding1[$i] * $embedding1[$i];
            $norm2 += $embedding2[$i] * $embedding2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 === 0 || $norm2 === 0) {
            return 0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    /**
     * Find most similar embeddings in a collection
     */
    public function findMostSimilar(array $queryEmbedding, array $candidateEmbeddings, int $topK = 5): array
    {
        $similarities = [];

        foreach ($candidateEmbeddings as $index => $candidateEmbedding) {
            $similarity = $this->calculateSimilarity($queryEmbedding, $candidateEmbedding);
            $similarities[] = [
                'index' => $index,
                'similarity' => $similarity
            ];
        }

        // Sort by similarity descending
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similarities, 0, $topK);
    }

    /**
     * Preprocess text for embedding generation
     */
    private function preprocessText(string $text): string
    {
        // Clean and normalize text
        $text = trim($text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Limit text length (OpenAI has token limits)
        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000) . '...';
        }
        
        return $text;
    }

    /**
     * Generate mock embedding for development/testing
     */
    private function generateMockEmbedding(string $text): array
    {
        // Generate a deterministic "embedding" based on text hash
        $hash = md5($text);
        $embedding = [];
        
        for ($i = 0; $i < $this->embeddingDimensions; $i++) {
            // Create pseudo-random values based on hash
            $seed = hexdec(substr($hash, $i % 32, 2));
            $value = ($seed / 255.0) * 2 - 1; // Normalize to [-1, 1]
            $embedding[] = $value;
        }
        
        // Normalize the vector
        $norm = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $embedding)));
        if ($norm > 0) {
            $embedding = array_map(function($x) use ($norm) { return $x / $norm; }, $embedding);
        }
        
        $this->logger->debug('Generated mock embedding', [
            'textLength' => strlen($text),
            'embeddingDimensions' => count($embedding)
        ]);
        
        return $embedding;
    }

    /**
     * Get embedding dimensions
     */
    public function getEmbeddingDimensions(): int
    {
        return $this->embeddingDimensions;
    }

    /**
     * Check if OpenAI API is configured
     */
    public function isOpenAIConfigured(): bool
    {
        return !empty($this->openaiApiKey);
    }

    /**
     * Test API connectivity
     */
    public function testConnectivity(): array
    {
        if (empty($this->openaiApiKey)) {
            return [
                'status' => 'not_configured',
                'message' => 'OpenAI API key not configured'
            ];
        }

        try {
            $response = $this->httpClient->request('GET', $this->openaiApiUrl . '/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                return [
                    'status' => 'connected',
                    'message' => 'Successfully connected to OpenAI API'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to connect to OpenAI API',
                    'statusCode' => $response->getStatusCode()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error connecting to OpenAI API: ' . $e->getMessage()
            ];
        }
    }
}
