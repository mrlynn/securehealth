# Contributing to SecureHealth

Thank you for your interest in contributing to SecureHealth, a HIPAA-compliant healthcare application built with Symfony and MongoDB Queryable Encryption. This document provides guidelines for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
- [Project Structure](#project-structure)
- [Contributing Guidelines](#contributing-guidelines)
- [Security Considerations](#security-considerations)
- [Testing](#testing)
- [Documentation](#documentation)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)

## Code of Conduct

This project follows a professional code of conduct. By participating, you agree to:

- Be respectful and inclusive
- Focus on constructive feedback
- Maintain patient privacy and data security
- Follow HIPAA compliance guidelines
- Respect intellectual property rights

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Docker and Docker Compose
- MongoDB Atlas account (for production encryption keys)
- Node.js 18+ (for documentation)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/mrlynn/securehealth.git
   cd securehealth
   ```

2. **Set up environment**
   ```bash
   cp .env.example .env
   # Edit .env with your MongoDB Atlas connection string
   ```

3. **Start development environment**
   ```bash
   docker-compose up -d
   ```

4. **Install dependencies**
   ```bash
   docker-compose exec php composer install
   ```

5. **Initialize the application**
   ```bash
   docker-compose exec php bin/console app:init
   ```

## Development Environment

### Docker Setup

The project uses Docker for consistent development environments:

- **PHP Container**: Symfony application with PHP 8.2
- **MongoDB Container**: Local MongoDB instance for development
- **Nginx Container**: Web server and reverse proxy

### Environment Variables

Key environment variables for development:

```bash
# MongoDB Configuration
MONGODB_URI=mongodb://mongodb:27017/securehealth
MONGODB_DATABASE=securehealth

# Encryption Configuration (Development)
MONGO_ENCRYPTION_KEY_ID=your_key_id
MONGO_ENCRYPTION_KEY_ALT_NAME=hipaa_encryption_key

# Application Configuration
APP_ENV=dev
APP_SECRET=your_secret_key
```

### Development Tools

- **Symfony CLI**: For console commands and debugging
- **PHPUnit**: For testing
- **PHPStan**: For static analysis
- **Docusaurus**: For documentation site

## Project Structure

```
src/
├── Command/           # Symfony console commands
├── Controller/        # HTTP controllers
│   ├── Api/          # API endpoints
│   └── Web/          # Web controllers
├── Document/         # MongoDB ODM documents
├── Repository/       # Data access layer
├── Security/         # Authentication and authorization
│   ├── Voter/       # Access control voters
│   └── Authenticator/ # Custom authenticators
├── Service/          # Business logic services
└── Type/            # Form types

config/
├── packages/         # Symfony bundle configurations
├── routes/          # Route definitions
└── services.yaml    # Service definitions

public/
├── assets/          # Frontend assets
├── *.html          # Static HTML pages
└── api_*.php       # Direct PHP API endpoints

tests/
├── Controller/      # Controller tests
├── Integration/     # Integration tests
└── Security/        # Security tests
```

## Contributing Guidelines

### Branch Strategy

- **main**: Production-ready code
- **develop**: Integration branch for features
- **feature/***: Feature development branches
- **hotfix/***: Critical bug fixes

### Code Standards

1. **PHP Standards**
   - Follow PSR-12 coding standards
   - Use PHP 8.2+ features appropriately
   - Write self-documenting code with clear variable names

2. **Symfony Best Practices**
   - Use dependency injection
   - Follow Symfony naming conventions
   - Implement proper error handling

3. **Security Requirements**
   - All patient data must be encrypted
   - Implement proper access controls
   - Log all security-related events
   - Validate all user inputs

### Commit Message Format

```
type(scope): brief description

Detailed explanation of the change, including:
- What was changed
- Why it was changed
- Any security implications

Closes #issue_number
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## Security Considerations

### HIPAA Compliance

All contributions must maintain HIPAA compliance:

1. **Data Encryption**
   - Patient data must use MongoDB Queryable Encryption
   - Encryption keys must be managed securely
   - No plaintext patient data in logs

2. **Access Controls**
   - Implement role-based access control (RBAC)
   - Use Symfony Voters for fine-grained permissions
   - Audit all access attempts

3. **Data Integrity**
   - Validate all data inputs
   - Implement proper error handling
   - Maintain audit trails

### Security Checklist

Before submitting a pull request, ensure:

- [ ] No sensitive data in logs or error messages
- [ ] Proper input validation and sanitization
- [ ] Access controls implemented correctly
- [ ] Encryption used for all patient data
- [ ] Security tests pass
- [ ] No hardcoded secrets or credentials

## Testing

### Test Structure

- **Unit Tests**: Test individual components in isolation
- **Integration Tests**: Test component interactions
- **Security Tests**: Test access controls and encryption
- **Compliance Tests**: Test HIPAA compliance requirements

### Running Tests

```bash
# Run all tests
docker-compose exec php bin/phpunit

# Run specific test suite
docker-compose exec php bin/phpunit tests/Security/

# Run with coverage
docker-compose exec php bin/phpunit --coverage-html coverage/
```

### Test Requirements

- All new features must have corresponding tests
- Security-related code must have comprehensive test coverage
- Tests must pass in CI/CD pipeline
- Maintain at least 80% code coverage

## Documentation

### Documentation Standards

1. **Code Documentation**
   - Use PHPDoc for all public methods
   - Document security implications
   - Include usage examples

2. **API Documentation**
   - Document all API endpoints
   - Include request/response examples
   - Document authentication requirements

3. **User Documentation**
   - Update README.md for new features
   - Maintain troubleshooting guides
   - Document deployment procedures

### Documentation Site

The project includes a Docusaurus documentation site:

```bash
cd lab/
npm install
npm start
```

## Pull Request Process

### Before Submitting

1. **Create a feature branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**
   - Follow coding standards
   - Add appropriate tests
   - Update documentation

3. **Test your changes**
   ```bash
   docker-compose exec php bin/phpunit
   docker-compose exec php bin/console lint:yaml config/
   ```

### Pull Request Requirements

- [ ] Clear description of changes
- [ ] Reference to related issues
- [ ] All tests pass
- [ ] Code follows project standards
- [ ] Security implications documented
- [ ] Documentation updated

### Review Process

1. **Automated Checks**
   - Code style validation
   - Test suite execution
   - Security scanning

2. **Manual Review**
   - Code quality review
   - Security review
   - HIPAA compliance check

3. **Approval**
   - At least one maintainer approval required
   - Security-sensitive changes require additional review

## Issue Reporting

### Bug Reports

When reporting bugs, include:

- **Environment**: PHP version, Symfony version, MongoDB version
- **Steps to Reproduce**: Clear, numbered steps
- **Expected Behavior**: What should happen
- **Actual Behavior**: What actually happens
- **Screenshots**: If applicable
- **Logs**: Relevant error logs (sanitized)

### Feature Requests

For feature requests, provide:

- **Use Case**: Why is this feature needed?
- **Proposed Solution**: How should it work?
- **Alternatives**: Other approaches considered
- **Security Impact**: Any security considerations

### Security Issues

**DO NOT** report security vulnerabilities through public issues. Instead:

1. Email security@securehealth.dev
2. Include detailed description
3. Provide steps to reproduce
4. Wait for acknowledgment before disclosure

## Development Workflow

### Daily Development

1. **Start development environment**
   ```bash
   docker-compose up -d
   ```

2. **Check application status**
   ```bash
   docker-compose exec php bin/console app:status
   ```

3. **Run tests before committing**
   ```bash
   docker-compose exec php bin/phpunit
   ```

### Debugging

1. **Enable debug mode**
   ```bash
   # In .env
   APP_ENV=dev
   APP_DEBUG=true
   ```

2. **View logs**
   ```bash
   docker-compose logs -f php
   ```

3. **Use Symfony Profiler**
   - Available at `/_profiler` in development

## Performance Considerations

### Optimization Guidelines

- Use MongoDB indexes appropriately
- Implement caching where beneficial
- Optimize database queries
- Monitor memory usage
- Profile encryption operations

### Monitoring

- Monitor application performance
- Track encryption/decryption times
- Monitor database performance
- Alert on security events

## Deployment

### Environment-Specific Considerations

1. **Development**
   - Use local MongoDB
   - Enable debug mode
   - Use test encryption keys

2. **Staging**
   - Use MongoDB Atlas
   - Test with production-like data
   - Validate security controls

3. **Production**
   - Use MongoDB Atlas with proper security
   - Disable debug mode
   - Use production encryption keys
   - Enable monitoring and alerting

## Getting Help

### Resources

- **Documentation**: [docs.securehealth.dev](https://docs.securehealth.dev)
- **Live Demo**: [securehealth.dev](https://securehealth.dev)
- **Issues**: GitHub Issues for bug reports and feature requests
- **Discussions**: GitHub Discussions for questions and ideas

### Community Guidelines

- Be respectful and professional
- Help others learn and grow
- Share knowledge and best practices
- Maintain focus on healthcare security

## License

By contributing to SecureHealth, you agree that your contributions will be licensed under the same license as the project.

## Acknowledgments

Thank you to all contributors who help make SecureHealth a secure, compliant, and useful healthcare application. Your contributions help improve healthcare data security and patient privacy.

---

**Remember**: This is a healthcare application handling sensitive patient data. Always prioritize security, compliance, and patient privacy in your contributions.
