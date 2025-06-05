-- Add start_time and end_time columns to timetable table
ALTER TABLE timetable
ADD COLUMN start_time DATETIME NOT NULL AFTER facility_id,
ADD COLUMN end_time DATETIME NOT NULL AFTER start_time; 