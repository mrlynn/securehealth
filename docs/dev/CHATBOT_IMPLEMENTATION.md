# 🤖 SecureHealth RAG Chatbot Implementation

## Overview

The SecureHealth RAG (Retrieval Augmented Generation) Chatbot is a HIPAA-compliant AI assistant that can answer questions about the application, provide medical knowledge, and access patient data based on user permissions.

## Features

### ✅ Knowledge Queries (RAG)
- Answers questions about MongoDB Queryable Encryption
- Explains Symfony Voters and security implementation
- Provides guidance on HIPAA compliance
- Explains application architecture and features
- Uses vector search to find relevant documentation

### ✅ Patient Data Queries (Function Calling)
- Search patients by name (all healthcare staff)
- Search patients by medical condition (doctors only)
- View patient diagnosis (doctors only)
- Check drug interactions (doctors only)
- Respects role-based access control

### ✅ Security & Compliance
- All interactions are audit logged
- Respects Symfony Voter permissions
- HIPAA-compliant data handling
- No PHI stored in conversation history
- Encrypted communication

## Architecture

```
User Query
    ↓
Query Classification (Knowledge vs Data)
    ↓
┌─────────────────┬─────────────────┐
│ Knowledge Query │   Data Query    │
│ (RAG Pipeline)  │ (Function Call) │
└─────────┬───────┴─────────┬───────┘
          │                 │
          ▼                 ▼
Vector Search + LLM    Patient Data + Voters
          │                 │
          └─────────┬───────┘
                    ▼
              Formatted Response
```

## Files Created

### Backend Services
- `src/Service/RAGChatbotService.php` - Main chatbot service with RAG and function calling
- `src/Controller/Api/ChatbotController.php` - API endpoints for chatbot interactions
- `config/services.yaml` - Service configuration updates

### Frontend Components
- `public/assets/js/chatbot.js` - Floating chatbot UI component
- `public/assets/css/chatbot.css` - Chatbot styling and responsive design

### Integration
- Updated `public/index.html` - Added chatbot to main page
- Updated `public/dashboard.html` - Added chatbot to dashboard
- Updated `templates/base.html.twig` - Added chatbot to Symfony pages

### Testing & Documentation
- `test-chatbot.sh` - Test script for chatbot functionality
- `CHATBOT_IMPLEMENTATION.md` - This documentation file

## API Endpoints

### `GET /api/chatbot/status`
Returns chatbot status and user capabilities.

**Response:**
```json
{
  "success": true,
  "status": "active",
  "user_role": "ROLE_DOCTOR",
  "capabilities": {
    "knowledge_queries": true,
    "patient_search": true,
    "condition_search": true,
    "diagnosis_view": true,
    "drug_interactions": true
  }
}
```

### `GET /api/chatbot/examples`
Returns example queries based on user role.

**Response:**
```json
{
  "success": true,
  "examples": {
    "knowledge": [
      "What is MongoDB Queryable Encryption?",
      "How do Symfony Voters work?"
    ],
    "patient_data": [
      "Show me patients named Smith"
    ]
  }
}
```

### `POST /api/chatbot/query`
Processes a chatbot query.

**Request:**
```json
{
  "query": "What is MongoDB Queryable Encryption?"
}
```

**Response:**
```json
{
  "success": true,
  "response": "MongoDB Queryable Encryption is...",
  "type": "knowledge",
  "sources": [
    {
      "title": "Understanding MongoDB Queryable Encryption",
      "category": "blog",
      "source": "building-hipaa-compliant-medical-records-improved.md",
      "relevance": 0.921
    }
  ],
  "timestamp": "2024-01-15 10:30:00"
}
```

## User Interface

### Floating Chatbot Button
- Located in bottom-right corner of all pages
- Animated robot icon with gradient background
- Notification badge for new interactions
- Smooth hover and click animations

### Chatbot Window
- **Header**: Title, clear button, examples button, close button
- **Messages Area**: Conversation history with user and assistant messages
- **Examples Panel**: Role-based example queries
- **Input Area**: Textarea with character count and send button
- **Footer**: Status indicator and HIPAA compliance badge

### Message Types
- **User Messages**: Blue background, right-aligned
- **Assistant Messages**: White background, left-aligned
- **Warning Messages**: Yellow background for drug interactions
- **Error Messages**: Red background for errors
- **Success Messages**: Green background for successful operations

## Role-Based Access Control

### ROLE_ADMIN
- Full access to all features
- Can view all patient data
- Access to all medical knowledge

### ROLE_DOCTOR
- Knowledge queries ✅
- Patient search by name ✅
- Patient search by condition ✅
- View patient diagnosis ✅
- Check drug interactions ✅

### ROLE_NURSE
- Knowledge queries ✅
- Patient search by name ✅
- Limited patient data access

### ROLE_RECEPTIONIST
- Knowledge queries ✅
- Basic patient search only

## Testing

### Automated Testing
Run the test script to verify chatbot functionality:

```bash
./test-chatbot.sh
```

### Manual Testing
1. Open the application in your browser
2. Look for the floating chatbot button (🤖) in the bottom-right corner
3. Click it to open the chatbot interface
4. Try various queries:

**Knowledge Queries:**
- "What is MongoDB Queryable Encryption?"
- "How do Symfony Voters work?"
- "Explain the difference between deterministic and random encryption"
- "How do I set up encryption keys?"

**Data Queries (based on role):**
- "Show me patients named Smith"
- "Find patients with diabetes"
- "What is patient ID 123's diagnosis?"
- "Check drug interactions for metformin and lisinopril"

## Configuration

### Environment Variables
Ensure these are set in your `.env` file:

```bash
OPENAI_API_KEY=sk-your-openai-api-key-here
```

### MongoDB Atlas Vector Search
The chatbot requires a vector search index in MongoDB Atlas:

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

## Performance & Costs

### Indexing (One-Time)
- ~90 documents × $0.00002 = **$0.0018**
- Time: ~5 minutes

### Per Query
- **Knowledge queries:** ~$0.01
- **Data queries:** ~$0.01

### Monthly (1000 queries)
- **~$10-15/month**

## Security Features

### HIPAA Compliance
- ✅ All chatbot queries are audit logged
- ✅ Patient data queries respect Voter permissions
- ✅ No PHI stored in conversation history
- ✅ Encrypted communication (HTTPS)
- ✅ Session-based authentication

### Permission-Aware
- ✅ Knowledge questions: Available to all authenticated users
- ✅ Patient data: Filtered by role (Doctor/Nurse/Receptionist)
- ✅ Audit trail: Every query logged with user ID

### Encrypted Communication
- ✅ All API calls use HTTPS
- ✅ Session cookies are HttpOnly, Secure, SameSite
- ✅ Patient data encrypted at rest in MongoDB

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

### "Permission denied for patient query"
**Expected Behavior:** Chatbot respects Symfony Voters

**Solution:**
- Login as Doctor to access all data
- Nurses can't view SSN
- Receptionists limited to insurance

## Future Enhancements

### Planned Features
1. **Conversation Memory**: Persistent conversation history across sessions
2. **File Upload Support**: Allow users to upload documents for analysis
3. **Voice Interface**: Speech-to-text and text-to-speech capabilities
4. **Multi-language Support**: Support for multiple languages
5. **Advanced Analytics**: Usage analytics and performance metrics

### Integration Opportunities
1. **Medical Databases**: Integration with medical knowledge databases
2. **EHR Systems**: Integration with external EHR systems
3. **Lab Results**: Integration with lab result systems
4. **Prescription Systems**: Integration with pharmacy systems

## Support

For issues or questions about the chatbot implementation:

1. Check the audit logs for error details
2. Verify OpenAI API key configuration
3. Ensure MongoDB Atlas vector search index is active
4. Check user permissions and role assignments
5. Review browser console for JavaScript errors

## Summary

The SecureHealth RAG Chatbot provides:

✅ **Intelligent Knowledge Base**: Answers questions about MongoDB, HIPAA, and application features
✅ **Patient Data Access**: Secure, role-based access to patient information
✅ **HIPAA Compliance**: Full audit logging and permission enforcement
✅ **Modern UI**: Beautiful, responsive floating chatbot interface
✅ **Cost Effective**: Efficient use of OpenAI API with vector search
✅ **Scalable Architecture**: Built for production use with proper error handling

The chatbot is now ready for production use and will help healthcare professionals access information and patient data more efficiently while maintaining full HIPAA compliance.
