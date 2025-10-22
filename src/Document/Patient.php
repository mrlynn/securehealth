<?php

namespace App\Document;

use App\Service\MongoDBEncryptionService;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'patients')]
class Patient
{
    /**
     * Patient ID
     * @Assert\NotBlank(message="ID is required")
     */
    #[ODM\Id]
    private $id = null;
    
    /**
     * Patient's last name - deterministically encrypted (searchable)
     * @Assert\NotBlank(message="Last name is required")
     * @Assert\Length(
     *     min=2, 
     *     max=50, 
     *     minMessage="Last name must be at least {{ limit }} characters long",
     *     maxMessage="Last name cannot be longer than {{ limit }} characters"
     * )
     */
    #[ODM\Field(type: 'string')]
    private string $lastName;

    /**
     * Patient's first name - deterministically encrypted (searchable)
     * @Assert\NotBlank(message="First name is required")
     * @Assert\Length(
     *     min=2, 
     *     max=50, 
     *     minMessage="First name must be at least {{ limit }} characters long",
     *     maxMessage="First name cannot be longer than {{ limit }} characters"
     * )
     */
    #[ODM\Field(type: 'string')]
    private string $firstName;

    /**
     * Patient email - deterministically encrypted (searchable)
     * @Assert\Email(
     *     message="The email {{ value }} is not a valid email address"
     * )
     * @Assert\NotBlank(message="Email is required")
     */
    #[ODM\Field(type: 'string')]
    private string $email;
    
    /**
     * Patient phone number - deterministically encrypted (searchable)
     * @Assert\Regex(
     *     pattern="/^\d{3}-\d{3}-\d{4}$/",
     *     message="Phone number must be in format XXX-XXX-XXXX",
     *     normalizer="trim"
     * )
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $phoneNumber = null;

    /**
     * Patient's birth date - range encrypted (supports range queries)
     * @Assert\NotBlank(message="Birth date is required")
     */
    #[ODM\Field(type: 'date')]
    private UTCDateTime $birthDate;

    /**
     * Social Security Number - strongly encrypted (no search)
     * @Assert\Regex(
     *     pattern="/^\d{3}-\d{2}-\d{4}$/",
     *     message="SSN must be in format XXX-XX-XXXX"
     * )
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $ssn = null;

    /**
     * Medical diagnosis - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'collection')]
    private ?array $diagnosis = [];

    /**
     * Medications - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'collection')]
    private ?array $medications = [];
    
    /**
     * Insurance details - strongly encrypted (no search)
     */
    #[ODM\Field(type: 'hash', nullable: true)]
    private ?array $insuranceDetails = null;

    /**
     * Medical notes - strongly encrypted (no search)
     * @deprecated Use notesHistory instead for better tracking
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes = null;

    /**
     * Medical notes history - array of note entries with timestamps and doctor attribution
     * Each entry contains: content, doctorId, doctorName, createdAt, updatedAt
     */
    #[ODM\Field(type: 'collection')]
    private array $notesHistory = [];
    
    /**
     * Record creation timestamp
     */
    #[ODM\Field(type: 'date')]
    private UTCDateTime $createdAt;
    
    /**
     * Record update timestamp
     */
    #[ODM\Field(type: 'date', nullable: true)]
    private ?UTCDateTime $updatedAt = null;
    
    /**
     * Primary doctor ID reference
     */
    #[ODM\Field(type: 'object_id', nullable: true)]
    private ?ObjectId $primaryDoctorId = null;

    public function __construct()
    {
        $this->createdAt = new UTCDateTime();
        $this->diagnosis = [];
        $this->medications = [];
        $this->notesHistory = [];
    }

    /**
     * Convert object to an array with role-based access control
     * @param UserInterface|string|null $userOrRole Either a User object or a role string
     * @return array
     */
    public function toArray($userOrRole = null): array
    {
        $data = [
            'id' => (string)$this->getId(),
            'firstName' => $this->getFirstName(),
            'lastName' => $this->getLastName(),
            'email' => $this->getEmail(),
            'phoneNumber' => $this->getPhoneNumber(),
            'birthDate' => $this->getBirthDate() ? $this->getBirthDate()->toDateTime()->format('Y-m-d') : null,
            'createdAt' => $this->getCreatedAt() ? $this->getCreatedAt()->toDateTime()->format('Y-m-d H:i:s') : null
        ];

        // Get roles either from user object or directly from string
        $roles = [];
        if ($userOrRole instanceof UserInterface) {
            $roles = $userOrRole->getRoles();
        } elseif (is_string($userOrRole)) {
            $roles = [$userOrRole];
        }
        
        // Doctors can see all patient data
        if (in_array('ROLE_DOCTOR', $roles)) {
            $data['ssn'] = $this->getSsn();
            $data['diagnosis'] = $this->getDiagnosis();
            $data['medications'] = $this->getMedications();
            $data['insuranceDetails'] = $this->getInsuranceDetails();
            $data['notes'] = $this->getNotes();
            $data['notesHistory'] = $this->getNotesHistory();
            $data['primaryDoctorId'] = $this->getPrimaryDoctorId() ? (string)$this->getPrimaryDoctorId() : null;
        }
        // Nurses can see diagnosis and medications but not SSN
        elseif (in_array('ROLE_NURSE', $roles)) {
            $data['diagnosis'] = $this->getDiagnosis();
            $data['medications'] = $this->getMedications();
            $data['notes'] = $this->getNotes();
            $data['notesHistory'] = $this->getNotesHistory();
        }
        // Receptionists can see insurance details but no medical data
        elseif (in_array('ROLE_RECEPTIONIST', $roles)) {
            $data['insuranceDetails'] = $this->getInsuranceDetails();
        }
        // Patients can see their own basic info, medications, and appointments but not SSN or provider notes
        elseif (in_array('ROLE_PATIENT', $roles)) {
            $data['medications'] = $this->getMedications();
            $data['insuranceDetails'] = $this->getInsuranceDetails();
            // Patients don't see diagnosis details, SSN, or provider notes for privacy
        }

        return $data;
    }

    /**
     * Create a Patient from an array of data
     */
    public static function fromArray(array $data, MongoDBEncryptionService $encryptionService): self
    {
        $patient = new self();

        if (isset($data['firstName'])) {
            $patient->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $patient->setLastName($data['lastName']);
        }

        if (isset($data['email'])) {
            $patient->setEmail($data['email']);
        }

        if (isset($data['phoneNumber'])) {
            $patient->setPhoneNumber($data['phoneNumber']);
        }

        if (isset($data['birthDate'])) {
            if ($data['birthDate'] instanceof UTCDateTime) {
                $patient->setBirthDate($data['birthDate']);
            } elseif (is_string($data['birthDate'])) {
                $dateTime = new \DateTime($data['birthDate']);
                $patient->setBirthDate(new UTCDateTime($dateTime));
            }
        }

        if (isset($data['ssn'])) {
            $patient->setSsn($data['ssn']);
        }

        if (isset($data['diagnosis']) && is_array($data['diagnosis'])) {
            $patient->setDiagnosis($data['diagnosis']);
        }

        if (isset($data['medications']) && is_array($data['medications'])) {
            $patient->setMedications($data['medications']);
        }

        if (isset($data['insuranceDetails'])) {
            $patient->setInsuranceDetails($data['insuranceDetails']);
        }

        if (isset($data['notes'])) {
            $patient->setNotes($data['notes']);
        }

        if (isset($data['primaryDoctorId'])) {
            if ($data['primaryDoctorId'] instanceof ObjectId) {
                $patient->setPrimaryDoctorId($data['primaryDoctorId']);
            } elseif (is_string($data['primaryDoctorId'])) {
                $patient->setPrimaryDoctorId(new ObjectId($data['primaryDoctorId']));
            }
        }

        return $patient;
    }

    /**
     * Create a Patient from a MongoDB document (with decryption)
     */
    public static function fromDocument(array $document, MongoDBEncryptionService $encryptionService): self
    {
        $patient = new self();

        // Set ID if present
        if (isset($document['_id'])) {
            if ($document['_id'] instanceof ObjectId) {
                $patient->id = $document['_id'];
            } elseif (is_string($document['_id'])) {
                $patient->id = new ObjectId($document['_id']);
            }
        }

        // Decrypt and set fields
        if (isset($document['firstName'])) {
            $patient->firstName = $encryptionService->decrypt($document['firstName']);
        }

        if (isset($document['lastName'])) {
            $patient->lastName = $encryptionService->decrypt($document['lastName']);
        }

        if (isset($document['email'])) {
            $patient->email = $encryptionService->decrypt($document['email']);
        }

        if (isset($document['phoneNumber'])) {
            $patient->phoneNumber = $encryptionService->decrypt($document['phoneNumber']);
        }

        if (isset($document['birthDate'])) {
            $decryptedBirthDate = $encryptionService->decrypt($document['birthDate']);
            if ($decryptedBirthDate instanceof UTCDateTime) {
                $patient->birthDate = $decryptedBirthDate;
            } elseif (is_string($decryptedBirthDate)) {
                $patient->birthDate = new UTCDateTime(new \DateTime($decryptedBirthDate));
            } else {
                $patient->birthDate = $decryptedBirthDate;
            }
        }

        if (isset($document['ssn'])) {
            $patient->ssn = $encryptionService->decrypt($document['ssn']);
        }

        if (isset($document['diagnosis'])) {
            $decryptedDiagnosis = $encryptionService->decrypt($document['diagnosis']);
            if ($decryptedDiagnosis instanceof \MongoDB\Model\BSONArray) {
                $patient->diagnosis = iterator_to_array($decryptedDiagnosis);
            } elseif (is_array($decryptedDiagnosis)) {
                $patient->diagnosis = $decryptedDiagnosis;
            } else {
                $patient->diagnosis = [];
            }
        }

        if (isset($document['medications'])) {
            $decryptedMedications = $encryptionService->decrypt($document['medications']);
            if ($decryptedMedications instanceof \MongoDB\Model\BSONArray) {
                $patient->medications = iterator_to_array($decryptedMedications);
            } elseif (is_array($decryptedMedications)) {
                $patient->medications = $decryptedMedications;
            } else {
                $patient->medications = [];
            }
        }

        if (isset($document['insuranceDetails'])) {
            $decryptedInsurance = $encryptionService->decrypt($document['insuranceDetails']);
            if ($decryptedInsurance instanceof \MongoDB\Model\BSONDocument) {
                $patient->insuranceDetails = iterator_to_array($decryptedInsurance);
            } elseif ($decryptedInsurance instanceof \stdClass) {
                $patient->insuranceDetails = (array) $decryptedInsurance;
            } elseif (is_array($decryptedInsurance)) {
                $patient->insuranceDetails = $decryptedInsurance;
            } else {
                $patient->insuranceDetails = null;
            }
        }

        if (isset($document['notes'])) {
            $patient->notes = $encryptionService->decrypt($document['notes']);
        }

        if (isset($document['notesHistory'])) {
            // Handle arrays by decrypting JSON strings
            $decryptedNotesHistory = $encryptionService->decrypt($document['notesHistory']);
            
            // Handle both old data (arrays) and new data (JSON strings)
            if (is_array($decryptedNotesHistory)) {
                // Old data format - already an array, convert dates if needed
                $patient->notesHistory = self::convertNoteDatesToUTCDateTime($decryptedNotesHistory);
            } elseif (is_string($decryptedNotesHistory)) {
                // New data format - JSON string that needs decoding
                $notesArray = json_decode($decryptedNotesHistory, true) ?? [];
                $patient->notesHistory = self::convertNoteDatesToUTCDateTime($notesArray);
            } else {
                // Fallback for unexpected data types
                $patient->notesHistory = [];
            }
        }

        if (isset($document['createdAt'])) {
            $patient->createdAt = $document['createdAt'];
        }

        if (isset($document['updatedAt'])) {
            $patient->updatedAt = $document['updatedAt'];
        }

        if (isset($document['primaryDoctorId'])) {
            $patient->primaryDoctorId = $document['primaryDoctorId'];
        }

        return $patient;
    }

    /**
     * Convert Patient to MongoDB document (with manual encryption)
     */
    public function toDocument(MongoDBEncryptionService $encryptionService): array
    {
        $document = [];

        if ($this->id) {
            $document['_id'] = $this->id;
        }

        // Manual encryption for HIPAA compliance
        $document['firstName'] = $encryptionService->encrypt('patient', 'firstName', $this->firstName);
        $document['lastName'] = $encryptionService->encrypt('patient', 'lastName', $this->lastName);
        $document['email'] = $encryptionService->encrypt('patient', 'email', $this->email);
        
        if ($this->phoneNumber) {
            $document['phoneNumber'] = $encryptionService->encrypt('patient', 'phoneNumber', $this->phoneNumber);
        }
        
        $document['birthDate'] = $encryptionService->encrypt('patient', 'birthDate', $this->birthDate);
        
        if ($this->ssn) {
            $document['ssn'] = $encryptionService->encrypt('patient', 'ssn', $this->ssn);
        }
        
        if ($this->diagnosis) {
            $document['diagnosis'] = $encryptionService->encrypt('patient', 'diagnosis', $this->diagnosis);
        }
        
        if ($this->medications) {
            $document['medications'] = $encryptionService->encrypt('patient', 'medications', $this->medications);
        }
        
        if ($this->insuranceDetails) {
            $document['insuranceDetails'] = $encryptionService->encrypt('patient', 'insuranceDetails', $this->insuranceDetails);
        }
        
        if ($this->notes) {
            $document['notes'] = $encryptionService->encrypt('patient', 'notes', $this->notes);
        }
        
        if (!empty($this->notesHistory)) {
            // Convert UTCDateTime objects to strings for JSON encoding
            $notesHistoryForJson = [];
            foreach ($this->notesHistory as $note) {
                $noteForJson = $note;
                
                // Handle createdAt
                if (is_array($note) && isset($note['createdAt'])) {
                    if ($note['createdAt'] instanceof UTCDateTime) {
                        try {
                            $noteForJson['createdAt'] = $note['createdAt']->toDateTime()->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            $noteForJson['createdAt'] = $note['createdAt']->__toString();
                        }
                    }
                }
                
                // Handle updatedAt
                if (is_array($note) && isset($note['updatedAt'])) {
                    if ($note['updatedAt'] instanceof UTCDateTime) {
                        try {
                            $noteForJson['updatedAt'] = $note['updatedAt']->toDateTime()->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            $noteForJson['updatedAt'] = $note['updatedAt']->__toString();
                        }
                    }
                }
                
                $notesHistoryForJson[] = $noteForJson;
            }
            
            // Handle arrays by converting to JSON string for encryption
            $document['notesHistory'] = $encryptionService->encrypt('patient', 'notesHistory', json_encode($notesHistoryForJson));
        }
        
        $document['createdAt'] = $this->createdAt;
        
        if ($this->updatedAt) {
            $document['updatedAt'] = $this->updatedAt;
        }
        
        if ($this->primaryDoctorId) {
            $document['primaryDoctorId'] = $this->primaryDoctorId;
        }

        return $document;
    }

    
    // Getters and Setters

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        if (is_string($id)) {
            $this->id = new ObjectId($id);
        } else {
            $this->id = $id;
        }
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getBirthDate(): UTCDateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate(UTCDateTime $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getSsn(): ?string
    {
        return $this->ssn;
    }

    public function setSsn(?string $ssn): self
    {
        $this->ssn = $ssn;
        return $this;
    }

    public function getDiagnosis(): ?array
    {
        return $this->diagnosis;
    }

    public function setDiagnosis($diagnosis): self
    {
        // Convert stdClass to array if needed
        if ($diagnosis instanceof \stdClass) {
            $diagnosis = (array) $diagnosis;
        } elseif (is_null($diagnosis)) {
            $diagnosis = [];
        }
        
        $this->diagnosis = $diagnosis;
        return $this;
    }

    public function addDiagnosis(string $diagnosis): self
    {
        if (!in_array($diagnosis, $this->diagnosis)) {
            $this->diagnosis[] = $diagnosis;
        }
        return $this;
    }

    public function removeDiagnosis(string $diagnosis): self
    {
        if (($key = array_search($diagnosis, $this->diagnosis)) !== false) {
            unset($this->diagnosis[$key]);
            $this->diagnosis = array_values($this->diagnosis);
        }
        return $this;
    }

    public function getMedications(): ?array
    {
        return $this->medications;
    }

    public function setMedications($medications): self
    {
        // Convert stdClass to array if needed
        if ($medications instanceof \stdClass) {
            $medications = (array) $medications;
        } elseif (is_null($medications)) {
            $medications = [];
        }
        
        $this->medications = $medications;
        return $this;
    }

    public function addMedication(string $medication): self
    {
        if (!in_array($medication, $this->medications)) {
            $this->medications[] = $medication;
        }
        return $this;
    }

    public function removeMedication(string $medication): self
    {
        if (($key = array_search($medication, $this->medications)) !== false) {
            unset($this->medications[$key]);
            $this->medications = array_values($this->medications);
        }
        return $this;
    }

    public function getInsuranceDetails(): ?array
    {
        return $this->insuranceDetails;
    }

    public function setInsuranceDetails($insuranceDetails): self
    {
        // Convert stdClass to array if needed
        if ($insuranceDetails instanceof \stdClass) {
            $insuranceDetails = (array) $insuranceDetails;
        }
        
        $this->insuranceDetails = $insuranceDetails;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): UTCDateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(UTCDateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?UTCDateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?UTCDateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getPrimaryDoctorId(): ?ObjectId
    {
        return $this->primaryDoctorId;
    }

    public function setPrimaryDoctorId(?ObjectId $primaryDoctorId): self
    {
        $this->primaryDoctorId = $primaryDoctorId;
        return $this;
    }

    public function getNotesHistory(): array
    {
        return $this->notesHistory;
    }

    public function setNotesHistory(array $notesHistory): self
    {
        $this->notesHistory = $notesHistory;
        return $this;
    }

    /**
     * Add a new note to the patient's notes history
     * 
     * @param string $content The note content
     * @param ObjectId $doctorId The ID of the doctor adding the note
     * @param string $doctorName The name of the doctor adding the note
     * @return self
     */
    public function addNote(string $content, ObjectId $doctorId, string $doctorName): self
    {
        $note = [
            'id' => (string) new ObjectId(),
            'content' => $content,
            'doctorId' => (string) $doctorId,
            'doctorName' => $doctorName,
            'createdAt' => new UTCDateTime(),
            'updatedAt' => new UTCDateTime()
        ];
        
        $this->notesHistory[] = $note;
        return $this;
    }

    /**
     * Add an AI-generated note to the patient's notes history
     * 
     * @param string $content The AI-generated note content
     * @param ObjectId $doctorId The ID of the doctor who requested the AI generation
     * @param string $doctorName The name of the doctor who requested the AI generation
     * @param string $aiType The type of AI generation (soap_note, visit_summary, enhanced_notes, etc.)
     * @param float $confidenceScore The confidence score of the AI generation (0-1)
     * @param array $metadata Additional metadata about the AI generation
     * @return self
     */
    public function addAINote(
        string $content, 
        ObjectId $doctorId, 
        string $doctorName, 
        string $aiType, 
        float $confidenceScore = 0.0,
        array $metadata = []
    ): self {
        $now = new UTCDateTime();
        $note = [
            'id' => (string) new ObjectId(),
            'content' => $content,
            'doctorId' => (string) $doctorId,
            'doctorName' => $doctorName,
            'createdAt' => $now,
            'updatedAt' => $now,
            'aiGenerated' => true,
            'aiType' => $aiType,
            'confidenceScore' => $confidenceScore,
            'metadata' => $metadata
        ];
        
        $this->notesHistory[] = $note;
        
        // Update the patient's updatedAt timestamp
        $this->updatedAt = $now;
        
        return $this;
    }

    /**
     * Update an existing note in the patient's notes history
     * 
     * @param string $noteId The ID of the note to update
     * @param string $content The updated note content
     * @param ObjectId $doctorId The ID of the doctor updating the note
     * @param string $doctorName The name of the doctor updating the note
     * @return bool True if note was found and updated, false otherwise
     */
    public function updateNote(string $noteId, string $content, ObjectId $doctorId, string $doctorName): bool
    {
        foreach ($this->notesHistory as &$note) {
            if ($note['id'] === $noteId) {
                $note['content'] = $content;
                $note['doctorId'] = (string) $doctorId;
                $note['doctorName'] = $doctorName;
                $note['updatedAt'] = new UTCDateTime();
                return true;
            }
        }
        
        return false;
    }

    /**
     * Remove a note from the patient's notes history
     * 
     * @param string $noteId The ID of the note to remove
     * @return bool True if note was found and removed, false otherwise
     */
    public function removeNote(string $noteId): bool
    {
        foreach ($this->notesHistory as $index => $note) {
            if ($note['id'] === $noteId) {
                unset($this->notesHistory[$index]);
                $this->notesHistory = array_values($this->notesHistory); // Re-index array
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get a specific note by ID
     * 
     * @param string $noteId The ID of the note to retrieve
     * @return array|null The note if found, null otherwise
     */
    public function getNoteById(string $noteId): ?array
    {
        foreach ($this->notesHistory as $note) {
            if ($note['id'] === $noteId) {
                return $note;
            }
        }
        
        return null;
    }
    
    /**
     * Convert date strings in notes array back to UTCDateTime objects
     */
    private static function convertNoteDatesToUTCDateTime(array $notes): array
    {
        foreach ($notes as &$note) {
            // Ensure $note is an array
            if (!is_array($note)) {
                continue;
            }
            
            if (isset($note['createdAt'])) {
                if (is_string($note['createdAt'])) {
                    try {
                        $note['createdAt'] = new UTCDateTime(new \DateTime($note['createdAt']));
                    } catch (\Exception $e) {
                        // If date parsing fails, keep as string
                    }
                } elseif ($note['createdAt'] instanceof UTCDateTime) {
                    // Already a UTCDateTime, keep as is
                }
            }
            
            if (isset($note['updatedAt'])) {
                if (is_string($note['updatedAt'])) {
                    try {
                        $note['updatedAt'] = new UTCDateTime(new \DateTime($note['updatedAt']));
                    } catch (\Exception $e) {
                        // If date parsing fails, keep as string
                    }
                } elseif ($note['updatedAt'] instanceof UTCDateTime) {
                    // Already a UTCDateTime, keep as is
                }
            }
        }
        return $notes;
    }
}