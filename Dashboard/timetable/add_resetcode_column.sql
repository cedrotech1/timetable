-- Run this once on the live timetable-v3 database.
-- Fixes password reset (reset.php) which expects users.resetcode.

ALTER TABLE `users`
  ADD COLUMN `resetcode` varchar(20) DEFAULT NULL COMMENT 'Password reset verification code'
  AFTER `password`;
