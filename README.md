artracker-incoming
==================

Quick guide: how to run the processing scripts (CLI / PowerShell)

**Overview**
- This repository contains CLI PHP scripts that read rows from the `raw` table, process them into `device_events`, and aggregate intervals into `device_interval`.
- Key scripts:
  - `php/process_one.php` — process the newest single row from `raw`.
  - `php/process_hundred.php` — process up to 100 newest rows from `raw`.
  - `php/process_interval_worker.php` — worker that aggregates recent `device_events` into `device_interval` (intended to run regularly).
  - `php/process_interval_backfill.php` — backfill historical `device_interval` rows from `device_events` over a date range.

**Prerequisites**
- PHP CLI installed (PHP 7.4+ / 8.x recommended).
- MySQL/MariaDB database and credentials set in `php/dbconfig.php` or via environment variables (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
- Run commands from the repository root (where this `README.md` lives).
- Example environment: XAMPP on Windows; adjust paths and service names as needed.

**Important DB notes**
- `process_one.php` and `process_hundred.php` will now insert the original row into the `processed` table (with `status = 'processed'`) and then delete the row from `raw` after successful processing.
- `process_interval_backfill.php` is idempotent after the recent change: re-running will replace interval `points_count` with the aggregated value (not add it), so backfill runs should not double-count points.

Commands (PowerShell)
- Syntax-check a script:

  ```powershell
  php -l .\php\process_one.php
  php -l .\php\process_hundred.php
  php -l .\php\process_interval_worker.php
  php -l .\php\process_interval_backfill.php
  ```

- Run `process_one` (process the newest `raw` row):

  ```powershell
  php .\php\process_one.php
  ```

- Run `process_hundred` (process up to 100 newest `raw` rows):

  ```powershell
  php .\php\process_hundred.php
  ```

- Run the interval worker (typically run regularly via cron/task scheduler):

  ```powershell
  php .\php\process_interval_worker.php
  ```

- Backfill intervals for a date range (optional):

  ```powershell
  # Backfill for 2026-01-01 full day
  php .\php\process_interval_backfill.php "2026-01-01 00:00:00" "2026-01-02 00:00:00"
  ```

  If you omit `start`/`end`, the backfill script determines min/max `event_at` in `device_events` and will process the full range in day-sized chunks.

Testing / small-scale verification
- Prefer running backfill on a small known range first to confirm results.
- Inspect `device_interval` rows after a run to confirm `points_count` matches expected aggregation from `device_events`.

Troubleshooting
- If DB connection fails, ensure `php/dbconfig.php` exists and credentials are correct, or set env vars `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` before running the script.
- Check the PHP error log or `error_log()` output if scripts write errors.

Next steps / suggestions
- Consider using a scheduler (Windows Task Scheduler or cron) to run `process_interval_worker.php` at your desired cadence.
- If you want, I can:
  - commit the new README for you,
  - add a `--dry-run` flag to `process_interval_backfill.php`, or
  - run a small-range backfill (if you provide a safe date range).

---
(copilot)