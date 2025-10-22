# MongoDB Queryable Encryption Search Interface

## Overview

This document describes the MongoDB Queryable Encryption search interface that demonstrates encrypted search capabilities in a HIPAA-compliant medical records system. The interface showcases three types of encrypted searches:

1. **Equality Search** - Exact matches using deterministic encryption
2. **Range Search** - Range queries using range encryption  
3. **Complex Search** - Multi-field searches combining different encryption types

## Features

### 🔒 Encryption Types Demonstrated

- **Deterministic Encryption**: Enables exact match searches on encrypted fields
- **Range Encryption**: Enables range queries on encrypted numeric/date fields
- **Standard Encryption**: Maximum security for highly sensitive data

### 🔍 Search Capabilities

- **Equality Search**: Find patients by exact matches on lastName, firstName, email, or phone
- **Range Search**: Find patients by birth date ranges or age ranges
- **Complex Search**: Combine multiple encrypted field searches in a single query

### 📊 Real-time Statistics

- Search execution time
- Number of encrypted fields queried
- Total results returned
- Query type and encryption method used

## File Structure

```
public/
├── queryable-encryption-search.html    # Main search interface
└── ...

src/Controller/Api/
├── QueryableEncryptionSearchController.php  # Search API endpoints
├── HealthController.php                     # Health check endpoint
└── ...

src/Repository/
└── PatientRepository.php                    # Extended with search methods
```

## API Endpoints

### Search Endpoints

#### Equality Search
```http
POST /api/encrypted-search/equality
Content-Type: application/json

{
    "lastName": "Smith",
    "firstName": "John",
    "email": "john.smith@example.com",
    "phone": "555-123-4567"
}
```

**Response:**
```json
{
    "success": true,
    "searchType": "equality",
    "criteria": {...},
    "results": [...],
    "totalResults": 5,
    "searchTime": 45.2,
    "encryptedFields": ["lastName", "firstName", "email", "phoneNumber"],
    "encryptionType": "deterministic"
}
```

#### Range Search
```http
POST /api/encrypted-search/range
Content-Type: application/json

{
    "birthDateFrom": "1980-01-01",
    "birthDateTo": "1990-12-31",
    "minAge": 30,
    "maxAge": 50
}
```

#### Complex Search
```http
POST /api/encrypted-search/complex
Content-Type: application/json

{
    "lastName": "Smith",
    "email": "gmail.com",
    "minAge": 25,
    "phonePrefix": "555",
    "birthYear": 1985
}
```

### Capabilities Endpoint

```http
GET /api/encrypted-search/capabilities
```

**Response:**
```json
{
    "searchTypes": {
        "equality": {
            "description": "Exact match searches using deterministic encryption",
            "supportedFields": ["lastName", "firstName", "email", "phoneNumber"],
            "encryptionType": "deterministic"
        },
        "range": {
            "description": "Range queries using range encryption",
            "supportedFields": ["birthDate"],
            "encryptionType": "range"
        },
        "complex": {
            "description": "Multi-field searches combining different encryption types",
            "supportedFields": ["lastName", "email", "birthDate", "phoneNumber"],
            "encryptionTypes": ["deterministic", "range"]
        }
    },
    "fieldEncryptionMap": {
        "lastName": "deterministic",
        "firstName": "deterministic",
        "email": "deterministic",
        "phoneNumber": "deterministic",
        "birthDate": "deterministic",
        "ssn": "random",
        "diagnosis": "random",
        "medications": "random",
        "insuranceDetails": "random",
        "notes": "random"
    }
}
```

## Implementation Details

### Frontend (HTML/JavaScript)

The search interface is built with:
- **Bootstrap 5** for responsive design
- **Font Awesome** for icons
- **Modern CSS** with gradients and animations
- **Vanilla JavaScript** for API interactions

Key features:
- Tabbed interface for different search types
- Real-time search statistics
- Authentication checking
- Error handling and loading states
- Responsive design for full-screen usage

### Backend (Symfony/PHP)

The backend implementation includes:

#### QueryableEncryptionSearchController
- Handles three types of encrypted searches
- Validates input criteria
- Performs encrypted queries
- Returns structured responses with metadata
- Logs all search activities for audit purposes

#### PatientRepository Extensions
- `findByEqualityCriteria()` - Deterministic encryption searches
- `findByRangeCriteria()` - Range encryption searches  
- `findByComplexCriteria()` - Multi-field encrypted searches
- `getSearchStats()` - Collection statistics and monitoring

### Encryption Service Integration

The search functionality integrates with `MongoDBEncryptionService`:

```php
// Encrypt search criteria
$encryptedLastName = $encryptionService->encrypt('patient', 'lastName', $criteria['lastName']);

// Perform encrypted query
$cursor = $collection->find(['lastName' => $encryptedLastName]);
```

## Security Considerations

### Data Protection
- All sensitive data is encrypted at rest, in transit, and in use
- Search operations work on encrypted data without server-side decryption
- Only authenticated users can perform searches
- All search activities are logged for audit purposes

### Encryption Types
- **Deterministic**: Same input always produces same encrypted output
- **Range**: Enables comparison operations on encrypted data
- **Random**: Maximum security for highly sensitive data (no search)

### Access Control
- Role-based access to patient data
- Doctors see all data, nurses see medical data (no SSN), receptionists see basic info only
- Authentication required for all search operations

## Usage Instructions

### 1. Access the Interface
Navigate to `/queryable-encryption-search.html` in your browser.

### 2. Authentication
The interface will check if you're logged in. If not, you'll be prompted to log in first.

### 3. Choose Search Type
Select from three tabs:
- **Equality Search**: Exact matches on encrypted fields
- **Range Search**: Range queries on encrypted date fields
- **Complex Search**: Multi-field searches

### 4. Enter Search Criteria
Fill in the relevant fields for your chosen search type.

### 5. Execute Search
Click the search button or press Enter to perform the encrypted search.

### 6. View Results
Results are displayed with:
- Patient information (role-filtered)
- Search statistics
- Encryption indicators
- Execution time

## Testing

### Manual Testing
1. Create test patients with various data
2. Test each search type with different criteria
3. Verify encryption indicators are shown
4. Check search statistics are accurate
5. Test error handling with invalid input

### API Testing
```bash
# Test equality search
curl -X POST http://localhost:8080/api/encrypted-search/equality \
  -H "Content-Type: application/json" \
  -d '{"lastName": "Smith"}'

# Test range search  
curl -X POST http://localhost:8080/api/encrypted-search/range \
  -H "Content-Type: application/json" \
  -d '{"minAge": 30, "maxAge": 50}'

# Test capabilities
curl http://localhost:8080/api/encrypted-search/capabilities
```

## Performance Considerations

### Optimization
- Indexes on encrypted fields for faster searches
- Caching of encryption keys
- Efficient query construction
- Pagination for large result sets

### Monitoring
- Search execution times
- Number of encrypted fields queried
- Result set sizes
- Error rates

## Troubleshooting

### Common Issues

1. **Authentication Required**: Make sure you're logged in
2. **No Results**: Check your search criteria
3. **Search Failed**: Check server logs for errors
4. **Slow Performance**: Consider adding indexes

### Debug Information
- Check browser console for JavaScript errors
- Check server logs for PHP errors
- Verify MongoDB connection and encryption setup
- Test API endpoints directly with curl

## Future Enhancements

### Planned Features
- Prefix/suffix search support (MongoDB 8.2+)
- Substring search capabilities
- Advanced filtering options
- Export search results
- Search history and saved searches

### Performance Improvements
- Query result caching
- Index optimization
- Parallel search execution
- Real-time search suggestions

## Related Documentation

- [MongoDB Queryable Encryption Documentation](https://www.mongodb.com/docs/manual/core/queryable-encryption/)
- [HIPAA Compliance Guide](./hipaa-compliance.md)
- [MongoDB Encryption Guide](./mongodb-encryption-guide.md)
- [API Documentation](./api-documentation.md)
