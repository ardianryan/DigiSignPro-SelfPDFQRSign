-- Update SQL for v1.3.0
-- Menambahkan kolom S3 Public URL ke tabel app_settings

ALTER TABLE app_settings ADD COLUMN s3_public_url VARCHAR(255) DEFAULT NULL AFTER s3_endpoint;
