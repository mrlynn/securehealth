<?php

namespace App\Command;

use App\Service\MedicalKnowledgeService;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-medical-knowledge',
    description: 'Seed the medical knowledge base with sample data'
)]
class SeedMedicalKnowledgeCommand extends Command
{
    private MedicalKnowledgeService $knowledgeService;

    public function __construct(MedicalKnowledgeService $knowledgeService)
    {
        $this->knowledgeService = $knowledgeService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing knowledge before seeding')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of entries to create', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $clear = $input->getOption('clear');
        $count = (int) $input->getOption('count');

        if ($clear) {
            $io->note('Clearing existing medical knowledge...');
            // Note: In a real implementation, you would clear the collection here
        }

        $io->title('Seeding Medical Knowledge Base');
        $io->info("Creating {$count} sample medical knowledge entries...");

        $sampleData = $this->getSampleMedicalKnowledge();
        $created = 0;
        $errors = 0;

        // Start progress bar
        $io->progressStart($count);

        foreach (array_slice($sampleData, 0, $count) as $index => $data) {
            try {
                $knowledge = $this->knowledgeService->createKnowledgeEntry(
                    $data['title'],
                    $data['content'],
                    $data['source'],
                    $data['tags'],
                    $data['specialties'],
                    $data['summary'],
                    $data['sourceUrl'] ?? null,
                    isset($data['sourceDate']) ? new UTCDateTime(new \DateTime($data['sourceDate'])) : null,
                    $data['confidenceLevel'],
                    $data['evidenceLevel'],
                    $data['relatedConditions'],
                    $data['relatedMedications'],
                    $data['relatedProcedures'],
                    $data['requiresReview']
                );

                $created++;
                $io->progressAdvance();

            } catch (\Exception $e) {
                $errors++;
                $io->error("Failed to create entry {$index}: " . $e->getMessage());
                $io->progressAdvance(); // Still advance progress bar on error
            }
        }

        $io->progressFinish();

        if ($created > 0) {
            $io->success("Successfully created {$created} medical knowledge entries");
        }

        if ($errors > 0) {
            $io->warning("Failed to create {$errors} entries");
        }

        // Display statistics
        $stats = $this->knowledgeService->getKnowledgeBaseStats();
        $io->section('Knowledge Base Statistics');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Entries', $stats['totalEntries']],
                ['Average Confidence Level', $stats['avgConfidenceLevel']],
                ['Average Evidence Level', $stats['avgEvidenceLevel']],
                ['Total Specialties', $stats['totalSpecialties']],
                ['Total Sources', $stats['totalSources']]
            ]
        );

        return Command::SUCCESS;
    }

    private function getSampleMedicalKnowledge(): array
    {
        return [
            [
                'title' => 'Hypertension Management Guidelines',
                'content' => 'Hypertension is defined as systolic blood pressure ≥140 mmHg or diastolic blood pressure ≥90 mmHg. First-line treatment includes lifestyle modifications: weight reduction, DASH diet, sodium restriction, regular physical activity, and moderation of alcohol consumption. Pharmacological treatment should be initiated with ACE inhibitors, ARBs, calcium channel blockers, or thiazide diuretics based on patient characteristics and comorbidities.',
                'summary' => 'Comprehensive guidelines for hypertension diagnosis, lifestyle modifications, and pharmacological treatment options.',
                'source' => 'American Heart Association Guidelines',
                'sourceUrl' => 'https://www.ahajournals.org/journal/hyp',
                'sourceDate' => '2023-01-15',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['hypertension', 'cardiovascular', 'treatment', 'guidelines'],
                'specialties' => ['cardiology', 'internal-medicine', 'family-medicine'],
                'relatedConditions' => ['hypertension', 'cardiovascular-disease', 'diabetes', 'chronic-kidney-disease'],
                'relatedMedications' => ['lisinopril', 'losartan', 'amlodipine', 'hydrochlorothiazide'],
                'relatedProcedures' => ['blood-pressure-monitoring', 'echocardiogram', 'electrocardiogram'],
                'requiresReview' => false
            ],
            [
                'title' => 'Diabetes Type 2 Treatment Protocol',
                'content' => 'Type 2 diabetes management focuses on glycemic control through lifestyle interventions and pharmacotherapy. Metformin remains the first-line therapy for most patients. Additional agents include SGLT2 inhibitors, GLP-1 receptor agonists, and insulin when needed. Regular monitoring of HbA1c, blood glucose, and complications screening is essential. Target HbA1c is generally <7% for most adults.',
                'summary' => 'Evidence-based treatment protocol for Type 2 diabetes including medication options and monitoring guidelines.',
                'source' => 'American Diabetes Association Standards of Care',
                'sourceUrl' => 'https://diabetesjournals.org/care/issue/46/Supplement_1',
                'sourceDate' => '2023-01-01',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['diabetes', 'endocrinology', 'treatment', 'protocol'],
                'specialties' => ['endocrinology', 'internal-medicine', 'family-medicine'],
                'relatedConditions' => ['diabetes-mellitus-type-2', 'hyperglycemia', 'diabetic-nephropathy', 'diabetic-retinopathy'],
                'relatedMedications' => ['metformin', 'glipizide', 'insulin', 'semaglutide', 'empagliflozin'],
                'relatedProcedures' => ['hba1c-testing', 'glucose-monitoring', 'diabetic-foot-exam'],
                'requiresReview' => false
            ],
            [
                'title' => 'Acute Myocardial Infarction Diagnosis and Management',
                'content' => 'Acute myocardial infarction (AMI) requires immediate recognition and treatment. Diagnosis is based on clinical presentation, ECG changes, and cardiac biomarkers (troponin). Primary PCI is the preferred reperfusion strategy when available within 90 minutes. Medical therapy includes dual antiplatelet therapy, anticoagulation, beta-blockers, ACE inhibitors, and statins. Complications include arrhythmias, heart failure, and mechanical complications.',
                'summary' => 'Comprehensive guide for rapid diagnosis and evidence-based treatment of acute myocardial infarction.',
                'source' => 'European Society of Cardiology Guidelines',
                'sourceUrl' => 'https://academic.oup.com/eurheartj',
                'sourceDate' => '2022-12-01',
                'confidenceLevel' => 10,
                'evidenceLevel' => 5,
                'tags' => ['myocardial-infarction', 'cardiology', 'emergency', 'treatment'],
                'specialties' => ['cardiology', 'emergency-medicine', 'internal-medicine'],
                'relatedConditions' => ['acute-myocardial-infarction', 'unstable-angina', 'heart-failure', 'cardiogenic-shock'],
                'relatedMedications' => ['aspirin', 'clopidogrel', 'heparin', 'metoprolol', 'lisinopril'],
                'relatedProcedures' => ['primary-pci', 'coronary-angiography', 'ecg', 'echocardiogram'],
                'requiresReview' => false
            ],
            [
                'title' => 'Asthma Exacerbation Treatment Protocol',
                'content' => 'Acute asthma exacerbations require prompt assessment and treatment. Severity is determined by peak flow, oxygen saturation, and clinical presentation. Treatment includes short-acting beta-agonists, systemic corticosteroids, and oxygen therapy. Severe exacerbations may require magnesium sulfate, heliox, or mechanical ventilation. Discharge planning includes optimization of controller medications and patient education.',
                'summary' => 'Evidence-based protocol for managing acute asthma exacerbations in emergency and inpatient settings.',
                'source' => 'Global Initiative for Asthma (GINA)',
                'sourceUrl' => 'https://ginasthma.org/',
                'sourceDate' => '2023-04-01',
                'confidenceLevel' => 8,
                'evidenceLevel' => 4,
                'tags' => ['asthma', 'pulmonology', 'emergency', 'exacerbation'],
                'specialties' => ['pulmonology', 'emergency-medicine', 'pediatrics', 'internal-medicine'],
                'relatedConditions' => ['asthma', 'chronic-obstructive-pulmonary-disease', 'bronchitis'],
                'relatedMedications' => ['albuterol', 'prednisone', 'methylprednisolone', 'ipratropium'],
                'relatedProcedures' => ['peak-flow-measurement', 'arterial-blood-gas', 'chest-x-ray'],
                'requiresReview' => false
            ],
            [
                'title' => 'Sepsis Recognition and Management',
                'content' => 'Sepsis is a life-threatening organ dysfunction caused by a dysregulated host response to infection. Early recognition using qSOFA or SOFA scores is crucial. The sepsis bundle includes blood cultures, lactate measurement, broad-spectrum antibiotics within 1 hour, fluid resuscitation, and vasopressors if needed. Source control and monitoring for organ dysfunction are essential components of care.',
                'summary' => 'Critical care protocol for early recognition and evidence-based management of sepsis and septic shock.',
                'source' => 'Surviving Sepsis Campaign Guidelines',
                'sourceUrl' => 'https://www.sccm.org/',
                'sourceDate' => '2023-03-01',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['sepsis', 'critical-care', 'infection', 'emergency'],
                'specialties' => ['critical-care', 'emergency-medicine', 'internal-medicine', 'infectious-disease'],
                'relatedConditions' => ['sepsis', 'septic-shock', 'multi-organ-dysfunction', 'bacteremia'],
                'relatedMedications' => ['vancomycin', 'piperacillin-tazobactam', 'norepinephrine', 'vasopressin'],
                'relatedProcedures' => ['blood-cultures', 'lactate-measurement', 'central-line-placement'],
                'requiresReview' => false
            ],
            [
                'title' => 'Atrial Fibrillation Management',
                'content' => 'Atrial fibrillation (AF) management focuses on rate control, rhythm control, and stroke prevention. Rate control with beta-blockers or calcium channel blockers is often first-line. Rhythm control may be considered for symptomatic patients. All patients with AF should be assessed for stroke risk using CHA2DS2-VASc score. Anticoagulation with warfarin or DOACs is recommended for high-risk patients.',
                'summary' => 'Comprehensive approach to atrial fibrillation management including rate control, rhythm control, and anticoagulation.',
                'source' => 'American College of Cardiology Guidelines',
                'sourceUrl' => 'https://www.acc.org/',
                'sourceDate' => '2023-02-15',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['atrial-fibrillation', 'cardiology', 'arrhythmia', 'anticoagulation'],
                'specialties' => ['cardiology', 'electrophysiology', 'internal-medicine'],
                'relatedConditions' => ['atrial-fibrillation', 'stroke', 'heart-failure', 'hypertension'],
                'relatedMedications' => ['metoprolol', 'diltiazem', 'warfarin', 'apixaban', 'rivaroxaban'],
                'relatedProcedures' => ['cardioversion', 'catheter-ablation', 'echocardiogram'],
                'requiresReview' => false
            ],
            [
                'title' => 'Pneumonia Treatment Guidelines',
                'content' => 'Community-acquired pneumonia (CAP) treatment is based on severity assessment using CURB-65 or PSI scores. Outpatient treatment includes beta-lactams or respiratory fluoroquinolones. Inpatient treatment may require broader coverage including MRSA and atypical pathogens. Healthcare-associated pneumonia requires broader antimicrobial coverage. Duration of therapy is typically 5-7 days for uncomplicated cases.',
                'summary' => 'Evidence-based guidelines for diagnosis and treatment of community-acquired and healthcare-associated pneumonia.',
                'source' => 'Infectious Diseases Society of America',
                'sourceUrl' => 'https://www.idsociety.org/',
                'sourceDate' => '2023-01-20',
                'confidenceLevel' => 8,
                'evidenceLevel' => 4,
                'tags' => ['pneumonia', 'infectious-disease', 'respiratory', 'antibiotics'],
                'specialties' => ['infectious-disease', 'pulmonology', 'internal-medicine', 'emergency-medicine'],
                'relatedConditions' => ['community-acquired-pneumonia', 'healthcare-associated-pneumonia', 'sepsis'],
                'relatedMedications' => ['amoxicillin-clavulanate', 'levofloxacin', 'ceftriaxone', 'azithromycin'],
                'relatedProcedures' => ['chest-x-ray', 'blood-cultures', 'sputum-culture', 'urine-antigen-test'],
                'requiresReview' => false
            ],
            [
                'title' => 'Chronic Kidney Disease Management',
                'content' => 'Chronic kidney disease (CKD) management focuses on slowing progression and managing complications. ACE inhibitors or ARBs are recommended for proteinuria and hypertension. Blood pressure targets are <130/80 mmHg. Diabetes management with tight glycemic control is crucial. Phosphate binders and vitamin D analogs may be needed for mineral bone disease. Nephrology referral is recommended for eGFR <30.',
                'summary' => 'Comprehensive management strategy for chronic kidney disease including progression monitoring and complication prevention.',
                'source' => 'Kidney Disease: Improving Global Outcomes (KDIGO)',
                'sourceUrl' => 'https://kdigo.org/',
                'sourceDate' => '2023-03-15',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['chronic-kidney-disease', 'nephrology', 'renal', 'management'],
                'specialties' => ['nephrology', 'internal-medicine', 'family-medicine'],
                'relatedConditions' => ['chronic-kidney-disease', 'diabetes', 'hypertension', 'proteinuria'],
                'relatedMedications' => ['lisinopril', 'losartan', 'furosemide', 'calcium-carbonate'],
                'relatedProcedures' => ['creatinine-measurement', 'urinalysis', 'kidney-ultrasound'],
                'requiresReview' => false
            ],
            [
                'title' => 'Stroke Prevention in Atrial Fibrillation',
                'content' => 'Stroke prevention in atrial fibrillation requires careful risk stratification. CHA2DS2-VASc score determines stroke risk, while HAS-BLED score assesses bleeding risk. Anticoagulation with warfarin (INR 2-3) or direct oral anticoagulants (DOACs) is recommended for patients with CHA2DS2-VASc ≥2 (men) or ≥3 (women). Left atrial appendage closure may be considered for patients with contraindications to anticoagulation.',
                'summary' => 'Evidence-based approach to stroke prevention in patients with atrial fibrillation using risk stratification and anticoagulation.',
                'source' => 'European Heart Rhythm Association',
                'sourceUrl' => 'https://www.escardio.org/',
                'sourceDate' => '2023-02-01',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['atrial-fibrillation', 'stroke-prevention', 'anticoagulation', 'risk-stratification'],
                'specialties' => ['cardiology', 'neurology', 'internal-medicine'],
                'relatedConditions' => ['atrial-fibrillation', 'stroke', 'transient-ischemic-attack'],
                'relatedMedications' => ['warfarin', 'apixaban', 'rivaroxaban', 'dabigatran'],
                'relatedProcedures' => ['left-atrial-appendage-closure', 'echocardiogram'],
                'requiresReview' => false
            ],
            [
                'title' => 'Drug Interactions: Warfarin and Common Medications',
                'content' => 'Warfarin has numerous drug interactions that can significantly affect INR and bleeding risk. Major interactions include: antibiotics (trimethoprim-sulfamethoxazole, ciprofloxacin), antifungals (fluconazole, voriconazole), antiplatelets (aspirin, clopidogrel), and many others. Regular INR monitoring is essential when starting or stopping interacting medications. Patient education about bleeding precautions and medication compliance is crucial.',
                'summary' => 'Comprehensive guide to warfarin drug interactions, monitoring requirements, and clinical management.',
                'source' => 'American College of Clinical Pharmacy',
                'sourceUrl' => 'https://www.accp.com/',
                'sourceDate' => '2023-01-10',
                'confidenceLevel' => 8,
                'evidenceLevel' => 4,
                'tags' => ['warfarin', 'drug-interactions', 'anticoagulation', 'pharmacology'],
                'specialties' => ['pharmacy', 'cardiology', 'internal-medicine', 'hematology'],
                'relatedConditions' => ['atrial-fibrillation', 'deep-vein-thrombosis', 'pulmonary-embolism'],
                'relatedMedications' => ['warfarin', 'aspirin', 'clopidogrel', 'fluconazole', 'ciprofloxacin'],
                'relatedProcedures' => ['inr-monitoring', 'pt-inr-testing'],
                'requiresReview' => false
            ],
            [
                'title' => 'Acute Stroke Treatment Protocol',
                'content' => 'Acute stroke treatment requires rapid assessment and intervention. Time is brain - every minute counts. IV thrombolysis with alteplase is indicated for eligible patients within 4.5 hours of symptom onset. Mechanical thrombectomy may be considered for large vessel occlusions up to 24 hours. Blood pressure management, glucose control, and fever prevention are important supportive measures.',
                'summary' => 'Time-critical protocol for acute stroke treatment including thrombolysis and mechanical thrombectomy criteria.',
                'source' => 'American Heart Association/American Stroke Association',
                'sourceUrl' => 'https://www.stroke.org/',
                'sourceDate' => '2023-01-05',
                'confidenceLevel' => 10,
                'evidenceLevel' => 5,
                'tags' => ['stroke', 'neurology', 'emergency', 'thrombolysis'],
                'specialties' => ['neurology', 'emergency-medicine', 'interventional-radiology'],
                'relatedConditions' => ['ischemic-stroke', 'hemorrhagic-stroke', 'transient-ischemic-attack'],
                'relatedMedications' => ['alteplase', 'aspirin', 'clopidogrel', 'nimodipine'],
                'relatedProcedures' => ['ct-scan', 'mri', 'mechanical-thrombectomy', 'carotid-endarterectomy'],
                'requiresReview' => false
            ],
            [
                'title' => 'Heart Failure Management Guidelines',
                'content' => 'Heart failure management includes both pharmacological and non-pharmacological approaches. ACE inhibitors or ARBs are first-line therapy, with beta-blockers added for symptomatic improvement. Diuretics manage fluid overload. SGLT2 inhibitors have shown benefit in heart failure regardless of diabetes status. Device therapy (ICD, CRT) may be indicated for appropriate patients. Patient education on fluid restriction and daily weights is essential.',
                'summary' => 'Comprehensive heart failure management including pharmacological therapy, device therapy, and lifestyle modifications.',
                'source' => 'American College of Cardiology Foundation',
                'sourceUrl' => 'https://www.acc.org/',
                'sourceDate' => '2023-02-20',
                'confidenceLevel' => 9,
                'evidenceLevel' => 5,
                'tags' => ['heart-failure', 'cardiology', 'management', 'guidelines'],
                'specialties' => ['cardiology', 'internal-medicine', 'heart-failure-specialist'],
                'relatedConditions' => ['heart-failure', 'left-ventricular-dysfunction', 'atrial-fibrillation'],
                'relatedMedications' => ['lisinopril', 'carvedilol', 'furosemide', 'spironolactone', 'empagliflozin'],
                'relatedProcedures' => ['echocardiogram', 'icd-implantation', 'cardiac-resynchronization-therapy'],
                'requiresReview' => false
            ]
        ];
    }
}
