ALTER TABLE /*_*/emailnotifications_notifications
  ADD `skip_strategy` enum('contains', 'does not contain', 'regex') NULL default 'contains' AFTER `must_differ`;
