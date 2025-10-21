<?php

namespace App\Controller;

use App\Document\MedicalKnowledge;
use App\Security\Voter\MedicalKnowledgeVoter;
use App\Service\MedicalKnowledgeService;
use App\Service\AuditLogService;
use App\Service\VectorSearchService;
use MongoDB\BSON\UTCDateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/medical-knowledge', name: 'admin_medical_knowledge_')]
class MedicalKnowledgeManagementController extends AbstractController
{
    private MedicalKnowledgeService $knowledgeService;
    private AuditLogService $auditLogService;
    private VectorSearchService $vectorSearchService;

    public function __construct(
        MedicalKnowledgeService $knowledgeService,
        AuditLogService $auditLogService,
        VectorSearchService $vectorSearchService
    ) {
        $this->knowledgeService = $knowledgeService;
        $this->auditLogService = $auditLogService;
        $this->vectorSearchService = $vectorSearchService;
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted(MedicalKnowledgeVoter::VIEW)]
    public function index(Request $request): Response
    {
        $specialty = $request->query->get('specialty');
        $tag = $request->query->get('tag');
        $searchQuery = $request->query->get('q');

        $knowledgeEntries = [];
        $stats = $this->knowledgeService->getKnowledgeBaseStats();

        if ($searchQuery) {
            // Search by query string
            if (strlen($searchQuery) >= 3) {
                $knowledgeEntries = $this->knowledgeService->textSearch($searchQuery);
            }
        } elseif ($specialty) {
            // Filter by specialty
            $knowledgeEntries = $this->knowledgeService->getBySpecialty($specialty);
        } elseif ($tag) {
            // Filter by tag
            $knowledgeEntries = $this->knowledgeService->getByTags([$tag]);
        } else {
            // Get all active entries
            $knowledgeEntries = $this->knowledgeService->getAllActive();
        }

        $this->auditLogService->log(
            $this->getUser(),
            'MEDICAL_KNOWLEDGE_LIST_VIEW',
            [
                'searchQuery' => $searchQuery,
                'specialty' => $specialty,
                'tag' => $tag,
                'resultCount' => count($knowledgeEntries)
            ]
        );

        return $this->render('admin/medical-knowledge/index.html.twig', [
            'entries' => $knowledgeEntries,
            'stats' => $stats,
            'searchQuery' => $searchQuery,
            'specialty' => $specialty,
            'tag' => $tag
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(MedicalKnowledgeVoter::CREATE)]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $title = $request->request->get('title');
                $content = $request->request->get('content');
                $summary = $request->request->get('summary');
                $source = $request->request->get('source');
                $sourceUrl = $request->request->get('sourceUrl');
                $sourceDate = $request->request->get('sourceDate');
                $tags = $request->request->all('tags');
                $specialties = $request->request->all('specialties');
                $relatedConditions = $request->request->all('relatedConditions');
                $relatedMedications = $request->request->all('relatedMedications');
                $relatedProcedures = $request->request->all('relatedProcedures');
                $confidenceLevel = (int)$request->request->get('confidenceLevel', 5);
                $evidenceLevel = (int)$request->request->get('evidenceLevel', 3);
                $requiresReview = $request->request->getBoolean('requiresReview', false);

                // Validate required fields
                if (!$title || !$content || !$source) {
                    $this->addFlash('error', 'Title, content and source are required fields.');
                    return $this->redirectToRoute('admin_medical_knowledge_new');
                }

                $knowledge = $this->knowledgeService->createKnowledgeEntry(
                    $title,
                    $content,
                    $source,
                    $tags,
                    $specialties,
                    $summary,
                    $sourceUrl,
                    $sourceDate ? new UTCDateTime(new \DateTime($sourceDate)) : null,
                    $confidenceLevel,
                    $evidenceLevel,
                    $relatedConditions,
                    $relatedMedications,
                    $relatedProcedures,
                    $requiresReview,
                    $this->getUser()
                );

                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_CREATE',
                    [
                        'id' => (string)$knowledge->getId(),
                        'title' => $knowledge->getTitle()
                    ]
                );

                $this->addFlash('success', 'Medical knowledge entry created successfully.');
                return $this->redirectToRoute('admin_medical_knowledge_view', ['id' => $knowledge->getId()]);
            } catch (\Exception $e) {
                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_CREATE_ERROR',
                    [
                        'error' => $e->getMessage()
                    ]
                );

                $this->addFlash('error', 'Error creating medical knowledge: ' . $e->getMessage());
                return $this->redirectToRoute('admin_medical_knowledge_new');
            }
        }

        return $this->render('admin/medical-knowledge/new.html.twig', [
            'specialties' => $this->getCommonSpecialties(),
            'tags' => $this->getCommonTags(),
            'conditions' => $this->getCommonConditions(),
            'medications' => $this->getCommonMedications(),
            'procedures' => $this->getCommonProcedures()
        ]);
    }

    #[Route('/{id}', name: 'view', methods: ['GET'])]
    #[IsGranted(MedicalKnowledgeVoter::VIEW)]
    public function view(string $id): Response
    {
        $knowledge = $this->knowledgeService->getById($id);

        if (!$knowledge) {
            $this->addFlash('error', 'Medical knowledge entry not found.');
            return $this->redirectToRoute('admin_medical_knowledge_index');
        }

        $this->auditLogService->log(
            $this->getUser(),
            'MEDICAL_KNOWLEDGE_VIEW',
            [
                'id' => $id,
                'title' => $knowledge->getTitle()
            ]
        );

        // Find related knowledge entries
        $related = [];

        if ($knowledge->getRelatedConditions()) {
            $relatedByConditions = $this->knowledgeService->getByCondition($knowledge->getRelatedConditions()[0]);
            $related = array_merge($related, array_slice($relatedByConditions, 0, 3));
        }

        if ($knowledge->getRelatedMedications() && count($related) < 5) {
            $relatedByMedications = $this->knowledgeService->getByMedication($knowledge->getRelatedMedications()[0]);
            $related = array_merge($related, array_slice($relatedByMedications, 0, 3));
        }

        return $this->render('admin/medical-knowledge/view.html.twig', [
            'knowledge' => $knowledge,
            'related' => array_slice($related, 0, 5) // Limit to 5 related entries
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted(MedicalKnowledgeVoter::EDIT)]
    public function edit(Request $request, string $id): Response
    {
        $knowledge = $this->knowledgeService->getById($id);

        if (!$knowledge) {
            $this->addFlash('error', 'Medical knowledge entry not found.');
            return $this->redirectToRoute('admin_medical_knowledge_index');
        }

        if ($request->isMethod('POST')) {
            try {
                $title = $request->request->get('title');
                $content = $request->request->get('content');
                $summary = $request->request->get('summary');
                $tags = $request->request->all('tags');
                $specialties = $request->request->all('specialties');
                $relatedConditions = $request->request->all('relatedConditions');
                $relatedMedications = $request->request->all('relatedMedications');
                $relatedProcedures = $request->request->all('relatedProcedures');
                $confidenceLevel = (int)$request->request->get('confidenceLevel', 5);
                $evidenceLevel = (int)$request->request->get('evidenceLevel', 3);
                $requiresReview = $request->request->getBoolean('requiresReview', false);

                // Validate required fields
                if (!$title || !$content) {
                    $this->addFlash('error', 'Title and content are required fields.');
                    return $this->redirectToRoute('admin_medical_knowledge_edit', ['id' => $id]);
                }

                $knowledge = $this->knowledgeService->updateKnowledgeEntry(
                    $id,
                    $title,
                    $content,
                    $summary,
                    $confidenceLevel,
                    $evidenceLevel,
                    $tags,
                    $specialties,
                    $relatedConditions,
                    $relatedMedications,
                    $relatedProcedures,
                    $requiresReview
                );

                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_UPDATE',
                    [
                        'id' => $id,
                        'title' => $knowledge->getTitle()
                    ]
                );

                $this->addFlash('success', 'Medical knowledge entry updated successfully.');
                return $this->redirectToRoute('admin_medical_knowledge_view', ['id' => $id]);
            } catch (\Exception $e) {
                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_UPDATE_ERROR',
                    [
                        'id' => $id,
                        'error' => $e->getMessage()
                    ]
                );

                $this->addFlash('error', 'Error updating medical knowledge: ' . $e->getMessage());
                return $this->redirectToRoute('admin_medical_knowledge_edit', ['id' => $id]);
            }
        }

        return $this->render('admin/medical-knowledge/edit.html.twig', [
            'knowledge' => $knowledge,
            'specialties' => $this->getCommonSpecialties(),
            'tags' => $this->getCommonTags(),
            'conditions' => $this->getCommonConditions(),
            'medications' => $this->getCommonMedications(),
            'procedures' => $this->getCommonProcedures()
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    #[IsGranted(MedicalKnowledgeVoter::EDIT)]
    public function toggleStatus(Request $request, string $id): Response
    {
        try {
            $knowledge = $this->knowledgeService->getById($id);
            
            if (!$knowledge) {
                return $this->json(['success' => false, 'message' => 'Medical knowledge entry not found'], Response::HTTP_NOT_FOUND);
            }
            
            $isActive = $knowledge->getIsActive();
            $knowledge->setIsActive(!$isActive);
            $knowledge->touchUpdatedAt();
            
            $this->knowledgeService->updateKnowledgeEntry(
                $id,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null
            );
            
            $action = $isActive ? 'MEDICAL_KNOWLEDGE_DEACTIVATE' : 'MEDICAL_KNOWLEDGE_ACTIVATE';
            $message = $isActive ? 'Entry deactivated successfully' : 'Entry activated successfully';
            
            $this->auditLogService->log(
                $this->getUser(),
                $action,
                [
                    'id' => $id,
                    'title' => $knowledge->getTitle()
                ]
            );
            
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'message' => $message,
                    'isActive' => !$isActive
                ]);
            } else {
                $this->addFlash('success', $message);
                return $this->redirectToRoute('admin_medical_knowledge_view', ['id' => $id]);
            }
        } catch (\Exception $e) {
            $this->auditLogService->log(
                $this->getUser(),
                'MEDICAL_KNOWLEDGE_STATUS_UPDATE_ERROR',
                [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]
            );
            
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Error updating status: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            } else {
                $this->addFlash('error', 'Error updating status: ' . $e->getMessage());
                return $this->redirectToRoute('admin_medical_knowledge_view', ['id' => $id]);
            }
        }
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    #[IsGranted(MedicalKnowledgeVoter::IMPORT)]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $source = $request->request->get('source');
                $importData = $request->request->get('importData');
                
                if (!$source || !$importData) {
                    $this->addFlash('error', 'Source and import data are required.');
                    return $this->redirectToRoute('admin_medical_knowledge_import');
                }
                
                // Parse JSON data
                $data = json_decode($importData, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->addFlash('error', 'Invalid JSON format: ' . json_last_error_msg());
                    return $this->redirectToRoute('admin_medical_knowledge_import');
                }
                
                $result = $this->knowledgeService->importFromExternalSource(
                    $source,
                    $data,
                    $this->getUser()
                );
                
                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_IMPORT',
                    [
                        'source' => $source,
                        'imported' => count($result['imported']),
                        'errors' => count($result['errors'])
                    ]
                );
                
                $this->addFlash('success', sprintf(
                    'Import completed: %d entries imported successfully, %d errors.',
                    count($result['imported']),
                    count($result['errors'])
                ));
                
                return $this->redirectToRoute('admin_medical_knowledge_index');
            } catch (\Exception $e) {
                $this->auditLogService->log(
                    $this->getUser(),
                    'MEDICAL_KNOWLEDGE_IMPORT_ERROR',
                    [
                        'error' => $e->getMessage()
                    ]
                );
                
                $this->addFlash('error', 'Import error: ' . $e->getMessage());
                return $this->redirectToRoute('admin_medical_knowledge_import');
            }
        }
        
        return $this->render('admin/medical-knowledge/import.html.twig');
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    #[IsGranted(MedicalKnowledgeVoter::VIEW_STATS)]
    public function stats(): Response
    {
        $stats = $this->knowledgeService->getKnowledgeBaseStats();
        
        $this->auditLogService->log(
            $this->getUser(),
            'MEDICAL_KNOWLEDGE_STATS_VIEW',
            [
                'totalEntries' => $stats['totalEntries'] ?? 0
            ]
        );
        
        return $this->render('admin/medical-knowledge/stats.html.twig', [
            'stats' => $stats
        ]);
    }

    /**
     * Helper method to get common medical specialties
     */
    private function getCommonSpecialties(): array
    {
        return [
            'Cardiology',
            'Dermatology',
            'Endocrinology',
            'Family Medicine',
            'Gastroenterology',
            'Geriatrics',
            'Hematology',
            'Infectious Disease',
            'Internal Medicine',
            'Nephrology',
            'Neurology',
            'Obstetrics and Gynecology',
            'Oncology',
            'Ophthalmology',
            'Orthopedics',
            'Otolaryngology',
            'Pediatrics',
            'Psychiatry',
            'Pulmonology',
            'Rheumatology',
            'Urology'
        ];
    }

    /**
     * Helper method to get common medical tags
     */
    private function getCommonTags(): array
    {
        return [
            'Acute',
            'Chronic',
            'Diagnostic',
            'Emergency',
            'Preventive',
            'Surgical',
            'Therapeutic',
            'Pediatric',
            'Geriatric',
            'Medication',
            'Procedure',
            'Treatment',
            'Guidelines',
            'Research',
            'Clinical Trial',
            'Side Effects',
            'Drug Interactions',
            'Contraindications',
            'Follow-up',
            'Referral'
        ];
    }

    /**
     * Helper method to get common medical conditions
     */
    private function getCommonConditions(): array
    {
        return [
            'Hypertension',
            'Diabetes Mellitus',
            'Asthma',
            'COPD',
            'Coronary Artery Disease',
            'Heart Failure',
            'Atrial Fibrillation',
            'Stroke',
            'Osteoarthritis',
            'Rheumatoid Arthritis',
            'Osteoporosis',
            'Depression',
            'Anxiety Disorder',
            'Alzheimer\'s Disease',
            'Parkinson\'s Disease',
            'Multiple Sclerosis',
            'Epilepsy',
            'Migraine',
            'Hypothyroidism',
            'Hyperthyroidism',
            'Chronic Kidney Disease',
            'GERD',
            'Peptic Ulcer Disease',
            'Inflammatory Bowel Disease',
            'Irritable Bowel Syndrome',
            'Hepatitis',
            'Cirrhosis',
            'Anemia',
            'Breast Cancer',
            'Lung Cancer',
            'Prostate Cancer',
            'Colorectal Cancer',
            'Melanoma',
            'HIV/AIDS',
            'Tuberculosis',
            'Pneumonia',
            'Urinary Tract Infection',
            'Cellulitis',
            'Obesity',
            'Malnutrition'
        ];
    }

    /**
     * Helper method to get common medications
     */
    private function getCommonMedications(): array
    {
        return [
            'Aspirin',
            'Acetaminophen',
            'Ibuprofen',
            'Naproxen',
            'Lisinopril',
            'Amlodipine',
            'Metoprolol',
            'Atenolol',
            'Losartan',
            'Hydrochlorothiazide',
            'Furosemide',
            'Spironolactone',
            'Metformin',
            'Glipizide',
            'Insulin',
            'Levothyroxine',
            'Albuterol',
            'Fluticasone',
            'Montelukast',
            'Omeprazole',
            'Pantoprazole',
            'Ranitidine',
            'Simvastatin',
            'Atorvastatin',
            'Rosuvastatin',
            'Warfarin',
            'Clopidogrel',
            'Apixaban',
            'Rivaroxaban',
            'Amoxicillin',
            'Azithromycin',
            'Ciprofloxacin',
            'Doxycycline',
            'Prednisone',
            'Gabapentin',
            'Pregabalin',
            'Sertraline',
            'Fluoxetine',
            'Escitalopram',
            'Duloxetine',
            'Alprazolam',
            'Lorazepam',
            'Zolpidem',
            'Tramadol',
            'Oxycodone',
            'Hydrocodone'
        ];
    }

    /**
     * Helper method to get common medical procedures
     */
    private function getCommonProcedures(): array
    {
        return [
            'Physical Examination',
            'Vital Signs',
            'Electrocardiogram (ECG/EKG)',
            'Echocardiogram',
            'Stress Test',
            'Cardiac Catheterization',
            'Coronary Angiography',
            'X-ray',
            'Computed Tomography (CT)',
            'Magnetic Resonance Imaging (MRI)',
            'Ultrasound',
            'Mammography',
            'Colonoscopy',
            'Endoscopy',
            'Bronchoscopy',
            'Pulmonary Function Tests',
            'Blood Tests',
            'Urinalysis',
            'Biopsy',
            'Lumbar Puncture',
            'Joint Aspiration',
            'Bone Marrow Aspiration',
            'Dialysis',
            'Physical Therapy',
            'Occupational Therapy',
            'Speech Therapy',
            'Cognitive Behavioral Therapy',
            'Radiation Therapy',
            'Chemotherapy',
            'Immunotherapy',
            'Appendectomy',
            'Cholecystectomy',
            'Hernia Repair',
            'Hysterectomy',
            'Cesarean Section',
            'Joint Replacement',
            'Coronary Artery Bypass Graft (CABG)',
            'Angioplasty',
            'Stent Placement',
            'Pacemaker Insertion',
            'Cataract Surgery',
            'LASIK',
            'Tonsillectomy',
            'Wisdom Tooth Extraction',
            'Wound Care',
            'Casting and Splinting',
            'Incision and Drainage',
            'Suturing',
            'Debridement',
            'Intubation'
        ];
    }
}