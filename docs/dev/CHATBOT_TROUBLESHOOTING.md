# Chatbot 500 Error Troubleshooting Guide

## Current Status

The chatbot is returning a 500 Internal Server Error when processing queries. The fixes have been implemented but the error persists.

## What We Fixed

1. ✅ **Installed OpenAI PHP Client** - Package is now installed (`openai-php/client v0.17.1`)
2. ✅ **Added Error Handling** - Enhanced error handling with fallback responses
3. ✅ **Added PHI Sanitization** - Implemented HIPAA-compliant data handling
4. ✅ **Fixed Role Awareness** - Updated role-based access control

## Debugging Steps

### Step 1: Check Browser Console for Detailed Error

When you see the 500 error in the chatbot, open the browser console (F12) and look for:
- The exact error message in the response
- Any JavaScript errors
- The full response body from the `/api/chatbot/query` endpoint

### Step 2: Check PHP Error Logs

Run this command to watch for errors in real-time:

```bash
docker-compose logs -f php
```

Then trigger the chatbot query and look for any PHP errors or exceptions.

### Step 3: Test the Service Directly

Run this command to test the chatbot service:

```bash
docker-compose exec php bin/console debug:container RAGChatbotService --show-arguments
```

### Step 4: Check OpenAI API Key

The API key in `.env` appears to be invalid. To verify:

```bash
# Test the API key
curl -H "Authorization: Bearer YOUR_API_KEY" https://api.openai.com/v1/models
```

If the key is invalid, you'll need to:
1. Get a new API key from https://platform.openai.com/api-keys
2. Update the `.env` file
3. Restart the containers: `docker-compose restart`

### Step 5: Enable Symfony Debug Mode

Add this to your `.env.local` (create if it doesn't exist):

```
APP_DEBUG=1
APP_ENV=dev
```

Then clear cache:
```bash
docker-compose exec php bin/console cache:clear
```

## Expected Behavior

### With Valid API Key
The chatbot should provide AI-powered responses using OpenAI's GPT model.

### With Invalid API Key (Current State)
The chatbot should provide fallback responses for common queries:
- HIPAA questions → Static HIPAA information
- MongoDB questions → Static MongoDB information
- Patient queries → Instruction to provide more details

## Testing the Fix

After updating the API key, test with these queries:

1. **HIPAA Query**: "what is hipaa?"
   - Should return: HIPAA information (either from AI or fallback)

2. **Patient Query**: "who is Alice Williams?"
   - Should return: Request for more specific patient information

3. **MongoDB Query**: "what is queryable encryption?"
   - Should return: MongoDB encryption information

## Common Issues

### Issue 1: OpenAI Package Not Found
**Solution**: Already fixed - package is installed

### Issue 2: Invalid API Key
**Solution**: Update `.env` with valid API key from OpenAI

### Issue 3: Service Configuration
**Solution**: Service is properly configured in `config/services.yaml`

### Issue 4: Cache Not Cleared
**Solution**: Run `docker-compose exec php bin/console cache:clear`

## Next Steps

1. **Get New API Key**: Visit https://platform.openai.com/api-keys
2. **Update `.env`**: Replace the OPENAI_API_KEY value
3. **Restart Services**: Run `docker-compose restart`
4. **Test Chatbot**: Try asking "what is hipaa?" again

## Support

If the issue persists after following these steps:
1. Check the browser console for the exact error message
2. Check PHP logs with `docker-compose logs php --tail=100`
3. Verify the service is properly instantiated
4. Check if there are any missing dependencies

## Fallback Mode

The chatbot is currently designed to work even without a valid OpenAI API key by providing fallback responses for common queries. If you're still seeing 500 errors, there may be another issue with the service configuration or dependencies.
