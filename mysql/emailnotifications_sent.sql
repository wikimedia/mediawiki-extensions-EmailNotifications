
CREATE TABLE IF NOT EXISTS /*_*/emailnotifications_sent (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `recipients` int(11) NOT NULL,
  `text` MEDIUMTEXT NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE /*_*/emailnotifications_sent
  ADD PRIMARY KEY (`id`);

ALTER TABLE /*_*/emailnotifications_sent
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


