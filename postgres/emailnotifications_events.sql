
CREATE TABLE IF NOT EXISTS emailnotifications_events (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `notification_datetime` datetime NOT NULL,
  `message_id` TEXT NOT NULL,
  `type` TEXT NOT NULL,
  `data` TEXT NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE emailnotifications_events
  ADD PRIMARY KEY (`id`);

ALTER TABLE emailnotifications_events
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


