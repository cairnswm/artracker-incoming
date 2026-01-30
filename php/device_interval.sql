-- device_interval table: one row per device per 150-second interval
CREATE TABLE IF NOT EXISTS `device_interval` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial` VARCHAR(32) NOT NULL,
  `imei` VARCHAR(20) NOT NULL,
  `interval_start` DATETIME(3) NOT NULL,
  `interval_end` DATETIME(3) NOT NULL,
  `points_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `min_speed` DECIMAL(8,3) NULL DEFAULT NULL,
  `max_speed` DECIMAL(8,3) NULL DEFAULT NULL,
  
  `last_event_at` DATETIME(3) NULL DEFAULT NULL,
  `last_latitude` DECIMAL(9,6) NULL DEFAULT NULL,
  `last_longitude` DECIMAL(9,6) NULL DEFAULT NULL,
  `raw_message` MEDIUMTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  `modified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
  PRIMARY KEY (`serial`, `imei`, `interval_start`),
  INDEX `idx_interval_start` (`interval_start`),
  INDEX `idx_serial_imei` (`serial`, `imei`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
