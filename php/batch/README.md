# Batch Processing Guide

This folder contains the batch runtime for scheduled processing.

## Files

- `php/batch.php` — scheduler tick entrypoint (run every 10 seconds from Task Scheduler or cron).
- `php/batch/manager.php` — due-job selection, concurrency checks, and worker dispatch.
- `php/batch/run_job.php` — executes one queued run and writes run logs.
- `php/batch/jobs.php` — callable batch functions.
- `php/batch/api.php` — HTTP API to create/update jobs and read jobs/runs.
- `php/database-batch.sql` — DB schema + sample jobs.

## Setup

1. Apply base schema from `php/database.sql`.
2. Apply batch schema from `php/database-batch.sql`.
3. Configure DB credentials in `php/dbconfig.php` (or env vars).
4. Schedule this command every 10 seconds:

```powershell
php .\php\batch.php
```

## How it works

- Each row in `batch_jobs` defines:
  - frequency (`run_every_seconds`)
  - max runtime (`max_execution_seconds`)
  - concurrency (`allow_parallel`)
  - callable target (`file_path`, `function_name`, `params_json`)
- Every scheduler tick (`batch.php`) checks due jobs (`next_run_at <= NOW()`).
- If `allow_parallel = 0`, a new run is skipped while one is still `running`.
- Stale `running` rows over max runtime are marked `timeout`.
- Executions are logged in `batch_job_runs` with status, duration, output, result, and errors.
- If a run was missed, the next tick will run it because `next_run_at` is still in the past.

## API quick usage

- Upsert job: `POST /php/batch/api.php?action=upsert_job`
- List jobs: `GET /php/batch/api.php?action=list_jobs`
- List runs: `GET /php/batch/api.php?action=list_runs&job_id=1&limit=20`

Ready-to-run examples are in `php/batch/.http`.
