CREATE TABLE IF NOT EXISTS `#__splaskscore_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int unsigned NOT NULL DEFAULT 0,
  `token_hash` char(64) NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade_key` varchar(32) NOT NULL,
  `grade_label` varchar(64) NOT NULL,
  `status_label` varchar(64) NOT NULL,
  `verification_url` varchar(2048) NOT NULL DEFAULT '',
  `source_checked_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_splaskscore_history_lookup` (`module_id`, `token_hash`, `created_at`),
  KEY `idx_splaskscore_history_source` (`module_id`, `token_hash`, `source_checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
