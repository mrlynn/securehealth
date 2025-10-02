# Video Script: Building HIPAA-Compliant Medical Records with MongoDB Queryable Encryption

**Duration:** 12-15 minutes  
**Style:** Engaging developer advocate presentation with live coding  
**Target Audience:** Developers building healthcare applications

---

## INTRO (1 minute)

### Visual: Screen showing title card with MongoDB logo

**[Michael on camera, enthusiastic energy]**

"Hey everyone, Michael Lynn here! Today I want to show you something that's going to change how you think about building healthcare applications.

For years, developers have been stuck in this impossible situation: you need to encrypt patient data to comply with HIPAA, but the moment you encrypt everything, you can't search it anymore. You're forced to choose between security and functionality.

Well, not anymore.

In the next 12 minutes, I'm going to show you how to build a real, production-ready medical records system using MongoDB's Queryable Encryption. We're talking:
- Searching encrypted data WITHOUT decrypting it
- Role-based access control that actually works
- Full HIPAA compliance with audit logs
- And it's all surprisingly simple

Let's dive in!"

### Visual: Quick montage of final application
- Patient search interface
- Encrypted data in MongoDB Compass
- Audit logs showing access trails
- Different role views

---

## PART 1: THE PROBLEM (1.5 minutes)

### Visual: Screen showing a diagram of traditional encryption problems

**[Sharing screen, pointing to diagram]**

"Alright, so let's talk about the problem first. Healthcare data breaches are a BIG deal - we're talking an average cost of $7.8 million per incident. HIPAA exists for good reason.

**[Show traditional encryption diagram]**

The traditional approach looks like this:

Option 1: Encrypt the entire database
- Problem: Can't search anything
- Have to decrypt everything to find anything
- Performance nightmare

Option 2: Application-layer encryption
- Problem: Massive complexity
- Keys everywhere
- Still can't query encrypted fields

Option 3: Tokenization
- Problem: Complexity explosion
- Maintaining token mappings
- Still limited query capability

**[Pause for emphasis]**

None of these feel right! We want the best of both worlds.

**[Show MongoDB Queryable Encryption logo/diagram]**

Enter MongoDB Queryable Encryption. This changes everything. It lets us search encrypted data WITHOUT decrypting it on the server. The database never sees your plaintext data. Ever.

Let me show you how it works."

---

## PART 2: ARCHITECTURE WALKTHROUGH (2 minutes)

### Visual: Architecture diagram animation

**[Animated diagram showing data flow]**

"Here's our architecture. It's actually beautifully simple:

**[Point to frontend]**

We've got a web interface built with HTML, JavaScript, and Bootstrap. Nothing fancy here - it's role-aware, so doctors see different things than nurses.

**[Point to API layer]**

Behind that is our Symfony 7.0 backend API. This is where the magic happens:
- JWT authentication for users
- Role-based access control
- Encryption service that handles all our crypto
- Audit logging service for HIPAA compliance

**[Point to MongoDB]**

And then MongoDB Atlas with Queryable Encryption. This is our secret weapon.

**[Animate data flow]**

Now watch what happens when a doctor searches for a patient named 'Smith':

1. Doctor types 'Smith' in the search box
2. Frontend sends encrypted JWT token to API
3. Symfony validates: 'Are you a doctor? Cool.'
4. Encryption service encrypts 'Smith' using deterministic encryption
5. MongoDB searches the encrypted field
6. MongoDB returns encrypted results
7. Symfony decrypts them - but only the fields the doctor can see
8. Audit service logs: 'Dr. Jones searched for Smith'
9. Response goes back to the frontend

**[Highlight the key point]**

The beautiful part? MongoDB NEVER sees 'Smith' in plaintext. Even if someone hacks the database, they get encrypted gibberish. Even your database administrators can't read patient data.

This is called 'zero-knowledge' security, and it's a game-changer."

---

## PART 3: SETTING UP (2 minutes)

### Visual: MongoDB Atlas interface

**[Show MongoDB Atlas dashboard]**

"Alright, let's build this thing! First step: MongoDB Atlas setup.

**[Click through Atlas interface]**

I'm creating an M10 cluster here - that's the minimum tier for Queryable Encryption. You'll need:
1. MongoDB Atlas cluster (M10+)
2. Queryable Encryption enabled
3. A key vault namespace

**[Show key vault setup]**

The key vault is where MongoDB stores your Data Encryption Keys. These are encrypted by your Customer Master Key, which we're going to generate now.

**[Switch to terminal]**

In production, you'd use AWS KMS or Azure Key Vault for this, but for development, we can use a local key:

```bash
openssl rand -base64 96 > docker/encryption.key
```

**[Show the key file]**

96 bytes of pure randomness. This is what protects everything.

**Important:** Never commit this to Git. Add it to .gitignore immediately.

**[Show Docker Compose file]**

Here's our Docker setup. Two containers:
- PHP/Symfony
- Nginx

We're pointing to MongoDB Atlas, and we're mounting our encryption key.

**[Terminal: docker-compose up]**

Let's fire it up:

```bash
docker-compose up -d
docker-compose exec php composer install
```

And we're running!"

---

## PART 4: THE ENCRYPTION SERVICE - THE HEART OF THE SYSTEM (3 minutes)

### Visual: Code editor showing MongoDBEncryptionService.php

**[Open MongoDBEncryptionService.php]**

"This right here is the heart of our system: the MongoDBEncryptionService.

**[Scroll through the class]**

It handles three things:
1. Deciding what gets encrypted and how
2. Encrypting data before it goes to MongoDB
3. Decrypting data when we read it back

**[Highlight the encryption algorithms]**

We have three encryption types:

```php
const ALGORITHM_DETERMINISTIC = 'AEAD_AES_256_CBC_HMAC_SHA_512-Deterministic';
const ALGORITHM_RANDOM = 'AEAD_AES_256_CBC_HMAC_SHA_512-Random';
const ALGORITHM_RANGE = 'range';
```

**[Explain each one]**

Deterministic: Same input always gives same encrypted output. This means we can search it! Use it for names, emails, phone numbers.

Random: Maximum security. Same input gives different encrypted output every time. Can't search it, but it's super secure. Use it for SSN, diagnoses, medical notes.

Range: For dates and numbers. Enables range queries on encrypted data. We're not using it in this demo, but in production you'd want it for birth dates.

**[Show the configuration method]**

Here's where we decide what gets encrypted:

```php
private function configureEncryptedFieldsDefinitions(): void
{
    $this->encryptedFields['patient'] = [
        // Searchable fields
        'lastName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'firstName' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        'email' => ['algorithm' => self::ALGORITHM_DETERMINISTIC],
        
        // Super sensitive - no search
        'ssn' => ['algorithm' => self::ALGORITHM_RANDOM],
        'diagnosis' => ['algorithm' => self::ALGORITHM_RANDOM],
        'medications' => ['algorithm' => self::ALGORITHM_RANDOM],
    ];
}
```

**[Point to the encrypt method]**

The encrypt method is surprisingly simple:

```php
public function encrypt(string $documentType, string $fieldName, $value): Binary
{
    // Get the config
    $config = $this->encryptedFields[$documentType][$fieldName];
    
    // Get or create the Data Encryption Key
    $keyId = $this->getOrCreateDataKey("{$documentType}_{$fieldName}_key");
    
    // Encrypt!
    return $this->clientEncryption->encrypt($value, [
        'keyId' => $keyId,
        'algorithm' => $config['algorithm']
    ]);
}
```

**[Emphasize the key management]**

Notice we're using separate Data Encryption Keys for each field. This gives us granular control. If we need to rotate keys, we can do it field by field.

The MongoDB driver does all the heavy lifting. We just tell it what to encrypt and how."

---

## PART 5: THE PATIENT DOCUMENT (2 minutes)

### Visual: Code editor showing Patient.php

**[Open Patient.php]**

"Now let's look at our Patient document. This represents an actual patient record with Protected Health Information.

**[Scroll through the properties]**

Look at these fields:

```php
// Searchable - deterministic encryption
private string $lastName;
private string $firstName;
private string $email;

// Super sensitive - random encryption
private ?string $ssn = null;
private ?array $diagnosis = [];
private ?array $medications = [];
```

**[Highlight the toDocument method]**

When we save to MongoDB, we call toDocument():

```php
public function toDocument(MongoDBEncryptionService $encryptionService): array
{
    return [
        'firstName' => $encryptionService->encrypt('patient', 'firstName', $this->firstName),
        'lastName' => $encryptionService->encrypt('patient', 'lastName', $this->lastName),
        'ssn' => $encryptionService->encrypt('patient', 'ssn', $this->ssn),
        // ...
    ];
}
```

Every sensitive field gets encrypted before it touches MongoDB.

**[Show the fromDocument method]**

When we read from MongoDB:

```php
public static function fromDocument(array $document, MongoDBEncryptionService $encryptionService): self
{
    $patient = new self();
    $patient->setFirstName($encryptionService->decrypt($document['firstName']));
    $patient->setLastName($encryptionService->decrypt($document['lastName']));
    // ...
}
```

We decrypt everything coming back.

**[Now the important part - toArray method]**

But here's the coolest part - role-based access control:

```php
public function toArray($user = null): array
{
    $data = [
        'firstName' => $this->firstName,
        'lastName' => $this->lastName,
        // Basic info everyone sees
    ];
    
    if (in_array('ROLE_DOCTOR', $user->getRoles())) {
        // Doctors see EVERYTHING
        $data['ssn'] = $this->ssn;
        $data['diagnosis'] = $this->diagnosis;
    }
    elseif (in_array('ROLE_NURSE', $user->getRoles())) {
        // Nurses see medical info but NOT SSN
        $data['diagnosis'] = $this->diagnosis;
    }
    elseif (in_array('ROLE_RECEPTIONIST', $user->getRoles())) {
        // Receptionists see insurance only
        $data['insuranceDetails'] = $this->insuranceDetails;
    }
    
    return $data;
}
```

**[Pause for emphasis]**

This is HIPAA's 'minimum necessary' rule in code. Each role sees only what they need to do their job."

---

## PART 6: SEARCHING ENCRYPTED DATA - THE MAGIC (2 minutes)

### Visual: Code editor showing PatientRepository.php

**[Open PatientRepository.php, show findByLastName method]**

"Okay, this is where it gets really cool. Let's search for a patient by last name.

Remember, lastName is encrypted. So how do we search it?

```php
public function findByLastName(string $lastName, MongoDBEncryptionService $encryptionService): array
{
    // Step 1: Encrypt the search term
    $encryptedLastName = $encryptionService->encrypt('patient', 'lastName', $lastName);
    
    // Step 2: Search using the encrypted value
    $cursor = $collection->find(['lastName' => $encryptedLastName]);
    
    // Step 3: Decrypt the results
    foreach ($cursor as $document) {
        $patients[] = Patient::fromDocument($document, $encryptionService);
    }
    
    return $patients;
}
```

**[Break it down step by step]**

Here's what's happening:

1. User searches for 'Smith'
2. We encrypt 'Smith' using deterministic encryption
3. MongoDB searches for that EXACT encrypted value
4. Because we used deterministic encryption, all 'Smith' entries have the same encrypted value
5. MongoDB finds them WITHOUT decrypting anything
6. We get the results back (still encrypted)
7. We decrypt them before returning to the user

**[Switch to MongoDB Compass]**

Let me show you what this looks like in the database.

**[Show MongoDB Compass with encrypted data]**

See these Binary objects? That's encrypted data. Even looking directly at the database, you can't see 'Smith'. You just see random bytes.

But our application can search it!

**[Switch back to code]**

The key is using the SAME encryption algorithm and key. That's why deterministic encryption works for searching."

---

## PART 7: AUDIT LOGGING (1.5 minutes)

### Visual: Code editor showing AuditLogService.php

**[Show AuditLogService.php]**

"HIPAA requires comprehensive audit logs. Every access to patient data must be logged.

**[Show the log method]**

Our AuditLogService captures:
- Who accessed the data
- What they accessed
- When they accessed it
- From where (IP address)
- What action they performed

```php
public function logPatientAccess(UserInterface $user, string $accessType, string $patientId): AuditLog
{
    $auditLog = new AuditLog();
    $auditLog->setUsername($user->getUserIdentifier());
    $auditLog->setActionType('PATIENT_' . $accessType);
    $auditLog->setEntityId($patientId);
    $auditLog->setTimestamp(new UTCDateTime());
    $auditLog->setIpAddress($request->getClientIp());
    
    // Save it
    $this->saveAuditLog($auditLog);
}
```

**[Show the Event Subscriber]**

We use a Symfony Event Subscriber to log automatically:

```php
public function onKernelController(ControllerEvent $event): void
{
    if (str_starts_with($route, 'patient_')) {
        $this->auditLogService->logPatientAccess($user, $accessType, $patientId);
    }
}
```

**[Emphasize the importance]**

This runs BEFORE the controller, so even unauthorized access attempts get logged.

**Important:** Audit logs are NOT encrypted. They don't contain PHI - just metadata about who accessed what."

---

## PART 8: DEMO TIME! (2 minutes)

### Visual: Terminal and Postman/browser

**[Switch to terminal/Postman]**

"Alright, let's see this in action!

**[Login as doctor]**

First, I'm logging in as a doctor:

```bash
curl -X POST http://localhost:8081/api/login \
  -H "Content-Type: application/json" \
  -d '{"_username":"doctor@example.com","_password":"doctor"}'
```

**[Show JWT token]**

Got our JWT token.

**[Create a patient]**

Now let's create a patient:

```bash
curl -X POST http://localhost:8081/api/patients \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "firstName": "John",
    "lastName": "Smith",
    "ssn": "123-45-6789",
    "diagnosis": ["Type 2 Diabetes"],
    "medications": ["Metformin 500mg"]
  }'
```

**[Show response]**

Patient created!

**[Search for the patient]**

Now let's search for them:

```bash
curl "http://localhost:8081/api/patients/search?lastName=Smith" \
  -H "Authorization: Bearer TOKEN"
```

**[Show results]**

There they are! Notice we can search by lastName even though it's encrypted.

**[Login as nurse]**

Now let's login as a nurse:

```bash
curl -X POST http://localhost:8081/api/login \
  -d '{"_username":"nurse@example.com","_password":"nurse"}'
```

**[Get the same patient as nurse]**

```bash
curl http://localhost:8081/api/patients/PATIENT_ID \
  -H "Authorization: Bearer NURSE_TOKEN"
```

**[Point out the differences]**

Look! The nurse can see:
- ✅ Name and contact info
- ✅ Diagnosis
- ✅ Medications
- ❌ But NOT the SSN

That's role-based access control in action!

**[Show audit logs]**

And finally, let's check the audit logs:

```bash
curl http://localhost:8081/api/audit-logs \
  -H "Authorization: Bearer DOCTOR_TOKEN"
```

**[Show the audit trail]**

Every single access is logged:
- Doctor created patient
- Doctor searched for Smith
- Nurse viewed patient record

HIPAA compliant!"

---

## PART 9: PERFORMANCE & BEST PRACTICES (1 minute)

### Visual: Performance comparison chart

**[Show performance benchmarks]**

"Let's talk performance. Yes, encryption has overhead:

- Deterministic encryption queries: 2-3x slower than unencrypted
  - Unencrypted: ~20ms
  - Encrypted: ~50ms
  - Still very acceptable

- Bulk operations: 2-3x slower
  - Still under a second for hundreds of records

**[Show optimization strategies]**

How we optimized:

1. **Selective Encryption** - Only encrypt PHI, not everything
2. **Smart Indexing** - Index searchable encrypted fields
3. **Projection** - Only retrieve fields you need
4. **Connection Pooling** - Reuse connections

**[Best practices slide]**

Key best practices:

✅ Start with data classification
✅ Encrypt from day one (don't retrofit)
✅ Test with realistic data volumes
✅ Never log PHI
✅ Use cloud KMS in production (AWS KMS, Azure Key Vault)

❌ Don't over-encrypt
❌ Don't ignore key rotation
❌ Don't trust client-side validation alone"

---

## CLOSING (1 minute)

### Visual: Summary slide

**[Back on camera]**

"And that's it! We just built a HIPAA-compliant medical records system with:

✅ Field-level encryption
✅ Searchable encrypted data
✅ Role-based access control
✅ Complete audit logging
✅ Zero-knowledge security

The beautiful thing about MongoDB Queryable Encryption is that it solves that impossible choice between security and functionality. You can have both.

**[Show repo link]**

All the code is on GitHub - link in the description. Clone it, play with it, build on it.

**[Production readiness checklist]**

Before going to production, remember:
1. Use AWS KMS or Azure Key Vault for key management
2. Enable MongoDB Atlas backups
3. Set up monitoring and alerting
4. Get a security audit
5. Document everything

**[Final thoughts]**

Healthcare data is personal. It's sensitive. It's about real people trusting their providers with their most private information. Building systems that respect that trust isn't just about compliance - it's about doing the right thing.

Thanks for watching! If you build something with this, let me know in the comments. And if you have questions, drop them below - I read every one.

Until next time, stay secure, friends! 🔒"

**[End screen with links]**
- GitHub repo
- MongoDB Queryable Encryption docs
- HIPAA compliance resources
- michaellynn.dev

---

## VIDEO PRODUCTION NOTES

### B-Roll Suggestions
1. MongoDB Compass showing encrypted data (Binary objects)
2. Architecture diagram animation
3. Code scrolling through key files
4. Terminal commands running
5. Postman/curl commands executing
6. Audit logs scrolling
7. Different role views side-by-side

### Key Moments to Emphasize
- 2:30 - "MongoDB NEVER sees your plaintext data"
- 5:00 - The encryption service configuration
- 7:00 - Role-based access control in toArray()
- 9:00 - Searching encrypted data explanation
- 11:00 - Comparing doctor vs nurse views

### Screen Layout
- Main: Code editor / Terminal / Browser
- Picture-in-picture: Michael on camera (bottom right)
- Pop-up overlays for key concepts

### Graphics Needed
1. Architecture diagram (animated)
2. Encryption types comparison chart
3. Data flow visualization
4. Performance benchmark charts
5. HIPAA compliance checklist
6. Best practices summary

### Captions/Annotations
Add on-screen text for:
- Key terms (Deterministic Encryption, Random Encryption, etc.)
- Important URLs
- Code snippets that are too small
- Performance numbers
- Security warnings

---

## THUMBNAIL IDEAS

**Option 1: Split Screen**
- Left: Encrypted data (gibberish)
- Right: Decrypted data (readable)
- Text: "Search Encrypted Data?!"

**Option 2: HIPAA Compliant**
- Large checkmark
- MongoDB + Symfony logos
- Text: "HIPAA Compliant in 15 Minutes"

**Option 3: Lock Icon**
- Large padlock with MongoDB logo
- Patient record in background
- Text: "Queryable Encryption Explained"

---

## YOUTUBE DESCRIPTION

```
🔒 Build a HIPAA-Compliant Medical Records System with MongoDB Queryable Encryption

Learn how to build a production-ready healthcare application that's secure AND functional! 

In this tutorial, we'll build a complete medical records API using:
✅ MongoDB Queryable Encryption - search encrypted data without decrypting it!
✅ Symfony 7.0 for our backend API
✅ Role-based access control for HIPAA compliance
✅ Comprehensive audit logging
✅ Docker for easy deployment

🎯 What You'll Learn:
• How MongoDB Queryable Encryption works
• Implementing field-level encryption in PHP
• Building role-based access control (Doctor, Nurse, Receptionist)
• HIPAA-compliant audit logging
• Searching encrypted data without decryption
• Real-world performance optimization

⏱️ Timestamps:
0:00 - Introduction & The Problem
1:00 - Architecture Overview
3:00 - Setting Up MongoDB Atlas
5:00 - The Encryption Service (The Heart)
8:00 - Patient Document with Encrypted Fields
10:00 - Searching Encrypted Data (The Magic!)
11:30 - Audit Logging for HIPAA
13:30 - Live Demo
14:30 - Performance & Best Practices
15:30 - Conclusion & Next Steps

📦 Resources:
• GitHub Repo: [link]
• MongoDB Queryable Encryption Docs: https://docs.mongodb.com/manual/core/queryable-encryption/
• Symfony Security Guide: https://symfony.com/doc/current/security.html
• HIPAA Compliance: https://www.hhs.gov/hipaa

🎓 Prerequisites:
• Basic PHP knowledge
• Understanding of MongoDB
• Docker installed
• MongoDB Atlas account (free tier works!)

💬 Have questions? Drop them in the comments!

🔔 Subscribe for more tutorials on secure application development!

#MongoDB #HIPAA #PHP #Symfony #Security #Healthcare #Encryption #Tutorial

---
Michael Lynn - Developer Advocate @ MongoDB
🌐 michaellynn.dev
🐦 @mlynn
```

---

## SOCIAL MEDIA SNIPPETS

**Twitter/X (Thread)**
```
🧵 Just published: Building HIPAA-Compliant Medical Records with MongoDB

1/ The problem: Healthcare apps need encryption, but encrypted data can't be searched. You're forced to choose security OR functionality.

2/ MongoDB Queryable Encryption changes this. You can search encrypted data WITHOUT decrypting it on the server. True zero-knowledge security.

3/ In my new tutorial, we build a complete medical records system with:
• Field-level encryption
• Role-based access
• Audit logging
• All HIPAA compliant

4/ The magic? Deterministic encryption for searchable fields, random encryption for maximum security. MongoDB handles the complexity.

5/ Watch the full tutorial: [link]
Code on GitHub: [link]

#MongoDB #HIPAA #PHP
```

**LinkedIn Post**
```
🏥 Just released: How to Build HIPAA-Compliant Healthcare Applications

For years, developers have faced an impossible choice: secure encryption OR searchable databases. You couldn't have both.

MongoDB Queryable Encryption solves this.

In my new tutorial, I walk through building a complete medical records system that:
✅ Encrypts patient data at the field level
✅ Enables searching WITHOUT decryption
✅ Implements role-based access (Doctor/Nurse/Receptionist)
✅ Provides comprehensive audit logging
✅ Meets HIPAA compliance requirements

The system uses:
• MongoDB 8.2 with Queryable Encryption
• Symfony 7.0 for the API
• Docker for deployment
• Real-world security patterns

Key insight: Even MongoDB database administrators can't see the plaintext patient data. True zero-knowledge security.

Full video tutorial: [link]
Complete source code: [link]

This is production-ready code you can actually deploy.

#HealthTech #Security #MongoDB #HIPAA #SoftwareDevelopment
```

**Reddit r/PHP**
```
Title: [Tutorial] Building a HIPAA-Compliant Medical Records API with MongoDB Queryable Encryption and Symfony 7

I just published a comprehensive tutorial on building secure healthcare applications.

**The Problem:**
Encryption typically means you can't search your data. For healthcare apps, this is a dealbreaker.

**The Solution:**
MongoDB's Queryable Encryption lets you search encrypted data without decrypting it server-side.

**What's Covered:**
• Complete Symfony 7.0 API implementation
• Field-level encryption with MongoDB
• Role-based access control (Doctors, Nurses, Receptionists see different data)
• HIPAA-compliant audit logging
• Docker deployment

**Why This Matters:**
• Zero-knowledge security - even DBAs can't see patient data
• Searchable encryption - full database functionality
• Production-ready - includes error handling, logging, security patterns

Full code on GitHub, 15-minute video walkthrough, detailed blog post with all the implementation details.

[Links]

Happy to answer questions!
```
