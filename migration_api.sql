-- migration_api.sql
-- This script creates the table for storing API keys.

CREATE TABLE `api_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `api_key` VARCHAR(255) NOT NULL,
  `project_name` VARCHAR(255) NOT NULL,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `api_key_unique` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example of how to insert a key.
-- You should generate a secure, random key for production use.
-- Example key: 'your-super-secret-api-key'
INSERT INTO `api_keys` (`api_key`, `project_name`, `is_active`) VALUES
('your-super-secret-api-key', 'External Analytics Dashboard', TRUE);
