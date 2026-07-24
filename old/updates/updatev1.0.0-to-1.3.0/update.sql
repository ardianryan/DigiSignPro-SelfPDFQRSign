-- DigiSign Cumulative Update v1.0.0 to v1.3.3
-- Database Structure Changes

-- 1. Table users: Add signature_prefix
ALTER TABLE users ADD COLUMN IF NOT EXISTS signature_prefix VARCHAR(9) DEFAULT 'DS' AFTER signature_path;
ALTER TABLE users MODIFY signature_prefix VARCHAR(9) DEFAULT 'DS';

-- 2. Table app_settings: Add new configuration columns
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS max_upload_size_bulk INT(11) DEFAULT 52428800 AFTER max_upload_size;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS max_prefix_length INT(2) DEFAULT 3 AFTER max_upload_size_bulk;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) DEFAULT 'Asia/Jakarta' AFTER max_prefix_length;

-- 3. Table app_settings: Add S3/R2 columns
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS storage_mode ENUM('local', 's3', 'both') DEFAULT 'local';
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_endpoint VARCHAR(255) DEFAULT NULL;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_region VARCHAR(50) DEFAULT 'us-east-1';
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_bucket VARCHAR(100) DEFAULT NULL;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_access_key VARCHAR(255) DEFAULT NULL;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_secret_key VARCHAR(255) DEFAULT NULL;
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_directory VARCHAR(100) DEFAULT 'digisign/';
ALTER TABLE app_settings ADD COLUMN IF NOT EXISTS s3_public_url VARCHAR(255) DEFAULT NULL;

-- 4. Table signatures: Ensure columns exist
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS batch_id VARCHAR(50) DEFAULT NULL AFTER signature_type;
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS document_number VARCHAR(100) DEFAULT NULL AFTER document_name;
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS document_subject TEXT DEFAULT NULL AFTER document_number;
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS document_attachment VARCHAR(255) DEFAULT NULL AFTER document_subject;

-- 5. Data Initialization
INSERT IGNORE INTO app_settings (id, app_name) VALUES (1, 'DigiSign Pro');
UPDATE app_settings SET 
    max_upload_size_bulk = COALESCE(max_upload_size_bulk, 52428800),
    max_prefix_length = COALESCE(max_prefix_length, 3),
    timezone = COALESCE(timezone, 'Asia/Jakarta'),
    storage_mode = COALESCE(storage_mode, 'local'),
    s3_directory = COALESCE(s3_directory, 'digisign/')
WHERE id = 1;

-- 6. Ensure default user prefix
UPDATE users SET signature_prefix = 'DS' WHERE signature_prefix IS NULL OR signature_prefix = '';
