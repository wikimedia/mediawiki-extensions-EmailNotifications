
CREATE TABLE IF NOT EXISTS /*_*/emailnotifications_events (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `notification_datetime` datetime NOT NULL,
  `message_id` TINYTEXT NOT NULL,
  `type` TINYTEXT NOT NULL,
  `data` TEXT NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE /*_*/emailnotifications_events
  ADD PRIMARY KEY (`id`);

ALTER TABLE /*_*/emailnotifications_events
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


