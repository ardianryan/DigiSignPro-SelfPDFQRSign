-- Update SQL for v1.3.2
-- Add S3 columns if not exists (already handled by auto-migration in settings.php)
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_public_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_directory VARCHAR(255) DEFAULT 'digisign/';
