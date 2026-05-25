-- Add device location columns to scan_logs to store device GPS at time of scan
ALTER TABLE scan_logs
    ADD COLUMN device_lat DECIMAL(10,7) NULL AFTER event_id,
    ADD COLUMN device_lng DECIMAL(10,7) NULL AFTER device_lat,
    ADD COLUMN device_accuracy_m INT NULL AFTER device_lng;
