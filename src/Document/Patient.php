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
     */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes = null;
    
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
            $data['primaryDoctorId'] = $this->getPrimaryDoctorId() ? (string)$this->getPrimaryDoctorId() : null;
        }
        // Nurses can see diagnosis and medications but not SSN
        elseif (in_array('ROLE_NURSE', $roles)) {
            $data['diagnosis'] = $this->getDiagnosis();
            $data['medications'] = $this->getMedications();
            $data['notes'] = $this->getNotes();
        }
        // Receptionists can see insurance details but no medical data
        elseif (in_array('ROLE_RECEPTIONIST', $roles)) {
            $data['insuranceDetails'] = $this->getInsuranceDetails();
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
            $patient->diagnosis = $encryptionService->decrypt($document['diagnosis']);
        }

        if (isset($document['medications'])) {
            $patient->medications = $encryptionService->decrypt($document['medications']);
        }

        if (isset($document['insuranceDetails'])) {
            $decryptedInsurance = $encryptionService->decrypt($document['insuranceDetails']);
            if ($decryptedInsurance instanceof \stdClass) {
                $patient->insuranceDetails = (array) $decryptedInsurance;
            } else {
                $patient->insuranceDetails = $decryptedInsurance;
            }
        }

        if (isset($document['notes'])) {
            $patient->notes = $encryptionService->decrypt($document['notes']);
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
     * Convert Patient to MongoDB document (with encryption)
     */
    public function toDocument(MongoDBEncryptionService $encryptionService): array
    {
        $document = [];

        if ($this->id) {
            $document['_id'] = $this->id;
        }

        // Encrypt sensitive fields
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
}