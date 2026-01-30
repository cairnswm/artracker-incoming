# Copilot Instructions — Project Structure & Guidance

This file describes the structure of the `artracker-incoming` repository and provides guidance for Copilot-style assistants and contributors when modifying or adding code.

## Repository Overview

- Name: `artracker-incoming`
- Purpose: Incoming HTTP endpoint(s) implemented in PHP for tracking / processing incoming requests. Core logic lives in the `php/` directory.

## Project Structure

```
php/
  corsheaders.php        # CORS header helper (sets CORS-related response headers)
  dbconfig.php          # Database configuration values (should not store secrets in repo)
  dbconnection.php      # DB connection helper (mysqli)
  getguid.php           # Utility to generate/return GUIDs
  in.php                # Main incoming endpoint handler
  utils.php             # Shared helper functions
```

Root-level files are minimal; most application code and endpoints are under `php/`.

## File Descriptions

- `php/corsheaders.php`: Responsible for emitting appropriate CORS headers for the endpoint(s). Keep it minimal and idempotent.
- `php/dbconfig.php`: Loads DB connection configuration. In the repository this may contain example values — **do not commit real credentials**. Prefer environment variables or an out-of-repo config for secrets.
- `php/dbconnection.php`: Establishes and returns a database connection (using mysqli). Centralize error handling here.
- `php/getguid.php`: Exposes a GUID generator helper used by endpoints that need unique identifiers.
- `php/in.php`: The main HTTP endpoint that receives incoming requests and coordinates validation, DB work, and responses.
- `php/utils.php`: Generic helpers used across endpoints (input validation, sanitization, small utilities).

## Coding & Safety Guidelines (for Copilot and contributors)

- Language: PHP (maintain compatibility with the project's target PHP version; if unknown, aim for PHP 7.4+ / 8.x compatibility).
- Security: Always validate and sanitize external input. Use prepared statements (mysqli with parameter binding) for all DB queries to prevent SQL injection - prefer executeSQL from dbconnection.php.
- Secrets: Never hardcode passwords, API keys, or other secrets into committed files. config goes in /php/dbconfig.php.
- Error Handling: Log internal errors server-side and return minimal, non-sensitive error messages to clients.
- CORS: use /php/corsheaders.php.
- Dependencies: do not use composser.
- Code style: Keep code clear and consistent. Follow PSR-12 where practical for new PHP files.

## When Adding or Modifying Files

- Place PHP endpoint and helper code in `php/`.
- If adding a new route or endpoint file, add a short description at the top of the file explaining its purpose.
- Add small, focused commits with descriptive messages. Example: `php: add GUID helper` or `fix(db): use prepared statements for insert`.

## Testing & Verification

- There are no automated tests in the repo by default. When adding features, include simple manual verification steps in the PR description.
- For DB changes, provide sample SQL and expected test data to reproduce behavior safely.

## Pull Requests & Commits

- Commit messages: Describe what changed, why, and any manual verification steps.

---

If you (Copilot) are asked to generate code for this repository, follow the guidance above: keep changes small, secure, and documented, and place code in the `php/` directory unless a new top-level file is justified.
