# Example Environment Configuration

This document provides an example configuration for the `.env.local` file used in production environments.

## Example `.env.local` File

```dotenv
###> symfony/framework-bundle ###
# Use 'prod' in production environments
APP_ENV=prod
# Generate a secure random string for the APP_SECRET
APP_SECRET=change_this_to_a_secure_random_string
###< symfony/framework-bundle ###

###> doctrine/mongodb-odm-bundle ###
# Replace with your actual MongoDB connection string
# Format: mongodb://username:password@host:port/database?authSource=admin&replicaSet=rs0
MONGODB_URL=mongodb://app_user:strong_password_here@mongodb.example.com:27017
MONGODB_DB=securehealth
###< doctrine/mongodb-odm-bundle ###

# Optional: Configure logging
LOG_LEVEL=info

# Optional: Configure trusted proxies for proper client IP detection
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8

# Optional: Configure CORS for API access
CORS_ALLOW_ORIGIN=^https?://(localhost|example\.com)(:[0-9]+)?$
```

## Production Environment Variables

In a production environment, ensure these environment variables are set securely:

1. **App Secret**: Generate a unique, random string for `APP_SECRET`
2. **Database Credentials**: Use strong passwords for MongoDB access
3. **Connection String**: Use a fully qualified domain name for MongoDB hosts
4. **Trusted Proxies**: Configure correctly if behind a reverse proxy or load balancer

## Security Considerations

1. **Do Not Commit Secrets**: Never commit `.env.local` to version control
2. **Use Environment Variables**: For maximum security, set values directly as environment variables
3. **Principle of Least Privilege**: Database users should have minimal required permissions
4. **Rotate Credentials**: Regularly change passwords and secrets

## Different Environments

### Development

```dotenv
APP_ENV=dev
APP_SECRET=dev_secret_do_not_use_in_production
MONGODB_URL=mongodb://app_user:app_password@mongodb:27017
MONGODB_DB=securehealth_dev
```

### Testing

```dotenv
APP_ENV=test
APP_SECRET=test_secret_do_not_use_in_production
MONGODB_URL=mongodb://app_user:app_password@mongodb:27017
MONGODB_DB=securehealth_test
```

### Staging

```dotenv
APP_ENV=prod
APP_SECRET=staging_secret_change_in_production
MONGODB_URL=mongodb://app_user:staging_password@mongodb.staging.example.com:27017
MONGODB_DB=securehealth_staging
```

## Configuration Management

For production deployments, consider using:

1. **Docker Secrets**: For containerized deployments
2. **Kubernetes Secrets**: For Kubernetes deployments
3. **AWS Parameter Store/Secrets Manager**: For AWS deployments
4. **HashiCorp Vault**: For centralized secret management

These methods provide more secure ways to manage sensitive configuration values compared to environment files.

## Environment Validation

The application validates required environment variables during startup. If any required variables are missing, the application will fail to start with a clear error message.

## Adding New Environment Variables

When adding new configuration options:

1. Add them to `.env` with default development values
2. Document them in this file
3. Update deployment documentation
4. Consider if they should be included in environment validation checks