-- --------------------------------------------------------
-- Host:                         cairnsgames.co.za
-- Server version:               10.11.15-MariaDB-cll-lve - MariaDB Server
-- Server OS:                    Linux
-- HeidiSQL Version:             12.4.0.6659
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table cairnsgamesco_tracker_incoming.device_events
CREATE TABLE IF NOT EXISTS `device_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial` varchar(32) NOT NULL,
  `imei` varchar(20) NOT NULL,
  `object_name` varchar(64) NOT NULL,
  `object_desc` varchar(128) DEFAULT NULL,
  `object_groups` text DEFAULT NULL,
  `event_at` datetime(3) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `speed` decimal(8,3) NOT NULL DEFAULT 0.000,
  `altitude` int(11) DEFAULT NULL,
  `direction` smallint(5) unsigned DEFAULT NULL,
  `started` tinyint(1) NOT NULL DEFAULT 0,
  `hardware` varchar(64) DEFAULT NULL,
  `hw_signal_level` smallint(6) DEFAULT NULL,
  `hw_message_type` smallint(6) DEFAULT NULL,
  `hw_tower` varchar(64) DEFAULT NULL,
  `hw_altitude` int(11) DEFAULT NULL,
  `raw_message` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_imei_event` (`imei`,`event_at`),
  KEY `idx_serial_event` (`serial`,`event_at`),
  KEY `idx_event_at` (`event_at`),
  KEY `idx_lat_lon` (`latitude`,`longitude`)
) ENGINE=InnoDB AUTO_INCREMENT=910 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsgamesco_tracker_incoming.device_interval
CREATE TABLE IF NOT EXISTS `device_interval` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial` varchar(32) NOT NULL,
  `imei` varchar(20) NOT NULL,
  `interval_start` datetime(3) NOT NULL,
  `interval_end` datetime(3) NOT NULL,
  `points_count` int(10) unsigned NOT NULL DEFAULT 0,
  `min_speed` decimal(8,3) DEFAULT NULL,
  `max_speed` decimal(8,3) DEFAULT NULL,
  `last_event_at` datetime(3) DEFAULT NULL,
  `last_latitude` decimal(9,6) DEFAULT NULL,
  `last_longitude` decimal(9,6) DEFAULT NULL,
  `raw_message` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_imei_interval_start` (`serial`,`imei`,`interval_start`),
  KEY `idx_interval_start` (`interval_start`),
  KEY `idx_serial_imei` (`serial`,`imei`)
) ENGINE=InnoDB AUTO_INCREMENT=615 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsgamesco_tracker_incoming.device_latest
CREATE TABLE IF NOT EXISTS `device_latest` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial` varchar(32) NOT NULL,
  `imei` varchar(20) NOT NULL,
  `object_name` varchar(64) NOT NULL,
  `object_desc` varchar(128) DEFAULT NULL,
  `object_groups` text DEFAULT NULL,
  `event_at` datetime(3) NOT NULL,
  `latitude` decimal(9,6) NOT NULL,
  `longitude` decimal(9,6) NOT NULL,
  `speed` decimal(8,3) NOT NULL DEFAULT 0.000,
  `altitude` int(11) DEFAULT NULL,
  `direction` smallint(5) unsigned DEFAULT NULL,
  `started` tinyint(1) NOT NULL DEFAULT 0,
  `hardware` varchar(64) DEFAULT NULL,
  `hw_signal_level` smallint(6) DEFAULT NULL,
  `hw_message_type` smallint(6) DEFAULT NULL,
  `hw_tower` varchar(64) DEFAULT NULL,
  `hw_altitude` int(11) DEFAULT NULL,
  `raw_message` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `serial_imei_event_at` (`serial`,`imei`,`event_at`),
  KEY `idx_imei_event` (`imei`,`event_at`) USING BTREE,
  KEY `idx_serial_event` (`serial`,`event_at`) USING BTREE,
  KEY `idx_event_at` (`event_at`) USING BTREE,
  KEY `idx_lat_lon` (`latitude`,`longitude`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsgamesco_tracker_incoming.processed
CREATE TABLE IF NOT EXISTS `processed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text NOT NULL,
  `get` text NOT NULL,
  `post` text NOT NULL,
  `headers` text NOT NULL,
  `ip_address` varchar(50) NOT NULL DEFAULT '',
  `status` varchar(50) NOT NULL DEFAULT 'processed',
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL,
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2545 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsgamesco_tracker_incoming.process_error
CREATE TABLE IF NOT EXISTS `process_error` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text NOT NULL,
  `get` text NOT NULL,
  `post` text NOT NULL,
  `headers` text NOT NULL,
  `ip_address` varchar(50) NOT NULL DEFAULT '',
  `error` varchar(500) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1795 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsgamesco_tracker_incoming.raw
CREATE TABLE IF NOT EXISTS `raw` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text NOT NULL,
  `get` text NOT NULL,
  `post` text NOT NULL,
  `headers` text NOT NULL,
  `ip_address` varchar(50) NOT NULL DEFAULT '',
  `status` varchar(50) NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2045 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
