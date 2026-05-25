-- Add geofence/location support to existing databases
-- Run this against the existing `event_attendance` database.

ALTER TABLE events
  ADD COLUMN location_lat DECIMAL(10,7) NULL AFTER event_time,
  ADD COLUMN location_lng DECIMAL(10,7) NULL AFTER location_lat,
  ADD COLUMN geofence_radius_m INT NULL AFTER location_lng;
