<?php

namespace App\Service;

use App\Document\Patient;
use App\Security\SessionUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI Documentation Service
 * 
 * Provides AI-powered clinical documentation assistance including:
 * - SOAP note generation
 * - Visit summaries
 * - ICD-10 code suggestions
 * - Clinical note enhancement
 */
class AIDocumentationService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $openaiApiKey;
    private string $openaiApiUrl;
    private string $openaiModel;

    public function __construct(
        LoggerInterface $logger,
        string $openaiApiKey = '',
        string $openaiApiUrl = 'https://api.openai.com/v1/chat/completions',
        string $openaiModel = 'gpt-4'
    ) {
        $this->logger = $logger;
        $this->openaiApiKey = $openaiApiKey;
        $this->openaiApiUrl = $openaiApiUrl;
        $this->openaiModel = $openaiModel;
        $this->httpClient = HttpClient::create();
    }

    /**
     * Generate a SOAP note from patient data and conversation text
     */
    public function generateSOAPNote(
        Patient $patient, 
        SessionUser $doctor, 
        string $conversationText, 
        array $vitalSigns = [],
        array $physicalExam = []
    ): array {
        $this->logger->info('Generating SOAP note', [
            'patientId' => (string)$patient->getId(),
            'doctorId' => (string)$doctor->getId(),
            'conversationLength' => strlen($conversationText)
        ]);

        try {
            $prompt = $this->buildSOAPPrompt($patient, $doctor, $conversationText, $vitalSigns, $physicalExam);
            $this->logger->info('Calling OpenAI API', ['promptLength' => strlen($prompt)]);
            $response = $this->callOpenAI($prompt);
            $this->logger->info('OpenAI API response received', ['responseLength' => strlen($response)]);
            
            $soapNote = $this->parseSOAPResponse($response);
            
            $this->logger->info('SOAP note generated successfully', [
                'patientId' => (string)$patient->getId(),
                'doctorId' => (string)$doctor->getId()
            ]);

            return $soapNote;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate SOAP note', [
                'patientId' => (string)$patient->getId(),
                'doctorId' => (string)$doctor->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->getFallbackSOAPNote($patient, $doctor, $conversationText);
        }
    }

    /**
     * Generate a concise visit summary
     */
    public function generateVisitSummary(
        Patient $patient,
        SessionUser $doctor,
        string $rawNotes,
        array $diagnosis = [],
        array $medications = []
    ): string {
        $this->logger->info('Generating visit summary', [
            'patientId' => (string)$patient->getId(),
            'doctorId' => (string)$doctor->getId()
        ]);

        try {
            $prompt = $this->buildSummaryPrompt($patient, $doctor, $rawNotes, $diagnosis, $medications);
            $response = $this->callOpenAI($prompt);
            
            return $this->extractSummaryFromResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate visit summary', [
                'patientId' => (string)$patient->getId(),
                'doctorId' => (string)$doctor->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->getFallbackSummary($rawNotes);
        }
    }

    /**
     * Suggest ICD-10 codes based on symptoms and diagnosis
     */
    public function suggestICDCodes(
        array $symptoms,
        array $diagnosis,
        array $patientData = []
    ): array {
        $this->logger->info('Suggesting ICD-10 codes', [
            'symptomsCount' => count($symptoms),
            'diagnosisCount' => count($diagnosis)
        ]);

        try {
            $prompt = $this->buildICDPrompt($symptoms, $diagnosis, $patientData);
            $response = $this->callOpenAI($prompt);
            
            return $this->parseICDResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to suggest ICD-10 codes', [
                'error' => $e->getMessage()
            ]);
            
            return $this->getFallbackICDCodes($symptoms, $diagnosis);
        }
    }

    /**
     * Enhance existing clinical notes with AI suggestions
     */
    public function enhanceClinicalNotes(
        string $existingNotes,
        Patient $patient,
        array $context = []
    ): array {
        $this->logger->info('Enhancing clinical notes', [
            'patientId' => (string)$patient->getId(),
            'notesLength' => strlen($existingNotes)
        ]);

        try {
            $prompt = $this->buildEnhancementPrompt($existingNotes, $patient, $context);
            $response = $this->callOpenAI($prompt);
            
            return $this->parseEnhancementResponse($response);
        } catch (\Exception $e) {
            $this->logger->error('Failed to enhance clinical notes', [
                'patientId' => (string)$patient->getId(),
                'error' => $e->getMessage()
            ]);
            
            return [
                'enhanced_notes' => $this->getFallbackEnhancedNotes($existingNotes),
                'suggestions' => [
                    'Consider adding specific timing information (e.g., "headache for 2 hours")',
                    'Include severity scale (e.g., "mild", "moderate", "severe")',
                    'Add associated symptoms or triggers',
                    'Document patient\'s general appearance and vital signs'
                ],
                'confidence' => 0.6,
                'areas_for_improvement' => [
                    'Add more specific details about symptom duration',
                    'Include severity assessment',
                    'Document any associated symptoms'
                ]
            ];
        }
    }

    /**
     * Build SOAP note generation prompt
     */
    private function buildSOAPPrompt(
        Patient $patient,
        SessionUser $doctor,
        string $conversationText,
        array $vitalSigns,
        array $physicalExam
    ): string {
        $patientInfo = $this->getPatientInfoForPrompt($patient);
        $doctorInfo = $this->getDoctorInfoForPrompt($doctor);
        
        return "You are an AI assistant helping a healthcare provider generate a structured SOAP note. 

PATIENT INFORMATION:
{$patientInfo}

DOCTOR INFORMATION:
{$doctorInfo}

CONVERSATION/INTERVIEW:
{$conversationText}

VITAL SIGNS:
" . json_encode($vitalSigns) . "

PHYSICAL EXAMINATION FINDINGS:
" . json_encode($physicalExam) . "

Please generate a structured SOAP note with the following format:

SUBJECTIVE:
- Chief Complaint
- History of Present Illness
- Review of Systems
- Past Medical History (relevant)
- Medications
- Allergies

OBJECTIVE:
- Vital Signs
- Physical Examination Findings
- Laboratory Results (if mentioned)

ASSESSMENT:
- Primary Diagnosis
- Differential Diagnoses
- Clinical Reasoning

PLAN:
- Treatment Plan
- Medications
- Follow-up Instructions
- Patient Education

IMPORTANT: 
- Use medical terminology appropriately
- Be concise but comprehensive
- Include relevant clinical details
- Maintain professional medical documentation standards
- Do not include any information not provided in the input

Return the response as a JSON object with fields: subjective, objective, assessment, plan, confidence_score (0-1), and suggestions.";
    }

    /**
     * Build visit summary generation prompt
     */
    private function buildSummaryPrompt(
        Patient $patient,
        SessionUser $doctor,
        string $rawNotes,
        array $diagnosis,
        array $medications
    ): string {
        $patientInfo = $this->getPatientInfoForPrompt($patient);
        
        return "You are an AI assistant helping generate a concise visit summary for a healthcare provider.

PATIENT INFORMATION:
{$patientInfo}

DOCTOR: " . $doctor->getUsername() . "

RAW NOTES:
{$rawNotes}

DIAGNOSIS:
" . implode(', ', $diagnosis) . "

MEDICATIONS:
" . implode(', ', $medications) . "

Please generate a concise, professional visit summary (2-3 sentences) that includes:
- Chief complaint/reason for visit
- Key findings or diagnosis
- Treatment plan or next steps

The summary should be suitable for:
- Patient communication
- Referral letters
- Insurance documentation
- Medical records

Keep it clear, professional, and medically accurate.";
    }

    /**
     * Build ICD-10 code suggestion prompt
     */
    private function buildICDPrompt(array $symptoms, array $diagnosis, array $patientData): string {
        return "You are an AI assistant helping suggest appropriate ICD-10 codes for medical documentation.

SYMPTOMS:
" . implode(', ', $symptoms) . "

DIAGNOSIS:
" . implode(', ', $diagnosis) . "

PATIENT DATA:
" . json_encode($patientData) . "

Please suggest the most appropriate ICD-10 codes for this case. Consider:
- Primary diagnosis codes
- Secondary diagnosis codes
- Symptom codes if diagnosis is uncertain
- Comorbidity codes if applicable

Return the response as a JSON array with objects containing:
- code: ICD-10 code
- description: Code description
- category: Primary/Secondary/Symptom
- confidence: Confidence score (0-1)
- rationale: Brief explanation

Limit to 5-7 most relevant codes.";
    }

    /**
     * Build clinical notes enhancement prompt
     */
    private function buildEnhancementPrompt(string $existingNotes, Patient $patient, array $context): string {
        $patientInfo = $this->getPatientInfoForPrompt($patient);
        
        return "You are an AI assistant helping enhance clinical documentation.

PATIENT INFORMATION:
{$patientInfo}

EXISTING NOTES:
{$existingNotes}

CONTEXT:
" . json_encode($context) . "

Please analyze the existing notes and provide:
1. Enhanced version with improved clarity and completeness
2. Suggestions for additional information that might be helpful
3. Areas where the notes could be more specific or detailed

Return as JSON with:
- enhanced_notes: Improved version of the notes
- suggestions: Array of specific suggestions
- confidence: Confidence in the enhancement (0-1)
- areas_for_improvement: Specific areas that need work";
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt): string {
        if (empty($this->openaiApiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        // Construct the API URL for chat completions
        $apiUrl = rtrim($this->openaiApiUrl, '/') . '/chat/completions';

        $response = $this->httpClient->request('POST', $apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->openaiModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a medical AI assistant helping healthcare providers with clinical documentation. Always maintain professional medical standards and accuracy.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3, // Lower temperature for more consistent medical documentation
                'max_tokens' => 2000
            ]
        ]);

        $data = $response->toArray();
        
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid response from OpenAI API');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Parse SOAP note response
     */
    private function parseSOAPResponse(string $response): array {
        try {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        } catch (\Exception $e) {
            // Fall through to text parsing
        }

        // Fallback: parse as text
        return [
            'subjective' => $this->extractSection($response, 'SUBJECTIVE'),
            'objective' => $this->extractSection($response, 'OBJECTIVE'),
            'assessment' => $this->extractSection($response, 'ASSESSMENT'),
            'plan' => $this->extractSection($response, 'PLAN'),
            'confidence_score' => 0.8,
            'suggestions' => ['Generated from text format']
        ];
    }

    /**
     * Extract section from text response
     */
    private function extractSection(string $text, string $section): string {
        $pattern = "/{$section}:(.*?)(?=\n[A-Z]+:|$)/s";
        preg_match($pattern, $text, $matches);
        return isset($matches[1]) ? trim($matches[1]) : '';
    }

    /**
     * Extract summary from response
     */
    private function extractSummaryFromResponse(string $response): string {
        // Try to extract from JSON first
        try {
            $data = json_decode($response, true);
            if (isset($data['summary'])) {
                return $data['summary'];
            }
        } catch (\Exception $e) {
            // Fall through to return raw response
        }

        return trim($response);
    }

    /**
     * Parse ICD response
     */
    private function parseICDResponse(string $response): array {
        try {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        } catch (\Exception $e) {
            // Fall through to fallback
        }

        return $this->getFallbackICDCodes([], []);
    }

    /**
     * Parse enhancement response
     */
    private function parseEnhancementResponse(string $response): array {
        try {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        } catch (\Exception $e) {
            // Fall through to fallback
        }

        return [
            'enhanced_notes' => $response,
            'suggestions' => [],
            'confidence' => 0.5,
            'areas_for_improvement' => []
        ];
    }

    /**
     * Get patient info for prompts (HIPAA-safe)
     */
    private function getPatientInfoForPrompt(Patient $patient): string {
        return "Patient: {$patient->getFirstName()} {$patient->getLastName()}\n" .
               "Age: " . $this->calculateAge($patient->getBirthDate()) . "\n" .
               "Gender: [Not specified for privacy]\n" .
               "Previous diagnoses: " . implode(', ', $patient->getDiagnosis() ?? []) . "\n" .
               "Current medications: " . implode(', ', $patient->getMedications() ?? []);
    }

    /**
     * Get doctor info for prompts
     */
    private function getDoctorInfoForPrompt(SessionUser $doctor): string {
        return "Dr. {$doctor->getUsername()}\n" .
               "Specialty: [General Practice]\n" .
               "Date: " . date('Y-m-d');
    }

    /**
     * Calculate age from birth date
     */
    private function calculateAge($birthDate): int {
        if ($birthDate instanceof \MongoDB\BSON\UTCDateTime) {
            $birthDate = $birthDate->toDateTime();
        }
        
        $now = new \DateTime();
        return $now->diff($birthDate)->y;
    }

    /**
     * Fallback SOAP note when AI fails
     */
    private function getFallbackSOAPNote(Patient $patient, SessionUser $doctor, string $conversationText): array {
        // Extract key information from conversation text
        $conversationLower = strtolower($conversationText);
        $symptoms = [];
        $vitals = [];
        
        // Simple symptom extraction
        if (strpos($conversationLower, 'headache') !== false) $symptoms[] = 'headache';
        if (strpos($conversationLower, 'fever') !== false) $symptoms[] = 'fever';
        if (strpos($conversationLower, 'cough') !== false) $symptoms[] = 'cough';
        if (strpos($conversationLower, 'pain') !== false) $symptoms[] = 'pain';
        if (strpos($conversationLower, 'nausea') !== false) $symptoms[] = 'nausea';
        if (strpos($conversationLower, 'dizziness') !== false) $symptoms[] = 'dizziness';
        
        // Simple vital signs extraction
        if (preg_match('/bp[:\s]*(\d+\/\d+)/i', $conversationText, $matches)) $vitals[] = "BP: " . $matches[1];
        if (preg_match('/hr[:\s]*(\d+)/i', $conversationText, $matches)) $vitals[] = "HR: " . $matches[1];
        if (preg_match('/temp[:\s]*(\d+\.?\d*)/i', $conversationText, $matches)) $vitals[] = "Temp: " . $matches[1] . "°F";
        
        $subjective = "Patient reports: " . implode(', ', $symptoms ?: ['general complaints']);
        if (!empty($vitals)) {
            $subjective .= "\nVital signs: " . implode(', ', $vitals);
        }
        
        $objective = "Physical examination findings to be documented by provider.";
        if (!empty($vitals)) {
            $objective = "Vital signs: " . implode(', ', $vitals) . "\n" . $objective;
        }
        
        $assessment = "Clinical assessment pending provider review.";
        if (!empty($symptoms)) {
            $assessment = "Differential diagnosis to consider based on symptoms: " . implode(', ', $symptoms);
        }
        
        $plan = "Treatment plan to be determined by provider.";
        if (!empty($symptoms)) {
            $plan = "Consider symptomatic treatment for: " . implode(', ', $symptoms) . "\n" . $plan;
        }
        
        return [
            'subjective' => $subjective,
            'objective' => $objective,
            'assessment' => $assessment,
            'plan' => $plan,
            'confidence_score' => 0.6, // Higher confidence for structured fallback
            'suggestions' => [
                'Review and complete physical examination findings',
                'Consider appropriate diagnostic tests based on symptoms',
                'Document treatment plan after assessment'
            ]
        ];
    }

    /**
     * Fallback summary when AI fails
     */
    private function getFallbackSummary(string $rawNotes): string {
        // Extract key information from raw notes
        $notesLower = strtolower($rawNotes);
        $keyPoints = [];
        
        // Extract key medical terms
        if (strpos($notesLower, 'diagnosis') !== false) $keyPoints[] = 'Diagnosis discussed';
        if (strpos($notesLower, 'treatment') !== false) $keyPoints[] = 'Treatment plan reviewed';
        if (strpos($notesLower, 'medication') !== false) $keyPoints[] = 'Medications reviewed';
        if (strpos($notesLower, 'follow') !== false) $keyPoints[] = 'Follow-up scheduled';
        if (strpos($notesLower, 'test') !== false) $keyPoints[] = 'Tests ordered';
        
        $summary = "Visit Summary:\n";
        if (!empty($keyPoints)) {
            $summary .= "• " . implode("\n• ", $keyPoints) . "\n";
        }
        $summary .= "• Patient notes: " . substr($rawNotes, 0, 200);
        if (strlen($rawNotes) > 200) {
            $summary .= "...";
        }
        
        return $summary;
    }

    /**
     * Fallback ICD codes when AI fails
     */
    private function getFallbackICDCodes(array $symptoms, array $diagnosis): array {
        $codes = [];
        
        // Map common symptoms to ICD-10 codes
        $symptomCodes = [
            'headache' => ['R51', 'Headache'],
            'fever' => ['R50.9', 'Fever, unspecified'],
            'cough' => ['R05', 'Cough'],
            'pain' => ['R52', 'Pain, unspecified'],
            'nausea' => ['R11.0', 'Nausea'],
            'dizziness' => ['R42', 'Dizziness and giddiness'],
            'fatigue' => ['R53.83', 'Other fatigue'],
            'shortness of breath' => ['R06.02', 'Shortness of breath'],
            'chest pain' => ['R06.02', 'Chest pain, unspecified']
        ];
        
        // Add codes based on symptoms
        foreach ($symptoms as $symptom) {
            $symptomLower = strtolower(trim($symptom));
            foreach ($symptomCodes as $key => $code) {
                if (strpos($symptomLower, $key) !== false) {
                    $codes[] = [
                        'code' => $code[0],
                        'description' => $code[1],
                        'category' => 'Primary',
                        'confidence' => 0.7,
                        'rationale' => 'Based on reported symptom: ' . $symptom
                    ];
                    break;
                }
            }
        }
        
        // Add codes based on diagnosis
        foreach ($diagnosis as $diag) {
            $diagLower = strtolower(trim($diag));
            if (strpos($diagLower, 'hypertension') !== false) {
                $codes[] = [
                    'code' => 'I10',
                    'description' => 'Essential hypertension',
                    'category' => 'Primary',
                    'confidence' => 0.8,
                    'rationale' => 'Based on diagnosis: ' . $diag
                ];
            } elseif (strpos($diagLower, 'diabetes') !== false) {
                $codes[] = [
                    'code' => 'E11.9',
                    'description' => 'Type 2 diabetes mellitus without complications',
                    'category' => 'Primary',
                    'confidence' => 0.8,
                    'rationale' => 'Based on diagnosis: ' . $diag
                ];
            }
        }
        
        // Default fallback if no specific codes found
        if (empty($codes)) {
            $codes[] = [
                'code' => 'Z00.00',
                'description' => 'Encounter for general adult medical examination without abnormal findings',
                'category' => 'Primary',
                'confidence' => 0.5,
                'rationale' => 'General examination code - manual review recommended'
            ];
        }
        
        return $codes;
    }

    /**
     * Fallback enhanced notes when AI fails
     */
    private function getFallbackEnhancedNotes(string $existingNotes): string {
        // Basic enhancement of the existing notes
        $enhanced = $existingNotes;
        
        // Add basic improvements
        $enhanced = ucfirst(trim($enhanced));
        
        // Add period if missing
        if (!preg_match('/[.!?]$/', $enhanced)) {
            $enhanced .= '.';
        }
        
        // Add basic clinical context
        $enhanced .= "\n\nClinical Assessment: Patient presents with reported symptoms requiring further evaluation.";
        
        return $enhanced;
    }
}
