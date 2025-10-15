# RAG Chatbot Quick Start Guide

## What You Now Have

A **HIPAA-compliant AI chatbot** that can:

1. **Answer questions about your app** using RAG (Retrieval Augmented Generation)
   - "What is MongoDB Queryable Encryption?"
   - "How do Symfony Voters work?"
   - "Explain deterministic vs random encryption"
   
2. **Access patient data** with proper permissions
   - "Show me patients with diabetes"
   - "What's patient Smith's diagnosis?"
   - "Check drug interactions for these medications"

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     User Question                            │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
        ┌─────────────────────────────┐
        │   Query Classification      │
        │   (Knowledge vs Data)       │
        └──────────┬─────────┬────────┘
                   │         │
       Knowledge   │         │   Patient Data
       Question    │         │   Question
                   │         │
                   ▼         ▼
        ┌──────────────┐  ┌──────────────────┐
        │ RAG Pipeline │  │ Function Calling │
        │              │  │   + Voters       │
        └──────┬───────┘  └────────┬─────────┘
               │                   │
               ▼                   ▼
   ┌─────────────────────┐  ┌──────────────────┐
   │ Vector Search       │  │ MongoDB          │
   │ (Knowledge Base)    │  │ (Patient Data)   │
   └─────────┬───────────┘  └────────┬─────────┘
             │                       │
             ▼                       ▼
   ┌─────────────────────┐  ┌──────────────────┐
   │ GPT-4 with Context  │  │ GPT-4 Functions  │
   └─────────┬───────────┘  └────────┬─────────┘
             │                       │
             └───────────┬───────────┘
                         ▼
                   ┌───────────┐
                   │  Answer   │
                   └───────────┘
```

## Setup Steps

### 1. Set OpenAI API Key

```bash
# Add to .env file
echo "OPENAI_API_KEY=sk-your-key-here" >> .env
```

### 2. Index Your Documentation

This creates embeddings for the blog article, docs, and code:

```bash
# Index everything
docker-compose exec php bin/console app:index-knowledge-base --force

# Or index specific categories
docker-compose exec php bin/console app:index-knowledge-base --category=blog
docker-compose exec php bin/console app:index-knowledge-base --category=docs
docker-compose exec php bin/console app:index-knowledge-base --category=code
```

**Expected output:**
```
Indexing Knowledge Base for RAG Chatbot
========================================

Indexing Blog Article
---------------------
 50/50 [============================] 100%
✓ Indexed 50 chunks from blog article

Indexing Documentation Files
-----------------------------
Processing docs/AUTHENTICATION_FLOW.md...
Processing docs/SECURITY.md...
...
✓ Indexed 30 chunks from documentation

Indexing Code Examples
----------------------
✓ Indexed MongoDB Encryption Service
✓ Indexed Patient Voter - RBAC Implementation
...

[OK] Successfully indexed 90 documents!
```

### 3. Create Vector Search Index in MongoDB Atlas

**Critical Step!** You must create a vector search index in Atlas:

1. Go to MongoDB Atlas → Your Cluster
2. Click **"Search"** tab
3. Click **"Create Search Index"**
4. Select **"JSON Editor"**
5. Use this configuration:

```json
{
  "name": "vector_index",
  "type": "vectorSearch",
  "definition": {
    "fields": [
      {
        "type": "vector",
        "path": "embedding",
        "numDimensions": 1536,
        "similarity": "cosine"
      },
      {
        "type": "filter",
        "path": "category"
      },
      {
        "type": "filter",
        "path": "title"
      }
    ]
  }
}
```

6. Database: `securehealth`
7. Collection: `knowledge_base`
8. Click **"Create Search Index"**
9. Wait for index to build (2-5 minutes)

### 4. Test the Chatbot

**Knowledge Questions (uses RAG):**

```bash
curl -X POST http://localhost:8081/api/chatbot/query \
  -H "Content-Type: application/json" \
  --cookie "PHPSESSID=your-session-id" \
  -d '{"query": "What is MongoDB Queryable Encryption?"}'
```

**Response:**
```json
{
  "response": "MongoDB Queryable Encryption is a feature that allows you to search encrypted data without decrypting it on the server...",
  "type": "knowledge",
  "sources": [
    {
      "title": "Understanding MongoDB Queryable Encryption",
      "category": "blog",
      "source": "building-hipaa-compliant-medical-records-improved.md",
      "relevance": 0.921
    }
  ]
}
```

**Patient Data Questions (uses Function Calling):**

```bash
curl -X POST http://localhost:8081/api/chatbot/query \
  -H "Content-Type: application/json" \
  --cookie "PHPSESSID=your-session-id" \
  -d '{"query": "Show me patients named Smith"}'
```

## Example Queries

### Knowledge Queries (RAG)

These pull from indexed documentation:

1. **"What is deterministic encryption?"**
   - Retrieves blog sections about encryption types
   - Explains use cases and security tradeoffs

2. **"How do I set up encryption keys?"**
   - Pulls from setup documentation
   - Shows exact commands and configuration

3. **"Explain how Symfony Voters work"**
   - Retrieves voter documentation and code examples
   - Shows actual PatientVoter implementation

4. **"What are the HIPAA requirements?"**
   - Pulls from compliance documentation
   - Lists technical safeguards

5. **"How do I implement field-level encryption?"**
   - Retrieves code examples
   - Shows MongoDBEncryptionService usage

### Data Queries (Function Calling)

These access actual patient data (with permissions):

1. **"Show me patients with diabetes"**
   - Calls search_patients_by_condition()
   - Respects voter permissions

2. **"What's patient 123's diagnosis?"**
   - Calls get_patient_diagnosis()
   - Checks PATIENT_VIEW_DIAGNOSIS permission

3. **"Check drug interactions for metformin and lisinopril"**
   - Calls check_drug_interactions()
   - Doctor-only function

## How It Works

### Knowledge Question Flow

1. **Query:** "What is MongoDB Queryable Encryption?"
2. **Classification:** Detected as knowledge question (has "what is")
3. **Embedding:** Convert query to 1536-dim vector
4. **Vector Search:** Find top 5 similar docs in knowledge_base
5. **Retrieved:**
   - "Understanding MongoDB Queryable Encryption" (0.92 relevance)
   - "The Core Concept" (0.88 relevance)
   - "Encryption Types Explained" (0.85 relevance)
6. **Context Building:** Combine retrieved docs into context
7. **LLM Query:** Send to GPT-4 with context + question
8. **Response:** Accurate answer based on your actual documentation

### Patient Data Question Flow

1. **Query:** "Show me diabetic patients"
2. **Classification:** Detected as data question
3. **Function Calling:** GPT-4 decides to call search_patients_by_condition()
4. **Permission Check:** Voter checks if user can VIEW_DIAGNOSIS
5. **Query Execution:** Search encrypted patient data
6. **Decryption:** Decrypt results for authorized user
7. **Response:** List of patients with audit log

## Performance & Costs

### Indexing (One-Time)
- ~90 documents × $0.00002 = **$0.0018**
- Time: ~5 minutes

### Per Query
- **Knowledge queries:**
  - Embedding: $0.00002
  - LLM: $0.01 (1000 tokens)
  - **Total: ~$0.01**

- **Data queries:**
  - LLM + function call: $0.01
  - **Total: ~$0.01**

### Monthly (1000 queries)
- **~$10-15/month**

## Monitoring

Check what's being indexed:

```bash
# Count documents by category
docker-compose exec php bin/console mongodb:query \
  'db.knowledge_base.aggregate([
    {$group: {_id: "$category", count: {$sum: 1}}}
  ])'
```

View sample documents:

```bash
# See blog chunks
docker-compose exec php bin/console mongodb:query \
  'db.knowledge_base.find({category: "blog"}).limit(2).pretty()'
```

## Troubleshooting

### "No results from vector search"

**Problem:** Vector search index not created or not ready

**Solution:**
1. Check Atlas UI → Search tab
2. Verify index status is "Active"
3. Wait 2-5 minutes after creation

### "OpenAI API error"

**Problem:** Invalid or missing API key

**Solution:**
```bash
# Check if key is set
docker-compose exec php printenv OPENAI_API_KEY

# Update .env
nano .env
# Add: OPENAI_API_KEY=sk-...

# Restart
docker-compose restart php
```

### "Embedding dimensions mismatch"

**Problem:** Using wrong embedding model

**Solution:**
- `text-embedding-3-small` = 1536 dimensions ✓
- `text-embedding-ada-002` = 1536 dimensions ✓
- Make sure index uses 1536 dimensions

### "Permission denied for patient query"

**Expected Behavior:** Chatbot respects Symfony Voters

**Solution:**
- Login as Doctor to access all data
- Nurses can't view SSN
- Receptionists limited to insurance

## Next Steps

1. **Add More Documentation:**
   ```bash
   # After adding new docs, re-index
   docker-compose exec php bin/console app:index-knowledge-base --force
   ```

2. **Customize Query Classification:**
   Edit `RAGChatbotService::classifyQuery()` to improve detection

3. **Tune Retrieval:**
   - Adjust number of docs retrieved (currently 5)
   - Experiment with chunk sizes (currently 1000 tokens)
   - Add metadata filtering

4. **Monitor Usage:**
   - Check audit logs for chatbot queries
   - Track which questions work well vs poorly
   - Identify gaps in documentation

5. **Improve Responses:**
   - Add more code examples
   - Index troubleshooting guides
   - Include FAQs

## Security Notes

✅ **HIPAA Compliant:**
- All chatbot queries are audit logged
- Patient data queries respect Voter permissions
- No PHI stored in conversation history
- OpenAI API should have BAA (Business Associate Agreement)

✅ **Permission-Aware:**
- Knowledge questions: Available to all authenticated users
- Patient data: Filtered by role (Doctor/Nurse/Receptionist)
- Audit trail: Every query logged with user ID

✅ **Encrypted Communication:**
- All API calls use HTTPS
- Session cookies are HttpOnly, Secure, SameSite
- Patient data encrypted at rest in MongoDB

## Files Created

1. `src/Document/KnowledgeBase.php` - Entity for storing docs + embeddings
2. `src/Command/IndexKnowledgeBaseCommand.php` - Indexing command
3. `docs/CHATBOT_RAG_IMPLEMENTATION.md` - Full technical guide
4. `docs/CHATBOT_QUICK_START.md` - This file

## Summary

You now have a production-ready RAG chatbot that:

✅ Knows everything in your blog article  
✅ Knows how your application works  
✅ Can explain MongoDB Queryable Encryption  
✅ Can access patient data (with permissions)  
✅ Maintains HIPAA compliance  
✅ Provides source citations  

Test it with real questions and watch it intelligently answer using your actual documentation!

