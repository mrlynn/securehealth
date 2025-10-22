# Medical Knowledge Base with MongoDB Vector Search

This document describes the implementation of a HIPAA-compliant medical knowledge base using MongoDB Vector Search for AI-powered clinical decision support.

## Overview

The Medical Knowledge Base integrates MongoDB Vector Search to provide semantic search capabilities across medical literature, clinical guidelines, treatment protocols, and drug interaction databases. This system enables healthcare providers to quickly find relevant medical information using natural language queries.

## Architecture

### Core Components

1. **MedicalKnowledge Document Model** (`src/Document/MedicalKnowledge.php`)
   - Stores medical knowledge entries with vector embeddings
   - Supports encrypted storage of sensitive medical content
   - Includes metadata for categorization and filtering

2. **EmbeddingService** (`src/Service/EmbeddingService.php`)
   - Generates vector embeddings using OpenAI API
   - Supports batch processing and similarity calculations
   - Includes fallback mock embeddings for development

3. **VectorSearchService** (`src/Service/VectorSearchService.php`)
   - Performs semantic search across medical knowledge
   - Provides specialized search functions for different use cases
   - Implements hybrid search combining vector similarity with metadata filtering

4. **MedicalKnowledgeService** (`src/Service/MedicalKnowledgeService.php`)
   - Manages knowledge base operations
   - Handles data ingestion and updates
   - Provides search functionality with role-based access control

5. **MedicalKnowledgeRepository** (`src/Repository/MedicalKnowledgeRepository.php`)
   - MongoDB ODM repository with vector search capabilities
   - Implements encrypted storage and retrieval
   - Supports complex aggregation pipelines for hybrid search

6. **MedicalKnowledgeController** (`src/Controller/Api/MedicalKnowledgeController.php`)
   - RESTful API endpoints for knowledge base access
   - Implements role-based security
   - Provides specialized endpoints for different search types

## Features

### 1. Semantic Search
- Natural language queries across medical content
- Vector-based similarity matching
- Configurable similarity thresholds
- Filtering by specialty, confidence level, and evidence level

### 2. Clinical Decision Support
- Patient-specific recommendations based on conditions, medications, and symptoms
- Integration with existing patient data
- Evidence-based treatment suggestions

### 3. Drug Interaction Analysis
- Comprehensive drug interaction checking
- Consideration of patient conditions and allergies
- Safety alerts and contraindication warnings

### 4. Treatment Guidelines
- Specialty-specific treatment protocols
- Severity-based recommendations
- Age-group considerations

### 5. Diagnostic Criteria
- Symptom-based diagnostic assistance
- Differential diagnosis support
- Test result interpretation

## API Endpoints

### Search Endpoints

#### Semantic Search
```http
POST /api/medical-knowledge/search
Content-Type: application/json

{
    "query": "hypertension treatment guidelines",
    "filters": {
        "specialty": "cardiology",
        "minConfidenceLevel": 8,
        "minEvidenceLevel": 4
    },
    "limit": 10,
    "threshold": 0.7
}
```

#### Clinical Decision Support
```http
POST /api/medical-knowledge/clinical-decision-support
Content-Type: application/json

{
    "patientData": {
        "conditions": ["hypertension", "diabetes"],
        "medications": ["lisinopril", "metformin"],
        "symptoms": ["chest pain"]
    },
    "specialty": "cardiology"
}
```

#### Drug Interactions
```http
POST /api/medical-knowledge/drug-interactions
Content-Type: application/json

{
    "medications": ["warfarin", "aspirin"],
    "conditions": ["atrial fibrillation"],
    "allergies": ["penicillin"]
}
```

#### Treatment Guidelines
```http
POST /api/medical-knowledge/treatment-guidelines
Content-Type: application/json

{
    "condition": "diabetes type 2",
    "specialty": "endocrinology",
    "severity": "moderate",
    "patientAge": "adult"
}
```

### Management Endpoints

#### Create Knowledge Entry
```http
POST /api/medical-knowledge
Content-Type: application/json

{
    "title": "New Treatment Protocol",
    "content": "Detailed treatment information...",
    "source": "Clinical Guidelines",
    "tags": ["treatment", "protocol"],
    "specialties": ["cardiology"],
    "confidenceLevel": 8,
    "evidenceLevel": 4
}
```

#### Import Knowledge
```http
POST /api/medical-knowledge/import
Content-Type: application/json

{
    "source": "External Database",
    "data": [
        {
            "title": "Entry 1",
            "content": "Content 1...",
            "source": "External Database"
        }
    ]
}
```

#### Knowledge Base Statistics
```http
GET /api/medical-knowledge/stats
```

## Security and Compliance

### HIPAA Compliance
- All sensitive medical content is encrypted using MongoDB Queryable Encryption
- Role-based access control ensures appropriate data access
- Comprehensive audit logging for all knowledge base interactions
- Data minimization principles applied to search results

### Access Control
- **Doctors**: Full access to all knowledge base features
- **Nurses**: Limited access to relevant medical information
- **Admins**: Full administrative access including import capabilities
- **Receptionists/Patients**: No access to knowledge base

### Audit Logging
All knowledge base interactions are logged with:
- User identification
- Action performed
- Search parameters (anonymized)
- Timestamp
- Result counts

## Setup and Configuration

### 1. Environment Variables
Add the following to your `.env` file:

```bash
# OpenAI API Configuration (optional - will use mock embeddings if not provided)
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_API_URL=https://api.openai.com/v1
```

### 2. MongoDB Atlas Vector Search Index
Create a vector search index in MongoDB Atlas:

```javascript
{
  "fields": [
    {
      "type": "vector",
      "path": "embedding",
      "numDimensions": 1536,
      "similarity": "cosine"
    },
    {
      "type": "filter",
      "path": "isActive"
    },
    {
      "type": "filter", 
      "path": "specialties"
    },
    {
      "type": "filter",
      "path": "confidenceLevel"
    },
    {
      "type": "filter",
      "path": "evidenceLevel"
    }
  ]
}
```

### 3. Seed Sample Data
Run the seeding command to populate the knowledge base:

```bash
php bin/console app:seed-medical-knowledge --count=50
```

## Frontend Interface

The medical knowledge base includes a comprehensive web interface (`public/medical-knowledge-search.html`) with:

### Features
- **Semantic Search**: Natural language queries with advanced filtering
- **Clinical Decision Support**: Patient-specific recommendations
- **Drug Interaction Checking**: Comprehensive safety analysis
- **Treatment Guidelines**: Specialty-specific protocols
- **Knowledge Base Statistics**: Usage and content metrics

### User Interface
- Tabbed interface for different search types
- Advanced filtering options
- Real-time search results with confidence scores
- Responsive design for mobile and desktop
- Integration with existing authentication system

## Use Cases

### 1. Clinical Decision Support
Healthcare providers can input patient symptoms, conditions, and medications to receive evidence-based treatment recommendations.

### 2. Drug Safety
Pharmacists and physicians can check for potential drug interactions before prescribing medications.

### 3. Treatment Protocols
Clinicians can quickly access the latest treatment guidelines for specific conditions and specialties.

### 4. Diagnostic Assistance
Medical professionals can use symptom-based searches to explore differential diagnoses and diagnostic criteria.

### 5. Continuing Education
Healthcare providers can use the knowledge base for ongoing education and staying current with medical advances.

## Performance Considerations

### Vector Search Optimization
- MongoDB Atlas vector search indexes for fast similarity queries
- Configurable similarity thresholds to balance precision and recall
- Batch processing for bulk operations

### Caching Strategy
- Search results caching for frequently accessed queries
- Embedding caching to reduce API calls
- Metadata caching for filter options

### Rate Limiting
- API rate limiting to prevent abuse
- OpenAI API usage monitoring and limits
- Graceful degradation when external services are unavailable

## Monitoring and Analytics

### Usage Metrics
- Search query frequency and patterns
- Most accessed knowledge entries
- User engagement statistics
- Performance metrics

### Quality Metrics
- Search result relevance scores
- User feedback on search results
- Knowledge base coverage analysis
- Update frequency tracking

## Future Enhancements

### Planned Features
1. **Multi-language Support**: Vector embeddings for multiple languages
2. **Image Analysis**: Integration with medical imaging data
3. **Real-time Updates**: Live knowledge base updates from medical literature
4. **Machine Learning**: Continuous improvement of search relevance
5. **Integration**: Connection with external medical databases and APIs

### Advanced AI Features
1. **Natural Language Generation**: AI-generated summaries and recommendations
2. **Predictive Analytics**: Risk assessment and outcome prediction
3. **Personalized Recommendations**: User-specific knowledge suggestions
4. **Automated Categorization**: AI-powered content organization

## Troubleshooting

### Common Issues

#### Vector Search Not Working
- Verify MongoDB Atlas vector search index is created
- Check embedding dimensions match index configuration
- Ensure OpenAI API key is valid (or using mock embeddings)

#### Slow Search Performance
- Check vector search index configuration
- Consider reducing similarity threshold
- Verify network connectivity to MongoDB Atlas

#### Authentication Errors
- Ensure user has appropriate role (ROLE_DOCTOR or ROLE_ADMIN)
- Verify JWT token is valid and not expired
- Check security configuration for API endpoints

### Debugging
Enable debug logging by setting:
```bash
APP_ENV=dev
APP_DEBUG=true
```

Check logs in `var/log/dev.log` for detailed error information.

## Contributing

When adding new medical knowledge entries:

1. Ensure content is from authoritative medical sources
2. Include appropriate confidence and evidence levels
3. Add relevant tags and specialties for discoverability
4. Follow HIPAA compliance guidelines
5. Test search functionality with various queries

## Support

For technical support or questions about the medical knowledge base:
- Check the application logs for error details
- Verify MongoDB Atlas connection and vector search configuration
- Ensure all required environment variables are set
- Review the API documentation for endpoint usage
