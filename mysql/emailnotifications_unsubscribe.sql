
CREATE TABLE IF NOT EXISTS /*_*/emailnotifications_unsubscribe (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE /*_*/emailnotifications_unsubscribe
  ADD PRIMARY KEY (`notification_id`);



