# GitHub Copilot Instructions for Secrets and Variables API

## Project Overview

This is a Secrets and Variables API that allows users to manage repositories, secrets, and variables. The project consists of:

- **PHP Backend API** (`/php/`) - RESTful API for managing repositories, secrets, and variables
- **JavaScript Client** (`/js/`) - Browser-based wrapper for interacting with the API

## Architecture

### Backend (PHP)
- **api.php** - Main API endpoint handler with RESTful routing
- **orm.php** - Simple ORM for database operations
- **utils.php** - Utility functions for encryption, authentication, and validation
- **config.php** - Database configuration (not in repository, see config.example.php)

### Frontend (JavaScript)
- **repo.js** - JavaScript wrapper providing async methods for all API operations
- **repo.html** - Demo/test page for the API

## Key Design Patterns

### Security
- Secrets are **encrypted** in the database (is_encrypted = 1)
- Secrets **never** return their values via GET endpoints (always null)
- API key authentication required for all endpoints (via `apikey` header)
- Each repository has a unique API key (UUID format)

### Data Model
Three main entities:
1. **Repositories** - Container for secrets/variables
2. **Secrets** - Encrypted sensitive values (passwords, tokens, etc.)
3. **Variables** - Plain-text configuration values

All entities use similar structure:
- id (primary key)
- repository_id (foreign key)
- name (identifier)
- value (actual data)
- type ("secret" or "variable")
- is_encrypted (1 for secrets, 0 for variables)
- created_at / modified_at (timestamps)

## API Conventions

### Request Headers
- `Content-Type: application/json`
- `apikey: {repository-api-key}` (for authenticated endpoints)

### Response Format
All successful responses return an array of objects, even for single-item operations:
```json
[{ "id": 1, "name": "example", ... }]
```

### Error Responses
```json
{ "error": "Error message describing the issue" }
```

## Common Operations

### Creating Resources
Use POST with JSON body containing required fields:
- Repositories require: `name`, `user_id`
- Secrets/Variables require: `repository_id`, `name`, `value`, `type`

### Updating Resources
Use PUT with partial data - only include fields to update:
- Most updates only send `value` for secrets/variables
- Repository updates can include `name`, `description`, etc.

### Retrieving Resources
GET endpoints support:
- Individual resources: `/repository/{id}`
- Collections: `/repositories/{user_id}`
- Filtered: `/repository/{id}/secrets` or `/repository/{id}/variables`
- Combined: `/repository/{id}/properties` (all secrets + variables)

## Code Style Guidelines

### PHP
- Use procedural style for route handlers
- Keep functions focused and single-purpose
- Always validate input data before database operations
- Use the ORM layer for all database access
- Set `is_encrypted` flag to 1 for secrets, 0 for variables

### JavaScript
- Use async/await for all API calls
- Provide default values from global state (globalUserId, globalRepoId)
- Always check required parameters and throw descriptive errors
- Keep the API wrapper consistent with backend endpoints

## Testing

### Manual Testing
Use `.http` files in the `/php/` directory for manual API testing with REST client extensions.

### Integration
The GitHub Actions workflow (`ftp_deploy.yml`) demonstrates real-world API usage:
- Fetching configuration from the API
- Authenticating with API key
- Using secrets and variables in CI/CD

## Common Pitfalls

1. **Don't forget encryption flag**: New secrets must set `is_encrypted` to 1
2. **Secrets are write-only**: Never expose secret values in responses
3. **Array responses**: Always return arrays, even for single items
4. **CORS headers**: All endpoints must support OPTIONS for preflight requests
5. **API key validation**: Check `apikey` header for protected endpoints

## When Adding New Features

1. **Database Changes**: Update ORM configuration in `api.php`
2. **API Endpoints**: Add route handlers following existing patterns
3. **JavaScript Wrapper**: Add corresponding methods to `REPO` object
4. **Documentation**: Update `/php/README.md` with new endpoints
5. **Type Definitions**: Update `/js/repo.md` with new TypeScript types

## Related Documentation

- API Reference: `/php/README.md`
- JavaScript Client: `/js/repo.md`
- Configuration Example: `/php/config.example.php`
