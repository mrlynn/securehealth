# SecureHealth Documentation System

This guide explains the documentation system available in SecureHealth for developers and users.

## Documentation System Overview

SecureHealth provides a comprehensive documentation system to help users understand the application's features, particularly its HIPAA compliance and MongoDB Queryable Encryption capabilities.

## Accessing Documentation

There are several ways to access documentation in SecureHealth:

### 1. Documentation Link

From any page in the application, click on the "Documentation" link in the navigation bar or footer to access the main documentation page.

### 2. Direct URLs

The documentation is available at these URLs:

- Main documentation: `/documentation.html`
- Simple docs: `/docs/simple-docs.html` 
- Individual Markdown files: `/docs/{filename}.md`

## Documentation Structure

### Main Documentation Page

The main documentation page (`/documentation.html`) provides:

- Overview of the SecureHealth application
- Links to detailed documentation on specific topics
- Search functionality
- Navigation by category

### Simple Documentation

For environments where JavaScript might be restricted, a simplified HTML-only documentation is available at `/docs/simple-docs.html`.

## Markdown-Based Content

The documentation system uses Markdown files stored in the `/public/docs/` directory. These files are:

1. Rendered in the browser using JavaScript
2. Accessible directly as raw Markdown
3. Automatically included in the navigation structure

## Adding New Documentation

To add new documentation:

1. Create a Markdown file in the `/public/docs/` directory
2. Follow the Markdown formatting conventions
3. Add a title at the top using a single `#` heading
4. Organize content with section headings (`##`, `###`)
5. Include code examples with triple backticks

Example Markdown file:

```markdown
# Feature Name

Description of the feature.

## Getting Started

Steps to use the feature.

## Code Example

```php
// Example code
$example = new Feature();
$example->doSomething();
```

## MongoDB-Independent Documentation

An important feature of the documentation system is that it works even when MongoDB is not available or disabled. This is implemented through:

1. Environment variable `MONGODB_DISABLED` that can be set to `true`
2. Fallback static content when the database is unavailable
3. Local markdown file processing that doesn't require database access

This ensures that users can access documentation even if they haven't fully configured their MongoDB connection yet.

## Technical Implementation

The documentation system is implemented using:

1. **CommonMark PHP Library**: For server-side Markdown rendering
2. **JavaScript Markdown Parser**: For client-side rendering
3. **Custom Nginx Configuration**: For serving both dynamic and static content

The Nginx configuration includes these important routes:

```nginx
# Static HTML documentation
location = /documentation.html {
    root /var/www/html/public;
    add_header Cache-Control "no-store, no-cache, must-revalidate";
}

# Simple HTML documentation alternative
location = /docs/simple-docs.html {
    root /var/www/html/public;
    add_header Cache-Control "no-store, no-cache, must-revalidate";
}

# Static documentation assets
location ~ ^/docs/.*\.(js|css|json|md)$ {
    root /var/www/html/public;
    try_files $uri =404;
    add_header Cache-Control "no-store, no-cache, must-revalidate";
}
```

## Documentation Examples

The following documentation files are available:

1. **mongodb-encryption.md**: Explains the MongoDB Queryable Encryption implementation
2. **authentication-system.md**: Details on the authentication and role-based access control
3. **hipaa-compliance.md**: Information on HIPAA compliance features
4. **api-reference.md**: API endpoints reference

## Future Improvements

The documentation system could be enhanced with:

1. Version control for documentation
2. User-specific documentation based on role
3. Interactive API documentation with examples
4. Documentation in multiple languages

## Troubleshooting

If documentation is not displaying correctly:

1. Check that Nginx is properly configured and running
2. Verify that the Markdown files exist in the `/public/docs/` directory
3. Ensure that JavaScript is enabled in your browser for dynamic rendering
4. Try the simple documentation version at `/docs/simple-docs.html` as a fallback