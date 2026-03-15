-- --------------------------------------------------------
-- Host:                         cairns.co.za
-- Server version:               10.11.16-MariaDB-cll-lve - MariaDB Server
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

-- Dumping structure for table cairnsco_armanager.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `border_color` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categories_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.courses
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `name` text NOT NULL,
  `distance` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_courses_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.device
CREATE TABLE IF NOT EXISTS `device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serial` varchar(50) NOT NULL DEFAULT '0',
  `imei` varchar(50) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.device_events
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
) ENGINE=InnoDB AUTO_INCREMENT=1201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.device_interval
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
) ENGINE=InnoDB AUTO_INCREMENT=909 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.device_latest
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.events
CREATE TABLE IF NOT EXISTS `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `name` text NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `description` text DEFAULT NULL,
  `race_start_location_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_events_race_start_location_id` (`race_start_location_id`),
  KEY `idx_events_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.legs
CREATE TABLE IF NOT EXISTS `legs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `course_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `total_checkpoints` int(11) NOT NULL,
  `order_no` int(11) NOT NULL,
  `ends_at` text DEFAULT NULL,
  `special_checkpoints` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`special_checkpoints`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `route_gpx` longtext DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `distance_text` varchar(50) DEFAULT NULL,
  `altitude_gain` varchar(50) DEFAULT NULL,
  `start_location_id` int(11) DEFAULT NULL,
  `end_location_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legs_course_order` (`course_id`,`order_no`),
  KEY `idx_legs_course_id` (`course_id`),
  KEY `idx_legs_start_location_id` (`start_location_id`),
  KEY `idx_legs_end_location_id` (`end_location_id`),
  KEY `idx_legs_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.leg_checkpoints
CREATE TABLE IF NOT EXISTS `leg_checkpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leg_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `order_no` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leg_location_order` (`leg_id`,`order_no`),
  KEY `idx_leg_checkpoints_leg_id` (`leg_id`),
  KEY `idx_leg_checkpoints_location_id` (`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.leg_special_checkpoints
CREATE TABLE IF NOT EXISTS `leg_special_checkpoints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leg_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leg_special` (`leg_id`,`name`) USING HASH,
  KEY `idx_leg_special_checkpoints_leg_id` (`leg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.locations
CREATE TABLE IF NOT EXISTS `locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `short_name` varchar(100) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_locations_lat_lng` (`lat`,`lng`),
  KEY `idx_locations_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.processed
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
) ENGINE=InnoDB AUTO_INCREMENT=2836 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.process_error
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
) ENGINE=InnoDB AUTO_INCREMENT=1799 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.race
CREATE TABLE IF NOT EXISTS `race` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `data` longtext DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_race_event` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.raw
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
) ENGINE=InnoDB AUTO_INCREMENT=4316 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.special_checkpoint_events
CREATE TABLE IF NOT EXISTS `special_checkpoint_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_progress_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `occurred_at` datetime DEFAULT NULL,
  `checkpoints_before` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_scp_team_progress_id` (`team_progress_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.teams
CREATE TABLE IF NOT EXISTS `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `name` text NOT NULL,
  `bib_number` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `withdrawn` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `number` int(11) DEFAULT NULL,
  `biography` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teams_course_id` (`course_id`),
  KEY `idx_teams_category_id` (`category_id`),
  KEY `idx_teams_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.team_device
CREATE TABLE IF NOT EXISTS `team_device` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL DEFAULT 0,
  `device_id` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `device_id` (`device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.team_members
CREATE TABLE IF NOT EXISTS `team_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) DEFAULT NULL,
  `team_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `dropped_out` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_team_members_team_id` (`team_id`),
  KEY `idx_team_members_legacy_id` (`slug`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.team_messages
CREATE TABLE IF NOT EXISTS `team_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `sender` varchar(200) NOT NULL,
  `text` text NOT NULL,
  `timestamp` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_team_messages_team_id` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.team_progress
CREATE TABLE IF NOT EXISTS `team_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `leg_id` int(11) NOT NULL,
  `checkpoints_collected` int(11) DEFAULT NULL,
  `finish_time` datetime DEFAULT NULL,
  `transition_in` datetime DEFAULT NULL,
  `transition_out` datetime DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_progress_team_leg` (`team_id`,`leg_id`),
  KEY `idx_team_progress_team_id` (`team_id`),
  KEY `idx_team_progress_leg_id` (`leg_id`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.team_track
CREATE TABLE IF NOT EXISTS `team_track` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL DEFAULT 0,
  `track` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_id` (`team_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

-- Dumping structure for table cairnsco_armanager.transition_times
CREATE TABLE IF NOT EXISTS `transition_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `time` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `modified_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transition_times_team_id` (`team_id`),
  KEY `location_id` (`location_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
