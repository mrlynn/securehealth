# Security Guidelines for SecureHealth

## 🔐 Environment Variables Security

This document outlines security best practices for managing sensitive configuration data in SecureHealth.

### ⚠️ Never Commit Secrets

**CRITICAL**: Never commit files containing secrets to version control. The following files contain sensitive information and are automatically ignored by git:

- `.env` - Contains actual secrets and credentials
- `.env.local` - Local overrides
- `docker/encryption.key` - Encryption keys
- Any files with passwords, API keys, or connection strings

### 🛡️ Secure Setup Process

#### Option 1: Automated Setup (Recommended)

```bash
# Run the interactive setup script
./setup-env.sh
```

This script will:
- Create a `.env` file from the template
- Prompt for your MongoDB credentials
- Generate secure random keys for JWT and app secrets
- Ensure proper file permissions

#### Option 2: Manual Setup

1. **Copy the template:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` with your actual values:**
   ```bash
   nano .env  # or your preferred editor
   ```

3. **Generate secure secrets:**
   ```bash
   # Generate app secret
   openssl rand -hex 32
   
   # Generate JWT secret
   openssl rand -hex 32
   
   # Generate JWT passphrase
   openssl rand -hex 16
   ```

### 🔒 Environment Variables Reference

| Variable | Description | Example |
|----------|-------------|---------|
| `MONGODB_URI` | MongoDB connection string with credentials | `mongodb+srv://user:pass@cluster.mongodb.net/db` |
| `MONGODB_DB` | Database name | `securehealth` |
| `APP_SECRET` | Application secret key (32+ chars) | `a1b2c3d4e5f6...` |
| `JWT_SECRET_KEY` | JWT signing key (32+ chars) | `x1y2z3a4b5c6...` |
| `JWT_PASSPHRASE` | JWT passphrase (16+ chars) | `secret123456789` |

### 🚨 Security Checklist

Before deploying or sharing your code:

- [ ] `.env` file is not tracked by git
- [ ] `.env.example` contains placeholder values only
- [ ] All secrets are generated using cryptographically secure methods
- [ ] Database credentials use least-privilege access
- [ ] Encryption keys are stored securely
- [ ] No hardcoded secrets in source code
- [ ] Environment variables are properly scoped

### 🔧 Docker Security

The Docker Compose configuration uses environment variables to avoid hardcoding secrets:

```yaml
services:
  php:
    env_file:
      - .env
    environment:
      - MONGODB_URI=${MONGODB_URI}
      - MONGODB_URL=${MONGODB_URL}
      # ... other variables
```

### 🛠️ Development vs Production

#### Development
- Use `.env` for local development
- Generate random secrets for testing
- Use MongoDB Atlas free tier or local MongoDB

#### Production
- Use environment variables from your hosting platform
- Use MongoDB Atlas production cluster
- Rotate secrets regularly
- Monitor access logs
- Use HTTPS/TLS for all connections

### 📋 Incident Response

If secrets are accidentally committed:

1. **Immediately rotate all exposed credentials**
2. **Remove from git history** (if possible)
3. **Audit access logs** for unauthorized access
4. **Update all affected systems**
5. **Review and strengthen security practices**

### 🔍 Security Monitoring

Regular security practices:

- Monitor MongoDB access logs
- Review application logs for suspicious activity
- Keep dependencies updated
- Regular security audits
- Test encryption/decryption functionality
- Verify RBAC permissions

### 📞 Support

For security-related questions or to report vulnerabilities:

- Review the documentation: `/documentation.html`
- Check the API reference for security endpoints
- Test with the provided demo users
- Use the audit logging features to monitor access

---

**Remember**: Security is everyone's responsibility. When in doubt, ask for help rather than taking shortcuts.
