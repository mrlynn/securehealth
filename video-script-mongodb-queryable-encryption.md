# MongoDB Queryable Encryption Demo Video Script
## "Building HIPAA-Compliant Systems with MongoDB 8.2 Queryable Encryption"

### Video Overview
**Duration**: 15-20 minutes  
**Target Audience**: Developers building healthcare applications  
**Goal**: Demonstrate MongoDB Queryable Encryption in a real-world HIPAA-compliant system

---

## Opening Hook (0:00 - 1:00)

**[Screen: Live demo of SecureHealth application]**

**Narrator**: "Stop reading tutorials. Nobody reads tutorials anymore."

**[Screen: Show securehealth.dev loading]**

**Narrator**: "Seriously, just go check out the live demo at securehealth.dev. But if you're still here, let me show you something that actually matters."

**[Screen: Split screen - encrypted database vs readable data]**

**Narrator**: "Here's the problem every healthcare developer faces: You need to encrypt everything because HIPAA will destroy you if you don't. But you also need to search through that data because doctors aren't going to memorize patient IDs."

**[Screen: Show traditional encryption pain points]**

**Narrator**: "Traditional approaches force you to pick your poison: encrypt everything and lose searchability, or keep it unencrypted and pray the auditors don't notice."

**[Screen: MongoDB Queryable Encryption logo]**

**Narrator**: "MongoDB 8.2's Queryable Encryption changes everything. You get encryption AND searchability. It's like magic, but with more documentation."

---

## The Problem Demo (1:00 - 3:00)

**[Screen: Show raw MongoDB data]**

**Narrator**: "Let me show you what we're dealing with. Here's what patient data looks like in a traditional encrypted database."

**[Screen: Show encrypted gibberish]**

**Narrator**: "This is encrypted data. Completely useless for searching. Want to find all patients named 'Smith'? Good luck."

**[Screen: Show unencrypted data]**

**Narrator**: "And here's what happens when you keep it unencrypted for searchability. Fast searches, but you might as well just hand your database to the first hacker who asks nicely."

**[Screen: Show HIPAA violation headlines]**

**Narrator**: "This is why healthcare data breaches cost companies millions. You can't have both security and functionality... or can you?"

---

## The Solution: MongoDB Queryable Encryption (3:00 - 6:00)

**[Screen: Show SecureHealth application login]**

**Narrator**: "Let me show you SecureHealth - a production-ready HIPAA-compliant medical records system that actually works."

**[Screen: Login as doctor@securehealth.com]**

**Narrator**: "I'm logging in as a doctor. Notice how the system knows exactly what I can and can't see based on my role."

**[Screen: Navigate to patient list]**

**Narrator**: "Here's our patient list. Looks normal, right? But here's the thing - every single piece of sensitive data is encrypted at the database level."

**[Screen: Show patient detail page]**

**Narrator**: "Let me show you patient details. I can see the patient's name, diagnosis, medications - everything a doctor needs. But if I switch to a nurse role..."

**[Screen: Switch to nurse role]**

**Narrator**: "Now I can't see the SSN. The system automatically filters what I can access based on my permissions."

**[Screen: Switch to admin role]**

**Narrator**: "And as an admin, I can see basic patient info but not medical data. This is field-level access control in action."

---

## The Technical Magic (6:00 - 10:00)

**[Screen: Show MongoDB Atlas dashboard]**

**Narrator**: "Here's where the magic happens. Let me show you the actual database."

**[Screen: Show encrypted collection in MongoDB Atlas]**

**Narrator**: "This is what the data looks like in MongoDB Atlas. Every sensitive field is encrypted. But watch this..."

**[Screen: Perform encrypted search]**

**Narrator**: "I can search for patients by name, by diagnosis, even by date ranges. The search works on encrypted data without ever decrypting it on the server."

**[Screen: Show search results]**

**Narrator**: "The results come back decrypted only for authorized users. The database never sees the actual patient data - it only sees encrypted gibberish."

**[Screen: Show encryption schema code]**

**Narrator**: "Here's how we configure this. We define an encryptedFieldsMap that tells MongoDB exactly how to encrypt each field."

```javascript
const encryptedFieldsMap = {
  'securehealth.patients': {
    fields: [
      {
        path: 'firstName',
        bsonType: 'string',
        keyId: firstNameKeyId,
        queries: [{ queryType: 'equality' }]
      },
      {
        path: 'ssn',
        bsonType: 'string',
        keyId: ssnKeyId
        // No queries = random encryption (no search)
      }
    ]
  }
};
```

**Narrator**: "Deterministic encryption for searchable fields, random encryption for highly sensitive data. The driver handles all the encryption and decryption automatically."

---

## The Access Control Layer (10:00 - 13:00)

**[Screen: Show Symfony Security Voters code]**

**Narrator**: "But encryption is only half the story. You also need to control WHO can see WHAT. That's where Symfony's voter system comes in."

**[Screen: Show PatientVoter code]**

**Narrator**: "This isn't just 'are you a doctor?' - it's 'are you THIS doctor, looking at THIS patient's SSN, at THIS time?'"

```php
class PatientVoter extends Voter
{
    public const VIEW_SSN = 'PATIENT_VIEW_SSN';
    public const VIEW_DIAGNOSIS = 'PATIENT_VIEW_DIAGNOSIS';
    
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        $roles = $user->getRoles();
        
        switch ($attribute) {
            case self::VIEW_SSN:
                return in_array('ROLE_DOCTOR', $roles);
            case self::VIEW_DIAGNOSIS:
                return in_array('ROLE_DOCTOR', $roles) || 
                       in_array('ROLE_NURSE', $roles);
        }
    }
}
```

**[Screen: Show API response filtering]**

**Narrator**: "The controller builds responses based on what the user is actually allowed to see. Field-level access control means doctors see more data than nurses, and nurses see more than admins."

**[Screen: Show audit logs]**

**Narrator**: "And everything gets logged. Every access attempt, successful or failed, gets recorded for HIPAA compliance."

---

## The Performance Reality (13:00 - 15:00)

**[Screen: Show performance metrics]**

**Narrator**: "Now, let's talk about the elephant in the room - performance. Encryption isn't free."

**[Screen: Show storage overhead]**

**Narrator**: "Encrypted data takes up 2-4x more space than unencrypted data. Deterministic encryption: about 2x overhead. Random encryption: 3-4x overhead."

**[Screen: Show query performance]**

**Narrator**: "And queries are slower. Encrypted indexes take more time and space. But here's the thing - it's still fast enough for real-world use."

**[Screen: Show optimization strategies]**

**Narrator**: "You need to be strategic about indexing. Only index fields you actually query on. Use compound indexes for common search patterns."

---

## The Compliance Story (15:00 - 17:00)

**[Screen: Show HIPAA compliance checklist]**

**Narrator**: "Let's talk HIPAA compliance. This architecture handles most of the heavy lifting."

**[Screen: Show audit log interface]**

**Narrator**: "Access control? Check. Audit logging? Check. Data integrity? Check. Encryption at rest, in transit, and in use? Check."

**[Screen: Show compliance report]**

**Narrator**: "The system generates compliance reports automatically. You can show auditors exactly who accessed what, when, and whether they were supposed to."

**[Screen: Show data retention policies]**

**Narrator**: "Data retention? Handled. Key management? Planned for. Disaster recovery? Covered."

---

## The Developer Experience (17:00 - 19:00)

**[Screen: Show development workflow]**

**Narrator**: "Here's what's beautiful about this approach - it's actually developer-friendly."

**[Screen: Show normal MongoDB queries]**

**Narrator**: "You write normal MongoDB queries. The driver figures out what needs to be encrypted. No manual encryption/decryption in your application code."

**[Screen: Show Doctrine ODM integration]**

**Narrator**: "It works seamlessly with Doctrine MongoDB ODM. Your documents look normal, but sensitive fields get encrypted automatically."

**[Screen: Show testing approach]**

**Narrator**: "Testing is straightforward. Test that encryption works, test that access control works, test that they work together."

---

## The Future (19:00 - 20:00)

**[Screen: Show MongoDB 8.2 features]**

**Narrator**: "MongoDB 8.2 introduces even more capabilities. Substring searches on encrypted data. Range queries. The future is bright."

**[Screen: Show live demo links]**

**Narrator**: "Want to see this in action? Go to securehealth.dev. Want to see the code? Check out the GitHub repo. Want the full technical details? Read the blog article."

**[Screen: Show call-to-action]**

**Narrator**: "Stop choosing between security and functionality. With MongoDB Queryable Encryption, you can have both. Now go build something that actually protects patient data."

---

## Technical Deep Dive Segments (Optional - 5-10 minutes each)

### Segment A: Encryption Schema Deep Dive
- Show different encryption types (deterministic, random, range)
- Demonstrate key management
- Explain query limitations and capabilities

### Segment B: Access Control Implementation
- Show voter system in detail
- Demonstrate role hierarchy
- Show field-level permission checks

### Segment C: Performance Optimization
- Show indexing strategies
- Demonstrate query optimization
- Explain monitoring and alerting

### Segment D: Deployment and Operations
- Show production deployment
- Explain key management in production
- Show monitoring and maintenance

---

## Visual Elements Needed

### Screenshots/Recordings
1. SecureHealth application login and navigation
2. Patient list and detail pages with different roles
3. MongoDB Atlas dashboard showing encrypted data
4. Code editor with encryption schema and voter code
5. Performance metrics and monitoring dashboards
6. Audit logs and compliance reports

### Graphics/Animations
1. Encryption/decryption flow diagram
2. Access control decision tree
3. Performance overhead visualization
4. HIPAA compliance checklist with checkmarks

### Code Examples
1. EncryptedFieldsMap configuration
2. Symfony Security Voter implementation
3. Controller with field-level access control
4. Performance monitoring code
5. Audit logging implementation

---

## Key Messages

1. **MongoDB Queryable Encryption solves the security vs. functionality dilemma**
2. **Field-level encryption with searchability is now possible**
3. **Symfony Voters provide fine-grained access control**
4. **HIPAA compliance is achievable with proper architecture**
5. **Performance overhead is manageable with proper planning**
6. **Developer experience is actually good with the right tools**

---

## Call-to-Action

- Visit securehealth.dev to see the live demo
- Check out the GitHub repository for implementation details
- Read the full blog article for technical deep dive
- Try MongoDB Queryable Encryption in your own projects
- Join the MongoDB community for support and best practices

---

## Notes for Production

- Ensure all demo data is properly anonymized
- Use production-like environment for demonstrations
- Have backup plans for live demo failures
- Prepare technical Q&A for developer questions
- Include links to documentation and resources
- Consider creating shorter "highlight reels" for social media
