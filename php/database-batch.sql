CREATE TABLE IF NOT EXISTS `batch_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `run_every_seconds` int(10) unsigned NOT NULL DEFAULT 60,
  `max_execution_seconds` int(10) unsigned NOT NULL DEFAULT 60,
  `allow_parallel` tinyint(1) NOT NULL DEFAULT 0,
  `file_path` varchar(255) NOT NULL,
  `function_name` varchar(120) NOT NULL,
  `params_json` longtext DEFAULT NULL,
  `next_run_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_started_at` datetime DEFAULT NULL,
  `last_finished_at` datetime DEFAULT NULL,
  `last_status` enum('never','success','failed','timeout') NOT NULL DEFAULT 'never',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_jobs_name` (`name`),
  KEY `idx_batch_jobs_active_next` (`is_active`,`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `batch_job_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_job_id` int(11) NOT NULL,
  `status` enum('running','success','failed','timeout') NOT NULL DEFAULT 'running',
  `scheduled_at` datetime NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` bigint(20) unsigned DEFAULT NULL,
  `worker_pid` varchar(64) DEFAULT NULL,
  `output` longtext DEFAULT NULL,
  `result_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batch_job_runs_job_status_started` (`batch_job_id`,`status`,`started_at`),
  KEY `idx_batch_job_runs_status_started` (`status`,`started_at`),
  CONSTRAINT `fk_batch_job_runs_job` FOREIGN KEY (`batch_job_id`) REFERENCES `batch_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `batch_jobs` (`name`, `is_active`, `run_every_seconds`, `max_execution_seconds`, `allow_parallel`, `file_path`, `function_name`, `params_json`, `next_run_at`)
VALUES
  ('Process Hundred', 1, 10, 60, 0, 'batch/jobs.php', 'batch_job_process_hundred', '{"limit":100}', NOW()),
  ('Process Interval Worker', 1, 10, 60, 0, 'batch/jobs.php', 'batch_job_process_interval_worker', '{"minutes_window":10,"safety_minutes":1}', NOW()),
  ('Process Race Event 1', 1, 30, 120, 0, 'batch/jobs.php', 'batch_job_process_race', '{"event_id":1}', NOW())
ON DUPLICATE KEY UPDATE
  `is_active` = VALUES(`is_active`),
  `run_every_seconds` = VALUES(`run_every_seconds`),
  `max_execution_seconds` = VALUES(`max_execution_seconds`),
  `allow_parallel` = VALUES(`allow_parallel`),
  `file_path` = VALUES(`file_path`),
  `function_name` = VALUES(`function_name`),
  `params_json` = VALUES(`params_json`);
