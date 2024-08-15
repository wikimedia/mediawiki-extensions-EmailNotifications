
CREATE TABLE IF NOT EXISTS emailnotifications_notifications (
  `id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `groups` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` int(11) NOT NULL,
  `subject` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` TEXT NOT NULL,
  `enabled` TINYINT(1) NOT NULL default 1,
  `must_differ` TINYINT(1) NOT NULL default 1,
   `skip_strategy` enum('contains', 'does not contain', 'regex') NULL default 'contains',
  `skip_text` TEXT NULL,
  `updated_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE emailnotifications_notifications
  ADD PRIMARY KEY (`id`);

ALTER TABLE emailnotifications_notifications
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


