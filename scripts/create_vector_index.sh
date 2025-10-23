#!/bin/bash

# Create Vector Search Index for MongoDB Atlas
# This script provides instructions for creating the necessary vector search index
# for the RAGChatbotService to function properly

echo -e "\033[1;32m=== MongoDB Atlas Vector Search Index Setup Guide ===\033[0m"
echo -e "\033[1;34mFollow these steps to create the required vector search index:\033[0m"
echo

echo -e "\033[1;33mStep 1: Log into your MongoDB Atlas account\033[0m"
echo "Open https://cloud.mongodb.com in your browser"
echo

echo -e "\033[1;33mStep 2: Navigate to your cluster\033[0m"
echo "Select the cluster used by the SecureHealth application"
echo

echo -e "\033[1;33mStep 3: Go to the Search tab\033[0m"
echo "Click on 'Search' in the left navigation menu"
echo

echo -e "\033[1;33mStep 4: Create a new search index\033[0m"
echo "Click 'Create Index' button"
echo

echo -e "\033[1;33mStep 5: Select Vector Search\033[0m"
echo "Choose 'Vector Search' as the index type"
echo "Select the 'medical_knowledge' collection"
echo

echo -e "\033[1;33mStep 6: Configure vector search settings\033[0m"
echo "Index Name: medical_knowledge_vector_index"
echo "Dimensions: 1536 (standard for OpenAI embeddings)"
echo "Vector Field: embedding"
echo "Select 'Cosine' as similarity metric"
echo

echo -e "\033[1;33mStep 7: Review and create index\033[0m"
echo "Verify the settings and create the index"
echo

echo -e "\033[1;33mJSON Configuration Sample (for Atlas UI/API):\033[0m"
echo '
{
  "name": "medical_knowledge_vector_index",
  "type": "vectorSearch",
  "fields": [
    {
      "type": "vector",
      "path": "embedding",
      "numDimensions": 1536,
      "similarity": "cosine"
    }
  ]
}
'

echo -e "\033[1;32m=== Testing Instructions ===\033[0m"
echo "After creating the index:"
echo "1. Run the scripts/generate-knowledge.php script to seed the database"
echo "2. Test the chatbot by asking 'What is HIPAA?' or 'Explain MongoDB Queryable Encryption'"
echo

echo -e "\033[1;31mNOTE: The RAGChatbotService will not work correctly until this index is created.\033[0m"
echo "The application is configured to look for an index named 'medical_knowledge_vector_index'"