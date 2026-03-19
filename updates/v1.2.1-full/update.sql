-- Update SQL for S3/R2 Features
-- Menambahkan kolom pengaturan S3 ke tabel app_settings

ALTER TABLE app_settings ADD COLUMN storage_mode ENUM('local', 's3', 'both') DEFAULT 'local' AFTER timezone;
ALTER TABLE app_settings ADD COLUMN s3_endpoint VARCHAR(255) DEFAULT NULL AFTER storage_mode;
ALTER TABLE app_settings ADD COLUMN s3_region VARCHAR(50) DEFAULT 'us-east-1' AFTER s3_endpoint;
ALTER TABLE app_settings ADD COLUMN s3_bucket VARCHAR(100) DEFAULT NULL AFTER s3_region;
ALTER TABLE app_settings ADD COLUMN s3_access_key VARCHAR(255) DEFAULT NULL AFTER s3_bucket;
ALTER TABLE app_settings ADD COLUMN s3_secret_key VARCHAR(255) DEFAULT NULL AFTER s3_access_key;
ALTER TABLE app_settings ADD COLUMN s3_directory VARCHAR(100) DEFAULT 'digisign/' AFTER s3_secret_key;
